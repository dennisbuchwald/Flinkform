# Tests

## PHP unit tests

Standalone, no PHPUnit. Each exits 0 on success, 1 on failure.

    php tests/rule-evaluator-date-test.php
    php tests/rule-groups-test.php
    node tests/rule-groups-parity.mjs
    node tests/visibility-parity.mjs
    node tests/group-validation.mjs
    php tests/mail-body-test.php
    php tests/consent-empty-step-test.php
    php tests/field-select-render-test.php
    php tests/field-address-render-test.php
    php tests/notice-render-test.php
    php tests/condition-initial-state-test.php
    php tests/mailer-sender-test.php
    php tests/script-translations-test.php
    php tests/default-strings-test.php
    php tests/asset-version-test.php

`rule-evaluator-date-test.php` covers the `date_before` / `date_on_or_after`
operators, including the guards against empty and malformed values.

`rule-groups-test.php` covers nested condition groups on the server: the
motivating "(A or B or C) and D" case, mixed nesting both ways, empty and
malformed groups, the depth ceiling, and that a flat rule set saved before
groups existed still behaves and still serialises identically.

`rule-groups-parity.mjs` is the one that matters most. The browser decides
what a visitor sees, the server decides what is stored — if the two
evaluators disagree, a field hides in the browser and submits anyway, or the
reverse, and neither failure announces itself. So it runs one shared table
(`rule-groups-cases.json`) through both implementations and compares the
verdicts, rather than testing each half against its own expectations. That
shared table is why the browser evaluator lives in `src/shared/rule-evaluator.js`
rather than inside view.js: Node can import it directly.

`visibility-parity.mjs` is the second parity harness, for the resolution of
which fields are hidden. Visibility depends on values and values depend on
visibility, so both halves iterate to a fixed point — and if they iterate
differently (a different pass cap, a different entry order, an accumulating
set on one side) they reach different answers for cascades. Cases with
`expected: null` are contradictory on purpose: there is no single right
answer, only the requirement that both sides pick the same one.

`consent-empty-step-test.php` pins the server half of the "empty first
section" report: a multi-step form whose first section holds no fields
(or only a heading) must still map every field to the right step, keep
the consent's required-by-default flag (Gutenberg drops attributes that
equal the block.json default), and resolve nothing as hidden when no
conditions exist — so the required error reaches the visitor no matter
what the browser let through.

`field-select-render-test.php` pins the select's leading empty option: a
single select with no placeholder used to preselect its first real option,
which made required unenforceable and tripped conditions on page load
(surfaced by a radio→select block transform).

`group-validation.mjs` pins the one validation rule we write ourselves.
"Tick at least one of these" cannot be expressed in HTML, so the group
check is hand-written — and therefore has to reproduce what the browser
applies everywhere else for free: a disabled control is barred from
validation and can never block a submit. It did not, so a hidden group
belonging to the other branch of an either/or form silently blocked the
whole form. The tests cover both directions: hidden groups must never
block, visible empty ones always must.

`mail-body-test.php` covers the notification body. Mostly about what the
mail leaves out — a hidden or empty field must not surface as a blank row —
plus that it never prints an internal field name, formats dates and links
the way a person expects, and escapes everything.

`field-address-render-test.php` renders the address block against every label
position and asserts the label/placeholder contract. Background: 1.6.0 shipped
hard-coded placeholders on the sub-inputs, which print straight through a
floating label because the label rests *inside* the input while it is empty.
It also pins the "line 2 is never required" rule and the conditional-logic
forwarding onto the fieldset.

`condition-initial-state-test.php` covers `Wrapper::condition_attributes()`,
which decides whether a conditional block starts hidden. The server has to
reach the same verdict the client evaluator would from the same values — if
the two drift, the load-time flash comes back, just in the other direction.
It pins the awkward inputs in particular: a missing key and an empty string
must behave identically, because the browser reports an untouched input as
an empty string while the server simply has no entry for it.

`mailer-sender-test.php` covers the per-form sender and the Reply-To
headers. The sender goes through the `wp_mail_from` filters rather than a
`From:` header, because wp_mail() runs a header-derived address through
that filter afterwards and an SMTP plugin hooked there would silently
overrule the form. The test pins both halves of that: the form-level value
wins, and the filters are gone again once the mail is out, so no other
plugin's mail inherits them.

`script-translations-test.php` guards the editor's translations. WordPress
locates a JED file by hashing the script path relative to the plugin folder,
so a name that is off by one character fails silently — the editor simply
stays English, which is how it went unnoticed until 1.8.1. The test
recomputes the name the way core does, asserts a non-empty file exists for
every editor bundle, and checks that the Registry passes a languages path at
all (without one, core only searches WP_LANG_DIR/plugins).

`default-strings-test.php` keeps three copies of the same literals in
sync: the English block.json defaults, the PHP map that translates them at
render time, and the editor helper that does the same for the preview. A
drift between them is silent — the label just stays English — so the test
compares all three and checks each default is a real msgid.

`asset-version-test.php` guards cache-busting. The `?ver=` on every block
asset comes from block.json, which sat at 0.1.0 from the first commit, so
for thirty-odd releases every stylesheet had the same URL and browsers kept
their first copy. The failure is invisible server-side — the file on disk is
correct, only the URL never moves — so the test pins that the Registry
stamps the plugin version over it, and that the version itself is in sync
across the plugin header, the constant and the readme.

`notice-render-test.php` covers the Notice block. It submits nothing, so the
risk is output rather than validation: an unknown variant must not reach the
class attribute, an empty note must not render as a bare coloured strip, and
the message must be filtered down to inline formatting only.

# Frontend smoke tests

## module-smoke.html

Loads the BUILT form-container view module (`build/form-container/view.js`)
in a real browser with a stubbed `@wordpress/interactivity` import and a
minimal form + spam block. Catches module-evaluation errors (TDZ, syntax,
broken imports) that `npm run build` does NOT catch — webpack only checks
syntax, it never executes the module.

Background: 1.4.0 shipped a const declared below the module's init block.
ES modules are deferred, the init ran during evaluation, hit the temporal
dead zone, and the ReferenceError aborted the whole module — spam solver
included, silently rejecting every submission. This file is the test that
would have caught it.

Run after every build, before every deploy:

    npm run build
    python3 -m http.server 8737 &
    open http://localhost:8737/tests/module-smoke.html

Expected on the page/console: `window.__result.loaded === true` and the
hidden spam-solution input fills with a number within ~1 second.

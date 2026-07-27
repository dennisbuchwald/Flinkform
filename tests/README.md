# Tests

## PHP unit tests

Standalone, no PHPUnit. Each exits 0 on success, 1 on failure.

    php tests/rule-evaluator-date-test.php
    php tests/field-address-render-test.php
    php tests/notice-render-test.php
    php tests/condition-initial-state-test.php

`rule-evaluator-date-test.php` covers the `date_before` / `date_on_or_after`
operators, including the guards against empty and malformed values.

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

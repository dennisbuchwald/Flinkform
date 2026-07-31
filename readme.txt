=== Flinkform - Forms for the Block Editor ===
Contributors: dbwmediadennis
Tags: forms, contact form, form builder, conditional logic, block editor
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block-native form builder for the WordPress Block Editor — theme.json styling, multi-step forms, conditional logic, Interactivity API.

== Description ==

Flinkform is a form builder that lives entirely inside the WordPress Block Editor. Forms are composed from native blocks (`block.json` v3), styled through `theme.json` design tokens, and powered by the Interactivity API — no separate admin UI, no shortcodes, no jQuery.

= How it works =

* **Block Editor native** — forms are built with `block.json` and the Interactivity API, directly inside the editor
* **theme.json styling** — forms inherit your theme's typography, colours and spacing automatically
* **Modern stack** — WordPress 6.5+, PHP 8.1+, no jQuery, frontend JS under 15 KB gzipped
* **Multi-step forms** — split long forms into steps with a Page Break block, included in the free core
* **Conditional logic** — show/hide fields based on user input, included in the free core
* **WCAG 2.1 AA** — full keyboard navigation, screen-reader compatible, aria-live announcements
* **Privacy by design** — no external services, no tracking cookies, no IP tracking — everything stays on your server

= Features (free core) =

**Form building**
* 14 field types: Text, Email, Textarea, Number, Date, URL, Phone, Select, Radio, Checkbox, Toggle, Hidden, Consent, Address
* Composite Address field: street, postal code and city in a compact grid, with optional address line 2 and country
* Dedicated Consent field for privacy-policy agreement
* Notice block: a highlighted note between fields (info, success, warning, important) — pair it with conditional logic to surface guidance only when it applies
* Section Heading and Page Break blocks for structuring longer forms
* Multi-step forms with Page Break block, per-step validation and progress indicator (bar, dots or numbers)
* Conditional logic — show/hide fields, skip steps, gate the submit button, with nestable groups for "(A or B) and C"
* Two-column layout with per-field full-width override

**Styling**
* Automatic theme.json inheritance (colours, typography, spacing, border radius)
* Style panel: primary colour, field style (bordered/soft/underline/minimal), label position (above/beside/floating/placeholder), submit button style (fill/outline/ghost)

**Notifications**
* Admin notification email on every submission (configurable recipient, merge tags)
* Optional confirmation email to the submitter
* Sender name and address per form, with a Reply-To for each email — send from your own address without an SMTP plugin
* Sends through your site's standard WordPress mail (`wp_mail`)

**Spam protection**
* Always-on honeypot + signed time-based check (zero configuration)
* Built-in proof-of-work challenge with accessible math fallback for visitors without JavaScript
* No external service, no API keys, no tracking cookies, 100% GDPR-friendly

**After submission**
* Success message or redirect to a custom thank-you URL (with open-redirect protection)
* Optional submission ID and form ID query parameters for conversion tracking (GA4, Meta Pixel, Plausible, etc.)

**Admin**
* Submissions list with search, filter by form, sort, bulk actions
* Single-submission detail view with all field labels and values
* Mark as read/unread
* Per-form data retention with automatic daily purge

== Installation ==

1. Upload the `flinkform` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Open any page or post in the Block Editor
4. Insert the **Form** block (search for "Flinkform" or "Form")
5. Add fields, configure settings in the block inspector, publish — done

== Frequently Asked Questions ==

= Is Flinkform free? =

Yes. Flinkform is GPLv2-licensed and completely free — including multi-step forms and conditional logic. Everything you need to build and run real forms is in the core.

= What WordPress version do I need? =

WordPress 6.5 or higher and PHP 8.1 or higher. Flinkform uses modern WordPress APIs (Interactivity API, block.json v3, viewScriptModule) that are not available in older versions.

= Does Flinkform work with my theme? =

Yes. Flinkform reads your theme's design tokens from `theme.json` and inherits colours, typography, spacing and border radius automatically. Forms look native on any modern WordPress theme — tested with GeneratePress, Twenty Twenty-Five, Astra and Kadence.

= Does Flinkform support multi-step forms? =

Yes, in the free core. Insert a **Page Break** block between fields to split the form into steps, choose a progress indicator style (bar, dots or numbers), and benefit from per-step validation. Steps can even be skipped conditionally based on earlier answers.

= How does the spam protection work? =

Flinkform uses a layered approach that requires no setup:

1. **Honeypot** — a hidden field that bots fill in but humans never see
2. **Signed time check** — submissions faster than a couple of seconds after page load are rejected; the timestamp is cryptographically signed so bots cannot forge it
3. **Proof-of-work challenge** — the visitor's browser solves a small computational puzzle in the background; visitors without JavaScript get a simple math question instead

No external service is contacted. No tracking cookies are set. No personal data is shared.

= Is Flinkform GDPR-compliant? =

Flinkform is designed with privacy by default — see the Privacy section below for the full detail. In short: no IP addresses or user-agent strings are stored, no data ever leaves your server, no external spam service is used, and Flinkform integrates with WordPress's privacy tools for data-subject access and erasure requests.

= My notification emails don't arrive. What can I do? =

Email deliverability depends on your host. Many hosts send `wp_mail()` unreliably. If your notifications don't arrive, install a dedicated SMTP plugin to route mail through a proper provider — it will handle delivery for Flinkform too.

= Can I redirect to a thank-you page after submission? =

Yes. In the block inspector's "After Submit" panel, choose "Redirect to URL" and enter your thank-you page URL (validated against open redirects). Optionally append the submission ID and form ID as query parameters for conversion tracking.

== Screenshots ==

1. Block Editor — building a contact form with the Flinkform blocks
2. Frontend — a styled single-step form on GeneratePress
3. Multi-step form with progress bar
4. Submissions list in wp-admin
5. Submission detail view
6. Conditional logic in the block inspector
7. Style panel — field style, label position, colours

== Changelog ==

= 1.10.0 =
* Fix: a conditionally hidden field no longer influences conditions. Its value was still being read, so switching a choice could leave a notice on screen or the submit button locked over an answer nobody could see or change — while the server had already dropped the field, meaning browser and server disagreed about the same form. A hidden field now counts as empty on both sides. Visibility is resolved to a fixed point first, so a cascade (hiding one field makes the next one's rule true) settles correctly, and a contradictory setup stops deterministically instead of looping.
* New: the notification email to the form owner is properly laid out. It now goes out as multipart — readable HTML with a plain-text alternative for clients that will not render it. Field labels instead of internal names, room between entries, dates as DD.MM.YYYY, multi-line messages with their line breaks, email and phone as tappable links, and only fields that were actually filled in. A custom body you have written is kept word for word and only gains the same shell.

= 1.9.0 =
* New: condition groups. A condition could only ever be one flat list of rules under a single ALL/ANY, which cannot express "(A or B or C) and D". You can now add a group inside a condition, give it its own ALL/ANY, and it counts as a single rule in the level above. The case it was built for: allow submitting only when the due date is empty, before a holiday, or after it — and the postcode is not an excluded one.
* Existing conditions are untouched. A rule set without groups behaves exactly as before, and a saved condition never needs migrating.

= 1.8.4 =
* Fix: style and script updates actually reach the browser. Every asset URL carried `?ver=0.1.0`, taken from a field in the block manifest that had not changed since the very first commit — so across more than thirty releases the stylesheet was served from an identical URL and browsers, CDNs and page caches all kept the copy they first downloaded. A fix could be deployed, be correct on the server, and remain invisible to anyone who had loaded a form before. Asset URLs now carry the plugin version, so every future release busts the cache on its own. This one still needs a single hard reload, because the old URL is what is cached.

= 1.8.3 =
* Fix: dropdowns rendered wrong in Safari. WebKit ignores vertical padding on a select with its native appearance and sizes the control from the font alone, which left the box shorter than its own text — Safari clipped the selected option along the top edge and the floating label landed on top of it, while Chrome looked fine. Flinkform now draws the control itself, so a dropdown is exactly as tall as every other field in every browser.
* Fix: a freshly inserted field is labelled in your site language. Block defaults live in a JSON file and never passed through the translation layer, so a new Date Field read "Date" and a dropdown read "Choose one" even on a fully German site. Labels the author has typed are untouched, including one that deliberately uses the English wording.

= 1.8.2 =
* Fix: a submit button held back by a Submit Condition now looks unavailable. It already carried the disabled attribute, so clicking did nothing — but it kept its full colour, its hover effect and the normal pointer, so the only clue was a line of grey text below it. It is now faded, desaturated and shows a not-allowed cursor, in all three button styles. The submitting state keeps its own look: a spinner on a greyed-out button reads as broken rather than busy.
* Fix: the hint under a gated submit button lines up with the button instead of being centred across the form.

= 1.8.1 =
* Fix: the block editor is finally translated. Flinkform ships German language files for the editor, but WordPress never read them: register_block_type() wires up script translations without a path, which makes WordPress look only in wp-content/languages/plugins — a folder filled by translate.wordpress.org, where Flinkform has no translations. So every inspector panel stayed English even on sites where the frontend and the admin screens were translated correctly. The block registry now points at the plugin's own languages folder.

= 1.8.0 =
* New: sender name and address per form, under Notifications. Both emails then come from your own address instead of "wordpress@yourdomain". No SMTP plugin needed — the sender is a wp_mail setting and applies however your site delivers mail. The editor warns when the address is on a different domain than the website, because your server is not authorised to send for someone else's domain and those emails fail SPF.
* New: Reply-To for the confirmation email to the submitter. Previously only the admin notification had one, so a visitor replying to their confirmation wrote to whatever address the site sends from — usually a mailbox nobody reads. Now the form can send from the website address and collect replies wherever you actually read them.

= 1.7.2 =
* Fix: the spam question ("What is 2 + 2?") no longer appears and disappears on a hard reload. It is the no-JavaScript fallback and used to stay on screen until the proof-of-work finished solving. It is now hidden from the first paint wherever scripting is available, and comes back on every path the solver cannot finish: no Web Crypto, an aborted computation, or a device slow enough to still be working after four seconds. Visitors without JavaScript are unaffected — the decision is made in the browser, so a cached page still serves them the question.
* Fix: even vertical rhythm with floating labels. The room a lifted label needs was added as a margin on text-flavoured fields only, so rows alternated between two spacings depending on whether the next block was a text field or something else — a notice, the address group, the consent row. The spacing now lives on the form's row gap and every row shares it.
* Fix: no more layout shift when the floating-label notch resolves. That room also changed size between the two notch states, which reflowed the whole form the moment the surface colour was worked out. It is now constant.

= 1.7.1 =
* Fix: conditional blocks no longer flash into view on page load. Everything with conditional logic was rendered visible and only hidden once the frontend module ran, which for a text field was a blip but for a Notice block meant a coloured box that appeared and then vanished. The server now works out the initial state itself — it knows the values the browser will evaluate against, either none on a fresh page or the submitted ones when a failed submission is re-rendered — and renders the block hidden straight away when it does not apply.

= 1.7.0 =
* New: Notice block — a highlighted note placed between fields, in four types (info, success, warning, important) with a matching icon. It submits nothing, so it never appears in your submissions or CSV export. Its real strength is conditional logic: show a note only when it applies, for example a delivery surcharge that kicks in from a certain distance, or an explanation attached to one particular answer. Text supports bold, italic and links; the colours follow your theme where it provides them.

= 1.6.4 =
* Fix: the floating-label notch no longer paints a mismatched box behind the label. It used to fall back to white whenever the surface colour was unknown, which on a tinted page produced a white rectangle around every lifted label. The notch is now opt-in: it only appears once the surface colour has been positively resolved, and otherwise the label simply rests clear of the border line, which needs no colour at all.
* Improvement: surface detection is far more capable. It starts at the form itself, composites semi-transparent layers onto whatever opaque colour lies beneath them, falls back to the browser canvas when nothing paints a colour, and deliberately declines gradients and background images instead of guessing. It also re-runs on load, on resize and when the colour-scheme changes, so a dark-mode toggle no longer leaves a light notch behind.
* Fix: the editor preview shares the same detection code as the frontend, so the notch can no longer differ between editing and the published page.
* Docs: the feature list said 13 field types and counted Section Heading as one of them, while omitting Address and Consent. It now lists the actual 14.

= 1.6.3 =
* Fix: restored the German (de_DE) translation. Version 1.6.1 rebuilt the language files from an incomplete template and silently dropped 243 already-translated strings, so German sites fell back to English for most of the admin UI, the block inspector and the default success message. All strings are translated again, including the address field and the date comparison operators.
* Fix: address field no longer prints its label and its placeholder on top of each other when the form uses floating labels. The sub-fields now follow the form's label position setting the same way every other text field does (no placeholder in floating mode, required marker moved into the placeholder in placeholder mode).
* Fix: the red error outline on invalid inputs never actually rendered. A nested SCSS selector expanded into an unmatchable compound (`.flinkform-field--has-error .flinkform-form.flinkform-form .flinkform-field__input`); same bug disabled the toggle field's label layout.
* Fix: conditional logic can now target address fields. The rule builder offered the composite field under its base name, which no input ever submits under, so such rules could never match. It now lists the individual sub-fields (street, postal code, city and any enabled optional lines).
* Fix: the address group heading showed the untranslated default "Address" on non-English sites. Block attribute defaults do not pass through the translation layer, so the untouched default is now translated explicitly.
* Fix: the address block editor preview matches the frontend again - correct label/placeholder split, required markers on the sub-fields and the "Full width" setting honoured.
* Fix: floating labels on address sub-fields keep their top spacing; the grid layout reset was overriding it.
* Fix: `date_before` / `date_on_or_after` used a PHP pattern that also accepted a trailing newline, where the identical client-side check did not. Server and browser now agree on every input.

= 1.6.2 =
* Enhancement: address field sub-inputs now render as full `.flinkform-field--text` wrappers, so they automatically inherit ALL form styles — floating labels, bordered/soft/underline/minimal field styles, beside/placeholder label modes, compact/relaxed spacing. The group heading is a subtle uppercase legend instead of a bold label.

= 1.6.1 =
* i18n: German (de_DE) translation for the address field and the date comparison operators. Note: this release also removed a large number of existing translations by mistake - see 1.6.3, which restores them.

= 1.6.0 =
* New: Address Field — a composite field with street, postal code and city in a compact grid layout. Optional address line 2 and country sub-fields. When set to required, all visible sub-fields except line 2 are enforced. Each sub-field stores separately for clean CSV export columns.

= 1.5.3 =
* Fix: conditional logic now correctly hides fields. The `.flinkform-field { display: flex }` rule (specificity 0,3,0) was overriding the browser's native `[hidden] { display: none }` (0,1,0), so conditionally hidden fields remained visible despite the JS correctly setting the `hidden` attribute. The rule now carries an explicit `[hidden] { display: none }` override at matching specificity.

= 1.5.2 =
* New: conditional logic operators "is before (date)" and "is on or after (date)" for comparing a date field against a fixed YYYY-MM-DD cutoff. Useful for gating form submission based on a deadline (e.g. show a notice and lock the submit button when a due date is too early).
* Fix: floating labels now automatically detect the nearest ancestor's background colour. The label notch matches the container background on any surface — no manual colour setting needed.

= 1.5.1 =
* Fix: radio buttons in "Buttons" display mode now lay out horizontally (side by side) instead of stacking vertically. The base field layout was overriding the flex direction due to higher CSS specificity.
* New: configurable button shape — choose "Pill" (fully rounded, default), "Rounded" (matches form field radius) or "Square" (no rounding) in the block inspector when display is set to "Buttons".

= 1.5.0 =
* New: the Radio field can now display its choices as selectable buttons ("pills") instead of a plain list — set "Display: Buttons" in the block inspector. The active choice fills with your form's primary colour. Keyboard, screen-reader and no-JavaScript support are unchanged (the buttons are real radio inputs, only styled).

= 1.4.4 =
* Fix: forms embedded outside the current page's own content — in a footer or header template part, a synced pattern, a theme-builder element or a site-wide popup — were silently rejected on submit, because the submission was matched only against the current page's content. Submissions now resolve the form wherever it actually lives, so popup and footer forms save correctly.

= 1.4.3 =
* Critical fix: a variable-ordering error introduced in 1.4.0 aborted the whole frontend module on load — the proof-of-work spam solver never ran, so EVERY submission (popup or not) was silently rejected as spam and nothing was stored. If you are on 1.4.0-1.4.2, update immediately.

= 1.4.2 =
* Fix: popup submissions now actually use the fetch path — the hidden admin-post "action" input shadowed the form's action property in JavaScript, so every fetch went to a broken URL and silently fell back to the classic page-reload submit
* Change: the spam-challenge token lifetime is now 30 minutes (was 5) — visitors who read a long page or open a popup form some minutes after page load were silently rejected with no feedback and no stored submission

= 1.4.1 =
* Fix: the spam-challenge token is now consumed when a submission is accepted instead of when it is checked — a popup submission that failed validation can be corrected and resubmitted without hitting the replay guard (the popup flow never re-renders the page, so no fresh token is minted between attempts)

= 1.4.0 =
* New: forms inside popups/modals now submit via fetch() without a page reload — the success card and validation errors render inline, so the popup stays open and the visitor sees the outcome
* Applies automatically when a form sits inside a modal container (`[role="dialog"]`, a native `<dialog>` element, or the dbw popup block); forms in the normal page flow keep the exact same behaviour as before
* All server-side protections (nonce, honeypot, time-check, spam challenge, duplicate-submission guard) run unchanged; redirect-after-submit still works from popups and network errors fall back to the classic submission

= 1.3.1 =
* Update: plugin homepage now points to the dedicated product site at flinkform.de

= 1.3.0 =
* New: duplicate-submission protection — a double-click, back-button resubmit or parallel request no longer creates a second entry, duplicate notification emails or repeated side effects (webhooks, payment verification in Pro)
* New: `flinkform_admin_format_value` filter — add-ons can format field values on the submission detail view (Flinkform Pro uses it to render uploaded files as download links)

= 1.2.1 =
* Fix: pages with a Flinkform are now excluded from full-page caching (DONOTCACHEPAGE) — prevents stale spam-challenge tokens from silently rejecting submissions on cached pages

= 1.2.0 =
* UX: redesigned error messages with inline icon, subtle background on global errors, and a gentle shake animation on invalid fields
* UX: consent field shows a clear "Please agree to continue" error instead of the internal field name
* UX: removed the thick left-border error indicator in favour of a cleaner full-border highlight

= 1.1.1 =
* UX: consent field error message fix (included in 1.2.0)

= 1.1.0 =
* Fix: consent checkbox is now correctly enforced as required during server-side validation (previously the required attribute was lost because Gutenberg does not serialise defaults)
* GDPR: new default consent text with inline privacy-policy link (replaces the old appended link)
* GDPR: consent text supports a `{privacy_policy}` placeholder that renders as an inline link to the site's privacy-policy page
* UX: updated default success message to "Thank you! Your message has been sent successfully."
* Backwards-compatible: forms saved with the old consent text or success message automatically use the new defaults

= 1.0.4 =
* i18n: regenerate German (de_DE) translation files (.mo + .json) to ensure all editor strings are up to date

= 1.0.3 =
* Style: reduce consent checkbox label to 12px for a cleaner visual hierarchy

= 1.0.0 =
* Floating labels now work on all text-input field types (URL, phone, date, select) - previously only text, email, textarea and number were supported
* Date and select fields start with the label in the lifted/notched position since they always show native browser UI
* Beside and hidden label positions now also apply to URL, phone, date and select fields
* Fix: style toggle buttons (field style, label position) no longer overflow in the editor sidebar, especially with longer translated labels
* First stable release

= 0.4.2 =
* i18n: block attribute defaults (success message, submit label, consent text) are now translated at render time - existing forms on non-English sites display the correct language without manual editing
* i18n: complete German (de_DE) translation - all frontend text, editor UI, admin screens and validation messages
* i18n: load bundled translations via load_plugin_textdomain() so they work without waiting for translate.wordpress.org
* Fix: the "Add field" editor button no longer inherits a 62 px font-size when the form block is placed inside a Spectra/UAGB container

= 0.4.0 =
* Renamed the plugin to Flinkform (new slug, text domain, prefixes `flinkform_`/`FLINKFORM_`, block namespace `flinkform/*`)
* Security: the spam time-check timestamp is now HMAC-signed and form-bound, so it can no longer be forged
* Security: additional sanitisation on the notification Reply-To header
* Reliability: the daily retention purge is now guarded against overlapping cron runs
* Corrected the FAQ: multi-step forms are part of the free core (and always were since 0.2.7)
* Fixed the plugin and author URIs to use a resolvable host (www.dennisbuchwald.de)
* Documented the public source repository and build steps in the readme (Source Code section)
* Output escaping: conditional-logic data attributes are now escaped late at render time (esc_attr), and submission detail values are output via wp_kses_post()

= 0.3.0 =
* Renamed all WordPress-global prefixes to satisfy WordPress.org naming requirements
* Revised readme description to remove promotional language

= 0.2.9 =
* WordPress.org Plugin Check pass: documented the safe direct custom-table queries, fixed admin sort-order input handling, sanitised spam/honeypot inputs — no functional change
* Resolved all Plugin Check errors and warnings (output escaping is handled internally; queries are prepared)

= 0.2.8 =
* Added a dedicated Consent field (GDPR), per-form retention auto-purge, and a GPLv2 LICENSE file
* Accessibility: explicit focus rings for checkboxes/radios/toggles, High-Contrast-Mode-safe focus on the soft field style, aria-invalid on group/consent errors, improved contrast
* Hardening: mail subject + Reply-To stripped of CR/LF; privacy-policy strings escaped; webhook header REST input sanitised
* Privacy text now documents the retention period and the strictly-necessary flash cookie

= 0.2.7 =
* Architecture refactor: the core stays fully free (incl. multi-step + conditional logic); integration features (webhooks, SMTP, CSV export) were factored out of the core
* Privacy: full WordPress privacy-tools integration (exporter + eraser); accurate disclosure of the single strictly-necessary flash cookie
* Accessibility: broader `prefers-reduced-motion` coverage; required spam-math fallback for no-JS visitors
* Hardening: defence-in-depth against mail-header injection; open-redirect-safe thank-you redirects

= 0.1.0 =
* Initial build

== Upgrade Notice ==

= 1.10.0 =
Hidden fields no longer count towards conditions, and the notification email is far easier to read.

= 1.9.0 =
Conditional logic can now nest: a group with its own ALL/ANY counts as one rule in the level above, so "(A or B) and C" is expressible.

= 1.8.4 =
Important: asset URLs never changed between releases, so browsers kept serving old CSS. Reload once with a hard refresh after updating; from here on it takes care of itself.

= 1.8.3 =
Fixes dropdown fields rendering too short in Safari, and translates the default labels of newly inserted fields.

= 1.8.2 =
A submit button blocked by a Submit Condition now visibly looks disabled instead of fully clickable.

= 1.8.1 =
The block editor now uses the bundled translations. On a German site the whole form inspector switches from English to German.

= 1.8.0 =
Your forms can now send from your own address, and the confirmation email can carry a Reply-To.

= 1.7.2 =
Removes the spam question flashing up on load, and evens out the spacing of forms using floating labels.

= 1.7.1 =
Conditional fields and notices no longer flash into view while the page loads.

= 1.7.0 =
Adds the Notice block: highlighted notes between your fields, and with conditional logic they appear only when they apply.

= 1.6.4 =
Fixes the white box that appeared behind floating labels on any page whose background is not plain white.

= 1.6.3 =
Recommended for every site, essential for German ones: 1.6.1 accidentally removed 243 translated strings, and this release restores them. Also fixes the address field under floating labels and the red outline on invalid inputs, which never rendered.

= 1.4.3 =
Critical. Versions 1.4.0 to 1.4.2 silently rejected every submission as spam and stored nothing. Update immediately.

= 0.4.2 =
German translation included. Success message, submit label and consent text now render in the site language automatically. Fixes a visual bug with the editor button in Spectra containers.

= 0.4.0 =
The plugin was renamed to Flinkform. All settings prefixes changed; this version is intended for fresh installations.

== Privacy ==

Flinkform is built with privacy by default. Here is what the free core does and does not do:

**What the free core stores:**
* Form submissions (the field values visitors enter) in a dedicated database table (`{prefix}flinkform_submissions`)

**What the free core does NOT do:**
* It stores no IP addresses and no browser user-agent strings
* It sets no tracking, analytics or marketing cookies. Flinkform sets exactly one strictly-necessary cookie — `flinkform_flash` (lifetime ~60 seconds, httpOnly) — and only when a form submission fails validation, to carry the error message and the visitor's input across the page reload. Successful submissions set no cookie at all
* It contacts no external service

**Data retention:**
* By default, submissions are retained until you delete them. To comply with the storage-limitation principle (GDPR Art. 5), set a per-form retention period (Form block → Data Retention) and Flinkform deletes older submissions automatically each day
* Individual submissions can be deleted from the admin submissions screen at any time

**Data deletion:**
* All free-core data (the submissions table) is permanently removed when the plugin is uninstalled through the WordPress admin
* Flinkform integrates with WordPress's privacy tools (Tools > Export Personal Data / Erase Personal Data) to support data-subject access and erasure requests

== Source Code ==

The complete, uncompiled source code (including the `src/` directory with the
unminified JavaScript/CSS that compiles into `build/`) is publicly available at:
https://github.com/dennisbuchwald/Flinkform

Build instructions (Node.js 18+ and npm required):
1. Clone the repository: `git clone https://github.com/dennisbuchwald/Flinkform.git`
2. Install dependencies: `npm install`
3. Build the compiled assets into `build/`: `npm run build`

The build is powered by `@wordpress/scripts` (webpack). The `src/` sources are
excluded from the distributed plugin zip to keep it small; this repository is
the canonical, reviewable source.

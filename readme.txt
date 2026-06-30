=== Flinkform - Forms for the Block Editor ===
Contributors: dbwmediadennis
Tags: forms, contact form, form builder, conditional logic, block editor
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.2.1
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
* 13 field types: Text, Email, Textarea, Number, Date, URL, Phone, Select, Radio, Checkbox, Toggle, Hidden, Section Heading
* Dedicated Consent field for privacy-policy agreement
* Multi-step forms with Page Break block, per-step validation and progress indicator (bar, dots or numbers)
* Conditional logic — show/hide fields, skip steps, gate the submit button
* Two-column layout with per-field full-width override

**Styling**
* Automatic theme.json inheritance (colours, typography, spacing, border radius)
* Style panel: primary colour, field style (bordered/soft/underline/minimal), label position (above/beside/floating/placeholder), submit button style (fill/outline/ghost)

**Notifications**
* Admin notification email on every submission (configurable recipient, merge tags)
* Optional confirmation email to the submitter
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

=== PerForm Forms ===
Contributors: dbwmediadennis
Tags: forms, contact form, form builder, conditional logic, block editor
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block-native form builder for the WordPress Block Editor — theme.json styling, conditional logic, Interactivity API.

== Description ==

PerForm Forms is a form builder that lives entirely inside the WordPress Block Editor. Forms are composed from native blocks (`block.json` v3), styled through `theme.json` design tokens, and powered by the Interactivity API — no separate admin UI, no shortcodes, no jQuery.

= How it works =

* **Block Editor native** — forms are built with `block.json` and the Interactivity API, directly inside the editor
* **theme.json styling** — forms inherit your theme's typography, colours and spacing automatically
* **Modern stack** — WordPress 6.5+, PHP 8.1+, no jQuery, frontend JS under 15 KB gzipped
* **Conditional logic** — show/hide fields based on user input, included in the free core
* **WCAG 2.1 AA** — full keyboard navigation, screen-reader compatible, aria-live announcements
* **Privacy by design** — no external services, no tracking cookies, no IP tracking — everything stays on your server

= Features (free core) =

**Form building**
* 10 field types: Text, Email, Textarea, Number, Select, Radio, Checkbox, Toggle, Hidden, Section Heading
* Conditional logic — show/hide fields based on user input
* Two-column layout with per-field full-width override

**Styling**
* Automatic theme.json inheritance (colours, typography, spacing, border radius)
* Style panel: primary colour, field style (bordered/underline/minimal), label position (above/beside/floating), submit button style (fill/outline/ghost)
* Dark mode support via `prefers-color-scheme`

**Notifications**
* Admin notification email on every submission (configurable recipient, merge tags)
* Optional confirmation email to the submitter
* Sends through your site's standard WordPress mail (`wp_mail`)

**Spam protection**
* Always-on honeypot + time-based check (zero configuration)
* No external service, no API keys, no tracking cookies, 100% GDPR-friendly

**After submission**
* Success message or redirect to a custom thank-you URL (with open-redirect protection)
* Optional submission ID and form ID query parameters for conversion tracking (GA4, Meta Pixel, Plausible, etc.)

**Admin**
* Submissions list with search, filter by form, sort, bulk actions
* Single-submission detail view with all field labels and values
* Mark as read/unread

== Installation ==

1. Upload the `perform-forms` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Open any page or post in the Block Editor
4. Insert the **Form** block (search for "PerForm" or "Form")
5. Add fields, configure settings in the block inspector, publish — done

== Frequently Asked Questions ==

= Is PerForm Forms free? =

Yes. PerForm Forms is GPLv2-licensed and completely free — including conditional logic. Everything you need to build and run real forms is in the core.

= What WordPress version do I need? =

WordPress 6.5 or higher and PHP 8.1 or higher. PerForm uses modern WordPress APIs (Interactivity API, block.json v3, viewScriptModule) that are not available in older versions.

= Does PerForm work with my theme? =

Yes. PerForm reads your theme's design tokens from `theme.json` and inherits colours, typography, spacing and border radius automatically. Forms look native on any modern WordPress theme — tested with GeneratePress, Twenty Twenty-Five, Astra and Kadence.

= My notification emails don't arrive. What can I do? =

Email deliverability depends on your host. Many hosts send `wp_mail()` unreliably. If your notifications don't arrive, install a dedicated SMTP plugin (such as WP Mail SMTP or FluentSMTP) to route mail through a proper provider — it will handle delivery for PerForm too.

= How does the spam protection work? =

PerForm Forms uses a two-layer approach that requires no setup:

1. **Honeypot** — a hidden field that bots fill in but humans never see
2. **Time check** — submissions faster than a couple of seconds after page load are rejected

No external service is contacted. No tracking cookies are set. No personal data is shared.

= Does PerForm Forms support multi-step forms? =

Multi-step forms are available as a premium feature. Insert a **Page Break** block between fields to split the form into steps, choose a progress indicator style, and benefit from per-step validation.

= Is PerForm GDPR-compliant? =

PerForm is designed with privacy by default — see the Privacy section below for the full detail. In short: no IP addresses or user-agent strings are stored, no data ever leaves your server, and PerForm integrates with WordPress's privacy tools for data-subject access and erasure requests.

= Can I redirect to a thank-you page after submission? =

Yes. In the block inspector's "After Submit" panel, choose "Redirect to URL" and enter your thank-you page URL (validated against open redirects). Optionally append the submission ID and form ID as query parameters for conversion tracking.

== Screenshots ==

1. Block Editor — building a contact form with the PerForm blocks
2. Frontend — a styled single-step form on GeneratePress
3. Multi-step form with progress bar
4. Submissions list in wp-admin
5. Submission detail view
6. Conditional logic in the block inspector
7. Style panel — field style, label position, colours

== Changelog ==

= 0.3.0 =
* Renamed all WordPress-global prefixes from `perform_` to `perffo_` (constants, options, transients, hooks, form fields, script/style handles, menu slugs) to satisfy WordPress.org naming requirements
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
* Privacy: full WordPress privacy-tools integration (exporter + eraser); accurate disclosure of the single strictly-necessary `perffo_flash` cookie
* Accessibility: broader `prefers-reduced-motion` coverage; required spam-math fallback for no-JS visitors
* Hardening: defence-in-depth against mail-header injection; open-redirect-safe thank-you redirects
* Numerous internal correctness, security and standards improvements

= 0.1.0 =
* Initial build
* 10 field types, multi-step forms, conditional logic, email notifications with merge tags
* Built-in spam protection (honeypot + time-check + proof-of-work + math fallback)
* Style panel with theme.json inheritance; per-form thank-you redirect
* Submissions admin with search, filter and bulk actions
* WCAG 2.1 AA accessibility; privacy-by-design

== Upgrade Notice ==

= 0.2.7 =
Multi-step and conditional logic stay in the free core; integration features (webhooks, SMTP, CSV export) were factored out of the core.

== Privacy ==

PerForm is built with privacy by default. Here is what the free core does and does not do:

**What the free core stores:**
* Form submissions (the field values visitors enter) in a dedicated database table (`{prefix}perffo_submissions`)

**What the free core does NOT do:**
* It stores no IP addresses and no browser user-agent strings
* It sets no tracking, analytics or marketing cookies. PerForm sets exactly one strictly-necessary cookie — `perffo_flash` (lifetime ~60 seconds, httpOnly) — and only when a submission fails validation, to carry the error message across the page reload
* It contacts no external service

**Data deletion:**
* Individual submissions can be deleted from the admin submissions screen
* Optionally, set a per-form retention period (Form block → Data Retention) and PerForm deletes older submissions automatically each day
* All free-core data (the submissions table) is permanently removed when the plugin is uninstalled through the WordPress admin
* PerForm integrates with WordPress's privacy tools (Tools > Export Personal Data / Erase Personal Data) to support data-subject access and erasure requests

== Source Code ==

The source code for this plugin is available at:
https://github.com/dennisbuchwald/perform-forms

Build instructions:
1. Clone the repository
2. Run `npm install`
3. Run `npm run build`

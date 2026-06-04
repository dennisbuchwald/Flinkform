=== PerForm ===
Contributors: dbwmediadennis
Tags: forms, contact form, form builder, multi-step form, conditional logic
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Beautiful, native WordPress forms built for the block editor — fast, accessible, free. Multi-step and conditional logic included.

== Description ==

**PerForm** is the form plugin WordPress should have shipped natively. Beautiful by default, fast to use, powerful when you need it — and the core is free, always.

= The standard =

If a first-time user cannot build and publish a working contact form in under 5 minutes without reading any documentation, we have failed.

= What makes PerForm different =

* **Native, not bolted on** — built inside the WordPress Block Editor with `block.json` and the Interactivity API, not in a separate admin UI
* **Beautiful by default** — forms inherit your theme's typography, colours and spacing automatically via theme.json
* **Modern stack** — WordPress 7.0+, PHP 8.1+, no jQuery, frontend JS under 15 KB gzipped
* **Genuinely capable for free** — multi-step forms and conditional logic are part of the free core, not a paid upsell
* **WCAG 2.1 AA** — full keyboard navigation, screen-reader compatible, aria-live announcements
* **Privacy by design** — no external services, no tracking cookies, no IP tracking. Everything stays on your server

= Features (free core) =

**Form building**
* 10 field types: Text, Email, Textarea, Number, Select, Radio, Checkbox, Toggle, Hidden, Section Heading
* Multi-step forms with progress indicator (bar, dots, numbers)
* Conditional logic — show/hide fields and steps based on user input
* Two-column layout with per-field full-width override
* Custom CSS per form with scoped selectors

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
* Built-in proof-of-work challenge — transparent for JS-enabled visitors, math fallback for everyone else
* No external service, no API keys, no tracking cookies, 100% GDPR-friendly

**After submission**
* Success message or redirect to a custom thank-you URL (with open-redirect protection)
* Optional submission ID and form ID query parameters for conversion tracking (GA4, Meta Pixel, Plausible, etc.)

**Admin**
* Submissions list with search, filter by form, sort, bulk actions
* Single-submission detail view with all field labels and values
* Mark as read/unread

= PerForm Pro =

[PerForm Pro](https://dbw-media.de/perform-forms-pro/) is an optional paid add-on that installs alongside the free core and unlocks integrations, deliverability and data tooling:

* **Webhooks** — send submissions to Zapier, Make, n8n, Airtable or any URL (JSON/form-encoded, custom headers, conditional triggers, automatic retry, delivery log)
* **SMTP delivery** — route notification mail through Gmail, Outlook, SendGrid, Mailgun, Brevo, Postmark or Amazon SES, with a one-click test and conflict detection
* **CSV export** of submissions
* Coming soon: external CAPTCHA providers (Turnstile, hCaptcha, reCAPTCHA), file uploads and payment fields

The free core works fully on its own; Pro simply adds these capabilities when installed.

== Installation ==

1. Upload the `perform-forms` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Open any page or post in the Block Editor
4. Insert the **Form** block (search for "PerForm" or "Form")
5. Add fields, configure settings in the block inspector, publish — done

== Frequently Asked Questions ==

= Is PerForm free? =

Yes. The PerForm core is GPLv2-licensed and free, including multi-step forms and conditional logic. An optional paid add-on, **PerForm Pro**, adds integrations (webhooks), SMTP delivery and CSV export — but you never need it to build and run real forms.

= What does PerForm Pro add, and do I need it? =

Pro adds webhooks, an SMTP module and CSV export (with more coming). You only need it if you want those specific integrations — the free core handles form building, multi-step, conditional logic, notifications and spam protection on its own.

= What WordPress version do I need? =

WordPress 7.0 or higher and PHP 8.1 or higher. PerForm uses modern WordPress APIs (Interactivity API, block.json v3, viewScriptModule) that are not available in older versions.

= Does PerForm work with my theme? =

Yes. PerForm reads your theme's design tokens from `theme.json` and inherits colours, typography, spacing and border radius automatically. Forms look native on any modern WordPress theme — tested with GeneratePress, Twenty Twenty-Five, Astra and Kadence.

= My notification emails don't arrive. What can I do? =

Email deliverability depends on your host. Many hosts send `wp_mail()` unreliably. PerForm Pro includes an SMTP module (Gmail, Outlook, SendGrid, Mailgun, Brevo, Postmark, Amazon SES) that routes mail through a proper provider. If you already use a dedicated SMTP plugin (WP Mail SMTP, FluentSMTP, etc.), it will handle delivery for PerForm too.

= How does the spam protection work? =

PerForm uses a three-layer approach that requires no setup:

1. **Honeypot** — a hidden field that bots fill in but humans never see
2. **Time check** — submissions faster than a couple of seconds after page load are rejected
3. **Proof-of-work challenge** — the visitor's browser solves a small cryptographic puzzle (~50–500 ms, completely transparent). If JavaScript is disabled, a simple math question appears as fallback

No external service is contacted. No tracking cookies are set. No personal data is shared.

= Does PerForm support multi-step forms? =

Yes — in the free core. Insert a **Page Break** block between fields to split the form into steps. Choose a progress indicator style (bar, dots, numbers, none). Per-step validation ensures users complete required fields before advancing. Steps can be conditionally skipped via the conditional-logic engine.

= Is PerForm GDPR-compliant? =

PerForm is designed with privacy by default — see the Privacy section below for the full detail. In short: no IP addresses or user-agent strings are stored, no data leaves your server unless you install PerForm Pro and configure an integration, and PerForm integrates with WordPress's privacy tools for data-subject access and erasure requests.

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

= 0.2.8 =
* Added a dedicated Consent field (GDPR), per-form retention auto-purge, and a GPLv2 LICENSE file
* Accessibility: explicit focus rings for checkboxes/radios/toggles, High-Contrast-Mode-safe focus on the soft field style, aria-invalid on group/consent errors, improved contrast
* Hardening: mail subject + Reply-To stripped of CR/LF; privacy-policy strings escaped; webhook header REST input sanitised
* Privacy text now documents the retention period and the strictly-necessary flash cookie

= 0.2.7 =
* Introduces the Free/Pro architecture: the core is free (incl. multi-step + conditional logic); webhooks, SMTP and CSV export move to the optional PerForm Pro add-on
* Privacy: full WordPress privacy-tools integration (exporter + eraser); accurate disclosure of the single strictly-necessary `perform_flash` cookie
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
Free/Pro split: multi-step and conditional logic stay free; webhooks, SMTP and CSV export are now part of the optional PerForm Pro add-on.

== Privacy ==

PerForm is built with privacy by default. Here is what the free core does and does not do:

**What the free core stores:**
* Form submissions (the field values visitors enter) in a dedicated database table (`{prefix}perform_submissions`)

**What the free core does NOT do:**
* It stores no IP addresses and no browser user-agent strings
* It sets no tracking, analytics or marketing cookies. PerForm sets exactly one strictly-necessary cookie — `perform_flash` (lifetime ~60 seconds, httpOnly) — and only when a submission fails validation, to carry the error message across the page reload
* It contacts no external service

**PerForm Pro (optional add-on):**
If you install PerForm Pro, additional data handling applies and is disclosed separately by that plugin: SMTP credentials (encrypted) are stored in `wp_options` when SMTP is enabled; webhook configurations and delivery logs are stored in dedicated tables; and, when configured, submission data is transmitted to your webhook URLs and notification mail is routed through your SMTP provider (both of which may involve third parties, possibly outside the EU).

**Data deletion:**
* Individual submissions can be deleted from the admin submissions screen
* Optionally, set a per-form retention period (Form block → Data Retention) and PerForm deletes older submissions automatically each day
* All free-core data (the submissions table) is permanently removed when the plugin is uninstalled through the WordPress admin
* PerForm integrates with WordPress's privacy tools (Tools > Export Personal Data / Erase Personal Data) to support data-subject access and erasure requests. Deleting a submission also removes any related PerForm Pro webhook delivery records.
* If you used PerForm Pro, uninstall it as well: its webhook delivery log lives in the add-on's own tables and is removed by the Pro uninstaller. (WordPress already prevents deleting the free core while Pro is active.)

== Source Code ==

The source code for this plugin is available at:
https://github.com/dennisbuchwald/perform-forms

Build instructions:
1. Clone the repository
2. Run `npm install`
3. Run `npm run build`

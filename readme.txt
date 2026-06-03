=== PerForm ===
Contributors: dbwmediadennis
Tags: forms, contact form, form builder, gutenberg, block editor
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Beautiful, native WordPress forms built for the block editor — fast, accessible, free.

== Description ==

**PerForm** is the form plugin WordPress should have shipped natively. Beautiful by default, fast to use, powerful when you need it — and free, always.

= The standard =

If a first-time user cannot build and publish a working contact form in under 5 minutes without reading any documentation, we have failed.

= What makes PerForm different =

* **Native, not bolted on** — built inside the WordPress Block Editor with `block.json` and the Interactivity API, not in a separate admin UI
* **Beautiful by default** — forms inherit your theme's typography, colours and spacing automatically via theme.json
* **Modern stack** — WordPress 7.0+, PHP 8.1+, no jQuery, frontend JS under 15 KB gzipped
* **Power on tap** — multi-step forms, conditional logic, webhooks, built-in SMTP module
* **WCAG 2.1 AA** — full keyboard navigation, screen-reader compatible, aria-live announcements
* **GDPR by design** — no external services, no cookies, no IP tracking. Everything stays on your server

= Features =

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
* Admin notification email on every submission (configurable recipients, merge tags)
* Optional confirmation email to the submitter
* Built-in SMTP module with provider presets (Gmail, Outlook, SendGrid, Mailgun, Brevo, Postmark, Amazon SES)
* SMTP diagnostic status block with one-click test email

**Integrations**
* Outgoing webhooks (JSON/form-encoded) to Zapier, Make, n8n, Airtable, or any URL
* Multiple webhooks per form, conditional triggers, retry with exponential backoff
* Webhook delivery log with per-submission detail view

**Spam protection**
* Always-on honeypot + time-based check (zero configuration)
* Built-in proof-of-work challenge — transparent for JS-enabled visitors, math fallback for everyone else
* No external service, no API keys, no cookies, 100% GDPR-compatible

**After submission**
* Success message or redirect to a custom thank-you URL
* Optional submission ID and form ID query parameters for conversion tracking (GA4, Meta Pixel, Plausible, etc.)

**Admin**
* Submissions list with search, filter by form, sort, bulk actions
* Single-submission detail view with all field labels and values
* CSV export per form
* Mark as read/unread

== Installation ==

1. Upload the `perform-forms` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Open any page or post in the Block Editor
4. Insert the **Form** block (search for "PerForm" or "Form")
5. Add fields, configure settings in the block inspector, publish — done

== Frequently Asked Questions ==

= Is PerForm really free? =

Yes. PerForm is GPLv2-licensed and free forever. There is no premium tier.

= What WordPress version do I need? =

WordPress 7.0 or higher and PHP 8.1 or higher. PerForm uses modern WordPress APIs (Interactivity API, block.json v3, viewScriptModule) that are not available in older versions.

= Does PerForm work with my theme? =

Yes. PerForm reads your theme's design tokens from `theme.json` and inherits colours, typography, spacing and border radius automatically. Forms look native on any modern WordPress theme — tested with GeneratePress, Twenty Twenty-Five, Astra and Kadence.

= Do I need an SMTP plugin? =

Not necessarily. PerForm includes its own SMTP module with provider presets for Gmail (App Password), Outlook.com, SendGrid, Mailgun, Brevo, Postmark and Amazon SES. If you already use a dedicated SMTP plugin (WP Mail SMTP, FluentSMTP, etc.), PerForm detects it automatically and stays out of the way.

= How does the spam protection work? =

PerForm uses a three-layer approach that requires no setup:

1. **Honeypot** — a hidden field that bots fill in but humans never see
2. **Time check** — submissions faster than 2 seconds after page load are rejected
3. **Proof-of-work challenge** — the visitor's browser solves a small cryptographic puzzle (~50–500 ms, completely transparent). If JavaScript is disabled, a simple math question appears as fallback

No external service is contacted. No cookies are set. No personal data is shared.

= Is PerForm GDPR-compliant? =

PerForm is designed with privacy by default:

* No IP addresses or user-agent strings are stored
* No data is sent to external services unless you explicitly configure webhooks or SMTP
* The built-in spam protection runs entirely on your server and in the visitor's browser
* Submissions can be deleted individually, in bulk, or entirely when the plugin is uninstalled
* PerForm integrates with WordPress's privacy tools (Tools > Export/Erase Personal Data) so you can fulfil data-subject requests

= Can I use webhooks to connect to Zapier / Make / n8n? =

Yes. Each form can have multiple outgoing webhooks. Configure the URL, HTTP method (POST/GET), payload format (JSON/form-encoded), custom headers (for API keys) and optional field mapping — all from the block inspector. Failed deliveries retry automatically with exponential backoff.

= Does PerForm support multi-step forms? =

Yes. Insert a **Page Break** block between fields to split the form into steps. Choose a progress indicator style (bar, dots, numbers, none). Per-step validation ensures users complete required fields before advancing. Steps can be conditionally skipped via the conditional-logic engine.

= Can I redirect to a thank-you page after submission? =

Yes. In the block inspector's "After Submit" panel, choose "Redirect to URL" and enter your thank-you page URL. Optionally append the submission ID and form ID as query parameters for conversion tracking.

== Screenshots ==

1. Block Editor — building a contact form with the PerForm blocks
2. Frontend — a styled single-step form on GeneratePress
3. Multi-step form with progress bar
4. Submissions list in wp-admin
5. Submission detail view
6. SMTP settings with diagnostic status block
7. Webhook configuration in the block inspector
8. Style panel — field style, label position, colours

== Changelog ==

= 0.1.0 =
* Initial release
* 10 field types: Text, Email, Textarea, Number, Select, Radio, Checkbox, Toggle, Hidden, Section Heading
* Multi-step forms with progress indicator and per-step validation
* Conditional logic for fields, steps and submit button
* Email notifications with merge-tag system
* Built-in SMTP module with 8 provider presets and diagnostic status block
* Outgoing webhooks with retry, conditional triggers and delivery log
* Style panel with theme.json inheritance, 3 field styles, 3 label positions, 3 button styles
* Built-in spam protection (honeypot + time-check + proof-of-work + math fallback)
* Per-form thank-you redirect with conversion-tracking metadata
* Submissions admin with search, filter, CSV export, bulk actions
* WCAG 2.1 AA accessibility
* GDPR by design — no external services, no IP storage, privacy-tools integration
* Dark mode support

== Upgrade Notice ==

= 0.1.0 =
Initial release.

== Privacy ==

PerForm is built with privacy by default. Here is what the plugin does and does not do:

**What PerForm stores:**
* Form submissions (the field values visitors enter) in a dedicated database table (`{prefix}_perform_submissions`)
* SMTP credentials (encrypted with AES-256-CBC) in `wp_options` when the SMTP module is enabled
* Webhook configurations and delivery logs in dedicated database tables

**What PerForm does NOT store:**
* IP addresses
* Browser user-agent strings
* Cookies (PerForm sets no cookies of any kind)

**External services:**
PerForm does not contact any external service by default. The following features, when explicitly enabled by a site administrator, may transmit data to third parties:

* **Webhooks** — submission data is sent to the URL(s) you configure
* **SMTP** — notification emails are routed through your configured SMTP provider

The built-in spam protection (proof-of-work + math fallback) runs entirely on your server and in the visitor's browser. No data is sent to any external anti-spam service.

**Data deletion:**
* Individual submissions can be deleted from the admin submissions screen
* All plugin data (tables, options, transients) is permanently removed when the plugin is uninstalled through the WordPress admin
* PerForm integrates with WordPress's privacy tools (Tools > Export Personal Data / Erase Personal Data) to support data-subject access and erasure requests

== Source Code ==

The source code for this plugin is available at:
https://github.com/dennisbuchwald/perform-forms

Build instructions:
1. Clone the repository
2. Run `npm install`
3. Run `npm run build`

=== PerForm ===
Contributors: dbwmediadennis
Tags: forms, contact form, form builder, gutenberg, block editor
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Beautiful, native WordPress forms built for the block editor — fast, accessible, free.

== Description ==

**PerForm** is the form plugin WordPress should have shipped natively. Beautiful by default, fast to use, powerful when you need it — and free, always.

= The standard =

If a first-time user cannot build and publish a working contact form in under 5 minutes without reading any documentation, we have failed.

= What makes PerForm different =

* **Native, not bolted on** — built inside the WordPress Block Editor with `block.json` and the Interactivity API, not in a separate admin UI
* **Beautiful by default** — forms inherit your theme's typography, colors and spacing automatically via theme.json
* **Modern stack** — WordPress 7.0+, PHP 8.1+, no jQuery, frontend JS budget under 15kb gzipped
* **Power on tap** — multi-step forms, conditional logic, webhooks, optional SMTP module
* **WCAG 2.1 AA** — full keyboard navigation, screen-reader compatible, `aria-live` error messages
* **Developer first** — REST API, WP-CLI commands, action and filter hooks, Block Bindings API integration

= Status =

This is the public scaffold of PerForm v0.1.0. The MVP is in active development. See the project repository for current progress.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Insert the **Form** block in any page or post

== Frequently Asked Questions ==

= Is PerForm really free? =

Yes. PerForm is GPLv2-licensed and free forever on WordPress.org. There is no premium tier hiding the features you need.

= What WordPress version do I need? =

WordPress 7.0 or higher and PHP 8.1 or higher. PerForm uses modern WordPress APIs intentionally — older versions are not supported.

= Does PerForm work with my theme? =

Yes. PerForm inherits your theme's design tokens (colors, typography, spacing, border-radius) from `theme.json` automatically. Forms look native on any modern WordPress theme without configuration.

== Changelog ==

= 0.1.0 =
* Initial scaffold release — plugin skeleton, no functional form blocks yet.

== Upgrade Notice ==

= 0.1.0 =
Initial scaffold. Not yet feature-complete — see project repository for status.

== Source Code ==

The source code for this plugin is available at:
https://github.com/dbw-media/perform-forms

Build instructions:
1. Clone the repository
2. Run `npm install`
3. Run `npm run build`

== Privacy Policy ==

PerForm itself does not collect, store, or transmit any personal data to third parties. Form submissions are stored in your own WordPress database, in a dedicated `{prefix}_perform_submissions` table. Optional features (reCAPTCHA, hCaptcha, Turnstile, webhook destinations) connect to the third-party services *you* configure — they are off by default.

== License ==

This plugin is licensed under the GPL v2 or later.

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

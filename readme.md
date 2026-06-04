# PerForm

[![WordPress Plugin Version](https://img.shields.io/badge/version-0.2.7-blue)](https://wordpress.org/plugins/perform-forms/)
[![License](https://img.shields.io/badge/license-GPL%20v2-green)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-7.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://php.net/)

> The form plugin WordPress should have shipped natively.

**PerForm** builds beautiful, accessible forms inside the WordPress Block Editor — fast by default, privacy-first, and genuinely capable for free. Multi-step forms and conditional logic are part of the free core, not a paid upsell.

```
If a first-time user cannot build and publish a working contact form in under
5 minutes without reading any documentation, we have failed.
```

## Why PerForm

- **Native, not bolted on** — built with `block.json` and the Interactivity API, not a separate admin UI
- **Beautiful by default** — inherits your theme's colours, typography and spacing from `theme.json`
- **Modern stack** — WordPress 7.0+, PHP 8.1+, no jQuery, frontend JS under 15 KB gzipped
- **Capable for free** — multi-step and conditional logic ship in the core
- **WCAG 2.1 AA** — full keyboard navigation, screen-reader support, `aria-live` announcements
- **Privacy by design** — no external services, no tracking cookies, no IP logging; everything stays on your server

## Features (free core)

- **10 field types** — Text, Email, Textarea, Number, Select, Radio, Checkbox, Toggle, Hidden, Consent — plus Section Heading and Page Break
- **Multi-step forms** with a progress indicator (bar, dots, numbers) and per-step validation
- **Conditional logic** — show/hide fields and steps based on user input
- **Theme-aware styling** — `theme.json` inheritance, field styles (bordered / underline / minimal), label positions, dark mode
- **Notifications** — admin + optional confirmation email via `wp_mail`, with merge tags
- **Spam protection** — honeypot + time-check + a built-in proof-of-work challenge (math fallback for no-JS), zero configuration, no external service
- **Submissions admin** — list with search, filter, sort, bulk actions, and a detail view
- **Privacy tools** — WordPress data export / erasure integration, optional retention auto-purge

## PerForm Pro

[PerForm Pro](https://dbw-media.de/perform-forms-pro/) is an optional paid add-on that installs alongside the free core and adds integrations, deliverability and data tooling:

- **Webhooks** — Zapier, Make, n8n, Airtable or any URL (conditional triggers, retries, delivery log)
- **SMTP delivery** — Gmail, Outlook, SendGrid, Mailgun, Brevo, Postmark, Amazon SES
- **CSV export** of submissions

The free core works fully on its own — Pro simply adds these capabilities when installed.

## Architecture

- **Rendering** — dynamic blocks with server-side `render.php`; frontend interactivity via `@wordpress/interactivity` (script modules)
- **Database** — a dedicated `{prefix}perform_submissions` table (not posts/meta)
- **Free/Pro split** — the core is a platform with frozen extension seams (filters/actions); Pro is a separate plugin that hooks them and never ships inside this repo
- **Build** — `@wordpress/scripts`
- **Stack** — WordPress 7.0+, PHP 8.1+, no jQuery, JS budget under 15 KB gzipped

## Development

```bash
npm install        # install dependencies
npm run start      # development build with watch
npm run build      # production build
npm run lint:js    # lint JavaScript
npm run plugin-zip # produce a distributable zip
```

Source blocks live in `src/` (compiled to `build/`); PHP classes follow PSR-4 under `includes/` (namespace `PerForm\`).

## Standards

- **Accessibility** — WCAG 2.1 AA, keyboard-navigable, screen-reader compatible
- **Performance** — frontend JS under 15 KB gzipped, conditional asset loading, no jQuery
- **Security** — nonces, capability checks, prepared statements, sanitize-in / escape-out
- **i18n** — every user-facing string translatable
- **Privacy** — no IP/user-agent storage, no external calls in the free core

## Compatibility

- WordPress 7.0 or higher
- PHP 8.1 or higher
- Block Editor (Gutenberg) required

## About dbw media

[dbw media](https://dbw-media.de) is a WordPress studio focused on custom development, Gutenberg blocks and performance. PerForm is built and maintained by [Dennis Buchwald](https://dbw-media.de).

## License

GPL v2 or later. See [the GPL](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

Built by [dbw media](https://dbw-media.de) — professional WordPress development.

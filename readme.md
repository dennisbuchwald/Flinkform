# Flinkform

[![WordPress Plugin Version](https://img.shields.io/badge/version-0.2.9-blue)](https://wordpress.org/plugins/flinkform/)
[![License](https://img.shields.io/badge/license-GPL%20v2-green)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-blue)](https://wordpress.org/)
[![Tested up to](https://img.shields.io/badge/tested%20up%20to-7.0-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://php.net/)

> Block-native forms for WordPress — conditional logic, accessible, and free.

**Flinkform Forms** builds forms the way WordPress should have done it from the start — inside the Block Editor, with the Interactivity API, no separate admin UI. Beautiful, accessible, privacy-first, and genuinely capable for free.

```
If a first-time user cannot build and publish a working contact form in under
5 minutes without reading any documentation, we have failed.
```

## Why Flinkform

- **Native, not bolted on** — built with `block.json` and the Interactivity API, not a separate admin UI
- **Beautiful by default** — inherits your theme's colours, typography and spacing from `theme.json`
- **Modern stack** — WordPress 6.5+, PHP 8.1+, no jQuery, frontend JS under 15 KB gzipped
- **Conditional logic** — show/hide fields based on user input, included in the free core
- **WCAG 2.1 AA** — full keyboard navigation, screen-reader support, `aria-live` announcements
- **Privacy by design** — no external services, no tracking cookies, no IP logging; everything stays on your server

## Features (free core)

- **10 field types** — Text, Email, Textarea, Number, Select, Radio, Checkbox, Toggle, Hidden, Consent — plus Section Heading
- **Conditional logic** — show/hide fields based on user input
- **Theme-aware styling** — `theme.json` inheritance, field styles (bordered / underline / minimal), label positions, dark mode
- **Notifications** — admin + optional confirmation email via `wp_mail`, with merge tags
- **Spam protection** — honeypot + time-check, zero configuration, no external service
- **Submissions admin** — list with search, filter, sort, bulk actions, and a detail view
- **Privacy tools** — WordPress data export / erasure integration, optional retention auto-purge

## Architecture

- **Rendering** — dynamic blocks with server-side `render.php`; frontend interactivity via `@wordpress/interactivity` (script modules)
- **Database** — a dedicated `{prefix}perform_submissions` table (not posts/meta)
- **Extensible** — the core exposes frozen extension seams (filters/actions) so integrations can hook in without modifying the core
- **Build** — `@wordpress/scripts`
- **Stack** — WordPress 6.5+, PHP 8.1+, no jQuery, JS budget under 15 KB gzipped

## Development

```bash
npm install        # install dependencies
npm run start      # development build with watch
npm run build      # production build
npm run lint:js    # lint JavaScript
npm run plugin-zip # produce a distributable zip
```

Source blocks live in `src/` (compiled to `build/`); PHP classes follow PSR-4 under `includes/` (namespace `Flinkform\`).

## Standards

- **Accessibility** — WCAG 2.1 AA, keyboard-navigable, screen-reader compatible
- **Performance** — frontend JS under 15 KB gzipped, conditional asset loading, no jQuery
- **Security** — nonces, capability checks, prepared statements, sanitize-in / escape-out
- **i18n** — every user-facing string translatable
- **Privacy** — no IP/user-agent storage, no external calls in the free core

## Compatibility

- WordPress 6.5 or higher
- PHP 8.1 or higher
- Block Editor (Gutenberg) required

## Support

- **Documentation**: Check the [plugin page](https://wordpress.org/plugins/flinkform/)
- **Support Forum**: [WordPress.org Support](https://wordpress.org/support/plugin/flinkform/)
- **Professional Support**: [dbw media](https://dbw-media.de/kontakt)

## About dbw media

[dbw media](https://dbw-media.de) is a WordPress studio focused on custom development, Gutenberg blocks and performance. Flinkform is built and maintained by [Dennis Buchwald](https://dennisbuchwald.de) — the personal brand behind dbw media.

## License

GPL v2 or later. See [the GPL](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

Built by [dbw media](https://dbw-media.de) — professional WordPress development.

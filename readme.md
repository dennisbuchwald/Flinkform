# PerForm

[![WordPress Plugin Version](https://img.shields.io/badge/version-0.1.0-blue)](https://wordpress.org/plugins/perform-forms/)
[![License](https://img.shields.io/badge/license-GPL%20v2-green)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-7.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://php.net/)
[![Status](https://img.shields.io/badge/status-scaffold-orange)](#status)

> "The last form plugin you'll ever install."

Beautiful, native WordPress forms built for the block editor — fast, accessible, free.

## Status

**Pre-MVP scaffold (v0.1.0).** The plugin shell, build pipeline, deployment script and project documentation are in place. The functional form blocks, submissions storage and admin UI are in active development. See [`PERFORM_SPEC.md`](PERFORM_SPEC.md) for the full project specification.

## Vision

PerForm is the form plugin WordPress should have shipped natively. The mission is simple:

- **Beautiful by default** — forms inherit your theme's design tokens via `theme.json`
- **Fast to use** — zero configuration to get a working form on a page
- **Powerful when you need it** — multi-step, conditional logic, webhooks, optional SMTP
- **Free, always** — GPLv2, no premium tier hiding what you need
- **Native** — built with `block.json`, the Interactivity API and WordPress 7.0 DataViews — feels like WordPress core

The standard: if a first-time user cannot build and publish a working contact form in under 5 minutes without reading any documentation, we have failed.

## MVP Scope

See [`PERFORM_SPEC.md`](PERFORM_SPEC.md) §6 for the full breakdown. In short:

- Form Container Block + 11 core field blocks
- Multi-step forms via Page Break blocks (progress bar, per-step validation)
- Submissions in a dedicated DB table with admin list view + CSV export
- Email notifications with merge tags
- Honeypot + time-based spam protection
- Single webhook per form (JSON POST)
- `theme.json` inheritance + basic style panel

## Architecture

- **Rendering:** dynamic blocks with `render.php` (server-side), interactivity via `@wordpress/interactivity`
- **Database:** custom `{prefix}_perform_submissions` table (not WP posts/meta)
- **Admin UI:** WordPress 7.0 native `@wordpress/dataviews`
- **Build:** `@wordpress/scripts`
- **Stack:** WordPress 7.0+, PHP 8.1+, no jQuery, JS budget under 15kb gzipped

See [`PERFORM_SPEC.md`](PERFORM_SPEC.md) §7 for details.

## Development

```bash
# Install dependencies
npm install

# Development with hot reload
npm run start

# Production build
npm run build

# Linting and formatting
npm run lint:js
npm run format
```

### File Structure

```
perform-forms/
├── perform-forms.php    # Main plugin file
├── readme.txt           # WordPress.org documentation
├── readme.md            # GitHub documentation (this file)
├── uninstall.php        # Cleanup on uninstall
├── deploy.sh            # WordPress.org SVN deployment
├── package.json         # Build pipeline (@wordpress/scripts)
├── PERFORM_SPEC.md      # Project specification
├── INITIAL_PROMPT.md    # Onboarding prompt for AI assistants
├── src/                 # Block sources (block.json + JS/SCSS)
├── includes/            # PHP classes (PSR-4 — namespace PerForm\)
├── templates/           # Email templates, block render templates
├── languages/           # Translation files (.po / .mo / .json)
└── build/               # Compiled assets (gitignored)
```

## Standards

- **Accessibility:** WCAG 2.1 AA, keyboard-navigable, screen-reader compatible
- **Performance:** frontend JS bundle < 15kb gzipped, no jQuery
- **Code quality:** WordPress Coding Standards (PHP), ESLint + Prettier (JS)
- **i18n:** all strings translatable from day one
- **Security:** nonces, capability checks, prepared statements, sanitize-in / escape-out

## Compatibility

- WordPress 7.0 or higher
- PHP 8.1 or higher
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Gutenberg / Block Editor (required)

## About dbw media

[dbw media](https://dbw-media.de) is a WordPress agency specializing in custom development, Gutenberg blocks and performance optimization.

## License

GPL v2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

Developed by [dbw media](https://dbw-media.de) — Professional WordPress Development

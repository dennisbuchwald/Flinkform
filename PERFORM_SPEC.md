# Flinkform — Complete Project Specification
> "The last form plugin you'll ever install."

---

## 1. Vision & Mission

Flinkform is a free, open-source WordPress form plugin built for 2026 and beyond. It exists because every existing form solution for WordPress is either too old, too ugly, too expensive, too bloated, or too complex — often all five at once.

The mission is simple: build the form plugin that WordPress should have shipped natively. Beautiful by default. Fast to use. Powerful when you need it. Free, always.

Flinkform is not a feature race against Gravity Forms. It is a UX statement. The goal is not to have more features — it is to make the features it has feel effortless.

**The standard:** If a first-time user cannot build and publish a working contact form in under 5 minutes without reading any documentation, we have failed.

---

## 2. Target Audience

### Primary: The Frustrated WordPress User
- Freelancers and small agencies who build sites for clients
- Has used CF7, WPForms, or Gravity Forms at least once
- Knows what they want but is tired of fighting their tools
- Does not want to pay €59/year just to add conditional logic
- Wants something that looks as good as the rest of their modern site

### Secondary: The Senior WordPress Developer
- Builds custom solutions, needs extensibility
- Wants hooks, filters, REST API, CLI support
- Respects clean code and good architecture
- Will extend and contribute if the foundation is solid
- Needs Block Bindings, custom field types, custom validation

### Non-target (for now)
- Enterprise clients needing HIPAA compliance, complex payment flows, or CRM integrations at scale — this can come later as optional modules

---

## 3. Core Philosophy

### "Zero to form in 5 minutes"
The default experience should require zero configuration. Insert the Form block, it works. No settings required to get a functional, good-looking form on a page.

### Progressive disclosure
Everything advanced is hidden until the user needs it. The editor should feel clean and uncluttered. Power users can go deep; everyone else never has to.

### Native, not bolted on
Flinkform should feel like it was built by the WordPress core team. It uses WordPress APIs, follows WordPress conventions, lives inside the Block Editor as if it were always there. No iframe embeds, no separate admin UIs that look like a different product.

### Beautiful by default, customizable always
Forms inherit the active theme's typography, colors, and spacing automatically. They look right on any site without a single style tweak. For those who want to go further, a full style panel is available.

### Performance is non-negotiable
Forms should add minimal weight to the page. No jQuery. No large JavaScript bundles. The frontend should feel instant.

---

## 4. Feature Set

### 4.1 Form Builder (Block Editor Native)

The entire form-building experience lives inside the WordPress Block Editor (Gutenberg). There is no separate form builder interface — Flinkform is Gutenberg.

**Form Container Block**
The parent block that wraps all form fields. Contains global form settings (submit behavior, notifications, spam protection). Renders as a `<form>` element with all necessary attributes and nonces.

**Field Blocks — Core Set**
Every form field is its own block, nestable inside the Form Container:

- **Text Field** — single-line text input
- **Email Field** — email input with native validation
- **Textarea** — multi-line text, configurable rows
- **Number** — numeric input with min/max/step options
- **Phone** — tel input, optional format mask
- **URL** — url input with validation
- **Select / Dropdown** — single or multi-select, options manageable in block inspector
- **Radio Group** — single choice from multiple options
- **Checkbox Group** — multiple choice
- **Toggle / Single Checkbox** — yes/no, agree to terms style
- **Date** — date picker, configurable format
- **Time** — time picker
- **Date & Time** — combined
- **Rating** — star or number scale rating
- **Range / Slider** — numeric range input with visual slider
- **Hidden Field** — passes data silently (user agent, current URL, custom value, etc.)
- **Section Heading** — visual divider with optional title and description, not a field
- **Page Break** — triggers a new step in multi-step forms (see 4.2)

**Field Block — Shared Settings (Inspector Panel)**
Every field block exposes these in the block inspector:
- Label (text + option to hide visually but keep accessible)
- Placeholder
- Required toggle
- Help text / description
- Field name (the key used in submission data) — auto-generated, overridable
- Default value
- Conditional logic rules (show/hide this field based on other field values)

### 4.2 Multi-Step Forms

Multi-step forms are built by inserting **Page Break blocks** inside the form container. Each Page Break creates a new step.

- **Progress indicator** — choose between: progress bar, step dots, step numbers, or none
- **Step labels** — optional names per step shown in the progress indicator
- **Navigation** — Back / Next buttons, fully customizable label and style
- **Validation per step** — required fields in step 1 are validated before moving to step 2
- **Animated transitions** — subtle, smooth slide or fade between steps
- **Non-linear navigation** — optional: allow jumping back to previous steps freely
- **Completion step** — final step can be a custom thank-you message or a redirect

The experience should feel like Typeform's one-question-at-a-time flow, but adapted naturally for the multi-field format users expect in a WordPress context.

### 4.3 Submissions

All form submissions are stored in a dedicated database table (not as WordPress posts — this is intentional for performance and clean separation of concerns).

**Submission Storage**
- Every submission stored with: form ID, field data (JSON), submission date, IP address (optional, can be disabled for GDPR), user agent, user ID (if logged in), status (read/unread)
- Configurable retention: keep all, keep for X days, don't store at all (webhook-only mode)

**Submissions Admin Panel**
Built using WordPress 7.0's native DataViews/DataForm components, matching the modern wp-admin design language exactly.

- List view with columns: date, form name, status, preview of key fields
- Filters: by form, by date range, by read/unread status
- Full-text search across submission data
- Single submission detail view — all fields displayed cleanly
- Mark as read / unread
- Delete single or bulk
- Export to CSV

### 4.4 Notifications & Email

**Admin Notification**
After every submission, an email is sent to the site admin (or custom address). The email template is configurable:
- To, CC, BCC (static addresses or dynamic — e.g. "send to the email the user entered in field X")
- Subject line with merge tags (e.g. `New submission from {field:name}`)
- Body with all field values listed, or a custom template using merge tags
- Reply-to configurable (e.g. set to the submitter's email)

**Confirmation Email to Submitter**
Optional second notification sent to the person who filled out the form:
- Same merge tag system
- Custom subject and body
- Requires an email field to be present in the form

**Merge Tags**
A clean, simple system for inserting dynamic values into email templates:
- `{field:field-name}` — value of a specific field
- `{form:title}` — the form's title
- `{site:name}`, `{site:url}` — site information
- `{submission:date}`, `{submission:id}` — submission metadata

### 4.5 Conditional Logic

Show or hide any field (or entire steps) based on the values of other fields. The interface should feel as simple as possible — a small set of if/then rules, not a flowchart.

**Rule structure:** `IF [field] [operator] [value] THEN [show/hide this field]`

**Operators:** is, is not, contains, does not contain, is empty, is not empty, greater than, less than

**Logic operators:** AND / OR between multiple rules

Conditional logic applies to:
- Individual fields (show/hide)
- Page Break steps (skip a step entirely)
- The submit button (enable/disable based on conditions)

### 4.6 Spam Protection

- **Honeypot field** — enabled by default, no configuration needed, invisible to humans
- **Time-based check** — submissions faster than X seconds are flagged (bots fill forms instantly)
- **Google reCAPTCHA v3** — optional, requires API key, completely invisible to users
- **hCaptcha** — optional alternative to reCAPTCHA, better for privacy
- **Cloudflare Turnstile** — optional, modern, privacy-respecting CAPTCHA alternative
- **Akismet integration** — optional, for sites already running Akismet

The default (honeypot + time check) should catch the vast majority of spam with zero user friction.

### 4.7 Webhooks

Built-in webhook support — no Zapier account needed for basic integrations.

- **Webhook URL** — where to send the data
- **Method** — POST (default) or GET
- **Payload format** — JSON (default) or form-encoded
- **Custom headers** — for authentication (Bearer tokens, API keys)
- **Field mapping** — choose which fields to send, and optionally rename keys
- **Trigger** — on every submission, or only when conditional rules are met
- **Test button** — sends a sample payload to the URL and shows the response, inline in the block editor
- **Retry logic** — if the webhook fails (non-2xx response), retry up to 3 times with exponential backoff
- **Webhook log** — last N delivery attempts with status and response, visible in the submission detail view

Multiple webhooks per form are supported.

### 4.8 DSGVO / GDPR Compliance

Data protection is not an afterthought — it is built into the architecture from day one. Flinkform must be usable by European sites without any additional configuration or legal risk.

**Data minimisation by default:**
- IP address storage is **optional** and **off by default**. The user explicitly opts in per form if they need it (e.g. for abuse prevention).
- User agent storage is optional, off by default.
- Only the data the user explicitly collects via form fields is stored — nothing else.

**Data retention:**
- Configurable retention period per form: keep forever, keep for X days, or store nothing at all (webhook-only mode, data is forwarded and never persisted in WordPress).
- Automatic deletion of submissions older than the configured period via WP-Cron.

**Right to erasure:**
- Individual submissions can be deleted from the admin panel at any time.
- Bulk delete is supported.
- A WP-CLI command for programmatic deletion is provided.

**Third-party services:**
- No data is sent to any external service by default.
- Optional CAPTCHA providers (reCAPTCHA, Turnstile, hCaptcha) only activate when explicitly configured — and the privacy implications of each are clearly noted in the settings UI.
- When a CAPTCHA service is active, a notice should be shown to guide the operator to update their privacy policy accordingly.

**No cookies by default:**
- Flinkform sets no cookies on the frontend unless a CAPTCHA provider that requires cookies is activated.

### 4.8b Spam Protection & CAPTCHA

**Always-on (zero configuration, zero user friction):**
- Honeypot field — invisible hidden field; bots fill it, humans don't. Submissions with a filled honeypot are silently rejected.
- Time-based check — submissions completed faster than a human could read and fill the form are flagged as bot traffic.

These two layers alone handle the majority of spam without any external service, without cookies, and without DSGVO implications.

**Optional CAPTCHA (operator activates per form or globally):**
The specific CAPTCHA solution is intentionally left open — the choice depends on the operator's privacy posture and legal requirements. Flinkform should support at least two options, with a clean abstraction layer so adding more is straightforward.

Candidate providers to evaluate and implement (in order of privacy-friendliness):
1. **Cloudflare Turnstile** — invisible, no cookies in most cases, GDPR-friendlier than reCAPTCHA
2. **hCaptcha** — privacy-respecting alternative, EU-friendly
3. **Google reCAPTCHA v3** — most widely known, but sends data to Google — operators must disclose this in their privacy policy

The implementation should make the privacy trade-off of each option visible to the WordPress site operator in plain language, not just an API key input field.

**Recommendation note for implementer:** Cloudflare Turnstile is the preferred default CAPTCHA option given its superior privacy posture, but the final decision should be validated against current service terms and DSGVO guidance at time of implementation.

### 4.9 Optional SMTP Module

WordPress's built-in `wp_mail()` function relies on PHP's `mail()` — which is unreliable on most hosting environments and gets flagged as spam. The standard solution is a separate SMTP plugin (WP Mail SMTP has 3M+ installs — the problem is real and universal).

Flinkform includes an optional, built-in SMTP configuration module. It is **not enabled by default** and adds no overhead to sites that don't use it. Activating it takes one click.

**Supported providers with guided setup:**
- Gmail / Google Workspace (OAuth2)
- SMTP2GO
- Mailgun
- SendGrid
- Postmark
- Brevo (Sendinblue)
- Generic SMTP (any provider, manual credentials)

**Features:**
- Test email — send a test to any address and confirm delivery
- Connection status indicator in the admin
- Configures `wp_mail()` globally — affects all WordPress emails, not just Flinkform
- From name and From email configurable

This module positions Flinkform as "the last plugin you need for forms AND email" — reducing plugin bloat on WordPress sites significantly.

### 4.9 Styling & Theming

**Default behavior:** Flinkform forms inherit the active theme's design tokens automatically. Colors, fonts, border-radius, spacing — all pulled from theme.json if available. Forms look native on any site with zero configuration.

**Style Panel (in block inspector):**
- Primary color (submit button, focus states, progress bar)
- Field border style (none / underline / full border)
- Border radius
- Field spacing
- Label position (above, beside, floating/animated)
- Font size override
- Error message style

**Layout options:**
- Single column (default)
- Two-column grid (fields can span 1 or 2 columns)
- Full-width fields toggle

**Dark mode:** Automatic via CSS `prefers-color-scheme` media query. No extra configuration.

**Custom CSS:** A plain text area in form settings for arbitrary CSS scoped to that specific form.

### 4.10 Developer Features

Flinkform is built to be extended. The developer experience is a first-class concern.

**PHP Action & Filter Hooks:**
- `perform_before_submission` — runs before a submission is saved, can cancel it
- `perform_after_submission` — runs after a submission is saved, receives the submission data
- `perform_submission_data` — filter to modify submission data before saving
- `perform_email_notification` — filter to modify email before sending
- `perform_register_field_types` — register custom field types
- `perform_validate_field` — custom validation logic per field type or field name
- `perform_webhook_payload` — filter the webhook payload before sending

**Custom Field Types:**
Developers can register custom field block types that integrate natively into the Flinkform ecosystem — they appear in the block inserter alongside core fields and support all shared settings (required, conditional logic, etc.).

**Block Bindings API Integration:**
Form field values can be bound to WordPress post meta, custom fields, or external data sources using the native WP 7.0 Block Bindings API. This enables pre-populated forms, edit forms for existing data, and complex dynamic workflows.

**REST API:**
- `GET /wp-json/flinkform/v1/forms` — list forms
- `GET /wp-json/flinkform/v1/forms/{id}` — get form config
- `POST /wp-json/flinkform/v1/forms/{id}/submissions` — submit a form (for headless/custom frontends)
- `GET /wp-json/flinkform/v1/forms/{id}/submissions` — list submissions (authenticated)
- `GET /wp-json/flinkform/v1/submissions/{id}` — get single submission (authenticated)

**WP-CLI Commands:**
- `wp perform forms list`
- `wp perform submissions list --form=<id>`
- `wp perform submissions export --form=<id> --format=csv`
- `wp perform test-webhook --form=<id>`

---

## 5. Non-Features (Intentional Omissions for v1)

These are explicitly out of scope for the initial release. They may come later as optional modules or future versions.

- **Payments / Stripe / PayPal** — this is a separate product category
- **Quiz / score-based forms** — possible future module
- **File uploads** — technically complex, hosting-dependent, post-MVP
- **Signature fields** — post-MVP
- **Conversational / one-question-at-a-time mode** (Typeform-style) — post-MVP, would be a separate layout option
- **Native CRM integrations** (HubSpot, Salesforce, etc.) — covered by webhooks for now
- **Form analytics / conversion tracking** — post-MVP
- **A/B testing** — post-MVP
- **Zapier/Make official integration** — webhooks cover this already; official Zap can come later
- **Multisite network support** — post-MVP
- **User-submitted content / front-end post creation** — different plugin territory

---

## 6. MVP Scope

The Minimum Viable Product is the smallest version of Flinkform that delivers on the core promise: beautiful, native, modern WordPress forms that are faster and more pleasant to use than anything else available.

### MVP Must-Haves

**Form Builder**
- Form Container Block
- Field blocks: Text, Email, Textarea, Number, Select, Radio Group, Checkbox Group, Toggle, Hidden Field, Page Break, Section Heading
- All shared field settings (label, placeholder, required, help text, field name)
- Basic styling that inherits from theme.json

**Multi-Step**
- Page Break block creates steps
- Progress bar
- Back/Next navigation
- Per-step validation

**Submissions**
- Custom DB table storage
- Basic admin list view (can be a simple WP_List_Table if DataViews integration takes too long)
- Single submission detail view
- Read/unread status
- Delete
- CSV export

**Notifications**
- Admin email notification after submission
- Basic merge tags: `{field:name}`, `{site:name}`, `{submission:date}`
- Configurable To, Subject, Body

**Spam Protection**
- Honeypot (always on)
- Time-based check (always on)

**Webhooks**
- Single webhook URL per form
- POST with JSON payload
- Test button

**Styling**
- theme.json inheritance
- Basic style panel: primary color, border style, border radius

### MVP Nice-to-Haves (include if time allows)
- Conditional logic (simple show/hide)
- reCAPTCHA v3 support
- Confirmation email to submitter
- Multiple webhooks per form

### Post-MVP (v1.1+)
- Full conditional logic (step skipping, submit button logic)
- Full Submissions DataViews panel (WP 7.0 native)
- SMTP module
- File upload field
- Block Bindings API integration
- REST API (full)
- WP-CLI commands
- hCaptcha / Turnstile
- Rating and Range fields
- Advanced styling panel
- Date/Time fields

---

## 7. Architecture Directions

> Note: These are directions and recommendations, not final decisions. The implementing developer should evaluate and finalize based on current WordPress best practices and any new information.

### Rendering
**Recommended direction:** Dynamic blocks with `render.php` for server-side rendering. The form HTML is generated in PHP and sent to the browser ready-to-read — no JavaScript required to render the form. This is good for performance, SEO, and accessibility.

Frontend interactivity (multi-step navigation, real-time validation, AJAX submission) is added on top using the **WordPress Interactivity API** (`@wordpress/interactivity`). This is the current WordPress-native standard for block interactivity and avoids shipping a custom JavaScript framework.

### Block Registration
**Recommended direction:** Use the new WP 7.0 PHP-only block registration where appropriate (simpler field types), and the standard `block.json` + build pipeline approach for more complex blocks that need rich editor interfaces.

### Database
**Recommended direction:** A dedicated custom table for submissions (`{prefix}_perform_submissions`) rather than using WordPress posts/meta. This is better for performance at scale, easier to query, and keeps the WP posts table clean.

Schema direction:
```
id, form_id, data (JSON), created_at, ip_address, user_agent, user_id, status
```

### Admin UI
**Recommended direction:** Use WordPress 7.0's native `@wordpress/dataviews` package for the submissions list view. This matches the modern wp-admin design language and provides filtering, sorting, and grouping for free.

### SMTP Module
**Recommended direction:** Implement as a separate, optionally-loaded class. When inactive, it adds zero overhead. When active, it hooks into `phpmailer_init` to configure the SMTP connection — the standard WordPress way to do this.

### File Structure
**Recommended direction:** Follow modern WordPress plugin conventions:
- `/src` — JavaScript/TypeScript source for blocks
- `/build` — compiled assets
- `/includes` — PHP classes
- `/templates` — email templates, block render templates
- `perform.php` — main plugin file
- `block.json` files co-located with each block's source

---

## 8. Quality Standards

- **Accessibility:** All forms must be fully keyboard-navigable and screen-reader compatible. WCAG 2.1 AA is the minimum standard. Labels are always associated with inputs. Error messages are announced via `aria-live`.
- **Performance:** The frontend JavaScript bundle should be under 15kb gzipped. No jQuery dependency.
- **Code quality:** Follows WordPress Coding Standards for PHP. ESLint + Prettier for JavaScript.
- **Compatibility:** WordPress 7.0+, PHP 8.1+. No support for older versions — we use modern APIs intentionally.
- **Internationalisation:** All strings wrapped in WordPress i18n functions from day one. The plugin should be fully translatable before the first release.
- **Security:** All inputs sanitized, all outputs escaped. Nonce verification on all form submissions. Capability checks on all admin actions. Prepared statements for all DB queries.

---

## 9. Positioning & Go-to-Market

**Primary channel:** WordPress.org Plugin Directory (free)
**Plugin slug:** `flinkform` (preferred) or `flinkform-wp`
**Target review score:** 4.8+ stars

**Differentiation in the directory listing:**
- Screenshots showing the beautiful block editor experience
- The "zero configuration" story front and center
- Comparison callout: works natively with WordPress 7.0 block editor

**Secondary channels:**
- GitHub (open source, community contributions welcome)
- Twitter/X and developer communities (r/WordPress, WP Tavern, Post Status)
- Product Hunt launch

**Brand voice:** Direct, a little opinionated, confident without being arrogant. We know the current state of WordPress forms is frustrating and we're not pretending otherwise. But we're not mean about it — we're just fixing it.

---

## 10. Success Metrics (Year 1)

- 10,000 active installs within 6 months of launch
- 4.8+ star rating on WordPress.org
- Less than 2% support ticket rate (forms just work)
- Mentioned as a recommended alternative in at least 3 major WordPress publications
- Community contributions / pull requests from outside the core team

---

*Document version: 1.0 — May 2026*
*This spec is intended as a briefing document for initial implementation. Technical decisions should be validated against current WordPress 7.0 documentation and core team recommendations before finalizing.*

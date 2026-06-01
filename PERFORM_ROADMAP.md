# PerForm — MVP Build Roadmap
> How we build it, in what order, and why.

---

## How to use this document

This roadmap defines the exact sequence in which PerForm is built. Each phase has a clear goal, a definition of "done", and a list of what is explicitly NOT included yet.

**The rule:** A phase must be complete, tested, and working before the next phase begins. No exceptions. This is not a waterfall — if something needs to change mid-phase, change it. But do not start Phase 3 while Phase 2 is half-finished.

After each phase: the developer (or owner) reviews the result on a real WordPress installation before continuing.

---

## Phase 0 — Plugin Foundation
**Goal:** A valid, installable WordPress plugin that does nothing yet, but is structured correctly and ready to build on.

### What gets built:
- Plugin directory and main entry file (`perform-forms.php`) with correct plugin header (Name, Description, Version, Author, Text Domain, Requires at least: WordPress 7.0, Requires PHP: 8.1)
- Autoloader for PHP classes (PSR-4 via Composer, or a simple custom autoloader)
- Basic class structure: `Plugin` (bootstrap), `Activator`, `Deactivator`
- `composer.json` and `package.json` set up
- Build pipeline configured (`@wordpress/scripts`) — `npm run build` and `npm run start` work
- Plugin activates and deactivates without errors
- Text domain registered, ready for translations
- `README.md` and `readme.txt` (WordPress.org format) scaffolded with placeholder content
- `.gitignore` set up correctly (excludes `node_modules`, `vendor`, `build` artifacts)

### Definition of done:
Plugin appears in the WordPress admin plugins list. Activate/deactivate works. No PHP errors. `npm run build` completes without errors.

### Not included yet:
Everything else.

---

## Phase 1 — The Form (It Works)
**Goal:** A real form that a user can add to a page via the Block Editor, fill out, and submit. Data goes somewhere. This is the proof of concept.

### What gets built:

**Blocks:**
- `perform/form` — Form Container block. Wraps all fields. Renders as a `<form>` element with nonce, action, hidden form ID field. Block inspector shows: form title (internal), submit button label.
- `perform/field-text` — Single-line text input. Inspector: label, placeholder, required toggle, help text.
- `perform/field-email` — Email input. Inspector: same as text + basic email format validation.
- `perform/field-textarea` — Multi-line text. Inspector: same as text + rows setting.

**Frontend rendering:**
- All blocks render via `render.php` (dynamic blocks). No JavaScript required to display the form.
- Form submits via standard POST to `admin-post.php` (or a REST endpoint — implementer decides what is cleaner for the architecture).
- Server-side validation: required fields, email format.
- On success: show a configurable success message (default: "Thank you! Your message has been sent.") — set in the Form block inspector.
- On error: re-render the form with inline error messages next to the failing fields.
- Nonce verification on every submission. No nonce = rejected silently.

**Spam protection (always on, no configuration):**
- Honeypot hidden field injected automatically into every form render.
- Time-based check: timestamp injected on render, checked on submit. Submissions under 2 seconds are rejected.

**Basic submissions storage:**
- Create custom DB table on plugin activation: `{prefix}_perform_submissions` with columns: `id`, `form_id`, `data` (JSON), `created_at`, `status` (read/unread).
- Every valid submission is saved to this table.
- IP address and user agent: NOT stored at this stage (privacy by default).

### Definition of done:
A user can open the Block Editor, add a Form block, add Text + Email + Textarea fields, publish the page, fill out the form, submit it, see the success message, and the submission appears in the database. PHP errors: zero.

### Not included yet:
Admin UI for submissions, email notifications, styling, more field types, multi-step, webhooks, CAPTCHA.

---

## Phase 2 — More Fields + Submissions Admin
**Goal:** A complete set of basic field types, and a place in wp-admin to see what was submitted.

### What gets built:

**Additional field blocks:**
- `perform/field-number` — numeric input, inspector: min, max, step
- `perform/field-select` — dropdown, inspector: options editor (add/remove/reorder options), single vs. multi-select toggle
- `perform/field-radio` — radio button group, inspector: options editor
- `perform/field-checkbox` — checkbox group, inspector: options editor
- `perform/field-toggle` — single checkbox (agree to terms style), inspector: label, required
- `perform/field-hidden` — hidden input, inspector: field name, value (static text, or dynamic: current URL, current user ID, current date)
- `perform/section-heading` — not a field, just a visual divider with optional title and description text

**Submissions admin panel:**
- Menu item in wp-admin: "PerForm" → "Submissions"
- List view using `WP_List_Table` (DataViews can be a Phase 4+ upgrade): columns = date, form name, status (read/unread), short preview of first text field
- Clickable row → single submission detail view showing all field labels and their submitted values, formatted cleanly
- Mark as read / unread (bulk action + per-row action)
- Delete single submission (with confirmation)
- Bulk delete
- Basic filter: by form (dropdown at top of list)
- CSV export: button that downloads all submissions for the selected form as a `.csv` file

**Forms admin panel:**
- Menu item: "PerForm" → "Forms"
- Simple list of all forms that exist (pulled from the blocks registered in posts/pages)
- Note: forms are not stored separately — they live as blocks inside posts. This list is a convenience view derived from scanning block content. Implementer should decide the best approach (e.g. a registered post type `perform_form` vs. scanning post content). Recommendation: a lightweight `perform_form` CPT that stores form config separately from the page it's embedded on — this makes the submissions list and form management much cleaner. Final decision to implementer.

### Definition of done:
All field blocks work in the editor and on the frontend. Submissions admin shows all submissions, the detail view is readable, delete and export work. Zero PHP errors or JS console errors.

### Not included yet:
Email notifications, styling panel, multi-step, conditional logic, webhooks, CAPTCHA options.

---

## Phase 3 — Email Notifications
**Goal:** When someone submits a form, the site admin gets an email. The submitter can optionally get a confirmation email.

### What gets built:

**Merge tag system:**
A simple, extensible system for inserting dynamic values into email templates.
- `{field:field-name}` — value of a specific field from the submission
- `{form:title}` — internal title of the form
- `{site:name}` — WordPress site name
- `{site:url}` — WordPress site URL
- `{submission:date}` — date and time of submission
- `{submission:id}` — the submission's database ID
- Merge tags should be resolved via a central helper function so they can be reused for webhooks, future integrations, etc.

**Admin notification email:**
Configured in the Form block's inspector panel, under a "Notifications" section (collapsed by default):
- Toggle: "Send admin notification" (on by default)
- **To: text field, defaults to `{site:admin_email}`, supports merge tags** — this is the per-form recipient address requested by the community (so the contact form goes to sales@, the support form goes to support@, etc.). Multiple recipients via comma-separated list.
- Subject: text field, default: `New submission: {form:title}`
- Message: textarea, default: a clean list of all fields and their values, auto-generated. Can be customised with merge tags.
- Reply-To: optional, can be set to `{field:email}` to make replying to the notification reply to the submitter

**Confirmation email to submitter:**
Configured in the same "Notifications" section:
- Toggle: "Send confirmation to submitter" (off by default)
- Email field: select which form field contains the submitter's email address (dropdown of email-type fields in the form)
- Subject, Message: same merge tag system

**Email sending:**
Uses WordPress's `wp_mail()` — works with the default PHP mail and with any SMTP plugin the user has installed. PerForm does not configure SMTP at this phase (that is Phase 7+). The optional SMTP module comes later.

**Post-submit behaviour (per form):**
Configured in the Form block's inspector panel, under a new "After Submit" section (always visible):
- Behaviour selector: `Show success message` (default, current Phase 1 behaviour) / `Redirect to URL`
- Success message: textarea (only shown when behaviour = message). Default: "Thank you! Your message has been sent."
- Redirect URL: text input (only shown when behaviour = redirect). Accepts absolute or relative URLs. Validated server-side via `wp_validate_redirect()` against an allowlist (site host by default) to prevent open-redirect abuse.
- Append-submission-id toggle (only when redirect): when on, the handler appends `?perform_submission={id}` to the redirect URL so the thank-you page can read the submission via shortcode or query var (useful for "Thank you, [name]" personalisation without leaking field data through the URL).

This unblocks **conversion tracking** (GA4, Meta Pixel, Plausible, Matomo, etc.) — every analytics platform fires conversion events on a dedicated thank-you-page pageview. Without a redirect option, none of those integrations work without custom JS.

### Definition of done:
Submit a form → admin receives an email with all submitted values formatted clearly → if enabled, submitter receives a confirmation email. Merge tags resolve correctly. No emails sent on spam-detected submissions. Switching the form's after-submit behaviour to "Redirect" sends the user to the configured thank-you page on success. Invalid/external redirect URLs fall back to the success message and log a notice.

### Not included yet:
Styling, multi-step, conditional logic, webhooks, CAPTCHA options, SMTP module.

---

## Phase 4 — Styling & Theme Integration
**Goal:** Forms look good on any WordPress theme with zero configuration, and power users can customise the visual style.

### What gets built:

**theme.json inheritance (automatic):**
- Form fields pick up the active theme's font family, font size, border radius, color palette, and spacing scale automatically via CSS custom properties.
- No configuration needed. A form dropped into a Twenty-Twenty-Five or Blocksy site should look native immediately.

**Style panel (in Form block inspector):**
Collapsed by default under a "Style" section:
- Primary color — used for submit button background, focus ring, progress bar, checked states. Defaults to theme primary color if available.
- Field style — choice of three options: Bordered (full border around each input), Underline (just a bottom border), Minimal (no visible border, relies on background color difference)
- Border radius — slider or input (affects fields and submit button together)
- Field spacing — compact / normal / relaxed
- Label position — Above field (default) / Beside field (inline layout) / Floating (animated label that moves above field on focus)
- Submit button style — primary color fill (default) / outline / ghost

**Responsive layout:**
- Single column by default, full width on mobile. No extra work required.
- Two-column option: Form block has a layout toggle (1 column / 2 columns). Individual field blocks can be set to span full width.

**Dark mode:**
- Automatic via `@media (prefers-color-scheme: dark)`. Form adapts without any setting.

**Custom CSS:**
- Text area in the Form block inspector for arbitrary CSS scoped to that specific form instance (using a generated form ID as the scope selector).

### Definition of done:
A form looks clean and native on at least three different popular WordPress themes (e.g. Twenty-Twenty-Five, Kadence, Astra) without any style settings changed. The style panel controls all work visually. Dark mode renders correctly. Mobile layout is usable.

### Not included yet:
Multi-step, conditional logic, webhooks, CAPTCHA options, SMTP module.

---

## Phase 5 — Multi-Step Forms
**Goal:** A user can build a multi-step form (wizard / funnel style) by inserting Page Break blocks, with progress indication and per-step validation.

### What gets built:

**`perform/page-break` block:**
- Inserted between field blocks inside the Form container.
- In the editor: renders as a visible divider with a step label (e.g. "Step 2: Contact Details") — makes the step structure visually clear while editing.
- Inspector: optional step label (shown in progress indicator on frontend).

**Frontend multi-step behaviour (Interactivity API):**
- Only the current step is visible. Previous and next steps are hidden (not removed from DOM — hidden via CSS/state, so server-rendered content is preserved).
- "Next" button validates all required fields in the current step before advancing. If validation fails, errors are shown inline and the step does not advance.
- "Back" button navigates to the previous step without re-validating.
- State managed via the WordPress Interactivity API store — no custom JavaScript framework.
- Animations: a simple, subtle slide or fade transition between steps. Should feel smooth, not flashy.

**Progress indicator:**
- Configurable in Form block inspector: Bar (default) / Dots / Step numbers (1 of 3) / None
- Optional step labels shown below/above the indicator.
- Progress updates reactively as the user moves through steps.

**Final step behaviour:**
- The submit button only appears on the last step.
- After successful submission, the entire form is replaced with the success message (or redirect triggers).

### Definition of done:
A multi-step form with 3 steps works end-to-end. Navigation works. Per-step validation works. Progress bar updates correctly. The submission captures all fields from all steps. Keyboard navigation between steps works. Zero layout shift during transitions.

### Not included yet:
Conditional logic (step skipping), webhooks, CAPTCHA options, SMTP module.

---

## Phase 6 — Webhooks
**Goal:** When a form is submitted, the data can automatically be sent to an external URL. This enables integration with Zapier, Make, Airtable, Notion, and any other service without a dedicated plugin.

### What gets built:

**Webhook configuration (in Form block inspector, "Integrations" section):**
- Webhook URL — text input
- HTTP Method — POST (default) / GET
- Payload format — JSON (default) / form-encoded
- Custom headers — repeatable key/value pairs (for Authorization tokens, API keys, etc.)
- Field mapping — optional: choose which fields to include in the payload, and optionally rename their keys (e.g. send `first_name` instead of `field-text-1`)
- Trigger condition — "On every submission" (default) / "Only when [field] [operator] [value]" (simple single-condition rule)
- Active/inactive toggle — disable without deleting

**Webhook delivery:**
- Delivery happens asynchronously via WP-Cron (immediately scheduled after submission) — does not block the form submission response.
- If delivery fails (non-2xx response or timeout): retry up to 3 times with exponential backoff (1 min, 5 min, 30 min).
- Each delivery attempt is logged: timestamp, HTTP status code, response body (truncated), success/failure.

**Test button:**
- In the Form block inspector, a "Send test" button sends a sample payload (using placeholder values for each field) to the configured URL and shows the HTTP response status and body inline. No submission is created.

**Webhook log:**
- Accessible from the submission detail view: shows last N delivery attempts for that specific submission with status and truncated response.
- Also accessible from a global "Webhook Log" tab in the PerForm admin area for a bird's-eye view.

**Multiple webhooks:**
- Multiple webhook configurations per form are supported (add/remove in inspector).

### Definition of done:
Configure a webhook to a test endpoint (e.g. webhook.site). Submit a form. The payload arrives at the endpoint with the correct fields and values within a few seconds. A failed webhook retries correctly. The test button sends a payload and shows the response. The log shows the delivery attempt.

### Not included yet:
CAPTCHA options, SMTP module, conditional logic.

---

## Phase 7 — Conditional Logic
**Goal:** Fields (and steps) can be shown or hidden based on what the user has entered in other fields. This is the feature that unlocks dynamic, personalised forms.

### What gets built:

**Conditional rules on field blocks:**
Each field block gets a "Conditional Logic" section in its inspector (collapsed by default):
- Toggle: "Show this field only when..." (off by default — field always shows)
- Rule builder: `IF [field selector dropdown] [operator dropdown] [value input]`
- Operators: is / is not / contains / does not contain / is empty / is not empty / greater than / less than
- Multiple rules: AND / OR logic between rules (add rule button)
- Default behaviour when no condition is met: hidden (not just invisible — removed from submission data too)

**Conditional rules on Page Break blocks:**
- Same rule builder available on page break blocks.
- When a step's condition is not met, the step is skipped entirely (user goes directly from step N to step N+2).
- Fields inside a skipped step are excluded from validation and from the submission data.

**Frontend behaviour (Interactivity API):**
- Field visibility updates reactively as the user types / selects — no page reload, no debounce delay.
- Hidden fields are excluded from the submitted data server-side (even if a user manipulates the DOM, the server re-evaluates conditions before saving).
- Server-side condition re-evaluation is required for security — client-side is UX, server-side is truth.

**Conditional submit button:**
- The Form block inspector allows an optional condition on the submit button: "Only enable submit when [rule]". Useful for "I agree to terms" checkboxes.

### Definition of done:
A form with 5 fields where field 3 only shows when field 1 equals a specific value. Field 3 hides/shows instantly as the user types in field 1. If field 3 is hidden, its value does not appear in the saved submission. A multi-step form with a step that is conditionally skipped works correctly.

### Not included yet:
SMTP module, CAPTCHA options (optional, can be added any time from Phase 1 onwards as a parallel track).

---

## Phase A — SMTP Module (Basic Auth)
**Goal:** Site operators can route plugin emails (admin notification + submitter confirmation) through their own SMTP provider without needing a separate WP Mail SMTP plugin. Inserted between Phase 7 and Phase 8 because real-world deployments need it before launch — a contact form that only ships via PHP mail loses ~30% of its mails to spam filters.

**Scope decision (2026-05-26):** Phase A ships **Basic Auth + App-Passwords** only. OAuth2 for Google Workspace / Microsoft 365 is **Phase D** (post-launch), so we don't block Phase B (CAPTCHA) and Phase C (Thank-You-Redirect) on the much larger OAuth integration. Owner-confirmed; full reasoning lives in commit `<TBD A-a>` body.

### What gets built (split across A-a → A-c slices):

**Slice A-a ✅ shipped — Settings page + storage + crypto helper:**
- New admin page `PerForm → SMTP` (`SmtpPage::SLUG = 'perform-smtp'`) wired into `Admin\Menu`. Same `render()` + `dispatch()` controller pattern used by SubmissionsPage / FormsPage / WebhookLogPage.
- Single autoloaded `wp_options` key `perform_smtp_settings` holds the full config array (`enabled`, `provider`, `host`, `port`, `encryption`, `auth`, `username`, `password`, `from_email`, `from_name`).
- New `PerForm\Settings\Secret` helper — AES-256-CBC + random IV + key derived from `wp_salt('auth')`. Cipher format `perform_enc_v1:<base64(iv||ciphertext)>` (versioned for future cipher upgrades). Reused by all future modules that persist secrets (Phase B CAPTCHA secrets, Phase D OAuth refresh tokens).
- Provider-preset dropdown with auto-fill JS for: Custom · Gmail (App-Password) · Outlook.com · SendGrid · Mailgun · Brevo · Postmark · Amazon SES. Two intentionally-disabled entries (Microsoft 365, Google Workspace) point operators at Phase D's OAuth2 release.
- Password field renders empty on page reload + treats "empty submit" as "keep the existing cipher" — plaintext never round-trips through the browser.

**Slice A-b ✅ shipped — PHPMailer hook + plugin-conflict detection:**
- New class `PerForm\Smtp\Transport` (separate namespace from `Notifications\Mailer` — "what to send" vs. "how to send" stay split at the namespace level).
- `phpmailer_init` callback at priority 1000 — applies the stored config to the active PHPMailer instance only when `enabled = true` AND no known SMTP plugin is active AND a usable host/port/(plaintext password) is available.
- Encryption mapping: `tls` → `ENCRYPTION_STARTTLS`, `ssl` → `ENCRYPTION_SMTPS`, `none` → opt-out (also disables `SMTPAutoTLS` so opportunistic STARTTLS doesn't sneak back in).
- Conflict detection against `wp-mail-smtp/wp_mail_smtp.php`, `fluent-smtp/fluent-smtp.php`, `easy-wp-smtp/easy-wp-smtp.php`, `post-smtp/postman-smtp.php` via `get_option('active_plugins')` (front-end safe — `is_plugin_active()` lives in wp-admin only). Detected conflict self-disables the override and renders an admin notice on every wp-admin page.
- `wp_mail_from` / `wp_mail_from_name` filter overrides honour the `from_email` / `from_name` settings. All three hooks registered through `add_action('init', …)` so any `__()` call inside the callbacks is i18n-timing-safe.
- Conflict-detection result is memoised on the Transport instance (one `active_plugins` fetch per request even when multiple mails go out).

**Slice A-c ✅ shipped — Live diagnostic status block:**
- Refactored `Transport::detect_conflict()` to `public static` so the settings page can call it without an instance, and added `Transport::get_status()` returning a 5-field diagnostic snapshot (`transport_loaded`, `enabled`, `configured` + reason, `conflict`, `effective`).
- New status block at the top of the SMTP settings page with traffic-light badges per row + a single bold "Effective: Active / Inactive" verdict at the bottom answering "will the next wp_mail() actually route via PerForm?".
- Partial-upload defense: if `class_exists(Transport::class)` is false (e.g. operator uploaded only the new SmtpPage.php but not the new Transport.php), the block renders a single red row pointing at the missing file instead of fataling.
- Effective-state border highlight (green = active, red = inactive) so the verdict is visible without reading any text.

**Slice A-d ✅ shipped — Test email button + result notice + last-test history:**
- "Send test email" button as its own POST form below the status block — separate nonce (`perform_smtp_test`) so the test action can never accidentally save unsaved settings.
- Error capture via the `wp_mail_failed` action hook — when wp_mail() returns false, we read the PHPMailer-level error string out of the WP_Error and surface it inline (truncated to 500 chars in persistence, full text in the post-redirect transient notice).
- Per-user transient (`perform_smtp_test_result_{user_id}`) carries the result through the post-save redirect; auto-cleared on render so a page refresh never replays a stale notice.
- Last-test history persisted in `wp_options` (`perform_smtp_last_test`) — feeds the new "Last test" row of the status block (timestamp, recipient, success/fail, truncated error). Never-tested state shows neutral "Never" + hint.
- Recipient is always the current admin user's WordPress profile email; no separate "send to" input, keeps the test single-purpose.
- Test button stays visible even when SMTP is inactive — operator gets a yellow "test will fall through to WordPress default" warning so the behaviour is intentional, not surprising. Useful for verifying the fallback path too.

### Definition of done (Phase A — complete):
Operator configures SMTP credentials on a sandbox WordPress, ticks the enable toggle, clicks "Send test email", and the mail arrives via the configured provider (verified by checking the `Received:` headers). Other SMTP plugin installed → PerForm SMTP self-disables and shows a notice. Form submission triggers the admin notification through the configured SMTP. The status block above the form continuously shows whether the override is effective; the "Last test" row records the most recent verification.

### Not included (Phase D — post-launch):
OAuth2 for Workspace / M365, multi-account configs per form, OAuth-token rotation logic, hosted OAuth-proxy decision.

---

## Phase B — CAPTCHA Module (Cloudflare Turnstile + hCaptcha + reCAPTCHA v3)
**Goal:** Form builders can enable CAPTCHA per form. Cloudflare Turnstile is the recommended default (GDPR-friendly, no cookies in most flows).

### Slice plan (revised 2026-05-26 after Phase A complete):

Owner-chosen strategy: **built-in challenge first, external providers later** as opt-in. The built-in challenge is zero-setup, requires no third-party account, and is 100% GDPR-clean (no external calls, no cookies, no third-party scripts). Mainstream provider integration comes after the in-house path is solid.

**Slice B-a ✅ shipped — Built-in challenge end-to-end (zero setup, default-on):**
- New `PerForm\Spam\Challenge` — HMAC-signed JSON token format (`v|f|s|d|a|e|n`, base64-url-encoded + dot-separated HMAC), 5-minute TTL, single-use replay protection via transient. HMAC key derives from `wp_salt('auth')` matching `Settings\Secret`.
- Two strategies share the same token: Proof-of-Work (default, ~18 bits = ~50–500 ms transparent in browser via `crypto.subtle.digest`) + Math fallback (visible "what is 3 + 7?" for no-JS visitors; answer is sha256-hashed against the salt so brute-force isn't viable within the TTL).
- New `PerForm\Spam\Renderer` — emits the challenge markup (`<input type="hidden" name="perform_spam_token">` + solution input + math fallback row) into the form. Markup carries the data attributes the PoW solver needs (`data-perform-pow-salt`, `data-perform-pow-difficulty`).
- New `PerForm\Spam\Guard` — façade (`should_protect()` + `verify_submission()`) the Submissions\Handler + form-container/render.php call. Resolves the form's `spamProtection` block attribute (`'auto' | 'builtin' | 'none'`) into an active strategy. Today only `'builtin'` and `'none'` are meaningful; B-b will extend the switch to `'turnstile' | 'hcaptcha' | 'recaptcha-v3'`.
- `form-container` block gets a new `spamProtection` attribute (default `'auto'`), inspector panel "Spam Protection" with strategy select + warning when set to `'none'`.
- Frontend PoW solver appended to `form-container/view.js` (+1 KiB minified, ~3 KB gzipped total). Runs on main thread with `await setTimeout(0)` yield every 1024 iterations to keep UI responsive; hides the math row via the `hidden` attribute the moment a solution is found.
- Hook in `Submissions\Handler::handle()` between time-check and field validation: a failed challenge results in silent_reject() the same way honeypot hits do. Honeypot + time-check from Phase 1 still apply even when `spamProtection='none'` (defense in depth).
- Existing forms in the wild inherit `spamProtection='auto'` via the block-attribute default — protection activates on next form render, no migration needed.

**Phase B — closed at B-a (2026-05-26).** Owner-decision after B-a verification: the built-in challenge alone covers the realistic threat model for PerForm's audience (small-to-mid-sized German Mittelstand WordPress sites doing contact / lead / newsletter forms). External providers (Cloudflare Turnstile / hCaptcha / reCAPTCHA v3) are valuable mainly for high-profile sites with targeted attackers — not the launch audience. Moved to **Phase D** (post-launch) so we can ship v0.1 faster, keep the DSGVO-story unconditionally clean (no third-party scripts, no consent banners, no privacy-policy disclosure logic), and minimise WP.org-review friction (plugins without external service dependencies pass easier).

The architecture in B-a is already prepared for B-b/B-c when they ship as part of Phase D: `Guard::resolve_strategy()` is the single extension point, the form-container `spamProtection` attribute is already an enum (`'auto' | 'builtin' | 'none'` today; `'turnstile' | 'hcaptcha' | 'recaptcha-v3'` plug in cleanly).

---

## Phase C — Thank-You-Redirect Enhancement ✅ shipped
**Goal:** Form builders can set a per-form success-redirect URL (the Phase-3 spec promised it but the code only had source-page redirect with `?perform_status=success`). Phase C delivers the actual implementation + the conversion-tracking enhancements.

### Audit finding (Phase 3 was incomplete):
Phase 3's roadmap listed "Behaviour selector: Show message / Redirect to URL" and "Append-submission-id toggle" — but the form-container block.json never received a `successRedirectUrl` or `afterSubmit` attribute, and `Submissions\Handler::redirect_success()` only had the source-page redirect path. Phase C therefore implements the redirect path from scratch instead of polishing an existing one.

### What shipped (single commit, both slices folded together):

**Block-attribute `afterSubmit`** on `form-container`:
```
{ * 	behaviour:          'message' | 'redirect',   // default 'message'
	redirectUrl:        string,                    // default ''
	appendSubmissionId: bool,                      // default false
	appendFormId:       bool,                      // default false
}
```

**Inspector — new "After Submit" panel** (collapsed by default, sits between "Form Settings" and "Style"):
- `ToggleGroupControl` "On successful submission" with `Show message` / `Redirect to URL`.
- When `Show message`: TextareaControl for `successMessage` (moved out of "Form Settings" panel so the after-submit configuration is colocated).
- When `Redirect to URL`: `URLInput` with WordPress's built-in page autocomplete; the help text states "Same-origin only" + that external URLs are silently rejected by the safe-redirect filter.
- When `Redirect to URL`: `ToggleControl` for `appendSubmissionId` (adds `?perform_submission_id=N`) and `appendFormId` (adds `?perform_form_id=UUID`), both default-off because submission IDs are PII-adjacent.

**`Submissions\Handler::redirect_success()` rewritten** to accept the form attributes + submission id and route in this order:
1. If `afterSubmit.behaviour === 'redirect'` and a non-empty `redirectUrl` is set, build the URL with optional metadata query args and run through `wp_validate_redirect()`. Same-origin URLs pass through; external URLs return `''` from the filter and we fall through to step 2 (no surprise blank pages).
2. Legacy fallback — source-page redirect with `?perform_status=success` (used by message-mode forms and as the safe landing for rejected external URLs).

**`build_redirect_url()` helper** — appends the metadata args only when configured AND the author didn't hand-roll them into the URL already (defends against `?perform_submission_id=1&perform_submission_id=2` double-args).

**Honeypot + time-check redirects intentionally use the legacy path** — a bot that hits the honeypot doesn't get to learn the configured thank-you URL.

### Definition of done:
A form with `Redirect to URL` set to `/danke?utm_source=contact` and both metadata toggles on submits successfully → visitor lands on `/danke?utm_source=contact&perform_submission_id=42&perform_form_id=abc-...`. Same form with an external URL (`https://example.com/danke`) submits successfully → visitor lands on the source page with `?perform_status=success` (safe-redirect filtered the external URL, but we didn't blank-page the visitor). Honeypot hit on the same form → still source-page success redirect, no leak of the configured thank-you URL.

### Not included (Phase D — post-launch):
Site-wide "allow external redirects" toggle for operators with cross-domain thank-you pages (Mailchimp landing pages etc.).

---

## Phase D — Post-launch enhancements (OAuth2 SMTP + External CAPTCHA Providers)
**Goal:** Add the deferred opt-in integrations once v0.1 is in the wild and we have real installation feedback.

### Track 1 — OAuth2 for SMTP (Workspace + M365)
Original Phase D content. See "Phase A SMTP Module" for context.

- BYO vs. hosted oauth.perform-forms.com proxy decision is reserved for Phase D kickoff with field data instead of speculation.
- Adds ~3 weeks (authorization-code flow + token storage + refresh logic + PHPMailer XOAUTH2 + per-provider quirks).

### Track 2 — External CAPTCHA providers (Cloudflare Turnstile, hCaptcha, reCAPTCHA v3)
Moved from Phase B at the B-a checkpoint (2026-05-26) — built-in challenge alone covers the launch audience; mainstream provider integration is a power-user feature for high-profile sites.

**Full detailed implementation guide lives in repo memory:** `reference_phase_d_captcha_providers.md`. It includes:
- Provider comparison table (GDPR posture · cost · cookies · API endpoint · test-keys for dev).
- The three pre-prepared extension points in B-a's architecture (no refactor needed to start).
- Slice ordering D-Track2-a → D-Track2-c (provider abstraction + admin page → Turnstile → hCaptcha + reCAPTCHA v3 + privacy disclosure) plus D-Track2-d (site-wide external-redirect toggle from Phase C deferral).
- Migration story for `spamProtection='auto'` (forward-compatible, no data migration).
- Frontend bundle strategy (conditional enqueue per provider, ~zero new code in form-container/view.js).
- Privacy-disclosure copy templates for each provider + consent-first mode design options.
- A re-onboarding cheatsheet for the future session that picks this up.

Memory is autoloaded into every Claude session for this repo, so a future "lass uns Phase D Track 2 angehen" prompt resolves the architecture immediately.

### Open questions (still relevant for the CAPTCHA track when D ships):
1. Single provider per site, or per-form override?
2. Consent-first mode for reCAPTCHA + hCaptcha (they load third-party scripts pre-consent)?
3. Fail-closed vs. fail-open when the provider API is unreachable?

(All three documented with current-best-thinking recommendations in `reference_phase_d_captcha_providers.md` section 7.)

### Open architecture decision for OAuth2 SMTP track:
- **BYO OAuth-App:** operator registers their own OAuth client in Google Cloud Console / Azure → WP.org-clean, but high UX friction for non-developer operators.
- **Hosted OAuth-Proxy:** PerForm runs `oauth.perform-forms.com` with registered apps → comfortable for operators, but adds hosting + compliance cost and needs careful WP.org-review argumentation (precedent: WP Mail SMTP and FluentSMTP both ship this).
- **BYO with detailed wizard:** middle ground — detailed step-by-step wizard with screenshots + direct console links + copy-paste snippets.

---

## Phase 8 — Polish, Accessibility & WordPress.org Launch Prep
**Goal:** PerForm is production-ready, accessible, performant, and ready to be submitted to the WordPress.org plugin directory.

### What gets built:

**Accessibility audit & fixes:**
- All form fields have correct, associated `<label>` elements (even when labels are visually hidden).
- Error messages use `aria-live="polite"` so screen readers announce them.
- Multi-step navigation is keyboard-operable (Tab, Enter, Escape).
- Focus management: after advancing a step, focus moves to the top of the new step.
- Colour contrast meets WCAG 2.1 AA for all default styles.
- Progress indicator has appropriate ARIA roles and labels.

**Performance audit:**
- Measure total JS bundle size added to the frontend. Target: under 15kb gzipped.
- Measure page load impact on a simple page with one form. Target: no meaningful difference vs. a page without a form.
- Ensure `wp_enqueue_scripts` is conditional — assets only load on pages that contain a PerForm form.

**DSGVO / GDPR final review:**
- Confirm no data is sent to external services by default.
- Confirm IP and user agent are not stored unless explicitly enabled.
- Confirm data retention and deletion work correctly.
- Write a short "GDPR notes" section for the plugin documentation.

**WordPress.org submission package:**
- `readme.txt` fully written: Description, Installation, FAQ, Changelog sections.
- At least 5 high-quality screenshots of the plugin in action (Block Editor, frontend form, multi-step, submissions list, settings).
- Plugin tested against WordPress.org plugin check tool (automated checks passing).
- Plugin tested on the last 3 WordPress major versions (7.0, 6.9, 6.8) — graceful degradation where possible.
- No PHP warnings or notices on WP_DEBUG mode.
- License: GPL-2.0+

**Final QA checklist:**
- [ ] Fresh WordPress install: activate plugin, create form, submit, check email, check submissions — all in under 5 minutes
- [ ] Test on mobile
- [ ] Test with a screen reader
- [ ] Test with a caching plugin active (WP Rocket, W3 Total Cache)
- [ ] Test with a popular security plugin active (Wordfence)
- [ ] Deactivate plugin: confirm no data left in DB (or confirm data is intentionally retained and documented)

### Definition of done:
Plugin submitted to WordPress.org plugin directory. No blocking issues from the plugin review team.

---

## Phase M — Freemium Release (Free-Core + Pro-Addon)
**Goal:** Turn PerForm into a sellable product. The free version stays on WordPress.org as the funnel; the paid features ship as a separate Pro add-on plugin that docks onto a stable extension layer in the free core. Owner wants to move toward release ASAP, so this phase is architected to be **platform-agnostic** — the actual sales/license platform is wired in only at the very last slice.

**Decided (2026-06-01, this session):**
- **Architecture = Model B** — Free-Core (in .org repo) + separate Pro add-on plugin (Yoast-style), *not* one license-gated codebase. Reason: the Pro code never enters the public .org repo, and Pro can be released/updated without going through the .org review team each time.
- **The "two plugins" UX concern is designed away** via a 1-click upgrade flow: the free core downloads + activates the Pro add-on automatically after the license key is entered (WP Plugin Install API + license/update endpoint). The customer experiences one click, not "install two plugins".
- **Merchant of Record is mandatory** for the seller (German solo vendor) — the platform must handle EU VAT / OSS. Current tendency: **Freemius** (sells + licenses + auto-update + MoR in one), decision deferred to slice M-h.
- **Bridge-layer = an API contract.** Once Pro ships, the extension hooks must not break — a free-core update must never break installed Pro copies. Same contract discipline as the DB-schema migration recipe.
- **Free/Pro split = DECIDED (strategy: "balanced").** Pro: Conditional Logic, Multi-Step, Webhooks, CSV Export, SMTP (Basic Auth + OAuth2), external CAPTCHA. Free: builder + basic fields + single-recipient notify + submissions view + built-in spam challenge + privacy + thank-you redirect. See the feature matrix below. Since everything is currently in one codebase and nothing is released yet, this is the clean window to assign features without breaking the "never paywall a shipped free feature" rule.

### What gets built (split across M-a → M-h slices):

**Sub-track E — Free/Pro architecture foundation (no selling yet):**
- **M-a:** Define the extension/bridge layer in the free core — a small, *stable*, documented set of hooks the Pro add-on docks onto. Initial extension points: `performforms/register_fields` (Pro field types), `performforms/notification_routes` (CRM/webhook routes), `performforms/captcha_providers` (Phase-D Turnstile/hCaptcha/reCAPTCHA). Reuse the existing Guard-façade / conflict-detection pattern. Document as an internal API with a "do not break" contract note.
- **M-b:** Scaffold a real (initially near-empty) Pro add-on as a *separate* plugin that docks via the M-a hooks. Includes a dependency + min-version check against the free core (admin notice if free core missing/too old). Proves the separation holds end-to-end.
- **M-c:** Assign existing/planned features to Free vs Pro per the feature matrix (see below). Move Pro-bound features (e.g. Phase-D external CAPTCHA providers, SMTP OAuth2) into the add-on without breaking the free core.

**Sub-track F — License & updates:**
- **M-d:** License client in the Pro add-on — enter key, validate against endpoint, cache result, surface status in admin. Built behind a `License_Provider` interface (adapter) so the platform is swappable.
- **M-e:** Auto-update mechanism for the Pro add-on (hooks into the WP update transient against the platform/own update endpoint). Free core continues to update via .org as normal.
- **M-f:** "Upgrade to Pro" flow in the free core — license entry → 1-click download + activation of the Pro add-on (the UX fix for the "two plugins" concern).

**Sub-track G — WordPress.org readiness of the free version:**
- **M-g:** Make the free version .org-submittable — `readme.txt` to .org standard, Plugin Check tool green, trademark/guideline compliance, *tasteful* (non-nagging) upsell hints only. (Overlaps with Phase 8; consolidate, do not duplicate.)

**Sub-track H — Sales & platform:**
- **M-h:** Pick and wire the platform (Freemius vs Lemon Squeezy vs self-hosted EDD) into the single `License_Provider` adapter + update endpoint from M-d/M-e. Tendency: Freemius (covers F entirely + EU VAT). This is the *only* slice that touches the platform — everything before it is platform-agnostic.

### Free / Pro feature matrix (DECIDED 2026-06-01 — strategy: "balanced"):

Positioning of Free: *"A solid contact form with the best free spam protection on the market."* — genuinely useful, not crippleware.

| Free (the .org funnel — fully useful standalone) | Pro (scale / automate / integrate) |
|---|---|
| Form builder + container | **Conditional logic** (`Conditions` module) |
| Basic fields: text, email, textarea, number, checkbox, radio, select, toggle, hidden, section-heading | **Multi-step forms** (the `page-break` block + step logic) |
| Single-recipient email notification + merge tags | **Webhooks** (`Webhooks` module) |
| Submissions admin — **view / list only** | **Submissions CSV export** (`Exporter`) |
| Built-in spam challenge (HMAC + PoW + math) — kept Free as the differentiator | **SMTP** — Basic Auth *and* OAuth2 (full `Smtp` module is Pro) |
| Privacy / GDPR basics, thank-you redirect (Phase C) | External CAPTCHA providers (Phase-D: Turnstile / hCaptcha / reCAPTCHA) |
| Single notification route | Future: file uploads, payment fields (Stripe), CRM integrations, multiple notification routes, A/B |

**Architecture consequences for M-c (since these are already built in the single codebase):**
- The `page-break` block moves to Pro → free core must degrade gracefully if a form references a page-break but Pro is inactive (render as single page, no hard error).
- The `Conditions`, `Webhooks`, `Smtp`, and the `Exporter` modules move into the Pro add-on, wired back via the M-a hooks.
- **SMTP-to-Pro mitigation (build in M-c):** free version stays on `wp_mail()`; add a tasteful admin notice ("Deliverability issues? PerForm Pro adds SMTP") + a readme FAQ entry, so the limit reads as an upsell, not a defect. (Owner chose SMTP=Pro despite the deliverability-review risk; this is the agreed safeguard.)

**Pricing model (decided direction):** annual subscription with site tiers (1 / 5 / unlimited), ~30% first-year discount, auto-renew. Lifetime deals only as a one-off launch booster (e.g. AppSumo), never permanent — they kill recurring revenue.

### Definition of done (Phase M — complete):
Free version is live on WordPress.org. A customer can buy a license, click "Upgrade to Pro" once, and the Pro add-on installs, activates, validates the license, and unlocks Pro features — with auto-updates working and EU VAT handled by the chosen platform.

### Not included yet:
Affiliate program, in-plugin onboarding wizard, usage analytics/telemetry, multi-currency manual handling (the MoR covers this). Defer until after first paying customers.

### Golden rules for this phase:
- **Never paywall a feature that already shipped as free.** It burns trust. Free features only ever move *into* free, never out of it.
- **The bridge layer (M-a) is frozen once Pro ships.** Add hooks, never remove or change signatures.
- **Stay platform-agnostic until M-h.** Everything routes through the `License_Provider` adapter so Freemius ↔ Lemon Squeezy is an isolated swap.

---

## Parallel Track — CAPTCHA Options (can be added any time Phase 1+)

CAPTCHA integration is a parallel concern — it does not block any phase and can be implemented alongside any phase after Phase 1. It is called out separately because it requires external service evaluation.

**Minimum to implement:**
- A CAPTCHA abstraction layer: a PHP interface that any CAPTCHA provider implements. This ensures adding a new provider later is a matter of adding a class, not rewriting core logic.
- At least one provider implemented for launch. Recommended first choice: **Cloudflare Turnstile** (privacy-friendly, no cookies in most flows, free tier, DSGVO-compatible).
- Settings UI: global PerForm settings page → CAPTCHA section → provider selector dropdown → API key inputs → test button.
- Privacy notice: each provider shows a one-line privacy note explaining what data is shared with the third party (e.g. "Cloudflare Turnstile may process your IP address to detect bots. See Cloudflare's privacy policy.").
- Frontend: CAPTCHA widget rendered inside the Form block on the last step (or only step) before the submit button.
- Server-side verification: every submission is verified against the CAPTCHA provider's API before being accepted.

**Providers to support at launch (priority order):**
1. Cloudflare Turnstile (recommended default)
2. hCaptcha
3. Google reCAPTCHA v3

---

## Summary Timeline View

| Phase | What | Key milestone |
|-------|------|--------------|
| 0 | Plugin Foundation | Plugin installs, nothing breaks |
| 1 | The Form (It Works) | First submission saved to DB |
| 2 | More Fields + Submissions Admin | All basic fields + admin can view submissions |
| 3 | Email Notifications | Admin gets email on submission |
| 4 | Styling & Theme Integration | Forms look native on any theme |
| 5 | Multi-Step Forms | Multi-step with validation works |
| 6 | Webhooks | Data sent to external URL on submit |
| 7 | Conditional Logic | Fields show/hide based on other fields |
| A | SMTP Module (Basic Auth) | Plugin emails delivered via configured SMTP |
| B | Built-in Spam Protection (PoW + Math) | Self-hosted, 100% GDPR, zero setup |
| C | Thank-You-Redirect Polish | Per-form success URL + conversion-tracking metadata |
| 8 | Polish + Launch Prep | Submitted to WordPress.org |
| D | OAuth2 SMTP + External CAPTCHA Providers (post-launch) | Workspace / M365 / Turnstile / hCaptcha / reCAPTCHA |
| M | Freemium Release (Free-Core + Pro-Addon) | Customer buys a license, 1-click installs Pro, EU VAT handled |

---

*Roadmap version: 1.0 — May 2026*
*Each phase should be reviewed and tested on a real WordPress 7.0 installation before proceeding to the next.*

---

## Community Feedback Backlog

Build-in-public means features land here as the community surfaces them. Each entry is logged with: who/when, what was asked for, and how it maps to the phased plan above. This list is the audit trail — once an item is shipped or rejected, it stays here for historical reference.

### Logged 2026-05-24

| # | Request | Disposition | Lands in |
|---|---------|-------------|----------|
| 1 | Per-form recipient email address | **Already in plan.** Phase 3 `To:` field supports per-form configuration and merge tags. Highlighted explicitly in the Phase 3 spec above. | Phase 3 |
| 2 | Tracking integration — at minimum, redirect to a "Thank you" page so GA4 / Meta Pixel / Plausible can fire conversion events | **Gap closed.** Added "Post-submit behaviour" section to Phase 3 (message vs. redirect, with open-redirect protection and optional `?perform_submission={id}` for personalised thank-you pages). | Phase 3 |
| 3 | Reliable spam protection | **Already shipped (baseline).** Phase 1 includes always-on honeypot + time-check. Both are 100% GDPR-safe: no external service, no cookies, no fingerprinting, no third-country data transfer. | Phase 1 ✅ |
| 4 | Reliable spam protection (stronger, but still GDPR-compatible) | **Already in plan (parallel track).** The CAPTCHA parallel track (see above) ships **Cloudflare Turnstile** as the recommended default precisely because it is the most GDPR-friendly mainstream CAPTCHA option (no tracking cookies, IP processed only for bot scoring, Cloudflare offers an EU-data-residency commitment). hCaptcha as second option. reCAPTCHA v3 supported but flagged in the UI as "requires explicit privacy-policy disclosure" because it does feed Google's behavioural profiling. | Parallel track (post-Phase 3) |

### Disposition rules (how new entries are slotted)

When a new community request comes in, classify it as:
- **Already in plan** — point to the phase, optionally raise its visibility there.
- **Gap → fits an existing phase** — add the requirement to that phase's spec and note it here.
- **Gap → new phase or post-launch** — add to this backlog with a target (e.g. "post-1.0", "future"). Do not retro-fit into the phased plan unless it logically belongs there. Most requests should defer.
- **Out of scope** — link to the rationale in `PERFORM_SPEC.md §5` (Anti-Goals) and close with a one-line reason.

Keep this backlog short and decisive. It is not a wishlist — it is the record of what was asked and what was decided.

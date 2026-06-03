# PerForm + PerForm Pro — Pre-Polish Audit Prompt

> Paste this to an auditing agent (Claude Code with repo access). It audits BOTH
> plugins as of the completed Phase-M module migration (v0.2.5), before polishing.
> The goal is to surface defects NOW — the plugins are pre-release (not on .org yet).

---

## Your role

You are a senior WordPress plugin auditor and a GDPR/DSGVO reviewer. Audit two
plugins for **correctness, security, GDPR compliance, Free/Pro separation
integrity, and WordPress.org readiness**. **Report only — do not fix anything.**

Before you start, READ for context:
- `perform-forms/includes/Bridge/README.md` (the frozen Free/Pro bridge contract)
- `perform-forms/PERFORM_ROADMAP.md` → "Phase M" (what moved and why)

## The two codebases

- **Free core** — `perform-forms/` · namespace `PerForm\` · GPL, WordPress.org-bound,
  free forever. A block-editor forms plugin.
- **Pro add-on** — `perform-forms-pro/` · namespace `PerFormPro\` · separate paid
  plugin that docks onto the free core. Pro code never ships in the free repo.

## Architecture you must understand first

- **Model B freemium:** the free core is a platform with extension seams; Pro is a
  separate plugin that hooks them. Pro hard-depends on the core via the
  `Requires Plugins` header **and** a runtime `PERFORM_PRO_MIN_CORE` version guard.
- **Bridge seams** (frozen once Pro shipped): `perform_pro_features` (filter →
  `Bridge\Features` capability façade), `perform_register_modules` (action, Pro
  foothold), `perform_block_dirs` (filter), `perform_spam_providers` (filter),
  `perform_submissions_table_actions` (action, CSV export button),
  `perform.formContainer.inspectorPanels` (JS filter, editor panels),
  `perform_submission_detail_after` (action, webhook deliveries section).
- **Moved free → Pro:** CSV export (`Exporter` → `PerFormPro\Export\*`), SMTP
  (transport + settings page → `PerFormPro\Smtp\*`), Webhooks (REST + 2 DB tables +
  cron + log page + the editor integrations panel → `PerFormPro\Webhooks\*`).
- **Stayed free (intentionally):** conditional logic, multi-step — woven into the
  core blocks, not separable.
- **DB:** Pro owns the webhook tables `perform_webhooks` + `perform_webhook_deliveries`
  using the SAME names the free core used → Pro adopts existing tables in place
  (`PerFormPro\Database\Schema`, `perform_pro_db_version`, dbDelta auto-migrate, no
  drop on deactivate). Free core owns `perform_submissions` only.

## Audit dimensions

Rank every finding **Critical / High / Medium / Low**. For each:
`[SEVERITY] path/file.php:line — short title` → what's wrong · why it matters · concrete fix.

### 1. Free/Pro separation integrity
- Free core must run standalone with **zero fatals when Pro is absent** — no dangling
  references to moved classes (`Webhooks\*`, `Smtp\*`, `Submissions\Exporter`,
  `WebhookLogPage`/`WebhookLogListTable`). Grep the free core for them.
- **No double-registration** when both active: admin submenus, REST routes, cron
  events, action/filter hooks, editor panels. Confirm the `PERFORM_PRO_MIN_CORE`
  gates actually prevent a mismatched-version pair from double-registering.
- Every bridge seam: correctly fired by free + consumed by Pro? Signature/argument
  mismatch? Timing bugs (admin_menu priority, plugins_loaded order, JS filter
  registered before the form-container renders)?
- Has any seam's signature changed since it was documented (contract violation)?

### 2. Correctness / bugs — focus on the migration
- Namespace swaps: each moved class sits in the correct namespace and the Pro
  autoloader resolves it (path = namespace). Pro references to `\PerForm\...` must
  point at classes that still exist (`Submissions\Repository`, `Admin\Menu`,
  `Settings\Secret`). Free core must not reference `PerFormPro\...`.
- ⭐ **DB table adoption (high-risk):** compare Pro's `Database\Schema` webhook
  `CREATE TABLE` statements against the free core's ORIGINAL definitions in git
  history. They must be **identical** — a column/key mismatch means dbDelta will try
  to ALTER a live customer table on adoption (data risk) or silently diverge.
- Cron lifecycle: schedule registered with correct timing (the `cron_schedules`
  filter fires before `init`), event scheduled on activation, cleared on
  deactivation, self-healed on file-only update, never duplicated/orphaned.
- REST: routes registered, every route has a correct `permission_callback`, args
  sanitized + validated.
- The CSV-export and webhook-resend handlers run on `admin_init` **independently** of
  the core's dispatch — verify capability + nonce checks are airtight (no bypass,
  correct nonce action names, correct capability `Menu::CAPABILITY`).
- Activation / deactivation / uninstall hooks correct + idempotent in both plugins.

### 3. Security
- Every `$wpdb` call uses `prepare()` for variable input; table names interpolated
  (never from user input). Look hard at the webhook repos + delivery queries.
- Capability checks on every admin action, REST endpoint, and settings save.
- Nonce verification on every state-changing request (export, resend, SMTP save +
  test-send, webhook CRUD).
- Output escaping (`esc_html`/`esc_attr`/`esc_url`/`wp_kses_post`) wherever DB or user
  data is printed — incl. the SMTP status page, webhook log, deliveries section.
- Input sanitization on all `$_GET`/`$_POST`/`$_REQUEST`.
- **SSRF:** webhooks POST to admin-supplied URLs — note any lack of guards
  (internal-IP/loopback filtering). Admin-configured lowers severity but flag it.
- Secrets: SMTP password encrypted via `Settings\Secret`; never logged, never echoed,
  not exposed via REST. Check the encryption + the wp-config salt dependency.
- No `eval`, no `unserialize` of untrusted data, no `extract`.

### 4. GDPR / DSGVO — **first-class priority (owner flagged this explicitly)**
- **Data inventory:** enumerate every piece of personal data stored (submission field
  values, webhook delivery response bodies, anything else), where, and for how long.
- **Privacy-policy content:** the free core registers content via
  `wp_add_privacy_policy_content` (`Privacy.php`). After webhooks + SMTP moved to Pro,
  the free text was generalized. ⭐ **Does Pro add its OWN privacy disclosures** for
  webhooks (submission data sent to third-party URLs, possibly outside the EU) and
  SMTP (mail routed through a provider)? This is a **likely GAP** — webhooks transmit
  personal data to third parties and MUST be disclosed.
- ⭐ **Erasure cascade (likely GAP):** the free core has a personal-data exporter +
  eraser (`Privacy.php`) for submissions. Webhook delivery rows (now in Pro) carry
  `submission_id` and may hold personal data in `response_body`. When a submission is
  erased (or via WP's eraser), are the related webhook delivery rows also erased? Does
  Pro register its own exporter/eraser for the delivery log? If not, personal data is
  orphaned after an erasure request — a GDPR violation.
- **Data minimization:** the plugin claims it stores no IP / user-agent. Verify in the
  submission `Handler`, the spam challenge, and webhook deliveries.
- **Third-country transfer / external transmission:** webhooks → arbitrary URLs; SMTP
  → configured provider. Both are admin-configured (lawful basis is the controller's
  duty) but the plugin must be transparent. Confirm the built-in spam challenge stays
  100% local (no external call).
- **No silent external calls:** confirm the free core contacts nothing by default, and
  Pro contacts nothing until an admin configures a webhook/SMTP.
- **Retention:** submissions are retained indefinitely until manual delete/uninstall.
  Flag whether that is documented and whether a retention/auto-purge option is
  warranted for GDPR storage-limitation.
- **Uninstall vs deactivate:** deactivate must preserve all data (verify); free
  uninstall drops `perform_submissions`, Pro uninstall drops the webhook tables —
  confirm no personal data is orphaned and no table is left behind on full removal.

### 5. WordPress.org / standards
- Text domains: free = `perform-forms`, Pro = `perform-forms-pro`. The ~10 moved files
  were swapped with sed — check for **missed or over-swapped** domains and any string
  that isn't actually a text domain getting mangled.
- i18n timing: no `__()`/`_e()` before `init` (WP 6.7+ JIT-load notice) — check
  early hooks, `cron_schedules` display strings, activation callbacks.
- Prefixing: all global functions, options, hooks, cron hooks prefixed `perform_`.
- `perform-forms/readme.txt`: still lists webhooks/SMTP/CSV as free and says "There is
  no premium tier" — confirm this is stale and scope the rewrite (slated for M-g).
- Free core should pass the WordPress.org Plugin Check tool — note blockers.

### 6. Build / packaging
- Shipped zips must exclude dev cruft (`node_modules`, `src`, `.git`, dev `.md`) but
  INCLUDE `build/`. Pro `build/index.js` + `index.asset.php` present; the editor
  enqueue reads deps/version from the manifest.
- No secrets, no `.claude/`, no `settings.local.json` in either zip.

## Known risk areas — start here (pre-flagged as likely)

1. **GDPR — webhook delivery logs are not covered by erasure**, and **Pro adds no
   privacy-policy content** for webhooks/SMTP. (Dimension 4.)
2. **DB column parity** between the free core's original webhook table SQL and Pro's
   `Database\Schema` — must be byte-identical. (Dimension 2.)
3. **Text-domain swap misses** across the moved files. (Dimension 5.)
4. **Cross-plugin hook timing** — admin_menu priority 20, the JS inspector filter,
   `perform_after_submission` fired by free + consumed by Pro. (Dimension 1.)
5. **Double-registration / orphaned cron** across version-mismatched pairs. (Dim. 1/2.)

## Output

Group findings by dimension, severity-ranked. End with: (a) a prioritized fix list,
(b) an explicit **go / no-go for polishing**, and (c) the top 3 GDPR actions. Cite
`file:line` for every finding. Audit only — implement nothing.

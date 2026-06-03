# PerForm Bridge Layer — Free/Pro Contract

This directory defines the **stable API** the free core exposes for the
**PerForm Pro** add-on to dock onto. It is the heart of the Free/Pro split
(Model B: free core on WordPress.org + separate paid Pro add-on plugin).

## The frozen-contract rule

> Once the Pro add-on ships publicly, **every hook name and signature below is
> frozen**. A free-core update must never break an installed Pro copy.

Allowed: **adding** new hooks / new capability keys / new optional parameters
appended at the end.
Forbidden: renaming a hook, removing a hook, changing an argument's type or
position, or repurposing a capability key.

Same discipline as the DB-schema migration recipe — the contract is the product.

---

## How the Pro add-on docks

The Pro add-on is a separate plugin. From its **main file (top-level code)** —
which runs before `plugins_loaded` — it does two things:

```php
// 1. Advertise its capabilities so the free core degrades gracefully.
add_filter( 'perform_pro_features', static function ( array $features ): array {
    $features[] = 'submissions_export';
    $features[] = 'smtp';
    $features[] = 'webhooks'; // once the module moves to Pro
    return $features;
} );

// 2. Wire its own subsystems once the free core is ready.
add_action( 'perform_register_modules', static function (): void {
    // new \PerFormPro\Webhooks\…()->register();  etc.
} );
```

Because the add-on's main file executes during the same load cycle as the free
core (and before `plugins_loaded`), both listeners are attached before
`Plugin::init()` fires them. With no add-on installed, both are no-ops.

---

## Extension points (as of slice M-a)

### 1. `perform_pro_features` (filter) — capability advertisement
The single source of truth for "is this Pro feature available?".
Resolved by `PerForm\Bridge\Features`. Free core returns `[]` → all Pro
features off → graceful degradation everywhere.

- **Input:** `array $features` (empty from core)
- **Return:** list `[ 'multistep', … ]` *or* keyed map `[ 'multistep' => true ]`
- **Read via:** `Features::has( Features::MULTISTEP )`, `Features::is_pro_active()`
- Capability keys: `webhooks`, `submissions_export`, `smtp` (see `Features`
  constants). Conditional logic and multi-step stay in the free core.

### 2. `perform_register_modules` (action) — module foothold
Fires once on `plugins_loaded`, after the free core has wired its own modules.
The one place the Pro add-on boots its subsystems.

- **Args:** none. The add-on instantiates and `register()`s its own classes.

### 3. `perform_block_dirs` (filter) — block / field-type registration
Lets the Pro add-on register blocks (e.g. Pro field types, the multi-step
`page-break` block once it moves to Pro) from the **add-on's own** build dir.

- **Input/Return:** `array<string,string>` — map of `slug => absolute path` to
  the directory holding the compiled `block.json`.
- Applied in `PerForm\Blocks\Registry::register_blocks()`.

### 4. `perform_spam_providers` (filter) — spam / CAPTCHA providers
Lets Pro append external CAPTCHA providers (Phase D: Turnstile, hCaptcha,
reCAPTCHA). A form requesting an unregistered provider degrades to `builtin`.

- **Input/Return:** `array<int,string>` — provider keys (core: `[ 'builtin' ]`).
- Consulted in `PerForm\Spam\Guard::resolve_strategy()`.

### 5. `perform_submissions_table_actions` (action) — submissions filter-bar actions
Added in slice M-c-a (CSV export move). Fires in the submissions list filter
bar, next to the Filter button. Pro attaches inline actions here — the CSV
export button — so the free core ships no export UI. With no add-on, the bar
shows only the free filter controls.

- **Args:** `array<string,string> $current` — active filter values
  (`form_id`, `status`, `date_from`, `date_to`, `search`), so Pro can carry the
  current filter into the export URL.
- Fired in `PerForm\Admin\SubmissionsListTable` (filter-bar render).
- Pro's request handler runs independently on `admin_init`, re-checking
  `Menu::CAPABILITY` + the `perform_export` nonce — the free core's `dispatch()`
  no longer routes an `export` action.

---

### 6. `perform.formContainer.inspectorPanels` (JS filter) — editor inspector panels
Added in slice M-c-c. A **JavaScript** filter (`@wordpress/hooks`), not a PHP
hook — it is the editor-extensibility mechanism for every Pro module with a
form-container inspector UI (webhooks, conditional logic, multi-step). The free
core's `form-container/edit.js` applies it and renders the returned panels
inside `<InspectorControls>`. The Pro editor script (`assets/editor.js`, enqueued
on `enqueue_block_editor_assets`) attaches via `wp.hooks.addFilter`.

- **Input/Return:** `Array` of React elements (panels). Core default: `[]`.
- **Context arg:** `{ attributes, setAttributes, clientId, formId, formFields }`.
- Pro consumes it with deps `wp-hooks`, `wp-element`, `wp-components`, `wp-i18n`.
- With no add-on the list is empty → no extra panels render.

---

## Planned extension points (not yet cut — added when Pro needs them)

Adding these later is contract-compliant (additive). Listed here so the shape is
known in advance:

- **`perform_notification_routes` (filter)** — multiple notification routes
  (extra recipients, CRM). Deferred from M-a on purpose: the `Notifications\Mailer`
  is already cleanly factored with a working `perform_email_notification` filter;
  it is not restructured until the Pro multi-route feature actually lands (M-c).

---

*Slice M-a — bridge seams cut, free core behaves identically. No modules moved
yet (that is M-c).*

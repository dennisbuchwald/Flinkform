# Flinkform — Fortsetzungs-Prompt für Claude (neuer Chat)

> Diesen Prompt **komplett** in einen frischen Claude-Code-Chat im Projektordner
> `flinkform/` einfügen. Antwortsprache: Deutsch. Arbeitsverzeichnis muss
> `…/02_Eigenentwicklungen/flinkform/` sein.

---

## TL;DR — Wo wir stehen

**Phase 0 bis 7, Phase A (SMTP), Phase B-a (Built-in Spam Challenge) und Phase C
(Thank-You-Redirect) sind komplett abgeschlossen, getestet und gepusht.** Der
Plugin-Code ist v0.1-ready in puncto Funktionalität. Was noch fehlt:

→ **Phase 8 — Polish, Accessibility & WordPress.org-Launch-Prep**

Diese Phase enthält keine "feature work" mehr, sondern den letzten Qualitäts-
Pass + die WP.org-Submission selbst. Sie ist in 6 kleine Slices unterteilt
(Accessibility / Performance / DSGVO / readme.txt + Screenshots / Multi-Theme-
Test / Final QA + Submission). Du kannst sofort mit Slice 8a (Accessibility-
Audit) in Plan-then-Go-Manier loslegen.

**Nach Phase 8 / nach Launch** kommt **Phase D — Post-launch Enhancements**
(OAuth2 für SMTP + externe CAPTCHA-Provider Turnstile/hCaptcha/reCAPTCHA + Site-
wide "Allow external redirects"-Toggle). Phase D ist nicht launch-blocking und
in Memory + Roadmap ausführlich dokumentiert — siehe Anhang.

## Kontext

Du übernimmst die Weiterentwicklung des WordPress-Plugins **Flinkform**. Das ist
eine WP.org-Submission-fähige Form-Builder-Erweiterung mit Gutenberg-Blocks,
dynamic rendering, server-side validation, Style-Panel mit Theme-Inheritance,
Multi-Step-Forms, Conditional Logic, Outgoing Webhooks (n8n/Zapier/etc.),
Notifications-System, Custom-CSS-Escape-Hatch, eigenem SMTP-Modul (Basic Auth +
8 Provider-Presets), Built-in Spam Protection (PoW + Math-Fallback, 100% DSGVO,
kein externes Konto nötig) und Per-Form Thank-You-Redirect mit Conversion-
Tracking-Metadata. Owner: dbw media. Plugin wird auf einer echten Sandbox-WP-
Instanz mit GeneratePress getestet.

**Plugin-Daten (fix, nicht ändern):**
- Anzeigename: **Flinkform**
- WordPress.org-Slug: **`flinkform`**
- Text Domain: `flinkform`
- Namespace: `Flinkform\` (PSR-4, unter `includes/`)
- Konstanten-Präfix: `PERFORM_`
- Funktions-Präfix: `perform_`
- Version: `0.1.0` (Scaffold, noch nicht bumped — Phase 8 bumpt offiziell auf `0.1.0` für die WP.org-Submission)
- Mindestens: WordPress 7.0 + PHP 8.1
- Autor: dbw media
- GitHub: **https://github.com/dennisbuchwald/flinkform**
- Test-Umgebung: **https://sandbox.dbw-development.de/index.php/flinkform/**
  (WordPress mit GeneratePress, Owner lädt via FTP hoch)

## Was du als Allererstes tust

1. **Memory-Dateien überfliegen** im Pfad
   `~/.claude/projects/-Users-dennisbuchwald-Arbeitsplatz-01-Code-04-Ressource-02-Eigenentwicklungen-flinkform/memory/`
   — werden automatisch geladen, aber ein bewusster Read früh in der Session
   stellt sicher dass du die Konventionen kennst. Besonders wichtig:
   - `feedback_commit_author.md` — **keine Co-Authored-By-Trailer in Commits**
   - `feedback_communication.md` — Deutsch + English in Code + Plan-then-Go
   - `feedback_quality_standard.md` — höchster Qualitätsanspruch ist das Minimum
   - `feedback_phase_slicing.md` — Phasen in a/b/c/d-Slices schneiden
   - `feedback_theme_resistance.md` — SCSS-Spezifitätsstrategie
   - `feedback_i18n_timing.md` — niemals `__()` vor WP `init`-Hook
   - `reference_perform_infrastructure.md` — Repo, Sandbox, FTP-Mechanik
   - `reference_perform_secret_helper.md` — AES-256-CBC für Settings-Secrets
   - `reference_perform_settings_page_pattern.md` — Settings-Page-Controller-Pattern
   - `reference_perform_plugin_conflict_detection.md` — Self-disable vs. rivale Plugins
   - `reference_perform_spam_challenge.md` — Built-in Spam-Challenge-Architektur
   - `reference_phase_d_captcha_providers.md` — Post-launch External-CAPTCHA-Guide
   - `project_phase_a_smtp_decisions.md` — SMTP-Module-Entscheidungen
   - `project_phase_b_spam_decisions.md` — Phase B closed at B-a Rationale
2. **`PERFORM_SPEC.md` quer lesen** (Vision + Architektur-Entscheidungen). Hat
   lokale GDPR/CAPTCHA-Modifikationen die NICHT mitcommitted werden — die
   sind als Notiz-Quelle gedacht, nicht als Code-Plan.
3. **`PERFORM_ROADMAP.md`** überfliegen — alle Phasen inklusive Phase 8 und Phase D.
4. **`git log --oneline -25`** — Commit-Messages der letzten Phasen sind sehr
   ausführlich und dokumentieren jede Architekturentscheidung.

## Aktueller Stand (Stand: nach Phase C, ~Commit `22292c5`)

### Phasen-Status

| Phase | Was | Status | Letzter Commit |
|-------|-----|--------|----------------|
| 0 | Plugin Foundation | ✅ | — |
| 1 | The Form (It Works) | ✅ | — |
| 2 | More Fields + Submissions Admin | ✅ | — |
| 3 | Email Notifications | ✅ (Redirect-URL-Feature war Lücke, geschlossen in Phase C) | — |
| 4 | Styling & Theme Integration | ✅ | — |
| 5 | Multi-Step Forms | ✅ | — |
| 6 | Webhooks | ✅ | — |
| 7 | Conditional Logic | ✅ | `0efee20` |
| **A** | **SMTP Module (Basic Auth)** | ✅ A-a..A-d | `ca6bb51` |
| **B** | **Built-in Spam Protection (PoW + Math)** | ✅ B-a (B-b/c → D Track 2) | `5b3ee73` |
| **C** | **Thank-You-Redirect (per-form + metadata)** | ✅ | `22292c5` |
| **8** | **Polish + WP.org Launch-Prep** | 📋 **next** | — |
| D | OAuth2 SMTP + External CAPTCHA Providers + Site-wide Redirect Toggle | 📋 post-launch | — |

### Was an Architektur unter der Haube steht (NICHT umkippen)

1. **Dynamic Blocks mit `render.php`** — kein React fürs Frontend-Display.
2. **Output-Contract:** render.php-Files ECHOEN direkt, kein `ob_start()`.
3. **`<InnerBlocks.Content />` Pflicht** im form-container `save()`.
4. **Flash-Token lowercase** (`sanitize_key` lowercased beim Read).
5. **Label-Resolver** holt Default aus `WP_Block_Type_Registry`.
6. **Form-Definition lebt im Block-Markup** (Variante A), KEIN CPT. Locator
   parsed bei Submit, Indexer cached die Forms-Liste.
7. **Submission-Format self-contained** — Labels + Types werden mit Werten
   gespeichert, überlebt Form-Renames/Löschungen.
8. **Eigener PSR-4-Autoloader**, kein Composer/vendor/.
9. **Plugin-Hauptdatei `flinkform.php`** (nicht `perform.php`).
10. **CSS-Variablen-Kaskade:** `var(--flinkform-X, var(--wp--preset--Y, fallback))`
    — User-Override → theme.json → hartcodierter Fallback.
11. **WP Interactivity API** für alle reaktiven Frontend-Effekte (Multi-Step,
    Webhooks-Test-Button, Conditional Logic). view.js als ES-Modul
    (`viewScriptModule`) registriert; `--experimental-modules` Flag im
    build/start-Script gesetzt.
12. **DB-Tabellen** (Schema-Version `2`, auto-upgrade in `Plugin::init`):
    - `{prefix}_perform_submissions`
    - `{prefix}_perform_webhooks`
    - `{prefix}_perform_webhook_deliveries`
    (Phase A/B/C haben KEINE neuen Tabellen gebraucht — alles in `wp_options`,
    Transients oder Block-Attributen.)
13. **WP-Cron-Schedule** `perform_every_minute` (60s-Interval) treibt die
    Webhook-Delivery-Queue an.
14. **Conditional-Logic Rule-Engine** (`Flinkform\Conditions\RuleEvaluator`)
    teilt sich PHP- + JS-Side. 8 Operators, AND/ANY-Logic-Combiner.
    `is`/`is_not` sind case-insensitive.
15. **Server is Truth** — DOM-Manipulation zum Bypass von Conditional-Logic
    wird vom `Submissions\Handler` abgefangen.
16. **Block-Attribut-Konvention `conditionalLogic`** auf allen Field-Blocks +
    Page-Break + Section-Heading. Form-Container hat `submitCondition` für
    Submit-Button-Gating.
17. **Sanitisation für User-Input:** `sanitize_text_field` für Strings,
    Allow-Lists für Enums, Whitelist-Regex für CSS-Colors, `wp_strip_all_tags`
    + Pattern-Filter für Custom CSS, `esc_url_raw` für Webhook-URLs.
18. **Repo-Konvention:** Author = Dennis Buchwald, **kein** Co-Authored-By-Trailer.
    Pushen autonom nach erfolgreichen Commits.
19. **i18n-Frühruf-Verbot:** Niemals `__()` in `cron_schedules`-Filter oder
    anderen pre-`init`-Callbacks. WP 6.7+ trippt sonst
    `_load_textdomain_just_in_time` Notice.

### Was Phase A/B/C an Architektur dazugebracht hat

20. **`Flinkform\Settings\Secret`** — AES-256-CBC + random IV pro Encrypt + key
    derived von `wp_salt('auth')`. Cipher-Format `perform_enc_v1:<base64(iv||cipher)>`,
    versioniert für künftige Cipher-Upgrades. Reused für: SMTP-Password
    (Phase A), künftige Provider-Secrets (Phase D Track 2).
21. **Admin-Settings-Page-Pattern** — `render() + dispatch()` Controller mit
    eigenem POST-Handler (nicht Settings API, weil `options.php` redirects + die
    "empty password = keep cipher"-Logik bricht). Single autoloaded `wp_options`
    key pro Modul. Siehe `Admin/SmtpPage.php` als Referenz für künftige Module
    (z.B. Spam-Protection-Settings-Page in Phase D Track 2).
22. **Plugin-Konflikt-Detection** — `get_option('active_plugins')` direkt
    (NICHT `is_plugin_active()`, weil das in wp-admin/includes/plugin.php
    lebt und auf Frontend nicht geladen ist). Statische Klassen-Konstante
    `CONFLICTING_PLUGINS` als slug→label-Map. Self-disable + Admin-Notice bei
    Konflikt. Pattern: `Smtp\Transport` (Phase A-b).
23. **SMTP-Module** (Phase A komplett):
    - `Admin\SmtpPage` (`flinkform-smtp` slug) — Settings UI + Test-Email-Button
    - `Smtp\Transport` — `phpmailer_init` Hook (P1000) + `wp_mail_from{,_name}`
      Filter + Konflikt-Detection (WP Mail SMTP / FluentSMTP / Easy WP SMTP /
      Post SMTP). Hooks registered on `init` für i18n-Safety.
    - `Settings\Secret` (siehe oben) für encrypted Password-Storage.
    - **Diagnose-Status-Block** auf der SMTP-Page mit 6 Zeilen (Transport
      module / Master toggle / Configuration / Plugin conflict / Effective /
      Last test). Farb-coded Badges, live-state. Erlaubt Operatoren Selbst-
      Diagnose ohne Mail-Header-Forensik.
    - **Test-Email-Button** mit `wp_mail_failed`-Hook für Error-Capture.
      `LAST_TEST_OPTION_KEY` persistiert das letzte Test-Ergebnis.
24. **Spam-Challenge-Module** (Phase B-a):
    - `Spam\Challenge` — HMAC-signed JSON token (5min TTL + single-use via
      transient). Format: `base64url(payload).hex(hmac)`. PoW-Difficulty 18
      bits = ~50-500ms im Browser. Math-Fallback mit gehashtem Expected-Answer.
    - `Spam\Renderer` — emittet Challenge-Markup. Drei POST-Field-Konstanten
      (FIELD_TOKEN, FIELD_SOLUTION, FIELD_ANSWER).
    - `Spam\Guard` — Façade. `should_protect()` + `verify_submission()`.
      Hook in `Submissions\Handler::handle()` zwischen Time-Check + validate().
      `resolve_strategy()` ist der Single-Extension-Point für Phase D Track 2
      (Turnstile/hCaptcha/reCAPTCHA).
    - **PoW-Solver im Frontend** — Main-thread async mit `await setTimeout(0)`
      yield every 1024 iterations. Web Worker bewusst vermieden (Cache-Plugin-
      Friction + CSP-Friction). Bundle: +1 KiB minified.
    - **`spamProtection` block-attribute** auf form-container — enum
      `['auto', 'builtin', 'none']`. `'auto'` resolved heute zu `'builtin'`;
      Phase D Track 2 erweitert das auf Provider-Keys.
25. **Thank-You-Redirect** (Phase C):
    - **`afterSubmit` block-attribute** auf form-container — Object mit
      `behaviour`, `redirectUrl`, `appendSubmissionId`, `appendFormId`.
    - **`Submissions\Handler::redirect_success()` rewritten** — accepts
      `$form_attrs + $submission_id`, baut Redirect-URL mit Metadata-Args,
      filtert via `wp_validate_redirect()` (same-origin only by default,
      externe URLs werden silently zur Source-Page redirected — kein Open-
      Redirect, keine Blank-Page).
    - **Honeypot/time-check hits behalten den Legacy-Pfad** — Bot lernt die
      konfigurierte Thank-You-URL NICHT kennen.
    - **`build_redirect_url()` helper** schützt vor doppelten Query-Args
      wenn Operator die URL hand-rolled mit `?perform_submission_id=` schon
      drin hatte.

### Bekannte UX-Beobachtungen (kein Bug, Polish-Backlog)

- **Hidden-Field zeigt "empty"** im Admin wenn User es einfügt aber
  Value-Source nicht konfiguriert.
- **Doppelte Field-Labels** machen Detail-View verwirrend — Editor-Hinweis
  "Another field uses the same label" fehlt noch.
- **Multi-Theme-Sanity-Test-Pass** (Twenty-Twenty-Five / Astra / Kadence)
  wurde noch nicht durchgeführt — **wird Slice 8e**.
- **Webhook-Retry-Path** noch nicht real getestet — braucht failing endpoint
  (5xx/timeout). Codepath ist da (1min/5min/30min backoff), aber Browser-Claude
  konnte das im E2E nicht triggern.
- **Math-Challenge sichtbar bei Cache-Plugin + JS off** — das ist by design
  (FOUC würde durch SSR-`hidden`-Attribut entstehen wenn Caching das JS-on-
  Layout serviert; siehe Renderer-Kommentar). Falls Owner-Beschwerden kommen
  könnte Slice 8b (Performance) eine Lazy-Show-Variante prüfen.

### Repository / Git

- Remote: `origin → https://github.com/dennisbuchwald/flinkform.git`
- Branch: `main`.
- Letzter Commit: `22292c5 feat: Phase C — Thank-You-Redirect`.
- Lokal ungetracked: `CONTINUE_PROMPT.md` (du liest ihn grade),
  `PERFORM_ROADMAP.md`, `PERFORM_LANDINGPAGE.md`. **`PERFORM_SPEC.md` hat
  lokale GDPR/CAPTCHA-Notizen** — gehören nicht in unsere Commits.

### Hochladen aufs FTP

Komplettes `flinkform/`-Verzeichnis, **außer**:
`node_modules/`, `src/`, `.git/`, `_wporg-svn/`, `PERFORM_*.md`,
`CONTINUE_PROMPT.md`, `INITIAL_PROMPT.md`, `package*.json`, `deploy.sh`,
`.editorconfig`.
Auf dem Server: `flinkform.php`, `uninstall.php`, `readme.txt`,
`includes/`, `build/`, `languages/`.

`build/` ist gitignored — vor jedem Upload `npm run build` lokal laufen.

## Arbeitsweise (verbindlich, gehört auch in Memory)

- **Sprache:** Deutsch. Code-Kommentare auf Englisch (WP-Konvention).
- **Plan-then-Go.** Nicht-trivialer Slice: kurz WAS + WARUM, Architekturwahlen
  begründen, 1–4 offene Fragen, dann auf "Go" warten. Kleine reaktive Fixes
  dürfen sofort losgehen.
- **WordPress Coding Standards.** Tabs, `declare( strict_types = 1 );`,
  typisierte Signaturen.
- **i18n** durch `__()`/`_e()` mit Text-Domain `'flinkform'`. **Niemals
  vor `init`-Hook aufrufen.**
- **Security:** sanitize-in / escape-out, Nonces, Capability-Checks, prepared
  statements.
- **Performance-Budget:** Frontend-JS < 15 KB gzipped, kein jQuery.
  (Aktuell: view.js 7.84 KiB minified ≈ 3 KB gzipped. Editor-Bundle hat kein
  Budget weil Admin-Context — aktuell ~32 KiB minified.)
- **Accessibility:** WCAG 2.1 AA. (Phase 8a wird das systematisch durchgehen.)
- **Commits:** EIN Slice = EIN `feat:`-Commit mit ausführlichem Body.
  Hotfixes als eigene `fix:`-Commits. **Author = Dennis Buchwald, KEIN
  Co-Authored-By-Trailer.** PERFORM_SPEC.md / PERFORM_ROADMAP.md /
  CONTINUE_PROMPT.md / PERFORM_LANDINGPAGE.md NICHT mitcommiten ausser
  explizit gewünscht.
- **Push autonom** nach erfolgreichen Commits.

## Phase 8 — Polish + WordPress.org Launch-Prep (jetzt dran)

**Goal:** Flinkform ist production-ready, accessible, performant, DSGVO-final-
reviewed, mit kompletter WordPress.org-Submission-Package. Plugin wird beim
WP.org-Plugin-Directory eingereicht.

### Slice-Plan (Vorschlag — vor Implementation mit Owner abstimmen)

**Slice 8a — Accessibility Audit + Fixes (WCAG 2.1 AA):**
- Systematischer Audit jedes Frontend-Templates:
  - Alle `<input>` haben korrekt assoziierte `<label>` (auch visually-hidden).
  - Error-Messages: `aria-live="polite"` damit Screen-Reader sie ankündigen.
  - Multi-Step-Nav: keyboard-operable (Tab / Enter / Escape).
  - Focus-Management: nach Step-Wechsel Fokus auf erstes Field der neuen Step.
  - Color-Contrast: WCAG 2.1 AA für alle Default-Styles.
  - Progress-Indicator: korrekte ARIA-Rollen + Labels.
  - **Spam-Challenge Math-Row** — Label, aria-describedby für den hint.
  - **SMTP-Settings + Diagnostic-Block** — `<table role="presentation">` ist
    OK weil's reine Layout-Tabelle ist, aber Label-Assoziation prüfen.
- Tools: axe DevTools, WAVE, manueller Screen-Reader-Test (VoiceOver / NVDA).
- Fix-Commits pro entdecktem Issue, alle als `fix(a11y): …` oder `feat: 8a — …`.

**Slice 8b — Performance Audit:**
- Frontend-Bundle-Größe verifizieren: view.js < 15 KB gzipped, conditional-
  enqueued (nur auf Pages mit Flinkform-Form).
- LCP / FID / CLS impact: Lighthouse auf Sandbox mit + ohne Flinkform-Form
  vergleichen.
- Critical-CSS prüfen — Style-Index sollte nicht render-blocking sein.
- `wp_enqueue_scripts` conditional: keine Assets auf Pages ohne Form.
- Editor-Bundle ist Admin-Context, kein Budget — nur sanity-check dass es
  nicht explodiert.
- **Math-Challenge-FOUC-Mitigation** (siehe Bekannte UX): falls Owner sich
  beschwert, eine Lazy-Show-Variante implementieren.

**Slice 8c — DSGVO/GDPR Final Review:**
- Bestätigen: keine Daten an externe Services per Default (Webhooks sind
  opt-in, Notifications-Mailer geht über `wp_mail()` / konfiguriertes SMTP,
  Built-in Spam-Challenge ist self-hosted).
- Bestätigen: IP + User-Agent werden nicht gespeichert (Phase 1 entschied
  das bewusst, validieren).
- Bestätigen: Data-Retention + Deletion funktioniert (Submissions-Delete,
  Plugin-Deactivation, uninstall.php).
- **Privacy-Notice für Site-Admin schreiben** — kurzer Abschnitt in
  `readme.txt` "GDPR notes" der listet was Flinkform verarbeitet, wo's lebt,
  wie man's löscht.
- **Plugin-Privacy-API-Integration** prüfen — WP hat einen
  `wp_add_privacy_policy_content()`-Hook für Plugins die Daten verarbeiten.
  Sollten wir nutzen.

**Slice 8d — WordPress.org Submission Package:**
- **`readme.txt`** komplett schreiben:
  - Short description (max 150 chars)
  - Long description (Features-Listing)
  - Installation steps
  - FAQ (typische Operator-Fragen)
  - Changelog (alle Phasen kurz zusammenfassen)
  - Screenshots-Hinweise (1.png .. 8.png mit Captions)
- **Screenshots** generieren (mindestens 5 hochqualitative):
  1. Block-Editor mit Form + Inspector geöffnet
  2. Frontend-Form (single-step)
  3. Multi-Step-Form mit Progress-Bar
  4. Submissions-Admin (Liste + Detail)
  5. SMTP-Settings mit Diagnostic-Block
  6. Spam-Protection in Action (PoW transparent, Math-Fallback sichtbar
     wenn JS off)
  7. Webhook-Integrations-Panel
  8. After-Submit-Redirect-Config
- **WP.org Plugin-Check-Tool** lokal laufen lassen, alle Issues fixen.
- **License:** GPL-2.0+ in Plugin-Header bestätigen.
- **Tested up to:** auf neueste stabile WP-Version setzen.

**Slice 8e — Multi-Theme-Sanity-Test:**
- Auf einer Sandbox je einmal aktivieren und ein Form durchtesten:
  - **Twenty Twenty-Five** (Block-Theme)
  - **Astra** (klassisches Free-Theme)
  - **Kadence** (Block-Theme mit Customizer)
  - **GeneratePress** (haben wir schon → revalidieren)
- Pro Theme: Form rendern + Submit + Mail prüfen + Style-Panel-Werte
  prüfen + Dark-Mode-Toggle. Visuelle Bugs als `fix:`-Commits.

**Slice 8f — Final QA + WP.org-Submission:**
- Fresh-WP-Install-Test: aktivieren, Form erstellen, submitten, Email checken,
  Submission im Admin sehen — alles in unter 5 Minuten.
- Mobile-Test (Chrome DevTools Device Toolbar + ein echtes Smartphone wenn
  möglich).
- Screen-Reader-Test (VoiceOver auf Mac als Minimum).
- Caching-Plugin-Test (WP Rocket oder W3 Total Cache).
- Security-Plugin-Test (Wordfence aktiv lassen, prüfen ob Submissions klappen).
- Deactivate-Test: Plugin deaktivieren → bestätigen dass keine "Geister-Daten"
  in DB übrig bleiben (oder dass Daten intentional bleiben + dokumentiert ist).
- **`uninstall.php` testen** — Plugin löschen via WP-Admin, prüfen dass alle
  Tabellen + Options + Transients weg sind.
- **Version-Bump** in `flinkform.php` Header + `readme.txt`:
  `Stable tag: 0.1.0` und im Plugin-Header `Version: 0.1.0`.
- **WP.org SVN-Submission** vorbereiten — Owner reicht selbst ein, aber wir
  bereiten den `_wporg-svn/`-Workspace vor.
- **Tag das Release** als git tag `v0.1.0`.

### Offene Fragen für Phase 8 (Plan-Round)

1. **Screenshots-Style?** Werden mit Owner-Sandbox erstellt (echte Mails,
   echtes GeneratePress-Theme) oder mit einem Plain-Theme um WP.org-Reviewer-
   Standard zu zeigen?
2. **Multi-Theme-Test-Scope?** 4 Themes wie vorgeschlagen oder mehr/weniger?
3. **Version-Bump-Strategy?** v0.1.0 für die WP.org-Submission, dann v0.2.0
   für Phase D? Oder v1.0.0 für die submission (mehr Vertrauen)?
4. **Plugin-Check-Tool integration?** Lokal via wp-cli vs. WP-Admin-Plugin
   installieren?
5. **Tested-up-to-Versionen?** Wir requiren WP 7.0 + PHP 8.1. Sollen wir
   tatsächlich auf 6.8/6.9/7.0 testen, oder reicht 7.0?

## Phase D — Post-launch Enhancements (nicht jetzt)

Phase D ist explizit **nicht** Teil der v0.1-Launch-Arbeit. Sie ist in Memory
vollständig dokumentiert und sollte direkt nach dem ersten realen User-
Feedback angegangen werden.

**Phase D Track 1 — OAuth2 für SMTP** (Workspace + M365):
- BYO vs. hosted-Proxy-Entscheidung wartet auf Feldfeedback.
- ~3 Wochen Aufwand. Siehe `project_phase_a_smtp_decisions.md`.

**Phase D Track 2 — External CAPTCHA Providers**:
- Cloudflare Turnstile zuerst, dann hCaptcha + reCAPTCHA v3.
- Provider-Interface dokumentiert in `reference_phase_d_captcha_providers.md`.
- Architektur ist in B-a vorbereitet: `Guard::resolve_strategy()` ist der
  Single-Extension-Point.

**Phase D Track 2-d — Site-wide "Allow external redirects"-Toggle** (Phase C
deferral):
- Eine Checkbox in der Spam-Protection-Settings-Page erlaubt off-site
  Redirect-URLs (Mailchimp Landing Pages etc.).
- Heute filtert `wp_validate_redirect()` externe URLs silently zur Source-
  Page; mit dem Toggle on dürfen externe URLs durch.

## Ablauf jeder Iteration

1. Du beschreibst kurz **was** und **warum** als nächstes kommt.
2. Owner sagt Go oder korrigiert.
3. Du implementierst.
4. Du erklärst **was** gemacht wurde und **wo** + **wie** zu testen
   (FTP-Upload-Liste + 4–8 Test-Szenarien).

## Bestätige zum Start

Antworte kurz:

1. Status-Check: welche Phase und welcher erste Slice (Vorschlag:
   **Phase 8, Slice 8a — Accessibility-Audit**).
2. Offene Fragen vor dem Go (siehe „Offene Fragen für Phase 8" oben — Owner
   wird sie wahrscheinlich via AskUserQuestion beantworten).

Dann warte auf mein Go. 🚀

---

## Anhang — Übergreifende Erkenntnisse aus den bisherigen Chats

Diese Punkte sind nicht in den Memory-Dateien, aber **wichtig genug für jeden
Continuation-Chat zu wissen**:

### Browser-Claude-E2E-Workflow (Owner nutzt das öfter)

- Owner hat ein zweites Claude-Setup mit **Chrome-Extension** (Browser-Use).
- Nach größeren Phasen schickt Owner einen Self-Driven-Test-Prompt rüber.
- Browser-Claude kann selber Forms anlegen, konfigurieren, Webhooks setzen,
  webhook.site benutzen und Reports schreiben.
- Reports kommen als Markdown zurück mit PASS/FAIL pro TC + Bug-Liste.
- Übernimm gefundene Bugs als `fix:`-Commits zwischen Slices.

### WP Interactivity API Quirks die wir gelernt haben

- **SSR strippt static attrs wenn `data-wp-bind--X` darauf zielt** und der
  State nicht via `wp_interactivity_state()` gesetzt ist. Lösung: Werte in
  `data-wp-context` legen (per-form context-payload) statt als JS-Getter
  über `state.X`. Reference: Commit `beb775e`.
- **Pre-`init` `__()`-Aufrufe trippen WP 6.7+ Notice.** Display-Strings in
  `cron_schedules`-Filter etc. untranslated lassen. Reference: Commit
  `c092c70`.
- **`@starting-style` für Step-Fade** funktioniert in Chrome 117+ / Safari
  17.5+ / Firefox 129+. Graceful degradation für ältere Browser.
- **viewScriptModule braucht `--experimental-modules` Flag** in wp-scripts
  30.x. Im package.json bei build + start gesetzt.
- **Custom-Event-Dispatch** ist der Weg, eine WP-Interactivity-Action aus
  einem free-standing DOM-Listener zu triggern. Pattern: `data-wp-on--<custom-
  event>` auf dem Wrapper, dispatchEvent im Listener. Reference: Commit
  `bc63e67` (flinkform-skipped-changed).
- **`requestAnimationFrame`-Defer** ist nötig wenn `focus()` direkt nach
  `ctx.X = …` läuft. Reference: Commit `c430563`.

### Frontend-Bundle-Stand

- **view.js aktuell 7.84 KiB minified** (~3 KB gzipped). Budget 15 KB
  gzipped. Wachstum durch Phase B-a Spam-PoW-Solver +1 KiB.
- Editor-Bundle wuchs durch IntegrationsPanel + ConditionalLogicPanel +
  Spam-Protection-Panel + After-Submit-Panel auf ~32 KiB minified. Editor-
  Bundle hat kein Budget (Admin-Context).

### CSS-Doppel-Klassen-Strategie

- Field-level rules unter `.flinkform-form.flinkform-form` für (0,3,0)
  Specificity damit Theme-Overrides nicht reingrätschen. Form-Chrome
  (`__form`, `__submit`, `__step`, `__spam`) hat normale `.flinkform-form`-Scope.
  Strategie ist im Top-Kommentar von `src/form-container/style.scss`
  dokumentiert. **Niemals `!important`.**

### DB-Schema-Migration-Pattern

- `Database\Schema::DB_VERSION` ist die Source of Truth.
- `Plugin::init` checkt installierte vs. bundled version, ruft
  `Schema::create()` bei Mismatch. `dbDelta()` ist idempotent.
- **Bei jeder neuen Tabelle: DB_VERSION bumpen + `Schema::create()` um den
  neuen DDL erweitern + Schema::drop() um den DROP erweitern.**
- Phase A/B/C haben KEINE neuen Tabellen gebraucht — alles in `wp_options`,
  Transients oder Block-Attributen.

### Admin-Pages-Pattern für Settings-Pages

Phase A hat das Pattern eingeführt; Phase D Track 2 wird's wiederverwenden:
- `Admin\Menu::register_pages()` → `add_submenu_page()` mit Slug + Callback
- Page-Controller (z.B. `SmtpPage::render()`) hat `render()` + `dispatch()`
- `Menu::dispatch_actions()` (auf `admin_init`) routet POST-Action zum
  passenden Controller
- Single autoloaded `wp_options` key pro Modul
- Diagnostic-Status-Block oben als visuelle Live-State-Anzeige
- Test-Button als eigenes POST-Form (separater Nonce, eigene Action)
- Sensitive Values via `Settings\Secret` verschlüsseln (Password-Feld rendert
  immer empty, "leave empty to keep current"-Pattern)

Referenz: `includes/Admin/SmtpPage.php`. Memory:
`reference_perform_settings_page_pattern.md`.

### Verschlüsselung von Sensitive Settings

`Flinkform\Settings\Secret` ist die kanonische Stelle. Wird in Phase D Track 2
für CAPTCHA-Secrets reused. Ein Salt-Rotate in `wp-config.php` invalidiert
alle Stored Secrets (Operator muss neu eingeben — intentional, keine
in-Plugin-Key-Rotation-Logik nötig).

### Plugin-Konflikt-Detection

`Flinkform\Smtp\Transport` zeigt das Pattern. Phase D Track 2 sollte das auch
nutzen für externe CAPTCHA-Provider die mit anderen Anti-Spam-Plugins
kollidieren könnten. `get_option('active_plugins')` direkt (NICHT
`is_plugin_active()`, weil das auf Frontend nicht verfügbar ist).

### Spam-Challenge-Onboarding-Pointer

Phase B-a Architektur in `reference_perform_spam_challenge.md`. Phase D
Track 2 (External CAPTCHA Provider) komplett aufbereitet in
`reference_phase_d_captcha_providers.md` — Provider-Vergleichstabelle, API-
Endpoints + Test-Keys, Slice-Ordering, Migration-Story, Bundle-Strategy,
Privacy-Disclosure-Copy + Consent-First-Mode-Designoptionen. Quick-Re-
onboarding-Cheatsheet am Ende der Datei.

### Memory-Update-Hinweis für Continuation-Claude

Während dieser Chat-Session: wenn Owner explizit eine neue Konvention nennt
("ab jetzt immer X" oder "vermeide Y wegen Vorfall Z"), packe das als neuen
Memory-Eintrag rein. Memory-Schema ist in `/Users/dennisbuchwald/.claude/`-
Folder dokumentiert. Phase 8 wird möglicherweise neue Patterns einführen
(z.B. WP.org-Submission-Checklist, Accessibility-Audit-Workflow, Privacy-
Notice-Template) — diese gehören als `feedback_*.md` ODER `reference_*.md`
ins Memory.

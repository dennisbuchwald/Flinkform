# Audit-Prompt: Flinkform + Flinkform Pro

> Diesen Prompt in einem neuen Chat (Agent mit Repo-Zugriff) verwenden, um BEIDE
> Plugins — den kostenlosen Core UND das Pro-Add-on — sowie ihr Zusammenspiel
> unabhängig prüfen zu lassen. Stand: v0.2.6 (Phase-M-Migration + Audit-Polish
> abgeschlossen). Die Plugins sind pre-release (noch nicht auf WordPress.org).

---

Führe ein **vollständiges Security-, Performance-, Accessibility-, Code-Quality-,
Frontend/SEO- und DSGVO/Datenschutz-Audit** der beiden WordPress-Plugins
**„Flinkform"** (Free-Core) und **„Flinkform Pro"** (Add-on) durch — als *Paar*.

Flinkform ist ein block-editor-natives Formular-Plugin (WordPress 7.0+, PHP 8.1+,
Interactivity API, kein jQuery). Es rendert Formulare aus Gutenberg-Blöcken,
speichert Submissions in einer eigenen Tabelle, verschickt Benachrichtigungen
und schützt vor Spam mit einem eingebauten Proof-of-Work-Challenge. **Flinkform
Pro** ist ein separates Plugin, das über eine eingefrorene **Bridge** andockt und
die kostenpflichtigen Features liefert: Webhooks (REST + Cron + DB-Tabellen +
Log), SMTP-Versand, CSV-Export. Conditional Logic und Multi-Step bleiben bewusst
im Free-Core (untrennbar in die Blöcke verwoben).

## Codebases — Pfade & Zugriff

Beide liegen als Schwester-Verzeichnisse und als getrennte Git-Repos vor.

| Plugin | Lokaler Pfad | Git-Remote | Namespace |
|--------|--------------|------------|-----------|
| **Free-Core** | `/Users/dennisbuchwald/Arbeitsplatz/01_Code/04_Ressource/02_Eigenentwicklungen/flinkform` | `https://github.com/dennisbuchwald/flinkform` | `Flinkform\` |
| **Pro-Add-on** | `/Users/dennisbuchwald/Arbeitsplatz/01_Code/04_Ressource/02_Eigenentwicklungen/flinkform-pro` | `https://github.com/dennisbuchwald/flinkform-pro` | `FlinkformPro\` |

- Beide nutzen einen eigenen PSR-4-Autoloader (`includes/Autoloader.php`,
  Namespace → `includes/`). JS-Quellen in `src/`, kompiliert nach `build/` via
  `npm run build` (@wordpress/scripts). `build/` ist git-ignored und liegt nur im
  ausgelieferten ZIP — beim Lesen ggf. erst `npm run build` ausführen oder die
  `src/`-Quellen prüfen.
- **Zuerst lesen** (Kontext): `flinkform/includes/Bridge/README.md` (der
  eingefrorene Bridge-Vertrag), `flinkform/PERFORM_ROADMAP.md` (Phase M),
  beide Haupt-Dateien (`flinkform.php`, `flinkform-pro.php`),
  `flinkform/PERFORM_SPEC.md` falls vorhanden.

## Architektur, die du verstehen musst, bevor du auditierst

- **Model-B-Freemium:** Der Free-Core ist eine Plattform mit Erweiterungs-Nähten;
  Pro ist ein eigenes Plugin, das diese hookt. Pro-Code liegt NIE im Free-Repo.
  Pro hängt hart am Core via `Requires Plugins`-Header **und** einer Laufzeit-
  Versionssperre `PERFORM_PRO_MIN_CORE`.
- **Bridge-Nähte (eingefroren, sobald Pro live ist):** `perform_pro_features`
  (Filter → `Bridge\Features`-Façade), `perform_register_modules` (Action,
  Pro-Foothold), `perform_block_dirs` (Filter), `perform_spam_providers` (Filter),
  `perform_submissions_table_actions` (Action, CSV-Button),
  `perform.formContainer.inspectorPanels` (JS-Filter, Editor-Panels),
  `perform_submission_detail_after` (Action, Webhook-Deliveries),
  `perform_submissions_deleted` (Action, GDPR-Lösch-Kaskade). Submission-Lifecycle:
  `perform_before_submission`, `perform_after_submission`. Mail:
  `perform_email_notification`. Merge-Tags: `perform_merge_tags_context`,
  `perform_resolve_merge_tag`.
- **In Pro verschoben:** CSV-Export, SMTP (Transport + Settings-Seite), Webhooks
  (REST + 2 DB-Tabellen + Cron + Log-Seite + Editor-Integrations-Panel). **Im Free:**
  Builder, alle Feldtypen, Conditional Logic, Multi-Step, Spam-Challenge,
  Benachrichtigungen, Submissions-Ansicht.
- **DB:** Free besitzt `{prefix}perform_submissions`. Pro besitzt
  `{prefix}perform_webhooks` + `{prefix}perform_webhook_deliveries` (gleiche Namen
  wie früher im Core → adoptiert sie in-place via dbDelta; `perform_pro_db_version`
  Auto-Migrate; kein Drop bei Deactivate).

## Vorgehen & Output

Bewerte **jede der 8 Dimensionen auf einer Skala von 1–10**. Liste **alle Findings
mit `Datei:Zeile`, Severity (Kritisch/Hoch/Mittel/Niedrig) und konkretem Fix**.
Gib für JEDES Finding an, **welches Plugin** betroffen ist (Free / Pro / Bridge).
**Nur auditieren — nichts fixen.** Am Ende: pro Dimension der Score, eine
**priorisierte Top-10-Liste**, ein explizites **Go/No-Go für den Launch** und die
**Top-3-DSGVO-Maßnahmen**.

---

### Dimension 1 — Free/Pro-Trennung & Bridge-Integrität *(einzigartig & kritisch)*

- **Standalone-Free:** Läuft der Free-Core mit **null Fatals**, wenn Pro fehlt?
  Grep nach Referenzen auf verschobene Klassen (`Webhooks\*`, `Smtp\*`,
  `Export\*`, `WebhookLogPage/ListTable`) im Free-Core — es darf KEINE geben.
- **Graceful Degradation:** Verschwinden Pro-Features sauber (kein Button, kein
  Menü, kein Editor-Panel, kein Crash), wenn Pro inaktiv ist? Rendert ein Formular
  mit Webhooks-Config korrekt ohne Pro?
- **Keine Doppel-Registrierung** bei beiden aktiv: Admin-Submenüs, REST-Routes,
  Cron-Events, Hooks, Editor-Panels. Verhindern die `PERFORM_PRO_MIN_CORE`-Sperren
  zuverlässig, dass eine versions-gemischte Paarung (z. B. Pro 0.2.6 + Core 0.2.5)
  doppelt registriert?
- **Bridge-Vertrag:** Wird jede Naht vom Core korrekt gefeuert und von Pro korrekt
  konsumiert? Signatur-/Argument-Abweichungen? Timing-Bugs (admin_menu-Priorität 20,
  JS-Filter-Registrierung vs. Block-Render, `plugins_loaded`-Reihenfolge, der
  `perform_register_modules`-Foothold)? Hat eine Naht ihre Signatur seit der
  Dokumentation geändert (Vertragsbruch)?
- **Cross-Plugin-Hooks:** `perform_after_submission` (vom Core gefeuert, von Pros
  `SubmissionListener` konsumiert), `perform_submissions_deleted` (GDPR-Kaskade).
- **Namespaces & Cross-Refs:** Jede verschobene Klasse im richtigen Namespace +
  Autoloader-auflösbar. Pro nutzt `\Flinkform\…`-Klassen, die existieren
  (`Submissions\Repository`, `Admin\Menu`, `Settings\Secret`, `Privacy`).

### Dimension 2 — Sicherheit

- **Submit-Pipeline:** Nonce-Verifikation auf der Formular-Verarbeitung
  (`Submissions\Handler`), Konsistenz JS↔PHP Nonce-Namen. Honeypot + Time-Check +
  PoW-Challenge-Verifikation (`Spam\Guard`, `Spam\Challenge` — HMAC-Token: ist der
  Secret-Key sicher abgeleitet, ist die Token-Verifikation timing-safe via
  `hash_equals`?).
- **E-Mail-Header-Injection:** Werden Newlines/CRLF aus Reply-To / To / Subject
  entfernt, BEVOR sie in `wp_mail()` gehen? Achtung: das Merge-Tag-System
  (`Notifications\MergeTags`) rendert benutzer-eingegebene Feldwerte in Subject/
  Reply-To/Body — können eingeschleuste Header (z. B. `\nBcc:`) durchrutschen?
- **Open Redirect:** Die Thank-You-Redirect-Funktion — wird die Ziel-URL gegen
  `wp_safe_redirect()` / Whitelist geprüft (kein Redirect auf fremde Domains)?
- **XSS / Output-Escaping:** Alle Templates (`render.php` aller Blöcke,
  Admin-Seiten, Webhook-Log, SMTP-Status, Submission-Detail) — `esc_html`/
  `esc_attr`/`esc_url`/`wp_kses_post`. Im JS: kein `innerHTML`/`.html()` mit
  ungeprüften Daten (prüfe `view.js`, `integrations-panel.js`).
- **SQL-Injection:** Jeder `$wpdb`-Call mit Variablen-Input nutzt `prepare()`;
  Tabellennamen nur interpoliert (nie User-Input). Genau hinsehen:
  `Submissions\Repository`, Pro `Webhooks\Repository` + `DeliveryRepository`,
  und die `LIKE`-Query in `Privacy::find_by_email` (Full-Scan + Escape via
  `esc_like`?).
- **REST-Sicherheit (Pro `Webhooks\RestController`):** Jede Route mit korrektem
  `permission_callback` (nicht `__return_true`), `args` sanitized + validated.
- **SSRF (Pro Webhooks):** Der `Deliverer` POSTet an admin-konfigurierte URLs —
  ist `reject_unsafe_urls` gesetzt, validiert der REST-Endpoint die URL
  (`wp_http_validate_url`)? Reicht das, oder kann ein interner Endpoint
  (169.254.x, localhost) getroffen werden?
- **Capability-Checks:** Auf JEDER Admin-Aktion, jedem REST-Endpoint, jedem
  State-Change (Export, Webhook-Resend, SMTP-Save/Test, Submission-Delete).
- **Secret-Handling:** SMTP-Passwort-Verschlüsselung (`Settings\Secret`,
  AES-256-CBC, wp-config-Salt-Abhängigkeit) — wird der Klartext je geloggt/
  ausgegeben/per REST exponiert?
- **CSRF auf allen AJAX/GET-State-Changes**, `ABSPATH`-Guards auf allen
  PHP-Dateien, kein `eval`/`unserialize` von Untrusted-Daten.

### Dimension 3 — Performance

- **Frontend-Bundle:** Größe des Frontend-JS/CSS (Ziel laut Spec < 15 KB gzipped).
  Misst `build/form-container/view.js` (~966 Z. Multi-Step + Conditional-Logic).
  Conditional Asset Loading — werden Skripte/Styles NUR auf Seiten mit einem
  Flinkform-Block geladen (nicht site-weit)?
- **Interactivity API:** Hydration-Kosten, unnötige Re-Renders, Store-Größe bei
  Multi-Step.
- **DB pro Request:** Submission-Speicherung (`Handler` → `Repository`),
  Form-Lookup (`Forms\Locator`/`Indexer` — Caching/Transient?), die Submissions-
  Liste (Pagination, Indizes auf `form_id`/`status`/`created_at`).
- **Cron (Pro):** Der Webhook-Dispatcher läuft im **Minutentakt**
  (`perform_every_minute`). Ist das gerechtfertigt? Batch-Größe (BATCH_SIZE),
  atomares Claiming gegen Doppel-Versand, HTTP-Timeout, Last bei vielen Deliveries.
- **GDPR-Query:** `Privacy::find_by_email` macht `LIKE %email%` über die
  Submissions-Tabelle (Full-Table-Scan) — bei großen Tabellen ein Problem.

### Dimension 4 — Accessibility (WCAG 2.1 AA) *(forms-kritisch)*

- **Labels:** Jedes Feld mit korrekt assoziiertem `<label for>` (auch bei visuell
  versteckten / floating Labels). Required-Felder mit `aria-required` + sichtbarer
  Kennzeichnung.
- **Fehler-Handling:** Validierungsfehler via `aria-live="polite"` /
  `aria-describedby` angekündigt, Fehler-Felder mit `aria-invalid`. Server- UND
  Client-Fehler.
- **Multi-Step:** Tastatur-bedienbare Navigation (Tab/Enter), Focus-Management beim
  Schrittwechsel (Focus auf den neuen Schritt/erstes Feld), Progress-Indicator mit
  ARIA (`aria-valuenow`/-`valuemax`), Schritt-Ankündigung via Live-Region
  (`data-flinkform-step-announce`). Keine Layout-Shifts beim Wechsel.
- **Spam-Challenge:** Funktioniert die Math-Fallback-Variante ohne JS und ist sie
  zugänglich (Label, Fehler)? Kein Zugänglichkeits-Blocker durch das PoW-Widget.
- **Interaktive Elemente:** Echte `<button>`/`<a>` statt `<div onclick>`.
  Icon-Buttons mit `aria-label`. Submit/Next/Back klar benannt.
- **`prefers-reduced-motion`** auf allen Animationen/Transitions (`@starting-style`-
  Fade, Step-Übergänge). **Farbkontraste** der Default-Feld-Styles + Status-Badges
  gegen 4.5:1 prüfen. Sichtbarer **Focus-Indicator** auf allen Feldern/Buttons.
- **Editor-A11y:** Sind die Inspector-Controls + Block-UIs tastaturbedienbar?

### Dimension 5 — Code-Qualität & WordPress-Standards

- **WP Best Practices:** Sanitization/Escaping/Nonces durchgängig; keine
  deprecated APIs; kein `@`-Error-Suppression.
- **i18n:** Alle user-facing Strings in `__()`/`_e()`/`esc_html__()` mit der
  RICHTIGEN Text-Domain (Free = `flinkform`, Pro = `flinkform-pro` — prüfe
  die verbatim-verschobenen Pro-Dateien auf verpasste/über-ersetzte Domains).
  **i18n-Timing:** kein `__()` vor `init` (WP 6.7+ JIT-Notice) — Cron-Schedule-
  Display-Strings, Activation-Callbacks, früh registrierte Hooks.
- **Beschreibungstexte:** `wp_kses_post()` vs. `esc_html()` korrekt gewählt.
- **Prefixing:** Alle globalen Funktionen/Options/Hooks/Cron `perform_`-präfixiert.
- **Doc-Header:** `@package` (Flinkform vs. FlinkformPro), `@since` konsistent.
- **CSS:** Custom-Properties-Konsistenz, `!important`-Nutzung, Spezifität, die
  theme-resistente `.flinkform-form.flinkform-form`-Doppelklassen-Strategie.
- **JS:** Vanilla vs. wp.* Konsistenz, Error-Handling bei `apiFetch`/Fetch.
- **PHP:** strict_types, Typisierung, Single-Responsibility, tote/verwaiste
  Methoden nach der Migration.

### Dimension 6 — Frontend, Markup & SEO-Hygiene

- **Markup-Qualität:** Valides, semantisches HTML des gerenderten Formulars; kein
  Render-Blocking; **kein Cumulative Layout Shift** (reservierte Höhen, Multi-Step).
- **Kein SEO-Schaden:** Das Formular/Plugin darf die Seite nicht aufblähen oder
  unerwünschte Meta/Markup einschleusen. Inline-Styles/Scripts minimal.
- **Structured Data:** Falls Schema ausgegeben wird (i. d. R. minimal bei Forms) —
  Korrektheit prüfen; sonst als „n/a" markieren.
- **Bilder/Assets:** Falls Felder Bilder/Icons rendern: `width`/`height`,
  Lazy-Loading wo sinnvoll.

### Dimension 7 — DSGVO / Datenschutz *(erstklassig — explizit gewünscht)*

- **Daten-Inventar:** Welche personenbezogenen Daten werden verarbeitet/gespeichert
  und WO? (Submission-Feldwerte: Name/E-Mail/Telefon/Nachricht → `perform_submissions`;
  Webhook-Delivery-`response_body` → `perform_webhook_deliveries`; sonstiges?)
- **Datenminimierung:** Das Plugin behauptet, KEINE IP-Adressen / User-Agents zu
  speichern — verifiziere das in `Submissions\Handler`, im Spam-Challenge und in
  den Webhook-Deliveries.
- **Einwilligung/Consent:** Gibt es ein Datenschutz-Checkbox-Feld / einen Mechanismus,
  um vor der Verarbeitung eine Einwilligung einzuholen? Falls nicht — als Lücke +
  Empfehlung notieren (Consent-Feld als Block).
- **Auskunfts- & Löschrecht (WP Privacy Tools):** Free registriert Exporter + Eraser
  (`Privacy.php`) für Submissions; Pro registriert einen Exporter für Webhook-
  Delivery-Logs + eine **Lösch-Kaskade** via `perform_submissions_deleted`.
  **Prüfe:** Wird WIRKLICH jede personenbezogene Spur erfasst? Werden beim Erase/
  Delete eines Submissions die zugehörigen Delivery-Zeilen (Pro) zuverlässig
  mitgelöscht (keine verwaisten Daten)? Deckt der Export alle Daten ab? Bekannte
  Feinheit: die Kaskade löscht *still* — wird die Löschung im WP-Erasure-*Report*
  gezählt? (Optional verbesserbar.)
- **Externe Übertragung / Drittland:** Webhooks senden Submission-Daten an
  beliebige (admin-konfigurierte) URLs, evtl. außerhalb der EU; SMTP routet Mail
  über einen Provider. Sind beide im Datenschutzhinweis offengelegt (Pro
  `Privacy.php`)? Prüfe Vollständigkeit + Korrektheit der Texte.
- **Keine stillen externen Calls:** Bestätige, dass der Free-Core per Default NICHTS
  extern sendet (Spam-Challenge 100 % lokal) und Pro nichts, bis ein Admin etwas
  konfiguriert. Lädt der Editor/Frontend externe CDNs/Fonts (IP-Übertragung)?
- **Personenbezug in URLs/Logs:** Der Thank-You-Redirect kann `?perform_submission={id}`
  anhängen — ist das problematisch (ID in URL/Referrer)? Enthalten Webhook-
  Delivery-`response_body`s oder Logs personenbezogene Daten — sind sie löschbar?
- **Aufbewahrung:** Submissions werden unbegrenzt aufbewahrt (bis manuelles Löschen/
  Uninstall). Storage-Limitation-Risiko — gibt es / sollte es eine
  Aufbewahrungsfrist/Auto-Purge-Option geben? Dokumentiert?
- **Uninstall vs. Deactivate:** Deactivate erhält alle Daten (verifiziere); Free-
  Uninstall droppt `perform_submissions`, Pro-Uninstall droppt die Webhook-Tabellen
  + SMTP-Optionen. Bleibt nirgends personenbezogene Daten verwaist?
- **Empfehlungen:** Welche Maßnahmen für volle DSGVO-Konformität? (Consent-Feld,
  Aufbewahrungsfristen/Auto-Purge, Datenschutz-Doku, ggf. Eraser-Reporting.)

### Dimension 8 — Build, Packaging & .org-Readiness

- **ZIP-Hygiene:** Die ausgelieferten ZIPs schließen Dev-Ballast aus
  (`node_modules`, `src`, `.git`, Dev-`.md`, `.claude`, `package.json`) via
  `.distignore`, enthalten aber `build/`. Kein `settings.local.json`/Secrets im ZIP.
  Pro: `build/index.js` + `index.asset.php` vorhanden, Enqueue liest das Manifest.
- **Versionierung:** Header-Version = Konstante = `package.json` = `readme.txt`
  Stable-Tag, in beiden Plugins konsistent (0.2.6). `PERFORM_PRO_MIN_CORE` korrekt.
- **readme.txt (Free):** **Bekannte Lücke** — listet Webhooks/SMTP/CSV noch als
  „free" und behauptet „no premium tier". Muss für das Freemium-Modell neu
  geschrieben werden (blockt den .org-Upload). Bestätige Umfang.
- **Plugin Check:** Würde der Free-Core das WordPress.org „Plugin Check"-Tool
  bestehen? Blocker auflisten. GPL-Header korrekt in beiden.

---

## Bekannte Risiko-Bereiche (hier zuerst hinsehen)

1. **E-Mail-Header-Injection** über das Merge-Tag-System in Subject/Reply-To.
2. **Open-Redirect** im Thank-You-Redirect.
3. **GDPR-Lösch-Kaskade** — keine verwaisten Webhook-Delivery-Zeilen nach
   Submission-Löschung; Vollständigkeit von Export/Erase.
4. **A11y der Multi-Step-Navigation** (Focus-Management, Live-Region) + der
   Spam-Challenge.
5. **SSRF** bei Webhooks (interne Endpoints trotz `reject_unsafe_urls`?).
6. **Frontend-Bundle-Größe** (< 15 KB-Ziel) + Conditional Loading.
7. **Doppel-Registrierung / Versions-Mismatch** zwischen Free & Pro.
8. **readme.txt** veraltet (Freemium-Rewrite nötig).

## Output-Format

Pro Dimension: **Score 1–10** + kurze Begründung. Pro Finding:
`[SEVERITY] (Free|Pro|Bridge) datei.php:zeile — Titel` → Problem · Warum es zählt ·
Konkreter Fix. Am Ende: **priorisierte Top-10-Verbesserungen**, ein **Go/No-Go für
den Launch**, und die **Top-3-DSGVO-Maßnahmen**. **Implementiere nichts — nur
auditieren + berichten.**

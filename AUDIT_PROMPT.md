# Audit-Prompt: Flinkform + Flinkform Pro (v0.4.0)

> Diesen Prompt in einem neuen Chat (Agent mit Repo-Zugriff) verwenden, um BEIDE
> Plugins — den kostenlosen Core UND das Pro-Add-on — sowie ihr Zusammenspiel
> unabhängig und adversarial prüfen zu lassen. Stand: v0.4.0 (Flinkform-Rename,
> Extension-Seams, File-Upload, Newsletter-Integrationen, SMTP-Sende-Log).
> Die Plugins sind pre-release: Free steht kurz vor der WordPress.org-Einreichung,
> Pro soll kommerziell verkauft werden (Freemius geplant, noch nicht integriert).

---

## Auftrag

Führe ein **vollständiges, adversariales Audit** der beiden WordPress-Plugins durch:

- **Flinkform** (Free-Core): `/Users/dennisbuchwald/Arbeitsplatz/01_Code/02_Eigenentwicklungen/perform-forms`
- **Flinkform Pro**: `/Users/dennisbuchwald/Arbeitsplatz/01_Code/02_Eigenentwicklungen/perform-forms-pro`

(Die Ordner heißen noch nach dem alten Namen "perform-forms"; Git-Repos werden
noch umbenannt. Im Code ist alles bereits "flinkform".)

Prüfe Security, Korrektheit, DSGVO-Konformität (Code vs. Behauptungen!),
WordPress.org-Readiness, Accessibility, Performance und die Free↔Pro-Bridge.
**Vertraue keinem Kommentar und keinem readme-Claim — verifiziere am Code.**
Viele der heutigen Änderungen entstanden in hohem Tempo; genau dort liegen die
wahrscheinlichsten Fehler.

## Produkt-Kontext (für die Bewertung relevant)

- Block-editor-natives Formular-Plugin: Formulare bestehen aus Gutenberg-Blöcken
  (block.json v3), Frontend über die Interactivity API, kein jQuery, JS-Budget
  <15 KB gzipped. WordPress 6.5+, PHP 8.1+.
- **Positionierung:** privacy-first/performance-first Form-Plugin für deutsche/EU-
  Agenturen. Kern-USPs: keine IP-/UA-Speicherung, keine externen Dienste im
  Free-Core, PoW-Spamschutz statt reCAPTCHA, theme.json-Inheritance.
  DSGVO-Claims sind das zentrale Verkaufsargument — jede Abweichung zwischen
  Code und Datenschutz-Behauptung ist ein KRITISCHER Befund.
- **Free enthält bewusst:** Multi-Step (Page-Break), Conditional Logic, den
  PoW-Spamschutz (Web Worker), 13 Feldtypen inkl. Datum/URL/Telefon, Consent-Feld,
  Retention-Auto-Purge, Privacy-Exporter/-Eraser.
- **Pro enthält:** SMTP (AES-verschlüsselte Credentials, Konflikt-Erkennung,
  Sende-Log mit Retention), Webhooks (Cron-Dispatch, Retries, Delivery-Log,
  SSRF-Härtung), CSV-Export, Custom CSS, **File-Upload-Feld**,
  **Newsletter-Integrationen** (Brevo, Mailchimp, CleverReach).

## Architektur-Schnellüberblick

- Free: PSR-4 unter `includes/` (Namespace `Flinkform\`), Blöcke in `src/`
  (kompiliert nach `build/`). Submission-Pipeline in
  `includes/Submissions/Handler.php`: Nonce → Honeypot → signierter Timestamp →
  PoW/Math-Challenge → Feld-Validierung → Conditional-Strip →
  `flinkform_process_submission`-Filter → Persistenz → Hooks → Redirect.
- Pro dockt NUR über dokumentierte Seams an (`includes/Bridge/README.md` im
  Free-Core ist der Vertrag): `flinkform_pro_features`,
  `flinkform_register_modules`, `flinkform_block_dirs`, `flinkform_field_blocks`,
  `flinkform_field_extras`, `flinkform_sanitise_field`, `flinkform_validate_field`,
  `flinkform_process_submission`, `flinkform_submissions_before_delete`/`_deleted`,
  JS-Filter `flinkform.formContainer.inspectorPanels` + `.allowedBlocks`.
- Pro-Blöcke haben eine eigene Build-Pipeline (`blocks/src` → `blocks/build`).

## Frisch geänderter Code = Schwerpunkt der Prüfung

Diese Bereiche sind heute in hohem Tempo entstanden oder umgebaut worden.
Prüfe sie besonders kritisch und END-TO-END:

1. **Kompletter Rename** PerForm→perffo→flinkform (Prefixes, Hooks, Slugs,
   CSS-Klassen, Data-Attribute, Block-Namespace `flinkform/*`). Suche nach
   Resten: `perform`, `perffo`, `PerForm` — auch Bindestrich-Varianten
   (`perffo-…`), camelCase (`performXyz`), Strings in JS/JSON/SCSS/readme.
   Prüfe, dass JEDER Hook, auf den Pro hört, vom Free-Core auch wirklich
   gefeuert wird (und umgekehrt: Nonce-/Action-Namen, REST-Namespace
   `flinkform/v1`, Menu-Slugs, `FLINKFORM_VERSION`-Konstante).
2. **Spam-Stack:** `includes/Spam/Challenge.php` (HMAC-Token, signierter
   Render-Timestamp `mint_timestamp`/`verify_timestamp`, Replay-Guard,
   POW_DIFFICULTY=13) + der Web-Worker-Solver in
   `src/form-container/view.js` (`solvePoWInWorker`, Blob-URL-Worker,
   Main-Thread-Fallback). Stimmen Client- und Server-Bitmasken-Logik exakt
   überein? Ist der Worker-Code injektionssicher (kommt der Salt je aus
   Nutzereingaben)? CSP-Fallback korrekt? Timing-/Replay-Lücken?
3. **Extension-Seams + Sentinel-Mechanik (File-Upload):** Free
   `Handler::sanitise/validate_type/handle` + Pro `includes/Uploads/Uploader.php`
   und `Uploads/Module.php`. Der PENDING-Sentinel darf NIE persistiert werden
   (auch nicht über Flash-State/Repopulation!), Conditional-Logic-Strip +
   Multi-Step-Skip + Required-Gate müssen mit File-Feldern korrekt
   zusammenspielen. Upload-Security: wp_check_filetype_and_ext-Nutzung,
   Mime-Map, Größen-Clamping, Dateinamen-Randomisierung, .htaccess-Schutz
   (und seine Grenzen auf nginx), url_to_path-Containment, Lösch-Kaskade
   über `flinkform_submissions_before_delete` (Race-Conditions? Stash im
   Objekt-State bei mehreren Deletes im selben Request?).
4. **Newsletter-Modul (Pro):** `includes/Newsletter/` — Pflicht-Consent-Gate
   (lässt es sich umgehen? Was passiert bei manipulierten Block-Attributen?),
   Cron-Payload (landet ein API-Key oder personenbezogene Daten in
   wp_options/cron-Array? Cron-Args sind in der DB sichtbar!), Secret-
   Verschlüsselung, Provider-HTTP-Calls (Timeouts, Fehlerbehandlung,
   Retry-Logik), CleverReach-OAuth-Token-Caching.
5. **SMTP-Sende-Log (Pro):** `includes/Smtp/MailLog.php` + Schema v2.
   wp_mail_succeeded/-failed-Hooks, Datenminimierung (kein Body!), Purge-Logik,
   Privacy-Eraser (LIKE-Matching-Genauigkeit), Escaping der Log-Tabelle.
6. **UX-JS:** Submit-Loading-State + Success-Card-Fokus (`initSubmitFeedback`),
   bfcache-Restore, Duplikat-Form-ID-Re-Keying in `form-container/edit.js`
   (Endlosschleifen-Gefahr bei useSelect/useEffect?), lazy
   `getAllowedBlocks()`-Filter.
7. **Dropzone (Pro field-file):** view.js/style.scss — A11y (Fokus, Screenreader,
   der transparente Input), Verhalten ohne JS, `color-mix`-Browser-Support.

## Klassische Prüffelder (beide Plugins, vollständig)

- **Security:** Nonces/Capabilities auf jeder Mutation, prepared statements,
  Escaping (auch Admin-Tabellen/Log-Ausgaben), Mail-Header-Injection,
  Open Redirects, SSRF (Webhooks!), Stored XSS über Block-Attribute
  (Custom CSS! wp_kses-Strategie), REST-Permission-Callbacks, Path-Traversal.
- **DSGVO (Code vs. Claims):** readme.txt-Privacy-Sektion Zeile für Zeile gegen
  den Code. Grep nach REMOTE_ADDR/HTTP_USER_AGENT/wp_remote_* im Free-Core
  (muss leer bleiben!). Exporter/Eraser-Vollständigkeit über ALLE Tabellen
  (submissions, webhook_deliveries, mail_log) + Datei-Uploads. Cookie-Claims.
- **WP.org-Readiness (Free):** Plugin-Check-Killer, Prefix-Konsistenz,
  Text-Domain = Slug `flinkform`, Stable Tag vs. Header (0.4.0), keine
  Upsell-Verstöße, irreführende Claims (Multi-Step/Spam MÜSSEN free sein —
  prüfe, dass keine Gates mehr existieren), i18n-Vollständigkeit.
- **A11y:** WCAG 2.1 AA — Labels/Fieldsets, aria-required/-invalid/-describedby,
  Fokus-Management Multi-Step, aria-live, prefers-reduced-motion.
- **Korrektheit/Edge-Cases:** Multi-Step + Conditional Skip + File-Feld
  kombiniert; mehrere Formulare auf einer Seite; Block-Duplikate;
  Flash-Repopulation; Cron-Races; leere/manipulierte Block-Attribute.
- **Performance:** Frontend-JS-Budget, DB-Indizes vs. Queries, Transient-Nutzung,
  N+1 im Admin.

## Bekannt & gewollt (NICHT als Befund melden)

- Keine Lizenzierung/kein Update-Server im Pro (Freemius kommt als Nächstes).
- SMTP-OAuth (Google/Microsoft) bewusst auf später verschoben.
- Math-Fallback des Spam-Challenge ist bewusst schwach (dokumentiert).
- Newsletter-/Upload-Flows sind noch nicht gegen Live-APIs getestet.
- Interne Doku-Dateien (PERFORM_*.md) tragen noch alte Namen — egal, sind
  dist-excluded.
- CleverReach-doidata wird bewusst leer gesendet (keine IP-Erfassung).

## Arbeitsweise / Konventionen (einhalten, falls du Fixes vorschlägst)

- Code, Kommentare, Commit-Messages: Englisch. Antworten: Deutsch, knapp.
- Kein Em-Dash „—" in deutschen Texten.
- Commits nur unter Dennis' Namen, NIEMALS Claude als Co-Author.
- Einfachste saubere Lösung, bestehende Patterns übernehmen, kein
  Over-Engineering. Free/Pro-Trennung ist heilig: Pro fasst nie Core-Dateien an.
- Lokal verfügbar: PHP 8.5 (`php -l`), Node/npm (`npm run build` in beiden
  Repos). Kein lokales WordPress — keine Laufzeit-Tests möglich.

## Output-Format

1. **Executive Summary** (5 Sätze max): Gesamtzustand, Release-Empfehlung.
2. **Befunde** sortiert nach Schwere (KRITISCH/HOCH/MITTEL/NIEDRIG), jeweils:
   Datei:Zeile, konkretes Problem, Beweis (Code-Zitat), konkreter Fix-Vorschlag.
   Keine Theoriebefunde ohne Beleg am Code; im Zweifel den Angriffsweg/
   Reproduktionspfad skizzieren.
3. **Bridge-Verifikation:** Tabelle aller Free-Hooks ↔ Pro-Listener mit
   Übereinstimmungs-Check.
4. **DSGVO-Abgleich:** readme-Claim → Code-Beleg → Verdict.
5. **Go/No-Go** für (a) WP.org-Einreichung Free, (b) Pro-Beta an erste Kunden.

Kein Padding, keine Lobeshymnen — Befunde zählen. Wenn ein Bereich sauber ist,
ein Satz und weiter.

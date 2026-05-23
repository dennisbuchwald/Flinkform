# PerForm — Fortsetzungs-Prompt für Claude (neuer Chat)

> Diesen Prompt **komplett** in einen frischen Claude-Code-Chat im Projektordner
> `perform-forms/` einfügen. Antwortsprache: Deutsch. Arbeitsverzeichnis muss
> `…/02_Eigenentwicklungen/perform-forms/` sein.

---

## Kontext

Du übernimmst die Weiterentwicklung des WordPress-Plugins **PerForm** aus einem
vorigen Chat. Phase 0, 1 und 2 (komplett) sind abgeschlossen, getestet und
gepusht. Du startest mit dem nächsten Phasen-Schritt — vermutlich **Phase 3
(E-Mail-Notifications)**, sofern der Owner nichts anderes priorisiert.

**Plugin-Daten (fix, nicht ändern):**
- Anzeigename: **PerForm**
- WordPress.org-Slug: **`perform-forms`**
- Text Domain: `perform-forms`
- Namespace: `PerForm\` (PSR-4, unter `includes/`)
- Konstanten-Präfix: `PERFORM_`
- Funktions-Präfix: `perform_`
- Version: `0.1.0` (Scaffold, noch nicht bumped)
- Mindestens: WordPress 7.0 + PHP 8.1
- Autor: dbw media
- GitHub: https://github.com/dennisbuchwald/perform-forms- (Trailing-Dash ist
  vermutlich Tippfehler im Repo-Namen, Owner wollte ggf. umbenennen)
- Test-Umgebung: https://sandbox.dbw-development.de/index.php/perform/
  (WordPress 7.0, Owner lädt via FTP hoch)

## Was du als Allererstes tust

1. **`PERFORM_SPEC.md` quer lesen** (kannst überfliegen — dort ist die Vision
   und das vollständige Feature-Set; Architektur-Entscheidungen sind getroffen).
2. **`PERFORM_ROADMAP.md` lesen** für den Phasen-Plan (Phase 3+ sind die
   relevanten Sektionen).
3. **`INITIAL_PROMPT.md` überfliegen** — das ist der ursprüngliche Onboarding-
   Prompt; die Arbeitsweise-Regeln dort gelten weiter.
4. **`git log --oneline` ausführen** — die Commit-Messages dokumentieren jede
   getroffene Entscheidung sehr ausführlich. Lies vor allem die `feat:`-Commits.

## Aktueller Stand (Stand: Ende Phase 2)

### Was funktioniert

- **Phase 0** — Plugin-Foundation (Bootstrap, PSR-4-Autoloader,
  Activator/Deactivator-Stubs)
- **Phase 1** — Form rendert + Submit landet in DB
  - `perform/form` Container-Block mit immutable UUID als `formId`
  - 3 Field-Blocks: Text, Email, Textarea
  - DB-Tabelle `{prefix}_perform_submissions` (id, form_id, data JSON, created_at, status)
  - Submission-Handler: Nonce + Honeypot + Time-Check + Server-Validation +
    Flash-State (Cookie + Transient) für Error-Repopulation
  - Privacy-by-Default: keine IP/User-Agent-Speicherung
- **Phase 2a** — Submissions-Admin (`wp-admin → PerForm → Submissions`)
  - WP_List_Table mit Sortierung, Filter, Search, Bulk-Actions, CSV-Export
  - Detail-View mit auto-mark-as-read
  - Self-contained Submission-Format: `{fields: [{name, label, type, value}], _meta: {post_id, post_url, form_title}}`
- **Phase 2b** — 7 weitere Field-Blocks: Section-Heading (kein Feld), Number
  (min/max/step), Toggle (single Checkbox), Hidden (mit dynamischen Value-
  Sources via `HiddenResolver`), Select (single+multi), Radio-Group, Checkbox-
  Group. Handler kann jetzt Multi-Value (Arrays) speichern. Shared
  `OptionsEditor`-Komponente in `src/shared/options-editor.js`.
- **Phase 2c** — Forms-Admin (`wp-admin → PerForm → Forms`)
  - `Forms\Indexer` scant Posts via LIKE-Query auf `post_content`, aggregiert
    per `formId`, cached via Transient (5min TTL), invalidiert via `save_post`/
    `delete_post`/`wp_trash_post`/`untrash_post`
  - `title`-Attribut auf `perform/form` Container (internes Form-Label)
  - FormsListTable mit Source-Pages, Submission-Count, Last-Submission
  - Submissions-Liste zeigt jetzt Form-Title + Filter-Dropdown nutzt Titel
  - `form_title` wird als Snapshot in Submission-`_meta` gespeichert (überlebt
    Form-Renames/Löschungen)

### Bekannte UX-Beobachtungen (kein Bug, Polish-Backlog)
- **Hidden-Field zeigt "empty"** wenn User es einfügt aber Value-Source nicht
  konfiguriert (Default: `static` + leerer staticValue). Lösung wäre ein
  Inspector-Warning.
- **Doppelte Field-Labels** (z.B. zweimal "Number" wenn User mehrere
  Number-Fields ohne eindeutigen Label einfügt) machen Detail-View
  verwirrend. Lösung wäre Editor-Hinweis "Another field uses the same label".

### Repository / Git
- Remote: `origin → https://github.com/dennisbuchwald/perform-forms-.git`
- Branch: `main`
- Letzter Commit: `96db8e1 feat: Phase 2c — forms admin overview + form titles`
- Lokal sind ungetrackte Files: `CONTINUE_PROMPT.md` (du liest ihn grade),
  `PERFORM_ROADMAP.md` (vom Owner committet, falls nicht passiert ihn
  ignorieren), `PERFORM_SPEC.md` hat lokale Modifikationen (GDPR/CAPTCHA-
  Abschnitte vom Owner — gehören nicht in unsere Commits).

### Hochladen aufs FTP
- Komplettes `perform-forms/` Verzeichnis, **außer**: `node_modules/`, `src/`,
  `.git/`, `_wporg-svn/`, `PERFORM_*.md`, `CONTINUE_PROMPT.md`, `INITIAL_PROMPT.md`,
  `package*.json`, `deploy.sh`, `.editorconfig`. Auf dem Server brauchst du
  nur: `perform-forms.php`, `uninstall.php`, `readme.txt`, `includes/`,
  `build/`, `languages/`.

## Arbeitsweise (verbindlich, übernommen aus INITIAL_PROMPT.md)

- **Sprache:** Deutsch. Code-Kommentare auf Englisch (WordPress-Konvention).
- **Schritt für Schritt.** Erst Plan vorschlagen, auf "Go" warten, dann
  umsetzen. Keine großen Architektur-Umstellungen ohne Rücksprache.
- **WordPress Coding Standards** für PHP. Tabs zur Einrückung.
  `declare( strict_types = 1 );` in jeder PHP-Datei. Typisierte Signaturen.
- **i18n von Anfang an.** Jeder User-facing String durch `__()`/`_e()` mit
  Text-Domain `'perform-forms'`.
- **Security:** sanitize-in / escape-out, Nonces auf jedem Form-Submit,
  Capability-Checks im Admin, prepared statements für DB.
- **Performance-Budget:** Frontend-JS < 15 KB gzipped, kein jQuery.
- **Accessibility:** WCAG 2.1 AA.
- **Commit-Strategie:** Eine Phase = ein Commit (mit ausführlicher Body-
  Message). Hotfixes bekommen eigene Commits. Pre-Work des Owners
  (PERFORM_SPEC.md GDPR-Abschnitte, PERFORM_ROADMAP.md) **nicht** mitcommiten.
- **Co-Authored-By:** Trailer `Claude Opus 4.7 (1M context) <noreply@anthropic.com>`.
- **Push:** der Owner wünscht autonomes Pushen nach erfolgreichen Commits
  (siehe letzten Chat — Repo wurde explizit erstellt).

## Architektur-Entscheidungen aus den vorherigen Phasen (NICHT umkippen)

1. **Dynamic Blocks mit `render.php`** für Server-Render — kein React fürs
   Frontend-Display.
2. **Output-Contract:** render.php-Files ECHOEN direkt — kein `ob_start()` im
   Plugin (WordPress wrapt das automatisch). Steht als Kommentar oben in jeder
   render.php.
3. **`<InnerBlocks.Content />` ist Pflicht** im form-container `save()` — sonst
   gehen Inner-Blocks beim Save verloren. (Schmerzlich gelernt in Phase 1.)
4. **Flash-Token muss lowercase sein** (siehe Hotfix `e4229b8`) — `sanitize_key`
   lowercased beim Read, also auch beim Write.
5. **Label-Resolver:** Wenn `$attrs['label']` fehlt, hole Default aus
   `WP_Block_Type_Registry` bevor du auf `fieldName` fallback machst. Gutenberg
   speichert Default-Werte nicht im post_content.
6. **Form-Definition lebt im Block-Markup** (Variante A) — KEIN CPT. Locator
   parsed bei jedem Submit. Indexer cached die Forms-Liste.
7. **Submission-Format ist self-contained:** Labels + Types werden mit den
   Werten gespeichert, damit Submissions nach Form-Edits/Löschungen lesbar
   bleiben.
8. **Eigener PSR-4-Autoloader**, kein Composer/vendor/.
9. **Plugin-Hauptdatei heißt `perform-forms.php`** (nicht `perform.php` — WP.org
   Konvention).

## Empfohlene nächste Schritte (Plan vorschlagen, dann Rücksprache)

Vermutlich Phase 3 — der Owner hat zwischen Phase 3 / 4 / 5 / Polish noch nicht
entschieden, frag ihn am Anfang. Hier der Plan-Skelett für Phase 3:

### Phase 3 — E-Mail-Notifications

1. **Form-Block-Inspector erweitern** um "Notifications"-Panel (collapsed):
   - Toggle: "Send admin notification" (default on)
   - To, Subject, Body, Reply-To (alle merge-tag-fähig)
   - Toggle: "Send confirmation to submitter"
   - Email-field-Picker (Dropdown der Email-Felder im Form)
   - Confirmation-Subject + Body
2. **`Notifications\MergeTags`** — zentrale Resolver-Klasse:
   - `{field:<fieldName>}`, `{form:title}`, `{site:name}`, `{site:url}`,
     `{submission:date}`, `{submission:id}`
3. **`Notifications\Mailer`** — hooked an `perform_after_submission` (Action,
   neu einführen):
   - Liest Notification-Config aus Form-Attributes
   - Resolved Merge-Tags
   - Sendet via `wp_mail()` (kein SMTP-Modul — das ist Phase 7+)
   - Filter `perform_email_notification` für Override
4. **Action-Hook System** einführen (auch für später relevant):
   - `do_action( 'perform_before_submission', $form_id, $clean )`
   - `do_action( 'perform_after_submission', $submission_id, $form_id, $clean, $form_def )`

### Was *jetzt* nicht gemacht wird (Reminder)

- Kein File-Upload, kein Signature-Field, keine REST-API, kein WP-CLI, kein
  SMTP-Modul, kein Multisite-Support. (Siehe PERFORM_SPEC.md §5.)

## Ablauf jeder Iteration (verbindlich)

1. Du beschreibst kurz **was** du als Nächstes machen willst und **warum**.
2. Ich (der Owner) sage Go oder korrigiere.
3. Du implementierst.
4. Du erklärst kurz, **was** du gemacht hast und **wo** ich es testen kann
   (welche Files via FTP hochladen, was im Admin/Frontend zu prüfen).

## Bestätige zum Start

Antworte zuerst kurz:
1. Welche Phase und welchen ersten konkreten Slice du angehen willst
   (Vorschlag basierend auf diesem Stand).
2. Welche offenen Fragen du an mich hast, bevor du loslegst.

Dann warte auf mein Go.

# PerForm — Fortsetzungs-Prompt für Claude (neuer Chat)

> Diesen Prompt **komplett** in einen frischen Claude-Code-Chat im Projektordner
> `perform-forms/` einfügen. Antwortsprache: Deutsch. Arbeitsverzeichnis muss
> `…/02_Eigenentwicklungen/perform-forms/` sein.

---

## TL;DR — Wo wir stehen

**Phase 0, 1, 2, 3 und 4 sind abgeschlossen, getestet und gepusht.** Als Nächstes
startet **Phase 5 — Multi-Step Forms**. Repo, Sandbox, Memory und alle
Architektur-Entscheidungen sind tragfähig. Du kannst sofort mit Phase 5 in
Plan-then-Go-Manier loslegen.

## Kontext

Du übernimmst die Weiterentwicklung des WordPress-Plugins **PerForm**. Das ist
eine WP.org-Submission-fähige Form-Builder-Erweiterung mit Gutenberg-Blocks,
dynamic rendering, server-side validation, Style-Panel mit Theme-Inheritance,
Notifications-System und Custom-CSS-Escape-Hatch. Owner: dbw media. Plugin
wird auf einer echten Sandbox-WP-Instanz mit GeneratePress getestet.

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
- GitHub: **https://github.com/dennisbuchwald/perform-forms** (Repo wurde
  während Phase 4 umbenannt — trailing dash ist weg)
- Test-Umgebung: **https://sandbox.dbw-development.de/index.php/perform/**
  (WordPress mit GeneratePress, Owner lädt via FTP hoch)

## Was du als Allererstes tust

1. **Memory-Dateien überfliegen** im Pfad
   `~/.claude/projects/-Users-dennisbuchwald-Arbeitsplatz-01-Code-04-Ressource-02-Eigenentwicklungen-perform-forms/memory/`
   — die werden automatisch geladen, aber ein bewusster Read früh in der Session
   stellt sicher dass du die Konventionen kennst:
   - `feedback_commit_author.md` — **keine Co-Authored-By-Trailer in Commits**
   - `feedback_communication.md` — Deutsch + English in Code + Plan-then-Go
   - `feedback_quality_standard.md` — höchster Qualitätsanspruch ist das Minimum
   - `feedback_phase_slicing.md` — Phasen in a/b/c/d-Slices schneiden
   - `feedback_theme_resistance.md` — SCSS-Spezifitätsstrategie (kritisch für Phase 5 falls neue Field-Styles dazukommen)
   - `reference_perform_infrastructure.md` — Repo, Sandbox, FTP-Mechanik
2. **`PERFORM_SPEC.md` quer lesen** (Vision + Architektur-Entscheidungen sind dort).
3. **`PERFORM_ROADMAP.md` Section "Phase 5"** lesen — der konkrete Scope für jetzt.
4. **`git log --oneline -25`** — Commit-Messages der letzten Phasen sind sehr ausführlich, sie dokumentieren jede Architekturentscheidung.

## Aktueller Stand (Stand: Ende Phase 4, ~Commit `8f61a8e`)

### Was komplett funktioniert

- **Phase 0** — Plugin-Foundation (Bootstrap, PSR-4-Autoloader, Activator/Deactivator).
- **Phase 1** — Form rendert + Submit landet in DB.
  - `perform/form` Container-Block, immutable UUID als `formId`.
  - 3 Field-Blocks: Text, Email, Textarea.
  - DB-Tabelle `{prefix}_perform_submissions`.
  - Submission-Handler: Nonce + Honeypot + Time-Check + Server-Validation +
    Flash-State (Cookie + Transient) für Error-Repopulation.
- **Phase 2a/b/c** — Submissions-Admin + 7 weitere Field-Blocks + Forms-Admin.
- **Phase 3a/b/c** — Action-Hook-System, MergeTags, Mailer (Admin + Submitter).
  - Hooks: `perform_before_submission`, `perform_after_submission`.
  - Filter: `perform_email_notification` (4 Args: email, context, form_def, type).
  - Submitter-Confirmation mit Email-Field-Picker (auto-fill auf erstes
    Email-Feld, "touched"-Logik wie bei Reply-To).
- **Phase 4a/b/c/d** — Style-Panel komplett.
  - **4a**: theme.json-Inheritance, Primary Color, Submit Button Style (fill/outline/ghost).
  - **4b**: Field Style (bordered/underline/minimal) + Border Radius + Field Spacing + Label Position (above/beside/floating).
  - **4b-fix** (zwei Hotfixes): SCSS auf `.perform-form.perform-form`-Doppelklassen-Scope (0,3,0) Spezifität gehievt damit Theme-Overrides à la GeneratePress / Astra / Kadence nicht reingrätschen. Plus explizite Property-Coverage (background longhand + shorthand, focus-state override). **Siehe `feedback_theme_resistance.md` in der Memory.**
  - **4c**: Columns (1/2) + per-field `fullWidth`-Attribut (Toggle nur sichtbar bei 2-col) + Dark Mode via prefers-color-scheme.
  - **4c+ Material Floating**: existing Floating-Label upgraded auf Material-Outlined-Notch-Look (Label gleitet auf Top-Border, paint-overlap erzeugt Notch). CSS-only, kein JS.
  - **4c-fix**: `--perform-page-background` aus dem prefers-color-scheme:dark-Block entfernt — die Variable MUSS die ECHTE Page-Background-Color matchen, nicht die OS-Preference, sonst dunkler Notch auf weißer Seite.
  - **4d**: Custom CSS Textarea + Editor-Live-Preview + sanitisation (`wp_strip_all_tags` + drei IE-Legacy-Filter `expression(`, `behavior:`, `javascript:`).

### Was an Architektur unter der Haube steht (NICHT umkippen)

1. **Dynamic Blocks mit `render.php`** — kein React fürs Frontend-Display.
2. **Output-Contract:** render.php-Files ECHOEN direkt, kein `ob_start()`.
3. **`<InnerBlocks.Content />` Pflicht** im form-container `save()`.
4. **Flash-Token lowercase** (`sanitize_key` lowercased beim Read).
5. **Label-Resolver** holt Default aus `WP_Block_Type_Registry` falls `$attrs['label']` fehlt.
6. **Form-Definition lebt im Block-Markup** (Variante A), KEIN CPT. Locator parsed bei Submit, Indexer cached die Forms-Liste.
7. **Submission-Format self-contained** — Labels + Types werden mit Werten gespeichert, überlebt Form-Renames/Löschungen.
8. **Eigener PSR-4-Autoloader**, kein Composer/vendor/.
9. **Plugin-Hauptdatei `perform-forms.php`** (nicht `perform.php`).
10. **Block-Attribut-Struktur form-container:** `notifications` (`{admin: {…}, submitter: {…}}`), `appearance` (`{primaryColor, submitButtonStyle, fieldStyle, borderRadius, fieldSpacing, labelPosition, columns}`), `customCSS` (string). Alle Top-Level mit `default: {}` bzw. `''`. Spec.-Bumping siehe `feedback_theme_resistance`.
11. **CSS-Variablen-Kaskade:** `var(--perform-X, var(--wp--preset--Y, fallback))` — User-Override → theme.json → hartcodierter Fallback. Doku-Block in `src/form-container/style.scss`.
12. **Sanitisation für User-Input:** `sanitize_text_field` für Strings, Allow-Lists für Enums, Whitelist-Regex für CSS-Colors, `wp_strip_all_tags` + Pattern-Filter für Custom CSS.
13. **Repo-Konvention:** Author = Dennis Buchwald, **kein** Co-Authored-By-Trailer. Pushen autonom nach erfolgreichen Commits.

### Bekannte UX-Beobachtungen (kein Bug, Polish-Backlog)

- **Hidden-Field zeigt "empty"** im Admin wenn User es einfügt aber Value-Source nicht konfiguriert.
- **Doppelte Field-Labels** machen Detail-View verwirrend — Editor-Hinweis "Another field uses the same label" fehlt noch.
- **Multi-Theme-Sanity-Test-Pass** (Phase 4 Definition-of-Done) wurde noch nicht durchgeführt — Owner geht Twenty-Twenty-Five / Astra / Kadence manuell durch. Bei Issues kommen sie als Hotfix-Commits zwischen Phase 5 Slices rein.

### Repository / Git

- Remote: `origin → https://github.com/dennisbuchwald/perform-forms.git` (kein Trailing-Dash mehr).
- Branch: `main`.
- Letzter Commit: `8f61a8e feat: Phase 4d — custom CSS panel with editor live-preview`.
- Lokal ungetracked: `CONTINUE_PROMPT.md` (du liest ihn grade), `PERFORM_ROADMAP.md`. `PERFORM_SPEC.md` hat lokale GDPR/CAPTCHA-Modifikationen — gehören nicht in unsere Commits.

### Hochladen aufs FTP

Komplettes `perform-forms/`-Verzeichnis, **außer**:
`node_modules/`, `src/`, `.git/`, `_wporg-svn/`, `PERFORM_*.md`,
`CONTINUE_PROMPT.md`, `INITIAL_PROMPT.md`, `package*.json`, `deploy.sh`,
`.editorconfig`.
Auf dem Server: `perform-forms.php`, `uninstall.php`, `readme.txt`,
`includes/`, `build/`, `languages/`.

`build/` ist gitignored — vor jedem Upload `npm run build` lokal laufen.

## Arbeitsweise (verbindlich, gehört auch in Memory)

- **Sprache:** Deutsch. Code-Kommentare auf Englisch (WP-Konvention).
- **Plan-then-Go.** Nicht-trivialer Slice: kurz WAS + WARUM, Architekturwahlen
  begründen, 1–4 offene Fragen, dann auf "Go" warten. Kleine reaktive Fixes
  dürfen sofort losgehen.
- **WordPress Coding Standards.** Tabs, `declare( strict_types = 1 );`,
  typisierte Signaturen.
- **i18n** durch `__()`/`_e()` mit Text-Domain `'perform-forms'`.
- **Security:** sanitize-in / escape-out, Nonces, Capability-Checks, prepared
  statements.
- **Performance-Budget:** Frontend-JS < 15 KB gzipped, kein jQuery.
- **Accessibility:** WCAG 2.1 AA.
- **Commits:** EIN Slice = EIN `feat:`-Commit mit ausführlichem Body.
  Hotfixes als eigene `fix:`-Commits. **Author = Dennis Buchwald, KEIN
  Co-Authored-By-Trailer.** PERFORM_SPEC.md / PERFORM_ROADMAP.md / CONTINUE_PROMPT.md
  NICHT mitcommiten ausser explizit gewünscht.
- **Push autonom** nach erfolgreichen Commits.

## Phase 5 — Multi-Step Forms (jetzt dran)

Aus der Roadmap, kompakt:

**Goal:** User kann Multi-Step-Form (Wizard / Funnel) bauen, indem er
Page-Break-Blöcke zwischen Felder einfügt, mit Progress-Anzeige und
Per-Step-Validation.

### Plan-Skelett (Vorschlag — vor Implementation mit Owner abstimmen)

**Slice 5a — `perform/page-break` Block + statisches Multi-Step-Rendering**
- Neuer Block `perform/page-break` (parent: `perform/form`)
  - Attribute: `label` (optional, für Progress-Indicator)
  - Inspector mit Label-Input
- Container-Render parsed Inner-Blocks, splittet bei `page-break`, rendert
  alle Steps in einen `<div class="perform-form__step" data-step-index="…">`.
- Erstmal **alle** Steps gleichzeitig sichtbar im Editor (visuelles Divider)
  + im Frontend zunächst der Step-1-only Bereich (5b regelt navigation).
- Editor: Page-Break als sichtbarer Divider mit Step-Label.

**Slice 5b — Interactivity API: Next/Back, Per-Step-Validation, Progress**
- WordPress Interactivity-API-Store: `currentStep`, `totalSteps`, `errors`.
- Frontend-JS via `viewScriptModule` (oder `viewScript`). Aktuell hat das
  Plugin Null Frontend-JS — das ist die erste echte JS-Lieferung. Budget
  beachten (< 15 KB gzipped).
- "Next"-Button: HTML5-Validation pro Step (`form.checkValidity()` der
  aktuellen Step-Felder) plus Server-side-Trigger erst auf "Submit".
- "Back"-Button: validation skip, einfach zurück.
- State über `data-perform-state-*` Attribute synchronisiert.
- Animation: subtle Fade/Slide-Transition zwischen Steps (CSS).

**Slice 5c — Progress-Indicator + Inspector-Settings**
- Form-Container-Inspector: neues PanelBody "Multi-Step" mit:
  - Progress-Indicator-Style: Bar (default) / Dots / Step Numbers (1 of 3) / None
  - Optional: Step-Labels anzeigen
- Server-Render des Progress-Indicators mit ARIA-Roles (progressbar).
- Reactive State-Updates via Interactivity-API.

**Slice 5d — Submit-Button auf letztem Step + Polish**
- Submit-Button nur auf letztem Step sichtbar.
- Nach erfolgreichem Submit: gesamtes Form mit Success-Message ersetzen.
- Focus-Management: nach `nextStep()` Focus auf erstes Field des neuen Steps
  (für Keyboard-Nav + Screen-Reader).
- "Continue from where you left off" — nope, das ist Post-MVP.

### Was *jetzt* nicht gemacht wird (Reminder)

- Conditional Logic / Step-Skipping (Phase 7).
- File Upload, Signature Field, REST-API, WP-CLI, SMTP, Multisite. Webhooks
  sind Phase 6. CAPTCHA ist parallel-track, hat keinen Slice-Slot in Phase 5.

## Ablauf jeder Iteration

1. Du beschreibst kurz **was** und **warum** als nächstes kommt.
2. Owner sagt Go oder korrigiert.
3. Du implementierst.
4. Du erklärst **was** gemacht wurde und **wo** + **wie** zu testen
   (FTP-Upload-Liste + 4–8 Test-Szenarien).

## Bestätige zum Start

Antworte kurz:

1. Status-Check: welche Phase und welcher erste Slice (Vorschlag: **5a — `perform/page-break` Block + statisches Multi-Step-Rendering**).
2. Offene Fragen vor dem Go (Slicing-Granularität, Editor-UX für Page-Break-Block, Server- vs. Client-Render-Architektur für Multi-Step).

Dann warte auf mein Go. 🚀

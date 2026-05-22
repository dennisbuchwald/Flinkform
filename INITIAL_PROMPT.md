# PerForm — Initialer Prompt für Claude (neuer Chat)

> Diesen Prompt **komplett** in einen frischen Claude-Code-Chat im Projektordner
> `perform-forms/` einfügen. Antwortsprache: Deutsch. Arbeitsverzeichnis muss
> `…/02_Eigenentwicklungen/perform-forms/` sein.

---

## Kontext

Du startest die Entwicklung des WordPress-Plugins **PerForm** komplett neu in
diesem frischen Chat. Der bisherige Chat ging um ein anderes Plugin (den
"Logo Slider" Carousel-Block) und ist nicht mehr aktiv. Du übernimmst hier mit
einem fertigen Scaffold + einer ausführlichen Spec.

**Plugin-Daten (fix, nicht ändern):**
- Anzeigename: **PerForm**
- WordPress.org-Slug: **`perform-forms`** (der reine Slug `perform` war auf WP.org schon vergeben)
- Text Domain: `perform-forms`
- Namespace: `PerForm\` (PSR-4, später unter `includes/`)
- Konstanten-Präfix: `PERFORM_`
- Funktions-Präfix: `perform_`
- Version: `0.1.0` (Scaffold)
- Mindestens: WordPress 7.0 + PHP 8.1
- Lizenz: GPL v2 or later
- Autor: dbw media

## Was du als Allererstes tust

1. **`PERFORM_SPEC.md` komplett lesen.** Das ist die maßgebliche
   Projekt-Spezifikation (Vision, Feature-Set, MVP-Scope, Architektur-Richtungen,
   Qualitäts-Standards). Alle Architektur-Entscheidungen orientieren sich daran.
2. **`readme.md` und `readme.txt` überfliegen** — die spiegeln den
   Scaffold-Status (v0.1.0, keine funktionalen Form-Blöcke).
3. **`perform.php` lesen** — Plugin-Header, Konstanten, Bootstrap-Stub.
4. **`deploy.sh` lesen** — verstehst du, wann das relevant wird (erst nach
   WP.org-Approval und initialem `svn checkout` nach `./_wporg-svn`).
5. **Carousel-Plugin als Referenz**: `../infinite-logo-carousel-block/` ist das
   bereits live veröffentlichte Schwester-Plugin desselben Autors (v1.5.0,
   WordPress.org). Da steckt die gesamte Plugin-Check-, i18n- und
   Release-Erfahrung drin. Nutze es als **Vorlage für Workflows**
   (Translation-Pipeline `.po`/`.mo`/`.json`, Deploy-Script, Versionierung an
   allen Stellen synchron, Changelog-Pflege). **Übernimm aber nicht den
   Architektur-Stil** — der Carousel-Block nutzt die ältere Inline-PHP-
   Registrierung. PerForm bekommt die *moderne* Architektur:
   `block.json` + `render.php` + Interactivity API + PSR-4 unter `includes/`.

## Arbeitsweise (verbindlich)

- **Sprache:** Deutsch. Code-Kommentare auf Englisch (WordPress-Konvention).
- **Schritt für Schritt.** Erst Plan vorschlagen, auf "Go" warten, dann
  umsetzen. Keine großen Architektur-Umstellungen ohne Rücksprache.
- **WordPress Coding Standards** für PHP. Tabs zur Einrückung. `declare( strict_types = 1 );`
  in jeder PHP-Datei. Typisierte Funktions-Signaturen.
- **i18n von Anfang an.** Jeder User-facing String durch `__()`/`_e()` mit
  Text-Domain `'perform-forms'`. Bei Block-JS später `wp_set_script_translations()`.
- **Security:** sanitize-in / escape-out, Nonces auf jedem Form-Submit,
  Capability-Checks im Admin, prepared statements für DB.
- **Plugin Check** (offizielles WP.org-Tool) muss sauber sein. Im Carousel-Repo
  gibt's Erfahrung mit den typischen Stolperfallen: `Stable tag = Version`,
  `Tested up to` = aktuelle WP-Major, keine `load_plugin_textdomain()`-Aufrufe
  mehr (WP.org lädt automatisch), korrekte File-Header.
- **Performance-Budget:** Frontend-JS < 15 KB gzipped, kein jQuery, keine
  großen Libraries.
- **Accessibility:** WCAG 2.1 AA — Labels mit Inputs verknüpft,
  Error-Messages über `aria-live`, voll keyboard-navigierbar.

## Empfohlene erste Schritte (Plan vorschlagen, dann Rücksprache)

1. **Dependencies installieren:** `npm install`
2. **Erster funktionaler Slice — der Form-Container-Block:**
   - `src/form-container/block.json` (dynamic block mit `render.php`)
   - `src/form-container/edit.js`, `src/form-container/index.js`, `src/form-container/save.js`
   - `src/form-container/render.php` (server-seitiges Rendern als `<form>` mit Nonce)
   - `includes/Blocks/FormContainer.php` (Registrierung über `init`-Hook)
3. **Plugin-Bootstrap aktivieren:** `perform.php` → Autoloader laden,
   `\PerForm\Plugin::instance()->init()` aufrufen.
4. **Erstes Submission-Setup:**
   - `includes/Database/Schema.php` mit `dbDelta()` für `{prefix}_perform_submissions`
   - Activation-Hook `perform_activate()` ruft das auf
5. **Erstes Field-Block:** `src/field-text/` als simpelster Case (Text-Input).

## Was du *jetzt noch nicht* machst

- Kein File-Upload-Field (post-MVP)
- Kein Signature-Field (post-MVP)
- Keine REST-API-Routes (post-MVP)
- Keine WP-CLI-Commands (post-MVP)
- Keine SMTP-Modul-Aktivierung (post-MVP, das Modul *Vorbereiten* ist okay)
- Kein Multisite-Support (post-MVP)

Siehe `PERFORM_SPEC.md` §5 (Non-Features) und §6 (MVP-Scope).

## Ablauf jeder Iteration

1. Du beschreibst kurz **was** du als Nächstes machen willst und **warum**.
2. Ich (der User) sage Go oder korrigiere.
3. Du implementierst.
4. Du erklärst kurz, **was** du gemacht hast und **wo** ich es testen kann.

## Repository / Git

- Im Scaffold-Commit existiert `git init` schon, mit einem initialen Commit
  in meinem Namen ("Dennis Buchwald"). Pushe nichts, bevor du das GitHub-Remote
  mit mir geklärt hast.
- Commits in meinem Namen, deutscher Imperativ in der ersten Zeile
  ("Form-Container-Block hinzufügen"), bei mehrteiligen Commits gerne ausführlicher
  Body. Co-Authored-By-Trailer wie üblich.

## Bestätige zum Start

Antworte zuerst kurz:
1. Welche Architektur-Richtung du aus der Spec übernimmst (in eigenen Worten,
   damit ich sehe dass du sie gelesen hast).
2. Welchen ersten konkreten Slice du angehen willst (Vorschlag).
3. Welche offenen Fragen du an mich hast, bevor du loslegst.

Dann warte auf mein Go.

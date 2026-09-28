# Release-Handgriffe (manuell)

Was `./deploy.sh` **nicht** erledigt und deshalb von Hand passieren muss. Die
Datei ist bewusst kurz: sie beschreibt nur die Schritte, die ein Skript nicht
abnehmen kann, weil sie über eine Weboberfläche laufen oder eine Entscheidung
brauchen.

## 1. Versionsnummer an allen Stellen

`deploy.sh` bricht ab, wenn eine der ersten drei auseinanderläuft:

- `flinkform.php`: Header `Version:` **und** die Konstante `FLINKFORM_VERSION`
  (letztere hängt am Asset-Cache-Busting und wird vom Pre-Flight-Check *nicht*
  geprüft)
- `readme.txt`: `Stable tag:` plus je ein neuer Eintrag unter `== Changelog ==`
  und `== Upgrade Notice ==`. Die readme führt nur die letzten Releases
  (derzeit ab 1.13.0), ältere Einträge wandern nach `changelog.txt`. Grund:
  WordPress.org kürzt einen zu langen Changelog stillschweigend, und jeder
  Changelog-Satz ist ein String, der in GlotPress übersetzt werden muss.
- `changelog.txt`: denselben Changelog-Eintrag oben einfügen (vollständige
  Historie, wird mit dem Plugin ausgeliefert)
- `package.json`: `version`

`Tested up to:` in `readme.txt` und im Plugin-Header gegen die aktuelle
WordPress-Version prüfen. Ein veralteter Wert blendet im Verzeichnis die
Warnung „may no longer be maintained" ein.

## 2. Screenshots und Grafiken

Alles aus `.wordpress-org/` wird von `deploy.sh` nach SVN `assets/` gespiegelt
(`rsync --delete`). Benötigte Dateien, Maße und Reihenfolge stehen in
[.wordpress-org/README.md](.wordpress-org/README.md). Die Nummern der
Screenshot-Dateien müssen zur Liste unter `== Screenshots ==` in `readme.txt`
passen, sonst stehen falsche Bildunterschriften unter den Bildern.

## 3. Übersetzungen bei GlotPress nachziehen (nach jedem Release)

Die deutsche Oberfläche kommt aus zwei Quellen, die dasselbe sagen müssen:

- **Mitgeliefert:** `languages/flinkform-de_DE.*` und `flinkform-de_DE_formal.*`
  (.po, .mo, je 18 JED-JSON-Dateien für die Editor-Skripte).
- **Sprachpaket von translate.wordpress.org:** entsteht, sobald das Projekt
  "Stable" für eine Sprache 90 % erreicht, und hat danach **Vorrang** vor den
  mitgelieferten Dateien. Fällt Stable unter 90 %, wird kein neues Paket mehr
  gebaut und neue Strings bleiben englisch.

GlotPress ist deshalb die Quelle der Wahrheit, die mitgelieferten Dateien
spiegeln sie. de_DE ist auf WordPress.org die Du-Form, de_DE_formal die
Sie-Form. Die Werkzeuge liegen außerhalb des Repos in
`../flinkform-seo-baseline/glotpress/` (Anleitung dort in `ANLEITUNG.md`).

Ablauf pro Release, sobald der neue Tag in SVN ist (GlotPress liest die
Originale aus dem Stable-Tag, das dauert bis zu einer Stunde):

1. `python3 build_stable_po.py <repo> .` erzeugt aus der mitgelieferten .po die
   Importdateien für de_DE und de_DE_formal, inklusive der block.json-Strings
   mit Kontext ("block title", "block description", "block keyword").
2. Readme-Strings: `php tools/readme2pot.php <repo>/readme.txt flinkform-readme.pot`,
   neue oder geänderte Sätze in `readme_translations.py` übersetzen,
   `python3 build_readme_po.py flinkform-readme.pot .`.
3. Bei GlotPress für Stable und Stable Readme, jeweils de/default und
   de/formal, die passende .po importieren (Import Translations) und freigeben.
4. Gegenprobe: `check_coverage.py` gegen den GlotPress-Export der offenen
   Strings, Ziel 100 %.
5. Wurden Texte geändert, `sync_bundled.py` laufen lassen und die
   mitgelieferten Dateien mit dem nächsten Release ausliefern.

Neue JS-Strings brauchen zusätzlich neue JED-Schlüssel (siehe Memory-Notiz
`i18n-regeneration.md`), bevor `sync_bundled.py` sie füllen kann.

## 4. Release auslösen

Reihenfolge, die sich bewährt hat:

```bash
npm run build
git push
git tag v1.13.3 && git push origin v1.13.3
./deploy.sh 1.13.3
```

Danach die Website (`01_Webprojekte/flinkform.de`) nachziehen: `FREE_VERSION` in
`lib/site.ts` und der Changelog-Eintrag in `content/de/roadmap.ts` **und**
`content/en/roadmap.ts`.

Gegenprüfen:

```bash
svn list https://plugins.svn.wordpress.org/flinkform/tags/
svn cat https://plugins.svn.wordpress.org/flinkform/trunk/readme.txt | grep "Stable tag"
```

Die Tag-Verifikation am Ende von `deploy.sh` meldet gern „inconclusive", weil
SVN lexikografisch sortiert und `1.9.0` hinter `1.12.1` steht. Kein Fehler.

## 5. Nur readme oder Assets ändern, ohne Release

`deploy.sh` ist dafür nicht geeignet: es legt immer einen neuen Tag an, und
`svn copy trunk tags/<version>` auf einen vorhandenen Tag erzeugt
`tags/<version>/trunk`. Für eine reine readme- oder Asset-Änderung ohne
Versionssprung von Hand:

```bash
cd _wporg-svn && svn update
cp ../readme.txt trunk/readme.txt
cp ../readme.txt tags/<Stable tag>/readme.txt
rsync -av --delete --exclude=README.md ../.wordpress-org/ assets/
svn add --force assets
svn status            # nur readme.txt und assets/ dürfen auftauchen
svn commit -m "Update readme and assets" --username dbwmediadennis
```

Die readme im Stable-Tag steuert das Verzeichnis und die GlotPress-Strings,
die in trunk nur die "Development Readme".

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
  und `== Upgrade Notice ==`
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

## 3. Deutsche readme bei GlotPress einspielen

Die readme im Verzeichnis bleibt englisch. Deutschsprachige Nutzer bekommen die
Übersetzung aus GlotPress ausgespielt, sobald sie dort steht:

1. <https://translate.wordpress.org/projects/wp-plugins/flinkform/stable-readme/de/default/>
2. Abschnitte aus [.wordpress-org/readme-de_DE.txt](.wordpress-org/readme-de_DE.txt)
   einfügen. GlotPress übersetzt Zeichenkette für Zeichenkette, die Vorlage hat
   deshalb dieselbe Struktur und Reihenfolge wie `readme.txt`.
3. Als eigener PTE freigeben (das Konto `dbwmediadennis` ist Plugin-Autor und
   darf das selbst).

Ändert sich `readme.txt`, muss `readme-de_DE.txt` mitgepflegt und in GlotPress
nachgezogen werden.

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

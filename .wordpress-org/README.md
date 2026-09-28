# WordPress.org-Assets

Alles in diesem Ordner spiegelt `deploy.sh` per `rsync --delete` nach SVN
`assets/`. Was hier liegt, steht nach dem nächsten Release im Verzeichnis; was
hier fehlt, wird dort gelöscht. Ausgenommen ist nur diese Datei (`README.md`).
Unterordner werden mitgespiegelt: `blueprints/blueprint.json` landet in SVN
unter `assets/blueprints/blueprint.json`.

## Benötigte Dateien

| Datei | Maße | Zweck |
| --- | --- | --- |
| `icon-256x256.png` | 256 × 256 | Plugin-Icon, quadratisch (Retina) |
| `icon-128x128.png` | 128 × 128 | Plugin-Icon, Standardauflösung |
| `banner-1544x500.png` | 1544 × 500 | Kopfbanner, Retina |
| `banner-772x250.png` | 772 × 250 | Kopfbanner, Standardauflösung |
| `screenshot-1.png` | frei, siehe unten | Kontaktformular aus Blöcken im Block-Editor, Seitenleiste offen |
| `screenshot-2.png` | frei | Mehrstufiges Formular im Frontend mit Fortschrittsbalken |
| `screenshot-3.png` | frei | Regel der bedingten Logik in der Block-Seitenleiste |
| `screenshot-4.png` | frei | Flinkform > Submissions mit Suche und Filtern |
| `screenshot-5.png` | frei | Formular im Frontend, zu sehen: kein Captcha, nichts zu lösen |
| `screenshot-6.png` | frei | Dasselbe Formular in zwei Themes (Farben aus theme.json) |
| `blueprints/blueprint.json` | - | Live-Vorschau (WordPress Playground), siehe unten |

Stand heute vorhanden: Icons und Banner. **Die Screenshots fehlen komplett** und
sind der Punkt mit dem größten Hebel im Listing.

## Regeln

- **Reihenfolge ist Vertrag.** Die Nummer im Dateinamen bestimmt, welche Zeile
  aus `== Screenshots ==` in `readme.txt` als Bildunterschrift darunter steht.
  Wird eine Datei eingefügt oder getauscht, muss die Liste in `readme.txt`
  mitgezogen werden. Lücken in der Nummerierung sind nicht erlaubt.
- **Format.** PNG (`.jpg` und `.gif` gehen ebenfalls, aber einheitlich
  bleiben). Icons und Banner exakt in den Maßen oben, sonst skaliert
  WordPress.org unsauber.
- **Screenshot-Größe.** Keine feste Vorgabe. Bewährt haben sich 1280 × 800 oder
  1440 × 900, quer, mit sichtbarem Kontext (Editor-Sidebar, Admin-Menü). Das
  Verzeichnis zeigt sie in voller Breite an, feine 12-px-Schrift ist danach
  nicht mehr lesbar.
- **Bildunterschriften** stehen unter `== Screenshots ==` in `readme.txt` und
  werden über GlotPress mit übersetzt.
- **Sprache.** Die Screenshots zeigen die englische Oberfläche, weil die readme
  im Verzeichnis englisch ist. Deutsche Varianten heißen
  `screenshot-1-de_DE.png` usw. und erscheinen auf de.wordpress.org, sobald
  die deutsche readme in GlotPress steht.
- **Keine Preisangaben, keine Werbeflächen** im Banner. WordPress.org
  beanstandet werbliche Assets.

## Nach dem Ablegen

Die Bilder gehen mit dem nächsten `./deploy.sh <version>` mit. Ein eigener
Release ist dafür nicht nötig, Assets liegen in SVN neben den Tags und wirken
sofort. Kontrolle nach ein paar Minuten auf
<https://wordpress.org/plugins/flinkform/>.

## Live-Vorschau (blueprints/blueprint.json)

Das Blueprint installiert Flinkform aus dem Verzeichnis in WordPress
Playground, legt die Seite "Multi-step form demo" mit einem dreistufigen
Formular samt bedingter Logik an und öffnet sie. Erzeugt wird die Datei von
`make-blueprint.py` im Ordner `flinkform-seo-baseline` (neben dem Repo), dort
steht auch das Block-Markup.

Testen, bevor es live geht:

- Lokal: `npx @wp-playground/cli@latest server --blueprint=.wordpress-org/blueprints/blueprint.json`
  und `http://127.0.0.1:9400/?pagename=flinkform-demo` öffnen.
- Im Browser nach dem Deploy:
  `https://playground.wordpress.net/?blueprint-url=https://ps.w.org/flinkform/assets/blueprints/blueprint.json`

Den Button "Live Preview" sehen zuerst nur Committer. Für alle sichtbar wird
er, wenn ein Committer im Plugin-Admin unter **Advanced View** die Vorschau auf
"public" stellt.

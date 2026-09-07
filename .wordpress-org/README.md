# WordPress.org-Assets

Alles in diesem Ordner spiegelt `deploy.sh` per `rsync --delete` nach SVN
`assets/`. Was hier liegt, steht nach dem nächsten Release im Verzeichnis; was
hier fehlt, wird dort gelöscht. Ausgenommen sind die beiden Arbeitsdokumente
`README.md` (diese Datei) und `readme-de_DE.txt`.

## Benötigte Dateien

| Datei | Maße | Zweck |
| --- | --- | --- |
| `icon-256x256.png` | 256 × 256 | Plugin-Icon, quadratisch (Retina) |
| `icon-128x128.png` | 128 × 128 | Plugin-Icon, Standardauflösung |
| `banner-1544x500.png` | 1544 × 500 | Kopfbanner, Retina |
| `banner-772x250.png` | 772 × 250 | Kopfbanner, Standardauflösung |
| `screenshot-1.png` | frei, siehe unten | Formular im Block-Editor |
| `screenshot-2.png` | frei | Multi-Step-Formular im Frontend |
| `screenshot-3.png` | frei | Bedingte Logik konfigurieren |
| `screenshot-4.png` | frei | Submissions-Dashboard |
| `screenshot-5.png` | frei | Spam-Schutz ohne reCAPTCHA |
| `screenshot-6.png` | frei | theme.json-Styling |

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
- **Sprache.** Die Screenshots zeigen die englische Oberfläche, weil die readme
  im Verzeichnis englisch ist. Deutsche Varianten sind als
  `screenshot-1-de_DE.png` möglich, sobald die deutsche readme in GlotPress
  steht.
- **Keine Preisangaben, keine Werbeflächen** im Banner. WordPress.org
  beanstandet werbliche Assets.

## Nach dem Ablegen

Die Bilder gehen mit dem nächsten `./deploy.sh <version>` mit. Ein eigener
Release ist dafür nicht nötig, Assets liegen in SVN neben den Tags und wirken
sofort. Kontrolle nach ein paar Minuten auf
<https://wordpress.org/plugins/flinkform/>.

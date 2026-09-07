# Deutsche readme-Fassung fuer translate.wordpress.org
#
# Diese Datei wird NICHT ausgeliefert und ist keine gueltige readme.txt.
# Sie ist die Vorlage zum Einfuegen bei GlotPress:
# https://translate.wordpress.org/projects/wp-plugins/flinkform/stable-readme/de/default/
#
# GlotPress uebersetzt Zeichenkette fuer Zeichenkette, deshalb steht hier
# jeder Abschnitt in derselben Reihenfolge und mit derselben Struktur wie in
# readme.txt. Aendert sich readme.txt, muss diese Datei mitgepflegt werden.
#
# Kurzbeschreibung: maximal 150 Zeichen (aktuell 136).

== Kurzbeschreibung ==

Kontaktformulare aus nativen Blöcken. Multi-Step, bedingte Logik, Spam-Schutz ohne reCAPTCHA. Kostenlos, DSGVO-konform, aus Deutschland.

== Description ==

Flinkform ist ein Formular-Builder, der vollständig im WordPress-Block-Editor lebt. Formulare bestehen aus nativen Blöcken (`block.json` v3), werden über die Design-Tokens aus `theme.json` gestaltet und laufen über die Interactivity API: keine separate Formular-Oberfläche, keine Shortcodes, kein jQuery, unter 15 KB Frontend-JavaScript (gzip).

Entwickelt in Deutschland für Websites, die Datenschutz ernst nehmen. Das deutschsprachige Kontaktformular-Plugin für den Block-Editor.

**Erst ausprobieren, dann installieren:** Live-Demo mit echten Formularen, inklusive Multi-Step und bedingter Logik, unter https://demo.flinkform.de/

= DSGVO by Design =

Keine IP-Adressen, keine User-Agent-Daten, kein Tracking, keine externen Dienste im kostenlosen Kern. Der Spam-Schutz besteht aus einem Honeypot, einer signierten Zeitprüfung und einer Proof-of-Work-Aufgabe mit Rechenaufgabe als Fallback ohne JavaScript: kein reCAPTCHA, kein hCaptcha, kein Cloudflare Turnstile und damit keine Datenübertragung an US-Server. Enthalten sind ein Consent-Feld, Aufbewahrungsfristen mit automatischer Löschung und die Anbindung an die Datenschutz-Werkzeuge von WordPress.

= Kostenlos, keine Testversion =

Multi-Step-Formulare und bedingte Logik stecken im kostenlosen Kern, nicht hinter einer Bezahlschranke:

* Multi-Step-Formulare mit Fortschrittsanzeige und Validierung pro Schritt
* Bedingte Logik für Felder, Schritte, das Überspringen von Schritten und das Freischalten des Absenden-Buttons
* 14 Feldtypen, darunter ein eigenes Consent-Feld
* Submissions-Übersicht direkt in WordPress mit Suche, Filtern und Gelesen-Status
* Benachrichtigungs-E-Mails mit Merge-Tags, dazu optional eine Bestätigungsmail
* Automatisches theme.json-Styling: Formulare passen ohne zusätzliches CSS zum Theme
* Auf Barrierefreiheit gebaut: Das ausgelieferte Markup besteht die axe-core-Prüfung gegen WCAG 2.1 A/AA ohne Verstöße

= Flinkform Pro =

Das optionale Pro-Add-on ergänzt Stripe-Zahlungen (Karte, SEPA-Lastschrift, Apple Pay, Google Pay, je nachdem was im Stripe-Konto aktiviert ist), Berechnungsfelder, Multi-Datei-Upload, SMTP-Versand, Webhooks, Newsletter-Anbindungen, CSV-Export und eigenes CSS. Details und Preise: https://flinkform.de/pro

= Voraussetzungen =

WordPress 6.5 oder neuer, PHP 8.1 oder neuer, Block-Editor (Gutenberg).

= So funktioniert es =

* **Nativ im Block-Editor** - Formulare entstehen mit `block.json` und der Interactivity API, direkt im Editor
* **theme.json-Styling** - Formulare übernehmen Typografie, Farben und Abstände des Themes automatisch
* **Moderner Unterbau** - WordPress 6.5+, PHP 8.1+, kein jQuery, Frontend-JavaScript unter 15 KB gzip
* **Multi-Step-Formulare** - lange Formulare mit dem Seitenumbruch-Block in Schritte teilen, im kostenlosen Kern enthalten
* **Bedingte Logik** - Felder abhängig von der Eingabe ein- und ausblenden, im kostenlosen Kern enthalten
* **Barrierefrei angelegt** - vollständige Tastaturbedienung, Screenreader-tauglich, Ansagen über aria-live
* **Datenschutz von Anfang an** - keine externen Dienste, keine Tracking-Cookies, keine IP-Speicherung, alles bleibt auf deinem Server

= Funktionen (kostenloser Kern) =

**Formulare bauen**
* 14 Feldtypen: Text, E-Mail, Textbereich, Zahl, Datum, URL, Telefon, Auswahl, Radio, Checkbox, Schalter, Verstecktes Feld, Consent, Adresse
* Zusammengesetztes Adressfeld: Straße, PLZ und Ort in einem kompakten Raster, optional mit Adresszusatz und Land
* Eigenes Consent-Feld für die Zustimmung zur Datenschutzerklärung
* Hinweis-Block: ein hervorgehobener Hinweis zwischen den Feldern (Info, Erfolg, Warnung, Wichtig), zusammen mit bedingter Logik erscheint er nur dann, wenn er zutrifft
* Abschnittsüberschrift und Seitenumbruch zum Gliedern längerer Formulare
* Multi-Step-Formulare mit Seitenumbruch-Block, Validierung pro Schritt und Fortschrittsanzeige (Balken, Punkte oder Nummern)
* Bedingte Logik: Felder ein- und ausblenden, Schritte überspringen, den Absenden-Button freischalten, mit verschachtelbaren Gruppen für "(A oder B) und C"
* Zweispaltiges Layout mit Vollbreiten-Option je Feld

**Styling**
* Automatische theme.json-Übernahme (Farben, Typografie, Abstände, Eckenradius)
* Style-Panel: Primärfarbe, Feldstil (umrandet/weich/unterstrichen/minimal), Label-Position (oben/daneben/schwebend/Platzhalter), Button-Stil (gefüllt/Umriss/Ghost), dazu Farbwähler für Labels und Hilfe-/Consent-Texte, und der Abschnittsüberschrift-Block bringt WordPress' eigene Textfarben-Option mit

**Barrierefreiheit**
* Von Grund auf barrierefrei gebaut: echte label/for-Paare, fieldset/legend für Auswahlgruppen, Fehler werden über role="alert" angesagt und per aria-describedby verknüpft, der Fokus springt ins erste fehlerhafte Feld
* Die Schritt-Navigation kündigt den neuen Schritt per aria-live an und steuert den Fokus, die Fortschrittsanzeige ist eine echte progressbar mit aktuellen Werten
* Sichtbare Fokus-Ringe auch dann, wenn das Theme sie entfernt, prefers-reduced-motion wird beachtet, und der Spam-Schutz kommt ohne CAPTCHA aus: nichts zu entziffern, nichts zu lösen
* Funktioniert vollständig ohne JavaScript, und das Formular-Markup besteht axe-core (WCAG 2.1 A/AA) ohne Verstöße, auch im Fehlerzustand

**Benachrichtigungen**
* Benachrichtigungsmail an das Team bei jedem Eingang (Empfänger frei wählbar, Merge-Tags)
* Optionale Bestätigungsmail an die absendende Person
* Absendername und -adresse pro Formular, mit Reply-To für beide Mails: Versand aus der eigenen Adresse ohne SMTP-Plugin
* Versand über den Standard-Mailversand von WordPress (`wp_mail`)

**Spam-Schutz**
* Immer aktiver Honeypot plus signierte Zeitprüfung (ohne Konfiguration)
* Eingebaute Proof-of-Work-Aufgabe mit barrierefreier Rechenaufgabe als Fallback für Besucher ohne JavaScript
* Kein externer Dienst, keine API-Schlüssel, keine Tracking-Cookies, DSGVO-freundlich

**Nach dem Absenden**
* Erfolgsmeldung oder Weiterleitung auf eine eigene Danke-Seite (mit Schutz vor Open Redirects)
* Optional Submission-ID und Formular-ID als Query-Parameter für Conversion-Tracking (GA4, Meta Pixel, Plausible und andere)

**Verwaltung**
* Submissions-Liste mit Suche, Filter nach Formular, Sortierung und Sammelaktionen
* Detailansicht einer Einsendung mit allen Feldnamen und Werten
* Als gelesen/ungelesen markieren
* Aufbewahrungsfrist pro Formular mit automatischer täglicher Löschung

== Installation ==

1. Den Ordner `flinkform` nach `/wp-content/plugins/` hochladen
2. Das Plugin unter **Plugins** in WordPress aktivieren
3. Eine Seite oder einen Beitrag im Block-Editor öffnen
4. Den Block **Formular** einfügen (nach "Flinkform" oder "Formular" suchen)
5. Felder hinzufügen, Einstellungen im Block-Inspektor setzen, veröffentlichen, fertig

== Frequently Asked Questions ==

= Kann ich Flinkform ausprobieren, bevor ich es installiere? =

Ja. Unter https://demo.flinkform.de/ läuft eine Live-Demo mit mehreren Formularen zum Ausfüllen und Absenden, darunter ein Multi-Step-Formular und bedingte Logik. Das ist eine echte WordPress-Seite mit diesem Plugin, eine Anmeldung ist nicht nötig.

= Funktioniert Flinkform ohne reCAPTCHA? =

Ja, und es gibt gar keine reCAPTCHA-Anbindung. Spam filtern ein Honeypot-Feld, eine signierte Zeitprüfung und eine Proof-of-Work-Aufgabe, die auf eine einfache Rechenaufgabe zurückfällt, wenn kein JavaScript verfügbar ist. Es wird kein Drittanbieter kontaktiert, also verlassen auch keine Besucherdaten deinen Server.

= Ist Flinkform DSGVO-konform? =

Flinkform speichert Einsendungen in deiner eigenen WordPress-Datenbank und kontaktiert im kostenlosen Kern keinen externen Dienst. Es speichert keine IP-Adressen und keine User-Agent-Daten, bringt ein Consent-Feld mit, unterstützt Aufbewahrungsfristen mit automatischer Löschung und hängt sich in die Datenschutz-Werkzeuge von WordPress für Auskunft und Löschung ein (Details im Abschnitt Datenschutz weiter unten). Ob dein Gesamtaufbau konform ist, hängt weiterhin von deiner Datenschutzerklärung und deinem Mail-Anbieter ab.

= Sind Multi-Step-Formulare wirklich kostenlos? =

Ja. Multi-Step-Formulare mit Fortschrittsanzeige, Validierung pro Schritt und dem bedingten Überspringen von Schritten gehören zum kostenlosen Plugin. Die meisten Mitbewerber verlangen dafür Geld.

= Brauche ich einen Page-Builder wie Elementor oder Divi? =

Nein. Formulare entstehen im normalen WordPress-Block-Editor aus nativen Blöcken. Kein Page-Builder, keine Shortcodes, keine separate Formular-Oberfläche.

= Kann ich von Contact Form 7 oder WPForms umziehen? =

Formulare müssen im Block-Editor neu gebaut werden, einen automatischen Import gibt es nicht. Ein typisches Kontaktformular ist in wenigen Minuten nachgebaut, weil die Felder ganz normale Blöcke sind.

= Funktioniert Flinkform auf Deutsch? =

Ja. Die Oberfläche ist auf Deutsch verfügbar, also Block-Editor, Admin-Bereich und die Texte im Formular. Die Entwicklung findet in Heilbronn statt, der Support läuft auf Deutsch und Englisch.

= Ist Flinkform kostenlos? =

Ja. Flinkform steht unter GPLv2 und ist komplett kostenlos, inklusive Multi-Step-Formularen und bedingter Logik. Alles, was für echte Formulare nötig ist, steckt im Kern. Das optionale Add-on Flinkform Pro ist ein eigenständiges, kostenpflichtiges Plugin.

= Welche WordPress-Version brauche ich? =

WordPress 6.5 oder höher und PHP 8.1 oder höher. Flinkform nutzt moderne WordPress-APIs (Interactivity API, block.json v3, viewScriptModule), die in älteren Versionen nicht zur Verfügung stehen.

= Funktioniert Flinkform mit meinem Theme? =

Ja. Flinkform liest die Design-Tokens deines Themes aus `theme.json` und übernimmt Farben, Typografie, Abstände und Eckenradius automatisch. Formulare wirken auf jedem modernen WordPress-Theme wie dazugehörig, getestet mit GeneratePress, Twenty Twenty-Five, Astra und Kadence.

= Wie baue ich ein Multi-Step-Formular? =

Füge zwischen den Feldern einen **Seitenumbruch**-Block ein, um das Formular in Schritte zu teilen, wähle den Stil der Fortschrittsanzeige (Balken, Punkte oder Nummern) und nutze die Validierung pro Schritt. Schritte lassen sich sogar abhängig von früheren Antworten überspringen.

= Wie funktioniert der Spam-Schutz? =

Flinkform arbeitet mehrschichtig und muss dafür nicht eingerichtet werden:

1. **Honeypot** - ein verstecktes Feld, das Bots ausfüllen und Menschen nie sehen
2. **Signierte Zeitprüfung** - Einsendungen, die schon wenige Sekunden nach dem Laden der Seite eintreffen, werden abgewiesen; der Zeitstempel ist kryptografisch signiert und damit nicht fälschbar
3. **Proof-of-Work-Aufgabe** - der Browser löst im Hintergrund eine kleine Rechenaufgabe; wer kein JavaScript hat, bekommt stattdessen eine einfache Rechenfrage

Es wird kein externer Dienst kontaktiert. Es werden keine Tracking-Cookies gesetzt. Es werden keine personenbezogenen Daten weitergegeben.

= Ist Flinkform barrierefrei? =

Barrierefreiheit ist eingebaut, nicht nachträglich angeflanscht: echte label/for-Paare, fieldset/legend für Gruppen, Fehler werden über role="alert" angesagt und mit ihrem Feld verknüpft, Fokus-Steuerung und aria-live-Ansagen über alle Schritte hinweg, sichtbare Fokus-Ringe, Unterstützung für prefers-reduced-motion und ein Spam-Schutz ohne CAPTCHA. Das ausgelieferte Formular-Markup besteht die automatisierte axe-core-Prüfung gegen WCAG 2.1 A/AA ohne Verstöße, auch im Fehlerzustand. Ein formales Audit mit Screenreader-Protokoll wurde bisher nicht beauftragt. Wenn du eines durchführst, freuen wir uns über die Ergebnisse. Beachte, dass die Farben, die du im Editor wählst (und die Palette deines Themes), den Kontrast beeinflussen und in deiner Verantwortung bleiben.

= Meine Benachrichtigungsmails kommen nicht an. Was kann ich tun? =

Die Zustellbarkeit von E-Mails hängt vom Hoster ab. Viele Hoster versenden `wp_mail()` unzuverlässig. Wenn deine Benachrichtigungen nicht ankommen, installiere ein SMTP-Plugin, das den Versand über einen richtigen Anbieter leitet. Es übernimmt dann auch die Zustellung für Flinkform.

= Kann ich nach dem Absenden auf eine Danke-Seite weiterleiten? =

Ja. Im Block-Inspektor unter "Nach dem Absenden" wählst du "Auf URL weiterleiten" und trägst die Adresse deiner Danke-Seite ein (gegen Open Redirects geprüft). Auf Wunsch werden Submission-ID und Formular-ID als Query-Parameter angehängt, etwa für Conversion-Tracking.

== Screenshots ==

1. Ein Formular aus nativen Blöcken im WordPress-Block-Editor bauen
2. Multi-Step-Formular mit Fortschrittsanzeige im Frontend
3. Bedingte Logik: einblenden, ausblenden und Schritte überspringen
4. Submissions-Übersicht mit Suche, Filtern und Gelesen-Status
5. Spam-Schutz ohne reCAPTCHA
6. Formulare übernehmen das theme.json-Styling automatisch

== Privacy ==

Flinkform ist auf Datenschutz ausgelegt. Das macht der kostenlose Kern, und das macht er nicht:

**Was der kostenlose Kern speichert:**
* Formular-Einsendungen (die Werte, die Besucher eintragen) in einer eigenen Datenbanktabelle (`{prefix}flinkform_submissions`)

**Was der kostenlose Kern nicht tut:**
* Er speichert keine IP-Adressen und keine User-Agent-Daten des Browsers
* Er setzt keine Tracking-, Analyse- oder Marketing-Cookies. Flinkform setzt genau ein technisch notwendiges Cookie, `flinkform_flash` (Laufzeit etwa 60 Sekunden, httpOnly), und das nur dann, wenn eine Einsendung an der Validierung scheitert, um Fehlermeldung und Eingaben über den Seitenwechsel zu retten. Erfolgreiche Einsendungen setzen gar kein Cookie
* Er kontaktiert keinen externen Dienst

**Aufbewahrung:**
* Standardmäßig bleiben Einsendungen gespeichert, bis du sie löschst. Für den Grundsatz der Speicherbegrenzung (DSGVO Art. 5) setzt du pro Formular eine Aufbewahrungsfrist (Formular-Block, Datenaufbewahrung), und Flinkform löscht ältere Einsendungen täglich automatisch
* Einzelne Einsendungen lassen sich jederzeit in der Übersicht im Admin löschen

**Löschung:**
* Alle Daten des kostenlosen Kerns (die Submissions-Tabelle) werden endgültig entfernt, wenn das Plugin über den WordPress-Admin deinstalliert wird
* Flinkform ist an die Datenschutz-Werkzeuge von WordPress angebunden (Werkzeuge > Persönliche Daten exportieren / löschen) und unterstützt damit Auskunfts- und Löschanfragen betroffener Personen

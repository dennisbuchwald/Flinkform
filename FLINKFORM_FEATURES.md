# Flinkform - Feature-Liste (Free + Pro)

> Referenz für Landingpage, Vergleichstabelle und Marketing. Stand: 2026-06-14,
> aus dem Code verifiziert. Nicht im Plugin-Zip (dist-excluded).
>
> Legende: ⭐ = geht über ein normales Basic-Kontaktformular hinaus (Free) ·
> 🔒 = Pro

## Basis (hat jedes simple Kontaktformular)
- Text-, E-Mail-, Textarea-Feld
- Pflichtfeld-Validierung
- E-Mail-Benachrichtigung an Admin
- Erfolgsmeldung nach Absenden

## Formular bauen
- ⭐ 13 Feldtypen: Text, E-Mail, Textarea, Zahl, Datum, URL, Telefon, Dropdown,
  Radio, Checkbox-Gruppe, Toggle, Hidden, Consent (DSGVO)
- ⭐ Section-Heading + Zwei-Spalten-Layout (pro Feld auf volle Breite)
- ⭐ Multi-Step-Formulare (Page-Break) mit Fortschrittsanzeige (Balken/Punkte/
  Zahlen) und Schritt-Validierung
- ⭐ Conditional Logic (Felder/Schritte ein-/ausblenden, Submit-Button gaten,
  Schritte überspringen)
- ⭐ Block-nativ im Gutenberg-Editor (jedes Feld ein Block, keine separate UI)

## Styling
- ⭐ Automatische theme.json-Übernahme (Farben, Typo, Abstände, Radius)
- ⭐ Style-Panel: Primärfarbe, Feld-Stil (bordered/soft/underline/minimal),
  Label-Position (oben/daneben/floating/Platzhalter), Button-Stil (fill/outline/ghost)
- ⭐ Animierte Success-Card + Submit-Ladezustand (reduced-motion-sicher)

## Spam-Schutz (Privacy-USP)
- ⭐ Honeypot + signierter Zeit-Check + Proof-of-Work-Challenge, alles ohne externe
  Dienste (kein reCAPTCHA, kein Cloudflare)
- ⭐ Mathe-Fallback für Besucher ohne JavaScript

## Benachrichtigungen
- ⭐ Optionale Bestätigungsmail an den Absender
- ⭐ Merge-Tags (Feldwerte in Betreff/Text)
- 🔒 Datei als echter Mail-Anhang an die Admin-Mail (mit File-Upload-Feld)

## Nach dem Absenden
- ⭐ Weiterleitung auf Danke-Seite (Open-Redirect-Schutz)
- ⭐ Submission-ID + Form-ID als Query-Parameter für Conversion-Tracking
  (GA4, Meta Pixel, Plausible)

## Verwaltung (Admin)
- ⭐ Submissions-Liste mit Suche, Filter, Sortierung, Bulk-Aktionen
- ⭐ Detailansicht, Gelesen/Ungelesen-Status
- 🔒 CSV-Export der Einsendungen

## Datenschutz / DSGVO
- ⭐ Kein IP-/User-Agent-Logging, keine externen Dienste, kein Tracking
- ⭐ Privacy-Tools-Integration (WordPress Export/Löschung personenbezogener Daten)
- ⭐ Automatische Aufbewahrungs-Löschung pro Formular (Retention)
- ⭐ Dediziertes Consent-Feld mit Datenschutz-Link

## Barrierefreiheit & Technik
- ⭐ WCAG 2.1 AA (Tastatur, Screenreader, aria-live, Fokus-Management)
- ⭐ Interactivity API, kein jQuery, Frontend-JS unter 15 KB

## Pro-Integrationen
- 🔒 File-Upload-Feld (Dropzone, Typ-/Größen-Limit, sichere Speicherung,
  Lösch-Kaskade)
- 🔒 Webhooks (Zapier/Make/n8n) mit Retry, Delivery-Log, Bedingungen
- 🔒 SMTP-Versand (7 Provider-Presets, verschlüsselte Zugangsdaten) + Sende-Log
- 🔒 Newsletter-Integrationen: Brevo, Mailchimp, CleverReach (Pflicht-Consent-Gate)
- 🔒 Custom CSS pro Formular

## Einordnung
Praktisch alles ⭐ ist im Free (Multi-Step, Conditional Logic, 13 Feldtypen,
kompletter Privacy-Stack) - bewusst großzügig für Adoption + Differenzierung.
🔒 Pro = die Agentur-Kaufanreize: File-Upload, Webhooks, SMTP+Log, CSV, Newsletter.
Geplant (noch nicht drin): externe CAPTCHA-Provider, Stripe-Payments, SMTP-OAuth.

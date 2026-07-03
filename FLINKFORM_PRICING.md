# Flinkform - Pricing & Geschäftsmodell

> Internes Planungsdokument (DACH-Markt). Quelle der Wahrheit für das Freemius-Setup
> und die Umsatzplanung. Nicht im Plugin-Zip (dist-excluded via FLINKFORM_*.md).
> Stand: 2026-06-13.

---

## 1. Modell

- **Free** auf WordPress.org (Funnel, Dauer-Reichweite), Freemius-frei.
- **Pro** als separates Add-on, verkauft über **Freemius** (Merchant of Record:
  übernimmt EU-Umsatzsteuer, Lizenzvalidierung, Update-Auslieferung, Checkout).
- Freemius-SDK kommt NUR ins Pro-Plugin, nie ins Free (wp.org-Regeln + Free in Review).
- Alle Pro-Tiers enthalten ALLE Features, gestaffelt nur nach Site-Anzahl.
  Einfacher zu kommunizieren, kein Killer-Feature im teuren Tier eingesperrt.

## 2. Preis-Tiers (final, aktualisiert 2026-07-02)

| Plan | Preis/Jahr | Sites | Rolle im Lineup |
|---|---|---|---|
| **Single** | 59 € | 1 | Einstieg, Preisanker unten |
| **Studio** | 99 € | 3 | **Decoy**: existiert primär, um Agency unwiderstehlich zu machen |
| **Agency** | 149 € | bis 25 | Kern-Zielgruppe, der Umsatzbringer ("Bestseller"-Badge) |
| **Unlimited** | 299 € | unbegrenzt | Anker oben, macht 149 € "vernünftig" |
| **Lifetime (nur Launch)** | 399 € einmalig | bis 25 | Erste 3 Monate, danach abschalten |

**Begründung (inkl. Pricing-Psychologie, 2026-07):**
- Nicht unter 59 € (SureForms-Niveau). Wir sind das Premium-/Privacy-Produkt,
  49 € würde "weniger wert" signalisieren, dabei ist das DSGVO-Argument mehr wert.
- **Studio (99 €/3 Sites) ist der McDonald's-Mitteltier**: +40 € über Single wirkt
  fair, aber "+50 € für 22 weitere Sites" macht Agency zum No-Brainer. Gravity
  und WPForms nutzen dieselbe 1/3/x-Leiter. Erwartete Eigen-Conversion gering -
  das ist okay, er verkauft Agency.
- Agency (149 €) ist der Hebel: **unter 6 € pro Website** ist DAS Reframing für
  Agenturen mit Kundenseiten. Prominent auf der Karte ausweisen.
- Unter WPForms Elite (599 $), etwa auf Gravity-Elite-Niveau (259 $) = "etwas
  günstiger als die Großen", ohne sich unter Wert zu verkaufen.
- Lifetime nur als Launch-Aktion: bringt früh Cash fürs Marketing, killt aber bei
  Dauerangebot die wiederkehrenden Einnahmen. Nach 3 Monaten abschalten.
- **14-Tage-Geld-zurück-Garantie** statt Streichpreisen: Fake-Anker ("normally
  599 $" wie WPForms) sind in DE als Mondpreise abmahnfähig - Risk Reversal
  leistet dasselbe (Kaufhürde senken) und passt zur Seriös-Positionierung.
- Alle Pläne enthalten weiterhin ALLE Features (nur Site-Staffelung) - einfach
  zu kommunizieren, kein Killer-Feature im teuren Tier eingesperrt.

**Konkurrenz zum Abgleich:** SureForms 59 $ flat, Gravity 59/159/259 $,
WPForms 99/199/399/599 $, Contact Form 7 gratis (kein Pro).

## 3. Freemius-Dashboard-Mapping

- Ein Produkt "Flinkform Pro", vier kostenpflichtige Pläne (Single/Studio/Agency/
  Unlimited), Abrechnung jährlich. Site-Limits: 1 / 3 / 25 / unbegrenzt.
- 14-Tage-Geld-zurück-Garantie im Freemius-Checkout aktivieren (Refund-Policy).
- Trial: 7-14 Tage ohne Kreditkarte erwägen (Trials konvertieren ~18%). Erst nach
  Launch testen, ob es die Conversion hebt oder nur Support-Last erzeugt.
- Lifetime-Plan: als befristetes Angebot anlegen, nach 3 Monaten deaktivieren.
- Auszahlung: Freemius zahlt aus (Bank/PayPal). Keine eigene Steuer-/VAT-Pflicht.

## 4. Conversion-Annahmen

- Free->Pro bei WP-Plugins: 1-2% typisch, 2-4% gut, ~5% Spitze. Bezogen auf
  AKTIVE INSTALLS (nicht Downloads).
- Agentur-Zielgruppe zahlt eher -> Planung mit **2%**.
- Ø-Erlös pro Lizenz ~95 €/Jahr (Mix: ~70% Single + 25% Agency + 5% Unlimited).

## 5. Install-Prognose (aktive Installs, nicht Downloads)

Die Install-Zahl ist die entscheidende Variable und haengt fast komplett am
Marketing, nicht am Produkt (Formello: gleiche Idee, ~70 Installs ohne Distribution).

| Szenario | Jahr 1 | Jahr 2 | Jahr 3 |
|---|---|---|---|
| Pessimistisch (kaum Marketing) | 500 | 1.500 | 3.000 |
| Realistisch (aktiver Push) | 2.000 | 6.000 | 12.000 |
| Optimistisch (Content greift) | 5.000 | 15.000 | 30.000 |

**Dennis' Hebel (warum realistisch erreichbar):** eigene dbw-media-Kundenprojekte
als Sofort-Installs + Referenzen, DACH-Content-Kompetenz (SEO/Blog), BVwG-Urteil
als Content-Aufhänger, Free-Plugin als Dauer-Reichweite.

## 6. Umsatzprojektion (nach ~7% Freemius-Gebühr)

Realistisches Szenario, 2% Conversion, Ø 95 €/Lizenz:

| | aktive Installs | zahlende Lizenzen | Netto-Umsatz/Jahr |
|---|---|---|---|
| Jahr 1 | 2.000 | ~40 | ~3.500 € |
| Jahr 2 | 6.000 | ~120 | ~10.600 € |
| Jahr 3 | 12.000 | ~240 | ~21.200 € |

Eckwerte: pessimistisch ~grob ein Drittel davon, optimistisch ~das 2-2,5-fache.

**Abo-Effekt:** Umsatz akkumuliert (neue Kunden + Renewals der Vorjahre,
Renewal-Rate ~50-70%), startet also nicht jährlich bei null. Deshalb wächst Jahr 3
überproportional.

## 7. Risiken & Hebel (ehrlich)

- **Hauptrisiko:** Installs bleiben niedrig (kein Marketing = Formello-Szenario).
  Gegenmittel: eigene Projekte + konsequenter DACH-Content ab Launch.
- **Conversion-Risiko:** Free ist so gut, dass kaum jemand Pro braucht. Gegenmittel:
  echte Kaufanreize in Pro (File-Upload + Newsletter sind da; Payments/Stripe
  mittelfristig als stärkster zusätzlicher Trigger).
- **Kern-Erkenntnis:** Der Hebel ist die Zahl der aktiven Installs, NICHT der Preis.
  Bei der Preisgestaltung (50-150 €) kann man wenig falsch machen.

## 8. Fazit

Realistisch ein "netter Nebenverdienst", der mit den Installs skaliert: Jahr 1
deckt die Kosten + etwas mehr, ab Jahr 2-3 ein relevantes vierstelliges bis kleines
fünfstelliges jährliches Zusatzeinkommen. Kein WPForms-Killer (war nie das Ziel),
aber eine profitable, verteidigbare DACH-Privacy-Nische.

# Flinkform — Landing Page & Content Strategy

> Ideas for the marketing site and content engine around flinkform.

---

## How to use this document

This is a **brainstorm and idea capture** document — not a finished spec. It collects the marketing surfaces that will drive adoption: the landing page itself, the comparison content, and the blog content engine. Each section can grow over time as we lock in copy, design, and structure.

The goal of the landing page is **one thing**: convince a frustrated WordPress user who has used Contact Form 7, WPForms, or Gravity Forms that Flinkform is the upgrade they've been waiting for — without making it feel like just another marketing site.

> **Language note:** the actual landing-page copy ships in **German first** (DACH
> target audience), English second. In the German copy: no em-dash "—" / no
> en-dash "–" as a thought-separator (KI-marker) — recast with comma/colon/period.

---

## 0. The headline angle — Privacy / DSGVO first

This is the spine of the whole site, not a feature among many. Everyone else is a
form builder that *also* mentions GDPR; Flinkform is **the privacy-first form
plugin** that happens to be a great block-native builder.

**The hook (DACH-specific, legally loaded):**
- 2024 the Austrian BVwG ruled that **Google reCAPTCHA without prior consent
  violates the GDPR** (it builds a "digital fingerprint" from IP, browser, OS).
  CNIL (FR) fined €125,000 for reCAPTCHA without consent.
- **Every** major form plugin defaults to a US CAPTCHA service (reCAPTCHA,
  hCaptcha, Cloudflare Turnstile). Flinkform is the one that does **not** — its
  proof-of-work spam protection runs entirely in the browser + on your server,
  no third-party request, no consent banner needed for it.
- Message: *"Dein Kontaktformular schickt gerade Besucherdaten in die USA — ohne
  dass du es weißt. Flinkform nicht."*

**Three privacy pillars to hammer (hero + dedicated section):**
1. **Keine externen Dienste.** No IP/UA logging, no tracking, no telemetry, no
   US CAPTCHA. Everything stays on your server.
2. **DSGVO-Werkzeuge eingebaut.** Consent field, per-form retention auto-purge,
   WordPress exporter/eraser integration, honest cookie disclosure.
3. **Made in Germany / EU-first.** Spricht die Zielgruppe (Agenturen, die für
   ihre Kunden haften) direkt an. Vertrauen schlägt Feature-Liste.

**Why this wins:** the target buyer (DACH agencies + freelancers) is *liable* for
their clients' GDPR compliance. They don't buy "more fields" — they buy peace of
mind. The BVwG ruling turns a technical detail into a sales argument.

---

## 0b. The "we do it better" table (fill-in, defensible)

The honest, filled-in comparison. Tap-through chart in section 1 uses this data.
Keep it citable — link every competitor claim to their pricing/docs.

| | **Flinkform** | Contact Form 7 | WPForms (Lite/Pro) | Gravity Forms | SureForms |
|---|---|---|---|---|---|
| **Spam without US service** | ✅ Proof-of-work, on-server | ⚠️ needs reCAPTCHA/Akismet | ⚠️ reCAPTCHA/Turnstile | ⚠️ reCAPTCHA/Turnstile | ⚠️ reCAPTCHA/hCaptcha |
| **No IP/UA stored by default** | ✅ | ⚠️ Akismet sends data | ❌ stores entries+meta | ❌ | ⚠️ |
| **Block-editor native** | ✅ every field a block | ❌ shortcode | ❌ separate builder | ❌ separate builder | ✅ |
| **Multi-step free** | ✅ | ❌ | ❌ Pro | ❌ paid | ✅ |
| **Conditional logic free** | ✅ | ❌ (addon) | ❌ Pro $99+ | ❌ paid | ✅ |
| **Looks good out of the box** | ✅ theme.json | ❌ unstyled | ⚠️ own styles | ⚠️ own styles | ✅ |
| **Frontend JS** | ✅ <15 KB, no jQuery | ⚠️ jQuery | ⚠️ heavier | ⚠️ heavier | ⚠️ React-based |
| **Price (single site)** | **59 €** | free | $99/yr | $59/yr | $59/yr |
| **Made in / data location** | 🇩🇪 EU-first | JP, self-host | US | US | IN/US |

Honesty guardrails: SureForms is genuinely good and block-native too — our edge
over them is **privacy default + theme.json inheritance + <15 KB**, NOT "they're
bad". Against CF7/WPForms/Gravity the edge is broader (native editor, free
features, privacy). Never strawman; the team and savvy buyers will check.

---

## 1. Core Idea — The Comparison Chart

The centerpiece of the landing page is an **interactive comparison chart** that puts Flinkform side-by-side with every major WordPress form plugin. The user can **tap / swipe** through competitors and see a clear positive/negative gegenüberstellung for each one.

### Why this works

- Most visitors arriving on the landing page are already using _something else_. They are not asking "should I use a form plugin?" — they are asking "is this better than what I have?"
- A direct visual comparison answers that question faster than any sales copy.
- It is also pure SEO gold: every competitor name on the page is a keyword we want to rank for.

### Interaction pattern

- **Desktop:** tab interface. Each competitor is a tab. Clicking switches the comparison view.
- **Mobile:** swipeable card stack (or horizontally scrollable tabs). Each card shows one competitor vs. Flinkform.
- **Default tab:** Contact Form 7 (most-used, most likely visitor context).
- Animation: smooth horizontal slide between competitors — feels tactile, not laggy.

### Competitors to include (priority order)

1. **Contact Form 7** — the default everyone starts with
2. **WPForms** — biggest commercial player
3. **Gravity Forms** — the "professional" choice
4. **Ninja Forms** — historic alternative
5. **Fluent Forms** — modern competitor
6. **Forminator** — WPMU DEV's free option
7. **Formidable Forms** — power-user/dev focused
8. **Elementor Forms** — only available if you bought Elementor Pro
9. **Bricks Builder Forms** — bundled with Bricks
10. _(optional)_ **Typeform / Tally / Jotform** — non-WordPress, to position against SaaS form builders

### Comparison criteria (rows in the chart)

Each row is a feature or quality dimension. Flinkform column gets a ✓ / clear win, competitor column shows the actual reality (not a strawman — be honest).

Suggested categories:

**Pricing & Licensing**

- Free for all features (no paywall on conditional logic / multi-step / webhooks)
- No per-site license limits
- Open source (GPL)

**Editor Experience**

- Native Block Editor integration (no separate UI)
- Works with Site Editor / FSE themes
- No iframe / no "open form builder in modal" workflow

**Out of the Box**

- Looks good with zero configuration
- Inherits theme typography, colors, spacing automatically
- Mobile-first by default
- Dark mode support

**Core Features**

- Multi-step forms (free)
- Conditional logic (free)
- Email notifications with per-form recipient (free)
- 13 field types; redirect to thank-you page (GA4 / Meta Pixel conversion tracking)
- Spam protection: honeypot + signed time-check + proof-of-work (all free, no service)
- Pro: webhooks, SMTP delivery + send log, CSV export, file upload, newsletter integrations

**Privacy & GDPR** (the headline argument — see section 0)

- Zero external service calls in the free core, by default
- No IP / user-agent storage, no tracking, no telemetry
- Proof-of-work spam protection instead of reCAPTCHA/Turnstile — NO third-party
  CAPTCHA service is contacted. This is THE differentiator; never pitch Turnstile,
  it would contradict the whole positioning.
- One strictly-necessary cookie (`flinkform_flash`), set only on failed submit
- WordPress privacy-tools integration (exporter + eraser), per-form retention

**Performance**

- JS bundle size on frontend (target: <15kb gzipped)
- No jQuery dependency
- Assets only loaded on pages that contain a form

**Developer Experience**

- REST API
- WP-CLI commands
- Hooks & filters
- Block Bindings support
- Clean PHP 8.1+ code

**Accessibility**

- WCAG 2.1 AA compliant by default
- Screen reader friendly
- Keyboard-navigable multi-step

> **Note:** The comparison must be defensible. Every claim about a competitor should link to the source (their pricing page, their docs, a review). If we're going to call WPForms expensive, we cite their pricing page. If we say Gravity Forms requires a license, we link to it. No FUD.

---

## 2. Other Landing Page Sections (rough outline)

Sketch only — to be expanded once we lock in messaging.

1. **Hero**

   - Headline: a sharp, single-sentence positioning. Working draft: _"The form plugin WordPress should have shipped natively."_ — or play with: _"Forms that don't suck. For WordPress. Free, forever."_
   - Sub-headline: one paragraph capturing the philosophy (5-minute setup, beautiful by default, every feature free).
   - Primary CTA: "Install from WordPress.org" (or "Download" pre-launch).
   - Secondary CTA: "See how it compares" (anchor scroll to the comparison chart).
   - Visual: looping video / animation of building a form in the Block Editor in under 60 seconds.

2. **The Problem** (short)

   - 3-4 bullet pain points the target audience nods at: paywalled features, ugly default styles, slow bloated bundles, fighting the tool.

3. **The Comparison Chart** (Section 1 above) — _the load-bearing section._

4. **Feature Showcase**

   - Walk through the 5–7 hero features with short videos or animated screenshots.
   - Each feature gets a one-sentence "why it matters" — not "what it does".

5. **Built for the Block Editor**

   - A dedicated section showing screenshots of the editor experience. Differentiator vs. CF7 / WPForms / Gravity (all of which have separate form builder UIs).

6. **Privacy First**

   - Short callout explaining the GDPR posture: no external calls by default, no telemetry, Turnstile as default CAPTCHA. This will matter a lot to the EU/DACH audience.

7. **Built in Public**

   - Link to GitHub, roadmap (PERFORM_ROADMAP.md), changelog, community feedback backlog.
   - Show that the project is alive and the developer is responsive.

8. **FAQ**

   - "Is it really free?" — Yes.
   - "Do you sell add-ons?" — Not yet. If we ever do, the core stays free.
   - "How does this compare to [plugin]?" — Anchor link back to the comparison chart.
   - "What about migration from CF7 / WPForms?" — TODO: importer plan.
   - "Is it production-ready?" — Honest answer based on roadmap phase.

9. **Final CTA**
   - Install / Download / Star on GitHub.

---

## 3. Blog Content Strategy — "Alternative to …" Series

The blog is the SEO engine. People search for "alternative to Contact Form 7" or "WPForms alternative" all day long — those are warm, high-intent searches. Each comparison post is also a natural landing-page-to-blog handoff: the comparison chart on the landing page can deep-link into the relevant post for a richer breakdown.

### Article series — first wave

Each post follows the same template (see template below) so the series feels cohesive and is easy to produce.

1. **Flinkform vs. Contact Form 7 — A Modern Alternative for 2026**
2. **Flinkform vs. WPForms — All the Features, None of the Paywall**
3. **Flinkform vs. Gravity Forms — Free, Open Source, and Block Editor Native**
4. **Flinkform vs. Ninja Forms — Why the Editor Experience Matters**
5. **Flinkform vs. Fluent Forms — Side-by-Side Comparison**
6. **Flinkform vs. Forminator — Which Free Form Plugin Wins in 2026?**
7. **Flinkform vs. Formidable Forms — A Developer's Perspective**
8. **Flinkform vs. Elementor Forms — You Don't Need Elementor Pro for Great Forms**
9. **Flinkform vs. Bricks Forms — Block Editor vs. Builder**
10. **Best Free WordPress Form Plugins for 2026 (Honest Roundup)** — round-up post that links to all of the above

### Optional second wave (specific intent)

- **How to migrate from Contact Form 7 to Flinkform** (assumes importer exists)
- **How to add conditional logic to a WordPress form for free**
- **How to track form conversions in GA4 / Meta Pixel** (positions the redirect feature)
- **How to set up GDPR-compliant forms in WordPress**
- **How to build a multi-step form in WordPress with no code**

### Post template

Every "vs." post follows the same structure to keep production fast and the reader experience consistent:

1. **TL;DR table** — 5-row condensed version of the landing page comparison chart, scoped to this one competitor.
2. **Who [competitor] is for** — fair, generous summary. No strawmen.
3. **Where [competitor] falls short in 2026** — honest, sourced criticism.
4. **What Flinkform does differently** — feature-by-feature with screenshots.
5. **Pricing comparison** — clear table.
6. **When you should _not_ switch to Flinkform** — credibility move. If the answer is "you're already deeply invested in Gravity Forms add-ons", say so.
7. **How to migrate** — step-by-step if an importer exists, manual workflow if not.
8. **Final verdict** — one paragraph, no hedging.

### SEO notes

- Target the exact-match query in the H1 (`<competitor> alternative`, `<competitor> vs Flinkform`).
- Every post should answer "is it free?", "is it open source?", "does it support [feature]?" in the first 200 words — these are People-Also-Ask candidates.
- Internal link from every post → the landing page comparison chart anchor.
- Internal link from the landing page comparison chart → the relevant post.
- Schema: `Article` + `SoftwareApplication` for Flinkform and each competitor.

---

## 3b. Pricing & licensing (decided 2026-06-13)

Sold via **Freemius** (Merchant of Record — handles EU-VAT, license validation,
update delivery). SDK goes into the **Pro plugin only**; the free core on
wp.org stays Freemius-free. Free→Pro funnel = the wp.org listing.

All Pro tiers get **all features** — tiers differ only by **site count** (simpler
to communicate, no killer feature locked behind the top tier).

| Plan | Price/yr | Sites | Target |
|---|---|---|---|
| Single | **59 €** | 1 | Freelancer, single site |
| Agency | **149 €** | up to 25 | **Core target — the revenue driver** |
| Unlimited | **299 €** | ∞ | Large agencies, power users |
| Lifetime (launch only) | **399 € once** | up to 25 | First 3 months only, then retire — early cash for marketing |

Rationale: stay at/above SureForms' 59 € (we're the premium privacy product, not
the cheap one — 49 € would signal "lesser"). Agency tier undercuts WPForms Elite
($599) and roughly matches Gravity Elite ($259) → "a bit cheaper than the big
players" without underselling. Renewal/abo model: revenue accumulates across
years (new customers + renewals, WP renewal rate ~50-70%).

Realistic revenue (after ~7% Freemius fee, ~90 €/customer blended):
- Conservative (yr 1, 1.5k installs × 1.5%): ~1.8k €/yr
- Realistic (yr 2, 5k installs × 2%): ~8.4k €/yr
- Optimistic (yr 2-3, 15k installs × 2.5%): ~31k €/yr

**The lever is active installs, not price.** Pricing is within market norms;
the work is getting installs via the free plugin + DACH privacy content (BVwG
ruling as the content spearhead).

---

## 4. Open Questions / TODO

Things to decide before any of this can ship:

- [ ] **Domain & hosting** — where does the landing page live? (flinkform.com? perform.wordpress-style? subdomain?)
- [ ] **Stack** — is the landing page itself built on WordPress (dogfooding, including Flinkform for the contact form)? Or a static site (Astro / Eleventy)? Dogfooding is the more compelling story.
- [ ] **Design direction** — modern, calm, opinionated. Mood-board needed. Reference points: Linear, Plausible, Fathom, Cal.com landing pages.
- [ ] **Comparison data sourcing** — who maintains the comparison rows over time? (Competitors change pricing, ship features. This needs an owner.)
- [ ] **Migration story for each competitor** — which ones get an actual importer in v1.0? (At minimum: Contact Form 7, because of install base.)
- [ ] **Brand voice** — direct, dry, slightly opinionated. No marketing fluff. Closer to a developer tool than a SaaS landing page.
- [ ] **Newsletter / launch list** — pre-launch capture form (built with Flinkform, naturally).

---

_Document version: 0.1 — initial brainstorm, 2026-05-25_
_Owner: Dennis Buchwald_

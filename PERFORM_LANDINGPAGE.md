# PerForm — Landing Page & Content Strategy

> Ideas for the marketing site and content engine around perform-forms.

---

## How to use this document

This is a **brainstorm and idea capture** document — not a finished spec. It collects the marketing surfaces that will drive adoption: the landing page itself, the comparison content, and the blog content engine. Each section can grow over time as we lock in copy, design, and structure.

The goal of the landing page is **one thing**: convince a frustrated WordPress user who has used Contact Form 7, WPForms, or Gravity Forms that PerForm is the upgrade they've been waiting for — without making it feel like just another marketing site.

---

## 1. Core Idea — The Comparison Chart

The centerpiece of the landing page is an **interactive comparison chart** that puts PerForm side-by-side with every major WordPress form plugin. The user can **tap / swipe** through competitors and see a clear positive/negative gegenüberstellung for each one.

### Why this works

- Most visitors arriving on the landing page are already using _something else_. They are not asking "should I use a form plugin?" — they are asking "is this better than what I have?"
- A direct visual comparison answers that question faster than any sales copy.
- It is also pure SEO gold: every competitor name on the page is a keyword we want to rank for.

### Interaction pattern

- **Desktop:** tab interface. Each competitor is a tab. Clicking switches the comparison view.
- **Mobile:** swipeable card stack (or horizontally scrollable tabs). Each card shows one competitor vs. PerForm.
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

Each row is a feature or quality dimension. PerForm column gets a ✓ / clear win, competitor column shows the actual reality (not a strawman — be honest).

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
- Webhooks (free)
- Email notifications with per-form recipient
- Redirect to thank-you page (for GA4 / Meta Pixel conversion tracking)
- Spam protection (honeypot + time-check + Cloudflare Turnstile)

**Privacy & GDPR**

- Zero external service calls by default
- No tracking, no telemetry
- Cloudflare Turnstile as default CAPTCHA (most GDPR-friendly)
- IP / user agent not stored unless explicitly enabled

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

1. **PerForm vs. Contact Form 7 — A Modern Alternative for 2026**
2. **PerForm vs. WPForms — All the Features, None of the Paywall**
3. **PerForm vs. Gravity Forms — Free, Open Source, and Block Editor Native**
4. **PerForm vs. Ninja Forms — Why the Editor Experience Matters**
5. **PerForm vs. Fluent Forms — Side-by-Side Comparison**
6. **PerForm vs. Forminator — Which Free Form Plugin Wins in 2026?**
7. **PerForm vs. Formidable Forms — A Developer's Perspective**
8. **PerForm vs. Elementor Forms — You Don't Need Elementor Pro for Great Forms**
9. **PerForm vs. Bricks Forms — Block Editor vs. Builder**
10. **Best Free WordPress Form Plugins for 2026 (Honest Roundup)** — round-up post that links to all of the above

### Optional second wave (specific intent)

- **How to migrate from Contact Form 7 to PerForm** (assumes importer exists)
- **How to add conditional logic to a WordPress form for free**
- **How to track form conversions in GA4 / Meta Pixel** (positions the redirect feature)
- **How to set up GDPR-compliant forms in WordPress**
- **How to build a multi-step form in WordPress with no code**

### Post template

Every "vs." post follows the same structure to keep production fast and the reader experience consistent:

1. **TL;DR table** — 5-row condensed version of the landing page comparison chart, scoped to this one competitor.
2. **Who [competitor] is for** — fair, generous summary. No strawmen.
3. **Where [competitor] falls short in 2026** — honest, sourced criticism.
4. **What PerForm does differently** — feature-by-feature with screenshots.
5. **Pricing comparison** — clear table.
6. **When you should _not_ switch to PerForm** — credibility move. If the answer is "you're already deeply invested in Gravity Forms add-ons", say so.
7. **How to migrate** — step-by-step if an importer exists, manual workflow if not.
8. **Final verdict** — one paragraph, no hedging.

### SEO notes

- Target the exact-match query in the H1 (`<competitor> alternative`, `<competitor> vs PerForm`).
- Every post should answer "is it free?", "is it open source?", "does it support [feature]?" in the first 200 words — these are People-Also-Ask candidates.
- Internal link from every post → the landing page comparison chart anchor.
- Internal link from the landing page comparison chart → the relevant post.
- Schema: `Article` + `SoftwareApplication` for PerForm and each competitor.

---

## 4. Open Questions / TODO

Things to decide before any of this can ship:

- [ ] **Domain & hosting** — where does the landing page live? (perform-forms.com? perform.wordpress-style? subdomain?)
- [ ] **Stack** — is the landing page itself built on WordPress (dogfooding, including PerForm for the contact form)? Or a static site (Astro / Eleventy)? Dogfooding is the more compelling story.
- [ ] **Design direction** — modern, calm, opinionated. Mood-board needed. Reference points: Linear, Plausible, Fathom, Cal.com landing pages.
- [ ] **Comparison data sourcing** — who maintains the comparison rows over time? (Competitors change pricing, ship features. This needs an owner.)
- [ ] **Migration story for each competitor** — which ones get an actual importer in v1.0? (At minimum: Contact Form 7, because of install base.)
- [ ] **Brand voice** — direct, dry, slightly opinionated. No marketing fluff. Closer to a developer tool than a SaaS landing page.
- [ ] **Newsletter / launch list** — pre-launch capture form (built with PerForm, naturally).

---

_Document version: 0.1 — initial brainstorm, 2026-05-25_
_Owner: Dennis Buchwald_

# Block Roadmap Ideas — Install-Funnel Focus

Status: **Draft / brainstorming**
Date: 2026-04-27
Branch: `claude/improve-plugin-blocks-VXKBh`

## Why this doc exists

A formal audit (see `docs/audits/`) graded the plugin **A-** on architecture,
security, accessibility, FSE compatibility, and testing. The remaining
opportunity is *positioning*: what would push DesignSetGo from "polished
design library" to "first plugin a site builder reaches for"?

This doc is a brainstorm of new blocks, variations, and Form Builder upgrades
ranked by their likely impact on **plugin installs and retention**, not by
implementation cost. Every item listed should be re-spec'd in its own plan
before code is written.

## Competitive baseline

Plugins we measure against (free + premium tiers):

- **GenerateBlocks / GenerateBlocks Pro** — container-first, performance-led
- **Kadence Blocks** — broad block catalog, conversion-oriented
- **Stackable** — design-led, animation-heavy
- **Spectra (UAGB)** — large block count, marketing
- **GreenShift** — performance + animation + dynamic content
- **GhostKit** — interactive blocks (countdown, counters, etc.)
- **Essential Blocks** — feature-broad freemium

Where we already lead: **Dynamic Query (v2.5)**, **Visibility rules**,
**Style Bindings**, **scroll-driven family**, **shared block primitives**.

Where we trail: conversion blocks (pricing, testimonial, before/after),
Form Builder depth, starter templates / first-run experience.

---

## Tier 1 — Ship in the next minor release

These five items together change the marketing pitch from "design library"
to "design + lead-gen suite." Competitors charge $59–$199/yr for this combo.

### 1. Pricing Table block

- **Why**: Highest-traffic search term in the block-plugin category.
- **Shape**: Compound block — `pricing-table` parent + `pricing-tier` children.
  Tiers carry: title, price, period, CTA, feature list, "featured" flag.
- **Differentiators we already have**: Block Bindings (price from ACF/meta),
  Visibility (hide tiers based on auth/role), Style Bindings (color from data).
- **Differentiator to add**: Built-in **monthly / annual toggle** with
  Interactivity API store — no JS plugin needed.
- **Variations**: 3-column classic, comparison-grid, single-tier hero.

### 2. Testimonial / Review block

- **Why**: Social proof block competitors all ship; we ship a generic Card.
- **Shape**: New block (semantically distinct from Card) with star-rating
  attribute, author name/role, optional avatar, optional source logo.
- **Differentiator**: Emit **Schema.org Review** + **AggregateRating**
  microdata automatically — measurable SEO win, not just UI.
- **Variations**: Single quote, carousel (consume `slider` as inner blocks),
  grid (consume `grid`).

### 3. Before/After image comparison

- **Why**: Visual differentiator. Stackable / GreenShift charge for it.
- **Shape**: Custom block, two `<img>` slots + draggable handle, vertical or
  horizontal split, keyboard accessible.
- **Implementation note**: Pure CSS clip-path + Interactivity API drag store.
  No external libs. Respect `prefers-reduced-motion` for the auto-reveal
  variation.

### 4. Form Builder — File upload field

- **Why**: #1 missing field type. "Does it support file uploads?" is the
  most-asked question in form-plugin reviews.
- **Shape**: New `form-file-field` block + server-side handler in
  `class-form-handler.php`. Multi-file optional, MIME allowlist, size cap,
  scoped `wp_handle_upload` to a private uploads subdir.
- **Security must-haves**: nonce, capability, MIME sniff (not just
  extension), file count cap, total-size cap, configurable retention.
  Files stored outside `uploads/` web root if possible, served via
  authenticated REST route.
- **Touches**: `form-builder/edit.js`, REST submission handler, GDPR export
  (already wired via `class-gdpr-compliance.php`).

### 5. Form Builder — Submissions dashboard

- **Why**: Without an entries table, every form site needs a second plugin
  for storage. The submissions CPT (`dsgo_form_submission`) already exists;
  surfacing it as a dashboard removes that friction.
- **Shape**: Custom admin screen under DesignSetGo → Submissions. Filter by
  form, date, status. Bulk delete, CSV export, one-click "view full
  submission" panel. Reuse existing CPT instead of inventing a table.
- **Stretch**: Per-submission notes, mark-as-spam / mark-as-resolved,
  webhook resend.

---

## Tier 2 — Conversion-block follow-ups

### 6. Team Member

Variation of `card` (1–3 attribute delta + same `save()` output) — per
`.claude/claude.md` rules this should ship as a `registerBlockVariation`,
not a new block. Adds: avatar shape, social-link row, role/title slot.

### 7. Sticky CTA / Announcement bar

Site-wide top/bottom bar, dismissible (cookie), Visibility rules pick up the
auth/role logic for free. Likely a new block + a small
`enqueue_scripts` sticky positioner. Coexists with existing
`sticky-header-controls` extension.

### 8. Lottie player

Embed a `.lottie` / `.json` animation. Use `@lottiefiles/lottie-player`
loaded only when the block is present (lazy-load via
`maybe_enqueue_frontend_on_render` pattern already in
`class-assets.php`). Hover/scroll triggers reuse our existing
`block-animations` extension primitives.

### 9. Reading-time + word-count Block Binding source

No new block — register two new sources via the public helper
`designsetgo_register_bindings_source()` (added in v2.4). These slot into
`advanced-heading`, `card`, etc. with zero per-block changes. High
perceived-value-to-effort ratio.

### 10. FAQ schema toggle on Accordion

Add a single boolean `emitFaqSchema` attribute to `accordion`. When true,
emit JSON-LD `FAQPage` markup server-side. Pure-PHP additive change, no
deprecation needed (default off). SEO win for marketing pages.

---

## Tier 3 — Polish on existing blocks

| Block | Improvement | Notes |
|---|---|---|
| Slider | Thumbnail nav variation, lightbox mode, video slides | Slider already has aspectRatio + transitions; add nav-thumbnails as a new attribute, not a new block. Lightbox = new view variation. |
| Modal | Exit-intent / scroll / time-delay triggers + "show once" cookie | Triggers belong on `modal-trigger`; "show once" needs a cookie helper in `frontend.js`. |
| Map | Multiple markers + info windows | Currently single-marker. Markers should be inner blocks (`map-marker`) to inherit attributes pattern. |
| Advanced Heading | Animated text reveals (typewriter, word-fade) | Already has `heading-segment` children; reuse `block-animations` extension. |
| Comparison Table | Featured-column highlight + sticky header row | Two attributes, no new block. |
| Counter | Block Bindings source for live post/comment counts | Register a `designsetgo/counts` source; counter reads via existing binding plumbing. |
| Timeline | Filter by year | Inner-block sibling: `timeline-filter`. Mirrors the Query block filter pattern. |
| Icon library | Categorized SVG sets, brand icons | Currently free-form SVG. Bundle a curated set + searchable picker. |

---

## Tier 4 — Distribution / first-run experience

These are not blocks, but they move installs more than any single block.

### A. Starter pattern + template library expansion

We ship 16 patterns. Plugins that win the install funnel (Kadence,
Spectra) ship 100+ and surface them in a dedicated picker on activation.

- Group existing patterns by intent (hero, pricing, FAQ, CTA, footer).
- Add an "Insert Starter Pattern" button to the block inserter, scoped to
  the `designsetgo` category icon already in the toolbar.
- Optional: one-click full-page template import using
  `wp_insert_post()` + `register_block_pattern()`.

### B. Onboarding wizard on activation

Three steps: pick a pattern style (minimal / bold / editorial), opt into
form notification email, optional Cloudflare Turnstile site key. Writes
into the existing `dsgo_settings` option — no schema change.

### C. Block-level preview thumbnails in the inserter

`block.json` already supports `example`. Audit which blocks have an
`example` and which don't; fill the gaps. Thumbnails materially improve
discovery — a 1-day audit pass.

---

## Explicit non-goals (for now)

- **Header / mega-menu builder.** Site-wide nav is a theme concern; FSE
  Navigation block + our Visibility rules already cover most cases.
- **AI content generator.** Crowded space, undifferentiated, ongoing API
  cost. Revisit only if it ties into Block Bindings (e.g. AI-assisted alt
  text via the existing helpers).
- **Page builder mode.** We win by being a clean Gutenberg-native plugin.
  A "no-Gutenberg" mode would invalidate every architectural choice in
  `.claude/claude.md`.

---

## Open questions for the team

1. Pricing Table vs Testimonial — which lands first? Pricing is the bigger
   search term, Testimonial is the easier ship (variation of card).
2. Form file upload — store inside `uploads/` (simple, web-readable) or in
   a private path served via authenticated REST (secure, more code)?
   Recommend the second.
3. Submissions dashboard — fork our own admin screen, or rely on the
   existing `dsgo_form_submission` CPT edit screen with custom columns?
   The CPT route is half-built already in `class-form-submissions.php`.
4. Do we want a dedicated `pricing-table` block, or a Pricing variation on
   the existing `card`/`grid` combo? Variations are cheaper but lose
   per-tier semantic markup (no schema.org Product).

---

## Suggested first sprint

**Week 1**: Pricing Table (block + 3 variations) + FAQ schema toggle on accordion.
**Week 2**: Testimonial block + Schema.org Review markup.
**Week 3**: Form file-upload field + submissions dashboard MVP.
**Week 4**: Before/After block + readme.txt + marketing-page refresh
positioning the plugin as "design + lead-gen."

This sequence ships one Tier 1 item every week and lands the headline
features before the longer-tail polish work in Tier 3.

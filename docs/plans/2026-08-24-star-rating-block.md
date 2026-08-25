# Star Rating & Reviews — Implementation Notes

**Date:** 2026-08-24
**Status:** Shipped — `designsetgo/star-rating`, plus the Reviews pattern that follows from it.
**Parent:** [Elementor Gap Roadmap](2026-08-19-elementor-gap-roadmap.md) — this is Plan 4.

## Scope

Plan 4 asked for two things: a Star Rating block that is bindable from the first commit,
and Reviews — "Star Rating plus attribution inside a `query` loop, so most of it is a
pattern once the rating block exists." It also named the compounding value: the shipped
schema extension, so a rating emits its value into the page's JSON-LD graph.

All three landed. What follows is why each took the shape it did.

## D1 — The block is dynamic, and that is the whole point

`rating` and `ratingCount` are registered as bindable in
`includes/bindings/class-block-bindings-support.php`. That registry's own rule decides the
rest: an attribute may only be bound if it is renderable from `$block->attributes` at
render time — an HTML-sourced attribute, or a `render_callback`. A star rating has no text
to source from HTML, so it must be server-rendered, and `save()` returns `null`.

A static save would freeze whatever number the author last typed, which is exactly the
"hard-coded rating is the least interesting version of it" case the plan warned about.
Sources that work today with no further plumbing:

- `designsetgo/woo-average-rating` (Plan 8) — a product's average.
- `designsetgo/post-meta` and `designsetgo/acf` — any numeric field.
- The same inside a `designsetgo/query` loop, because `render-posts.php` supplies `postId`
  per item and Block Bindings resolve against it.

## D2 — Partial stars are a CSS clip, not markup

Two identical icon rows sit on top of each other; the upper one is clipped to
`--dsgo-star-rating-fill`. One markup shape serves 4, 4.5 and 4.3 — a 4.3 of 5 clips at
86%, which lands mid-icon — and it needs no half-star asset, no second icon set, and no
frontend JavaScript at all.

The clip is a separate element from the row it clips (`__fill-clip` wrapping `__fill`).
Putting a percentage width on the flex row itself would shrink its items rather than hide
them, which draws five narrow stars instead of four and a bit.

`precision` (whole / half / exact) snaps only the drawn icons. The printed number, and the
number handed to structured data, stay exact — see D5.

## D3 — Values, and only values, live in one place

`src/blocks/star-rating/utils/rating.js` and `includes/features/star-rating-functions.php`
are the same six functions twice, and three consumers depend on them agreeing: the editor
preview, `render.php`, and the JSON-LD builder. A drift between the first two shows up as
an author setting a half star in the canvas and publishing a whole one.

The PHP half lives in `includes/` rather than beside the block because the schema builder
runs on `wp_head`, before any block renders — a helper defined inside the block's
`render.php` would not exist yet.

Both halves clamp hard, because a bound source can return anything: the scale is capped at
ten icons (a bad meta value would otherwise emit thousands of SVGs), the rating is held
inside `0..max`, and non-numeric input reads as no rating rather than `NaN`. Numeric
*strings* are accepted deliberately — WooCommerce returns `'4.00'`, and post meta returns
whatever was saved.

The count template (`(%s)` by default) is applied with `str_replace()`, never `sprintf()`.
It is author input: a template reading "%s of 100%" would make `sprintf()` throw a
`ValueError` on PHP 8 and take the page down with it.

## D4 — The wrapper is a positioning box, so the CSS variables go on the inner element

Same pattern as Icon, Icon Button and Pill: `.dsgo-star-rating` is a block-level wrapper
that core's constrained layout caps at the content column, and the visible
`.dsgo-star-rating__inner` shrink-wraps inside it. Colour, border and padding are routed
inward by `designsetgo_route_visual_supports()` — left on the wrapper, a background would
paint across the whole column instead of hugging the stars.

That helper rewrites the wrapper's `style` attribute wholesale, so the block's custom
properties (`--dsgo-star-rating-fill` and friends) go on the inner element too. `edit.js`
does the same, for the same reason: it has to match.

Accessibility is one sentence, not five fragments. Every visual part is `aria-hidden`, and
a visually-hidden `__sr-text` span carries "Rated 4.5 out of 5, based on 128 ratings".
Reading "4.5" then "(128)" as disconnected strings is what a naive markup order produces.

## D5 — Structured data: two types, both refusing to emit a useless node

`src/extensions/schema/` gains a second block. The type control offers:

- **Aggregate rating** — many people's ratings of one thing. Emits nothing without a
  rating count, because `ratingCount` (or `reviewCount`) is required for the markup to be
  eligible at all; a node without one is dead weight that still asserts a rating.
- **Review** — one person's rating of one thing. Emits nothing without a named author, for
  the same reason: an anonymous review is ignored, and an anonymous rating claim is worth
  less than no claim.

Both attach to `itemReviewed` as a `Thing` named by the block's own `schemaItemName`, or
by the page title. Deliberately not `Product`: the block cannot know what the page is
about, and relabelling a blog post as a Product to chase a rich result is the
structured-data spam this extension's opt-in default exists to prevent. A site that really
does sell something should let WooCommerce (or its SEO plugin) own the Product node.

`worstRating` is 0, not schema.org's default of 1, because this block's scale genuinely
starts at zero stars.

The rating value in the node is the exact one, never the snapped one. Rounding 4.4 up to
4.5 for the icons is a display convention; publishing 4.5 to a search engine when the
source says 4.4 is a false statement.

### The one case that emits nothing, on purpose

**A bound rating produces no schema node.** `SchemaOutput` reads the *stored post content*,
and `parse_blocks()` does not resolve Block Bindings — so a rating driven by post meta, ACF
or WooCommerce is not knowable there. What *is* in the block comment is the placeholder the
author last typed, and emitting that would publish a number nobody meant.

Both remaining cases are the right outcome:

- A review or testimonial card with a rating typed into the block — the common case —
  emits its node.
- A product page whose rating is bound to WooCommerce emits nothing, which is correct:
  Woo already outputs a Product node carrying its own `aggregateRating`, and a second one
  would be duplicate structured data.

Resolving bindings at `wp_head` to close this gap was considered and rejected: it means
running binding sources outside any block render, on every singular request, to produce a
node that the one plugin most likely to supply the value already emits correctly.

## D6 — Reviews is a pattern

`patterns/testimonials/reviews-star-rating-cards.php`. The older
`testimonials-rating-cards` pattern draws its stars as five separate Icon blocks — whole
stars only, and silence for a screen reader. The new one uses a single Star Rating block
per card.

The plan's "inside a `query` loop" case needs no pattern of its own: drop the block into a
Query template and connect `rating` to a meta field through the editor's Connections
panel. That path is bindings plus the existing loop, and shipping hand-written Query
markup in a pattern file would add a fragile copy of it.

## Not built

- **No frontend JavaScript.** No animation on scroll, no interactive (clickable) rating
  input. An input widget is a form control and belongs to the form block family, not to a
  display block.
- **No `align: wide|full`.** Justification positions the stars; see Icon's render.php for
  what the `align` values cost a block whose root is a positioning wrapper.

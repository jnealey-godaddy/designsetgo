# Block Authorability & Markup-Slimming Audit

**Date:** 2026-07-02
**Question driving the audit:** Our blocks add a lot to both the `block.json` ("meta JSON") and the saved `<div>` markup. Where can we slim this down so LLMs (and any programmatic authoring) can build pages from scratch without hitting the "unexpected/invalid content" wall — the way a core block that serializes to a single line does?

---

## TL;DR

The `block.json` attribute surface is **not** the real barrier — attributes are just JSON inside the block comment, which LLMs handle fine. The barrier is the **static HTML each `save()` emits**, which WordPress validates byte-for-byte. The more custom markup (nested divs, inline styles, data-attributes) a block serializes, the harder it is to author by hand and the more deprecation debt it accrues.

Two independent audit lenses were run:

1. **Supports duplication** (does a custom attribute duplicate a native Block Support?) → small win: **~165 lines across 3 blocks**. Most custom controls are legitimately sub-element/state colors and are correctly implemented.
2. **Markup/serialization complexity** (how hard is the saved HTML to reproduce?) → **the real opportunity**, quantified below.

**Headline finding:** Server-side (dynamic) rendering is *already an established house pattern* here — **13 of 65 blocks are dynamic**, including the entire Query family, Slider, and Scroll-slides. Extending that pattern to the **25 static leaf blocks** collapses their saved form to a single self-closing comment (`<!-- wp:x {...} /-->`), which is trivially LLM-authorable and carries **zero markup-deprecation debt**.

**The deprecation tax, quantified:** static blocks currently carry **9,849 lines of `deprecated.js` across 25 blocks** — shims that exist *only* because saved markup must reproduce old output. Dynamic blocks don't need markup deprecations at all.

---

## The core mechanic

An LLM authoring a block writes the serialized comment **and** the static HTML:

```html
<!-- wp:designsetgo/icon {"icon":"star","iconSize":64} -->
<div class="wp-block-designsetgo-icon dsgo-icon" style="display:flex;align-items:center;justify-content:center">
	<div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:64px;height:64px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="star" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Star"></div>
</div>
<!-- /wp:designsetgo/icon -->
```

WordPress compares that HTML against `save()`. Any drift in class order, inline-style order, data-attributes, div nesting, or the computed `aria-label` fallback → **"This block contains unexpected or invalid content."** No LLM reproduces that reliably.

A **dynamic** block's saved form is instead just:

```html
<!-- wp:designsetgo/icon {"icon":"star","iconSize":64} /-->
```

No HTML to match → always valid → perfectly authorable. This is the "single line" core blocks achieve.

---

## Lens 1 — Attribute/Supports duplication (small, safe wins)

Full detail in the supports audit. Actionable items only:

| Priority | Block | Action | Difficulty | Est. lines |
|---|---|---|---|---|
| HIGH | `heading-segment` | Remove duplicate toolbar typography controls; native typography `supports` already provides them | Easy | ~105 |
| MEDIUM | `icon-list-item` | Replace `contentGap` + `SpacingPanel` with `supports.spacing.blockGap` | Medium | ~35 |
| LOW | `progress-bar` | Drop custom `borderRadius` control; use `__experimentalBorder.radius` + `border-radius: inherit` | Medium | ~25 |

Everything else flagged (accordion/tabs/card/modal/slider state & sub-element colors) is **correct** — those colors target hover/open/overlay/marker/arrow sub-elements that native `color.*` can't reach. No action.

---

## Lens 2 — Markup complexity (the real lever)

Metrics per block: `attrs` = custom attributes; `save_ln` = lines in save.js; `inline` = inline `style={}` sites; `data` = `data-*` attributes emitted; `view` = also needs runtime JS hydration; `dep_ln` = lines of deprecation shims.

### Prime dynamic-render candidates — leaf blocks (no InnerBlocks)

Sorted by markup noise. These are the highest-ROI conversions: saved form → one line, delete deprecation shims, and (where `view=1`) often delete the hydration JS too.

| block | attrs | save_ln | inline | data | view | dep_ln | notes |
|---|---|---|---|---|---|---|---|
| countdown-timer | 16 | 143 | 4 | 9 | ✓ | 587 | high noise + hydration + big dep debt |
| counter | 15 | 214 | 0 | 11 | – | 0 | 11 data-attrs baked into markup |
| map | 12 | 122 | 0 | 9 | ✓ | 245 | |
| icon-button | 13 | 152 | 1 | 3 | – | **1295** | largest deprecation debt in the library |
| icon | 10 | 136 | 2 | 3 | – | 370 | **pilot** — SVG isn't even in saved markup (lazy-injected) |
| modal-trigger | 8 | 115 | 2 | 3 | – | 312 | lazy-icon |
| counter-group | 12 | 64 | 0 | 6 | ✓ | 0 | |
| table-of-contents | 15 | 86 | 0 | 5 | ✓ | 43 | |
| progress-bar | 13 | 123 | 2 | 2 | ✓ | 0 | also in Lens 1 |
| scroll-marquee | 7 | 63 | 1 | 2 | ✓ | 442 | |
| divider | 4 | 63 | 4 | 1 | – | 121 | |
| pill | 3 | 64 | 1 | 0 | – | 166 | |
| comparison-table | 9 | 233 | 0 | 2 | ✓ | 0 | large template, good server-render fit |

**8 leaf blocks are "worst of both worlds"** — complex static markup *and* runtime JS hydration (`view=1`): countdown-timer, map, counter-group, table-of-contents, progress-bar, scroll-marquee, form-phone-field, comparison-table. Going dynamic removes both problems at once.

### The 11 form-field blocks (special cluster)

`form-*-field` blocks each emit ~1 data-attr + fairly stable input markup, but collectively carry heavy deprecation debt (form-phone 648, form-select 298, form-textarea 284, form-text 187, form-email 142 = ~1,559 lines). Candidates for **dynamic render + consolidation**, but higher-risk (submission/validation) — defer past the leaf-visual pilots.

### Container blocks — slim via CSS, do NOT convert to dynamic

These wrap InnerBlocks; keep `save()` but move static inline styles into scoped CSS and drop hardcoded data-attributes where a class works.

| block | attrs | save_ln | inline | data | note |
|---|---|---|---|---|---|
| modal | 36 | 157 | 2 | **24** | 24 data-attrs is the biggest single-block markup surface |
| form-builder | 36 | 219 | 4 | 8 | |
| card | 28 | 236 | 3 | 0 | move inline → CSS |
| tabs | 19 | 86 | 0 | 4 | |
| blobs | 10 | 70 | 2 | 1 | |
| timeline / flip-card / image-accordion | — | — | 0 | 3 | low priority |

---

## Already-dynamic blocks (precedent — this is not a new direction)

`breadcrumbs`, `dynamic-image`, `product-categories-grid`, `product-showcase-hero`, `slider`, `scroll-slide`, `scroll-slides`, and the Query family (`query`, `query-results`, `query-filter`, `query-pagination`, `query-no-results`, `query-group-header`). All use `render.php` + `"render"` key in `block.json`. `product-showcase-hero` already has **no `save.js` at all**.

---

## Recommended sequencing

1. **Pilot: `icon` → dynamic.** Leaf block, already half-dynamic (SVG is lazy-injected, so the saved markup is a placeholder that provides no authoring value today). Converting moves `svg-icons` + wrapper into PHP, deletes the lazy-injector for this block, adds one final deprecation for existing content. Establishes the "dynamic-first for visual leaf blocks" template.
2. **Fast-follow (highest dep-debt / hydration pain):** `icon-button` (1,295 dep lines), `countdown-timer`, `modal-trigger`, `map`, `counter` + `counter-group`, `table-of-contents`, `progress-bar`, `scroll-marquee`.
3. **Quick supports wins in parallel:** `heading-segment`, `icon-list-item`, `progress-bar` border-radius (Lens 1).
4. **Container CSS cleanup:** move inline `style={}` → scoped CSS in `card`, `form-builder`, `modal`, `blobs`.
5. **Later / higher-risk:** form-field cluster (dynamic + consolidation).

## Tradeoffs to weigh before committing

- **Server cost:** dynamic blocks render on every request (no cached markup in `post_content`). Cheap for leaf visuals; use block-level caching if any prove hot.
- **Editor parity:** `edit.js` still renders client-side, so PHP template and React preview must be kept visually in sync (the 13 existing dynamic blocks already live with this).
- **One last deprecation per converted block** to silently migrate existing static content.
- **Net dependency reduction:** removing lazy-icon hydration and shrinking `deprecated.js` offsets the new PHP.

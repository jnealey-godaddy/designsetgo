# Star Rating Block - User Guide

**Block name**: `designsetgo/star-rating`
**Version**: 1.0.0
**Category**: DesignSetGo
**Keywords**: rating, stars, review, score, testimonial

## Overview

The **Star Rating Block** shows a rating as a row of icons — from a fixed value typed in the editor, or bound to post meta, ACF, or a WooCommerce average rating via Block Bindings. It is always server-rendered, because a bound rating can only be resolved at render time; a static block would freeze whatever number the author last saw in the editor.

**Key Features:**
- Any icon from the plugin's icon library, filled or outlined, sized and spaced independently
- Three display precisions: exact, half-star, or whole-star rounding for the drawn icons — the printed number always stays exact
- Optional numeric value (with "/5" scale), and an optional rating count with a customizable template (e.g. `(128)`)
- `rating` and `ratingCount` are bindable via the WordPress 6.9 Block Bindings API
- Optional JSON-LD structured data (Review or AggregateRating) via a "Schema type" control
- Left/Center/Right justification within the content column

## Quick Start

1. Insert the **Star Rating** block.
2. Set the **Rating** and **Out of** (scale) sliders under Settings.
3. Pick an icon and size under Style.
4. Optionally connect **Rating** to a dynamic source (post meta, ACF, or a WooCommerce average) via the block's Connections/Bindings UI.

## Settings & Configuration

### Settings

- **Rating**: 0 to the current scale, step 0.1 (`rating`, default `4.5`). Disabled with a note when the attribute is bound to a dynamic source — the slider then shows only a preview value; the frontend uses the bound source.
- **Scale** ("Out of"): 1–10 (`maxRating`, default `5`).
- **Precision**: Whole / Half / Exact (`precision`, default `half`) — controls how far the *drawn* icons round. The printed value and any structured data are never rounded by this setting.
- **Show value**: prints the numeric rating (`showValue`, default `false`); a nested **Show scale** toggle appends "/5" (`showMax`, default `false`).
- **Rating count**: **Show rating count** (`showCount`, default `false`) reveals **Number of ratings** (`ratingCount`, default `0`, 0–1000) and **Count format** (`countTemplate`, default `(%s)`, where `%s` is replaced by the count).

### Style

- **Icon**: any icon from the shared icon picker (`icon`, default `star`).
- **Icon style**: Filled / Outlined (`iconStyle`, default `filled`).
- **Icon size**: 12–96px (`iconSize`, default `24`).
- **Icon gap**: 0–24px (`iconGap`, default `4`).

### Color

A **Stars** color dropdown in the sidebar's Color panel exposes **Rating** and **Track** colors (`ratingColor`, `trackColor`) — the filled portion and the unfilled background of each icon, respectively. Both accept theme presets or custom colors; unset values fall back to the current text color.

### Advanced — Structured Data

A "Structured Data" panel (added by the shared schema extension, not specific to this block) offers a **Schema type** select: None (default), Aggregate rating, or Review (`dsgoSchema`). Choosing either reveals two fields specific to this block:

- **Item being rated** (`schemaItemName`) — defaults to the page title when left blank.
- **Review author** (`schemaAuthor`, Review type only) — required; a Review with no author is dropped entirely.

See [Related Docs](#related-docs) below for how this output is assembled.

### Block Supports

Anchor; spacing (margin/padding, both off by default, padding not serialized to the wrapper); color (background/text, text on by default); typography (font size on by default, line height, font family, font weight); border (color/style/width, radius off by default). Visual supports are routed to `.dsgo-star-rating__inner`, not the block's positioning wrapper — see `selectors.root` in block.json.

## Block Bindings

`rating` and `ratingCount` are registered as bindable attributes (`includes/bindings/class-block-bindings-support.php`), so either can be connected to any Block Bindings source — the plugin's own `designsetgo/post-meta`, `designsetgo/acf`, `designsetgo/woo-average-rating`, or a third-party source — through the editor's Connections panel. See [BLOCK-BINDINGS.md](../api/BLOCK-BINDINGS.md) for the full bindings reference and available sources.

A bound `rating` disables the Rating slider in the inspector (it becomes preview-only) but does not disable anything else — icon, size, colors, and the value/count display all still apply on top of the bound number.

## Structured Data Output

When **Schema type** is set, the block's own `render.php` output is unaffected — the JSON-LD is assembled separately, at `wp_head`, by reading the post's *stored* block markup:

- **Aggregate rating** emits an `AggregateRating` node, but only when `ratingCount` is greater than zero.
- **Review** emits a `Review` node, but only when `schemaAuthor` is filled in.
- **A bound `rating` or `ratingCount` disqualifies the block from emitting anything.** Structured data is built from the block comment's stored attributes, and Block Bindings never resolve there — only at render time. Publishing whatever number the author last typed as a live claim would be misleading (and, for a bound WooCommerce average, duplicate: WooCommerce already emits its own `Product`/`AggregateRating` node).
- `worstRating` is always `0` (not schema.org's default of `1`), matching this block's 0-based scale.

## Accessibility

- The icon row is `aria-hidden="true"`; a visually-hidden `<span>` carries the full sentence a screen reader announces instead, e.g. "Rated 4.5 out of 5, based on 128 ratings."
- The fractional fill is pure CSS (two identical icon rows, the top one clipped by width) — no half-star image asset, and no extra markup per precision level.
- Rating and Track colors both default to sufficient contrast against text color; check contrast manually when overriding both.

## Related Docs

- [BLOCK-BINDINGS.md](../api/BLOCK-BINDINGS.md) — Block Bindings sources and the `scope` argument for nested loops.
- [ICON.md](ICON.md) — the icon library this block draws from.

## Developer Notes

- Fully dynamic: `save.js` returns `null`; all markup comes from `render.php`.
- Rating math (clamping, precision snapping, fill percentage, formatting) lives in `includes/features/star-rating-functions.php` and is shared by `render.php`, the editor preview, and the JSON-LD builder — the file's own comment explains why: those three consumers must never disagree on the same number.
- JSON-LD builders live in `includes/features/schema-builders-rating.php`; the `dsgoSchema` attribute itself is registered by `src/extensions/schema/` (allowlisted for `designsetgo/accordion` and `designsetgo/star-rating` only).
- Covered by `tests/phpunit/star-rating-test.php` (math + schema builders, driven from parsed block markup rather than hand-built attribute arrays) and `tests/unit/blocks/star-rating.test.js`.
- See [2026-08-24-star-rating-block.md](../plans/2026-08-24-star-rating-block.md) for the original implementation plan.

# Section Divider Block — Design

**Date:** 2026-07-06
**Status:** Approved design, ready for implementation
**Block:** `designsetgo/section-divider`

## Purpose

A standalone, placement-flexible divider block that can be dropped between any two
blocks in the content flow — not pinned to a section's top/bottom edge like the
existing `designsetgo/section` shape-divider attributes. It reuses the exact same
shape library and theme-inheritance mechanism, but renders a **solid-filled** shape
instead of the section's see-through knockout.

Both features coexist: authors can keep using section edge dividers *or* drop a
standalone divider anywhere.

## Design decisions (locked)

- **Intent:** placement flexibility (drop anywhere, reuse the big shape library).
- **Fill model:** solid fill with a theme-inherited default; user can override.
- **Fill default fallback:** new theme token → `--wp--preset--color--base`.
- **Width/alignment:** core `align` support (`wide`/`full`), default `full`.
- **Theme-driven defaults for shape AND height:** both are inherit-by-default so
  `save()` serializes nothing unless the user explicitly overrides. Only the
  overridden value is written into content.

## Architecture

The section shapes are painted purely in CSS via `mask-image` off `:root`-scoped
`--dsgo-shape--{slug}` vars, with `is-shape-inherit` resolving the theme token
`--wp--custom--designsetgo--shape-divider--type`. This mechanism is block-agnostic,
so theme inheritance comes along for free in the new block.

**Reused (no geometry duplication):**
- The 23 mask data-URIs in `_shape-masks.scss` (live at `:root`, global scope).
- The `SHAPE_DIVIDERS` slug list / `getShapeDividerOptions()` for the inspector
  shape picker.

**New & different from the section version:**
- Own block folder `src/blocks/section-divider/`.
- **Single-layer** mask fill (`background: var(--fill); mask: var(--dsgo-shape-mask)`)
  — simpler than the section's two-layer XOR knockout on `::before`.
- No dual top/bottom doubling, no inner-padding "clear" hack, no video-background
  mutual-exclusivity notice (all section-specific).

## Attributes (`block.json`)

| Attribute | Type | Default | Notes |
|---|---|---|---|
| `shape` | string | `"inherit"` | `"inherit"` or a shape slug. No off/`""` state — delete the block to remove it. |
| `height` | `["number","null"]` | `null` | `null` → inherit theme token. Explicit number → emit `--dsgo-shape-height`. Clamp 10–500. |
| `width` | number | `100` | horizontal stretch %, clamp 100–300. Per-instance, not tokenized. |
| `flipX` | boolean | `false` | mirror horizontally. Per-instance. |
| `flipY` | boolean | `false` | flip vertically. Per-instance. |
| `fillColor` | string | `""` | empty → theme token fallback; set → explicit `--dsgo-section-divider-fill`. |

`supports.align: ["wide","full"]`, `block.json` `"align": "full"`.

Color control uses `ColorGradientSettingsDropdown` (via `useBlockColors`) writing the
CSS custom prop `--dsgo-section-divider-fill` — NOT `supports.color.background`,
because the color paints the masked shape, not the wrapper background.

## Theme tokens (all `settings.custom.designsetgo.shapeDivider.*`)

All resolved purely in CSS:

- `.type` — **shared with the section block** (`--wp--custom--designsetgo--shape-divider--type`,
  fallback `wave`). A kit setting the section default shape sets this too.
- `.height` — **new** (`--wp--custom--designsetgo--shape-divider--height`, fallback `100px`).
- `.color` — **new** (`--wp--custom--designsetgo--shape-divider--color`, fallback
  `--wp--preset--color--base`).

The plugin does not ship theme.json; kits (native-ui repo) set these. Fallbacks make
the block work with no kit.

## Markup (save)

Fully-default divider serializes essentially bare:

```html
<div class="wp-block-designsetgo-section-divider alignfull">
  <div class="dsgo-section-divider__shape dsgo-shape-divider is-shape-inherit"></div>
</div>
```

With overrides, only the changed vars appear:

```html
<div class="wp-block-designsetgo-section-divider alignfull">
  <div class="dsgo-section-divider__shape dsgo-shape-divider is-shape-wave"
       style="--dsgo-section-divider-fill:…; --dsgo-shape-height:120px; --dsgo-shape-flip-x:-1;">
  </div>
</div>
```

- Outer = `useBlockProps.save()` (align + block-support classes).
- Inner `__shape` = masked, filled element. Emit a custom prop ONLY when it differs
  from the CSS default (same trick as `ShapeDivider.js`).
- `is-shape-inherit` when `shape === "inherit"`, else `is-shape-{slug}`.

## Frontend CSS (`_section-divider.scss`)

```scss
:where(.dsgo-section-divider__shape) {
  height: var(--dsgo-shape-height,
    var(--wp--custom--designsetgo--shape-divider--height, 100px));
  width: var(--dsgo-shape-width, 100%);
  background: var(--dsgo-section-divider-fill,
    var(--wp--custom--designsetgo--shape-divider--color, var(--wp--preset--color--base)));
  mask: var(--dsgo-shape-mask) no-repeat center / 100% 100%;
  transform: scaleX(var(--dsgo-shape-flip-x, 1)) scaleY(var(--dsgo-shape-flip-y, 1));
}
```

Plus `@use` the shared `_shape-masks.scss` and the `is-shape-inherit` resolver.

## Editor UX

- `edit.js` renders the same classed inner div so CSS paints it live (editor.scss
  imports the masks) — no on-canvas inline-SVG preview needed.
- Inspector follows the Theme-3 IA: a **Settings** `DsgoInspectorPanel` (shape picker
  with per-shape SVG preview swatches reusing `SHAPE_DIVIDERS`, height, width,
  flip X/Y) and the fill in `<InspectorControls group="color">`.

## Shared-CSS extraction (shared-primitives-first)

- Move `_shape-masks.scss` + the canonical slug list → `src/styles/shared/_shape-masks.scss`.
- Update the section block's `_shape-divider.scss` to `@use` the new shared location
  (behavior-neutral — verify with build + section render).
- New block's `style.scss`/`editor.scss` `@use` the same shared partial.

## File structure

```
src/blocks/section-divider/
  block.json
  index.js
  edit.js
  save.js
  utils/            # thin re-export of shape options from ../section/utils
  style.scss
  editor.scss
  test/save.test.js
src/styles/shared/_shape-masks.scss   # promoted from section
```

## Testing

1. Unit: default divider → bare `is-shape-inherit` markup; explicit shape/height/color
   → correct inline vars; flip transforms.
2. Section regression: `test/save.test.js` + `test/deprecated.test.js` still pass
   (the CSS move is the only risk to the section block).
3. Manual: editor + frontend + responsive; full-bleed inside a constrained group;
   theme-token override via a test theme.json changes defaults without touching content.
4. Style import verification: `grep -i "section-divider" build/*.css` confirms
   `is-shape-inherit`, fill fallback chain, and mask vars in both frontend + editor CSS.

## No deprecations

Brand-new block — nothing to migrate.

## Out of scope (YAGNI)

Animation, multiple stacked shapes, gradient fill, `core/separator` transforms.

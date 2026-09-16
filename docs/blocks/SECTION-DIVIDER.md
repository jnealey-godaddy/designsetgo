# Section Divider Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: divider, shape, separator, wave, section

## Overview

The **Section Divider Block** is a standalone shape divider you can drop between any two blocks. It reuses the same shape library and theme-inheritance mechanism as the [Section](SECTION.md) block's built-in top/bottom shape dividers, but is a separate block you can place anywhere in the content flow — not pinned to a Section's edge.

**How it differs from a Section's edge dividers:**
- A Section's `shapeDividerTop`/`shapeDividerBottom` attributes render a **see-through knockout** cut into the section's own top/bottom edge — they reveal whatever sits behind the section, not a filled shape.
- Section Divider is a **solid-filled** shape, painted as its own block. It needs a fill color (from an attribute, a theme token, or the plugin default) because there's no "behind" for it to reveal.

Both mechanisms are independent — using one doesn't disable or replace the other. See [`docs/plans/2026-07-06-section-divider-block-design.md`](../plans/2026-07-06-section-divider-block-design.md) for the original design rationale.

## 🚀 Quick Start

1. Insert the **Section Divider** block between two other blocks (found in the Design category).
2. Choose a **Shape** from the settings panel, or leave it at **Theme default**.
3. Set a **Fill** color, or leave it unset to inherit the theme's divider color.
4. Adjust **Height**, **Width**, and flip options as needed.

## ⚙️ Settings & Configuration

### Settings

- **Shape** — `Theme default` (the block's `inherit` value) or one of 29 shape slugs (Wave, Wave Double, Tilt, Curve, Triangle, Zigzag, Clouds, Drops, Steps, Torn Paper, and more). There's no "None" option — to remove a divider, delete the block.
- **Height** — 10–500px via the slider; values outside that range can still be set (e.g. via a pattern) and are used as-is, unlike the Section block's edge dividers, which clamp. Leaving it unset (`Reset`) inherits the theme's divider height.
- **Width** — 100–300% horizontal stretch; also unclamped if set outside that range programmatically. Unset inherits the theme's divider width. An explicit `100%` is preserved rather than treated as "unset," so you can pin a divider against a theme token that specifies something else.
- **Flip horizontal** / **Flip vertical** — mirror the shape.

### Color

Under the block's Color panel:
- **Background** — the color behind/around the masked shape (transparent by default, so whatever sits behind the block shows through).
- **Fill** — the color of the shape itself. Unset inherits the theme token, then falls back to the theme's base color.

### Block Supports

- **Alignment**: Wide, Full (defaults to Full on insert).
- **Anchor**: Yes.
- **Spacing**: Margin only.
- Editing raw HTML for this block is disabled.

## Theme Integration

All three pieces resolve purely in CSS, from most to least specific, so a theme or Style Kit can change the plugin-wide default without touching any saved content:

| Token (`settings.custom.designsetgo.*`) | CSS custom property | Fallback |
|---|---|---|
| `shapeDivider.type` | `--wp--custom--designsetgo--shape-divider--type` | `wave` |
| `shapeDivider.height` | `--wp--custom--designsetgo--shape-divider--height` | `100px` |
| `shapeDivider.width` | `--wp--custom--designsetgo--shape-divider--width` | `100%` |
| `shapeDivider.color` | `--wp--custom--designsetgo--shape-divider--color` | `--wp--preset--color--base` |

`shapeDivider.type` is **shared with the Section block** — a kit that sets the default shape for Section's edge dividers sets it for this block too. `height`, `width`, and `color` are also block-agnostic but are new as of this block; nothing else currently consumes them. The plugin ships no `theme.json` itself — these tokens only take effect when a theme or Style Kit defines them; without one, the fallbacks above apply.

## 💡 Common Use Cases

### 1. Breaking Up Long-Form Content
Drop a subtle wave or tilt divider between a text section and an image gallery, without wrapping either in a Section block.

### 2. Decorative Accents Inside a Card
Place a small divider inside a `core/group` or Card to separate a header from body content.

### 3. Matching a Theme's Signature Shape
Leave **Shape**, **Height**, and **Fill** all unset so every divider on the site automatically follows the active theme/kit's divider tokens; only override per-instance when a design intentionally departs from it.

## ✅ Best Practices

**DO:**
- Leave Shape/Height/Fill at their theme-inherited defaults unless a specific divider needs to look different — this keeps the site consistent and keeps saved markup minimal.
- Use **Background** (not Fill) if you want colored space around a narrower shape (e.g. an asymmetric or triangle shape) rather than a colored shape.

**DON'T:**
- Stack many differently-shaped dividers back to back — it reads as visual noise rather than a section break.
- Confuse this block with Section's edge shape dividers when a section-edge effect (background knockout) is what's actually needed — reach for [Section](SECTION.md)'s own `shapeDividerTop`/`shapeDividerBottom` settings for that.

## ♿ Accessibility

The divider is purely decorative: the shape element is marked `aria-hidden="true"` and carries no text, so it's skipped entirely by assistive technology. There's nothing to focus and no keyboard interaction.

## 👨‍💻 Developer Notes

**Markup** (fully default divider — no inline style at all):
```html
<div class="wp-block-designsetgo-section-divider alignfull">
  <div class="dsgo-section-divider__shape dsgo-shape-divider is-shape-inherit" aria-hidden="true"></div>
</div>
```

With overrides, only the changed custom properties are emitted:
```html
<div class="wp-block-designsetgo-section-divider alignfull" style="--dsgo-section-divider-bg:...">
  <div class="dsgo-section-divider__shape dsgo-shape-divider is-shape-wave"
       style="--dsgo-section-divider-fill:...;--dsgo-shape-height:120px;--dsgo-shape-flip-x:-1"
       aria-hidden="true"></div>
</div>
```

**Shared code**: `src/blocks/section-divider/utils/index.js` re-exports `getShapeDividerOptions()`/`getShapeDivider()` from `../../section/utils/shape-dividers.js` (the single source of truth for the shape slug list) and owns `getDividerStyle()`, `getDividerShapeClass()`, and `getDividerWrapperStyle()` — the same three helpers back both `edit.js` and `save.js`, so the editor canvas and frontend output can't drift. The mask data-URIs and the `is-shape-*` → `--dsgo-shape-mask` mapping live in `src/styles/shared/_shape-mask-classes.scss` and `_shape-masks.scss`, shared with the Section block's own edge dividers. The height/width fallback cascade lives in `src/styles/shared/_shape-size.scss`, also shared.

**Nullable size handling**: `height`/`width` use `isExplicitShapeSize()` (`src/utils/shape-size.js`) rather than a plain `typeof value === 'number'` check, so `0`, negative numbers, and `NaN` are all treated as "unset" (they'd otherwise serialize a meaningless value like `--dsgo-shape-height:NaNpx`).

**Tests**: `src/blocks/section-divider/test/save.test.js` — covers the bare-inherit default, per-attribute var emission, preset-color-to-CSS-var conversion, and the zero/negative/NaN-size guard.

**No deprecations**: this is a new block with no prior markup to migrate.

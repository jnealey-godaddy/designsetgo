# Advanced Heading Block - User Guide

**Block name**: `designsetgo/advanced-heading`
**Child block**: `designsetgo/heading-segment`
**Category**: DesignSetGo
**Keywords**: heading, title, typography, multi-font, advanced

## Overview

The **Advanced Heading** block renders a single semantic heading element (H1–H6) made up of one or more **Heading Segment** child blocks. Each segment is an independent inline span with its own font family, weight, size, color, transform, and other typography controls. Adjacent segments sit flush against one another — giving the appearance of a single heading with mixed styles — while each segment remains a discreet, editable unit on the canvas.

**Key Features:**
- One heading tag (H1–H6), any number of inline segments
- Per-segment typography: font family, weight (100–900), size, line height, letter spacing, text transform, text decoration, color, gradient
- Per-segment background color and border (color, radius, style, width)
- Rich text formatting inside each segment (bold, italic, strikethrough, superscript, subscript)
- Gap between segments controlled by Block Gap (default 0 in 2.1.0 so segments read as one)
- Segment appender on the canvas — add segments without opening the sidebar (restored in 2.1.0)
- Native Block Bindings: each `heading-segment`'s `content` attribute is bindable via WordPress 6.9+ Block Bindings API (added in 2.1.0)
- Full Block Supports: text color, background, gradients, margin, padding, block gap, alignment (left, center, right, wide, full), anchor

---

## Block Attributes

### Advanced Heading (`designsetgo/advanced-heading`)

| Attribute   | Type   | Default | Description |
|-------------|--------|---------|-------------|
| `level`     | number | `2`     | Heading level (1–6). Sets the rendered HTML tag (`h1`–`h6`). |
| `textAlign` | string | —       | Text alignment (`left`, `center`, `right`). Applied as a CSS class and toolbar control. |

Segment gap is controlled through `style.spacing.blockGap` (WordPress Block Supports spacing). The default was changed to **0** in version 2.1.0.

### Heading Segment (`designsetgo/heading-segment`)

| Attribute | Type   | Default | Description |
|-----------|--------|---------|-------------|
| `content` | string | `""`    | RichText HTML content of the segment. Saved in `.dsgo-heading-segment__text`. |

All other per-segment styles (font family, weight, size, color, etc.) are stored in the `style` and `fontFamily` attributes provided by WordPress Block Supports.

---

## Inspector Controls

### Advanced Heading — Settings Panel

- **Heading Level** — Button group (H1–H6). Selecting a level updates both the rendered HTML tag and the Block Controls toolbar dropdown.

The text alignment control lives in the **Block Controls toolbar** (alignment toolbar).

Advanced Heading inherits all standard **Color**, **Typography**, and **Spacing** panels from WordPress Block Supports. These act as defaults that segments can individually override.

---

### Heading Segment — Block Controls Toolbar

Each segment exposes three quick-access toolbar dropdowns:

- **Font Family** — Dropdown of all font families registered in `theme.json`. Appears only when fonts are available. Selecting the same font again resets to default.
- **Font Weight** — Thin (100) through Black (900). Toggling the active weight resets to default.
- **Text Transform** — None, Uppercase, Lowercase, Capitalize.

Full per-segment typography, color, and spacing controls are available in the standard **Typography**, **Color**, and **Spacing** sidebar panels provided by Block Supports.

Per-segment border controls (color, radius, style, width) are available in the **Border** sidebar panel.

---

## Usage Examples

### Mixed-weight heading

```
<!-- wp:designsetgo/advanced-heading {"level":1} -->
  <!-- wp:designsetgo/heading-segment {"style":{"typography":{"fontWeight":"300"}}} -->
    <span class="wp-block-designsetgo-heading-segment dsgo-heading-segment">
      <span class="dsgo-heading-segment__text">Light</span>
    </span>
  <!-- /wp:designsetgo/heading-segment -->
  <!-- wp:designsetgo/heading-segment {"style":{"typography":{"fontWeight":"800"}}} -->
    <span class="wp-block-designsetgo-heading-segment dsgo-heading-segment">
      <span class="dsgo-heading-segment__text">Bold</span>
    </span>
  <!-- /wp:designsetgo/heading-segment -->
<!-- /wp:designsetgo/advanced-heading -->
```

### Two-color heading (word colored differently)

Add two segments to a single H2, set a custom text color on the second segment via the Color panel in the sidebar.

### Bound heading (Block Bindings)

When WordPress 6.9+ is active, each segment's `content` attribute can be bound to a post-meta, ACF, or any registered Block Bindings source — allowing the heading text to resolve dynamically per post.

---

## Frontend Behavior

The block is entirely static HTML. There is no frontend JavaScript. The heading renders as:

```html
<div class="wp-block-designsetgo-advanced-heading dsgo-advanced-heading">
  <h2 class="dsgo-advanced-heading__inner">
    <span class="wp-block-designsetgo-heading-segment dsgo-heading-segment">
      <span class="dsgo-heading-segment__text">…</span>
    </span>
    <!-- additional segments -->
  </h2>
</div>
```

The `--dsgo-segment-gap` CSS custom property on `.dsgo-advanced-heading__inner` controls spacing between segments (set via Block Gap).

---

## Accessibility

- The outer `<div>` wrapper is presentational; semantic heading level is set on the inner `<h1>`–`<h6>` tag.
- Ensure each segment's text has sufficient color contrast against its background.
- Avoid skipping heading levels — use the heading level control to maintain logical document hierarchy.
- Segments do not carry `role` or ARIA overrides; screen readers read the full heading text in source order.

---

## Related Blocks

- **Heading Segment** (`designsetgo/heading-segment`) — Child block, only valid inside Advanced Heading.
- Core **Heading** (`core/heading`) — Standard single-style heading; use Advanced Heading when per-word or per-phrase styling is needed.

---

*DesignSetGo v2.0.0+ | WordPress 6.4+*
*Segment appender on canvas restored in v2.1.0. Default segment gap changed to 0 in v2.1.0. Block Bindings support for segment `content` added in v2.1.0.*

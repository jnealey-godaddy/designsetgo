# Fifty Fifty Block - User Guide

**Block name**: `designsetgo/fifty-fifty`
**Category**: DesignSetGo
**Keywords**: fifty, split, half, media, image, hero

## Overview

The **Fifty Fifty** block creates a full-width 50/50 split layout with edge-to-edge media on one side and constrained, editable content on the other. The media side fills its half of the viewport completely — no letterboxing — while the content side automatically aligns its inner text with the site's normal content-width boundary. Use it for hero sections, feature highlights, about sections, or any two-column image + copy composition that needs to reach both viewport edges.

**Key Features:**
- True edge-to-edge media: image fills its half from top to bottom and to the viewport edge
- Content side constrains inner text to half the theme content width so it aligns with normal page text
- Flip media left/right from the block toolbar or the Settings panel
- Vertical content alignment (top, center, bottom) within the content column
- Configurable minimum height with unit picker (px, vh, vw, em, rem)
- Focal point picker controls which part of the image stays visible
- Inner content is a free InnerBlocks area — any block can be placed on the content side
- Stacks vertically on mobile (media always renders first)
- Full Block Supports: color, text, gradients, link, margin, block gap, typography, anchor

---

## Block Attributes

| Attribute            | Type   | Default                        | Description |
|----------------------|--------|--------------------------------|-------------|
| `align`              | string | `"full"`                       | Block alignment. Always full width. |
| `mediaPosition`      | string | `"left"`                       | Which side the media occupies: `left` or `right`. |
| `mediaId`            | number | `0`                            | Media library attachment ID. `0` means no image selected. |
| `mediaUrl`           | string | `""`                           | URL of the selected image. |
| `mediaAlt`           | string | `""`                           | Alt text for the image. |
| `focalPoint`         | object | `{x: 0.5, y: 0.5}`            | `object-position` expressed as 0–1 fractions. |
| `minHeight`          | string | `"500px"`                      | CSS min-height of the block. Accepts any unit supported by UnitControl. |
| `verticalAlignment`  | string | `"center"`                     | Vertical alignment of content within the content column: `top`, `center`, or `bottom`. |
| `contentPadding`     | string | `"var:preset\|spacing\|50"`    | Internal padding for the content column. Accepts a spacing preset or a raw CSS value. |

---

## Inspector Controls

All controls live in a single **Settings** panel with per-control reset and a global "Reset all" button.

### Settings Panel

- **Media Position** — Select: Left (default) or Right. Also togglable from the block toolbar via the "Flip Layout" button.
- **Content Vertical Alignment** — Select: Top, Center (default), Bottom. Controls how inner content is positioned within the content column.
- **Min Height** — UnitControl (px, vh, vw, em, rem). Default `500px`. Sets the block's minimum height.
- **Image** — MediaUpload button. Shows a thumbnail preview once an image is selected, with "Replace" and "Remove" buttons.
- **Alt Text** — Text field (visible only when an image is selected). Describe the image for accessibility. Leave blank for decorative images.
- **Focal Point** — FocalPointPicker (visible only when an image is selected). Click to set which area of the image remains visible when cropped by the grid layout.

Block Supports panels (Color, Typography, Spacing) appear in their standard sidebar locations and apply to the block wrapper.

---

## Usage Examples

### Basic hero with media on the left

```
<!-- wp:designsetgo/fifty-fifty {"mediaPosition":"left","minHeight":"600px"} -->
  <!-- wp:heading {"level":1} -->
    <h1>A Bold Statement</h1>
  <!-- /wp:heading -->
  <!-- wp:paragraph -->
    <p>Supporting copy goes here.</p>
  <!-- /wp:paragraph -->
  <!-- wp:buttons -->
    <!-- wp:button -->
      <div class="wp-block-button"><a class="wp-block-button__link">Learn More</a></div>
    <!-- /wp:button -->
  <!-- /wp:buttons -->
<!-- /wp:designsetgo/fifty-fifty -->
```

### Media on the right, vertically top-aligned content

Set `"mediaPosition":"right"` and `"verticalAlignment":"top"` in the block attributes, or use the toolbar flip button and the Settings panel.

---

## Frontend Behavior

The block is static HTML — no frontend JavaScript. The rendered structure is:

```html
<div class="wp-block-designsetgo-fifty-fifty dsgo-fifty-fifty dsgo-fifty-fifty--media-left"
     style="--dsgo-fifty-fifty-min-height:600px; --dsgo-fifty-fifty-content-justify:center; …">
  <div class="dsgo-fifty-fifty__media">
    <img src="…" alt="…" loading="lazy" style="object-position:50% 50%;" />
  </div>
  <div class="dsgo-fifty-fifty__content">
    <div class="dsgo-fifty-fifty__content-inner">
      <!-- InnerBlocks output -->
    </div>
  </div>
</div>
```

The CSS custom property `--dsgo-fifty-fifty-min-height` controls block height. The `.dsgo-fifty-fifty__content-inner` wrapper caps the inner content width at half the theme content size (`calc(contentSize / 2)`) and uses `max()` padding to align the outer edge with the viewport's content margin.

**Mobile:** at 767 px and below the two-column grid switches to a single column. The media panel always renders first regardless of `mediaPosition`. The content-inner max-width constraint is removed so content spans the full mobile width.

---

## Accessibility

- Images without alt text automatically receive `aria-hidden="true"` so decorative images are skipped by screen readers. Always provide alt text for informational images.
- The image `loading="lazy"` attribute is added on save; use a non-lazy image (or set `fetchpriority="high"` via additional HTML) if the block is the page's above-the-fold hero.
- Content placed in the InnerBlocks area should maintain a logical heading hierarchy relative to the surrounding page.
- The flip toolbar button (`Flip Layout`) is keyboard accessible.

---

## Related Blocks

- **Section** (`designsetgo/section`) — Full-width container with background image/video support; use when you need more than two columns or a full-bleed background rather than a split.
- **Dynamic Image** (`designsetgo/dynamic-image`) — Use inside the content side (or replace the static media side) when the image must resolve per-post from a featured image or ACF field.
- **Grid** (`designsetgo/grid`) — For layouts with more than two columns or asymmetric proportions.

---

*DesignSetGo v1.5.0+ | WordPress 6.4+*

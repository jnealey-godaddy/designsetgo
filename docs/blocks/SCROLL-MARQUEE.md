# Scroll Marquee Block - User Guide

**Block name**: `designsetgo/scroll-marquee`
**Category**: DesignSetGo
**Keywords**: scrolling, gallery, images, parallax, scroll, infinite

## Overview

The **Scroll Marquee** block (titled "Scrolling Gallery" in the inserter) displays one or more rows of images that scroll horizontally as the user scrolls the page. Each row has an independent scroll direction (left or right), creating an alternating parallax gallery effect. Images in each row are seamlessly duplicated six times internally so the scroll loop is continuous.

Authors manage images directly on the canvas in the block editor — clicking "Add Images" opens the Media Library with multi-select, and individual images can be removed with a single click. Each row has a direction toggle button on the canvas.

**Key Features:**
- Multiple independent rows, each with a configurable scroll direction (left or right)
- Scroll-speed control: how fast rows move relative to page scroll
- Configurable image dimensions (height, width) and border radius
- Independent gap between images within a row and gap between rows
- Click-and-drag and mouse-wheel interaction for manual browsing (added in v1.2.0)
- Images duplicated 6× in the HTML for a seamless infinite loop
- Scroll animation runs only when the block is in the viewport (IntersectionObserver)
- Respects `prefers-reduced-motion` — no animation when the user opts out
- Full Block Supports: background color, text color, gradients, block gap, anchor

---

## Block Attributes

| Attribute      | Type   | Default   | Description |
|----------------|--------|-----------|-------------|
| `rows`         | array  | One row, direction `left`, no images | Array of row objects. Each row has `images` (array of `{id, url, alt}`) and `direction` (`"left"` or `"right"`). |
| `scrollSpeed`  | number | `0.5`     | Multiplier applied to `window.scrollY`. Higher values move rows faster. Range: 0.1–2. |
| `imageHeight`  | string | `"200px"` | CSS height of every image in the gallery. |
| `imageWidth`   | string | `"300px"` | CSS width of every image in the gallery. |
| `gap`          | string | `"20px"`  | Gap between images within a row. |
| `rowGap`       | string | `"20px"`  | Gap between rows. |
| `borderRadius` | string | `"8px"`   | Border radius applied to every image. |

---

## Inspector Controls

All controls live in a single **Settings** panel with per-control reset and a global "Reset all" button.

### Settings Panel

- **Scroll Speed** — RangeControl (0.1–2, step 0.1, default 0.5). Controls how many pixels rows translate per pixel of page scroll. Higher values create a more dramatic parallax.
- **Image Height** — UnitControl. Default `200px`. Sets the fixed height for all images.
- **Image Width** — UnitControl. Default `300px`. Sets the fixed width for all images.
- **Border Radius** — UnitControl. Default `8px`. Rounds the corners of every image.
- **Gap Between Images** — UnitControl. Default `20px`. Horizontal space between images within a row.
- **Gap Between Rows** — UnitControl. Default `20px`. Vertical space between rows.

A performance notice in the panel displays the total image count (images × 6 duplicates). A warning appears when total source images exceed 20.

### On-Canvas Controls

Row management and image selection happen directly on the block canvas rather than in the sidebar:

- **Direction Toggle** — Each row has a button ("← Scroll Left" / "Scroll Right →") that flips `direction` between `left` and `right`.
- **Add Images / Add More Images** — Opens the Media Library with multi-select. Replaces the existing image list for that row with the full selection.
- **Remove Image** — A close button on each image thumbnail removes it from the row.
- **Add Row** — A primary button at the bottom of the block appends a new row. New rows alternate direction automatically (even rows left, odd rows right).
- **Remove Row** — A close button on each row removes it entirely.

---

## Usage Examples

### Two-row alternating gallery

```json
{
  "rows": [
    {
      "images": [
        {"id": 10, "url": "/wp-content/uploads/img1.jpg", "alt": "Mountain vista"},
        {"id": 11, "url": "/wp-content/uploads/img2.jpg", "alt": "Forest path"}
      ],
      "direction": "left"
    },
    {
      "images": [
        {"id": 12, "url": "/wp-content/uploads/img3.jpg", "alt": "Coastal cliffs"},
        {"id": 13, "url": "/wp-content/uploads/img4.jpg", "alt": "Desert dunes"}
      ],
      "direction": "right"
    }
  ],
  "scrollSpeed": 0.5,
  "imageHeight": "220px",
  "imageWidth": "330px",
  "borderRadius": "12px"
}
```

### Slow full-width portrait gallery

Set `imageHeight` to `"320px"` and `imageWidth` to `"240px"` for portrait-oriented images, and lower `scrollSpeed` to `0.2` for a subtler effect.

---

## Frontend Behavior

The block ships a `view.js` frontend script that initializes on `DOMContentLoaded` (with a `load` event fallback).

For each `.dsgo-scroll-marquee` element on the page the script:

1. Reads `data-scroll-speed` from the wrapper element.
2. Measures each row's track-segment width and gap to calculate the loop range.
3. On every `scroll` event (throttled via `requestAnimationFrame`), computes `translateX` for each row track: rows with `data-direction="left"` move negative; rows with `data-direction="right"` move positive, offset so they start off-screen to the left.
4. Applies a modulo wrap so the translate value never grows unbounded, producing a seamless infinite loop.
5. Uses `IntersectionObserver` to pause updates when the block is not visible, conserving CPU.

**Click-and-drag:** The cursor changes to a grab hand on the block. Pointer events capture drag deltas and apply them as a `manualOffset` in addition to scroll position, so users can manually swipe through images.

**Mouse wheel:** Horizontal wheel events (and `shift+wheel`) adjust `manualOffset` and prevent default page scroll while the wheel is over the block. Vertical-only scroll passes through normally.

**Reduced motion:** When `prefers-reduced-motion: reduce` is set, the script exits immediately after initialization and the CSS resets any `transform` to `none`, displaying images in their natural static layout.

The saved HTML renders each row's images inside six identical `.dsgo-scroll-marquee__track-segment` elements (the duplication that enables infinite looping).

---

## Accessibility

- All images must have alt text set in the Media Library or entered when uploading. The block saves `alt` per image; empty alt text is preserved as `alt=""` for decorative images.
- Images are marked `loading="lazy"` on save.
- The block respects `prefers-reduced-motion`: when the user has enabled this system preference, all rows display statically with no scrolling animation.
- The draggable interaction is pointer-based; the gallery content remains readable without interaction.

---

## Related Blocks

- **Slider** (`designsetgo/slider`) — Use when you need navigation controls, auto-play, or per-slide rich content (headings, buttons, etc.) rather than a pure image gallery.
- **Scroll Slides** (`designsetgo/scroll-slides`) — Vertical scroll-driven section transitions; use when the scroll interaction should advance full-page sections rather than an image strip.

---

*DesignSetGo v1.0.0+ | WordPress 6.4+*
*Click-drag and mouse-wheel scroll interactions added in v1.2.0.*

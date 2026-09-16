# Hotspot Block - User Guide

**Version**: 1.0.0
**Category**: DesignSetGo
**Keywords**: image, tooltip, marker, hotspot

## Overview

The **Hotspot Block** places interactive markers over an image. Each marker (a **Hotspot Item**) opens a tooltip describing that point on the image — a diagram callout, a "shop the look" tag, a floor-plan annotation, or a step in a visual process.

A Hotspot block only ever contains Hotspot Item children; you cannot insert a Hotspot Item anywhere else.

## 🚀 Quick Start

1. **Add the Block**: Insert the **Hotspot** block. It comes with two starter markers already placed on the canvas.
2. **Choose an Image**: In the Settings panel, click **Select image**.
3. **Position Markers**: Click a marker to select it, then drag it to the spot you want, or use the arrow keys (hold `Shift` for larger 10% jumps).
4. **Edit Tooltips**: Click a marker to open its tooltip on the canvas, then type the description directly into it.
5. **Add More Markers**: With the Hotspot block selected, use the **Add hotspot** button in the toolbar.

## ⚙️ Settings & Configuration

### Settings (Hotspot block)

- **Image** — Select/replace the background image via the Media Library.
- **Alternative text** — Alt text for the image.
- **Trigger** — `Click` (default) or `Hover`. Controls how every marker opens its tooltip unless a marker overrides it.
- **Tooltip position** — `Top` (default), `Right`, `Bottom`, `Left`. Default side for tooltips relative to their marker.
- **Tooltip width** — 120–600px (default 240px).

### Style (Hotspot block)

- **Marker animation** — `Pulse` (default), `Scale`, `Fade`, or `None`. Applied to every marker unless a marker overrides it.
- **Sequence duration** — 0–3000ms (default 0). Staggers each marker's animation start by `sequenceOrder × sequenceDuration`, so markers can pulse in a wave rather than in unison.

### Color (Hotspot block)

Under **Colors** in the block's Color panel:
- **Marker color** / **Marker background** — the marker circle's text/icon and fill colors.
- **Tooltip background** / **Tooltip text color**.

### Toolbar

- **Show only selected hotspot / Show all hotspots** (eye icon) — editor-only view toggle that hides every marker except the selected one, useful when markers overlap. It never affects saved output.
- **Add / Duplicate / Move / Remove hotspot** — manages Hotspot Item children. Duplicating a marker gives the copy a fresh internal ID so its ARIA wiring doesn't collide with the original.

### Positioning a marker

The image doubles as a coordinate canvas. With a marker selected:
- **Drag** it with the mouse/pointer.
- **Arrow keys** nudge it 1% per press, or 10% with `Shift` held.

Position updates are announced to screen readers (e.g. "Hotspot position: 42% horizontal, 60% vertical").

## Hotspot Item (child block)

Each marker is a `designsetgo/hotspot-item`. It cannot be inserted on its own — only added inside a Hotspot block — and doesn't appear in the block inserter.

### Settings (Hotspot Item)

- **Label** — Text shown on the marker (default `+`).
- **Icon text** — An optional symbol/emoji shown instead of the label.
- **Link** — Optional URL. Only `https`, `http`, `mailto`, and `tel` links are used; anything else (e.g. `javascript:`) is silently ignored and the marker stays a plain button.
- **Trigger override** — `Inherit from Hotspot` (default), `Click`, or `Hover`.
- **Tooltip position** — `Inherit from Hotspot` (default) or an explicit side.
- **Tooltip width** — Inherits from the parent by default; can be set per marker (120–600px).

### Style (Hotspot Item)

- **Marker animation** — `Inherit from Hotspot` (default), `Pulse`, `Scale`, `Fade`, `None`.
- **Sequence order** — 0–20 (default 0). Multiplied by the parent's sequence duration to delay this marker's animation.
- **Horizontal origin** / **Vertical origin** — `Left/Center/Right` and `Top/Center/Bottom` (both default `Center`). Controls which point of the marker sits on the x/y coordinate — useful for anchoring a marker flush against the edge of a small target area instead of centering it.

### Marker behavior

- If a marker has a **Link**, it always renders as an `<a>` and keeps normal browser navigation — its tooltip opens on hover/focus only, and a click is never intercepted.
- Without a link, a **click**-trigger marker toggles its tooltip open/closed via a `<button>` with `aria-expanded`/`aria-controls`; a **hover**-trigger marker uses `aria-describedby` and opens on pointer/focus.
- If a marker has no meaningful visible text — an icon is set, or the label is empty or the default `+` — it gets an accessible `aria-label="Hotspot"` automatically.

## 💡 Common Use Cases

### 1. Product "Shop the Look"
Place markers on a lifestyle photo, each linking to a product page.

### 2. Diagram / Infographic Callouts
Annotate parts of a diagram or floor plan with short descriptions.

### 3. Before/After or Process Steps
Use **Sequence order** + **Sequence duration** to have markers pulse in order, guiding the eye across the image.

### 4. Feature Tours
Hover-triggered markers that reveal a short tip without requiring a click — good for onboarding screenshots.

## ✅ Best Practices

**DO:**
- Keep tooltip text short — it's a callout, not a paragraph.
- Use **Hover** trigger for desktop-only tours; use **Click** (the default) when the page also needs to work on touch devices.
- Give linked markers a clear label or icon so the destination is predictable.

**DON'T:**
- Overlap markers so closely that dragging one is difficult — use **Show only selected hotspot** while positioning.
- Rely on marker color alone to convey meaning (also vary label/icon).
- Nest a Hotspot inside another Hotspot's tooltip.

## ♿ Accessibility

- **Keyboard**: Markers are focusable buttons/links. `Tab` moves between them; `Enter`/`Space` (or click) toggles a click-trigger tooltip. `Escape` closes every open tooltip on the page and returns focus to the marker that opened it.
- **ARIA**: Click-trigger markers expose `aria-expanded`/`aria-controls`; hover-trigger and linked markers expose `aria-describedby`. The tooltip itself carries `role="tooltip"`.
- **Screen readers**: Icon-only or unlabeled markers get a fallback `aria-label="Hotspot"`. Dragging a marker in the editor announces its new position as a percentage.
- **Reduced motion**: Marker pulse/scale/fade animations and tooltip transitions are disabled under `prefers-reduced-motion: reduce`.

## 👨‍💻 Developer Notes

**Context**: The parent block provides `trigger`, `tooltipPosition`, `tooltipWidth`, `animation`, and `sequenceDuration` via block context (`designsetgo/hotspot/*`). A Hotspot Item consumes these and falls back to them whenever its own attribute is `'inherit'` (or, for `tooltipWidth`, unset).

**DOM structure** (frontend):
```html
<div class="dsgo-hotspot" data-dsgo-hotspot="true" data-dsgo-hotspot-trigger="click">
  <div class="dsgo-hotspot__image-wrap">
    <img class="dsgo-hotspot__image" src="..." alt="..." />
    <div class="dsgo-hotspot__items">
      <div class="dsgo-hotspot-item" data-dsgo-hotspot-item="true">
        <button class="dsgo-hotspot-item__marker" data-dsgo-hotspot-marker="true">+</button>
        <div class="dsgo-hotspot-item__tooltip" data-dsgo-hotspot-tooltip="true" role="tooltip" hidden>…</div>
      </div>
    </div>
  </div>
</div>
```

**Frontend interactivity** (`view.js`) uses one set of delegated `document` listeners (click, pointerover, pointerout, focusin, focusout, keydown) shared by every Hotspot block on the page, rather than per-marker listeners:
- Opening a click-trigger item closes any other open item **in the same parent Hotspot**; separate Hotspot blocks stay independent.
- A linked marker (`<a href>`) always behaves like a hover item for tooltip purposes, regardless of the resolved trigger, since its click must be left free to navigate.
- `Escape` closes every open tooltip on the page and restores focus to the marker whose item had it.

**Security**: `getSafeHotspotUrl()` (`src/blocks/hotspot-item/utils.js`) allow-lists `https:`, `http:`, `mailto:`, `tel:` — anything else is dropped and the marker saves as a `<button>` instead of an `<a>`. `getSafeHotspotColor()` allow-lists WordPress preset var tokens, hex, and `rgb()`/`hsl()` functional colors before they're written into inline CSS custom properties.

**Deprecation**: `hotspot-item/deprecated.js` ships one entry (`v1`) that flags previously-saved markers which predate the current, broader accessible-label rule (any marker with an icon, an empty label, or the default `+` label now gets `aria-label="Hotspot"`). Its `migrate()` is an identity function — the entry exists only to route matching content back through the current `save()` so the label gets added.

**Tests**: `src/blocks/hotspot/test/save.test.js`, `src/blocks/hotspot/test/view.test.js`, `src/blocks/hotspot-item/test/save.test.js`.

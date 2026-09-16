# Text Path Block - User Guide

**Block name**: `designsetgo/text-path`
**Version**: 1.0.0
**Category**: DesignSetGo
**Keywords**: text, svg, path, curve

## Overview

The **Text Path Block** flows a line of text along an SVG path — a wave, arc, circle, oval, spiral, or a custom SVG path you upload. It is a static block (saved markup, not server-rendered), with an optional gentle scrolling animation along the path.

**Key Features:**
- Six built-in path shapes, plus custom SVG path upload (validated server-side)
- Adjustable font size, rotation, start offset, word spacing, and padding away from the path
- Optional visible guide line, with its own color/opacity/thickness
- Optional looping motion animation, forward or reverse, that respects `prefers-reduced-motion`
- Optional link wrapping the whole graphic
- LTR/RTL text direction

## Quick Start

1. Insert the **Text Path** block.
2. Type your text in the **Text** field (Settings panel).
3. Choose a **Path** shape — Wave, Arc, Circle, Line, Oval, Spiral, or Custom SVG.
4. Adjust **Font size**, **Rotation**, and other Style controls until the text sits where you want it on the path.
5. Optionally turn on **Show path** while positioning text, then turn it off before publishing.

## Settings & Configuration

### Settings

- **Text**: the string drawn on the path (`text`, default `"Text on a path"`).
- **Path**: Wave, Arc, Circle, Line, Oval, Spiral, or Custom SVG (`pathType`, default `wave`). Choosing Custom SVG reveals a **Choose SVG path** upload button.
  - **Arc size** (Arc only): 0 (flat) to 100 (full curve) (`arcSize`, default `100`).
- **Show path**: draws the underlying path as a visible guide line, useful while positioning text (`showPath`, default `false`).
- **Direction**: Left to right / Right to left (`direction`, default `ltr`) — sets the SVG `<text>` element's `direction`.
- **Link**: an optional URL (`url`) wraps the whole graphic in an `<a>`; a nested **Open in new tab** toggle sets `target` (`target`, default `false`).

### Style

- **Font size**: 1–400px (`pathFontSize`, default `54`).
- **Rotation**: -180° to 180° in the inspector; clamped to -360°–360° at render (`rotation`, default `0`).
- **Start offset**: -100 to 100 (`startOffset`, default `0`) — where along the path the text begins, as a percentage of path length.
- **Word spacing**: -40 to 100 (`wordSpacing`, default `0`).
- **Path padding**: -200 to 200 (`pathPadding`, default `0`) — moves text perpendicular to the path (a `dy` offset); negative moves the opposite direction.
- **Path width**: 25–100% (`pathWidth`, default `100`) — scales the rendered graphic's width.
- **Path alignment**: Left / Center / Right (`pathAlignment`, default `left`) — horizontal position of the graphic within the block.
- **Guide opacity** / **Guide line thickness** (shown only when Show path is on): 0–1 (`guideOpacity`, default `0.35`) and 0–24px (`guideStrokeWidth`, default `2`).
- **Animate text**: enables looping motion along the path (`motion`, default `false`), revealing:
  - **Motion duration**: 2–120 seconds (`motionDuration`, default `12`).
  - **Motion direction**: Forward / Reverse (`motionDirection`, default `forward`).

### Color

A **Path colors** dropdown in the sidebar's Color panel exposes **Guide line** color (`guideColor`) always, and **Circle background** color (`circleBackgroundColor`) only when Path is set to Circle — it fills the circle behind the text.

### Block Supports

Anchor; text color; spacing (margin, padding); typography (font size, line height, font family, font weight, letter spacing).

## Custom SVG Paths

Uploading a custom SVG sends the file to `POST /designsetgo/v1/text-path/extract` (requires `upload_files`), which parses it server-side with `DOMDocument` and returns only a `viewBox` and a single `<path>` `d` string — never the original markup. The parser rejects:

- Files over 12 KB, or documents containing a DOCTYPE or entity declaration
- Any `<script>` or `<foreignObject>` element anywhere in the document
- A missing or non-positive `viewBox`
- Path data using any command outside `M L H V C S Q T A Z` (upper or lower case), or malformed argument counts/separators

The first path element in the document that passes validation is used; everything else in the file is discarded. See [REST-API-REFERENCE.md](../api/REST-API-REFERENCE.md#post-text-pathextract) for the endpoint reference.

## Frontend Behavior (Motion)

When **Animate text** is on, the frontend view script (`view.js`) scrolls the text along the path continuously:

- Motion pauses automatically when the browser tab is hidden (`visibilitychange`) and resumes when it becomes visible again.
- Motion never starts at all when the visitor's OS/browser reports `prefers-reduced-motion: reduce`.
- On a closed path (e.g. Circle), the text loops continuously. On an open path (e.g. Wave, Line), the motion bounces back and forth between the path's ends rather than jumping.
- The editor previews the same animation on canvas (via the shared `motion.js` module) so the Animate toggle isn't a "trust me" setting.

## Accessibility

- The SVG has `role="img"` with `aria-label` set to the block's text, so screen readers get the text content directly rather than trying to parse the path/text elements.
- Motion honors `prefers-reduced-motion: reduce` (see above) — no separate toggle is needed for vestibular-disorder accessibility.
- A decorative **Show path** guide line should generally be turned off before publishing unless it's part of the design.

## Related Blocks

- **Icon** (`designsetgo/icon`) — see [ICON.md](ICON.md) for the plugin's icon library, used elsewhere for icon-based graphics.

## Developer Notes

- Static block: markup is produced by `save.js`/`edit.js` from the shared `TextPathGraphic` component and stored in post content; there is no `render.php`.
- `uniqueId` is auto-generated per block instance (`useUniqueBlockId`) and de-duplicated on paste/duplicate — a duplicate ID found elsewhere in the editor is cleared automatically, since the ID becomes the SVG `<path>` element's `id` and a collision would make two blocks' `<textPath>` references point at the same path.
- The `rel` attribute is defined in `block.json` and exposed in the inspector's reset behavior, but `save.js` always writes a hardcoded `rel="noopener noreferrer"` on the link regardless of its value — the attribute is not currently read anywhere.
- Path presets (`wave`, `arc`, `circle`, `line`, `oval`, `spiral`) and the client-side SVG-path validator live in `src/utils/svg-paths.js`, shared between this block and the custom-upload flow.
- Has a deprecation (`deprecated.js`) for a pre-motion markup version.
- Covered by `src/blocks/text-path/test/block.test.js`, `save.test.js`, and `view.test.js`.

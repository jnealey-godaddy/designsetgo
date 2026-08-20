# Visual Effects Cluster Design

## Purpose

Deliver three close-Elementor-parity visual effects without duplicating the plugin's existing animation, SVG, and interaction systems: Hotspot, Text Path, and Animated Headline.

## Decisions

### Hotspot

`designsetgo/hotspot` is an image-backed parent block with `designsetgo/hotspot-item` children. Each child owns its percentage coordinates, label or icon, link, tooltip content, and per-marker override. The parent owns image, tooltip defaults, and shared visual styles.

The frontend uses a single delegated controller built on the Interaction Layers event model. Click mode supports Escape, outside-click close, `aria-expanded`, and `aria-controls`; hover mode is augmented by focus/blur. Reduced motion disables pulse, sequencing, and tooltip transitions.

### Text Path

`designsetgo/text-path` is a static block that emits one inline SVG containing a `<path>` and `<textPath>`. It shares preset path definitions and safe normalization utilities with `svg-patterns`, but does not reuse that extension's CSS-background renderer. For custom SVG it keeps only a normalized `viewBox` and the first usable `<path d>`; it never stores or emits uploaded SVG markup.

### Animated Headline

Animated Headline extends `designsetgo/advanced-heading`. Existing heading segments remain before/after text, while one `heading-segment` receives an enhanced animated role and an ordered word list. The parent controls highlighted or rotating mode, effect, timing, loop, and link. This preserves heading semantics and segment typography, and builds on the landed `text-reveal` splitter instead of creating another one.

## Compatibility and safety boundary

All blocks keep `apiVersion: 3`, use `useBlockProps()` and `useInnerBlocksProps()`, and use the standard Settings / Style / Advanced inspector layout. No global SVG upload MIME exception is added: a capability-checked endpoint returns only safe path data, and saved content never contains executable SVG.

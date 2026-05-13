# SVG Patterns Extension

Adds a library of 31 repeatable SVG background patterns to supported blocks. Patterns are rendered as CSS `background-image` data URIs — no JavaScript, no extra HTTP requests on the frontend.

## How it works

A `dsgoSvgPatternEnabled` toggle and supporting attributes are added to `core/group` and `designsetgo/section`. When a pattern is selected, the block's saved markup includes `data-dsgo-svg-pattern` and related data attributes. A `render_block` filter (`SVG_Pattern_Renderer`) reads those attributes server-side, builds the SVG markup, URL-encodes it, and injects the result as `--dsgo-svg-pattern-image` and `--dsgo-svg-pattern-size` CSS custom properties on the block's root element. The editor canvas applies the same pattern inline via JavaScript so the result is visible while editing.

CSS preset colors (`var(--wp--preset--color--{slug})`) are resolved to their actual hex values before encoding, since SVG data URIs are external documents that cannot inherit page CSS custom properties.

## Supported blocks

- `core/group`
- `designsetgo/section`

## Inspector controls

Select a supported block, then open the **SVG Pattern** panel in the block sidebar.

- **Enable SVG pattern** — toggle the pattern overlay on or off.
- **Pattern picker** — thumbnail grid grouped by category. Click a thumbnail to select it; click **Clear** to remove the selection.
- **Pattern Color** — colour picker in the **Styles → Color** panel (appears when a pattern is active). Accepts theme palette colours, custom hex, or any CSS colour value.
- **Pattern Opacity** — 0.05 to 1.0, step 0.05.
- **Pattern Scale** — 0.25× to 4×, step 0.25. Controls the tile size relative to the pattern's natural dimensions.
- **Fixed Background** — makes the pattern scroll at a different rate than the page content (CSS `background-attachment: fixed`). May not work on mobile.

## Pattern library

31 patterns across six categories:

| Category | Patterns (examples) |
|---|---|
| **Minimal** | Dot Grid, Cross Grid, Diagonal Lines, Horizontal Lines, Diagonal Stripes, Dashes, Polka Dots |
| **Geometric** | Hexagons, Diamond Grid, Chevrons, Flipped Diamonds, Triangle Grid, Falling Triangles, Glamorous, Architect, Hideout, Brick Wall |
| **Organic** | Topography, Waves, and others |
| **Texture** | Circuit Board, Graph Paper, and others |
| **Depth** | Multi-layer shadow patterns |
| **Technical** | Technical/blueprint-style patterns |

Pattern data is defined in `src/extensions/svg-patterns/pattern-data.js` (JS) and exposed to PHP via `includes/svg-pattern-data.php` through the `designsetgo_get_svg_pattern_data()` function.

## Frontend behavior

No JavaScript runs on the frontend. The pattern appears as a tiled SVG `background-image` on the block's root element, layered on top of any existing background using CSS. The `--dsgo-svg-pattern-image` and `--dsgo-svg-pattern-size` custom properties are set as inline styles; the plugin stylesheet reads those properties to apply the pattern.

Identical pattern/color/opacity/scale combinations on the same page share a per-request SVG cache, so repeated blocks do not regenerate the same markup.

## Notes

- The extension was present before 2.1.0. The PHP server-side renderer (`SVG_Pattern_Renderer`) was added in 2.1.0 for improved performance and CSS preset colour resolution.
- Pattern `d` path values are static; user input never reaches SVG attribute values.
- Color input is validated against an allowlist of CSS colour formats before being embedded in SVG markup.

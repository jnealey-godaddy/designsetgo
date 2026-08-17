# GreenShift Gap Roadmap — Spec

**Date:** 2026-08-16
**Status:** Spec. Seven independent plans derive from this document.

## Context

Competitive diff against GreenShift (greenshiftwp.com) as of 2026-08. We are ahead on
query construction (Dynamic Query v2.5), forms (they have none), dynamic-data breadth
(native Block Bindings across ACF / Meta Box / Pods / JetEngine), and modal triggering.

Conditional asset loading was audited and is **not** a gap: every `block.json` declares
`"style": "file:./style-index.css"`, so core enqueues per-block CSS on demand; the shared
bundle (`build/style-index.css`) is 88 KB raw / **7.3 KB gzipped** and carries only
extensions, utilities, and shared primitives. No duplication between the aggregate and
per-block chunks was found.

Deliberately **out of scope**: global-classes / Style Manager / Stylebook (fights
`theme.json` and Global Styles, violates the WP-native-first principle), 3D / Spline /
Rive, Figma-to-blocks conversion, WooCommerce depth.

## Global Constraints

These apply to every plan derived from this spec. Copied verbatim into each plan.

- **Indentation:** tabs for JS/SCSS/PHP, 2 spaces for JSON/YAML.
- **Prefixes:** `dsgo-` for CSS classes and `data-` attributes, `dsgoFooBar` for
  JS/block attributes, `designsetgo_` for PHP functions, `DesignSetGo\` for PHP classes.
- **File size:** 300 lines max per file, excluding data/constant tables.
- **Block JSON:** `"apiVersion": 3`, `"textdomain": "designsetgo"`,
  `"category": "designsetgo"` (matches every existing block — see
  `src/blocks/progress-bar/block.json`), `"$schema"` pointing at
  `https://schemas.wp.org/trunk/block.json`.
- **Block props:** always `useBlockProps()` / `useInnerBlocksProps()`. Never bare
  `<InnerBlocks />`.
- **Inspector IA:** three panels — Settings → Style → Advanced — via
  `<DsgoInspectorPanel>` with `panelName` of `'settings'` or `'style'`,
  `panelId={clientId}`, and `isShownByDefault` on every `.Item`. Colour lives in
  `<InspectorControls group="color">`.
- **Supports first:** prefer native `supports` (colour / typography / spacing / border)
  over custom controls.
- **Styles:** every new frontend stylesheet must be reachable. Per-block styles go in
  `src/blocks/<block>/style.scss` (auto-entried by `webpack.config.js`). Shared or
  extension styles go in `src/style.scss` **and** `src/styles/editor.scss`.
  `src/styles/style.scss` is dead code — never add to it.
- **No new runtime dependencies.** No GSAP, no Chart.js, no jQuery. Everything ships as
  hand-written ES modules or CSS. This is the performance story.
- **Frontend JS:** Interactivity API for any block with cross-block or persisted state
  (add a `src/blocks/<name>/view.module` marker file so webpack emits a real ES module);
  plain `view.js` with delegated `.closest()` listeners otherwise.
- **Escaping:** `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` on all PHP
  output. `defined( 'ABSPATH' ) || exit;` at the top of every PHP file.
- **i18n:** text domain `designsetgo`, `sprintf()` never concatenation.
- **Accessibility:** WCAG AA. Keyboard reachable, visible focus, `prefers-reduced-motion`
  honoured by every animation.
- **No `console.log`** in committed code.
- **Branches:** `claude/<short-name>`. Commits: `type: description`
  (`feat`, `fix`, `refactor`, `style`, `docs`, `chore`). No Claude attribution.
- **Pre-commit gate:** `npm run build && npm run lint:js && npm run lint:css &&
  npm run lint:php && npm run test:unit`.

## Plans

Ordered by leverage. Each is independently shippable.

| # | Plan | Why |
|---|------|-----|
| 1 | [Interaction Layers](2026-08-16-interaction-layers.md) | Their single biggest differentiator. Generalises our trigger system from one action (animate) to many, across all 69 blocks. |
| 2 | [Video Block](2026-08-16-video-block.md) | We have none. Facade loading is a real performance win. |
| 3 | [Chart Block](2026-08-16-chart-block.md) | We have none. Compounds with Query/bindings — nobody does dynamic charts natively. |
| 4 | [Schema JSON-LD](2026-08-16-schema-jsonld.md) | Cheap. Opens the review/affiliate market from blocks we already ship. |
| 5 | [Off-Canvas Panel](2026-08-16-offcanvas-panel.md) | Modal already has the machinery; this is mostly CSS and a variation. |
| 6 | [Social Share Block](2026-08-16-social-share-block.md) | Small, frequently requested, zero dependencies. |
| 7 | [Animation Depth](2026-08-16-animation-depth.md) | Matches most of their GSAP addon with zero JS library. |

## Sequencing note

Plan 1 introduces `src/extensions/interactions/`, and Plans 2, 5, and 7 each become
smaller if it lands first (video lightbox, off-canvas toggle, and stagger all reduce to
interaction actions). They do **not** hard-depend on it — each plan specifies its own
self-contained fallback — but running Plan 1 first avoids rework.

# Claude Memory - DesignSetGo

## v2.2 Codex review remediation (agent: codex-review-2026-04-18, commit: 37a491e0)

- **Priority 0 / CI fix**: `Plugin::maybe_upgrade()` is now `public` and hooked onto `admin_init` (no longer called directly in `__construct`). This prevents `FilterIndex::install()` from executing `require_once ABSPATH . 'wp-admin/includes/upgrade.php'` at phpstan analysis time.
- **#1 Slug→ID**: `src/blocks/query-filter/render.php` translates taxonomy slug values in `$dsgo_active_filters_by_key` to term IDs via `get_term_by()` before passing to `FilterIndex::count_for_options()`.
- **#2 Post-status gate**: `FilterIndex::reindex_object()` calls `get_post_status()` early; non-publish posts are removed and short-circuit via `remove_object()`. Covers meta/taxonomy hooks firing on drafts.
- **#3 Server-side registration**: `useFilterRegistration.js` deleted. `FilterIndexHooks::on_save_post()` now calls `register_filters_from_post_blocks()` → recursive `walk_blocks_for_filters()` on `parse_blocks()` output. Only published posts register filters.
- **#4a Infinite sentinel guard**: Single-page `totalPages < 2` guard moved BEFORE the `infinite` render path so single-page results never emit a sentinel.
- **#4b Infinite last-page teardown**: `loadMore` in view.js now removes `[data-dsgo-pagination="infinite"]` wrapper on last page (garbage-collects observer), not just the loadmore button.
- **#5 drop clears db_version**: `FilterIndexCLI::drop` also calls `delete_option('designsetgo_db_version')` so next `admin_init` reinstalls the table.
- **Tests**: 125 PHPUnit tests / 298 assertions all pass. 4 new tests in filter-index-test.php; 1 new test in filter-counts-test.php.

## Phase C — Infinite Scroll Bundle (agent: phase-c-2026-04-18)

### Implementation Summary
- **C1**: Added `paginationKind` enum (numbered/loadmore/infinite) + 3 new attrs (autoPauseAfter, sentinelOffsetPx, buttonLabelWhenPaused) to block.json. Created variations.js with `infinite-scroll` variation using `isActive: ['paginationKind']`. Updated index.js to call `registerBlockVariation`.
- **C2**: Created `src/blocks/query-pagination/components/InfiniteScrollControls.js` — 3 DsgoInspectorPanel.Item entries (NumberControl×2, TextControl×1). Uses eslint-disable comment *inside* the import block for `__experimentalNumberControl`. Updated edit.js with `PaginationPreview` sub-component (extracted to avoid no-nested-ternary lint error).
- **C3**: render.php now checks `paginationKind === 'infinite'` first (before totalPages guard). Emits sentinel div with `data-wp-init="callbacks.initInfiniteObserver"` + hidden button. IAPI context includes autoLoadCount=0, restUrl, nonce. Added `--infinite` modifier + sentinel styles to style.scss.
- **C4**: Added `callbacks.initInfiniteObserver` to the existing single `store('designsetgo/query', {...})` call. Uses `IntersectionObserver` with rootMargin offset. Fires `button.click()` (re-hidden via Promise.resolve microtask) to reuse the loadMore generator. Reduced-motion: reveals button, skips observer. Auto-pauses at threshold, reveals button, disconnects observer.
- **Tests**: 5 PHPUnit tests in `tests/phpunit/blocks/query/pagination-infinite-render-test.php`. All 109 query-block tests pass.
- **Commits**: `8bfd6911` (C1+C2), `d8b64aaa` (C3+C4+tests).

### Key Design Decisions
- `paginationKind` is a NEW attribute separate from the legacy `mode` attribute — both coexist for backwards compat. render.php resolves effective kind by checking paginationKind ≠ 'numbered' first, then falls back to mode.
- Infinite renders the sentinel even on single-page results (observer fires but finds no next page, ctx.autoLoadCount never increments).
- `eslint-disable-next-line` for `__experimentalNumberControl` must go INSIDE the import block (not before the import statement) to suppress the rule on the right line.
- `PaginationPreview` sub-component extracted from QueryPaginationEdit to satisfy `no-nested-ternary` lint rule.
- The `IntersectionObserver` no-undef lint error (line 385) is the same pre-existing pattern as HTMLElement/DOMParser elsewhere in view.js — codebase doesn't declare browser globals in eslint config.

## B3+B4 — Filter Counts + Intersection (agent: b3b4-2026-04-18)

### Implementation Summary
- **B3**: Added `showCounts` attr to query-filter block.json (default true). ToggleControl in Settings panel. render.php computes per-option counts via `FilterIndex::count_for_options()` for checkbox and select kinds when filter is registered and showCounts is true. CSS `.dsgo-query-filter__count` added to style.scss.
- **B4**: No new files — intersection works via existing $_GET overlay mechanism in `class-query.php::handle_render()` which overlays $_GET with incoming `params` payload before calling `designsetgo_query_render_region()`. Filter siblings re-render with updated $_GET so counts are always current. Added explanatory comment to render-helpers.php.
- **Tests**: `filter-counts-test.php` with 5 PHPUnit tests (group: query-block in class docblock, NOT file docblock — PHPUnit 9 ignores file-level @group). Total: 88 tests passing.
- **Commits**: `bf744d66` (B3), `3ce52fcb` (B4).
- **Key insight**: PHPUnit 9 requires @group annotation on the *class* docblock, not the file docblock.

## Task 14 — Query Filter Block (agent: task14-2026-04-18)

### Implementation Summary
- Block: `designsetgo/query-filter` with 6 variations (checkbox, select, search, sort, active, reset)
- render.php: Helper functions prefixed `designsetgo_query_filter_render_*`; all vars namespaced `$dsgo_filter_*` to avoid WP global conflicts
- view.js: Extended `store('designsetgo/query')` with 5 new actions (setFilter, setFilterDebounced, toggleFilter, removeActiveFilter, resetAll) + shared generator `dsgoQueryRefresh()` + async helper `dsgoQueryRefreshPlain()` for debounced search
- render-posts.php: Added `q` param direct handling (when `bindSearchTo` is empty) + `filter_<taxonomy>` → tax_query + `sort=orderby.DIR` → orderby/order
- PHPUnit: 38 tests pass (35 prior + 3 new in filter-server-test.php)
- Jest: 1525 tests pass (37 suites)

### Key Design Decisions
- Debounced search uses `dsgoQueryRefreshPlain()` (async/await) not the generator, because IAPI regular (non-generator) actions run synchronously, making `setTimeout` straightforward
- `yield*` (not `yield`) used for delegating to the shared `dsgoQueryRefresh` generator
- `$taxonomy` variable renamed to `$dsgo_filter_taxonomy` throughout render.php to avoid WordPress.WP.GlobalVariablesOverride
- `q` param direct handling added to render-posts.php in addition to the existing `bindSearchTo` attribute path

## Shape Dividers (Section Block)

### Design Decisions

1. **Positioning**: Shape dividers are positioned **inside** the section at `top: 0` / `bottom: 0` (not outside). Positioning outside the block boundary is bad practice and can cause overlap issues with adjacent content.

2. **Automatic Padding**: When a shape divider is enabled, the inner container (`dsgo-stack__inner`) automatically receives padding equal to the shape's height. This prevents content from overlapping the shapes while letting users adjust their own padding on top.

3. **Color Controls Location**: Shape divider colors appear in the **main Color panel** (InspectorControls group="color") alongside other color settings like Overlay, Hover Background, etc. The Shape Divider panels only handle shape selection, height, width, flip, and front options.

4. **Two Color Properties**:
   - **Shape Color**: The SVG fill color
   - **Background Color**: The color behind the shape (useful for transitions between sections)

### Files

- `src/blocks/section/components/ShapeDivider.js` - Renders the SVG shape
- `src/blocks/section/components/ShapeDividerControls.js` - Shape selection and settings (not colors)
- Color controls are in `edit.js` within `<InspectorControls group="color">`

### Shape Divider Theme Inheritance (agent: shape-divider-theme-inheritance-2026-07-01, JS core unit, commit c01f810d)

**Supersedes the SVG-based design above.** CSS layer (prior commits `88f98fa`, `b81ba13`) moved shape dividers to class-based CSS `mask-image` rendering — no inline `<svg>` in markup. This unit updated JS to match.

- `ShapeDivider.js` rewritten: renders a single empty `<div>` with classes `dsgo-shape-divider dsgo-shape-divider--{top|bottom} is-shape-{slug|inherit}` + optional `is-flip-x is-flip-y is-front`, and inline vars `--dsgo-shape-height`/`--dsgo-shape-width` (always) + `--dsgo-shape-fill`/`--dsgo-shape-band` (omitted when unset so CSS `var(..., fallback)` applies). Old props (`--dsgo-shape-offset/-color/-background/-gradient-dir`, `dsgo-shape-divider--front`) are gone.
- `save.js`/`edit.js`: `fillColor = explicit shapeDivider{Top,Bottom}Color via convertColorToCSSVar || sectionBackgroundColor` (never `sectionTextColor` anymore — that's now only used by `ShapeDividerControls`'s inspector preview swatch). `bandColor = explicit shapeDivider{Top,Bottom}BackgroundColor via convertColorToCSSVar` only, no fallback in JS (CSS provides `--wp--preset--color--base` fallback).
- `shapeDividerTop`/`shapeDividerBottom` attributes unchanged (still `string`, default `""`); value space gained `'inherit'` (theme-default). `'inherit'` is treated as "set" everywhere truthy checks are used (padding-clearing, `dsgo-stack--has-shape-divider` class) since it's a non-empty string.
- `ShapeDividerControls.js`: `'Theme default'` (`value: 'inherit'`) option spliced in as the 2nd option (right after "None") in the shared `ShapeDividerPanel`'s shape `SelectControl` — done locally in the component, NOT by mutating `getShapeDividerOptions()` in `utils/shape-dividers.js` (kept generic/reusable). `ShapePreview` sub-component silently renders nothing for `'inherit'` (no SVG mapping exists client-side for the theme default — that only resolves via CSS custom property) — acceptable, not a crash, out of scope to enhance.
- Color panel labels relabeled: "Top/Bottom Shape Color" → "Top/Bottom Shape Fill (default: section background)"; "Top/Bottom Shape Background" → "Top/Bottom Band Background (default: base)". `ColorGradientSettingsDropdown` settings items have no per-item `help` slot (only `label`), so the default-value hint was folded into the label text itself.
- `SHAPE_DIVIDERS` object and `deprecated.js` deliberately untouched (deprecation/migration is a later task).

**Jest test gotcha (important for future section-block tests):** `src/blocks/section/test/save.test.js` uses `createBlock`/`serialize`/`registerBlockType`/`setCategories` imported from `@wordpress/block-editor/node_modules/@wordpress/blocks` — NOT the top-level `@wordpress/blocks`. Reason: this repo's `@wordpress/block-editor` requires `@wordpress/blocks@^14.15.0` while the top-level package resolves to `13.10.0`, so npm nests a second copy. `save.js` imports `@wordpress/block-editor`, whose `useBlockProps.save()`/`useInnerBlocksProps.save()` read block-support metadata via `getBlockType()` against the NESTED registry. Registering the block on the top-level `@wordpress/blocks` instead leaves the nested registry empty → `useBlockProps.save()` throws (`Cannot read properties of undefined (reading 'align')`) → `serialize()` silently collapses to a self-closing comment (`<!-- wp:designsetgo/section {...} /-->`) with no error surfaced, which reads exactly like "the block didn't register" and can send you chasing the wrong bug. Also needed `setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }])` before `registerBlockType()`, since the `designsetgo` category isn't registered in the Jest env (only via PHP `block-categories` filters) and `registerBlockType()` silently rejects blocks with unknown categories too.

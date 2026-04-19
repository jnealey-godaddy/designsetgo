# Claude Memory - DesignSetGo

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

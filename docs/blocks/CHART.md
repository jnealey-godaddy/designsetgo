# Chart Block - User Guide

**Block name**: `designsetgo/chart`
**Version**: 1.0.0
**Category**: DesignSetGo
**Keywords**: chart, graph, data, bar, donut, statistics

## Overview

The **Chart Block** renders bar, line, and donut charts as inline SVG — no charting library, no frontend JavaScript. Data can be typed in by hand or pulled live from a post meta field, so the same chart can show different numbers per post in a query loop.

**Key Features:**
- Bar, line, and donut chart types, drawn as a single server-rendered SVG
- Manual data entry or a post meta field (`dataSource`) holding a JSON array of `{label, value}` rows
- Per-series colors, with theme-palette fallbacks
- Optional value labels, legend, and gridlines
- Prefix/suffix and thousands-grouping for every printed value
- Always emits a visually-hidden data table — this is the accessible, screen-reader and search-engine version of the chart, not a fallback
- Server-rendered (`render.php`): the editor preview and the frontend markup are produced by the same PHP, via `<ServerSideRender>`

## Quick Start

1. Insert the **Chart** block (Design category).
2. Choose a **Chart type**: Bar, Line, or Donut.
3. Leave **Data source** as "Enter data" and add rows with the **Add row** button, or switch it to "Post meta field" and enter a meta key.
4. Set colors per series from the **Color** panel (**Series**), and adjust height, legend, grid, and value formatting under **Style**.

## Settings & Configuration

### Settings

- **Chart type**: Bar, Line, or Donut (`chartType`, default `bar`). Donut hides the Legend/Grid toggles below because a donut has no axis.
- **Description**: Optional text (`label`) read by screen readers as the data table's `<caption>`.
- **Data source**: "Enter data" (`manual`) or "Post meta field" (`meta`) (`dataSource`).
  - **Data** (manual only): row-by-row editor — Label + numeric Value per row, with Add row / Remove row.
  - **Meta key** (meta only): the post meta key (`metaKey`) to read. The field must hold a JSON array of `{label, value}` objects (or an already-decoded PHP array in that shape). Uses `postId` from block context, so it works inside a query loop and reads the current item's post, not the block's own post.

### Style

- **Height**: 80–800px (`height`, default 240).
- **Legend**: Show/hide the legend (`showLegend`, default `true`). Hidden for donut charts only when every category name is already drawn on the axis; otherwise the toggle is unavailable because the legend is the only sighted route from a mark to its category.
- **Values**: Show/hide value labels on bars/points, or share-of-total labels on donut slices (`showValues`, default `true`).
- **Grid**: Show/hide horizontal gridlines and axis tick labels (`showGrid`, default `true`). Not available for donut.
- **Value prefix** / **Value suffix**: Text added before/after every printed value (`valuePrefix`, `valueSuffix`), e.g. `$` or `%`. On a donut, slice labels are always a share of the total and never take the prefix/suffix.
- **Group thousands**: Adds thousands separators, e.g. `1,234,567` (`groupThousands`, default `false`).

### Color

A **Series** dropdown in the sidebar's Color panel exposes one color control per data row (up to 6 slots when the source is post meta, since the actual rows aren't known until render). Colors are positional — setting series 2's color never shifts series 1 or 3. Unset series fall back to a 4-color theme palette (`--wp--preset--color--primary/secondary/tertiary/accent`, cycling if there are more series than colors).

### Block Supports

Anchor, `align: wide|full`, spacing (margin, padding), typography (font size, font family), and background/text color are available through the standard sidebar panels.

## Data Sources and Row Limits

- Rows must have a numeric `value`; rows without one are dropped silently.
- A hard cap (`designsetgo_chart_max_rows` filter, default 200) limits how many rows are drawn, regardless of source. Beyond the cap, the chart draws only the first 200 rows and the data table's caption discloses the true row count (e.g. "Showing the first 200 of 340 rows").
- A donut chart additionally drops rows with a value of zero or less — a negative can't be a positive share of a total, so those rows are removed from the plot, legend, *and* data table together rather than being misrepresented in one of them.
- Post-meta reads honor the same visibility gates as Block Bindings: protected meta keys are never read, and a private or password-protected post's meta is withheld unless the current user can read that post.

## Accessibility

- The `<svg>` itself is `aria-hidden="true"` with `role="presentation"` — it carries no accessible information of its own.
- A `<table class="screen-reader-text">` is always rendered alongside it, listing every row's label and formatted value. This table, not the SVG, is the chart's accessible representation, and it is what search engines and screen readers see.
- Color is never the only channel identifying a series: every series appears in the legend (when shown) and in the data table.
- Custom colors accept raw CSS (including `var()` references) but are run through a strict character allowlist that rejects anything that could break out of a `style` attribute (e.g. `;`, `url(`, `expression(`).

## Related Blocks

- **Icon** (`designsetgo/icon`) — see [ICON.md](ICON.md) for the icon library the plugin uses elsewhere; the Chart block does not use icons itself.
- **Progress Bar** (`designsetgo/progress-bar`) — see [PROGRESS-BAR.md](PROGRESS-BAR.md) for a single-value alternative when a full chart is more than the data needs.

## Developer Notes

- Fully dynamic: `save()` is not used (the block has no `save.js`); `render.php` is the single source of truth for both the editor preview (via `ServerSideRender`) and the frontend, so there is no JS/PHP geometry to keep in sync.
- Geometry (scaling, axis ticks, arc math) lives in `chart-geometry.php`; data resolution and the accessible table in `chart-data.php`; color/palette resolution in `chart-colors.php`.
- Covered by `tests/phpunit/chart-geometry-test.php`, `tests/phpunit/chart-render-test.php`, and Jest tests under `tests/unit/blocks/chart-*.test.js`.
- See [2026-08-16-chart-block.md](../plans/2026-08-16-chart-block.md) for the original implementation plan (architecture rationale for going server-rendered).

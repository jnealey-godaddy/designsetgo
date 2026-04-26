# Query Results Block

**Block name**: `designsetgo/query-results`
**Added in**: 2.1.0
**Category**: DesignSetGo

---

**Block family**: [Query (parent)](QUERY.md) · [Query Results](#) · [Query Pagination](QUERY-PAGINATION.md) · [Query Filter](QUERY-FILTER.md) · [Query Group Header](QUERY-GROUP-HEADER.md) · [No Results](QUERY-NO-RESULTS.md)

---

## Overview

The Query Results block is the required direct child of a [Dynamic Query](QUERY.md) block that renders the repeating item grid. It holds the item template — the set of inner blocks that define how each result looks — and owns the presentation attributes: columns, tag names, and group-by settings.

The reason this block was split out from the parent Query block is extensibility: by separating "what to query" (owned by the parent) from "how to lay out results" (owned by this block), the same query source and filter stack can power different output containers. When you insert a [Slider](SLIDER.md) or [Scroll Slides](SCROLL-SLIDES.md) block as a sibling of Query Results inside a Dynamic Query, those blocks can take over as the item host — iterating the same query items as slides or scroll panels — while the filters, pagination, and no-results blocks continue to work unchanged.

---

## Block Attributes

| Attribute | Type | Default | Notes |
|---|---|---|---|
| `tagName` | string | `"ul"` | Outer list element: `ul`, `ol`, or `div` |
| `itemTagName` | string | `"li"` | Per-item wrapper element: `li`, `div`, or `article` |
| `columns` | number | `1` | Desktop column count |
| `columnsTablet` | number | `0` | Tablet column count; `0` inherits desktop value |
| `columnsMobile` | number | `0` | Mobile column count; `0` defaults to 1 |
| `firstItemColumnSpan` | number | `1` | Column span for the first item (magazine/featured layouts) |
| `firstItemRowSpan` | number | `1` | Row span for the first item |
| `groupBy` | object | `null` | `{field, key}` — partition items; see [Group-by](QUERY.md#group-by) |
| `layoutVariant` | string | `""` | CSS class modifier (e.g. `magazine`, `avatar-grid`); applied as `is-layout-<variant>` |

Column counts are emitted as CSS custom properties (`--dsgo-query-columns`, `--dsgo-query-columns-tablet`, `--dsgo-query-columns-mobile`) on the grid wrapper, driving a responsive CSS Grid layout defined in the block's stylesheet.

---

## Inspector Controls

### Results layout panel

**Tag name** — outer wrapper element type.

**Item tag** — per-item wrapper element type.

**Columns (Desktop / Tablet / Mobile)** — responsive column grid. Tablet and mobile inherit desktop when left at 0 / 1.

**First item column span / row span** — creates a featured-item layout where the first result spans multiple columns and/or rows.

**Group by** — enables grouping; sub-controls appear for `field` (`taxonomy`, `meta`, `date`) and `key` (taxonomy slug, meta key name, or date precision `Y` / `Y-M` / `Y-M-D`).

**Layout variant** — free-text field that adds an `is-layout-<variant>` CSS class to the grid wrapper for scoped per-variation styles.

---

## Item Template

The inner blocks of Query Results define the per-item template. During server rendering:

1. The parent Query block runs the query and iterates results
2. For each item, it renders the inner blocks with the item's `postId` / `postType` (for posts) or `designsetgo/currentItemId` / `designsetgo/currentItemType` (for users/terms) injected into block context
3. The rendered HTML is wrapped in the `itemTagName` element with class `dsgo-query__item`
4. The accumulated HTML is stashed in a PHP global and picked up by this block's `render.php`

This architecture means the parent runs one `WP_Query` and populates the per-request state registry before any child block renders, so sibling pagination and no-results blocks can read the correct `totalPages` and `totalItems` without re-executing the query.

---

## Editor Preview Model

The editor preview uses a "first item editable, rest server-rendered" model:

- **Item 0**: editable `InnerBlocks` — the block template the author designs. Changes here are reflected immediately via the standard editor experience.
- **Items 1..N**: read-only HTML fetched from the same PHP render pipeline used on the frontend. This ensures exact editor-to-frontend parity: fluid spacing presets, block supports padding, style bindings, and dynamic tags all resolve identically to what visitors see.

While the server render for items 1..N is in flight, a `BlockPreview` fallback renders those positions using the current inner blocks as a best-guess approximation.

When `source` is `manual` or `current`, the live preview is skipped (manual has no query to run; current binds to the viewed post context which doesn't exist in the editor canvas).

---

## Group-by and the Query Group Header

When `groupBy` is set, the Query Results block partitions results before rendering. The [Query Group Header](QUERY-GROUP-HEADER.md) block — when present anywhere in the item template — is extracted from the template, rendered once per group with `designsetgo/groupLabel` and `designsetgo/groupValue` context, and prepended to that group's items. Each group is wrapped in:

```html
<section class="dsgo-query-group" data-dsgo-group-value="...">
  <!-- group header HTML -->
  <!-- item HTML -->
</section>
```

If `groupBy` is set but no Query Group Header block is found in the template, the editor shows an informational notice and the frontend renders items without group wrappers.

---

## Non-grid Item Hosts

When a [Slider](SLIDER.md) or [Scroll Slides](SCROLL-SLIDES.md) block is placed as a direct child of the parent Dynamic Query block alongside a Query Results block, it acts as the item host instead. The Query block detects the first registered item host in its child list and renders items into that host's template. The item wrapper (`itemTagName`) is set to `"none"` automatically so the host's own slide element is not double-wrapped.

A third-party block can register itself as an item host via the `designsetgo_query_item_host_block_names` filter (see [Developer Hooks](QUERY.md#developer-hooks)).

---

## CSS Loading Skeletons

During filter and pagination refreshes, the item grid container receives `aria-busy="true"`. The block stylesheet uses this attribute to show a CSS-only loading skeleton — a set of animated placeholder shapes that indicate content is loading — without requiring any JavaScript animation.

```css
[aria-busy="true"] .dsgo-query__item { /* skeleton styles */ }
```

The skeleton is purely CSS: no extra DOM nodes, no JavaScript timers.

---

## Frontend Markup

The block renders as:

```html
<ul class="dsgo-query-results dsgo-query-results--source-posts is-layout-magazine"
    style="--dsgo-query-columns:3; --dsgo-query-columns-tablet:2; --dsgo-query-columns-mobile:1;"
    data-dsgo-query-results-role="container"
    data-dsgo-query-id="abc123">
  <li class="dsgo-query__item"><!-- item template --></li>
  <!-- … -->
</ul>
```

The `data-dsgo-query-results-role="container"` and `data-dsgo-query-id` attributes are used by `view.js` to scope DOM operations (appending new items, aria-busy toggling) to the correct grid when multiple Query blocks exist on one page.

---

## Accessibility

- The grid element uses the `tagName` chosen by the author; `ul` (default) and `ol` are semantic list elements that expose the item count to assistive technology
- When infinite scroll is active, the grid receives `role="feed"` and each item gets `aria-setsize` / `aria-posinset` attributes stamped by `dsgoStampFeedPositions()`
- `aria-busy="true"` is set during refreshes; screen readers are notified when loading completes

---

## Related Blocks

- [Dynamic Query](QUERY.md) — the required parent container
- [Query Group Header](QUERY-GROUP-HEADER.md) — renders once per group inside grouped results
- [Query Pagination](QUERY-PAGINATION.md) — page navigation sibling
- [Query Filter](QUERY-FILTER.md) — filter controls sibling
- [No Results](QUERY-NO-RESULTS.md) — zero-result fallback sibling
- [Slider](SLIDER.md) — alternative item host for carousel-style output
- [Scroll Slides](SCROLL-SLIDES.md) — alternative item host for scroll-driven output

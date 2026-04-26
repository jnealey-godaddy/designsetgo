# Query Filter Block

**Block name**: `designsetgo/query-filter`
**Added in**: 2.1.0
**Category**: DesignSetGo

---

**Block family**: [Query (parent)](QUERY.md) · [Query Results](QUERY-RESULTS.md) · [Query Pagination](QUERY-PAGINATION.md) · [Query Filter](#) · [Query Group Header](QUERY-GROUP-HEADER.md) · [No Results](QUERY-NO-RESULTS.md)

---

## Overview

The Query Filter block adds interactive filtering controls to a [Dynamic Query](QUERY.md). One block instance renders one filter control. Insert multiple Query Filter blocks — one for each filter dimension you need — at any position within the Query block's tree.

The block has six variations (`filterKind`). All of them work by updating URL parameters and triggering an Interactivity API refresh, so the results update without a full page reload. When JavaScript is unavailable, every filter falls back to a `<form method="get">` submission that reloads the page with the updated URL.

Filter state is reflected in the URL so filtered views are shareable and indexed.

---

## Block Attributes

| Attribute | Type | Default | Notes |
|---|---|---|---|
| `filterKind` | string | `"checkbox"` | `checkbox` \| `select` \| `search` \| `sort` \| `active` \| `reset` |
| `taxonomy` | string | `"category"` | Taxonomy slug; used by `checkbox` and `select` kinds |
| `paramName` | string | `"filter_category"` | URL parameter name this filter reads and writes |
| `label` | string | `""` | Visible label, legend, or button text |
| `placeholder` | string | `""` | Input placeholder; used by `search` kind |
| `sortOptions` | array | `[Newest, Oldest, A–Z, Z–A]` | Option definitions for the `sort` kind; each entry has `value` and `label` |
| `showCounts` | boolean | `true` | Append `(N)` counts to checkbox and select options |
| `orientation` | string | `"vertical"` | `vertical` \| `horizontal` — checkbox list layout |
| `filterStyle` | string | `"default"` | `default` \| `pill` \| `underline` — visual style of checkbox items |

---

## Filter Variations

### Checkbox (`filterKind: "checkbox"`)

Renders a `<fieldset>` + `<legend>` with one checkbox per term in the taxonomy. Supports multi-value selection: checking multiple terms adds each value to the `paramName[]` URL array. Unchecked checkboxes are always visible so users can see all available options.

**Per-option counts** — when `showCounts` is true and the filter has been indexed, each term label includes a `(N)` count showing how many results match. Counts are *intersection-aware*: they reflect the number of results that match this term combined with all currently active filters across other dimensions, not just the term in isolation.

**Orientation** — `vertical` (default) or `horizontal` row. The `pill` and `underline` styles automatically switch to horizontal.

**Style variants**:
- `default` — standard checkboxes with visible controls
- `pill` — native checkbox hidden; label styled as a pill button
- `underline` — native checkbox hidden; label styled as an underlined tab

All style variants keep the `<input type="checkbox">` in the DOM so keyboard users and screen readers toggle filters the same way.

### Select (`filterKind: "select"`)

A `<select>` dropdown with one option per term plus a "All" default. Single-value: selecting a term sets `?paramName=slug`; selecting "All" removes the parameter. Supports per-option counts from the filter index.

### Search (`filterKind: "search"`)

A text input with a submit button. The search value sets `?paramName=value`. Submitting with an empty field removes the parameter. The query's `bindSearchTo` attribute on the parent Dynamic Query block can point to the same parameter so the search term drives the query's full-text search.

Default input role is `search`; the wrapper `<form>` carries `role="search"`.

### Sort (`filterKind: "sort"`)

A `<select>` dropdown for changing result order. Each option value is a `field.DIRECTION` string (e.g. `date.DESC`, `title.ASC`). The parent query reads the `sort` URL parameter and splits it into `orderBy` and `order` at render time.

Default sort options provided by the block:
- Newest (`date.DESC`)
- Oldest (`date.ASC`)
- A–Z (`title.ASC`)
- Z–A (`title.DESC`)

Custom sort options can be defined in the Inspector.

### Active filters (`filterKind: "active"`)

Renders a strip of "chip" links showing each currently active filter value. Clicking a chip removes that one filter from the URL. Each chip is an `<a href="…">` whose href pre-encodes the removal URL — the no-JS fallback navigates to that URL directly.

Only renders when at least one filter parameter (`filter_*`, `q`, or `sort`) is present in the URL. Renders nothing when no filters are active.

### Reset (`filterKind: "reset"`)

Renders a single button link that navigates to the current URL with all filter parameters (`filter_*`, `q`, `sort`, `paged`, `page`) removed. The button is an `<a href="…">` for no-JS fallback.

---

## Inspector Controls

**Filter kind** — select control for the six variations.

**Taxonomy** — visible for `checkbox` and `select`; taxonomy slug the filter operates on.

**URL parameter name** — the `paramName` this block reads from and writes to the URL.

**Label** — optional visible label. For `checkbox` it becomes the `<legend>` text. For `search` it becomes a `<label>` linked to the input. For `reset` it overrides the button text.

**Placeholder** — visible for `search`; input placeholder.

**Sort options** — visible for `sort`; repeatable list of option label/value pairs.

**Show counts** — toggle for `checkbox` and `select`; requires the filter to be indexed.

**Orientation** — toggle for `checkbox`; switches between vertical list and horizontal row.

---

## Filter Index and Counts

Per-option counts are powered by the `{$wpdb->prefix}dsgo_query_filter_index` table, which is maintained automatically on `save_post`, taxonomy change, and meta change events. The index stores one row per post × filter-option combination, enabling fast intersection-aware count queries.

For counts to appear, a filter must be registered. Filters are registered automatically when a Query Filter block is saved; they can also be registered programmatically via the `designsetgo_query_registered_filters` filter.

The filter index can be rebuilt from **Settings → DesignSetGo → Dynamic Query** or via WP-CLI:

```
wp dsgo query index rebuild
wp dsgo query index rebuild-filter
wp dsgo query index status
```

---

## URL State and Interactivity API

On every filter interaction, the Interactivity API store:

1. Builds a new URL by updating the relevant parameter
2. Strips both `paged` and `page` parameters (resets to page 1)
3. Posts to `POST /designsetgo/v1/query/render` with the new URL
4. Swaps the entire `dsgo-query-region` element's `innerHTML` — this updates the item grid, pagination, no-results block, and active-filter chips in one operation
5. Calls `history.replaceState` to update the browser URL without a page reload

**Post-swap DOM handling**: after a refresh, the DOM inside the region is replaced with server-rendered HTML. The Interactivity API's directive bindings no longer fire on replaced elements. A document-level delegated event listener handles all subsequent filter interactions on the swapped DOM, preventing silent no-ops.

---

## Frontend Behavior

- All filter forms wrap in `<form method="get">` for no-JS fallback
- `prefers-reduced-motion` has no special handling for filters (motion is limited to item list swaps which don't animate)
- Counts update with every filter change to reflect the new intersection state
- The filter block renders even if its taxonomy has no terms (for `checkbox` / `select`); the block simply outputs nothing in that case

---

## Accessibility

- `checkbox` variation: terms are wrapped in `<label>` elements; multiple checkboxes are grouped in `<fieldset>` + `<legend>` when a label is provided
- `search` variation: `<form role="search">`; input has an explicit `<label>` or `aria-label="Search"`
- `sort` variation: `<select>` has an explicit `<label>` or `aria-label="Sort"`
- Active filter chips: each chip is an `<a role="button">` with a screen-reader-only "Remove category: value" label
- Count badges are plain text appended to the option label; no separate `aria-label` is needed
- No-JS submit button inside `<noscript>` ensures progressive enhancement for checkbox and select forms

---

## Related Blocks

- [Dynamic Query](QUERY.md) — the parent container block
- [Query Results](QUERY-RESULTS.md) — the item grid that gets refreshed
- [Query Pagination](QUERY-PAGINATION.md) — pagination resets automatically on filter change
- [No Results](QUERY-NO-RESULTS.md) — shown when filters produce zero results

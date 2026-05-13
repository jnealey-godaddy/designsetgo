# Dynamic Query Block

**Block name**: `designsetgo/query`
**Added in**: 2.1.0
**Category**: DesignSetGo

---

**Block family**: [Query (parent)](#) · [Query Results](QUERY-RESULTS.md) · [Query Pagination](QUERY-PAGINATION.md) · [Query Filter](QUERY-FILTER.md) · [Query Group Header](QUERY-GROUP-HEADER.md) · [No Results](QUERY-NO-RESULTS.md)

---

## Overview

The Dynamic Query block is the container block for the query family. It owns all query configuration — source, filters, ordering, and pagination — and exposes those settings to its child and sibling blocks via block context. Actual item rendering is handled by the [Query Results](QUERY-RESULTS.md) child block (or by a Slider / Scroll Slides host that takes over rendering).

The block is fully server-rendered: PHP builds the item list once and passes the pre-rendered HTML to the child host block, while sibling blocks (filters, pagination, no-results) read from a per-request state registry so the query runs exactly once per page load.

**Key Features:**
- Six query sources: Posts, Users, Terms, Manual picks, Current archive, Relationship
- Taxonomy, meta, date, search, author, and offset filtering
- Multi-level AND/OR taxonomy and meta clause groups
- Group-by partitioning (taxonomy / meta / date precision)
- Nested loop support via `designsetgo/parentItem` context
- Live editor preview with an editable first item and server-rendered read-only items
- Template picker on first insert (8 pre-built starting points)
- Template export and import via Inspector buttons
- Style bindings (`dsgoStyleBinding`) for dynamic inline CSS
- ItemList schema.org markup for Posts queries
- Interactivity API: filter state syncs to URL parameters
- Query Monitor debug panel when QM is active

---

## Block Attributes

| Attribute | Type | Default | Notes |
|---|---|---|---|
| `queryId` | string | `""` | Auto-generated unique ID; used to coordinate with sibling blocks |
| `source` | string | `"posts"` | `posts` \| `users` \| `terms` \| `manual` \| `current` \| `relationship` |
| `postType` | string | `"post"` | Post type slug; applies only when `source` is `"posts"` |
| `perPage` | number | `6` | Items per page (1–48) |
| `offset` | number | `0` | Skip this many items from the start of the result set |
| `orderBy` | string | `"date"` | `date` \| `title` \| `menu_order` \| `rand` \| `comment_count` \| `meta_value` \| `meta_value_num` |
| `orderByMetaKey` | string | `""` | Meta key used when `orderBy` is `meta_value` or `meta_value_num` |
| `order` | string | `"DESC"` | `ASC` \| `DESC` |
| `search` | string | `""` | Static keyword search (same as `s` in WP_Query) |
| `bindSearchTo` | string | `""` | URL parameter name whose value overrides `search` at render time |
| `author` | array | `[]` | Array of author IDs |
| `excludeCurrent` | boolean | `false` | Exclude the post currently being viewed |
| `ignoreSticky` | boolean | `true` | Ignore sticky post elevation |
| `manualIds` | array | `[]` | Post IDs for `source: "manual"` (comma-separated in the Inspector, stored as integers) |
| `taxQuery` | object | `{relation:"AND",clauses:[]}` | Taxonomy filter; clauses can be nested groups |
| `metaQuery` | object | `{relation:"AND",clauses:[]}` | Meta filter; clauses can be nested groups |
| `dateQuery` | object | `{relation:"AND",clauses:[]}` | Date filter clauses |
| `relationshipField` | string | `""` | Meta key or ACF field on the parent post holding related IDs; used with `source: "relationship"` |
| `relationshipFallback` | string | `"empty"` | `empty` \| `all` \| `parent` — what to render when no related items exist |
| `emitSchema` | boolean | `true` | Emit `ItemList` schema.org JSON-LD for Posts queries |
| `showPlaceholder` | boolean | `true` | Show the template picker when no inner blocks exist |

---

## Inspector Controls

### Settings panel

**Source** selects what the query iterates:

| Value | What it does |
|---|---|
| `posts` | Standard `WP_Query` over any post type; exposes full taxonomy / meta / date filtering |
| `users` | Iterates `WP_User_Query` results; skips taxonomy/meta filter panels |
| `terms` | Iterates taxonomy terms; skips taxonomy/meta filter panels |
| `manual` | Renders a hand-picked list of post IDs; use the Manual IDs field |
| `current` | Inherits the current archive's query (useful inside a template part) |
| `relationship` | Reads a relationship field on the parent post and iterates the referenced posts via `post__in` |

**Post type** — visible only when `source` is `posts`. Lists all viewable registered post types.

**Relationship field** and **When no related items** — visible only when `source` is `relationship`. The field accepts a meta key or ACF field name that holds one or more post IDs (supports arrays, serialized arrays, comma-separated strings, or plain integers). The fallback controls what happens when that field is empty: render nothing (`empty`), fall back to all posts (`all`), or render the parent item itself (`parent`).

**Items per page** — range control, 1–48.

**Offset** — number input; shifts the query start position without affecting pagination total.

**Order by** and **Order direction** — standard WP_Query `orderby` and `order`.

**Template I/O** — Export and Import buttons for saving and restoring the full block tree as a JSON file. Export downloads `query-template-<id>.json`; Import calls `POST /designsetgo/v1/query/template`, validates the payload against the block type registry, generates a fresh `queryId`, and replaces the current block.

### Taxonomy filters panel

Displayed only when `source` is `posts`. Allows any number of taxonomy clauses, each with:

- **Taxonomy** dropdown (only taxonomies registered for the selected post type)
- **Terms** token field (type term names to add; backed by `getEntityRecords`)
- **Operator** — `IN`, `NOT IN`, or `AND` (require all terms)
- **Include child terms** toggle (default on)

Multiple clauses share a top-level **Relation** (`AND` / `OR`). Clauses can themselves be nested groups at any depth, forming a multi-level AND/OR structure that maps to WP_Query's `tax_query`.

### Meta filters panel

Displayed only when `source` is `posts`. Clause fields:

- **Key** — meta key name
- **Compare** — one of `=`, `!=`, `>`, `>=`, `<`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `EXISTS`, `NOT EXISTS`
- **Type** — `Text`, `Numeric`, or `Date`
- **Value** — hidden for `EXISTS` / `NOT EXISTS`

Multiple clauses share a **Relation** (`AND` / `OR`) and can be nested into groups.

### Date filters panel

Displayed only when `source` is `posts`. Each clause has:

- **Column** — `post_date`, `post_modified`, `post_date_gmt`, or `post_modified_gmt`
- **Mode** — `After`, `Before`, or `Between`
- **After** / **Before** date fields — accept ISO dates (`YYYY-MM-DD`) or PHP relative expressions (`-30 days`, `today`, `first day of last month`)

### Advanced query panel

- **Search text** — static keyword filter
- **Bind search to URL param** — when set, the named URL parameter overrides the static search at render time (e.g. entering `q` here makes `?q=keyword` functional)
- **Exclude current post** — automatically excludes the viewed post from results
- **Ignore sticky posts** — on by default
- **Manual post IDs** — visible only when `source` is `manual`; comma-separated list of post IDs

---

## Query Sources in Detail

### `posts` (default)

Standard WordPress `WP_Query`. Supports all filtering controls. Schema.org `ItemList` markup is emitted when `emitSchema` is true.

### `users`

Iterates `WP_User_Query` results. Bindings in the item template receive the user ID via `designsetgo/currentItemId` and `designsetgo/currentItemType: "user"`. Taxonomy and meta filter panels are hidden.

### `terms`

Iterates taxonomy terms. Bindings receive the term ID via `designsetgo/currentItemId` and `designsetgo/currentItemType: "term"`. Taxonomy and meta filter panels are hidden.

### `manual`

Renders a fixed list of post IDs entered in the Inspector. Items appear in the order entered. Useful for curated spotlight sections.

### `current`

Inherits the current archive query from WordPress. Intended for use inside Full Site Editing archive templates where a `WP_Query` is already in context.

### `relationship`

Reads a relationship field — a meta key or ACF field — on the parent item (determined by block context: the post being edited, or the outer query's current item in a nested loop). The field value can be stored as an array of integers, a serialized PHP array, a comma-separated string, or a plain integer. The block normalizes all these formats, then fetches the referenced posts via `post__in`.

Configure the **Relationship field** and the **When no related items** fallback in the Settings panel.

---

## Group-by

When `groupBy` is set on the [Query Results](QUERY-RESULTS.md) child block, items are partitioned into labelled sections before rendering. Each group emits a `<section class="dsgo-query-group">` wrapper. The [Query Group Header](QUERY-GROUP-HEADER.md) block, when present in the item template, renders once per group with `designsetgo/groupLabel` and `designsetgo/groupValue` context filled in.

Three grouping fields are available (configured on Query Results):

| Field | `key` values | Description |
|---|---|---|
| `taxonomy` | Taxonomy slug | Groups by term; multi-term posts appear in all matching groups |
| `meta` | Meta key name | Groups by meta value string |
| `date` | `Y`, `Y-M`, or `Y-M-D` | Groups by year, year + month, or full date |

---

## Nested Loops

A Query block inside another Query block automatically inherits the outer loop's current item. The outer item is available inside the inner query as:

- Block context key: `designsetgo/parentItem` (an object with `postId` and `postType`)
- PHP global during render: `$GLOBALS['designsetgo_parent_stack']` — an ordered array of `{postId, postType}` objects, one per nesting level

This enables patterns like "for each author, show their latest posts" or "for each product category, show related products" within a single page.

---

## Editor Preview

After inserting a template, the editor shows a live preview of query results:

- **Posts source**: uses `useEntityRecords` from `@wordpress/core-data` for instant in-editor data
- **Users / Terms sources**: fetches from `/designsetgo/v1/query/preview` REST endpoint
- **Item 0**: editable `InnerBlocks` — the template the author designs
- **Items 1..N**: server-rendered read-only HTML via the same PHP pipeline as the frontend, ensuring pixel-accurate parity

A result-count badge ("N items") appears in the block header when the query is valid.

---

## Template Picker

On first insert (before any inner blocks exist), the block shows a template picker with 8 starting points:

| Template | Description |
|---|---|
| Minimal | Bare skeleton: Results, No Results, Pagination |
| Blog index | Magazine cards with search + sort + numbered pagination |
| Team directory | Circular avatars in a centered grid |
| Testimonials | Pull-quote cards with load-more |
| Portfolio | Image tiles with category filter and load-more |
| Related posts | Compact horizontal rows; excludes current post |
| Featured carousel | Posts rendered inside a Slider block |
| Post spotlight | Posts rendered inside Scroll Slides |

---

## Style Bindings

The `dsgoStyleBinding` attribute maps CSS property names to a binding source and key. Values are injected as inline styles on the block root at render time.

Security: values containing `url(`, `expression(`, `javascript:`, `data:`, or the characters `;`, `{`, `}` are rejected before injection.

---

## Interactivity API and URL State

Filter interactions and load-more pagination are powered by the WordPress Interactivity API (`@wordpress/interactivity`). Filter state is reflected in URL parameters so results are shareable and navigable:

| Parameter | Used by |
|---|---|
| `q` | Search filter |
| `sort` | Sort filter |
| `filter_<taxonomy>` | Checkbox and select taxonomy filters |

When JavaScript is unavailable, filter controls fall back to standard `<form method="get">` submission for a full-page reload with the same parameters.

---

## ItemList Schema.org

When `emitSchema` is true (the default) and `source` is `"posts"`, the block emits a JSON-LD `ItemList` schema.org block in the page `<head>`. Each list item carries its position and permalink. Disable by setting `emitSchema` to false in the Inspector's Advanced panel.

---

## Admin Dashboard and WP-CLI

A dashboard page at **Settings → DesignSetGo → Dynamic Query** provides controls for rebuilding the filter index and managing registered filters.

WP-CLI commands:

```
wp dsgo query index rebuild          # Rebuild the full filter index
wp dsgo query index rebuild-filter   # Rebuild index for a single filter
wp dsgo query index status           # Show current index status
wp dsgo query index drop             # Drop the filter index table
```

---

## Query Monitor

When the Query Monitor plugin is active, a "DSGo (N)" panel appears in the QM output showing per-render query args, found-posts count, execution duration, and the SQL statement for each Dynamic Query on the page.

---

## Developer Hooks

### PHP filters

**`designsetgo_query_args`**
Fires before every `WP_Query` run. Use this to modify query arguments globally.

```php
add_filter( 'designsetgo_query_args', function( $args, $atts, $context ) {
    // e.g. exclude a specific post type
    return $args;
}, 10, 3 );
```

**`designsetgo/query/{queryId}/args`**
Same as above but fires only for the query with the matching `queryId`. Useful for targeting a specific query block without affecting others.

```php
add_filter( 'designsetgo/query/my-query-id/args', function( $args, $atts, $context ) {
    return $args;
}, 10, 3 );
```

**`designsetgo_query_registered_filters`**
Filter the list of registered filter definitions used by the filter index and count queries.

**`designsetgo_query_url_params`**
Extend the URL parameters that the Interactivity API store reads and syncs. By default the allowed list is `['q', 'sort']` plus any key prefixed with `filter_`.

```php
add_filter( 'designsetgo_query_url_params', function( $params ) {
    $params[] = 'my_custom_param';
    return $params;
} );
```

### PHP functions

**`designsetgo_query_partition_items( array $post_ids, array $group_spec ): array`**
Partitions an ordered list of post IDs into labelled groups. `$group_spec` is an array with keys `field` (`taxonomy`, `meta`, or `date`) and `key` (taxonomy slug, meta key, or date precision `Y`/`Y-M`/`Y-M-D`). Returns an array of groups, each with `label`, `value`, and `ids` keys.

### PHP globals

**`$GLOBALS['designsetgo_parent_stack']`**
An ordered array of item context objects `{postId, postType}` representing the nesting chain of currently-rendering query items. Index 0 is the outermost loop's current item. Available during any `render_block` hook or block render callback fired inside a query item.

---

## Accessibility

- The outer wrapper carries `aria-live="polite"` and is the Interactivity API refresh target; the screen-reader status element announces updated result counts after filter interactions
- Loading state uses `aria-busy="true"` on the item container (CSS-only skeleton)
- Filter controls use `<form method="get">` with proper `<label>`, `<fieldset>`, and `<legend>` elements
- No-JS fallback: all filters perform a full page reload, preserving all functionality without JavaScript

---

## Related Blocks

- [Query Results](QUERY-RESULTS.md) — the required child block that renders iterated items
- [Query Pagination](QUERY-PAGINATION.md) — numbered, load-more, and infinite-scroll pagination
- [Query Filter](QUERY-FILTER.md) — checkbox, select, search, sort, active-filters, and reset controls
- [Query Group Header](QUERY-GROUP-HEADER.md) — group label rendered once per group
- [No Results](QUERY-NO-RESULTS.md) — content shown when the query returns zero items
- [Slider](SLIDER.md) — can act as an item host for carousel-style query output
- [Scroll Slides](SCROLL-SLIDES.md) — can act as an item host for scroll-driven query output

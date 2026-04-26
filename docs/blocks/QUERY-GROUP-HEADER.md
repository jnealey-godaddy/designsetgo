# Query Group Header Block

**Block name**: `designsetgo/query-group-header`
**Added in**: 2.1.0
**Category**: DesignSetGo

---

**Block family**: [Query (parent)](QUERY.md) · [Query Results](QUERY-RESULTS.md) · [Query Pagination](QUERY-PAGINATION.md) · [Query Filter](QUERY-FILTER.md) · [Query Group Header](#) · [No Results](QUERY-NO-RESULTS.md)

---

## Overview

The Query Group Header block renders once per group when the [Query Results](QUERY-RESULTS.md) block has group-by enabled. It is placed inside the item template (inside the Query Results inner blocks), and at render time the server extracts it from the template, renders it with the current group's label and value injected as block context, and prepends it to the items in that group.

Without group-by enabled on the parent Query Results block, this block renders as an ordinary InnerBlocks container and has no special behavior.

---

## Block Attributes

| Attribute | Type | Default | Notes |
|---|---|---|---|
| `tagName` | string | `"header"` | HTML wrapper element: `header`, `div`, or `section` |

---

## Block Context

The block consumes three context keys set by the parent Query and its render pipeline:

| Context key | Type | Description |
|---|---|---|
| `designsetgo/queryId` | string | The parent query's unique ID |
| `designsetgo/groupLabel` | string | Human-readable group label (term name, meta value, or formatted date) |
| `designsetgo/groupValue` | string | Machine-readable group value (term slug, meta value, or date string) |

---

## Inspector Controls

**HTML element** — choose the wrapper tag: `header` (default), `div`, or `section`.

Typography, spacing, and color supports are available in the Styles panel via WordPress's native block supports.

---

## Displaying the Group Label

To display the current group's label inside the block, you have two options:

### Core Block Bindings

Any block inside Query Group Header that supports the WordPress 6.9 Block Bindings API can be bound to the `designsetgo/groupLabel` or `designsetgo/groupValue` context values. For example, bind a Heading block's content to the group label:

In the editor, select a Heading block inside the group header, open the Bindings panel, and connect `content` to the `designsetgo/groupLabel` context source.

### DesignSetGo Dynamic Tags

The DesignSetGo Dynamic Tags toolbar picker (available on any block's rich text) can bind text content to `designsetgo/groupLabel` or `designsetgo/groupValue` directly from the inline toolbar. This works on any block that accepts inline text, including Paragraph and Heading.

---

## How Grouped Rendering Works

When `groupBy` is configured on Query Results, the server-side render pipeline:

1. Runs the query and collects all post IDs in order
2. Calls `designsetgo_query_partition_items()` to bucket the IDs into groups (by taxonomy term, meta value, or date)
3. For each group, renders the Query Group Header block(s) found in the template with the group's `label` and `value` injected as context
4. Renders the item template for each ID in the group
5. Wraps the header + items in `<section class="dsgo-query-group" data-dsgo-group-value="...">`

Posts with multiple taxonomy terms (when grouping by taxonomy) appear in all matching groups. Posts with no matching term land in an "Uncategorized" bucket.

The editor preview shows a visual grouping with a placeholder group label. An informational notice appears in the editor if group-by is set but no Query Group Header block is found in the template.

---

## Frontend Markup

For a taxonomy-grouped query, one group renders as:

```html
<section class="dsgo-query-group" data-dsgo-group-value="photography">
  <header class="dsgo-query-group-header wp-block-designsetgo-query-group-header">
    <!-- inner blocks, e.g. a heading bound to groupLabel -->
    <h3>Photography</h3>
  </header>
  <li class="dsgo-query__item"><!-- item --></li>
  <li class="dsgo-query__item"><!-- item --></li>
</section>
```

---

## Usage Example

A typical grouped blog index with year headings:

1. Configure Query Results with `groupBy: { field: "date", key: "Y" }`
2. Place a Query Group Header block at the top of the item template
3. Inside the header, insert a Heading (H2)
4. Bind the heading's content to `designsetgo/groupLabel` via Block Bindings or Dynamic Tags

At render time each year appears as an H2 heading above its group of posts.

---

## Accessibility

- The wrapper element defaults to `<header>`, which carries implicit ARIA landmark semantics inside a `<section>`; change to `<div>` if the landmark is not desired
- Group sections use `<section class="dsgo-query-group">` on the server, which is a landmark element when it has an accessible name; add an `aria-labelledby` pointing to the group header heading if you want screen readers to enumerate sections

---

## Related Blocks

- [Dynamic Query](QUERY.md) — parent container; group-by is configured via Query Results
- [Query Results](QUERY-RESULTS.md) — owns the `groupBy` attribute that activates this block
- [No Results](QUERY-NO-RESULTS.md) — shown when the grouped query has no items

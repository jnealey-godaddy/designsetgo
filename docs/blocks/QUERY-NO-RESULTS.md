# No Results Block

**Block name**: `designsetgo/query-no-results`
**Added in**: 2.1.0
**Category**: DesignSetGo

---

**Block family**: [Query (parent)](QUERY.md) · [Query Results](QUERY-RESULTS.md) · [Query Pagination](QUERY-PAGINATION.md) · [Query Filter](QUERY-FILTER.md) · [Query Group Header](QUERY-GROUP-HEADER.md) · [No Results](#)

---

## Overview

The No Results block renders its content only when the parent [Dynamic Query](QUERY.md) returns zero items. Place it as a sibling of [Query Results](QUERY-RESULTS.md) anywhere inside the Query block's tree — it can appear before or after the results.

The block is a free-form InnerBlocks container: add any blocks inside it — a heading, a paragraph, a link back to the full archive, a [Query Filter](QUERY-FILTER.md) reset button — to create the empty-state message and any helpful recovery actions.

---

## Block Attributes

This block has no configurable attributes beyond standard WordPress block supports (color, typography, spacing).

---

## When It Renders

The block reads the parent query's `totalItems` from the per-request state registry after the query has run. It renders its authored content when `totalItems === 0`. It renders nothing (returns empty) when `totalItems > 0`.

**Two scenarios produce `totalItems === 0`:**

1. **The base query has no matching posts** — for example, a query for a custom post type that has no published entries
2. **Active filters intersect to zero** — the user has applied one or more [Query Filter](QUERY-FILTER.md) selections that together match no posts; because every filter refresh re-runs the query and updates the state registry, the No Results block responds correctly to filter-driven zero states as well as base-query zero states

---

## Editing Model

In the editor, the block always shows its inner content so authors can design the empty state without needing to temporarily remove posts from the database. The conditional render logic runs only on the frontend.

To preview how the block will look when there are no results, save the page and temporarily change the query to target a post type or taxonomy with no published posts.

---

## Inspector Controls

No block-specific controls. The standard WordPress color, typography, and spacing supports are available in the Styles panel.

---

## Usage Examples

A minimal empty-state message:

```
[No Results block]
  [Heading] "No posts found"
  [Paragraph] "Try adjusting your filters or search for something else."
  [Query Filter — reset kind] "Clear all filters"
```

A more helpful empty state that links back to the archive:

```
[No Results block]
  [Heading] "Nothing here yet"
  [Paragraph] "We couldn't find any results. Try a broader search."
  [Buttons block]
    [Button] "View all posts" → /blog
```

---

## Frontend Markup

When rendered (zero results), the block wraps its authored content in a `<div>` with `class="dsgo-query-no-results"` plus any native-supports classes (color, spacing) the author applied:

```html
<div class="dsgo-query-no-results wp-block-designsetgo-query-no-results">
  <!-- authored inner blocks -->
</div>
```

When there are results the block outputs nothing — no wrapper element, no hidden markup.

---

## Accessibility

- The block outputs nothing when results exist, so there is no need to hide it with `display:none` or `aria-hidden`
- The authored content renders as normal flow content; use appropriate heading levels and ensure any links or buttons have descriptive labels

---

## Related Blocks

- [Dynamic Query](QUERY.md) — the parent container; owns the query that determines totalItems
- [Query Results](QUERY-RESULTS.md) — the sibling that renders when totalItems > 0
- [Query Filter](QUERY-FILTER.md) — filters can produce zero results; pair with a reset kind to give users a recovery path
- [Query Pagination](QUERY-PAGINATION.md) — also hides itself when totalItems is 0 (totalPages < 2)

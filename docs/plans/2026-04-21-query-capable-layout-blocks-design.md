# Query-capable layout blocks — design

**Date:** 2026-04-21
**Branch:** `claude/query-block-onboarding-placeholder`
**Status:** Design validated, pending implementation

## Problem

The Dynamic Query family (`designsetgo/query` + siblings) today renders results through one item host: `designsetgo/query-results`, which lays items out as a grid/list. Other layout blocks (`designsetgo/slider`, `designsetgo/scroll-slides`, and eventually more) are structurally identical — chrome + N authored item children — and would benefit from consuming query data. We need a reusable contract so any "chrome + N items" block can be a query item host without duplicating data-config UI or query plumbing.

## Decision

**Composition shape:** inside-query. Authors compose

```
designsetgo/query > designsetgo/<layout> > designsetgo/<item>
```

The query container owns data config (post type, filters, sort, date/tax/meta queries, relationship, grouping). The layout block owns chrome (arrows/dots/autoplay for slider, pinned nav for scroll-slides). The item block is the iteration template.

**Detection:** implicit via block context. A layout block is in query mode iff `designsetgo/queryId` is present in its inherited context. No new attribute, no toggle. In-query = bound; anywhere else = authored (today's behavior, unchanged).

**Template rule:** exactly one child when in query mode. Editor enforces via `allowedBlocks` + a prune effect on first detection. Server render treats that one child as the per-item template.

## Contract

### Server render handoff

Split `designsetgo_query_render()` into two helpers in [src/blocks/query/render-helpers.php](../../src/blocks/query/render-helpers.php):

- **`designsetgo_query_render_items( $query_id, $template_block, $context )`** — reusable core. Runs the resolved query (posts / users / terms / relationship), pushes `$GLOBALS['designsetgo_parent_stack']` per iteration, renders the template block N times via `designsetgo_query_render_item()`, returns concatenated HTML with no outer wrapper.
- **`designsetgo_query_render()`** — kept as-is for `query-results`. Refactored to call `designsetgo_query_render_items()` internally and add its existing `.dsgo-query-results` wrapper + sidecar around it. Zero behavior change.

Layout blocks' `render.php` shape:

```php
if ( ! empty( $block->context['designsetgo/queryId'] ) ) {
    $template   = $block->parsed_block['innerBlocks'][0] ?? null;
    $items_html = designsetgo_query_render_items(
        $block->context['designsetgo/queryId'],
        $template,
        $block->context
    );
    return designsetgo_slider_wrap_chrome( $attributes, $items_html, $block );
}
// else: authored branch — render innerBlocks as today
```

**Sidecar.** Extract `[data-dsgo-blobs-for]` emission into `designsetgo_query_emit_blobs_sidecar()` so every query-capable layout block calls it once after its chrome wrapper. Keeps IAPI filter/pagination flows identical whether the host is `query-results`, `slider`, or `scroll-slides`.

### Editor preview

Extract the existing `query-results/edit.js` preview logic into a shared hook:

**`useQueryPreviewItems({ queryId, template, count })`** in `src/hooks/useQueryPreviewItems.js`. Returns `{ items, status }`. Internally handles the posts (`useEntityRecords`) / users / terms / relationship split and parent-stack emulation so nested bindings preview correctly.

Per-layout edit.js pattern:

```jsx
const boundToQuery = !!queryId;
if ( ! boundToQuery ) {
    // authored branch — useInnerBlocksProps on children, unchanged
}
const { items } = useQueryPreviewItems({ queryId, template, count: perPage });
return (
    <div {...blockProps}>
        <SliderChrome {...chromeProps}>
            {items.map((item, i) => i === 0
                ? <InnerBlocks /* editable template */ />
                : <BlockPreview blocks={template} context={item.previewAttributes} />
            )}
        </SliderChrome>
    </div>
);
```

`query-results/edit.js` refactors to consume the same hook; no visual diff.

### Sibling behavior

The query container permits filter / pagination / group-header / no-results as siblings of the item host. With slider/scroll-slides as the host:

- **Filters, sort, search, active, reset** — unchanged. IAPI `setFilter` rebuilds slide set; slider/scroll-slides re-init on DOM mutation.
- **Numbered pagination** — works, but UX clashes with slider's own arrows. No guard; author's call.
- **Load-more** — works well ("swipe to end, click load more, slides append").
- **Infinite (v2.2)** — recommended pairing for slider. IntersectionObserver on sentinel inside slider container triggers `loadMore` on last visible slide.
- **Group-header (v2.3)** — supported with scroll-slides (group heading = pinned section). Not supported with slider; documented, not blocked.
- **No-results** — works identically.

Net-new UI: **none.** No inspector toggles, no attributes. All behavior is author composition.

## Migration

Zero breaking changes. The query-mode branch is purely additive — entered only when `designsetgo/queryId` context is present. Existing authored sliders and scroll-slides keep identical save output, same view.js, same attributes. No deprecations.

**Per participating layout block, one block.json change:**

```json
"usesContext": [
    "designsetgo/queryId",
    "designsetgo/currentItemId",
    "designsetgo/currentItemType"
]
```

**Query container `allowedBlocks`** extends from `[query-results, query-filter, query-pagination, query-no-results, query-group-header]` to also permit `[slider, scroll-slides]` as valid item hosts.

## Build order

1. **Extract shared primitives.** `designsetgo_query_render_items()` (PHP) and `useQueryPreviewItems()` (JS). Refactor `query-results` to consume them. Confirm zero visual diff against current renders. This is the foundation; everything after reuses it.
2. **Wire slider as first participant.** Add `usesContext`, add query-mode branch to `edit.js` + `render.php`, enforce single-template child via `allowedBlocks` on `slide` + prune effect. Validate across posts, users, terms, relationship.
3. **Wire scroll-slides.** Mechanical repeat of step 2. If it's not mechanical, the abstraction is leaking — fix the primitive, not the second block. This is the payoff test.
4. **Docs.** Update [`.claude/docs/QUERY-BLOCK-GUIDE.md`](../../.claude/docs/QUERY-BLOCK-GUIDE.md) with the "making a layout block query-capable" recipe so future blocks can be added by pattern-matching.

## Out of scope for v1

- **Mixed mode** — leading static children + template marker. Real future ask; deferred.
- **Non-list layout blocks** — sticky-sections, marquee, timeline don't have a clean "one template child" shape yet. Not in scope until the pattern is proven on slider + scroll-slides.
- **Cross-block authoring aids** beyond the prune effect (drag-and-drop conversion, one-click "make dynamic", etc.).

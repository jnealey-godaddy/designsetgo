# Dynamic Query Block — Developer Guide

The `designsetgo/query` block is a server-rendered, filterable post/user/term loop block. It replaces hand-rolled PHP loops with a fully native Gutenberg experience: choose a data source, drop in inner blocks to define the per-item template, then publish. The block family ships four registered blocks and six ready-made variations.

**Block family**

| Block name | Role |
|---|---|
| `designsetgo/query` | Container — owns the query, renders the list |
| `designsetgo/query-pagination` | Numbered links or load-more button |
| `designsetgo/query-filter` | Filter controls (6 variations) |
| `designsetgo/query-no-results` | Fallback shown when a query returns zero items |

---

## Quick start

1. Open the inserter and search **"Dynamic Query"**. Pick a variation (see below) or the bare block.
2. In the sidebar **Settings** panel, choose a **Source** (Posts is the default).
3. Configure **Post type**, **Per page**, **Order**, and any taxonomy or meta conditions.
4. The per-item template lives directly inside the block as inner blocks. WordPress core template-part blocks (`core/post-title`, `core/post-featured-image`, `core/post-date`, etc.) work out of the box for Posts. For Users and Terms the binding source changes — see §6.
5. Optionally insert `designsetgo/query-pagination` and `designsetgo/query-filter` siblings on the same page and bind them to the same `queryId`.
6. Publish.

### Starter variations

All six appear in the block inserter:

| Variation key | What it shows |
|---|---|
| `blog-index` | Latest posts, 9-per-page grid, date + excerpt |
| `team` | Menu-order CPT grid, image + title + excerpt |
| `testimonials` | Quote layout, designed for ACF binding |
| `portfolio` | Project showcase, image + title |
| `related-posts` | Random 3 excluding current post |
| `events` | Upcoming events sorted ASC by date |

---

## Sources

| Source | Backs | Iterates over | Per-item context |
|---|---|---|---|
| `posts` | `WP_Query` | Any post type (default: `post`) | `postId`, `postType` (core blocks Just Work) |
| `manual` | `WP_Query` | Exactly the IDs listed in `postIds` attribute | same as posts |
| `current` | `WP_Query` | The single post / page currently being viewed | same as posts |
| `users` | `WP_User_Query` | WordPress users filtered by role/capability | `designsetgo/currentItemId`, `designsetgo/currentItemType = "user"` |
| `terms` | `WP_Term_Query` | Taxonomy terms | `designsetgo/currentItemId`, `designsetgo/currentItemType = "term"` |

The server-side dispatch lives in `render-helpers.php` → `designsetgo_query_render()`. It picks the correct renderer (`render-posts.php`, `render-users.php`, `render-terms.php`) from `$attributes['source']`.

---

## The Query ID

Every `designsetgo/query` block is seeded with a stable `queryId` attribute (a short slug like `q-a3f2`). The value flows down to sibling blocks via block context:

```
designsetgo/query  →  providesContext: { "designsetgo/queryId": "queryId" }
designsetgo/query-pagination  →  usesContext: ["designsetgo/queryId"]
designsetgo/query-filter      →  usesContext: ["designsetgo/queryId"]
designsetgo/query-no-results  →  usesContext: ["designsetgo/queryId"]
```

Siblings use `queryId` to stamp their output HTML with `data-dsgo-query-id` so the Interactivity API store knows which container to refresh.

**When to set it manually:** if you need two queries on one page that should share a filter (unusual), set both blocks to the same `queryId`. Otherwise leave it auto-generated.

**Frontend data contract:** the block's outer wrapper carries `data-dsgo-query-id="{queryId}"`. A hidden sibling `<div data-dsgo-blobs-for="{queryId}">` holds two `<script>` tags with the serialized `attributes` and `innerBlocks` JSON blobs; the IAPI load-more and filter actions read them to reconstruct REST request payloads without re-parsing the editor HTML.

---

## Filter hooks

Both hooks fire for every source (Posts, Users, Terms).

```php
/**
 * Modify WP_Query / WP_User_Query / WP_Term_Query args for every Dynamic Query.
 *
 * @param array  $args    Query args about to be passed to the query class.
 * @param array  $atts    Block attributes (source, postType, perPage, orderBy, …).
 * @param array  $context render context (query_id, page, params, …).
 */
add_filter( 'designsetgo_query_args', function ( $args, $atts, $context ) {
    return $args;
}, 10, 3 );

/**
 * Modify args only for a specific queryId.
 * Fires *after* designsetgo_query_args; scoped hook wins.
 *
 * @param array  $args
 * @param array  $atts
 * @param array  $context
 */
add_filter( 'designsetgo/query/related/args', function ( $args, $atts, $context ) {
    return $args;
}, 10, 3 );
```

The scoped hook name is `designsetgo/query/{queryId}/args`. Set the block's `queryId` to a human-readable slug (e.g. `related`) so the hook name stays readable.

### Recipe — related posts by shared taxonomy

```php
add_filter( 'designsetgo/query/related/args', function ( $args, $atts, $context ) {
    $categories = wp_get_post_categories( get_the_ID(), array( 'fields' => 'ids' ) );
    if ( $categories ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $categories,
            ),
        );
    }
    // Exclude the current post.
    $args['post__not_in'] = array( get_the_ID() );
    return $args;
}, 10, 3 );
```

Set the block's `queryId` to `related` and `excludeCurrent` to true in the Settings panel.

### Recipe — exclude posts from the past week

```php
add_filter( 'designsetgo_query_args', function ( $args, $atts, $context ) {
    if ( ( $atts['source'] ?? 'posts' ) !== 'posts' ) {
        return $args;
    }
    $args['date_query'] = array(
        array(
            'before' => '-7 days',
            'inclusive' => true,
        ),
    );
    return $args;
}, 10, 3 );
```

### Recipe — only posts with a featured image

```php
add_filter( 'designsetgo_query_args', function ( $args, $atts, $context ) {
    if ( ( $atts['source'] ?? 'posts' ) !== 'posts' ) {
        return $args;
    }
    $args['meta_query'][] = array(
        'key'     => '_thumbnail_id',
        'compare' => 'EXISTS',
    );
    return $args;
}, 10, 3 );
```

---

## Block Bindings

WP 6.5+ Block Bindings let you connect inner block attributes (e.g. `core/paragraph` content) to per-item values without writing a custom block.

Two binding sources are always available:

| Source | When active | What it returns |
|---|---|---|
| `designsetgo/post-meta` | Always | Raw postmeta value (scalar); returns `null` for password-protected, non-viewable, or protected-meta keys |
| `designsetgo/acf` | Only when `function_exists('get_field')` is true (ACF active) | Scalar ACF field value; returns `null` for array/object fields |

Both sources mirror WP core's `core/post-meta` security gates: password-protected posts, non-publicly-viewable posts, and protected meta keys return `null`.

### Block comment syntax

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"designsetgo/post-meta","args":{"key":"subtitle"}}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

Or with ACF:

```html
<!-- wp:heading {"level":3,"metadata":{"bindings":{"content":{"source":"designsetgo/acf","args":{"key":"job_title"}}}}} -->
<h3></h3>
<!-- /wp:heading -->
```

The `key` arg is passed through `sanitize_text_field()`. Note that `sanitize_key()` is intentionally NOT used because it lowercases the value — ACF field names and postmeta keys are case-sensitive.

### Using in the editor

In the block editor, open the bound block's toolbar → **Bind** → pick **Post meta (DesignSetGo)** or **ACF Field (DesignSetGo)** from the source list, then enter the meta key. The editor shows a placeholder; the value renders on the frontend inside the query loop.

---

## URL params for filters

`designsetgo/query-filter` sub-blocks generate `<form method="get">` markup so they work without JavaScript. With JavaScript, the Interactivity API intercepts submissions and refreshes only the list.

| Param | Applied to | Effect |
|---|---|---|
| `q` | Posts source | Adds `s` to WP_Query args |
| `sort` | Posts source | Format: `orderby.DIR` e.g. `date.DESC`, `title.ASC` |
| `filter_<taxonomy>` | Posts source | Narrow by a single term slug |
| `filter_<taxonomy>[]` | Posts source | Narrow by multiple term slugs (OR logic by default) |

Only the whitelisted params above are extracted from `$_GET` by `designsetgo_query_extract_params_from_request()`. Extend the whitelist via the `designsetgo_query_url_params` filter if you need custom params.

### query-filter variations

| Variation | `filterKind` | Default `paramName` | HTML output |
|---|---|---|---|
| `checkbox` | `checkbox` | `filter_category` | `<input type="checkbox" name="filter_cat[]">` per term |
| `select` | `select` | `filter_category` | `<select name="filter_cat">` with term options |
| `search` | `search` | `q` | `<input type="search" name="q">` |
| `sort` | `sort` | `sort` | `<select name="sort">` with configured options |
| `active` | `active` | _(none)_ | Chip list of active filters with remove links |
| `reset` | `reset` | _(none)_ | "Clear all" link pointing to URL without filter params |

Set `paramName` in the block's Settings panel. For taxonomies, prefix the slug with `filter_` (e.g. `filter_genre`, `filter_category`).

---

## Pagination

### Numbered pagination

Renders standard `<a href="?paged=N">` links. SEO-indexable. Uses WordPress `paginate_links()` output wrapped in `<nav role="navigation" aria-label="...">`.

The variation is selected via the `paginationKind` attribute (`numbered` vs `loadmore`). Set in the **Settings** panel of `designsetgo/query-pagination`.

### Load-more

Renders a button. On click the Interactivity API `loadMore` action fires:

1. Reads the blobs (attributes + innerBlocks JSON) from `[data-dsgo-blobs-for]`.
2. `POST`s to `designsetgo/v1/query/render` with `page = currentPage + 1`.
3. Parses the returned HTML and appends `.dsgo-query__item` nodes to the container.
4. Moves focus to the first newly-appended item (or its first naturally-focusable child).
5. Hides the button once `nextPage >= totalPages`.

No JavaScript: without JS, the button is a standard form submit to `?paged=N`.

---

## Interactivity API store

Store namespace: **`designsetgo/query`**

```js
import { store } from '@wordpress/interactivity';

store( 'designsetgo/query', {
    actions: { /* extend here */ },
} );
```

### Built-in actions

| Action | Trigger | What it does |
|---|---|---|
| `loadMore` | Load-more button click | Fetches next page and appends items |
| `setFilter` | Select / radio change | Sets or clears a single URL param, refreshes |
| `setFilterDebounced` | Search input `input` event | Debounced (250 ms) version of `setFilter` |
| `toggleFilter` | Checkbox change | Appends or removes a value from a URL array param, refreshes |
| `removeActiveFilter` | Active-filter chip click | Navigates to the chip's href (minus `paged`), refreshes |
| `resetAll` | Reset button click | Navigates to clean URL, refreshes |

All filter actions call the internal `dsgoQueryRefresh()` generator which:

1. Reads blobs from `[data-dsgo-blobs-for]`.
2. Collects `filter_*`, `q`, `sort` params from the new URL.
3. `POST`s to `designsetgo/v1/query/render` with `page: 1`.
4. Replaces the list `innerHTML` with the server response.
5. Updates the browser URL via `history.replaceState`.

### Extending the store

Add your own actions in a block's `view.js`. The `store()` call merges with the existing namespace:

```js
import { store, getContext } from '@wordpress/interactivity';

store( 'designsetgo/query', {
    actions: {
        myCustomAction() {
            const ctx = getContext();
            // ctx.queryId is available
        },
    },
} );
```

---

## REST endpoint

```
POST /wp-json/designsetgo/v1/query/render
```

**Authentication:** requires a logged-in user with the `read` capability + a valid `X-WP-Nonce` header (`wp_rest` action).

**Request body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `queryId` | string | yes | The block's queryId attribute |
| `attributes` | object | yes | Full block attributes object |
| `page` | integer | no (default 1) | Page number to render |
| `innerBlocks` | string | no | Serialized innerBlocks HTML |
| `params` | object | no | URL params (`q`, `sort`, `filter_*`) |

**Response (JSON):**

```json
{
    "html": "<ul class=\"wp-block-designsetgo-query …\">…</ul>",
    "totalPages": 4,
    "totalItems": 34
}
```

The endpoint calls `designsetgo_query_render()` — the same function used by the first-paint `render.php`. The only difference is that first-paint passes a `wrapper_attrs` string computed by `get_block_wrapper_attributes()`; the REST path passes `null`, which causes the helper to omit those classes from the wrapper (they already exist on the first-paint container). This means REST responses produce items-only HTML intended to be inserted into an already-rendered container.

---

## Performance notes

- **No index table in v1.** Per-option counts on filter controls are live `WP_Query` calls at render time. For small-to-medium result sets this is fine; for large catalogs (10 k+ posts) plan for v2's index table or add a page caching layer.
- **One `WP_Query` per block per render.** If you place 4 query blocks on one page you get 4 queries. Combine with a page cache (Cloudflare, WP Rocket, LiteSpeed) for archive pages where filters don't change per-user.
- **Cache invalidation.** Flush the page cache on post publish/update and on filter-toggle AJAX responses. WP Rocket's "Purge cache when post updated" covers the publish side; the IAPI filter refresh uses `history.replaceState` so the browser URL changes and cache keys differ automatically.
- **`ignoreSticky` defaults to `true`.** Sticky posts are excluded from query results unless you uncheck this in the Advanced panel (or set `$args['ignore_sticky_posts'] = false` in a filter hook).

---

## Accessibility defaults

- List wrapper renders as `<ul>` + `<li>` by default. The `tagName` and `itemTagName` attributes let you switch to `<ol>` / `<div>` when semantics differ.
- The container carries `aria-live="polite"` so screen readers announce new content after load-more or filter refresh.
- `aria-busy="true"` is toggled on the container during AJAX fetches and on the load-more button during its fetch.
- Focus moves to the first newly-appended item (or its first naturally-focusable child — `<a>`, `<button>`, `<input>`) after a load-more append. A temporary `tabindex="-1"` is stamped only when no naturally-focusable child exists, and it is removed on blur.
- Numbered pagination renders inside `<nav role="navigation" aria-label="…">`.
- Filter forms are `<form method="get">` — keyboard-operable without JS.
- The IAPI store respects `prefers-reduced-motion` for any transition CSS; no JS-driven animations run when the user prefers reduced motion.

---

## SEO

- **ItemList JSON-LD** is emitted for Posts source when the block attribute `emitSchema` is `true` (the default). Disable it in the Advanced panel if you have a conflicting schema plugin.
- **Numbered pagination** uses plain `<a href>` links — Googlebot indexes page 2+.
- **Load-more** is not indexable (page 2+ requires a button click). Use numbered pagination for content you want indexed beyond page 1.
- **`canonical` / `rel=next|prev`**: Dynamic Query does not emit these tags. Leave them to the theme's `<head>` output or an SEO plugin (Yoast, Rank Math). If you need them on paginated archives, ensure the theme emits them based on `get_query_var('paged')`.

---

## v2.3 Recipes

### Recipe — show posts linked via an ACF/Meta relationship field

An outer post stores an ACF relationship field (`related_posts`) containing an array of post IDs. Configure the Query block to iterate exactly those posts:

1. Query block → Settings → **Source**: Relationship.
2. **Relationship field**: `related_posts` (the ACF or postmeta key that stores the IDs array).
3. **Fallback when field is empty**: choose `Render no items` (or `all` / `parent` depending on your intent).

The server renderer reads the nearest parent-stack item's `related_posts` meta value, passes the IDs through `post__in`, and falls back according to `relationshipFallback`.

```html
<!-- wp:designsetgo/query {
    "queryId":"related-products",
    "source":"relationship",
    "relationshipField":"related_posts",
    "relationshipFallback":"empty"
} -->
<ul class="wp-block-designsetgo-query">

    <!-- wp:core/post-title {"level":3} /-->
    <!-- wp:core/post-featured-image /-->

</ul>
<!-- /wp:designsetgo/query -->
```

---

### Recipe — hide a "Featured" badge on non-featured posts

Use the Visibility panel on any inner block to show it only when a meta condition is met. No custom PHP required.

1. Select the inner block you want to conditionally show (e.g. a Heading that says "Featured").
2. Open **Block Settings** → **Visibility** panel.
3. Add a rule: **Type** = Meta, **Key** = `featured`, **Op** = equals, **Value** = `1`.
4. Leave operator at `AND` (default).

The `dsgoVisibility` attribute is registered on every block via the `blocks.registerBlockType` filter in `src/extensions/visibility/filters.js`. The server evaluator is `DesignSetGo\BlockVisibility::matches()`.

```html
<!-- wp:heading {
    "level":3,
    "dsgoVisibility":{
        "operator":"AND",
        "rules":[{"type":"meta","op":"equals","key":"featured","value":"1"}]
    }
} -->
<h3 class="wp-block-heading">Featured</h3>
<!-- /wp:heading -->
```

---

### Recipe — group posts by category with custom group headers

1. Query block → Settings → **Group by**: Taxonomy, **Group taxonomy**: `category`.
2. Inside the Query block, insert a **Query Group Header** block (`designsetgo/query-group-header`). It renders once per group, before that group's items.
3. Inside the Group Header, add a Heading and bind its content to the group context key `designsetgo/groupLabel` (the term name) or `designsetgo/groupCount` (item count).

```html
<!-- wp:designsetgo/query {
    "queryId":"by-category",
    "source":"posts",
    "groupBy":{"field":"taxonomy","key":"category"}
} -->
<ul class="wp-block-designsetgo-query">

    <!-- wp:designsetgo/query-group-header -->
    <div class="wp-block-designsetgo-query-group-header">
        <!-- wp:heading {"level":2,"metadata":{"bindings":{"content":{"source":"designsetgo/post-meta","args":{"key":"designsetgo/groupLabel","scope":"self"}}}}} -->
        <h2></h2>
        <!-- /wp:heading -->
    </div>
    <!-- /wp:designsetgo/query-group-header -->

    <!-- wp:core/post-title {"level":3} /-->

</ul>
<!-- /wp:designsetgo/query -->
```

Server partitioning logic lives in `designsetgo_query_partition_items()` inside `render-helpers.php`.

---

### Recipe — nested loops: for each post, show its 3 latest sibling posts

Outer Query iterates a post list; the inner Query, placed inside the item template, fetches that post's category siblings.

1. Outer Query: source = Posts, any post type.
2. Inside the item template, insert an inner Query block: source = Posts, `perPage = 3`, give it a unique `queryId` (e.g. `siblings`).
3. Use the scoped filter to restrict the inner query to the outer post's primary category. The outer post's context is available via `$GLOBALS['designsetgo_parent_stack']` during any `render_block` hook that fires inside a query item.

```php
add_filter( 'designsetgo/query/siblings/args', function ( $args, $atts, $context ) {
    // $GLOBALS['designsetgo_parent_stack'] is an ordered list (outermost first).
    $stack   = $GLOBALS['designsetgo_parent_stack'] ?? array();
    $parent  = end( $stack ); // nearest ancestor item
    if ( empty( $parent['postId'] ) ) {
        return $args;
    }

    $cats = wp_get_post_categories( $parent['postId'], array( 'fields' => 'ids' ) );
    if ( $cats ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $cats,
            ),
        );
    }
    $args['post__not_in'] = array( $parent['postId'] ); // exclude the parent post itself
    return $args;
}, 10, 3 );
```

You can also read the parent context via bindings using `scope: 'parent'`:

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"designsetgo/post-meta","args":{"key":"subtitle","scope":"parent"}}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

---

## v2.3 Extension points

### `scope` arg on binding sources

Both `designsetgo/post-meta` and `designsetgo/acf` accept a `scope` arg that controls which item in the parent stack is read:

| `scope` value | Reads from |
|---|---|
| `'self'` (default) | Current item |
| `'parent'` | Nearest enclosing query item (one level up) |
| `'root'` | Outermost item in the stack |

The stack itself is `$GLOBALS['designsetgo_parent_stack']` — an ordered list of item contexts pushed by `designsetgo_query_render_item()` in `render-helpers.php`.

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"designsetgo/post-meta","args":{"key":"company_name","scope":"parent"}}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

### `designsetgo_visibility_rule` filter

Register custom visibility rule types in PHP. Return `true` to show the block, `false` to hide it, or `null` to fall through to the next rule handler.

```php
/**
 * @param bool|null $match   Current match result (null = not yet determined).
 * @param array     $rule    Rule definition from the dsgoVisibility attribute.
 * @param array     $context Render context: postId, postType, currentItemId, etc.
 * @return bool|null
 */
add_filter( 'designsetgo_visibility_rule', function ( $match, $rule, $context ) {
    if ( $rule['type'] !== 'my_custom_type' ) {
        return $match; // pass through
    }
    // Example: show only to admins
    return current_user_can( 'manage_options' );
}, 10, 3 );
```

Built-in rule types (`meta`, `taxonomy`, `index`, `auth`) are evaluated in `DesignSetGo\BlockVisibility::matches()`. Custom types hook in after the built-ins.

### `groupBy` attribute shape

```json
{
    "field": "taxonomy",
    "key": "category"
}
```

| `field` | `key` meaning |
|---|---|
| `"taxonomy"` | Taxonomy slug (e.g. `"category"`, `"genre"`) |
| `"meta"` | Postmeta key whose value is used as the group identifier |
| `"date"` | Date part: `"year"`, `"month"`, `"year-month"` |

Group context keys injected into the `designsetgo/query-group-header` inner blocks:

| Key | Value |
|---|---|
| `designsetgo/groupLabel` | Human-readable group name (term name, meta value, or formatted date) |
| `designsetgo/groupKey` | Raw group identifier (term slug, meta value, or ISO date fragment) |
| `designsetgo/groupCount` | Number of items in this group |

### `$GLOBALS['designsetgo_parent_stack']`

An ordered list (outermost first) of item-context arrays. Each entry mirrors the per-item context shape:

```php
[
    'postId'   => 42,
    'postType' => 'post',
    // users/terms: 'currentItemId', 'currentItemType'
]
```

The stack is pushed before each item is rendered and popped after. It is safe to read during any `render_block` or `designsetgo_query_args` hook that fires inside a query item. The scoped filter hook name `designsetgo/query/{queryId}/args` fires with the stack already populated, so the nearest ancestor is always available.

---

## Known v1 limits

| Limit | Notes |
|---|---|
| No nested loops | One level of `designsetgo/query` only; nesting a query inside a query loop is not supported. |
| No filter option counts | Per-option result counts require a live `WP_Query` per option; no index table ships in v1. |
| No PHP escape-hatch UI | There is no "custom WP_Query args" text field; use the `designsetgo_query_args` filter hook instead. |
| No headless REST parity | The REST endpoint serves the Interactivity-API / AJAX use-case. It is not a general-purpose headless content API (no JSON representation of posts; HTML only). |
| ACF auto-detected only | `designsetgo/acf` binding source auto-registers only when `function_exists('get_field')` is true. Meta Box, Pods, and JetEngine are v2. |
| `sort` param posts-only | Sorting via URL param only affects Posts queries; Users and Terms ignore `sort` in v1. |

---

## Where to look in the code

```
src/blocks/query/
├── index.js                 Block registration + variations
├── edit.js                  Editor component
├── save.js                  Save output (empty — dynamic block)
├── view.js                  Interactivity API store
├── variations.js            6 starter variations
├── render.php               First-paint server render (entry point)
├── render-helpers.php       designsetgo_query_render() dispatcher
├── render-posts.php         Posts / manual / current source renderer
├── render-users.php         Users source renderer
├── render-terms.php         Terms source renderer
├── components/
│   ├── QuerySourcePanel.js  Source + post-type + orderby controls
│   ├── TaxQueryBuilder.js   Taxonomy filter UI
│   ├── MetaQueryBuilder.js  Meta query UI
│   └── AdvancedPanel.js     ignoreSticky, offset, wrapper tags
└── hooks/
    ├── useQueryId.js        Seeds stable queryId from clientId
    └── useQueryPreview.js   Live count badge in the editor

src/blocks/query-pagination/
├── index.js
├── edit.js
├── save.js
└── render.php

src/blocks/query-filter/
├── index.js
├── edit.js
├── save.js
├── render.php
├── variations.js            6 filter kind variations
└── components/
    └── FilterPreview.js

src/blocks/query-no-results/
├── index.js
├── edit.js
├── save.js
└── render.php

includes/blocks/
├── class-query.php          Controller — REST endpoint + shared render() wrapper
└── class-query-bindings.php Registers designsetgo/post-meta + designsetgo/acf sources
```

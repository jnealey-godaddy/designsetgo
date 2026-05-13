# Block Bindings

**Since**: 2.1.0 (native attribute support); helpers since 2.4.0 per source `@since` tags

DesignSetGo integrates with the WordPress 6.9 Block Bindings API on two levels: it opts its own block attributes into the native binding pipeline, and it ships a set of ready-made binding sources plus public PHP helpers so third-party plugins can register their own sources with the same security model.

See also:
- [REST API Reference](REST-API-REFERENCE.md) — Dynamic Query and llms.txt endpoints
- [MARKDOWN-CONTENT-NEGOTIATION.md](MARKDOWN-CONTENT-NEGOTIATION.md) — per-URL Markdown feature

---

## Native Block Bindings Support

**Source**: `includes/class-block-bindings-support.php`

DesignSetGo blocks participate in the WordPress 6.9 `block_bindings_supported_attributes` filter so their attributes can be driven by any Block Bindings source (DesignSetGo's own sources, WP core's `core/post-meta`, or any custom source) via the editor's Connections panel.

**Safe on WordPress < 6.9** — `add_filter()` registers the callback but the filter is never invoked on older versions, so no runtime check is required.

### Covered Blocks and Attributes

| Block | Bindable Attributes |
|-------|---------------------|
| `designsetgo/heading-segment` | `content` |
| `designsetgo/breadcrumbs` | `homeText`, `prefixText` |
| `designsetgo/query-pagination` | `labelLoadMore`, `labelLoading`, `buttonLabelWhenPaused` |

`content` on `designsetgo/heading-segment` is an HTML-sourced attribute, so WordPress's HTML API rewrites the rendered markup automatically. The Breadcrumbs and Query Pagination attributes flow into `render_callback` via `$block->attributes` — no extra plumbing is required.

### Extending the Attribute Map

Use the `designsetgo_block_bindings_supported_attributes` filter to add bindable attributes to any DesignSetGo block. The filter receives and must return an `array<string, string[]>` (block name → attribute names).

```php
add_filter(
    'designsetgo_block_bindings_supported_attributes',
    function ( array $map ): array {
        // Make the 'caption' attribute on your custom block bindable.
        $map['designsetgo/my-block'][] = 'caption';
        return $map;
    }
);
```

**Requirement**: the attribute must be readable at render time — either via an HTML-sourced attribute selector or via a `render_callback` that reads `$block->attributes`. Static (comment-only) attributes on blocks without a `render_callback` will not bind correctly.

---

## DesignSetGo Binding Sources

Five binding sources ship with the plugin. Each accepts a `scope` arg to target the correct post in nested-loop contexts (see [Nested Loop Context](#nested-loop-context) below).

**Source**: `includes/blocks/class-query-bindings.php`, `includes/blocks/class-query-bindings-metabox.php`, `includes/blocks/class-query-bindings-pods.php`, `includes/blocks/class-query-bindings-jetengine.php`, `includes/blocks/class-query-bindings-helpers.php`

### `designsetgo/post-meta`

Always available. Reads a raw post meta value via `get_post_meta()`.

```json
{
  "type": "designsetgo/post-meta",
  "args": { "key": "event_date" }
}
```

### `designsetgo/acf`

Registers when **Advanced Custom Fields (ACF)** is active (`function_exists('get_field')`). Delegates to `get_field()` for formatted output.

For array fields (image, file, relationship), pass `args.subkey` to extract a scalar:

```json
{
  "type": "designsetgo/acf",
  "args": { "key": "hero_image", "subkey": "url" }
}
```

Allowed `subkey` values: `url`, `id`, `alt`, `width`, `height`, `title`, `caption`.

### `designsetgo/metabox`

Registers when **Meta Box** is active (`function_exists('rwmb_meta')`). Delegates to `rwmb_meta( $key, [], $post_id )` so formatted dates, files, and relationship values render correctly.

```json
{
  "type": "designsetgo/metabox",
  "args": { "key": "mb_event_date" }
}
```

### `designsetgo/pods`

Registers when **Pods** is active (`function_exists('pods_field')`). Delegates to `pods_field( $pod_type, $post_id, $field_name )` where `$pod_type` is resolved from the post ID via `get_post_type()`.

```json
{
  "type": "designsetgo/pods",
  "args": { "key": "pods_speaker" }
}
```

### `designsetgo/jetengine`

Registers when **JetEngine** is active (`class_exists('Jet_Engine') && function_exists('jet_engine')`). Prefers `jet_engine()->listings->data->get_meta( $key, $post_id )` for field-type formatting; falls back to raw `get_post_meta()` when the listings data object is unavailable.

```json
{
  "type": "designsetgo/jetengine",
  "args": { "key": "je_price" }
}
```

### Scope Argument

All five sources accept a `scope` arg that controls which post in a nested-loop context is read:

| `scope` value | Meaning |
|--------------|---------|
| `self` (default) | The block's own `postId` context (or `get_the_ID()`). |
| `parent` | The ancestor one level up in `$GLOBALS['designsetgo_parent_stack']`. |
| `root` | The outermost (first) entry in the parent stack. |

```json
{
  "type": "designsetgo/post-meta",
  "args": { "key": "author_bio", "scope": "parent" }
}
```

### Security Gates

All five sources are registered via `designsetgo_register_bindings_source()`, which enforces three shared gates **before** calling the underlying callback:

1. **Post-password gate** — returns `null` for password-protected posts.
2. **Viewable gate** — returns `null` for non-public posts the current user cannot read.
3. **Protected-meta gate** — returns `null` when `args.key` is a protected meta key (prefixed with `_`).

Array or object values that cannot be reduced to a scalar (e.g., an ACF relationship field without `subkey`) are also silently dropped.

---

## Public PHP Helpers

**Source**: `includes/blocks/class-query-bindings-helpers.php`

### `designsetgo_register_bindings_source()`

Wraps WordPress core's `register_block_bindings_source()` and injects the shared security gates and scope resolution described above.

```php
function designsetgo_register_bindings_source(
    string   $slug,
    callable $callback,
    array    $options = []
): void
```

**Parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$slug` | `string` | Binding source slug, e.g. `'myplugin/my-source'`. |
| `$callback` | `callable` | Value callback. Receives `( array $args, ?WP_Block $block, string $attribute_name )`. `$args['__dsgo_post_id']` is pre-populated with the scope-resolved post ID. |
| `$options` | `array` | Optional. Keys: `label` (human-readable label, defaults to `$slug`), `uses_context` (additional context keys; `postId` is always included). |

**Silent no-ops**:
- `register_block_bindings_source()` does not exist (WordPress < 6.5).
- A source with the same `$slug` is already registered.

**Example**

```php
add_action( 'init', function () {
    designsetgo_register_bindings_source(
        'myplugin/event-meta',
        function ( array $args, $block, string $attr ): ?string {
            $post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
            $key     = sanitize_text_field( $args['key'] ?? '' );
            if ( ! $post_id || ! $key ) {
                return null;
            }
            return get_post_meta( $post_id, $key, true ) ?: null;
        },
        [
            'label'        => __( 'Event Meta', 'myplugin' ),
            'uses_context' => [ 'postType' ], // merged with 'postId'
        ]
    );
} );
```

---

### `designsetgo_resolve_bindings_post_id()`

Exposes the scope-aware post-ID resolution for callers that register directly via core `register_block_bindings_source()` and need to honour the `scope` arg themselves.

```php
function designsetgo_resolve_bindings_post_id(
    array    $args,
    ?WP_Block $block
): int
```

Returns the resolved post ID, or `0` when the requested scope cannot be resolved.

Resolution order:
1. `$args['__dsgo_post_id']` if already populated (REST preview context).
2. `$args['scope']` (`self` / `parent` / `root`) resolved via `$GLOBALS['designsetgo_parent_stack']`.
3. `$block->context['postId']` (with `WP_Block::$available_context` reflection fallback).
4. `get_the_ID()`.

---

## Nested Loop Context

When the `designsetgo/query` block renders each item, it pushes an entry onto `$GLOBALS['designsetgo_parent_stack']` before rendering inner blocks. This array is available to any `render_block` hook fired inside a query item.

### `$GLOBALS['designsetgo_parent_stack']`

An ordered list of context entries, oldest (outermost) first:

```php
[
    [ 'postId' => 42, 'postType' => 'post' ],   // outermost query item
    [ 'postId' => 17, 'postType' => 'product' ], // inner query item (current)
]
```

Use `scope: 'parent'` (penultimate entry) or `scope: 'root'` (first entry) to reach ancestor contexts. `scope: 'self'` (default) reads the current (innermost) entry.

### Block Context Keys

Inner blocks inside a query item receive the following block context:

| Context Key | Type | Description |
|-------------|------|-------------|
| `designsetgo/parentItem` | `object` | `{ postId, postType }` of the outer query's current item, available for nested loop bindings. |
| `designsetgo/groupLabel` | `string` | Human-readable group label when `groupBy` is active (e.g., term name, meta value, year). |
| `designsetgo/groupValue` | `string` | Machine-readable group key (slug, meta key, ISO date fragment). Prefer this for comparisons and links. |

`designsetgo/groupLabel` and `designsetgo/groupValue` are used by the `designsetgo/group-context` binding source (registered in `class-query-bindings.php`), which powers the `designsetgo/query-group-header` block. Core blocks that don't declare `usesContext` for these keys are served via `WP_Block::$available_context` reflection.

---

## Style Binding (cross-link)

The `dsgoStyleBinding` block attribute maps CSS property names (including CSS custom properties) to a DesignSetGo binding source and key. Values are injected as inline styles on the block root element via a `render_block` filter, using the same security gates as the Block Bindings sources above (`url(`, `expression(`, `javascript:`, `data:`, `;`, `{`, `}` are all rejected).

Full documentation: [`../extensions/STYLE-BINDING.md`](../extensions/STYLE-BINDING.md) · [`../extensions/DYNAMIC-TAGS.md`](../extensions/DYNAMIC-TAGS.md)

**Source**: `includes/class-style-binding.php`

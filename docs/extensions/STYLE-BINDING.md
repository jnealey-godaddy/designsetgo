# Style Binding Extension

**Added in 2.1.0**

Maps CSS property names — including CSS custom properties — to a DSGo binding source and field key. Values are resolved server-side at render time and injected as inline styles on the block's root element.

## How it works

Each block receives a `dsgoStyleBinding` attribute (type `object`, default `{}`). The attribute is a map of CSS property → `{ source, args: { key } }`. A `render_block` filter reads the attribute, resolves each value through the named binding source, and appends the resulting declarations to the block's existing `style` attribute using `WP_HTML_Tag_Processor`.

## Inspector controls

Open any block's settings, scroll to **Advanced**, then find the **Style Bindings** panel.

- Click **+ Add style binding** to create a new entry.
- Each entry has three fields:
  - **CSS property** — any valid CSS property name or custom property (e.g. `color`, `--brand-primary`).
  - **Source** — one of: Post meta, ACF, Meta Box, Pods, JetEngine.
  - **Field key / name** — the meta key or field name to read.
- Remove an entry with its **Remove** button.

The panel opens automatically when at least one binding is already configured.

## Supported sources

| Source slug | Available when |
|---|---|
| `designsetgo/post-meta` | Always |
| `designsetgo/acf` | ACF active |
| `designsetgo/metabox` | Meta Box active |
| `designsetgo/pods` | Pods active |
| `designsetgo/jetengine` | JetEngine active |

The resolver uses the same post-password / viewable / protected-meta security gates as the block bindings adapter. Protected meta keys (`is_protected_meta()` returns `true`) are never exposed.

## Security

Resolved values are validated before injection. Values matching any of the following patterns are silently dropped:

- `url(` — prevents loading external or data-URI resources
- `expression(` — IE legacy CSS expression execution
- `javascript:` — URI scheme injection
- `data:` — data URI scheme
- Literal `;`, `{`, or `}` — prevents breaking out of the declaration

Only scalar values returned by the source are accepted; non-scalar (array / object) results are discarded.

## Frontend behavior

Injection happens inside `render_block` (priority 5). No JavaScript is involved. The generated output looks like:

```html
<div class="wp-block-group" style="--brand-primary:#3a86ff;color:#3a86ff">
```

If the source returns an empty string or `null`, the property is omitted.

## Developer extension points

Third-party sources can hook into `designsetgo_style_binding_resolve`:

```php
add_filter(
    'designsetgo_style_binding_resolve',
    function ( $value, $source, $args ) {
        if ( $source !== 'my-plugin/custom-source' ) {
            return $value;
        }
        return my_plugin_resolve( $args['key'] ?? '' );
    },
    10,
    3
);
```

For registering a full binding source shared with Dynamic Tags, use the public helper `designsetgo_register_bindings_source()` documented in `../api/BLOCK-BINDINGS.md`.

## Notes

- Style Binding does not apply to `core/freeform`, `core/missing`, or `core/template-part`.
- The value of a bound CSS property in the editor canvas is not live-previewed; the resolved value appears only on the published/rendered frontend.
- Style Binding resolves the post ID from `$GLOBALS['designsetgo_parent_stack']` when inside a Dynamic Query, so each query item renders its own field value.

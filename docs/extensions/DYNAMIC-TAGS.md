# Dynamic Tags Extension

**Added in 2.1.0**

Binds any block's text, link, URL, or image to live data from post meta, site fields, archive context, user fields, or a third-party field plugin. Bindings are stored in the native WordPress Block Bindings format (`attributes.metadata.bindings`) and resolve through WP core's own pipeline — on the frontend via server-side render, and in the editor via a JS resolver that displays the actual live value rather than a placeholder.

## How it works

The extension adds a database-cylinder toolbar icon to every core block that supports the WordPress 6.9 Block Bindings API, and an **Inspector Dynamic Tags panel** listing the same bindable attributes. Both paths write to `metadata.bindings`, so values are fully compatible with native Block Bindings tooling. The extension also registers JS-side resolvers so the editor canvas shows the resolved value in real time (including tracking unsaved title/excerpt edits for the current post).

The extension honours the Block Manager's enabled-extensions allowlist. If `dynamic-tags` is excluded, no editor UI or live-preview bindings register.

## Supported blocks and bindable attributes

| Block | Bindable attribute(s) |
|---|---|
| `core/paragraph` | Content |
| `core/heading` | Content |
| `core/image` | Image URL, Attachment ID, Alt text, Title |
| `core/button` | URL, Text, Link target, Rel |
| `core/post-date` | Date |

## Inspector controls

Select a supported block, then open the **Dynamic Tags** panel in the block sidebar. Each bindable attribute is listed with a **Connect** button. Click the button to open the picker.

**Single-attribute blocks** (paragraph, heading, post-date): the toolbar shows one button that opens the picker directly.

**Multi-attribute blocks** (image, button): the toolbar shows a dropdown menu; select an attribute to open its picker.

The picker lets you choose a **source** and a **key / field name** appropriate to that source. The editor canvas updates the displayed value immediately once a binding is set.

To remove a binding, open the picker and clear the selection.

## Available binding sources

The following sources are built in and always registered:

| Source slug | Available when |
|---|---|
| `designsetgo/post-title` | Always |
| `designsetgo/post-excerpt` | Always |
| `designsetgo/post-id` | Always |
| `designsetgo/post-type` | Always |
| `designsetgo/post-permalink` | Always |
| `designsetgo/post-date` | Always |
| `designsetgo/post-modified-date` | Always |
| `designsetgo/site-title` | Always |
| `designsetgo/site-tagline` | Always |
| `designsetgo/site-url` | Always |
| `designsetgo/post-meta` | Always |
| `designsetgo/acf` | ACF active |
| `designsetgo/metabox` | Meta Box active |
| `designsetgo/pods` | Pods active |
| `designsetgo/jetengine` | JetEngine active |

Sources that require a field key (post-meta, acf, metabox, pods, jetengine) accept an optional `scope` argument — `self` (default), `parent`, or `root` — to read from an ancestor item in nested Dynamic Query scenarios.

## Frontend behavior

Bindings resolve server-side at render time via the standard Block Bindings `get_value` callback registered with `register_block_bindings_source()`. No frontend JavaScript is involved.

## Developer extension points

Register a custom binding source using the public helper:

```php
designsetgo_register_bindings_source( $slug, callable $callback, array $options );
```

This wraps `register_block_bindings_source()` and applies DSGo's shared post-password / viewable / protected-meta security gates automatically. Use `designsetgo_resolve_bindings_post_id( $args, $block )` to honour the `scope` arg when your callback needs to determine which post to read from.

Full API reference: `../api/BLOCK-BINDINGS.md`

## AI Abilities integration

Dynamic Tags exposes a `list-dynamic-tag-sources` Ability so DesignSetGo's AI Abilities API can enumerate available sources and their field metadata when manipulating blocks programmatically.

## Notes

- The extension applies to the five core blocks listed above. DSGo blocks can also expose bindable attributes — see `includes/class-block-bindings-support.php` and the `designsetgo_block_bindings_supported_attributes` filter.
- The `dynamic-tags` extension can be disabled globally via the Block Manager without affecting native WP Block Bindings on other blocks.

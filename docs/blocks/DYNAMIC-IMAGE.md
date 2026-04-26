# Dynamic Image Block - User Guide

**Block name**: `designsetgo/dynamic-image`
**Category**: DesignSetGo
**Keywords**: image, dynamic, featured, acf, tag

> **Updated in 2.1.0** — Inspector rebuilt to the Theme 3 IA convention: sticky footer, live editor preview, and Select-based controls for every finite-option setting.

## Overview

The **Dynamic Image** block renders an image whose source is resolved at render time rather than being stored as a fixed URL. Authors pick a **Dynamic Tag source** (featured image, ACF image field, site logo, and others) in the inspector; the block resolves the correct image per post on the frontend. A fallback image is shown when the source returns nothing — for example, a post that has no featured image.

The editor preview calls the REST endpoint `/designsetgo/v1/dynamic-tags/preview` so what you see in the block editor matches what `render.php` produces on the frontend.

**Key Features:**
- Source resolved at render time — image updates automatically when post data changes
- Supports any Dynamic Tag source that returns an image (featured image, site logo, ACF image fields, Meta Box, Pods, JetEngine, and any custom source)
- Live editor preview — the actual resolved image appears in the canvas while editing
- Configurable aspect ratio, object fit, and focal point
- Optional link with configurable target and `rel` attribute
- Fallback image for posts where the source is empty
- Alt text override (defaults to the alt text carried by the source)
- Server-rendered (`render.php`): outputs `<figure>` with a standard `<img>` (`loading="lazy"`, `decoding="async"`, `width`/`height` from attachment metadata)
- Outputs nothing (empty string) when both source and fallback are empty — safe to embed in query loops

---

## Block Attributes

| Attribute     | Type   | Default   | Description |
|---------------|--------|-----------|-------------|
| `source`      | string | `""`      | Dynamic Tag source identifier (e.g. `"post_thumbnail"`). Set via the Dynamic Tag picker. |
| `sourceArgs`  | object | `{}`      | Arguments passed to the source resolver (e.g. field key for ACF). |
| `size`        | string | `"full"`  | WordPress image size slug (e.g. `"thumbnail"`, `"medium"`, `"large"`, `"full"`). |
| `altOverride` | string | `""`      | Optional alt text. When blank, the alt text from the source is used. |
| `focalPoint`  | object | `{x:0.5, y:0.5}` | Focal point for `object-position`. Expressed as 0–1 fractions. |
| `aspectRatio` | string | `""`      | CSS aspect-ratio value (e.g. `"16/9"`, `"1/1"`). Empty means natural dimensions. |
| `objectFit`   | string | `"cover"` | CSS `object-fit` value: `cover`, `contain`, `fill`, `scale-down`. |
| `fallbackId`  | number | `0`       | Media library attachment ID used as fallback. |
| `fallbackUrl` | string | `""`      | URL of a fallback image (used when `fallbackId` is unavailable). |
| `fallbackAlt` | string | `""`      | Alt text for the fallback image. |
| `href`        | string | `""`      | Optional URL to wrap the image in a link. |
| `linkTarget`  | string | `""`      | Link target: empty (same tab) or `"_blank"` (new tab). |
| `rel`         | string | `""`      | `rel` attribute for the link. Auto-set to `noopener noreferrer` when `linkTarget` is `_blank` and `rel` is blank. |

---

## Inspector Controls (Settings Panel)

All controls live in a single **Settings** `DsgoInspectorPanel` with per-control reset-to-default. The panel itself has a "Reset all" button.

| Control | Type | Default | Notes |
|---------|------|---------|-------|
| **Dynamic source** | Dynamic Tag picker button | — | Opens the Dynamic Tag picker overlay. Shows the resolved source key (e.g. `post_thumbnail`) as a `<code>` summary after selection. |
| **Image size** | Select | `full` | Populated from registered WordPress image sizes. Falls back to Thumbnail / Medium / Large / Full if the REST data is unavailable. |
| **Alt text override** | Text | `""` | Leave blank to inherit alt text from the source. |
| **Aspect ratio** | Select | `""` (Original) | Options: Original, Square (1:1), Landscape 16:9, Landscape 4:3, Landscape 3:2, Portrait 3:4, Portrait 2:3, Portrait 9:16. Custom values saved by earlier plugin versions appear as an extra option so existing content is preserved. |
| **Object fit** | Select | `cover` | Cover, Contain, Fill, Scale down. |
| **Focal point** | Focal Point Picker | `{x:0.5, y:0.5}` | Only shown when an image (resolved or fallback) is available. |
| **Fallback image** | Media upload | — | Shown on posts where the source is empty. Button toggles between "Select image" / "Replace" and shows a small preview. |
| **Link URL** | Text | `""` | Hidden by default; add to show. |
| **Open in** | Select | same tab | Only shown when a Link URL is set. Options: Same tab, New tab. |
| **Rel attribute** | Select | `""` | Only shown when a Link URL is set. Options: None, nofollow, noopener noreferrer, nofollow noopener noreferrer, sponsored, ugc. |

Block Supports controls (border, spacing, alignment) appear in their standard sidebar panels.

---

## Usage Examples

### Featured image in a query loop

Insert the Dynamic Image block inside a `designsetgo/query` or `core/query` loop. Set the Dynamic source to "Featured Image". The block reads `postId` from block context and resolves the featured image for each post automatically.

```json
{
  "source": "post_thumbnail",
  "size": "large",
  "aspectRatio": "3/2",
  "objectFit": "cover"
}
```

### ACF image field with fallback

```json
{
  "source": "acf/image",
  "sourceArgs": { "key": "hero_photo" },
  "size": "large",
  "fallbackId": 42,
  "fallbackAlt": "Default product photo"
}
```

### Linked site logo

```json
{
  "source": "site_logo",
  "href": "/",
  "linkTarget": "",
  "rel": ""
}
```

---

## Frontend Behavior

The block is server-rendered. `render.php` calls `ImageResolver::resolve()` to obtain an image descriptor, then emits:

```html
<figure class="wp-block-designsetgo-dynamic-image" style="aspect-ratio:16/9;">
  <img src="…" alt="…" loading="lazy" decoding="async" width="1200" height="675"
       style="object-fit:cover; object-position:50% 50%;" />
</figure>
```

When a link is configured, the `<img>` is wrapped in `<a href="…">`. When both source and fallback are empty the block outputs nothing, so it is safe to use in templates where some posts will not have the field populated.

---

## Accessibility

- `loading="lazy"` and `decoding="async"` are set automatically on the server.
- `width` and `height` attributes are included from attachment metadata, preventing layout shift (CLS).
- Alt text falls back to the attachment's own alt text from the Media Library; use **Alt text override** when a more specific description is needed for the context.
- Images that are purely decorative should have an empty alt text override (`alt=""`).
- When `_blank` link target is used and no `rel` is set, `rel="noopener noreferrer"` is applied automatically.

---

## Related Blocks

- **Section** (`designsetgo/section`) — Often used as a full-bleed background container alongside or behind a Dynamic Image.
- **Fifty Fifty** (`designsetgo/fifty-fifty`) — Uses a static media library image; use Dynamic Image when the image must resolve per-post.

---

*DesignSetGo v2.0.0+ | WordPress 6.4+*
*Inspector updated to Theme 3 IA (sticky footer, live preview, Select controls) in v2.1.0.*

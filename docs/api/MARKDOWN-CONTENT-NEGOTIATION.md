# Markdown Content Negotiation

**Since**: 1.5.0 (negotiation handler); hardened filesystem writes in 2.1.0

Any published page or post URL returns Markdown when the HTTP request sends an `Accept: text/markdown` header that outranks `text/html` via q-values. This feature is implemented at the `template_redirect` layer — it is **not** a REST route.

See also:
- [REST API Reference](REST-API-REFERENCE.md) — full endpoint index including the `GET /llms-txt/markdown/{post_id}` JSON route
- [Block Bindings](BLOCK-BINDINGS.md) — Dynamic Tags and Block Bindings sources

---

## Overview

When a client sends an `Accept` header that prefers `text/markdown` over `text/html`, DesignSetGo:

1. Checks the feature enablement flag and post-type allowlist.
2. Resolves the current singular post.
3. Reads the pre-generated static `.md` file from `wp-content/uploads/designsetgo/llms/` if it exists.
4. Falls back to live conversion via `DesignSetGo\Markdown\Converter` if no static file is present.
5. Responds with `Content-Type: text/markdown; charset=utf-8` and `Vary: Accept`.

This passes the [acceptmarkdown.com](https://acceptmarkdown.com/) readiness contract: q-values are honoured per RFC 7231 §5.3.2, explicit media-type entries override wildcards, and malformed q-values default to `1.0` rather than silently rejecting the preference.

**Source**: `includes/llms-txt/class-negotiation-handler.php`, `includes/llms-txt/class-file-manager.php`, `includes/markdown/class-converter.php`

---

## Response Headers

| Header | Value |
|--------|-------|
| `Content-Type` | `text/markdown; charset=utf-8` |
| `Vary` | `Accept` |
| `X-Robots-Tag` | `noindex` |
| `Cache-Control` | *(no-cache — `nocache_headers()` is called)* |

No `Content-Length` header is emitted; it would become incorrect under `mod_deflate` or `ob_gzhandler`.

---

## q-Value Handling

The negotiation logic honours the full RFC 7231 q-value grammar:

| Accept Header | Result |
|---------------|--------|
| `Accept: text/markdown` | Markdown served |
| `Accept: text/markdown;q=0.9, text/html;q=0.5` | Markdown served (0.9 > 0.5) |
| `Accept: text/markdown;q=0.5, text/html` | HTML served (ties go to HTML, 1.0 > 0.5) |
| `Accept: */*;q=0.9, text/html;q=0.5` | HTML served (explicit `text/html` overrides wildcard) |
| `Accept: text/xml` | `406 Not Acceptable` |

A `406 Not Acceptable` response is returned only when the `Accept` header is present and rejects **both** `text/html` and `text/markdown` (q=0 or absent for both). The response body is `text/plain`: `Not Acceptable. Supported media types: text/html, text/markdown.`

---

## Eligibility Checks

A request is served as Markdown only when **all** of the following are true:

| Check | Detail |
|-------|--------|
| Feature enabled | `Settings → DesignSetGo → llms.txt` toggle is on (`llms_txt.enable = true`). |
| Singular URL | `is_singular()` is true (single post or page URL, not an archive). |
| Post type in allowlist | The post type is listed in `llms_txt.post_types` (default: `page`, `post`). |
| Post status | `post_status = 'publish'`. |
| Not password-protected | `post_password` is empty. |
| Not excluded | The post meta `_designsetgo_exclude_llms` is falsy. |

Front page and blog index requests (`is_front_page() || is_home()`) return the full `llms.txt` listing as Markdown instead of a single-post conversion.

---

## Enabling and Disabling

**Admin UI**: Settings → DesignSetGo → **llms.txt** tab. Toggle the **"Enable llms.txt"** switch. The feature is disabled by default (`llms_txt.enable = false`).

**REST API** (programmatic):

```bash
curl -X POST "https://example.com/wp-json/designsetgo/v1/settings" \
  -H "X-WP-Nonce: <nonce>" \
  -H "Content-Type: application/json" \
  -d '{"llms_txt": {"enable": true, "post_types": ["page","post"]}}'
```

See [REST API Reference — POST `/settings`](REST-API-REFERENCE.md#post-settings) for the full schema.

---

## Per-Post Exclusion

To exclude a single post or page from Markdown negotiation, set the post meta key:

```
_designsetgo_exclude_llms
```

**Type**: boolean — any truthy value excludes the post.

**Admin UI**: the "Exclude from llms.txt" toggle appears in the post editor sidebar under **DesignSetGo → llms.txt**.

**Programmatic** (e.g., WP-CLI or REST):

```bash
# Exclude post ID 42
wp post meta update 42 _designsetgo_exclude_llms 1
```

```php
update_post_meta( 42, '_designsetgo_exclude_llms', true );
```

The meta key is registered for all public post types via `register_post_meta()` with `show_in_rest: true`, so it is also accessible via the core posts REST API.

---

## Static File Storage

DesignSetGo pre-generates `.md` files and stores them under:

```
wp-content/uploads/designsetgo/llms/<post-slug>.md
```

For hierarchical post types (pages with parents) the full slug path is preserved:

```
wp-content/uploads/designsetgo/llms/products/widget-pro.md
```

An `.htaccess` file is written to the `designsetgo/llms/` directory on first use to configure Apache to serve `.md` files with the `text/markdown` MIME type:

```apache
# DesignSetGo llms.txt - serve Markdown inline
<IfModule mod_mime.c>
    AddType text/markdown .md
    AddCharset UTF-8 .md
</IfModule>
```

Nginx users must add a `text/markdown .md` MIME entry at the server level.

---

## Filesystem Writes (2.1.0 Hardening)

All file writes go through the WordPress Filesystem API (`WP_Filesystem`) with a `file_put_contents()` fallback for environments where `WP_Filesystem` is unavailable:

- **Managed hosts** (WP Engine, Kinsta, Pantheon) that do not define FTP constants: the web user typically owns the upload directory, so `file_put_contents()` succeeds.
- **Standard hosts**: `WP_Filesystem` handles the write using stored FTP/SSH credentials when needed.
- **Unit-test bootstrap**: the fallback path is exercised directly.

**Source**: `File_Manager::fs_put_contents()` in `includes/llms-txt/class-file-manager.php`.

---

## Testing

### curl

```bash
# Returns text/markdown for a published page
curl -v -H 'Accept: text/markdown' https://example.com/sample-page/

# q-value example: markdown wins over html
curl -v -H 'Accept: text/html;q=0.5, text/markdown;q=0.9' https://example.com/sample-page/

# Returns 406 when neither type is acceptable
curl -v -H 'Accept: application/json' https://example.com/sample-page/
```

Expected success response headers:

```http
HTTP/1.1 200 OK
Content-Type: text/markdown; charset=utf-8
Vary: Accept
X-Robots-Tag: noindex
```

### WordPress CLI

Check whether a static `.md` file exists for a post and regenerate it:

```bash
wp post meta get 42 _designsetgo_exclude_llms   # should be empty or 0

# Regenerate markdown files for all enabled post types
wp eval 'DesignSetGo\LLMS_Txt\Controller::schedule_flush_rewrite_rules(); echo "done\n";'
```

Use the admin REST route to regenerate all files:

```bash
wp eval '
  $c = new DesignSetGo\LLMS_Txt\File_Manager();
  $g = new DesignSetGo\LLMS_Txt\Generator($c);
  print_r($c->generate_all_files($g));
'
```

---

## WP-CLI Commands (Filter Index, not Markdown)

The `wp dsgo query index` command group manages the Dynamic Query filter index, not the Markdown pipeline. See [WP-CLI Reference](WP-CLI-REFERENCE.md) for details.

There are no dedicated WP-CLI commands for the Markdown/llms.txt pipeline; use the admin REST endpoints (`POST /llms-txt/generate-files`, `POST /llms-txt/flush-cache`) or the `wp eval` approach above.

---

## Relationship to the `GET /llms-txt/markdown/{post_id}` REST Route

The `GET /llms-txt/markdown/{post_id}` REST route (documented in [REST API Reference](REST-API-REFERENCE.md#get-llms-txtmarkdownpost_id)) returns a JSON object `{ id, title, url, updated, markdown }` and is intended for programmatic consumers (plugins, CLI scripts, AI indexers).

The content-negotiation feature described in this document operates at the `template_redirect` layer using the canonical page URL and returns raw Markdown — no JSON wrapper. The two mechanisms are complementary.

| | Content Negotiation | REST Route |
|---|---|---|
| URL | Any published page/post URL | `/wp-json/designsetgo/v1/llms-txt/markdown/{post_id}` |
| Trigger | `Accept: text/markdown` header | Always returns markdown in JSON |
| Response format | Raw Markdown (`text/markdown`) | JSON (`application/json`) |
| Auth required | No | No (feature must be enabled) |
| Use case | AI clients, `curl`, browser extensions | Plugin integrations, CLI scripts |

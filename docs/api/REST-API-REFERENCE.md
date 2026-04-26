# REST API Reference

**Namespace**: `designsetgo/v1`
**Base URL**: `/wp-json/designsetgo/v1/`

All endpoints require authentication unless noted otherwise. Admin endpoints require the `manage_options` capability. Nonce-protected write endpoints expect an `X-WP-Nonce` header with a valid `wp_rest` nonce.

---

## Settings

Manages plugin-wide configuration.

**Source**: `includes/admin/class-settings.php`

### GET `/settings`

Returns all current settings merged with defaults.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK`

```json
{
  "enabled_blocks": [],
  "enabled_extensions": [],
  "excluded_blocks": [],
  "performance": { "conditional_loading": true, "cache_duration": 3600 },
  "forms": { "enable_honeypot": true, "enable_rate_limiting": true, "enable_email_logging": false, "retention_days": 30 },
  "animations": { "enable_animations": true, "default_duration": 600, "default_easing": "ease-in-out", "respect_prefers_reduced_motion": true },
  "security": { "log_ip_addresses": true, "log_user_agents": true, "log_referrers": false },
  "integrations": { "google_maps_api_key": "", "turnstile_site_key": "", "turnstile_secret_key": "" },
  "sticky_header": { "enable": true, "custom_selector": "", "z_index": 100, "..." : "..." },
  "draft_mode": { "enable": true, "show_page_list_actions": true, "..." : "..." },
  "llms_txt": { "enable": false, "post_types": ["page", "post"] }
}
```

### POST `/settings`

Updates plugin settings. Partial updates are supported — only supplied keys are changed.

| Auth | Capability | Nonce |
|------|------------|-------|
| Required | `manage_options` | `X-WP-Nonce` |

**Body Parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `enabled_blocks` | `array<string>` | Block names to enable. Empty = all enabled. |
| `enabled_extensions` | `array<string>` | Extension names to enable. Empty = all enabled. |
| `excluded_blocks` | `array<string>` | Block name patterns excluded from abilities API. |
| `performance` | `object` | `{ conditional_loading: bool, cache_duration: int }` |
| `forms` | `object` | `{ enable_honeypot: bool, enable_rate_limiting: bool, enable_email_logging: bool, retention_days: int }` |
| `animations` | `object` | `{ enable_animations: bool, default_duration: int, default_easing: string, respect_prefers_reduced_motion: bool }` |
| `security` | `object` | `{ log_ip_addresses: bool, log_user_agents: bool, log_referrers: bool }` |
| `integrations` | `object` | `{ google_maps_api_key: string, turnstile_site_key: string, turnstile_secret_key: string }` |
| `sticky_header` | `object` | Sticky header configuration (see `get_defaults()` for full schema). |
| `draft_mode` | `object` | `{ enable: bool, show_page_list_actions: bool, show_page_list_column: bool, show_frontend_preview: bool, auto_save_enabled: bool, auto_save_interval: int }` |
| `llms_txt` | `object` | `{ enable: bool, post_types: array<string> }` |

**Response** — `200 OK`

```json
{ "success": true, "settings": { "..." : "..." } }
```

### GET `/blocks`

Returns available blocks organized by category.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK`

```json
{
  "containers": { "label": "Container Blocks", "blocks": [{ "name": "designsetgo/grid", "title": "Grid Container", "description": "...", "performance": "low" }] },
  "ui": { "..." : "..." },
  "interactive": { "..." : "..." },
  "widgets": { "..." : "..." },
  "forms": { "..." : "..." }
}
```

### GET `/extensions`

Returns available extensions.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK`

```json
[
  { "name": "animation", "title": "Animation", "description": "Base animation framework" },
  "..."
]
```

### GET `/stats`

Returns plugin statistics.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK`

```json
{ "total_blocks": 48, "enabled_blocks": 48, "form_submissions": 0 }
```

---

## Global Styles

Manages theme.json integration and style overrides.

**Source**: `includes/admin/class-global-styles.php`

### GET `/global-styles`

Returns saved global style overrides.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK` — Saved styles object (or empty object `{}`).

### POST `/global-styles`

Updates global style overrides.

| Auth | Capability | Nonce |
|------|------------|-------|
| Required | `manage_options` | `X-WP-Nonce` |

**Body Parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `spacing` | `object` | Spacing style overrides. |
| `typography` | `object` | Typography style overrides. |
| `color` | `object` | Color style overrides. |
| `border` | `object` | Border style overrides. |

**Response** — `200 OK`

```json
{ "success": true }
```

---

## Draft Mode

Create, publish, and discard draft copies of published pages.

**Source**: `includes/admin/class-draft-mode-rest.php`

### POST `/draft-mode/create`

Creates a draft copy of a published page.

| Auth | Capability |
|------|------------|
| Required | `publish_pages` + `edit_post` on target |

**Body Parameters**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `post_id` | `integer` | Yes | ID of the published page to copy. |
| `content` | `string` | No | Content override (captures unsaved edits). |
| `title` | `string` | No | Title override. |
| `excerpt` | `string` | No | Excerpt override. |

**Response** — `200 OK`

```json
{ "success": true, "draft_id": 123, "edit_url": "...", "message": "Draft created successfully.", "draft_title": "...", "original_id": 45 }
```

### POST `/draft-mode/{id}/publish`

Merges a draft back into the original published page.

| Auth | Capability |
|------|------------|
| Required | `publish_pages` + `delete_post` on draft + `publish_post` on original |

**URL Parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | `integer` | Draft post ID. |

**Response** — `200 OK`

```json
{ "success": true, "original_id": 45, "edit_url": "...", "view_url": "...", "message": "Changes published successfully." }
```

### DELETE `/draft-mode/{id}`

Discards a draft.

| Auth | Capability |
|------|------------|
| Required | `publish_pages` + `delete_post` on draft |

**URL Parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | `integer` | Draft post ID. |

**Response** — `200 OK`

```json
{ "success": true, "original_id": 45, "message": "Draft discarded." }
```

### GET `/draft-mode/status/{post_id}`

Returns draft mode status for a post.

| Auth | Capability |
|------|------------|
| Required | `edit_pages` + `edit_post` on target |

**URL Parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `post_id` | `integer` | Post ID to check. |

**Response** — `200 OK`

```json
{ "settings": { "enabled": true }, "exists": true, "is_draft": false, "has_draft": true, "draft_id": 123, "original_id": 45, "can_create": true }
```

---

## Revisions

> **Removed in 2.1.0.** The Visual Revision Comparison endpoints (`/revisions/*`) were removed in favor of WordPress 7.0's native visual diff for revisions. The associated settings (`revisions.enable_visual_comparison`, `revisions.default_to_visual`) were also removed. See the [2.1.0 changelog entry](../../CHANGELOG.md) for details.

---

## Forms

Public form submission endpoint.

**Source**: `includes/blocks/class-form-handler.php`

### POST `/form/submit`

Handles form submissions. This is a **public endpoint** — no authentication required. Protected by honeypot, time-based checks, rate limiting, optional Turnstile, and field validation.

| Auth | Capability |
|------|------------|
| None | Public |

**Body Parameters**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `formId` | `string` | Yes | Form identifier. |
| `fields` | `array` | Yes | Form field values. |
| `honeypot` | `string` | No | Honeypot field (must be empty). |
| `timestamp` | `string` | No | Submission timestamp for bot detection. |
| `enable_email` | `boolean` | No | Whether to send email notification. |
| `email_to` | `string` | No | Recipient email. |
| `email_from_email` | `string` | No | Sender email. |
| `email_from_name` | `string` | No | Sender name. |
| `email_subject` | `string` | No | Email subject line. |
| `turnstile_token` | `string` | No | Cloudflare Turnstile verification token. |

**Response** — `200 OK`

```json
{ "success": true, "message": "Form submitted successfully." }
```

**Error Responses**

| Code | Status | Cause |
|------|--------|-------|
| `spam_detected` | 403 | Honeypot or time check failed. |
| `rate_limit_exceeded` | 429 | Too many submissions from this IP. |
| `turnstile_failed` | 403 | Cloudflare verification failed. |
| `validation_error` | 400 | Field validation failed. |

---

## GDPR Compliance

Data export and deletion for form submissions.

**Source**: `includes/admin/class-gdpr-compliance.php`

### POST `/gdpr/export`

Exports all form submission data for an email address.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Body Parameters**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `email` | `string` | Yes | Email address to export data for. Must be a valid email. |

**Response** — `200 OK`

```json
{ "items_found": 5, "data": [...] }
```

### DELETE `/gdpr/delete`

Deletes all form submission data for an email address.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Body Parameters**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `email` | `string` | Yes | Email address to delete data for. Must be a valid email. |

**Response** — `200 OK`

```json
{ "items_removed": 5, "items_retained": 0, "done": true }
```

---

## llms.txt

Manages the llms.txt AI documentation system.

**Source**: `includes/llms-txt/class-rest-controller.php`

### GET `/llms-txt/post-types`

Returns public post types available for llms.txt generation.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK`

```json
[
  { "name": "page", "label": "Pages" },
  { "name": "post", "label": "Posts" }
]
```

### GET `/llms-txt/status`

Returns current llms.txt feature status and conflict info.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK`

```json
{ "enabled": false, "url": "https://example.com/llms.txt", "has_conflict": false, "conflict_dismissed": false, "conflict_info": null }
```

### GET `/llms-txt/markdown/{post_id}`

Returns a published post converted to markdown.

| Auth | Capability |
|------|------------|
| None | Public (feature must be enabled; post must be published and not excluded) |

**URL Parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `post_id` | `integer` | Post ID to convert. Must be > 0. |

**Response** — `200 OK`

```json
{ "id": 45, "title": "About Us", "url": "https://example.com/about/", "updated": "2026-02-07T12:00:00+00:00", "markdown": "# About Us\n..." }
```

**Error Responses**

| Code | Status | Cause |
|------|--------|-------|
| `not_found` | 404 | Post does not exist. |
| `not_published` | 404 | Post is not published. |
| `not_public` | 404 | Post is password-protected or not publicly viewable. |
| `excluded` | 403 | Post excluded via `_designsetgo_exclude_llms` meta. |
| `feature_disabled` | 403 | llms.txt feature is disabled in settings. |
| `post_type_disabled` | 403 | Post type not enabled for llms.txt. |

### POST `/llms-txt/flush-cache`

Clears all cached llms.txt and markdown data.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK`

```json
{ "success": true, "message": "llms.txt cache has been cleared." }
```

### POST `/llms-txt/generate-files`

Generates markdown files for all eligible posts.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK`

```json
{ "success": true, "generated_count": 12, "errors": [], "message": "Generated 12 markdown files." }
```

### POST `/llms-txt/resolve-conflict`

Renames an existing physical llms.txt file to resolve a conflict.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK`

```json
{ "success": true, "message": "The existing llms.txt file has been renamed. DesignSetGo will now serve the dynamic version." }
```

### POST `/llms-txt/dismiss-conflict`

Dismisses the conflict notice without resolving.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK`

```json
{ "success": true, "message": "Conflict notice dismissed." }
```

---

## Removed Endpoints

### `/designsetgo/v1/revisions/*` — Removed in 2.1.0

The Visual Revision Comparison endpoints (`GET /revisions/{post_id}`, `GET /revisions/render/{revision_id}`, `GET /revisions/diff/{from_id}/{to_id}`, `POST /revisions/restore/{revision_id}`) were removed in 2.1.0 in favour of WordPress 7.0's native visual diff for revisions. The associated admin page, block differ, revision renderer, and settings keys (`revisions.enable_visual_comparison`, `revisions.default_to_visual`) were also removed. Entries for these routes are retained in the Endpoint Summary table below for historical reference.

---

## Dynamic Query Endpoints

Server-side rendering, editor preview, and filter-index management for the `designsetgo/query` block family.

**Source**: `includes/blocks/class-query.php`, `includes/blocks/class-query-template-controller.php`

All write endpoints require an `X-WP-Nonce` header with a valid `wp_rest` nonce. The `/query/render` and `/query/preview` routes are editor-facing (require `read` / `edit_posts`); all filter-index and template routes require `manage_options` or `edit_post` as noted per endpoint.

---

### POST `/query/render`

Server-side render of a Dynamic Query block, used by the editor live-preview path for users and terms (posts use `useEntityRecords` on the client).

| Auth | Capability | Nonce |
|------|------------|-------|
| Required | `read` | `X-WP-Nonce` |

**Body Parameters**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `queryId` | `string` | Yes | Block's unique query identifier (e.g. `q-a3f2c1b4d9`). |
| `attributes` | `object` | Yes | Full block attributes object (source, perPage, taxQuery, etc.). |
| `page` | `integer` | No | Page number (default `1`). |
| `innerBlocks` | `string` | No | Serialized block-comment markup of inner blocks (item template + siblings). |
| `params` | `object` | No | Active filter/sort state: keys from `designsetgo_query_url_params` allowlist and any `filter_<taxonomy>` keys. |
| `currentUrl` | `string` | No | Canonical page URL used to build chip/reset links in the no-JS fallback. |

**Response** — `200 OK`

```json
{
  "html": "<div class=\"dsgo-query-region\">...</div>",
  "totalPages": 4,
  "totalItems": 22
}
```

**Example**

```bash
curl -X POST "https://example.com/wp-json/designsetgo/v1/query/render" \
  -H "X-WP-Nonce: $(wp eval 'echo wp_create_nonce("wp_rest");')" \
  -H "Content-Type: application/json" \
  -d '{
    "queryId": "q-a3f2c1b4d9",
    "attributes": { "source": "posts", "postType": "post", "perPage": 6 },
    "page": 1,
    "params": { "filter_category": "news" }
  }'
```

---

### GET `/query/preview`

Returns a lightweight list of preview items for `users` and `terms` sources. Posts use `useEntityRecords` on the client and will receive a `400` from this route if requested.

| Auth | Capability | Nonce |
|------|------------|-------|
| Required | `edit_posts` | `X-WP-Nonce` |

**Query Parameters**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `attributes` | `object` | Yes | Block attributes. Keys used: `source` (`users`\|`terms`), `perPage` (1–100, default 6), `taxonomy` (required when `source=terms`, default `category`). |

**Example request**

WordPress REST accepts nested objects on `GET` via bracket notation in the query string:

```bash
curl -G 'https://example.com/wp-json/designsetgo/v1/query/preview' \
  --data-urlencode 'attributes[source]=users' \
  --data-urlencode 'attributes[perPage]=6' \
  -H 'X-WP-Nonce: <nonce>'
```

For terms:

```bash
curl -G 'https://example.com/wp-json/designsetgo/v1/query/preview' \
  --data-urlencode 'attributes[source]=terms' \
  --data-urlencode 'attributes[taxonomy]=category' \
  --data-urlencode 'attributes[perPage]=10' \
  -H 'X-WP-Nonce: <nonce>'
```

**Response** — `200 OK`

```json
[
  { "id": 1, "name": "Alice Smith", "type": "user" },
  { "id": 2, "name": "Bob Jones",  "type": "user" }
]
```

**Error Responses**

| Code | Status | Cause |
|------|--------|-------|
| `not_needed` | 400 | `source` is `posts` — use `useEntityRecords` instead. |

---

### POST `/query/filter-register`

Registers an ad-hoc filter in the filter registry and schedules it for the next index rebuild.

| Auth | Capability | Nonce |
|------|------------|-------|
| Required | `manage_options` | `X-WP-Nonce` |

**Body Parameters**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `filter_key` | `string` | Yes | Unique registry key (sanitized via `sanitize_key`). |
| `config` | `object` | Yes | Filter configuration. Must include `type` (`taxonomy`\|`meta`) and `source` (taxonomy or meta key name). |

**Response** — `200 OK`

```json
{
  "registered": true,
  "filter_key": "price_range",
  "config": { "type": "meta", "source": "_price" }
}
```

**Error Responses**

| Code | Status | Cause |
|------|--------|-------|
| `dsgo_filter_register_invalid` | 400 | `filter_key`, `type`, or `source` missing. |
| `dsgo_filter_invalid_type` | 400 | `config.type` is not `taxonomy` or `meta`. |

---

### GET `/query/filter-status`

Returns the current health and state of the filter index table.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK`

```json
{
  "total_rows": 4821,
  "in_progress": false,
  "last_rebuilt_at": 1745510400,
  "processed": 320
}
```

| Field | Type | Description |
|-------|------|-------------|
| `total_rows` | `integer` | Live row count in `{prefix}dsgo_query_filter_index`. |
| `in_progress` | `boolean` | Whether a rebuild is currently running. Auto-clears after 5 minutes if stale. |
| `last_rebuilt_at` | `integer\|null` | Unix timestamp of the last completed rebuild, or `null`. |
| `processed` | `integer` | Posts processed in the last (or current) rebuild pass. |

---

### POST `/query/filter-rebuild`

Triggers a full synchronous rebuild of the filter index (truncate + re-index all published posts). On large sites this may approach PHP's `max_execution_time`; the admin dashboard polls `/filter-status` every 2 s to track progress.

| Auth | Capability | Nonce |
|------|------------|-------|
| Required | `manage_options` | `X-WP-Nonce` |

No body parameters. The rebuild batch size uses the server-side default (200 posts/batch).

**Response** — `200 OK`

```json
{
  "status": "complete",
  "processed": 320,
  "total_rows": 4821
}
```

| `status` value | Meaning |
|----------------|---------|
| `complete` | Rebuild finished successfully. |
| `locked` | Another rebuild is already running; try again shortly. |
| `error` | Table missing or truncation failed — check database. |

---

### GET `/query/filters`

Returns all filters currently registered in the filter registry.

| Auth | Capability |
|------|------------|
| Required | `manage_options` |

**Response** — `200 OK` — Array of registered filter objects.

```json
[
  { "key": "category",    "type": "taxonomy", "source": "category" },
  { "key": "price_range", "type": "meta",     "source": "_price"   }
]
```

Also accepts `DELETE /query/filters` with body `{ "filter_key": "<key>" }` to unregister a filter.

---

### GET `/query/template`

Exports a `designsetgo/query` block from a post as a portable JSON blob (`schemaVersion: 1`). Only attributes present in the block's `block.json` allowlist (resolved via `WP_Block_Type_Registry`) are included.

| Auth | Capability |
|------|------------|
| Required | `edit_post` on the target post |

**Query Parameters**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `post_id` | `integer` | Yes | ID of the post that contains the query block. |
| `query_id` | `string` | Yes | The `queryId` attribute of the block to export. |

**Response** — `200 OK`

```json
{
  "schemaVersion": 1,
  "exportedAt": "2026-04-26T12:00:00Z",
  "blockName": "designsetgo/query",
  "attributes": { "queryId": "q-a3f2c1b4d9", "source": "posts", "perPage": 6 },
  "innerBlocks": "<!-- wp:designsetgo/query-results -->...<!-- /wp:designsetgo/query-results -->"
}
```

**Error Responses**

| Code | Status | Cause |
|------|--------|-------|
| `dsgo_template_not_found` | 404 | Post not found. |
| `dsgo_template_block_not_found` | 404 | No `designsetgo/query` block with that `queryId` in the post. |
| `dsgo_template_forbidden` | 403 | Current user cannot edit the post. |

---

### POST `/query/template`

Imports a query template blob. Attributes are filtered against the current `block.json` allowlist. A fresh `queryId` is generated on every import so the result never collides with existing blocks.

| Auth | Capability |
|------|------------|
| Required | `edit_posts` |

**Body Parameters**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `schemaVersion` | `integer` | Yes | Must be `1`. |
| `blockName` | `string` | Yes | Must be `"designsetgo/query"`. |
| `attributes` | `object` | Yes | Block attributes from the export blob. Unknown keys are silently dropped. |
| `innerBlocks` | `string` | Yes | Serialized block-comment markup of inner blocks. |

**Response** — `200 OK`

```json
{
  "blockMarkup": "<!-- wp:designsetgo/query {\"queryId\":\"q-f7c3b8a2d1\",...} -->...<!-- /wp:designsetgo/query -->"
}
```

**Error Responses**

| Code | Status | Cause |
|------|--------|-------|
| `dsgo_template_schema_mismatch` | 400 | `schemaVersion` is not `1`. |
| `dsgo_template_wrong_block` | 400 | `blockName` is not `"designsetgo/query"`. |
| `dsgo_template_forbidden` | 403 | Current user cannot edit posts. |

---

## Markdown Content Negotiation

Any published page or post URL returns Markdown when the request sends `Accept: text/markdown` (or outranks `text/html` via q-values). This feature is implemented at the `template_redirect` layer, not as a REST route.

See [MARKDOWN-CONTENT-NEGOTIATION.md](MARKDOWN-CONTENT-NEGOTIATION.md) for full details, per-post exclusion meta, WP-CLI commands, and test instructions.

---

## Endpoint Summary

| Method | Route | Auth | Source |
|--------|-------|------|--------|
| GET | `/settings` | `manage_options` | `class-settings.php` |
| POST | `/settings` | `manage_options` + nonce | `class-settings.php` |
| GET | `/blocks` | `manage_options` | `class-settings.php` |
| GET | `/extensions` | `manage_options` | `class-settings.php` |
| GET | `/stats` | `manage_options` | `class-settings.php` |
| GET | `/global-styles` | `manage_options` | `class-global-styles.php` |
| POST | `/global-styles` | `manage_options` + nonce | `class-global-styles.php` |
| POST | `/draft-mode/create` | `publish_pages` | `class-draft-mode-rest.php` |
| POST | `/draft-mode/{id}/publish` | `publish_pages` | `class-draft-mode-rest.php` |
| DELETE | `/draft-mode/{id}` | `publish_pages` | `class-draft-mode-rest.php` |
| GET | `/draft-mode/status/{post_id}` | `edit_pages` | `class-draft-mode-rest.php` |
| GET | `/revisions/{post_id}` | `edit_post` | `class-revision-rest-api.php` (**removed 2.1.0**) |
| GET | `/revisions/render/{revision_id}` | `edit_post` | `class-revision-rest-api.php` (**removed 2.1.0**) |
| GET | `/revisions/diff/{from_id}/{to_id}` | `edit_post` | `class-revision-rest-api.php` (**removed 2.1.0**) |
| POST | `/revisions/restore/{revision_id}` | `edit_post` | `class-revision-rest-api.php` (**removed 2.1.0**) |
| POST | `/query/render` | `read` + nonce | `class-query.php` |
| GET | `/query/preview` | `edit_posts` + nonce | `class-query.php` |
| POST | `/query/filter-register` | `manage_options` + nonce | `class-query.php` |
| GET | `/query/filter-status` | `manage_options` | `class-query.php` |
| POST | `/query/filter-rebuild` | `manage_options` + nonce | `class-query.php` |
| GET | `/query/filters` | `manage_options` | `class-query.php` |
| DELETE | `/query/filters` | `manage_options` + nonce | `class-query.php` |
| GET | `/query/template` | `edit_post` on target | `class-query-template-controller.php` |
| POST | `/query/template` | `edit_posts` | `class-query-template-controller.php` |
| POST | `/form/submit` | Public | `class-form-handler.php` |
| POST | `/gdpr/export` | `manage_options` | `class-gdpr-compliance.php` |
| DELETE | `/gdpr/delete` | `manage_options` | `class-gdpr-compliance.php` |
| GET | `/llms-txt/post-types` | `manage_options` | `class-rest-controller.php` |
| GET | `/llms-txt/status` | `manage_options` | `class-rest-controller.php` |
| GET | `/llms-txt/markdown/{post_id}` | Public | `class-rest-controller.php` |
| POST | `/llms-txt/flush-cache` | `manage_options` | `class-rest-controller.php` |
| POST | `/llms-txt/generate-files` | `manage_options` | `class-rest-controller.php` |
| POST | `/llms-txt/resolve-conflict` | `manage_options` | `class-rest-controller.php` |
| POST | `/llms-txt/dismiss-conflict` | `manage_options` | `class-rest-controller.php` |

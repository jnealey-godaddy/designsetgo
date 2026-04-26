# Draft Mode Extension

Creates a private draft copy of any published page so changes can be edited and previewed without touching the live content. When the draft is ready, publishing it copies the draft's content back to the original page and deletes the draft.

Draft Mode applies to **pages only** (`post_type = page`). It is editor-only in the sense that the panel appears in the block editor sidebar; the underlying mechanism uses standard WordPress drafts accessible via the REST API.

## How it works

A **Draft Mode** panel appears in the document settings sidebar of any page. The panel has four states:

1. **Can create draft** — the page is published and has no active draft. A **Create Draft** button is shown.
2. **Has draft** — a draft copy exists. A link to open the draft in the editor is shown.
3. **Is a draft** — the current post is itself a draft of another page. **Publish changes** and **Discard draft** buttons are shown, along with a link back to the original page.
4. **Unavailable** — the page is not published (feature not applicable).

Creating a draft captures the current editor state (including unsaved title, content, and excerpt) and passes it to the REST endpoint. All block content is sanitized through an extended `wp_kses` filter that preserves legitimate block HTML, CSS custom properties, SVG elements, and block comment delimiters while stripping script tags, event handlers, and `javascript:` URIs.

## Inspector controls

The **Draft Mode** panel is registered as a `PluginDocumentSettingPanel` (document sidebar, not per-block). Confirmation modals appear before creating, publishing, or discarding a draft to prevent accidental changes.

Draft Mode can be enabled or disabled globally in **DesignSetGo Settings → Draft Mode**.

## REST endpoints

All write endpoints require `publish_pages` capability plus a valid `X-WP-Nonce` header (nonce verification added in 2.1.0 security hardening).

| Method | Route | Description |
|---|---|---|
| `POST` | `designsetgo/v1/draft-mode/create` | Create a draft of a published page |
| `POST` | `designsetgo/v1/draft-mode/{id}/publish` | Publish draft — copies content to original |
| `DELETE` | `designsetgo/v1/draft-mode/{id}` | Discard draft |
| `GET` | `designsetgo/v1/draft-mode/status/{post_id}` | Get draft status for a post |

The `GET` status endpoint requires `edit_pages` capability. All write endpoints additionally verify object-level capability for the specific post (`edit_post`, `delete_post`, `publish_post`).

## Frontend behavior

Draft Mode includes an optional frontend preview: logged-in administrators can view draft content on the live URL before publishing. This can be disabled in plugin settings.

## Notes

- Draft Mode was added in 1.4.0. Nonce verification on REST routes was hardened in 2.1.0.
- Only one draft per page is supported. Creating a new draft while one already exists is not permitted until the existing draft is published or discarded.
- The original page remains live and unaffected while a draft is in progress.
- Draft metadata is stored as post meta: `_dsgo_draft_of` (on the draft, pointing to original), `_dsgo_has_draft` (on the original, pointing to draft), and `_dsgo_draft_created` (timestamp).

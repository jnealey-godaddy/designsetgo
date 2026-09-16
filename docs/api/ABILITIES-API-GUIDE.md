# DesignSetGo Abilities API Guide

## Overview

The DesignSetGo Abilities API provides AI-native endpoints for programmatically creating and modifying WordPress content using DesignSetGo blocks.

## Important Note: Block Validation

**Blocks inserted via REST API will show "unexpected or invalid content" warnings in the WordPress editor until the post is saved.**

### Why This Happens

This is a WordPress architectural limitation:

1. **Block save functions are in JavaScript** (client-side)
2. **REST API inserts happen in PHP** (server-side)
3. **Server cannot execute client-side save functions**
4. **WordPress expects saved HTML** to match current save function output

When blocks are inserted via REST API, they contain only attributes (data), not the rendered HTML that WordPress expects. This causes validation warnings.

### How to Resolve

**Option 1: Save in Editor (Recommended for Production)**
1. Insert blocks via REST API
2. Open the post in WordPress editor
3. WordPress will show "Attempt recovery" buttons
4. Click "Attempt recovery" on each block (or save the post)
5. WordPress calls the save functions and generates proper HTML
6. Validation warnings disappear

**Option 2: Publish Without Editor (For AI/Automation)**
- Blocks WILL render correctly on the frontend despite validation warnings
- The warnings only appear in the editor
- Content displays properly to site visitors
- Suitable for fully automated workflows where human review isn't required

### Verification

```bash
# After inserting blocks via API, the frontend will render correctly:
curl http://localhost:8888/?p=POST_ID

# Editor will show validation warnings until saved:
# Open: http://localhost:8888/wp-admin/post.php?post=POST_ID&action=edit
```

## Available Abilities

DesignSetGo provides a focused set of abilities across 3 categories:

| Category | Description |
|----------|-------------|
| **Info** | Discover abilities, list blocks, list extensions, find/get blocks in posts |
| **Inserters** | `add-block` (generic top-level), `add-child-block` (nested), plus child-block inserters for accordion, tabs, timeline |
| **Configurators** | `update-block` (generic), block-specific configurators with custom logic (`configure-shape-divider`, `configure-custom-css`), operations |

### Info Abilities

#### list-abilities
Returns a manifest of all registered abilities with names, descriptions, categories, and input schemas. **Call this first** to discover what's available.

**Endpoint**: `GET /wp-abilities/v1/designsetgo/list-abilities/run`

**Input**:
```json
{
  "input": {
    "category": "all"
  }
}
```

**Output**: Array of ability objects with name, label, description, category, input_schema.

**Filter by category**:
```json
{
  "input": {
    "category": "inserter"
  }
}
```

#### list-blocks
Lists all available DesignSetGo blocks with their metadata.

**Endpoint**: `GET /wp-abilities/v1/designsetgo/list-blocks/run`

**Input**:
```json
{
  "input": {
    "category": "all"
  }
}
```

**Output**: Array of block objects with name, category, description, etc.

#### get-post-blocks
Retrieves all blocks from a post with document-order `blockIndex` values. Essential for targeting blocks with configurators and operations.

**Endpoint**: `GET /wp-abilities/v1/designsetgo/get-post-blocks/run`

**Input**:
```json
{
  "input": {
    "post_id": 123,
    "block_name": "designsetgo/section",
    "include_inner": true,
    "flatten": false
  }
}
```

Only `post_id` is required. Use `block_name` to filter, `include_inner` to include nested blocks, `flatten` to get a flat list.

**Output**: Array of block objects with `blockIndex`, `blockName`, `attrs`, `innerBlocks`.

#### find-blocks
Searches for blocks across multiple posts. Useful for finding all posts that use a specific block type.

**Endpoint**: `GET /wp-abilities/v1/designsetgo/find-blocks/run`

**Input**:
```json
{
  "input": {
    "block_name": "designsetgo/accordion",
    "post_type": "post",
    "post_status": "publish",
    "limit": 10
  }
}
```

Only `block_name` is required. Returns posts containing the specified block with occurrence counts.

#### list-extensions
Returns all DesignSetGo block extensions with their attribute schemas, applicable blocks, and descriptions. Extensions add features like animation, parallax, responsive visibility to existing blocks. Use `update-block` with the extension attribute names to apply them.

**Endpoint**: `GET /wp-abilities/v1/designsetgo/list-extensions/run`

**Input**:
```json
{
  "input": {
    "detail": "full"
  }
}
```

**Output**: Array of extension objects with name, description, applicable_blocks, and attribute schemas. Use the attribute names with `update-block` to apply extensions to blocks.

### Inserter Abilities

#### add-block
Generic top-level inserter that adds any block to a post. Replaces all block-specific inserters.

**Input**:
```json
{
  "input": {
    "post_id": 123,
    "block_name": "designsetgo/flex",
    "position": -1,
    "attributes": {
      "direction": "row",
      "justifyContent": "center",
      "alignItems": "center"
    },
    "innerBlocks": []
  }
}
```

`block_name` is any valid block type (e.g., `designsetgo/flex`, `designsetgo/grid`, `designsetgo/section`, `core/paragraph`). `position` controls where in the post: -1 appends, 0 prepends, N inserts at index.

#### add-child-block (Nested Insertion)
Inserts a block as a child of an existing block. Use `get-post-blocks` first to find the parent's `blockIndex`.

**Input**:
```json
{
  "input": {
    "post_id": 123,
    "parent_block_index": 2,
    "block_name": "core/paragraph",
    "attributes": {
      "content": "Added inside the section"
    },
    "position": -1
  }
}
```

`parent_block_index` is the document-order index from `get-post-blocks`. `position` controls where within the parent: -1 appends, 0 prepends, N inserts at index.

#### add-accordion-item
Adds a new item to an existing accordion block.

**Input**:
```json
{
  "input": {
    "post_id": 123,
    "block_index": 5,
    "title": "New Question",
    "content": "Answer content here"
  }
}
```

#### add-tab
Adds a new tab to an existing tabs block.

**Input**:
```json
{
  "input": {
    "post_id": 123,
    "block_index": 3,
    "title": "New Tab",
    "content": "Tab content here"
  }
}
```

#### add-timeline-item
Adds a new item to an existing timeline block.

**Input**:
```json
{
  "input": {
    "post_id": 123,
    "block_index": 7,
    "title": "2024",
    "content": "Major milestone achieved"
  }
}
```

#### All Inserter Abilities

| Ability | Description |
|---------|-------------|
| `add-block` | Insert any block at the top level of a post |
| `add-child-block` | Insert any block as a child of an existing block |
| `add-accordion-item` | Add an item to an existing accordion |
| `add-tab` | Add a tab to an existing tabs block |
| `add-timeline-item` | Add an item to an existing timeline |

### Configurator Abilities

Configurators modify existing blocks. They are organized into subcategories:

#### Applying Extensions

Extensions (animations, parallax, responsive visibility, background video, clickable groups, etc.) are applied using `list-extensions` for discovery and `update-block` to set the attributes.

**Workflow:**
1. Call `list-extensions` to discover available extensions and their attribute schemas
2. Call `update-block` with the extension's attribute names to apply them to a block

**Example — apply animation to a heading:**
```json
{
  "input": {
    "post_id": 123,
    "block_index": 0,
    "block_name": "core/heading",
    "attributes": {
      "dsgAnimationEnabled": true,
      "dsgEntranceAnimation": "fadeInUp",
      "dsgAnimationTrigger": "scroll",
      "dsgAnimationDuration": 600
    }
  }
}
```

#### Block-Specific Configurators (with custom logic)

##### configure-custom-css
Applies custom CSS to specific blocks with security sanitization.

##### configure-shape-divider
Configures decorative shape dividers on section blocks (see below).

#### Generic Configurator

##### update-block
Generic attribute configurator that works with **any** block. Validates attributes against the block registry. Also used to apply extension attributes (animation, parallax, responsive visibility, etc.) -- use `list-extensions` to discover available extension attribute names and schemas.

**Input**:
```json
{
  "input": {
    "post_id": 123,
    "block_index": 0,
    "block_name": "core/heading",
    "attributes": {
      "level": 2,
      "textAlign": "center"
    }
  }
}
```

Target a block by either `block_index` (document-order from `get-post-blocks`) or `block_client_id`. The `attributes` object accepts any valid attribute from the block's schema.

### Operation Abilities

#### batch-update
Updates multiple blocks in a single API call. Supports up to 20 operations per request.

**Input**:
```json
{
  "input": {
    "post_id": 123,
    "operations": [
      {
        "block_name": "core/heading",
        "attributes": {
          "textAlign": "center"
        }
      },
      {
        "block_name": "designsetgo/section",
        "attributes": {
          "paddingTop": "40px"
        },
        "filter": {
          "backgroundColor": "#ffffff"
        }
      }
    ]
  }
}
```

Each operation targets blocks by `block_name`. The optional `filter` object matches only blocks whose existing attributes match the filter values.

#### delete-block
Removes blocks from a post.

**Input**:
```json
{
  "input": {
    "post_id": 123,
    "block_name": "designsetgo/divider",
    "position": 0,
    "delete_all": false
  }
}
```

Target by `block_name`, `block_client_id`, or `position`. Set `delete_all: true` to remove all matching blocks. This ability has the `destructive` annotation.

## Authentication

Use WordPress Application Passwords for REST API authentication:

```bash
curl -X POST -u "username:app_password" \
  "http://yoursite.com/?rest_route=/wp-abilities/v1/ABILITY_NAME/run" \
  -H "Content-Type: application/json" \
  -d '{"input": {...}}'
```

## Best Practices

1. **Start with `list-abilities` and `list-extensions`**: Discover all available tools and extension attribute schemas before building workflows
2. **Use `get-post-blocks` before modifying**: Get block indices to target specific blocks
3. **Always specify post_id**: Required for all inserter, configurator, and generator abilities
4. **Use position parameter**: Control where blocks are inserted (0=prepend, -1=append, N=specific index)
5. **Use `add-child-block` for nesting**: Don't insert flat blocks when you need hierarchy
6. **Batch when possible**: Use `batch-update` instead of multiple individual configurator calls
7. **Validate on frontend first**: Test that blocks render correctly for visitors
8. **Plan your layout**: Use generators for complete sections, inserters for individual blocks
9. **Test with real content**: Use representative text lengths and images
10. **Review in editor before publishing**: Resolve validation warnings by saving once

## Troubleshooting

### Blocks Show "Unexpected or Invalid Content"
- **Cause**: REST API cannot generate client-side save HTML
- **Solution**: Open post in editor and save once
- **Note**: Frontend renders correctly regardless of validation warnings

### 403 Permission Denied
- **Cause**: Authentication failed or user lacks edit_posts capability
- **Solution**: Check Application Password and user permissions

### 404 Not Found
- **Cause**: Abilities API not registered or wrong endpoint URL
- **Solution**: Ensure plugin is active, check WordPress REST API is enabled

### Invalid Block Structure
- **Cause**: Missing required fields or malformed JSON
- **Solution**: Validate JSON schema, check required fields in documentation

## Example: Complete Landing Page

```bash
#!/bin/bash

POST_ID=123
USER="admin"
PASS="your_app_password"
URL="http://yoursite.com"
API="$URL/?rest_route=/wp-abilities/v1/designsetgo"

# 0. Discover available abilities
curl -X GET -u "$USER:$PASS" \
  "$API/list-abilities/run" \
  -H "Content-Type: application/json" \
  -d '{"input": {"category": "inserter"}}'

# 1. Add a hero section
curl -X POST -u "$USER:$PASS" \
  "$API/add-block/run" \
  -H "Content-Type: application/json" \
  -d "{\"input\": {\"post_id\": $POST_ID, \"block_name\": \"designsetgo/section\", \"attributes\": {\"contentWidth\": \"1140px\"}}}"

# 2. Add a feature grid
curl -X POST -u "$USER:$PASS" \
  "$API/add-block/run" \
  -H "Content-Type: application/json" \
  -d "{\"input\": {\"post_id\": $POST_ID, \"block_name\": \"designsetgo/grid\", \"attributes\": {\"desktopColumns\": 3}}}"

# 3. Add a FAQ accordion
curl -X POST -u "$USER:$PASS" \
  "$API/add-block/run" \
  -H "Content-Type: application/json" \
  -d "{\"input\": {\"post_id\": $POST_ID, \"block_name\": \"designsetgo/accordion\"}}"

# 4. Discover extension attributes, then apply animations via update-block
curl -X GET -u "$USER:$PASS" \
  "$API/list-extensions/run" \
  -H "Content-Type: application/json"

# 5. Batch update: center all headings, add padding to sections, and apply animations
curl -X POST -u "$USER:$PASS" \
  "$API/batch-update/run" \
  -H "Content-Type: application/json" \
  -d "{\"input\": {\"post_id\": $POST_ID, \"operations\": [
    {\"block_name\": \"core/heading\", \"attributes\": {\"textAlign\": \"center\"}},
    {\"block_name\": \"designsetgo/section\", \"attributes\": {\"paddingTop\": \"60px\"}}
  ]}}"

# 6. Verify the blocks were inserted
curl -X GET -u "$USER:$PASS" \
  "$API/get-post-blocks/run" \
  -H "Content-Type: application/json" \
  -d "{\"input\": {\"post_id\": $POST_ID}}"
```

## WordPress Abilities API Reference

For the full official WordPress Abilities API documentation, see:

- [Abilities API Overview](https://developer.wordpress.org/apis/abilities-api/)
- [Getting Started](https://developer.wordpress.org/apis/abilities-api/getting-started/)
- [Hooks Reference](https://developer.wordpress.org/apis/abilities-api/hooks/)
- [PHP Reference](https://developer.wordpress.org/apis/abilities-api/php-reference/)
- [REST API Endpoints](https://developer.wordpress.org/apis/abilities-api/rest-api-endpoints/)

### Key API Details

**Registration hooks** (order matters):
1. `wp_abilities_api_categories_init` — register categories first
2. `wp_abilities_api_init` — register abilities after categories exist

**`wp_register_ability()` key parameters:**
- `category` — top-level parameter (not nested in `meta`)
- `show_in_rest` — top-level parameter, set `true` to expose via REST
- `annotations` — top-level parameter with `readonly`, `destructive`, `idempotent`, `instructions`

**REST endpoint for execution:**
- `GET|POST|DELETE /wp-abilities/v1/{namespace}/{ability}/run`
- HTTP method determined by `annotations`: `readonly` = GET, `destructive` = DELETE, default = POST

**Execution hooks:**
- `wp_before_execute_ability` — fires before ability runs
- `wp_after_execute_ability` — fires after successful execution

## Future Roadmap

- [x] ~~Server-side render callbacks to eliminate validation warnings~~
- [x] ~~Bulk operations (apply settings to multiple blocks at once)~~
- [x] ~~Query abilities (search/filter existing blocks)~~
- [ ] Template abilities (save/load pre-designed layouts)
- [ ] MCP Server integration for direct AI agent access

## Support

- **GitHub Issues**: https://github.com/jnealey-godaddy/designsetgo/issues
- **Documentation**: https://designsetgo.com/docs
- **WordPress Abilities API Handbook**: https://developer.wordpress.org/apis/abilities-api/

---

**Version**: 2.2.0
**Last Updated**: 2026-03-07

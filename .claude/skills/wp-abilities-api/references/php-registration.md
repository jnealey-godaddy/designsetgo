# PHP registration quick guide

Key concepts and entrypoints for the WordPress Abilities API (WP 6.9+).

## Compatibility check

```php
if ( ! class_exists( 'WP_Ability' ) ) {
    // Abilities API not available — skip registration.
    return;
}
```

## Hook order (critical)

**Categories must be registered before abilities.** Use the correct hooks:

1. `wp_abilities_api_categories_init` — Register categories here first.
2. `wp_abilities_api_init` — Register abilities here (after categories exist).

**Warning:** Registering abilities outside `wp_abilities_api_init` triggers `_doing_it_wrong()` and the registration will fail.

```php
// 1. Register category first
add_action( 'wp_abilities_api_categories_init', function( $registry ) {
    wp_register_ability_category( 'my-plugin', array(
        'label'       => __( 'My Plugin', 'my-plugin' ),
        'description' => __( 'Abilities provided by My Plugin.', 'my-plugin' ),
    ) );
} );

// 2. Then register abilities
add_action( 'wp_abilities_api_init', function( $registry ) {
    wp_register_ability( 'my-plugin/get-info', array(
        'label'               => __( 'Get Site Info', 'my-plugin' ),
        'description'         => __( 'Returns basic site information.', 'my-plugin' ),
        'category'            => 'my-plugin',
        'output_schema'       => array(
            'type'       => 'object',
            'properties' => array(
                'title' => array( 'type' => 'string', 'description' => 'The site title' ),
            ),
        ),
        'execute_callback'    => 'my_plugin_get_info_callback',
        'permission_callback' => function() { return current_user_can( 'read' ); },
        'meta'                => array(
            'show_in_rest' => true,
            'annotations'  => array(
                'readonly'     => true,
                'instructions' => 'Returns the site title. No input required.',
            ),
        ),
    ) );
} );
```

## Common primitives

- `wp_register_ability_category( $slug, $args )` — Returns `?\WP_Ability_Category`
- `wp_register_ability( $name, $args )` — Returns `?\WP_Ability`
- `wp_ability_is_registered( $name )` — Check registration status
- `wp_get_ability( $name )` — Retrieve specific ability instance
- `wp_get_abilities()` — Get all registered abilities
- `wp_get_ability_category( $slug )` — Fetch specific category
- `wp_get_ability_categories()` — Retrieve all registered categories

## Key arguments for `wp_register_ability()`

| Argument | Required | Description |
| -------- | -------- | ----------- |
| `label` | Yes | Human-readable name for UI (e.g., command palette) |
| `description` | Yes | What the ability does |
| `category` | Yes | Category slug (must be registered first) |
| `output_schema` | Yes | JSON Schema for returned output |
| `execute_callback` | Yes | Function that executes the ability |
| `permission_callback` | Yes | Returns `true` or `WP_Error` for access control. WP core throws `InvalidArgumentException` if this is missing — there is no implicit default |
| `input_schema` | No | JSON Schema for expected input (enables validation) |
| `meta` | No | Metadata object (contains `show_in_rest`, `mcp`, `annotations`, and custom data) |
| `meta.show_in_rest` | No | Set `true` to expose via the `wp-abilities/v1` REST namespace (default: `false`) |
| `meta.mcp.public` | No | Set `true` to expose the ability as a tool via the bundled WordPress MCP adapter (default: `false`). Independent from `show_in_rest` |
| `meta.mcp.type` | No | One of `'tool'`, `'resource'`, `'prompt'` (default `'tool'`). Values outside this enum silently coerce to `'tool'` |
| `meta.annotations` | No | Behavioral metadata (see below) |
| `ability_class` | No | Custom `WP_Ability` subclass |

## Annotations

Annotations are nested inside the `meta` parameter. They describe ability behavior and determine the REST HTTP method for the `/run` endpoint. The REST controller reads them via `$ability->get_meta_item('annotations')`.

| Key | Type | Description | REST Method |
| --- | ---- | ----------- | ----------- |
| `readonly` | `bool` | Ability only reads data | `GET` |
| `destructive` | `bool` | Ability performs deletions | `DELETE` |
| `idempotent` | `bool` | Repeated calls have no additional effect | — |
| `instructions` | `string` | Custom usage guidance for AI agents | — |

When neither `readonly` nor `destructive` is set, the REST endpoint defaults to `POST`.

The three boolean annotations are *hints* for tooling and documentation — core does not enforce them at runtime, so a missing or `null` value is silently legal. That permissiveness is exactly why every registration should populate them explicitly: MCP / Command Palette / agent surfaces and review tooling reason about ability safety from these values *without* invoking the callback. A `readonly: null` ability is treated as "behavior unknown," which is a worse signal than either `true` or `false`. Treat the absence of an annotation as a bug, not a default.

## `show_in_rest` vs `mcp.public` — they target different surfaces

These two meta keys answer different questions and do not imply each other:

- `show_in_rest` controls visibility on the WordPress core REST namespace `wp-abilities/v1` (the abilities REST API). Clients that talk to that namespace see the ability iff this is `true`.
- `mcp.public` is read by the bundled WordPress MCP adapter package. The adapter's default MCP server only surfaces abilities whose `meta.mcp.public` is strictly `true`. Without it, the ability is registered but invisible to MCP clients connecting through that adapter.

A plugin can set both, either, or neither. If you want the ability discoverable to agents through MCP, set `mcp.public => true`. If you also want it on the abilities REST namespace (for tooling that talks to `wp-abilities/v1` directly), set `show_in_rest => true`. The two surfaces are independent.

## Execution

```php
$ability = wp_get_ability( 'my-plugin/get-info' );
if ( $ability && true === $ability->check_permissions( $input ) ) {
    $result = $ability->execute( $input );
    if ( is_wp_error( $result ) ) {
        // Handle error
    }
}
```

## Recommended patterns

- Use slash-namespaced IDs (e.g., `my-plugin/feature-name`). Treat IDs as a stable API; changing IDs is a breaking change.
- Use `input_schema` and `output_schema` for validation and to help AI agents understand usage.
- Always include a `permission_callback` for abilities that modify data.
- Set `meta.show_in_rest => true` for abilities you want accessible via REST API.
- Use `meta.annotations` to describe behavior — `readonly`, `destructive`, `idempotent`, and `instructions`.

## Hooks

### Actions

| Hook | When | Parameters |
| ---- | ---- | ---------- |
| `wp_abilities_api_categories_init` | Category registry init | `$registry` (`WP_Ability_Categories_Registry`) |
| `wp_abilities_api_init` | Abilities registry init | `$registry` (`WP_Abilities_Registry`) |
| `wp_before_execute_ability` | Before execution (after permissions) | `$ability_name`, `$input` |
| `wp_after_execute_ability` | After successful execution | `$ability_name`, `$input`, `$result` |

### Filters

| Filter | Purpose | Parameters |
| ------ | ------- | ---------- |
| `wp_register_ability_args` | Modify ability args before validation | `$args`, `$ability_name` |
| `wp_register_ability_category_args` | Modify category args before validation | `$args`, `$slug` |

## References

- Abilities API handbook: https://developer.wordpress.org/apis/abilities-api/
- Getting started: https://developer.wordpress.org/apis/abilities-api/getting-started/
- Hooks reference: https://developer.wordpress.org/apis/abilities-api/hooks/
- PHP reference: https://developer.wordpress.org/apis/abilities-api/php-reference/
- REST API endpoints: https://developer.wordpress.org/apis/abilities-api/rest-api-endpoints/

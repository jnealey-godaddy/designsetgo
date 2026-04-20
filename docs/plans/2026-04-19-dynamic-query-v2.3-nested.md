# Dynamic Query v2.3 — "Rival Bricks" Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (or superpowers:subagent-driven-development if staying in this session) to implement this plan task-by-task.

**Goal:** Ship four headline Dynamic Query features — nested loops with parent context, relationship-field source, conditional inner-block visibility, and group-by partitioning — closing the feature gap with Bricks, Elementor Pro, and JetEngine.

**Architecture:**
- **Parent-scoped bindings** — extend `designsetgo/post-meta` + `designsetgo/acf` with an optional `scope` arg (`self` | `parent` | `root`) resolved from a stack pushed by each outer Query's render loop. No new binding sources; no token parser.
- **Relationship source** — new `source: 'relationship'` that reads a parent-context post ID + field name, resolves to `post__in` + `orderby=post__in`. Parent context is a hard requirement; fails gracefully when absent.
- **Conditional visibility** — shared filter attribute `dsgoVisibility` (rules object) registered via `blocks.registerBlockType`. Server-side evaluator in the render helpers short-circuits inner blocks that don't match. Editor mirrors server logic for accurate previews.
- **Group-by** — optional `groupBy` attribute on Query block; render-helpers partition iterated items into buckets and render a user-defined `query-group-header` inner block once per group. Header block is a new sibling in the query family.

**Tech Stack:**
- WordPress 6.5+ Block Bindings API (parent/root scope plumbing).
- PHP 8.0+ with the `WP_Block` + `render_block` pipeline.
- `@wordpress/block-editor` context providers (`BlockContextProvider`) for editor-side nested loop previews.
- IAPI store `designsetgo/query` (existing) — no new frontend JS actions; nested loops inherit pagination/filter behavior per queryId.

---

## Pre-flight

- Branch is already created: `claude/query-v2.3-nested`, based on `origin/main`.
- Worktree: `.worktrees/query-v2.3-nested/`.
- Local dev port: `9451` (`.wp-env.json` already updated, no tests env).
- Before starting, run once in the worktree:

```bash
npm ci
composer install
npx wp-env start
npx wp-env run cli wp theme activate twentytwentyfive
```

Visit `http://localhost:9451/` to confirm WP boots. Admin is `/wp-admin/` (credentials `admin` / `password`).

---

## Phase A — Relationship source

Ships first because it has the smallest blast radius and the biggest single-feature value-per-line: "show posts this post references via an ACF/meta relationship field."

### Task A1: Relationship-source skeleton (PHP render path)

**Why:** Establish the new source value, its defaults, and the dispatcher wiring before touching the UI. This task alone unblocks all subsequent relationship tasks.

**Files:**
- Modify: `src/blocks/query/render-helpers.php` (add `'relationship'` to dispatcher + defaults)
- Modify: `src/blocks/query/block.json` (add `relationship` to `source` enum + new `relationshipField` + `relationshipFallback` attributes)
- Create: `src/blocks/query/render-relationship.php`
- Test: `tests/integration/blocks/query/RelationshipRenderTest.php`

**Step 1: Write the failing PHPUnit test**

```php
<?php
// tests/integration/blocks/query/RelationshipRenderTest.php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_UnitTestCase;

class RelationshipRenderTest extends WP_UnitTestCase {

    public function test_resolves_parent_meta_to_post_ids() {
        $parent  = self::factory()->post->create( array( 'post_title' => 'Parent' ) );
        $child_a = self::factory()->post->create( array( 'post_title' => 'Child A' ) );
        $child_b = self::factory()->post->create( array( 'post_title' => 'Child B' ) );
        update_post_meta( $parent, 'related_posts', array( $child_a, $child_b ) );

        require_once DSGO_PLUGIN_DIR . 'src/blocks/query/render-helpers.php';

        $result = designsetgo_query_render(
            array(
                'source'            => 'relationship',
                'relationshipField' => 'related_posts',
                'postType'          => 'post',
                'perPage'           => 10,
                'tagName'           => 'ul',
                'itemTagName'       => 'li',
            ),
            array(
                'query_id'     => 'rel-1',
                'page'         => 1,
                'inner_html'   => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->',
                'params'       => array(),
                'parent_stack' => array(
                    array( 'postId' => $parent, 'postType' => 'post' ),
                ),
            )
        );

        $this->assertSame( 2, $result['totalItems'] );
        $this->assertStringContainsString( 'dsgo-query__item', $result['html'] );
    }

    public function test_returns_empty_when_parent_stack_missing() {
        $result = designsetgo_query_render(
            array( 'source' => 'relationship', 'relationshipField' => 'x' ),
            array( 'query_id' => 'rel-2', 'page' => 1, 'inner_html' => '', 'params' => array() )
        );
        $this->assertSame( 0, $result['totalItems'] );
        $this->assertSame( '', trim( wp_strip_all_tags( $result['html'] ) ) );
    }
}
```

**Step 2: Run and verify it fails**

```bash
composer test -- --filter=RelationshipRenderTest
```

Expected: FAIL ("source value 'relationship' unsupported" or similar).

**Step 3: Add the dispatcher branch + defaults**

In `src/blocks/query/render-helpers.php::designsetgo_query_render()`, add:

```php
case 'relationship':
    require_once __DIR__ . '/render-relationship.php';
    if ( function_exists( 'designsetgo_query_render_relationship' ) ) {
        return designsetgo_query_render_relationship( $attributes, $context );
    }
    break;
```

In `designsetgo_query_defaults()` add the two new keys:

```php
'relationshipField'    => '',
'relationshipFallback' => 'empty', // empty | all | parent
```

In `src/blocks/query/block.json`, extend:

```json
"source": {
    "type": "string",
    "default": "posts",
    "enum": ["posts", "users", "terms", "manual", "current", "relationship"]
},
"relationshipField":    { "type": "string", "default": "" },
"relationshipFallback": { "type": "string", "default": "empty", "enum": ["empty", "all", "parent"] }
```

**Step 4: Implement the renderer**

Create `src/blocks/query/render-relationship.php`:

```php
<?php
/**
 * Dynamic Query — Relationship source renderer.
 *
 * Reads an ID-bearing field from the nearest parent Query item's
 * post and iterates the referenced posts in their declared order.
 *
 * Supported field storage shapes:
 *  - array of ints or WP_Post objects (ACF relationship)
 *  - comma-separated string of ints ("12, 34, 56")
 *  - serialized array (legacy ACF)
 *  - single int
 *
 * Requires a parent_stack entry; returns empty when the Query
 * block is used outside another Query's item template.
 *
 * @package DesignSetGo
 * @since   2.3.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_render_relationship' ) ) :

    function designsetgo_query_render_relationship( array $atts, array $context ) {
        $field = isset( $atts['relationshipField'] ) ? sanitize_text_field( (string) $atts['relationshipField'] ) : '';
        if ( '' === $field ) {
            return array( 'html' => '', 'totalPages' => 0, 'totalItems' => 0 );
        }

        $parent_stack = isset( $context['parent_stack'] ) && is_array( $context['parent_stack'] )
            ? $context['parent_stack']
            : array();
        $parent = empty( $parent_stack ) ? null : end( $parent_stack );

        $parent_post_id = 0;
        if ( is_array( $parent ) && ! empty( $parent['postId'] ) ) {
            $parent_post_id = (int) $parent['postId'];
        }

        if ( ! $parent_post_id ) {
            return designsetgo_query_relationship_fallback( $atts, $context );
        }

        $raw_value = function_exists( 'get_field' )
            ? get_field( $field, $parent_post_id )
            : get_post_meta( $parent_post_id, $field, true );

        $ids = designsetgo_query_relationship_normalize_ids( $raw_value );
        if ( empty( $ids ) ) {
            return designsetgo_query_relationship_fallback( $atts, $context );
        }

        // Delegate to the Posts renderer — override attributes so WP_Query
        // runs with post__in + orderby=post__in + post_type=any (relationship
        // fields are frequently cross-type).
        require_once __DIR__ . '/render-posts.php';

        $atts_override = array_merge(
            $atts,
            array(
                'source'     => 'manual',
                'manualIds'  => $ids,
                'postType'   => 'any',
                'perPage'    => max( 1, min( count( $ids ), (int) $atts['perPage'] ) ),
            )
        );

        return designsetgo_query_render_posts( $atts_override, $context );
    }

    /**
     * Normalize ACF/meta field values to a list of post IDs.
     */
    function designsetgo_query_relationship_normalize_ids( $value ) {
        if ( is_array( $value ) ) {
            $ids = array();
            foreach ( $value as $v ) {
                if ( $v instanceof \WP_Post ) {
                    $ids[] = (int) $v->ID;
                } elseif ( is_numeric( $v ) ) {
                    $ids[] = (int) $v;
                }
            }
            return array_values( array_filter( $ids ) );
        }
        if ( is_string( $value ) && '' !== $value ) {
            if ( str_contains( $value, ',' ) ) {
                return array_values( array_filter( array_map( 'absint', explode( ',', $value ) ) ) );
            }
            if ( is_numeric( $value ) ) {
                return array( (int) $value );
            }
            $unserialized = maybe_unserialize( $value );
            if ( is_array( $unserialized ) ) {
                return designsetgo_query_relationship_normalize_ids( $unserialized );
            }
        }
        if ( is_numeric( $value ) ) {
            return array( (int) $value );
        }
        return array();
    }

    /**
     * Empty / all / parent fallback for no-result relationship queries.
     */
    function designsetgo_query_relationship_fallback( array $atts, array $context ) {
        $mode = isset( $atts['relationshipFallback'] ) ? (string) $atts['relationshipFallback'] : 'empty';

        if ( 'empty' === $mode ) {
            return array( 'html' => designsetgo_query_wrap( '', $atts, $context, $context['wrapper_attrs'] ?? null ), 'totalPages' => 0, 'totalItems' => 0 );
        }

        require_once __DIR__ . '/render-posts.php';

        if ( 'all' === $mode ) {
            $atts['source'] = 'posts';
            return designsetgo_query_render_posts( $atts, $context );
        }

        // 'parent' fallback: render a single item using the parent post itself.
        $parent = null;
        if ( isset( $context['parent_stack'] ) && is_array( $context['parent_stack'] ) ) {
            $parent = end( $context['parent_stack'] );
        }
        if ( ! is_array( $parent ) || empty( $parent['postId'] ) ) {
            return array( 'html' => '', 'totalPages' => 0, 'totalItems' => 0 );
        }
        $atts['source']    = 'manual';
        $atts['manualIds'] = array( (int) $parent['postId'] );
        $atts['perPage']   = 1;
        return designsetgo_query_render_posts( $atts, $context );
    }

endif;
```

**Step 5: Run and verify tests pass**

```bash
composer test -- --filter=RelationshipRenderTest
```

Expected: 2/2 PASS.

**Step 6: Commit**

```bash
git add src/blocks/query/block.json src/blocks/query/render-helpers.php src/blocks/query/render-relationship.php tests/integration/blocks/query/RelationshipRenderTest.php
git commit -m "feat(query): add relationship source reading parent post field"
```

---

### Task A2: Relationship inspector UI

**Why:** Expose `relationshipField` + `relationshipFallback` in the Settings panel so users can pick it up without editing block attributes by hand.

**Files:**
- Modify: `src/blocks/query/components/QuerySourcePanel.js` (or wherever source-dependent controls live)
- Test: `tests/unit/blocks/query/edit.test.js` (extend existing suite)

**Step 1: Locate the existing source switch**

```bash
grep -n "source === 'users'" src/blocks/query/components/*.js
grep -n "SelectControl" src/blocks/query/components/QuerySourcePanel.js | head -5
```

Read the file so you see how existing source-gated controls render.

**Step 2: Write the failing Jest test**

Add to `tests/unit/blocks/query/edit.test.js`:

```js
it('renders the relationship field input when source is relationship', () => {
    renderWith({ source: 'relationship' });
    expect(screen.getByLabelText(/relationship field/i)).toBeInTheDocument();
});

it('renders the fallback select when source is relationship', () => {
    renderWith({ source: 'relationship' });
    expect(screen.getByLabelText(/when no related items/i)).toBeInTheDocument();
});

it('does not render relationship field input when source is posts', () => {
    renderWith({ source: 'posts' });
    expect(screen.queryByLabelText(/relationship field/i)).not.toBeInTheDocument();
});
```

**Step 3: Run and verify tests fail**

```bash
npm run test:unit -- --testPathPattern=query/edit.test.js
```

Expected: 3 new tests FAIL.

**Step 4: Wire the controls**

In `QuerySourcePanel.js`, add:

```js
if (source === 'relationship') {
    return (
        <>
            <TextControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Relationship field', 'designsetgo')}
                help={__('Meta key or ACF field on the parent item that holds the related post IDs.', 'designsetgo')}
                value={attributes.relationshipField || ''}
                onChange={(v) => setAttributes({ relationshipField: v })}
            />
            <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('When no related items', 'designsetgo')}
                value={attributes.relationshipFallback || 'empty'}
                onChange={(v) => setAttributes({ relationshipFallback: v })}
                options={[
                    { value: 'empty',  label: __('Render no items', 'designsetgo') },
                    { value: 'all',    label: __('Fall back to all posts', 'designsetgo') },
                    { value: 'parent', label: __('Render the parent item', 'designsetgo') },
                ]}
            />
        </>
    );
}
```

Extend the source `SelectControl` options list to include `{ value: 'relationship', label: __('Related items (field-driven)', 'designsetgo') }`.

**Step 5: Verify tests pass**

```bash
npm run test:unit -- --testPathPattern=query/edit.test.js
```

Expected: all tests PASS.

**Step 6: Commit**

```bash
git add src/blocks/query/components/QuerySourcePanel.js tests/unit/blocks/query/edit.test.js
git commit -m "feat(query): inspector controls for relationship source"
```

---

### Task A3: Editor preview parity for relationship source

**Why:** v2.2 added editor live preview via `useEntityRecords`. Relationship source needs a preview path so users see "this is what it'll render" instead of a blank placeholder.

**Files:**
- Modify: `src/blocks/query/hooks/useQueryPreview.js` (the hook added in v2.2 for editor previews)
- Test: `tests/unit/blocks/query/useQueryPreview.test.js`

**Step 1: Write the failing test**

```js
// tests/unit/blocks/query/useQueryPreview.test.js
import { renderHook } from '@testing-library/react';
import useQueryPreview from '../../../../src/blocks/query/hooks/useQueryPreview';

jest.mock('@wordpress/core-data', () => ({
    store: 'core',
    useEntityRecords: jest.fn(),
}));

const { useEntityRecords } = require('@wordpress/core-data');

describe('useQueryPreview — relationship source', () => {
    beforeEach(() => useEntityRecords.mockReset());

    it('returns empty state when relationshipField is empty', () => {
        useEntityRecords.mockReturnValue({ records: [], hasResolved: true });
        const { result } = renderHook(() =>
            useQueryPreview({ source: 'relationship', relationshipField: '', perPage: 3 })
        );
        expect(result.current.records).toEqual([]);
        expect(result.current.hasResolved).toBe(true);
    });
});
```

**Step 2: Run and verify it fails**

```bash
npm run test:unit -- --testPathPattern=useQueryPreview
```

Expected: FAIL (hook doesn't recognize `relationship` source yet).

**Step 3: Add the relationship branch**

In `useQueryPreview.js`, add a source switch that returns `{ records: [], hasResolved: true }` for `'relationship'` when `relationshipField` is empty, and — when present — falls through to `useEntityRecords('postType', 'post', { include: manualIds, per_page: perPage })` using the editor's current post ID as parent. Use `useSelect` + `getEditedPostAttribute('meta')` to read the field value.

```js
if (source === 'relationship') {
    const parentId = useSelect((s) => s('core/editor').getCurrentPostId(), []);
    const fieldValue = useSelect(
        (s) => (parentId ? s('core').getEntityRecord('postType', 'post', parentId)?.meta?.[relationshipField] : null),
        [parentId, relationshipField]
    );
    const ids = Array.isArray(fieldValue)
        ? fieldValue.map((v) => (typeof v === 'object' ? v.ID : Number(v))).filter(Boolean)
        : [];
    const { records, hasResolved } = useEntityRecords('postType', 'any', {
        include: ids.length ? ids : [0],
        per_page: Math.max(1, perPage),
        orderby: 'include',
    });
    return { records: ids.length ? records : [], hasResolved };
}
```

**Step 4: Run tests**

```bash
npm run test:unit -- --testPathPattern=useQueryPreview
```

Expected: PASS.

**Step 5: Commit**

```bash
git add src/blocks/query/hooks/useQueryPreview.js tests/unit/blocks/query/useQueryPreview.test.js
git commit -m "feat(query): editor preview for relationship source"
```

---

### Task A4: Relationship manual QA

**Why:** An integration test can't validate "does the admin feel right when I pick a field." Run the feature end-to-end once before moving on.

**Steps:**

1. `npm run build`
2. In the editor, create a new Post titled "Parent".
3. Add a custom field (`related_posts`) with value `12,34` (IDs of two real posts in the site).
4. Add a Dynamic Query block, set source → Relationship, field → `related_posts`.
5. Confirm the editor preview shows both referenced posts.
6. Save and view on the frontend. Confirm same two items render.
7. Change `relationshipFallback` to "all posts", clear the meta value, and re-check that all posts now show.

No commit for this task; log observations in the PR description.

---

## Phase B — Conditional inner-block visibility

A compound feature: a shared attribute registered on every block, server-side evaluation in the render helpers, editor-side evaluation that matches.

### Task B1: `dsgoVisibility` attribute registration

**Why:** WP's Block Editor needs to know every block can carry the attribute before we write UI or server logic.

**Files:**
- Create: `src/extensions/visibility/index.js`
- Create: `src/extensions/visibility/filters.js`
- Modify: `src/index.js` (import the extension)
- Test: `tests/unit/extensions/visibility/attribute.test.js`

**Step 1: Write the failing test**

```js
// tests/unit/extensions/visibility/attribute.test.js
import { applyFilters } from '@wordpress/hooks';
import '../../../../src/extensions/visibility';

describe('dsgoVisibility attribute filter', () => {
    it('adds the attribute to an allowed block', () => {
        const settings = applyFilters(
            'blocks.registerBlockType',
            { attributes: {} },
            'core/paragraph'
        );
        expect(settings.attributes.dsgoVisibility).toEqual({
            type: 'object',
            default: null,
        });
    });

    it('leaves unsupported blocks untouched', () => {
        const settings = applyFilters(
            'blocks.registerBlockType',
            { attributes: {} },
            'core/freeform'
        );
        expect(settings.attributes.dsgoVisibility).toBeUndefined();
    });
});
```

**Step 2: Run and verify it fails**

```bash
npm run test:unit -- --testPathPattern=visibility/attribute
```

Expected: FAIL — `dsgoVisibility` undefined.

**Step 3: Register the filter**

`src/extensions/visibility/filters.js`:

```js
import { addFilter } from '@wordpress/hooks';

const BLOCKED = new Set([
    'core/freeform',
    'core/missing',
    'core/template-part',
]);

function addVisibilityAttribute(settings, name) {
    if (BLOCKED.has(name)) return settings;
    if (!settings.attributes) settings.attributes = {};
    settings.attributes.dsgoVisibility = { type: 'object', default: null };
    return settings;
}

addFilter(
    'blocks.registerBlockType',
    'designsetgo/visibility/add-attribute',
    addVisibilityAttribute
);
```

`src/extensions/visibility/index.js`:

```js
import './filters';
```

Import in `src/index.js`:

```js
import './extensions/visibility';
```

**Step 4: Run tests**

```bash
npm run test:unit -- --testPathPattern=visibility/attribute
```

Expected: PASS.

**Step 5: Commit**

```bash
git add src/extensions/visibility src/index.js tests/unit/extensions/visibility
git commit -m "feat(visibility): register dsgoVisibility attribute on all blocks"
```

---

### Task B2: Rule-matching engine (server)

**Why:** Provide a pure, unit-testable evaluator that answers "given these rules and this context, should this block render?" Kept isolated from the Query block so other features can reuse it later.

**Files:**
- Create: `includes/class-block-visibility.php`
- Modify: `includes/class-plugin.php` (instantiate)
- Test: `tests/integration/BlockVisibilityTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/integration/BlockVisibilityTest.php
namespace DesignSetGo\Tests\Integration;

use DesignSetGo\BlockVisibility;
use WP_UnitTestCase;

class BlockVisibilityTest extends WP_UnitTestCase {

    public function test_null_rules_always_visible() {
        $this->assertTrue( BlockVisibility::matches( null, array( 'postId' => 1 ) ) );
        $this->assertTrue( BlockVisibility::matches( array(), array() ) );
    }

    public function test_meta_equals_rule() {
        $post_id = self::factory()->post->create();
        update_post_meta( $post_id, 'featured', '1' );

        $rules = array(
            'operator' => 'AND',
            'rules'    => array(
                array( 'type' => 'meta', 'key' => 'featured', 'op' => 'equals', 'value' => '1' ),
            ),
        );
        $this->assertTrue( BlockVisibility::matches( $rules, array( 'postId' => $post_id ) ) );

        update_post_meta( $post_id, 'featured', '0' );
        $this->assertFalse( BlockVisibility::matches( $rules, array( 'postId' => $post_id ) ) );
    }

    public function test_taxonomy_has_rule() {
        $post_id = self::factory()->post->create();
        $term_id = self::factory()->term->create( array( 'taxonomy' => 'category', 'slug' => 'news' ) );
        wp_set_post_terms( $post_id, array( $term_id ), 'category' );

        $rules = array(
            'operator' => 'AND',
            'rules'    => array(
                array( 'type' => 'taxonomy', 'taxonomy' => 'category', 'op' => 'has', 'value' => 'news' ),
            ),
        );
        $this->assertTrue( BlockVisibility::matches( $rules, array( 'postId' => $post_id ) ) );
    }

    public function test_index_rule() {
        $rules = array(
            'operator' => 'AND',
            'rules'    => array(
                array( 'type' => 'index', 'op' => 'equals', 'value' => 0 ),
            ),
        );
        $this->assertTrue( BlockVisibility::matches( $rules, array( 'postId' => 1, 'index' => 0 ) ) );
        $this->assertFalse( BlockVisibility::matches( $rules, array( 'postId' => 1, 'index' => 3 ) ) );
    }

    public function test_or_relation() {
        $rules = array(
            'operator' => 'OR',
            'rules'    => array(
                array( 'type' => 'index', 'op' => 'equals', 'value' => 0 ),
                array( 'type' => 'index', 'op' => 'equals', 'value' => 2 ),
            ),
        );
        $this->assertTrue( BlockVisibility::matches( $rules, array( 'index' => 0 ) ) );
        $this->assertTrue( BlockVisibility::matches( $rules, array( 'index' => 2 ) ) );
        $this->assertFalse( BlockVisibility::matches( $rules, array( 'index' => 1 ) ) );
    }
}
```

**Step 2: Run and verify it fails**

```bash
composer test -- --filter=BlockVisibilityTest
```

Expected: FAIL — class not found.

**Step 3: Implement the evaluator**

`includes/class-block-visibility.php`:

```php
<?php
/**
 * Shared rule evaluator for the dsgoVisibility attribute.
 *
 * Pure static methods — no WordPress hooks, no state. Consumers
 * (render helpers, REST endpoints) pass a rules array and a
 * per-item context; the evaluator returns bool.
 *
 * @package DesignSetGo
 * @since   2.3.0
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

class BlockVisibility {

    public static function matches( $rules, array $context ) {
        if ( empty( $rules ) || ! is_array( $rules ) || empty( $rules['rules'] ) ) {
            return true;
        }

        $operator = isset( $rules['operator'] ) && 'OR' === strtoupper( (string) $rules['operator'] ) ? 'OR' : 'AND';

        foreach ( (array) $rules['rules'] as $rule ) {
            $matched = self::evaluate_rule( (array) $rule, $context );
            if ( 'OR' === $operator && $matched ) {
                return true;
            }
            if ( 'AND' === $operator && ! $matched ) {
                return false;
            }
        }
        return 'AND' === $operator;
    }

    private static function evaluate_rule( array $rule, array $context ) {
        $type = isset( $rule['type'] ) ? (string) $rule['type'] : '';
        switch ( $type ) {
            case 'meta':     return self::evaluate_meta( $rule, $context );
            case 'taxonomy': return self::evaluate_taxonomy( $rule, $context );
            case 'index':    return self::evaluate_index( $rule, $context );
            case 'auth':     return self::evaluate_auth( $rule );
        }

        /**
         * Filter to add custom rule types.
         *
         * @param bool|null $match   Return bool to short-circuit; null to fall through.
         * @param array     $rule
         * @param array     $context
         */
        $filtered = apply_filters( 'designsetgo_visibility_rule', null, $rule, $context );
        return (bool) $filtered;
    }

    private static function evaluate_meta( array $rule, array $context ) {
        $post_id = isset( $context['postId'] ) ? (int) $context['postId'] : 0;
        $key     = isset( $rule['key'] ) ? sanitize_text_field( (string) $rule['key'] ) : '';
        if ( ! $post_id || '' === $key ) {
            return false;
        }
        $actual = get_post_meta( $post_id, $key, true );
        return self::compare( $actual, $rule['op'] ?? 'equals', $rule['value'] ?? '' );
    }

    private static function evaluate_taxonomy( array $rule, array $context ) {
        $post_id  = isset( $context['postId'] ) ? (int) $context['postId'] : 0;
        $taxonomy = isset( $rule['taxonomy'] ) ? sanitize_key( (string) $rule['taxonomy'] ) : '';
        if ( ! $post_id || '' === $taxonomy ) {
            return false;
        }
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return 'not_has' === ( $rule['op'] ?? 'has' );
        }
        $slugs = wp_list_pluck( $terms, 'slug' );
        $needle = sanitize_title( (string) ( $rule['value'] ?? '' ) );
        $contains = in_array( $needle, $slugs, true );
        return 'not_has' === ( $rule['op'] ?? 'has' ) ? ! $contains : $contains;
    }

    private static function evaluate_index( array $rule, array $context ) {
        $actual = isset( $context['index'] ) ? (int) $context['index'] : -1;
        return self::compare( $actual, $rule['op'] ?? 'equals', (int) ( $rule['value'] ?? 0 ) );
    }

    private static function evaluate_auth( array $rule ) {
        $expect = isset( $rule['value'] ) ? (bool) $rule['value'] : true;
        return is_user_logged_in() === $expect;
    }

    private static function compare( $actual, $op, $expected ) {
        switch ( $op ) {
            case 'not_equals': return (string) $actual !== (string) $expected;
            case 'contains':   return false !== stripos( (string) $actual, (string) $expected );
            case 'gt':         return (float) $actual > (float) $expected;
            case 'lt':         return (float) $actual < (float) $expected;
            case 'empty':      return '' === (string) $actual || null === $actual;
            case 'not_empty':  return '' !== (string) $actual && null !== $actual;
            case 'equals':
            default:           return (string) $actual === (string) $expected;
        }
    }
}
```

**Step 4: Register in the plugin class**

In `includes/class-plugin.php`, add to the requires list:

```php
require_once DSGO_PLUGIN_DIR . 'includes/class-block-visibility.php';
```

(No instantiation needed — it's a static-only class.)

**Step 5: Run tests**

```bash
composer test -- --filter=BlockVisibilityTest
```

Expected: 5/5 PASS.

**Step 6: Commit**

```bash
git add includes/class-block-visibility.php includes/class-plugin.php tests/integration/BlockVisibilityTest.php
git commit -m "feat(visibility): rule-matching engine with AND/OR and meta/tax/index/auth rule types"
```

---

### Task B3: Server-side application inside query items

**Why:** The evaluator exists; now wire it into the one place that iterates items: `designsetgo_query_render_item()`.

**Files:**
- Modify: `src/blocks/query/render-helpers.php` (add visibility check in the item renderer)
- Test: `tests/integration/blocks/query/VisibilityIntegrationTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/integration/blocks/query/VisibilityIntegrationTest.php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_UnitTestCase;

class VisibilityIntegrationTest extends WP_UnitTestCase {

    public function test_hides_block_when_rule_does_not_match() {
        $post_id = self::factory()->post->create();
        update_post_meta( $post_id, 'featured', '0' );

        require_once DSGO_PLUGIN_DIR . 'src/blocks/query/render-helpers.php';

        $inner_html = '<!-- wp:paragraph {"dsgoVisibility":{"operator":"AND","rules":[{"type":"meta","key":"featured","op":"equals","value":"1"}]}} -->'
                    . '<p>Featured badge</p>'
                    . '<!-- /wp:paragraph -->'
                    . '<!-- wp:paragraph --><p>Always shown</p><!-- /wp:paragraph -->';

        $html = designsetgo_query_render_item(
            $inner_html,
            array( 'postId' => $post_id, 'postType' => 'post', 'index' => 0 ),
            'li'
        );

        $this->assertStringNotContainsString( 'Featured badge', $html );
        $this->assertStringContainsString( 'Always shown', $html );
    }
}
```

**Step 2: Run and verify it fails**

```bash
composer test -- --filter=VisibilityIntegrationTest
```

Expected: FAIL — both paragraphs render.

**Step 3: Apply visibility in the item renderer**

In `designsetgo_query_render_item()`, before calling `$block_instance->render()`:

```php
$visibility = isset( $parsed_block['attrs']['dsgoVisibility'] ) ? $parsed_block['attrs']['dsgoVisibility'] : null;
if ( ! \DesignSetGo\BlockVisibility::matches( $visibility, $item_context ) ) {
    continue;
}
```

**Step 4: Verify tests pass**

```bash
composer test -- --filter=VisibilityIntegrationTest
```

Expected: PASS.

**Step 5: Commit**

```bash
git add src/blocks/query/render-helpers.php tests/integration/blocks/query/VisibilityIntegrationTest.php
git commit -m "feat(visibility): apply rules when rendering query items"
```

---

### Task B4: Visibility controls in the block inspector

**Why:** The attribute and engine exist; now an author needs a UI to set rules without hand-editing block markup.

**Files:**
- Create: `src/extensions/visibility/VisibilityPanel.js`
- Create: `src/extensions/visibility/RuleRow.js`
- Modify: `src/extensions/visibility/filters.js` (inject panel into BlockEdit)
- Test: `tests/unit/extensions/visibility/VisibilityPanel.test.js`

**Step 1: Write the failing test**

```js
// tests/unit/extensions/visibility/VisibilityPanel.test.js
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import VisibilityPanel from '../../../../src/extensions/visibility/VisibilityPanel';

// Mocks mirroring src/blocks/query/edit.test.js conventions
jest.mock('@wordpress/i18n', () => ({ __: (t) => t, sprintf: (t, a) => t.replace('%s', a) }));
jest.mock('@wordpress/components', () => ({
    SelectControl: ({ label, value, onChange, options }) => (
        <label>{label}<select value={value} onChange={(e) => onChange(e.target.value)}>{options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}</select></label>
    ),
    TextControl: ({ label, value, onChange }) => (
        <label>{label}<input value={value || ''} onChange={(e) => onChange(e.target.value)} /></label>
    ),
    Button: ({ children, onClick }) => <button onClick={onClick}>{children}</button>,
    __experimentalVStack: ({ children }) => <div>{children}</div>,
}));

describe('VisibilityPanel', () => {
    it('shows empty state when no rules exist', () => {
        render(<VisibilityPanel value={null} onChange={jest.fn()} />);
        expect(screen.getByText(/always visible/i)).toBeInTheDocument();
    });

    it('renders a row per rule', () => {
        const value = {
            operator: 'AND',
            rules: [{ type: 'meta', key: 'foo', op: 'equals', value: 'bar' }],
        };
        render(<VisibilityPanel value={value} onChange={jest.fn()} />);
        expect(screen.getByDisplayValue('foo')).toBeInTheDocument();
    });

    it('calls onChange with a new rule when Add clicked', () => {
        const onChange = jest.fn();
        render(<VisibilityPanel value={null} onChange={onChange} />);
        fireEvent.click(screen.getByText(/add rule/i));
        expect(onChange).toHaveBeenCalledWith(expect.objectContaining({
            operator: 'AND',
            rules: expect.arrayContaining([expect.objectContaining({ type: 'meta' })]),
        }));
    });
});
```

**Step 2: Run and verify it fails**

```bash
npm run test:unit -- --testPathPattern=VisibilityPanel.test
```

Expected: FAIL — component not found.

**Step 3: Build the panel**

Keep both new files under 200 lines. The Panel renders:
- A relation selector (AND / OR) — only shown when `rules.length > 1`.
- One `RuleRow` per rule with type picker (meta / taxonomy / index / auth), fields relevant to the type, op picker, value input.
- An "Add rule" button appending a default `{ type: 'meta', op: 'equals', key: '', value: '' }`.
- A "Remove" button per row.

The panel lives inside `<InspectorControls group="advanced">` via a `BlockEdit` HOC filter in `filters.js`:

```js
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { Fragment } from '@wordpress/element';
import VisibilityPanel from './VisibilityPanel';

const withVisibilityPanel = createHigherOrderComponent((BlockEdit) => (props) => {
    if (BLOCKED.has(props.name)) return <BlockEdit {...props} />;
    return (
        <Fragment>
            <BlockEdit {...props} />
            <InspectorControls group="advanced">
                <VisibilityPanel
                    value={props.attributes.dsgoVisibility}
                    onChange={(value) => props.setAttributes({ dsgoVisibility: value })}
                />
            </InspectorControls>
        </Fragment>
    );
}, 'withDsgoVisibilityPanel');

addFilter('editor.BlockEdit', 'designsetgo/visibility/inspector', withVisibilityPanel);
```

**Step 4: Run the tests**

```bash
npm run test:unit -- --testPathPattern=VisibilityPanel.test
```

Expected: PASS.

**Step 5: Commit**

```bash
git add src/extensions/visibility tests/unit/extensions/visibility
git commit -m "feat(visibility): inspector panel for configuring rules"
```

---

### Task B5: Editor-preview visibility evaluator

**Why:** Server hides blocks that don't match; editor should do the same so previews are honest. Otherwise users see their "Featured" badge in the editor but never on the frontend.

**Files:**
- Create: `src/extensions/visibility/evaluateRules.js` (JS mirror of PHP evaluator)
- Modify: `src/extensions/visibility/filters.js` (wrap BlockListBlock to hide children in item preview contexts)
- Test: `tests/unit/extensions/visibility/evaluateRules.test.js`

**Step 1: Write the failing test**

```js
// tests/unit/extensions/visibility/evaluateRules.test.js
import evaluateRules from '../../../../src/extensions/visibility/evaluateRules';

describe('evaluateRules', () => {
    it('defaults visible when rules null', () => {
        expect(evaluateRules(null, { postId: 1 })).toBe(true);
    });

    it('meta equals', () => {
        const ctx = { meta: { featured: '1' } };
        expect(evaluateRules({ operator: 'AND', rules: [{ type: 'meta', key: 'featured', op: 'equals', value: '1' }] }, ctx)).toBe(true);
        expect(evaluateRules({ operator: 'AND', rules: [{ type: 'meta', key: 'featured', op: 'equals', value: '0' }] }, ctx)).toBe(false);
    });

    it('index equals', () => {
        expect(evaluateRules({ operator: 'AND', rules: [{ type: 'index', op: 'equals', value: 0 }] }, { index: 0 })).toBe(true);
    });

    it('OR relation', () => {
        const rules = { operator: 'OR', rules: [{ type: 'index', op: 'equals', value: 0 }, { type: 'index', op: 'equals', value: 1 }] };
        expect(evaluateRules(rules, { index: 1 })).toBe(true);
        expect(evaluateRules(rules, { index: 5 })).toBe(false);
    });
});
```

**Step 2: Run and verify it fails**

```bash
npm run test:unit -- --testPathPattern=evaluateRules.test
```

Expected: FAIL — module not found.

**Step 3: Implement the mirror**

`src/extensions/visibility/evaluateRules.js`:

```js
/**
 * JS mirror of DesignSetGo\BlockVisibility::matches — keep in sync.
 *
 * @param {object|null} rules   The dsgoVisibility attribute.
 * @param {object}      context Per-item: { postId, postType, index, meta, terms }.
 */
export default function evaluateRules(rules, context = {}) {
    if (!rules || !rules.rules?.length) return true;

    const op = (rules.operator || 'AND').toUpperCase();
    for (const rule of rules.rules) {
        const ok = evaluate(rule, context);
        if (op === 'OR' && ok) return true;
        if (op === 'AND' && !ok) return false;
    }
    return op === 'AND';
}

function evaluate(rule, ctx) {
    const { type, op = 'equals', value } = rule;
    switch (type) {
        case 'meta':     return compare(ctx.meta?.[rule.key], op, value);
        case 'taxonomy': {
            const slugs = ctx.terms?.[rule.taxonomy] || [];
            const has = slugs.includes(String(value));
            return op === 'not_has' ? !has : has;
        }
        case 'index':    return compare(ctx.index, op, Number(value));
        case 'auth':     return !!ctx.isAuthenticated === !!value;
        default:         return false;
    }
}

function compare(actual, op, expected) {
    switch (op) {
        case 'not_equals': return String(actual) !== String(expected);
        case 'contains':   return String(actual).toLowerCase().includes(String(expected).toLowerCase());
        case 'gt':         return Number(actual) > Number(expected);
        case 'lt':         return Number(actual) < Number(expected);
        case 'empty':      return actual === '' || actual == null;
        case 'not_empty':  return actual !== '' && actual != null;
        case 'equals':
        default:           return String(actual) === String(expected);
    }
}
```

Then in `filters.js`, add a block-list-block wrapper that consults `BlockContext` for `designsetgo/itemMeta`, `designsetgo/itemTerms`, `designsetgo/itemIndex` (provided in Phase C by the Query edit component) and short-circuits rendering when `evaluateRules()` returns false.

**Step 4: Run tests**

```bash
npm run test:unit -- --testPathPattern=evaluateRules.test
```

Expected: PASS.

**Step 5: Commit**

```bash
git add src/extensions/visibility tests/unit/extensions/visibility/evaluateRules.test.js
git commit -m "feat(visibility): editor preview hides non-matching blocks in query items"
```

---

## Phase C — Nested loops with parent context

### Task C1: Parent-context stack in render pipeline

**Why:** Bindings and the relationship source both depend on being able to ask "what's the outer Query's current item?" This task adds that stack; nothing else works without it.

**Files:**
- Modify: `src/blocks/query/render-helpers.php` (push/pop around `designsetgo_query_render_item()`)
- Modify: `src/blocks/query/render-posts.php`, `render-users.php`, `render-terms.php` (pass `parent_stack` through in the per-item inner call)
- Test: `tests/integration/blocks/query/ParentStackTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/integration/blocks/query/ParentStackTest.php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_UnitTestCase;

class ParentStackTest extends WP_UnitTestCase {

    public function test_stack_is_pushed_and_popped_per_item() {
        $posts = self::factory()->post->create_many( 2 );

        // Accumulate stack snapshots captured inside a custom inner block.
        $GLOBALS['dsgo_stack_capture'] = array();
        add_action( 'render_block', function ( $html, $block ) {
            if ( ! empty( $block['blockName'] ) && 'core/paragraph' === $block['blockName'] ) {
                $GLOBALS['dsgo_stack_capture'][] = $GLOBALS['designsetgo_parent_stack'] ?? array();
            }
            return $html;
        }, 10, 2 );

        require_once DSGO_PLUGIN_DIR . 'src/blocks/query/render-helpers.php';
        designsetgo_query_render(
            array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 2, 'tagName' => 'ul', 'itemTagName' => 'li' ),
            array( 'query_id' => 'c1', 'page' => 1, 'inner_html' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', 'params' => array() )
        );

        $this->assertCount( 2, $GLOBALS['dsgo_stack_capture'] );
        $this->assertSame( $posts[0], $GLOBALS['dsgo_stack_capture'][0][0]['postId'] );
        $this->assertSame( $posts[1], $GLOBALS['dsgo_stack_capture'][1][0]['postId'] );
        $this->assertArrayNotHasKey( 'designsetgo_parent_stack', $GLOBALS ); // popped after render
    }
}
```

**Step 2: Run and verify it fails**

```bash
composer test -- --filter=ParentStackTest
```

Expected: FAIL — global is never set.

**Step 3: Push/pop the stack in `designsetgo_query_render_item()`**

```php
function designsetgo_query_render_item( $inner_html, array $item_context, $item_tag ) {
    $tag    = in_array( $item_tag, array( 'li', 'div', 'article' ), true ) ? $item_tag : 'li';
    $html   = '';
    $parsed = parse_blocks( $inner_html );

    if ( ! isset( $GLOBALS['designsetgo_parent_stack'] ) || ! is_array( $GLOBALS['designsetgo_parent_stack'] ) ) {
        $GLOBALS['designsetgo_parent_stack'] = array();
    }
    array_push( $GLOBALS['designsetgo_parent_stack'], $item_context );

    try {
        foreach ( $parsed as $parsed_block ) {
            if ( empty( $parsed_block['blockName'] ) ) continue;
            $visibility = $parsed_block['attrs']['dsgoVisibility'] ?? null;
            if ( ! \DesignSetGo\BlockVisibility::matches( $visibility, $item_context ) ) continue;
            $block_instance = new \WP_Block( $parsed_block, $item_context );
            $html          .= $block_instance->render();
        }
    } finally {
        array_pop( $GLOBALS['designsetgo_parent_stack'] );
        if ( empty( $GLOBALS['designsetgo_parent_stack'] ) ) {
            unset( $GLOBALS['designsetgo_parent_stack'] );
        }
    }

    return sprintf( '<%1$s class="dsgo-query__item">%2$s</%1$s>', $tag, $html );
}
```

Also in each `render-*.php`, add `$context['parent_stack'] = $GLOBALS['designsetgo_parent_stack'] ?? array();` before calling `designsetgo_query_render_item()` via the nested render dispatcher. (This lets nested queries know their ancestors without reading a global directly — context remains explicit.)

**Step 4: Verify tests pass**

```bash
composer test -- --filter=ParentStackTest
```

Expected: PASS.

**Step 5: Commit**

```bash
git add src/blocks/query/render-helpers.php src/blocks/query/render-posts.php src/blocks/query/render-users.php src/blocks/query/render-terms.php tests/integration/blocks/query/ParentStackTest.php
git commit -m "feat(query): push/pop parent-context stack during item render"
```

---

### Task C2: `scope` arg on DSGo bindings

**Why:** With the stack in place, bindings can resolve `{ scope: 'parent', key: 'featured' }` against the outer Query's current item instead of the current post.

**Files:**
- Modify: `includes/blocks/class-query-bindings.php`
- Test: `tests/integration/blocks/query/BindingsScopeTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/integration/blocks/query/BindingsScopeTest.php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use DesignSetGo\Blocks\Query\Bindings;
use WP_Block;
use WP_UnitTestCase;

class BindingsScopeTest extends WP_UnitTestCase {

    public function test_parent_scope_reads_from_parent_stack() {
        $parent_id = self::factory()->post->create();
        $child_id  = self::factory()->post->create();
        update_post_meta( $parent_id, 'parent_label', 'HELLO-PARENT' );

        $GLOBALS['designsetgo_parent_stack'] = array(
            array( 'postId' => $parent_id, 'postType' => 'post' ),
        );

        $bindings = new Bindings();
        $block    = new WP_Block(
            array( 'blockName' => 'core/paragraph' ),
            array( 'postId' => $child_id, 'postType' => 'post' )
        );
        $value = $bindings->get_post_meta_value(
            array( 'key' => 'parent_label', 'scope' => 'parent' ),
            $block,
            'content'
        );

        $this->assertSame( 'HELLO-PARENT', $value );
        unset( $GLOBALS['designsetgo_parent_stack'] );
    }

    public function test_self_scope_is_default() {
        $post_id = self::factory()->post->create();
        update_post_meta( $post_id, 'label', 'ME' );

        $bindings = new Bindings();
        $block    = new WP_Block(
            array( 'blockName' => 'core/paragraph' ),
            array( 'postId' => $post_id, 'postType' => 'post' )
        );
        $value = $bindings->get_post_meta_value( array( 'key' => 'label' ), $block, 'content' );
        $this->assertSame( 'ME', $value );
    }
}
```

**Step 2: Run and verify it fails**

```bash
composer test -- --filter=BindingsScopeTest
```

Expected: FAIL — scope arg ignored.

**Step 3: Extend the binding**

In both `get_post_meta_value()` and `get_acf_value()`, replace the existing `$post_id` resolution with:

```php
$scope = isset( $args['scope'] ) ? (string) $args['scope'] : 'self';

$post_id = 0;
if ( 'parent' === $scope ) {
    $stack  = $GLOBALS['designsetgo_parent_stack'] ?? array();
    $parent = empty( $stack ) ? null : end( $stack );
    if ( is_array( $parent ) && ! empty( $parent['postId'] ) ) {
        $post_id = (int) $parent['postId'];
    }
} elseif ( 'root' === $scope ) {
    $stack = $GLOBALS['designsetgo_parent_stack'] ?? array();
    $root  = empty( $stack ) ? null : reset( $stack );
    if ( is_array( $root ) && ! empty( $root['postId'] ) ) {
        $post_id = (int) $root['postId'];
    }
} else {
    if ( $block && isset( $block->context['postId'] ) ) {
        $post_id = (int) $block->context['postId'];
    }
}

if ( ! $post_id ) {
    $post_id = get_the_ID();
}
```

**Step 4: Verify tests pass**

```bash
composer test -- --filter=BindingsScopeTest
```

Expected: PASS.

**Step 5: Commit**

```bash
git add includes/blocks/class-query-bindings.php tests/integration/blocks/query/BindingsScopeTest.php
git commit -m "feat(bindings): add scope=parent|root|self to DSGo post-meta + ACF sources"
```

---

### Task C3: Editor-side context provider for nested previews

**Why:** The editor needs to provide matching context so nested-loop previews + `scope: 'parent'` bindings render right while authoring. Without this, a paragraph with `{scope:'parent'}` would silently fall back to the edited post's own meta in the editor, hiding bugs until publish.

**Files:**
- Modify: `src/blocks/query/edit-template.js` (the component that maps over preview records)
- Create: `src/extensions/visibility/ItemContextBridge.js` (pulls parent data from BlockContext into item context for visibility)
- Test: `tests/unit/blocks/query/NestedPreview.test.js`

**Step 1: Write the failing test**

```js
// tests/unit/blocks/query/NestedPreview.test.js
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import QueryEdit from '../../../../src/blocks/query/edit';

// Re-use the shared mock setup from edit.test.js for i18n, components, etc.
// Only the new thing is checking that BlockContextProvider receives a parentItem.
const ctxCapture = [];
jest.mock('@wordpress/block-editor', () => ({
    useBlockProps: () => ({ className: 'wp-block-designsetgo-query' }),
    useInnerBlocksProps: (p = {}) => ({ ...p, children: null }),
    InspectorControls: ({ children }) => <div data-testid="inspector">{children}</div>,
    InnerBlocks: () => <div data-testid="inner-blocks" />,
    BlockPreview: () => <div data-testid="block-preview" />,
    BlockContextProvider: ({ value, children }) => {
        ctxCapture.push(value);
        return <>{children}</>;
    },
    store: 'core/block-editor',
}));

// (Other mocks mirror existing edit.test.js — for brevity refer to it.)

describe('QueryEdit nested preview', () => {
    it('provides parent context to BlockContextProvider', () => {
        render(
            <QueryEdit
                attributes={{ queryId: 'nested-1', source: 'posts', perPage: 1, /* ... */ }}
                setAttributes={jest.fn()}
                clientId="cli-1"
                context={{ 'designsetgo/parentItem': { postId: 99, postType: 'post' } }}
            />
        );
        expect(ctxCapture.some((c) => c?.['designsetgo/parentItem']?.postId === 99)).toBe(true);
    });
});
```

**Step 2: Run and verify it fails**

```bash
npm run test:unit -- --testPathPattern=NestedPreview
```

Expected: FAIL — context not forwarded.

**Step 3: Forward and provide context**

In `block.json`, add:

```json
"usesContext":     ["designsetgo/parentItem", "postId", "postType"],
"providesContext": {
    "designsetgo/queryId":      "queryId",
    "designsetgo/querySource":  "source",
    "designsetgo/queryPostType":"postType",
    "designsetgo/parentItem":   "__dsgo_currentItem"
}
```

Note: `__dsgo_currentItem` is a synthetic attribute we never persist — it's set dynamically in the edit component via a wrapper component.

In `edit-template.js`, wrap each previewed item with:

```jsx
<BlockContextProvider
    key={record.id}
    value={{
        postId: record.id,
        postType: record.type,
        'designsetgo/parentItem': props.context?.['designsetgo/parentItem'] ?? null,
    }}
>
    {/* BlockPreview or InnerBlocks */}
</BlockContextProvider>
```

**Step 4: Run tests**

```bash
npm run test:unit -- --testPathPattern=NestedPreview
```

Expected: PASS.

**Step 5: Commit**

```bash
git add src/blocks/query/block.json src/blocks/query/edit-template.js src/extensions/visibility/ItemContextBridge.js tests/unit/blocks/query/NestedPreview.test.js
git commit -m "feat(query): forward parent item via BlockContext for nested previews"
```

---

### Task C4: Nested loop manual QA

**Steps:**

1. `npm run build`
2. Create a page with:
   - Outer Query (Posts source, perPage 3)
     - Item template:
       - Heading bound to `designsetgo/post-meta` with `scope: self`, key `title`
       - Inner Query (Posts source, perPage 2), with tax_query scoped to the parent term:
         - Item template: Paragraph bound to `designsetgo/post-meta` with `scope: self`, key `excerpt`
3. Save. Verify outer renders three posts, each with up to two nested posts from the same category.
4. Open the editor; confirm the nested preview shows items (even with mock data).
5. Add `?q=foo` to the frontend URL; confirm only the outer loop's search applies (since nested query has its own `queryId`).

No commit; log observations in the PR description.

---

## Phase D — Group-by / partitioning

### Task D1: `query-group-header` sibling block

**Why:** Grouping needs a user-editable template for the group header ("Category: News", "Year: 2024"). Cleanest route is a new sibling block similar to `query-no-results`.

**Files:**
- Create: `src/blocks/query-group-header/block.json`
- Create: `src/blocks/query-group-header/index.js`
- Create: `src/blocks/query-group-header/edit.js`
- Create: `src/blocks/query-group-header/save.js`
- Create: `src/blocks/query-group-header/render.php`
- Modify: `src/index.js` (register block)
- Test: `tests/unit/blocks/query-group-header/edit.test.js`

**Step 1: Write the failing test**

```js
// tests/unit/blocks/query-group-header/edit.test.js
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
// Reuse the same @wordpress/* mocks pattern from edit.test.js
import Edit from '../../../../src/blocks/query-group-header/edit';

describe('QueryGroupHeader edit', () => {
    it('renders an InnerBlocks wrapper', () => {
        render(<Edit attributes={{}} context={{}} clientId="g1" />);
        expect(screen.getByTestId('inner-blocks')).toBeInTheDocument();
    });
});
```

**Step 2: Run and verify it fails**

```bash
npm run test:unit -- --testPathPattern=query-group-header
```

Expected: FAIL — module not found.

**Step 3: Implement skeleton**

Minimal `block.json` (`apiVersion: 3`, textdomain `"designsetgo"`, `parent: ["designsetgo/query"]`, `usesContext: ["designsetgo/queryId", "designsetgo/groupLabel", "designsetgo/groupValue"]`).

`edit.js` returns `<div {...useBlockProps()}>{useInnerBlocksProps(useBlockProps(), {template: [['core/heading', { level: 3, placeholder: __('Group header', 'designsetgo') }]]}).children}</div>` — follow the same pattern as `query-no-results`.

`render.php` renders `innerBlocks` with the group context set, wrapped in `<div class="dsgo-query-group-header">`.

**Step 4: Verify tests pass**

```bash
npm run test:unit -- --testPathPattern=query-group-header
npm run build
```

Expected: PASS, build green.

**Step 5: Commit**

```bash
git add src/blocks/query-group-header src/index.js tests/unit/blocks/query-group-header
git commit -m "feat(query-group-header): new sibling block for group headers"
```

---

### Task D2: `groupBy` attribute + partitioning logic

**Why:** The parent Query needs a `groupBy` attribute plus render logic that buckets items before emitting them, interleaving group-header blocks.

**Files:**
- Modify: `src/blocks/query/block.json` (add `groupBy`)
- Modify: `src/blocks/query/render-helpers.php` (new `designsetgo_query_partition_items()`)
- Modify: `src/blocks/query/render-posts.php` (integrate partitioning before the items loop)
- Test: `tests/integration/blocks/query/GroupByTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/integration/blocks/query/GroupByTest.php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_UnitTestCase;

class GroupByTest extends WP_UnitTestCase {

    public function test_partitions_by_taxonomy() {
        $news_term   = self::factory()->term->create( array( 'taxonomy' => 'category', 'slug' => 'news' ) );
        $events_term = self::factory()->term->create( array( 'taxonomy' => 'category', 'slug' => 'events' ) );

        $news_post_ids = self::factory()->post->create_many( 2 );
        foreach ( $news_post_ids as $pid ) wp_set_post_terms( $pid, array( $news_term ), 'category' );

        $events_post_ids = self::factory()->post->create_many( 1 );
        wp_set_post_terms( $events_post_ids[0], array( $events_term ), 'category' );

        require_once DSGO_PLUGIN_DIR . 'src/blocks/query/render-helpers.php';

        $header_html = '<!-- wp:designsetgo/query-group-header -->'
                      . '<div class="wp-block-designsetgo-query-group-header"><h3>Group</h3></div>'
                      . '<!-- /wp:designsetgo/query-group-header -->';
        $inner       = $header_html . '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->';

        $html = designsetgo_query_render(
            array(
                'source'   => 'posts',
                'postType' => 'post',
                'perPage'  => 10,
                'tagName'  => 'ul',
                'itemTagName' => 'li',
                'groupBy'  => array( 'field' => 'taxonomy', 'key' => 'category' ),
            ),
            array( 'query_id' => 'g1', 'page' => 1, 'inner_html' => $inner, 'params' => array() )
        )['html'];

        // Two groups → two headers in output.
        $this->assertSame( 2, substr_count( $html, 'dsgo-query-group-header' ) );
    }
}
```

**Step 2: Run and verify it fails**

```bash
composer test -- --filter=GroupByTest
```

Expected: FAIL — groupBy attribute ignored.

**Step 3: Add attribute and partition helper**

In `block.json`:

```json
"groupBy": { "type": "object", "default": null }
```

And in `designsetgo_query_defaults()` add `'groupBy' => null`.

Add the partitioner in `render-helpers.php`:

```php
function designsetgo_query_partition_items( array $post_ids, array $group_spec ) {
    if ( empty( $group_spec['field'] ) || empty( $group_spec['key'] ) ) {
        return array( array( 'label' => '', 'value' => '', 'ids' => $post_ids ) );
    }
    $field = (string) $group_spec['field'];
    $key   = (string) $group_spec['key'];

    $groups = array();
    foreach ( $post_ids as $pid ) {
        $values = array();
        if ( 'taxonomy' === $field ) {
            $terms  = get_the_terms( $pid, $key );
            $values = empty( $terms ) || is_wp_error( $terms ) ? array( '' ) : wp_list_pluck( $terms, 'slug' );
            $labels = empty( $terms ) || is_wp_error( $terms ) ? array( __( 'Uncategorized', 'designsetgo' ) ) : wp_list_pluck( $terms, 'name' );
        } elseif ( 'meta' === $field ) {
            $values = array( (string) get_post_meta( $pid, $key, true ) );
            $labels = $values;
        } elseif ( 'date' === $field ) {
            $d      = get_post_field( 'post_date', $pid );
            $values = array( gmdate( 'Y' === $key ? 'Y' : ( 'Y-M' === $key ? 'Y-m' : 'Y-m-d' ), strtotime( $d ) ) );
            $labels = $values;
        } else {
            $values = array( '' );
            $labels = array( '' );
        }
        foreach ( $values as $i => $v ) {
            $groups[ $v ] ??= array( 'label' => $labels[ $i ] ?? $v, 'value' => $v, 'ids' => array() );
            $groups[ $v ]['ids'][] = $pid;
        }
    }
    return array_values( $groups );
}
```

In `render-posts.php`, split the existing loop: pre-collect IDs, split `innerBlocks` into `group_header_blocks` vs `item_template_blocks` (similar to how `render-helpers.php` already splits siblings), iterate over partitions, and interleave group-header render + item render.

Wrap each group in `<section class="dsgo-query-group" data-dsgo-group-value="...">…</section>`.

**Step 4: Verify tests pass**

```bash
composer test -- --filter=GroupByTest
```

Expected: PASS.

**Step 5: Commit**

```bash
git add src/blocks/query/block.json src/blocks/query/render-helpers.php src/blocks/query/render-posts.php tests/integration/blocks/query/GroupByTest.php
git commit -m "feat(query): partition items by taxonomy/meta/date with group headers"
```

---

### Task D3: Group-by inspector control + editor preview

**Files:**
- Modify: `src/blocks/query/components/QuerySourcePanel.js` (add group-by control to existing panel)
- Modify: `src/blocks/query/edit-template.js` (render group headers in preview)
- Test: `tests/unit/blocks/query/edit.test.js` (new describe block)

**Step 1: Write failing test**

```js
describe('QueryEdit — Group-by', () => {
    it('renders the group-by type select', () => {
        renderWith();
        expect(screen.getByLabelText(/group by/i)).toBeInTheDocument();
    });

    it('shows a taxonomy picker when groupBy.field is taxonomy', () => {
        renderWith({ groupBy: { field: 'taxonomy', key: 'category' } });
        expect(screen.getByLabelText(/group taxonomy/i)).toBeInTheDocument();
    });
});
```

**Step 2: Run and verify fails**

```bash
npm run test:unit -- --testPathPattern=query/edit.test
```

**Step 3: Implement the inspector control**

`SelectControl` for `groupBy.field` with options `none` / `taxonomy` / `meta` / `date`. When field changes to non-null, render a second `SelectControl` or `TextControl` for `groupBy.key`.

Wire `setAttributes({ groupBy: { field, key } })` or `setAttributes({ groupBy: null })` depending on the field pick.

**Step 4: Verify tests pass**

```bash
npm run test:unit -- --testPathPattern=query/edit.test
```

**Step 5: Commit**

```bash
git add src/blocks/query/components/QuerySourcePanel.js src/blocks/query/edit-template.js tests/unit/blocks/query/edit.test.js
git commit -m "feat(query): group-by inspector + editor preview"
```

---

### Task D4: Group-by manual QA + CSS

**Files:**
- Modify: `src/blocks/query/style.scss` (basic `.dsgo-query-group` gap rules)
- Modify: `src/styles/style.scss` + `src/styles/editor.scss` (if not already autoloaded)

**Steps:**

1. `npm run build`
2. Create posts in two categories (News, Events). Add a Dynamic Query block, enable Group-by → Taxonomy → Category.
3. Insert a Query Group Header inner block. Put a heading inside bound to a meta field that expands to the group name (for now, hard-code "Section").
4. Save. Verify frontend renders two `<section class="dsgo-query-group">` blocks with interleaved headers.
5. Commit CSS with message: `style(query): spacing + structure for .dsgo-query-group`

---

## Phase E — Docs, CHANGELOG, CI, PR

### Task E1: Update the Query guide

**Files:**
- Modify: `.claude/docs/QUERY-BLOCK-GUIDE.md` (extend Recipes section)
- Modify: `.claude/CLAUDE.md` (add v2.3 subsection under "Query block family")

Add:
- Recipe: "Show posts linked via an ACF relationship field".
- Recipe: "Hide the 'Featured' badge on non-featured posts".
- Recipe: "Group posts by category with custom group headers".
- Recipe: "Nested loops — for each post, show its 3 latest sibling posts".
- Extension point: `designsetgo_visibility_rule` filter for custom rule types.

**Commit:**

```bash
git add .claude/docs/QUERY-BLOCK-GUIDE.md .claude/CLAUDE.md
git commit -m "docs: v2.3 recipes + extension points"
```

---

### Task E2: Update CHANGELOG + plugin header

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `designsetgo.php` (`* Version: 2.3.0`)
- Modify: `package.json` (`"version": "2.3.0"`)

```markdown
## [2.3.0] — 2026-04-19
### Added
- Dynamic Query: relationship source reads a parent-context field (meta or ACF) and iterates referenced posts.
- Dynamic Query: nested loops — outer Query's current item is available to inner Queries via `designsetgo/parentItem` context and the new `scope: 'parent' | 'root'` arg on DSGo bindings.
- Dynamic Query: conditional inner-block visibility via the `dsgoVisibility` attribute, with inspector UI and shared evaluator (meta / taxonomy / index / auth rule types).
- Dynamic Query: `groupBy` partitions items by taxonomy / meta / date; new `designsetgo/query-group-header` sibling block renders once per group.

### Changed
- `designsetgo/post-meta` + `designsetgo/acf` bindings accept an optional `scope` arg (defaults to `'self'`).

### Migration
No breaking changes — all new attributes default to null / `'self'`.
```

**Commit:**

```bash
git add CHANGELOG.md designsetgo.php package.json
git commit -m "chore(release): bump to 2.3.0"
```

---

### Task E3: Run all checks + push branch + open PR

**Steps:**

1. Run:

```bash
npm run build
npm run lint:js
npm run lint:css
npm run lint:php
npm run test:unit
composer test
composer analyse
```

All green.

2. Start wp-env and sanity-check the four features (relationship, visibility, nested, group-by) on `http://localhost:9451/`.

3. Push:

```bash
git push -u origin claude/query-v2.3-nested
```

4. Open PR against `main`:

```bash
gh pr create --title "feat(query): Dynamic Query v2.3 — nested loops, relationships, visibility, group-by" --body "$(cat <<'EOF'
## Summary
- Relationship source reads a parent-context field and iterates referenced posts.
- Nested loops: outer item flows into inner queries via the new `designsetgo/parentItem` context + `scope` arg on DSGo bindings.
- Conditional visibility: `dsgoVisibility` attribute on every block; shared evaluator; inspector UI.
- Group-by: `groupBy` attribute + `designsetgo/query-group-header` sibling block partitions results.

## Test plan
- [ ] `composer test` 100% green.
- [ ] `npm run test:unit` 100% green.
- [ ] Manual: relationship source renders referenced posts on frontend + editor preview.
- [ ] Manual: visibility rule hides a paragraph on non-featured posts.
- [ ] Manual: nested Query resolves `scope: parent` binding to outer post meta.
- [ ] Manual: group-by=category renders one header per category.
- [ ] Manual: infinite scroll + filters still work inside a grouped query (regression).

Follow-up: v2.4 (JetEngine / Meta Box / Pods bindings, PHP escape-hatch UI, JSON export/import).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

5. Return the PR URL.

---

## Out of scope (v2.4+)

- JetEngine / Meta Box / Pods bindings.
- PHP escape-hatch UI for advanced users.
- JSON export/import of query configurations.
- Date-query UI, multi-level AND/OR tree, hierarchical taxonomy drilldown → v2.5+.

---

## Verification checklist (ship gate)

- [ ] `composer test` — all suites green.
- [ ] `npm run test:unit` — all suites green.
- [ ] `composer analyse` — phpstan clean.
- [ ] `npm run lint:js` / `lint:css` / `lint:php` — clean.
- [ ] `npm run build` — no warnings.
- [ ] WP admin at `localhost:9451/wp-admin/` — all four headline features work.
- [ ] Regression: infinite scroll, filters, load-more, editor live preview still work.
- [ ] CHANGELOG + plugin header updated to 2.3.0.

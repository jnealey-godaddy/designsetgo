# Dynamic Query v2.5 — Advanced Filters Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Extend the Dynamic Query block with four capabilities: an `include_children` toggle for taxonomy clauses, a Date Query UI, multi-level AND/OR nested groups in both tax and meta query builders, and a Query Monitor debug panel.

**Architecture:** Hierarchical taxonomy and date-query are additive — new attributes + new/modified components + PHP builder additions with no breaking schema changes. The multi-level AND/OR tree refactors TaxQueryBuilder and MetaQueryBuilder to use a shared recursive `ClauseGroupShell` component and updates the PHP builders to recurse into nested groups. The QM panel is a completely standalone PHP addition guarded by `defined('QM_VERSION')`.

**Tech Stack:** React/JSX (`@wordpress/components`, `@wordpress/block-editor`), PHP 7.4+ (WP_Query `tax_query`/`meta_query`/`date_query` arrays), PHPUnit 9, Jest, QM_Collector/QM_Output_Html (Query Monitor 3.x API).

---

## Context: existing architecture

- **`src/blocks/query/block.json`** — `taxQuery` and `metaQuery` are opaque `type: object` attributes with `{relation, clauses:[]}` defaults. No `dateQuery` exists yet.
- **`src/blocks/query/components/TaxQueryBuilder.js`** — flat clause list + top-level `relation` selector. Each clause: `{taxonomy, terms[], operator}`. No `include_children` field.
- **`src/blocks/query/components/MetaQueryBuilder.js`** — same flat pattern. Each clause: `{key, compare, value, type}`.
- **`src/blocks/query/render-posts.php`** — `designsetgo_query_build_posts_args()` builds tax/meta query arrays at lines ~260-302. No `date_query` block. No `include_children` pass-through (WP defaults it `true` when absent).
- **`src/blocks/query/edit.js`** — inspector mounts `<TaxQueryBuilder>` and `<MetaQueryBuilder>` when `source === 'posts'`. A new `<DateQueryBuilder>` follows the same gate.
- **Tests:** `tests/phpunit/blocks/query/render-posts-test.php`, `tests/unit/blocks/query/tax-query-builder.test.js`, `tests/unit/blocks/query/meta-query-builder.test.js`.

---

## Task 1: `include_children` toggle in TaxQueryBuilder

Adds a per-clause "Include child terms" toggle (defaults `true` to preserve existing behavior).

**Files:**
- Modify: `src/blocks/query/components/TaxQueryBuilder.js`
- Modify: `src/blocks/query/render-posts.php` (lines ~265–278)
- Modify: `tests/phpunit/blocks/query/render-posts-test.php`
- Modify: `tests/unit/blocks/query/tax-query-builder.test.js`

**Step 1: Write the failing PHP test**

Add to `tests/phpunit/blocks/query/render-posts-test.php` inside the test class:

```php
public function test_tax_clause_defaults_include_children_true() {
    $atts = [
        'source'   => 'posts',
        'taxQuery' => [
            'relation' => 'AND',
            'clauses'  => [ [ 'taxonomy' => 'category', 'terms' => [ 1 ], 'operator' => 'IN' ] ],
        ],
    ];
    $args = designsetgo_query_build_posts_args( $atts, null );
    $this->assertTrue( $args['tax_query'][0]['include_children'] );
}

public function test_tax_clause_include_children_false() {
    $atts = [
        'source'   => 'posts',
        'taxQuery' => [
            'relation' => 'AND',
            'clauses'  => [ [
                'taxonomy'         => 'category',
                'terms'            => [ 1 ],
                'operator'         => 'IN',
                'include_children' => false,
            ] ],
        ],
    ];
    $args = designsetgo_query_build_posts_args( $atts, null );
    $this->assertFalse( $args['tax_query'][0]['include_children'] );
}
```

**Step 2: Run to confirm failure**

```bash
cd /path/to/worktree
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/render-posts-test.php --filter=test_tax_clause
```

Expected: FAIL — `include_children` key missing from `$args['tax_query'][0]`.

**Step 3: Update PHP builder**

In `src/blocks/query/render-posts.php`, inside the `foreach ( $tax_clauses as $clause )` loop (lines ~269–274), add `include_children` to the clause array:

```php
$tax_query[] = array(
    'taxonomy'         => sanitize_key( (string) $clause['taxonomy'] ),
    'terms'            => array_map( 'absint', (array) $clause['terms'] ),
    'operator'         => in_array( ( $clause['operator'] ?? 'IN' ), array( 'IN', 'NOT IN', 'AND' ), true ) ? $clause['operator'] : 'IN',
    'include_children' => isset( $clause['include_children'] ) ? (bool) $clause['include_children'] : true,
);
```

**Step 4: Run PHP tests — expect pass**

```bash
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/render-posts-test.php --filter=test_tax_clause
```

Expected: PASS.

**Step 5: Write failing JS test**

In `tests/unit/blocks/query/tax-query-builder.test.js`, add tests verifying `addClause` seeds `include_children: true` and that a toggle renders per clause. Use the existing test file's import pattern. The test should call the `addClause` logic (extract it or spy on `setAttributes`) and assert `include_children: true` is present in the new clause.

```js
it('seeds include_children: true when adding a clause', () => {
    const setAttributes = jest.fn();
    const { getByRole } = render(
        <TaxQueryBuilder
            attributes={{ taxQuery: { relation: 'AND', clauses: [] }, postType: 'post' }}
            setAttributes={setAttributes}
            clientId="test-id"
        />
    );
    fireEvent.click( getByRole( 'button', { name: /add/i } ) );
    expect( setAttributes ).toHaveBeenCalledWith(
        expect.objectContaining({
            taxQuery: expect.objectContaining({
                clauses: expect.arrayContaining([
                    expect.objectContaining({ include_children: true }),
                ]),
            }),
        })
    );
} );
```

**Step 6: Run JS test — expect failure**

```bash
npx wp-scripts test-unit-js tests/unit/blocks/query/tax-query-builder.test.js --testNamePattern="include_children"
```

Expected: FAIL.

**Step 7: Update TaxQueryBuilder.js**

Three changes:

1. In `addClause()`, add `include_children: true` to the seeded clause object.

2. Add `import { ToggleControl } from '@wordpress/components';` if not already imported.

3. Inside the clause render (the `<VStack>` per clause, after the operator `<SelectControl>`), add:

```jsx
<ToggleControl
    label={ __( 'Include child terms', 'designsetgo' ) }
    checked={ clause.include_children ?? true }
    onChange={ ( val ) => updateClause( idx, { include_children: val } ) }
    __nextHasNoMarginBottom
/>
```

**Step 8: Run JS tests — expect pass**

```bash
npx wp-scripts test-unit-js tests/unit/blocks/query/tax-query-builder.test.js
```

**Step 9: Build and verify no console errors**

```bash
npm run build
npm run lint:js
```

**Step 10: Commit**

```bash
git add src/blocks/query/components/TaxQueryBuilder.js \
        src/blocks/query/render-posts.php \
        tests/phpunit/blocks/query/render-posts-test.php \
        tests/unit/blocks/query/tax-query-builder.test.js
git commit -m "feat(query): add include_children toggle to taxonomy clause builder"
```

---

## Task 2: Date Query UI

Adds a new `dateQuery` attribute and a `DateQueryBuilder` inspector component. Supports `before`, `after`, and `between` modes with ISO date strings or relative expressions (`-30 days`, `-1 year`).

**Date clause shape:**
```js
{
    column:    'post_date' | 'post_modified' | 'post_date_gmt' | 'post_modified_gmt',
    mode:      'after' | 'before' | 'between',
    after:     string,   // ISO date 'YYYY-MM-DD' or relative '-30 days'
    before:    string,   // ISO date 'YYYY-MM-DD' or relative
    inclusive: boolean,
}
```

**Files:**
- Modify: `src/blocks/query/block.json`
- Create: `src/blocks/query/components/DateQueryBuilder.js`
- Modify: `src/blocks/query/edit.js`
- Modify: `src/blocks/query/render-posts.php`
- Create: `tests/unit/blocks/query/date-query-builder.test.js`
- Modify: `tests/phpunit/blocks/query/render-posts-test.php`

**Step 1: Add `dateQuery` attribute to block.json**

In `src/blocks/query/block.json`, after the `metaQuery` attribute (currently at lines ~62–64), add:

```json
"dateQuery": {
  "type": "object",
  "default": { "relation": "AND", "clauses": [] }
}
```

**Step 2: Write failing PHP tests**

Add to `tests/phpunit/blocks/query/render-posts-test.php`:

```php
public function test_date_query_after_builds_correctly() {
    $atts = [
        'source'    => 'posts',
        'dateQuery' => [
            'relation' => 'AND',
            'clauses'  => [ [
                'column'    => 'post_date',
                'mode'      => 'after',
                'after'     => '-30 days',
                'before'    => '',
                'inclusive' => false,
            ] ],
        ],
    ];
    $args = designsetgo_query_build_posts_args( $atts, null );
    $this->assertArrayHasKey( 'date_query', $args );
    $this->assertEquals( 'post_date', $args['date_query'][0]['column'] );
    $this->assertEquals( '-30 days', $args['date_query'][0]['after'] );
    $this->assertArrayNotHasKey( 'before', $args['date_query'][0] );
}

public function test_date_query_between_includes_both_bounds() {
    $atts = [
        'source'    => 'posts',
        'dateQuery' => [
            'relation' => 'AND',
            'clauses'  => [ [
                'column'    => 'post_date',
                'mode'      => 'between',
                'after'     => '2024-01-01',
                'before'    => '2024-12-31',
                'inclusive' => true,
            ] ],
        ],
    ];
    $args = designsetgo_query_build_posts_args( $atts, null );
    $this->assertEquals( '2024-01-01', $args['date_query'][0]['after'] );
    $this->assertEquals( '2024-12-31', $args['date_query'][0]['before'] );
    $this->assertTrue( $args['date_query'][0]['inclusive'] );
}

public function test_date_query_skips_empty_clauses() {
    $atts = [
        'source'    => 'posts',
        'dateQuery' => [
            'relation' => 'AND',
            'clauses'  => [ [ 'column' => 'post_date', 'mode' => 'after', 'after' => '', 'before' => '' ] ],
        ],
    ];
    $args = designsetgo_query_build_posts_args( $atts, null );
    $this->assertArrayNotHasKey( 'date_query', $args );
}
```

**Step 3: Run PHP tests — expect failure**

```bash
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/render-posts-test.php --filter=test_date_query
```

Expected: FAIL — `date_query` key absent.

**Step 4: Add PHP date_query builder**

In `src/blocks/query/render-posts.php`, after the `meta_query` block (~line 302) and before any `manual` source handling (~line 305), add:

```php
// Date query.
$date_clauses = isset( $atts['dateQuery']['clauses'] ) ? (array) $atts['dateQuery']['clauses'] : array();
if ( ! empty( $date_clauses ) ) {
    $valid_columns = array( 'post_date', 'post_modified', 'post_date_gmt', 'post_modified_gmt' );
    $date_query    = array(
        'relation' => ( 'OR' === ( $atts['dateQuery']['relation'] ?? 'AND' ) ) ? 'OR' : 'AND',
    );
    foreach ( $date_clauses as $clause ) {
        $mode   = $clause['mode'] ?? 'after';
        $after  = sanitize_text_field( (string) ( $clause['after'] ?? '' ) );
        $before = sanitize_text_field( (string) ( $clause['before'] ?? '' ) );

        // Skip clauses with no date value.
        if ( 'between' === $mode && ( '' === $after || '' === $before ) ) {
            continue;
        }
        if ( 'after' === $mode && '' === $after ) {
            continue;
        }
        if ( 'before' === $mode && '' === $before ) {
            continue;
        }

        $entry = array(
            'column'    => in_array( ( $clause['column'] ?? 'post_date' ), $valid_columns, true ) ? $clause['column'] : 'post_date',
            'inclusive' => ! empty( $clause['inclusive'] ),
        );
        if ( 'after' === $mode || 'between' === $mode ) {
            $entry['after'] = $after;
        }
        if ( 'before' === $mode || 'between' === $mode ) {
            $entry['before'] = $before;
        }
        $date_query[] = $entry;
    }
    if ( count( $date_query ) > 1 ) {
        $args['date_query'] = $date_query;
    }
}
```

**Step 5: Run PHP tests — expect pass**

```bash
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/render-posts-test.php --filter=test_date_query
```

**Step 6: Write failing JS test**

Create `tests/unit/blocks/query/date-query-builder.test.js`:

```js
import { render, screen, fireEvent } from '@testing-library/react';
import DateQueryBuilder from '../../../src/blocks/query/components/DateQueryBuilder';

const defaultAttrs = {
    dateQuery: { relation: 'AND', clauses: [] },
};

describe( 'DateQueryBuilder', () => {
    it( 'renders empty state with an Add button', () => {
        render(
            <DateQueryBuilder
                attributes={ defaultAttrs }
                setAttributes={ jest.fn() }
                clientId="test"
            />
        );
        expect( screen.getByRole( 'button', { name: /add date clause/i } ) ).toBeInTheDocument();
    } );

    it( 'seeds new clause with post_date / after / inclusive:true defaults', () => {
        const setAttributes = jest.fn();
        render(
            <DateQueryBuilder
                attributes={ defaultAttrs }
                setAttributes={ setAttributes }
                clientId="test"
            />
        );
        fireEvent.click( screen.getByRole( 'button', { name: /add date clause/i } ) );
        expect( setAttributes ).toHaveBeenCalledWith(
            expect.objectContaining( {
                dateQuery: expect.objectContaining( {
                    clauses: [ expect.objectContaining( {
                        column: 'post_date',
                        mode: 'after',
                        inclusive: true,
                    } ) ],
                } ),
            } )
        );
    } );
} );
```

**Step 7: Run JS test — expect failure**

```bash
npx wp-scripts test-unit-js tests/unit/blocks/query/date-query-builder.test.js
```

Expected: FAIL — module not found.

**Step 8: Create DateQueryBuilder.js**

Create `src/blocks/query/components/DateQueryBuilder.js`:

```jsx
import { __ } from '@wordpress/i18n';
import {
    SelectControl,
    TextControl,
    ToggleControl,
    Button,
    VStack,
    HStack,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared/DsgoInspectorPanel';

const COLUMN_OPTIONS = [
    { label: __( 'Publish date', 'designsetgo' ), value: 'post_date' },
    { label: __( 'Modified date', 'designsetgo' ), value: 'post_modified' },
    { label: __( 'Publish date (GMT)', 'designsetgo' ), value: 'post_date_gmt' },
    { label: __( 'Modified date (GMT)', 'designsetgo' ), value: 'post_modified_gmt' },
];

const MODE_OPTIONS = [
    { label: __( 'After', 'designsetgo' ), value: 'after' },
    { label: __( 'Before', 'designsetgo' ), value: 'before' },
    { label: __( 'Between', 'designsetgo' ), value: 'between' },
];

const DEFAULT_CLAUSE = {
    column: 'post_date',
    mode: 'after',
    after: '',
    before: '',
    inclusive: true,
};

export default function DateQueryBuilder( { attributes, setAttributes, clientId } ) {
    const { dateQuery = { relation: 'AND', clauses: [] } } = attributes;
    const { relation, clauses } = dateQuery;

    const updateQuery = ( patch ) =>
        setAttributes( { dateQuery: { ...dateQuery, ...patch } } );

    const addClause = () =>
        updateQuery( { clauses: [ ...clauses, { ...DEFAULT_CLAUSE } ] } );

    const updateClause = ( idx, patch ) => {
        const next = clauses.map( ( c, i ) => ( i === idx ? { ...c, ...patch } : c ) );
        updateQuery( { clauses: next } );
    };

    const removeClause = ( idx ) =>
        updateQuery( { clauses: clauses.filter( ( _, i ) => i !== idx ) } );

    return (
        <DsgoInspectorPanel
            title={ __( 'Date filters', 'designsetgo' ) }
            panelName="settings"
            panelId={ clientId }
            onDeselect={ () => updateQuery( { relation: 'AND', clauses: [] } ) }
            hasValue={ clauses.length > 0 }
            isShownByDefault
        >
            <DsgoInspectorPanel.Item
                label={ __( 'Date clauses', 'designsetgo' ) }
                hasValue={ clauses.length > 0 }
                onDeselect={ () => updateQuery( { relation: 'AND', clauses: [] } ) }
                isShownByDefault
            >
                { clauses.length > 1 && (
                    <SelectControl
                        label={ __( 'Match', 'designsetgo' ) }
                        value={ relation }
                        options={ [
                            { label: __( 'All (AND)', 'designsetgo' ), value: 'AND' },
                            { label: __( 'Any (OR)', 'designsetgo' ), value: 'OR' },
                        ] }
                        onChange={ ( val ) => updateQuery( { relation: val } ) }
                        __nextHasNoMarginBottom
                    />
                ) }
                { clauses.map( ( clause, idx ) => (
                    <VStack key={ idx } spacing={ 2 } className="dsgo-query-date-clause">
                        <SelectControl
                            label={ __( 'Date column', 'designsetgo' ) }
                            value={ clause.column }
                            options={ COLUMN_OPTIONS }
                            onChange={ ( val ) => updateClause( idx, { column: val } ) }
                            __nextHasNoMarginBottom
                        />
                        <SelectControl
                            label={ __( 'Mode', 'designsetgo' ) }
                            value={ clause.mode }
                            options={ MODE_OPTIONS }
                            onChange={ ( val ) => updateClause( idx, { mode: val } ) }
                            __nextHasNoMarginBottom
                        />
                        { ( clause.mode === 'after' || clause.mode === 'between' ) && (
                            <TextControl
                                label={ clause.mode === 'between' ? __( 'After', 'designsetgo' ) : __( 'Date', 'designsetgo' ) }
                                value={ clause.after }
                                placeholder="2024-01-01 or -30 days"
                                onChange={ ( val ) => updateClause( idx, { after: val } ) }
                                __nextHasNoMarginBottom
                            />
                        ) }
                        { ( clause.mode === 'before' || clause.mode === 'between' ) && (
                            <TextControl
                                label={ __( 'Before', 'designsetgo' ) }
                                value={ clause.before }
                                placeholder="2024-12-31 or today"
                                onChange={ ( val ) => updateClause( idx, { before: val } ) }
                                __nextHasNoMarginBottom
                            />
                        ) }
                        <HStack justify="space-between">
                            <ToggleControl
                                label={ __( 'Inclusive', 'designsetgo' ) }
                                checked={ clause.inclusive }
                                onChange={ ( val ) => updateClause( idx, { inclusive: val } ) }
                                __nextHasNoMarginBottom
                            />
                            <Button
                                variant="tertiary"
                                isDestructive
                                size="small"
                                onClick={ () => removeClause( idx ) }
                            >
                                { __( 'Remove', 'designsetgo' ) }
                            </Button>
                        </HStack>
                    </VStack>
                ) ) }
                <Button variant="secondary" size="small" onClick={ addClause } __next40pxDefaultSize>
                    { __( 'Add date clause', 'designsetgo' ) }
                </Button>
            </DsgoInspectorPanel.Item>
        </DsgoInspectorPanel>
    );
}
```

**Step 9: Wire DateQueryBuilder into edit.js**

In `src/blocks/query/edit.js`:

1. Add import at the top alongside the other builders:
```js
import DateQueryBuilder from './components/DateQueryBuilder';
```

2. Inside the inspector, after `<MetaQueryBuilder>` and before `<AdvancedPanel>`:
```jsx
{ showPostsOnlyPanels && (
    <DateQueryBuilder attributes={attributes} setAttributes={setAttributes} clientId={clientId} />
) }
```

**Step 10: Run all tests**

```bash
npx wp-scripts test-unit-js tests/unit/blocks/query/date-query-builder.test.js
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/render-posts-test.php
```

Both expected: PASS.

**Step 11: Build**

```bash
npm run build && npm run lint:js && npm run lint:css
```

**Step 12: Commit**

```bash
git add src/blocks/query/block.json \
        src/blocks/query/components/DateQueryBuilder.js \
        src/blocks/query/edit.js \
        src/blocks/query/render-posts.php \
        tests/unit/blocks/query/date-query-builder.test.js \
        tests/phpunit/blocks/query/render-posts-test.php
git commit -m "feat(query): add date query builder with after/before/between modes"
```

---

## Task 3: Multi-level AND/OR groups (shared shell component)

Extracts a reusable `ClauseGroupShell` component so both TaxQueryBuilder and MetaQueryBuilder can support nested groups. Each entry in a `clauses` array is either a leaf clause or a group (detected by presence of a `clauses` key).

**Nested group shape:**
```js
// Leaf (tax):
{ taxonomy: 'category', terms: [1], operator: 'IN', include_children: true }

// Group (any depth):
{ clauses: [...], relation: 'AND' | 'OR' }
```

**Files:**
- Create: `src/blocks/query/components/ClauseGroupShell.js`
- Modify: `src/blocks/query/components/TaxQueryBuilder.js`
- Modify: `src/blocks/query/render-posts.php`
- Modify: `tests/phpunit/blocks/query/render-posts-test.php`
- Modify: `tests/unit/blocks/query/tax-query-builder.test.js`

### Step 1: Write failing PHP tests for nested tax groups

Add to `tests/phpunit/blocks/query/render-posts-test.php`:

```php
public function test_nested_tax_group_builds_correctly() {
    $atts = [
        'source'   => 'posts',
        'taxQuery' => [
            'relation' => 'AND',
            'clauses'  => [
                [
                    'relation' => 'OR',
                    'clauses'  => [
                        [ 'taxonomy' => 'category', 'terms' => [ 1 ], 'operator' => 'IN', 'include_children' => true ],
                        [ 'taxonomy' => 'category', 'terms' => [ 2 ], 'operator' => 'IN', 'include_children' => true ],
                    ],
                ],
                [ 'taxonomy' => 'post_tag', 'terms' => [ 5 ], 'operator' => 'IN', 'include_children' => false ],
            ],
        ],
    ];
    $args = designsetgo_query_build_posts_args( $atts, null );
    $this->assertEquals( 'AND', $args['tax_query']['relation'] );
    $sub = $args['tax_query'][0];
    $this->assertEquals( 'OR', $sub['relation'] );
    $this->assertEquals( 'category', $sub[0]['taxonomy'] );
    $this->assertEquals( 'category', $sub[1]['taxonomy'] );
    $leaf = $args['tax_query'][1];
    $this->assertEquals( 'post_tag', $leaf['taxonomy'] );
    $this->assertFalse( $leaf['include_children'] );
}
```

### Step 2: Run to confirm failure

```bash
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/render-posts-test.php --filter=test_nested_tax_group
```

Expected: FAIL.

### Step 3: Refactor PHP tax_query builder to be recursive

In `src/blocks/query/render-posts.php`, replace the existing `$tax_clauses` block (lines ~260–279) with a recursive helper function and updated caller.

Add the private helper function **above** `designsetgo_query_build_posts_args()`:

```php
/**
 * Recursively builds a WP_Query tax_query clause or nested group.
 *
 * @param array $entry Clause or group from block attributes.
 * @return array|null WP_Query tax_query entry, or null if invalid.
 */
function designsetgo_build_tax_query_entry( array $entry ) {
    if ( isset( $entry['clauses'] ) ) {
        // Nested group.
        $sub = array(
            'relation' => ( 'OR' === ( $entry['relation'] ?? 'AND' ) ) ? 'OR' : 'AND',
        );
        foreach ( (array) $entry['clauses'] as $child ) {
            $built = designsetgo_build_tax_query_entry( $child );
            if ( null !== $built ) {
                $sub[] = $built;
            }
        }
        return count( $sub ) > 1 ? $sub : null;
    }
    // Leaf clause.
    if ( empty( $entry['taxonomy'] ) || empty( $entry['terms'] ) ) {
        return null;
    }
    return array(
        'taxonomy'         => sanitize_key( (string) $entry['taxonomy'] ),
        'terms'            => array_map( 'absint', (array) $entry['terms'] ),
        'operator'         => in_array( ( $entry['operator'] ?? 'IN' ), array( 'IN', 'NOT IN', 'AND' ), true ) ? $entry['operator'] : 'IN',
        'include_children' => isset( $entry['include_children'] ) ? (bool) $entry['include_children'] : true,
    );
}
```

Then replace the `$tax_clauses` block in `designsetgo_query_build_posts_args()`:

```php
// Tax query (supports nested AND/OR groups).
$tax_clauses = isset( $atts['taxQuery']['clauses'] ) ? (array) $atts['taxQuery']['clauses'] : array();
if ( ! empty( $tax_clauses ) ) {
    $tax_query = array(
        'relation' => ( 'OR' === ( $atts['taxQuery']['relation'] ?? 'AND' ) ) ? 'OR' : 'AND',
    );
    foreach ( $tax_clauses as $entry ) {
        $built = designsetgo_build_tax_query_entry( $entry );
        if ( null !== $built ) {
            $tax_query[] = $built;
        }
    }
    if ( count( $tax_query ) > 1 ) {
        $args['tax_query'] = $tax_query;
    }
}
```

### Step 4: Run PHP tests — expect pass

```bash
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/render-posts-test.php
```

All existing + new tests expected: PASS.

### Step 5: Create ClauseGroupShell.js

Create `src/blocks/query/components/ClauseGroupShell.js`:

```jsx
import { __ } from '@wordpress/i18n';
import { Button, SelectControl, VStack } from '@wordpress/components';

/**
 * Reusable recursive group shell for tax/meta clause builders.
 *
 * Props:
 *   group        - { relation, clauses }
 *   onChange     - (patch) => void — called with { relation?, clauses? }
 *   onRemove     - () => void | undefined — present on nested groups, absent on root
 *   depth        - number (0 = root)
 *   renderClause - (clause, idx, update, remove) => JSX — renders one leaf clause
 *   newClause    - object — default shape for a new leaf clause
 */
export default function ClauseGroupShell( {
    group,
    onChange,
    onRemove,
    depth = 0,
    renderClause,
    newClause,
} ) {
    const { relation = 'AND', clauses = [] } = group;

    const updateEntry = ( idx, patch ) => {
        const next = clauses.map( ( c, i ) => ( i === idx ? { ...c, ...patch } : c ) );
        onChange( { clauses: next } );
    };

    const replaceEntry = ( idx, entry ) => {
        const next = clauses.map( ( c, i ) => ( i === idx ? entry : c ) );
        onChange( { clauses: next } );
    };

    const removeEntry = ( idx ) =>
        onChange( { clauses: clauses.filter( ( _, i ) => i !== idx ) } );

    const addClause = () =>
        onChange( { clauses: [ ...clauses, { ...newClause } ] } );

    const addGroup = () =>
        onChange( {
            clauses: [ ...clauses, { relation: 'AND', clauses: [ { ...newClause } ] } ],
        } );

    return (
        <VStack
            spacing={ 2 }
            className={ `dsgo-clause-group dsgo-clause-group--depth-${ depth }` }
            style={ depth > 0 ? { paddingLeft: '12px', borderLeft: '2px solid var(--wp-admin-theme-color-darker-10, #ccc)' } : undefined }
        >
            { ( clauses.length > 1 || depth > 0 ) && (
                <SelectControl
                    label={ __( 'Match', 'designsetgo' ) }
                    value={ relation }
                    options={ [
                        { label: __( 'All (AND)', 'designsetgo' ), value: 'AND' },
                        { label: __( 'Any (OR)', 'designsetgo' ), value: 'OR' },
                    ] }
                    onChange={ ( val ) => onChange( { relation: val } ) }
                    __nextHasNoMarginBottom
                />
            ) }

            { clauses.map( ( entry, idx ) =>
                Array.isArray( entry.clauses ) ? (
                    <ClauseGroupShell
                        key={ idx }
                        group={ entry }
                        onChange={ ( patch ) => replaceEntry( idx, { ...entry, ...patch } ) }
                        onRemove={ () => removeEntry( idx ) }
                        depth={ depth + 1 }
                        renderClause={ renderClause }
                        newClause={ newClause }
                    />
                ) : (
                    renderClause( entry, idx, updateEntry, removeEntry )
                )
            ) }

            <div className="dsgo-clause-group__actions">
                <Button variant="secondary" size="small" onClick={ addClause } __next40pxDefaultSize>
                    { __( '+ Clause', 'designsetgo' ) }
                </Button>
                <Button variant="secondary" size="small" onClick={ addGroup } __next40pxDefaultSize>
                    { __( '+ Group', 'designsetgo' ) }
                </Button>
                { onRemove && (
                    <Button variant="tertiary" isDestructive size="small" onClick={ onRemove }>
                        { __( 'Remove group', 'designsetgo' ) }
                    </Button>
                ) }
            </div>
        </VStack>
    );
}
```

### Step 6: Refactor TaxQueryBuilder to use ClauseGroupShell

In `src/blocks/query/components/TaxQueryBuilder.js`:

1. Import `ClauseGroupShell`:
```js
import ClauseGroupShell from './ClauseGroupShell';
```

2. Replace the existing clause-list rendering and buttons with a single `<ClauseGroupShell>` call. The `renderClause` prop receives one leaf clause and renders the existing per-clause VStack (taxonomy select, TermPicker, operator select, include_children toggle, remove button).

3. Remove the old `addClause` standalone button and the flat `clauses.map(...)` block — ClauseGroupShell owns that loop now.

4. Keep `TermPicker` as an internal sub-component — it is still needed by `renderClause`.

The final return inside `TaxQueryBuilder` should look like:

```jsx
return (
    <DsgoInspectorPanel
        title={ __( 'Taxonomy filters', 'designsetgo' ) }
        panelName="settings"
        panelId={ clientId }
        onDeselect={ () => setAttributes( { taxQuery: { relation: 'AND', clauses: [] } } ) }
        hasValue={ taxQuery.clauses.length > 0 }
        isShownByDefault
    >
        <DsgoInspectorPanel.Item
            label={ __( 'Taxonomy clauses', 'designsetgo' ) }
            hasValue={ taxQuery.clauses.length > 0 }
            onDeselect={ () => setAttributes( { taxQuery: { relation: 'AND', clauses: [] } } ) }
            isShownByDefault
        >
            <ClauseGroupShell
                group={ taxQuery }
                onChange={ ( patch ) =>
                    setAttributes( { taxQuery: { ...taxQuery, ...patch } } )
                }
                depth={ 0 }
                newClause={ { taxonomy: relevant[0]?.slug ?? 'category', terms: [], operator: 'IN', include_children: true } }
                renderClause={ ( clause, idx, updateEntry, removeEntry ) => (
                    <VStack key={ idx } spacing={ 2 } className="dsgo-query-tax-clause">
                        <SelectControl
                            label={ __( 'Taxonomy', 'designsetgo' ) }
                            value={ clause.taxonomy }
                            options={ relevant.map( ( t ) => ( { label: t.name, value: t.slug } ) ) }
                            onChange={ ( val ) => updateEntry( idx, { taxonomy: val, terms: [] } ) }
                            __nextHasNoMarginBottom
                        />
                        <TermPicker
                            taxonomy={ clause.taxonomy }
                            selected={ clause.terms }
                            onChange={ ( val ) => updateEntry( idx, { terms: val } ) }
                        />
                        <HStack>
                            <SelectControl
                                label={ __( 'Operator', 'designsetgo' ) }
                                value={ clause.operator }
                                options={ [
                                    { label: 'IN', value: 'IN' },
                                    { label: 'NOT IN', value: 'NOT IN' },
                                    { label: 'AND', value: 'AND' },
                                ] }
                                onChange={ ( val ) => updateEntry( idx, { operator: val } ) }
                                __nextHasNoMarginBottom
                            />
                            <Button
                                variant="tertiary"
                                isDestructive
                                size="small"
                                onClick={ () => removeEntry( idx ) }
                            >
                                { __( 'Remove', 'designsetgo' ) }
                            </Button>
                        </HStack>
                        <ToggleControl
                            label={ __( 'Include child terms', 'designsetgo' ) }
                            checked={ clause.include_children ?? true }
                            onChange={ ( val ) => updateEntry( idx, { include_children: val } ) }
                            __nextHasNoMarginBottom
                        />
                    </VStack>
                ) }
            />
        </DsgoInspectorPanel.Item>
    </DsgoInspectorPanel>
);
```

### Step 7: Write JS test for nested group

In `tests/unit/blocks/query/tax-query-builder.test.js`, add:

```js
it( 'adds a nested group when clicking + Group', () => {
    const setAttributes = jest.fn();
    const { getByRole } = render(
        <TaxQueryBuilder
            attributes={ { taxQuery: { relation: 'AND', clauses: [] }, postType: 'post' } }
            setAttributes={ setAttributes }
            clientId="test"
        />
    );
    fireEvent.click( getByRole( 'button', { name: /\+ group/i } ) );
    const call = setAttributes.mock.calls[0][0];
    expect( call.taxQuery.clauses[0] ).toHaveProperty( 'clauses' );
    expect( call.taxQuery.clauses[0].relation ).toBe( 'AND' );
} );
```

### Step 8: Run all tests

```bash
npx wp-scripts test-unit-js tests/unit/blocks/query/tax-query-builder.test.js
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/render-posts-test.php
```

Both expected: PASS.

### Step 9: Build

```bash
npm run build && npm run lint:js
```

### Step 10: Commit

```bash
git add src/blocks/query/components/ClauseGroupShell.js \
        src/blocks/query/components/TaxQueryBuilder.js \
        src/blocks/query/render-posts.php \
        tests/phpunit/blocks/query/render-posts-test.php \
        tests/unit/blocks/query/tax-query-builder.test.js
git commit -m "feat(query): add multi-level AND/OR groups to taxonomy clause builder"
```

---

## Task 4: Multi-level AND/OR groups in MetaQueryBuilder

Mirrors Task 3 but for `metaQuery`. Uses the `ClauseGroupShell` created in Task 3, so the recursive PHP builder needs the same treatment. Meta clauses are leaves only (no sub-taxonomy pickers needed).

**Files:**
- Modify: `src/blocks/query/components/MetaQueryBuilder.js`
- Modify: `src/blocks/query/render-posts.php`
- Modify: `tests/phpunit/blocks/query/render-posts-test.php`
- Modify: `tests/unit/blocks/query/meta-query-builder.test.js`

### Step 1: Write failing PHP test

Add to `tests/phpunit/blocks/query/render-posts-test.php`:

```php
public function test_nested_meta_group_builds_correctly() {
    $atts = [
        'source'    => 'posts',
        'metaQuery' => [
            'relation' => 'AND',
            'clauses'  => [
                [
                    'relation' => 'OR',
                    'clauses'  => [
                        [ 'key' => 'status', 'compare' => '=', 'value' => 'featured', 'type' => 'CHAR' ],
                        [ 'key' => 'featured', 'compare' => '=', 'value' => '1', 'type' => 'NUMERIC' ],
                    ],
                ],
                [ 'key' => 'active', 'compare' => '=', 'value' => '1', 'type' => 'CHAR' ],
            ],
        ],
    ];
    $args = designsetgo_query_build_posts_args( $atts, null );
    $this->assertEquals( 'AND', $args['meta_query']['relation'] );
    $sub = $args['meta_query'][0];
    $this->assertEquals( 'OR', $sub['relation'] );
    $this->assertEquals( 'status', $sub[0]['key'] );
    $this->assertEquals( 'active', $args['meta_query'][1]['key'] );
}
```

### Step 2: Run to confirm failure

```bash
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/render-posts-test.php --filter=test_nested_meta_group
```

### Step 3: Add recursive meta_query helper to render-posts.php

Add above `designsetgo_query_build_posts_args()` (alongside `designsetgo_build_tax_query_entry`):

```php
/**
 * Recursively builds a WP_Query meta_query clause or nested group.
 *
 * @param array $entry Clause or group from block attributes.
 * @return array|null WP_Query meta_query entry, or null if invalid.
 */
function designsetgo_build_meta_query_entry( array $entry ) {
    if ( isset( $entry['clauses'] ) ) {
        $sub = array(
            'relation' => ( 'OR' === ( $entry['relation'] ?? 'AND' ) ) ? 'OR' : 'AND',
        );
        foreach ( (array) $entry['clauses'] as $child ) {
            $built = designsetgo_build_meta_query_entry( $child );
            if ( null !== $built ) {
                $sub[] = $built;
            }
        }
        return count( $sub ) > 1 ? $sub : null;
    }
    if ( empty( $entry['key'] ) ) {
        return null;
    }
    $valid_compare = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS' );
    $valid_type    = array( 'CHAR', 'NUMERIC', 'DATE' );
    return array(
        'key'     => sanitize_text_field( (string) $entry['key'] ),
        'value'   => sanitize_text_field( (string) ( $entry['value'] ?? '' ) ),
        'compare' => in_array( ( $entry['compare'] ?? '=' ), $valid_compare, true ) ? $entry['compare'] : '=',
        'type'    => in_array( ( $entry['type'] ?? 'CHAR' ), $valid_type, true ) ? $entry['type'] : 'CHAR',
    );
}
```

Replace the `$meta_clauses` block in `designsetgo_query_build_posts_args()`:

```php
// Meta query (supports nested AND/OR groups).
$meta_clauses = isset( $atts['metaQuery']['clauses'] ) ? (array) $atts['metaQuery']['clauses'] : array();
if ( ! empty( $meta_clauses ) ) {
    $meta_query = array(
        'relation' => ( 'OR' === ( $atts['metaQuery']['relation'] ?? 'AND' ) ) ? 'OR' : 'AND',
    );
    foreach ( $meta_clauses as $entry ) {
        $built = designsetgo_build_meta_query_entry( $entry );
        if ( null !== $built ) {
            $meta_query[] = $built;
        }
    }
    if ( count( $meta_query ) > 1 ) {
        $args['meta_query'] = $meta_query;
    }
}
```

### Step 4: Run PHP tests — expect pass

```bash
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/render-posts-test.php
```

### Step 5: Refactor MetaQueryBuilder to use ClauseGroupShell

In `src/blocks/query/components/MetaQueryBuilder.js`:

1. Import `ClauseGroupShell`.
2. Replace the flat clause list + add button with `<ClauseGroupShell>`, passing `renderClause` that renders the existing per-clause VStack (key TextControl, compare SelectControl, type SelectControl, value TextControl, remove Button).
3. `newClause` prop: `{ key: '', compare: '=', value: '', type: 'CHAR' }`.

### Step 6: Write and run JS test

In `tests/unit/blocks/query/meta-query-builder.test.js`, add a test mirroring the TaxQueryBuilder group test:

```js
it( 'adds a nested group when clicking + Group', () => {
    const setAttributes = jest.fn();
    const { getByRole } = render(
        <MetaQueryBuilder
            attributes={ { metaQuery: { relation: 'AND', clauses: [] } } }
            setAttributes={ setAttributes }
            clientId="test"
        />
    );
    fireEvent.click( getByRole( 'button', { name: /\+ group/i } ) );
    const call = setAttributes.mock.calls[0][0];
    expect( call.metaQuery.clauses[0] ).toHaveProperty( 'clauses' );
} );
```

```bash
npx wp-scripts test-unit-js tests/unit/blocks/query/meta-query-builder.test.js
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/render-posts-test.php
```

Both expected: PASS.

### Step 7: Build and commit

```bash
npm run build && npm run lint:js
git add src/blocks/query/components/MetaQueryBuilder.js \
        src/blocks/query/render-posts.php \
        tests/phpunit/blocks/query/render-posts-test.php \
        tests/unit/blocks/query/meta-query-builder.test.js
git commit -m "feat(query): add multi-level AND/OR groups to meta clause builder"
```

---

## Task 5: Query Monitor debug panel

Adds a DSGo panel to Query Monitor (when active) that shows per-render query args, result count, SQL, and active filter state. Completely standalone — no changes to existing query code paths; data is captured via existing PHP action hooks.

**Files:**
- Create: `includes/class-query-qm-collector.php`
- Create: `includes/class-query-qm-output.php`
- Modify: `includes/class-plugin.php` (or wherever blocks are registered — load both classes conditionally)
- Modify: `src/blocks/query/render-helpers.php` (fire a capture action)

### Step 1: Write failing PHP test for collector

Create `tests/phpunit/blocks/query/qm-collector-test.php`:

```php
<?php
defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

class QMCollectorTest extends TestCase {

    public function test_collector_ignores_data_when_qm_absent() {
        // If QM is not active the collector should not be registered —
        // calling the action should be a no-op.
        $this->assertFalse( class_exists( 'DesignSetGo\QueryMonitor\Collector' ) );
    }

    public function test_data_structure_is_serialisable() {
        $data = [
            'query_id'    => 'abc123',
            'source'      => 'posts',
            'wp_args'     => [ 'post_type' => 'post', 'posts_per_page' => 6 ],
            'found_posts' => 12,
            'sql'         => 'SELECT ...',
            'filters'     => [],
            'duration_ms' => 14.5,
        ];
        $this->assertIsString( wp_json_encode( $data ) );
    }
}
```

### Step 2: Run to confirm expected state

```bash
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/qm-collector-test.php
```

Expected: PASS (test_collector_ignores_data_when_qm_absent passes because QM is not loaded in the test environment; test_data_structure_is_serialisable passes trivially).

This test serves as a smoke check — the real value is integration.

### Step 3: Add capture action to render-helpers.php

In `src/blocks/query/render-helpers.php`, inside `designsetgo_query_render_item()` (or `designsetgo_query_render()`) — after the WP_Query runs and results are known — fire:

```php
/**
 * Fires after a DSGo query executes. Consumed by the Query Monitor panel
 * when QM is active; no-op otherwise.
 *
 * @param array    $capture {
 *     @type string $query_id    Block queryId attribute.
 *     @type string $source      Query source (posts/users/terms/…).
 *     @type array  $wp_args     WP_Query args array.
 *     @type int    $found_posts Total matching posts before pagination.
 *     @type string $sql         Last query executed by wpdb.
 *     @type array  $filters     Active filter state from IAPI store (empty on first render).
 *     @type float  $duration_ms Milliseconds the WP_Query took.
 * }
 */
do_action( 'designsetgo_query_did_render', $capture );
```

Build `$capture` immediately after `$query = new WP_Query( $args )`:

```php
$t_start = microtime( true );
$query   = new WP_Query( $args );
$duration_ms = ( microtime( true ) - $t_start ) * 1000;

$capture = array(
    'query_id'    => $atts['queryId'] ?? '',
    'source'      => $atts['source'] ?? 'posts',
    'wp_args'     => $args,
    'found_posts' => $query->found_posts,
    'sql'         => $GLOBALS['wpdb']->last_query ?? '',
    'filters'     => array(),
    'duration_ms' => round( $duration_ms, 2 ),
);
do_action( 'designsetgo_query_did_render', $capture );
```

### Step 4: Create the QM Collector

Create `includes/class-query-qm-collector.php`:

```php
<?php
defined( 'ABSPATH' ) || exit;

namespace DesignSetGo\QueryMonitor;

if ( ! class_exists( '\QM_Collector' ) ) {
    return;
}

/**
 * Query Monitor data collector for DesignSetGo queries.
 */
class Collector extends \QM_Collector {

    public $id = 'dsgo_queries';

    /** @var array[] */
    private array $renders = [];

    public function __construct() {
        parent::__construct();
        add_action( 'designsetgo_query_did_render', [ $this, 'capture' ] );
    }

    public function capture( array $data ): void {
        $this->renders[] = $data;
    }

    public function process(): void {
        $this->data['renders'] = $this->renders;
        $this->data['count']   = count( $this->renders );
    }

    public function name(): string {
        return __( 'DSGo Queries', 'designsetgo' );
    }
}

add_filter(
    'qm/collectors',
    static function ( array $collectors ) {
        $collectors['dsgo_queries'] = new Collector();
        return $collectors;
    }
);
```

### Step 5: Create the QM Output panel

Create `includes/class-query-qm-output.php`:

```php
<?php
defined( 'ABSPATH' ) || exit;

namespace DesignSetGo\QueryMonitor;

if ( ! class_exists( '\QM_Output_Html' ) ) {
    return;
}

/**
 * Query Monitor HTML output for DesignSetGo queries.
 */
class OutputHtml extends \QM_Output_Html {

    public function __construct( \QM_Collector $collector ) {
        parent::__construct( $collector );
        add_filter( 'qm/output/menus', [ $this, 'admin_menu' ], 80 );
    }

    public function name(): string {
        return __( 'DSGo Queries', 'designsetgo' );
    }

    public function output(): void {
        $data    = $this->collector->get_data();
        $renders = $data['renders'] ?? [];

        $this->before_non_tabular_output();

        if ( empty( $renders ) ) {
            echo '<p>' . esc_html__( 'No DSGo queries ran on this request.', 'designsetgo' ) . '</p>';
            $this->after_non_tabular_output();
            return;
        }

        echo '<p>' . esc_html( sprintf(
            /* translators: %d: number of queries */
            _n( '%d DSGo query ran on this request.', '%d DSGo queries ran on this request.', count( $renders ), 'designsetgo' ),
            count( $renders )
        ) ) . '</p>';

        foreach ( $renders as $i => $r ) {
            echo '<h3>' . esc_html( sprintf( '#%d — %s (%s)', $i + 1, $r['query_id'], $r['source'] ) ) . '</h3>';
            echo '<table class="qm-sortable"><thead><tr>';
            echo '<th>' . esc_html__( 'Property', 'designsetgo' ) . '</th>';
            echo '<th>' . esc_html__( 'Value', 'designsetgo' ) . '</th>';
            echo '</tr></thead><tbody>';

            $rows = [
                __( 'Found posts', 'designsetgo' ) => $r['found_posts'],
                __( 'Duration (ms)', 'designsetgo' ) => $r['duration_ms'],
                __( 'WP_Query args', 'designsetgo' ) => '<pre style="margin:0;white-space:pre-wrap">' . esc_html( wp_json_encode( $r['wp_args'], JSON_PRETTY_PRINT ) ) . '</pre>',
                __( 'SQL', 'designsetgo' ) => '<code>' . esc_html( $r['sql'] ) . '</code>',
            ];

            foreach ( $rows as $label => $value ) {
                echo '<tr><td>' . esc_html( $label ) . '</td><td>' . wp_kses_post( $value ) . '</td></tr>';
            }

            echo '</tbody></table>';
        }

        $this->after_non_tabular_output();
    }

    public function admin_menu( array $menu ): array {
        $data  = $this->collector->get_data();
        $count = $data['count'] ?? 0;

        $menu[ $this->collector->id ] = $this->menu( [
            'title' => esc_html( sprintf(
                /* translators: %d: query count */
                _n( 'DSGo (%d)', 'DSGo (%d)', $count, 'designsetgo' ),
                $count
            ) ),
        ] );

        return $menu;
    }
}

add_filter(
    'qm/outputter/html',
    static function ( array $outputters, array $collectors ) {
        if ( isset( $collectors['dsgo_queries'] ) ) {
            $outputters['dsgo_queries'] = new OutputHtml( $collectors['dsgo_queries'] );
        }
        return $outputters;
    },
    80,
    2
);
```

### Step 6: Load QM classes conditionally in Plugin bootstrap

In `includes/class-plugin.php` (or whatever file bootstraps the plugin), in the constructor or `init()` method, add after the block classes are loaded:

```php
// Query Monitor integration — loads only when QM is active.
if ( defined( 'QM_VERSION' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'class-query-qm-collector.php';
    require_once plugin_dir_path( __FILE__ ) . 'class-query-qm-output.php';
}
```

### Step 7: Run PHP lint

```bash
npm run lint:php
```

Fix any PHPCS warnings before committing.

### Step 8: Manual verification

With `wp-env` running and Query Monitor active (install via `npx wp-env install-plugin query-monitor` or drop the plugin zip in `wp-content/plugins/`):

1. Load a page that has a Dynamic Query block.
2. Open QM's toolbar → look for the "DSGo (N)" menu item.
3. Confirm the panel shows query_id, source, found_posts, duration_ms, WP_Query args JSON, SQL.
4. Without QM active, confirm no errors in PHP logs.

### Step 9: Commit

```bash
git add includes/class-query-qm-collector.php \
        includes/class-query-qm-output.php \
        includes/class-plugin.php \
        src/blocks/query/render-helpers.php \
        tests/phpunit/blocks/query/qm-collector-test.php
git commit -m "feat(query): add Query Monitor debug panel for DSGo queries"
```

---

## Task 6: Dynamic CSS from meta (style bindings)

Adds a `dsgoStyleBinding` attribute to every block (registered as a global extension like `dsgoVisibility`). Authors map CSS property names → a DSGo binding source + key. The server-side `render_block` filter resolves the value and injects it as an inline `style` attribute on the block's root element. The editor shows a visual indicator badge in the inspector when bindings are active and provides add/remove UI in the Advanced controls.

**Supported sources:** `designsetgo/post-meta`, `designsetgo/acf`, `designsetgo/metabox`, `designsetgo/pods`, `designsetgo/jetengine` (same set as v2.4 bindings).

**Attribute shape:**
```json
{
  "dsgoStyleBinding": {
    "--brand-color": { "source": "designsetgo/post-meta", "args": { "key": "brand_color" } },
    "background-color": { "source": "designsetgo/acf", "args": { "name": "bg_color" } }
  }
}
```

Valid CSS property names: custom properties (`--[a-zA-Z][a-zA-Z0-9-_]*`) or standard CSS properties (`[a-z][a-z-]*`). Values with `url(`, `expression(`, or `javascript:` are rejected.

**Files:**
- Create: `src/extensions/style-binding/filters.js`
- Create: `src/extensions/style-binding/index.js`
- Modify: `src/index.js` (add import)
- Create: `includes/class-style-binding.php`
- Modify: `includes/class-plugin.php` (require the new class)
- Create: `tests/phpunit/extensions/style-binding-test.php`
- Create: `tests/unit/extensions/style-binding.test.js`

### Step 1: Write failing PHP tests

Create `tests/phpunit/extensions/style-binding-test.php`:

```php
<?php
defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

class StyleBindingTest extends TestCase {

    private function make_block( array $attrs ): array {
        return [
            'blockName' => 'core/paragraph',
            'attrs'     => $attrs,
            'innerBlocks' => [],
            'innerHTML'   => '',
        ];
    }

    public function test_no_binding_returns_html_unchanged() {
        $sb   = new DesignSetGo\StyleBinding();
        $html = '<p class="wp-block">Hello</p>';
        $this->assertSame( $html, $sb->apply_style_bindings( $html, $this->make_block( [] ) ) );
    }

    public function test_invalid_css_prop_is_skipped() {
        // Props with spaces or dangerous chars are invalid.
        $sb = new DesignSetGo\StyleBinding();
        // Return value should be unchanged because the prop is invalid.
        $html = '<p>Hello</p>';
        $block = $this->make_block( [
            'dsgoStyleBinding' => [
                'bad prop!' => [ 'source' => 'designsetgo/post-meta', 'args' => [ 'key' => 'x' ] ],
            ],
        ] );
        $this->assertStringNotContainsString( 'bad prop!', $sb->apply_style_bindings( $html, $block ) );
    }

    public function test_dangerous_value_is_rejected() {
        $sb = new DesignSetGo\StyleBinding();
        add_filter( 'designsetgo_style_binding_resolve', function( $val, $source, $args ) {
            return 'url(javascript:alert(1))';
        }, 10, 3 );
        $html  = '<div class="wp-block">X</div>';
        $block = $this->make_block( [
            'dsgoStyleBinding' => [
                '--color' => [ 'source' => 'designsetgo/post-meta', 'args' => [ 'key' => 'c' ] ],
            ],
        ] );
        $result = $sb->apply_style_bindings( $html, $block );
        $this->assertStringNotContainsString( 'javascript', $result );
        remove_all_filters( 'designsetgo_style_binding_resolve' );
    }

    public function test_valid_binding_injects_style_attribute() {
        $sb = new DesignSetGo\StyleBinding();
        add_filter( 'designsetgo_style_binding_resolve', function( $val, $source, $args ) {
            return '#ff0000';
        }, 10, 3 );
        $html  = '<div class="wp-block">X</div>';
        $block = $this->make_block( [
            'dsgoStyleBinding' => [
                '--brand' => [ 'source' => 'designsetgo/post-meta', 'args' => [ 'key' => 'color' ] ],
            ],
        ] );
        $result = $sb->apply_style_bindings( $html, $block );
        $this->assertStringContainsString( '--brand:#ff0000', $result );
        remove_all_filters( 'designsetgo_style_binding_resolve' );
    }

    public function test_existing_style_attribute_is_preserved() {
        $sb = new DesignSetGo\StyleBinding();
        add_filter( 'designsetgo_style_binding_resolve', fn() => 'blue', 10, 3 );
        $html  = '<div style="color:red">X</div>';
        $block = $this->make_block( [
            'dsgoStyleBinding' => [
                'background-color' => [ 'source' => 'designsetgo/post-meta', 'args' => [ 'key' => 'bg' ] ],
            ],
        ] );
        $result = $sb->apply_style_bindings( $html, $block );
        $this->assertStringContainsString( 'color:red', $result );
        $this->assertStringContainsString( 'background-color:blue', $result );
        remove_all_filters( 'designsetgo_style_binding_resolve' );
    }
}
```

### Step 2: Run to confirm failure

```bash
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/extensions/style-binding-test.php
```

Expected: FAIL — class `DesignSetGo\StyleBinding` not found.

### Step 3: Create the PHP StyleBinding class

Create `includes/class-style-binding.php`:

```php
<?php
defined( 'ABSPATH' ) || exit;

namespace DesignSetGo;

/**
 * Applies dsgoStyleBinding attribute values as inline CSS on block root elements.
 *
 * Resolves each bound property via the `designsetgo_style_binding_resolve` filter
 * so third-party code can supply custom sources. Built-in sources: post-meta, acf,
 * metabox, pods, jetengine — resolved directly against the current item's post ID
 * (honours $GLOBALS['designsetgo_parent_stack'] for nested query loops).
 */
class StyleBinding {

    public function __construct() {
        add_filter( 'render_block', [ $this, 'apply_style_bindings' ], 5, 2 );
    }

    /**
     * @param string $html  Rendered block HTML.
     * @param array  $block Parsed block array.
     * @return string
     */
    public function apply_style_bindings( string $html, array $block ): string {
        $binding = $block['attrs']['dsgoStyleBinding'] ?? null;
        if ( empty( $binding ) || ! is_array( $binding ) ) {
            return $html;
        }

        $styles = [];
        foreach ( $binding as $prop => $config ) {
            if ( ! is_string( $prop ) || ! preg_match( '/^--[a-zA-Z][a-zA-Z0-9\-_]*$|^[a-z][a-z\-]*$/', $prop ) ) {
                continue;
            }
            $source = sanitize_key( (string) ( $config['source'] ?? '' ) );
            $args   = is_array( $config['args'] ?? null ) ? $config['args'] : [];

            /**
             * Filters the resolved value for a style binding.
             *
             * @param string|null $value  Resolved value, or null to skip.
             * @param string      $source Binding source slug.
             * @param array       $args   Binding args.
             */
            $value = apply_filters( 'designsetgo_style_binding_resolve', $this->resolve( $source, $args ), $source, $args );

            if ( null === $value || '' === $value ) {
                continue;
            }
            // Reject dangerous CSS values.
            if ( preg_match( '/url\s*\(|expression\s*\(|javascript:/i', $value ) ) {
                continue;
            }
            $styles[] = esc_attr( $prop ) . ':' . esc_attr( $value );
        }

        if ( empty( $styles ) ) {
            return $html;
        }

        $processor = new \WP_HTML_Tag_Processor( $html );
        if ( ! $processor->next_tag() ) {
            return $html;
        }
        $existing = (string) ( $processor->get_attribute( 'style' ) ?? '' );
        $sep      = ( '' !== $existing && ! str_ends_with( rtrim( $existing ), ';' ) ) ? ';' : '';
        $processor->set_attribute( 'style', $existing . $sep . implode( ';', $styles ) );

        return $processor->get_updated_html();
    }

    /**
     * Resolves a binding source+args to a scalar string value.
     * Returns null when the source is unsupported or returns no value.
     *
     * @param string $source Binding source slug.
     * @param array  $args   Binding args.
     * @return string|null
     */
    private function resolve( string $source, array $args ): ?string {
        $post_id = $this->current_post_id();

        switch ( $source ) {
            case 'designsetgo/post-meta':
                $key = sanitize_key( (string) ( $args['key'] ?? '' ) );
                if ( ! $key || ! $post_id ) {
                    return null;
                }
                $val = get_post_meta( $post_id, $key, true );
                return is_scalar( $val ) ? (string) $val : null;

            case 'designsetgo/acf':
                if ( ! function_exists( 'get_field' ) ) {
                    return null;
                }
                $name = sanitize_text_field( (string) ( $args['name'] ?? '' ) );
                if ( ! $name || ! $post_id ) {
                    return null;
                }
                $val = get_field( $name, $post_id );
                return is_scalar( $val ) ? (string) $val : null;

            case 'designsetgo/metabox':
                if ( ! function_exists( 'rwmb_meta' ) ) {
                    return null;
                }
                $id  = sanitize_key( (string) ( $args['id'] ?? '' ) );
                $val = $id && $post_id ? rwmb_meta( $id, [], $post_id ) : null;
                return is_scalar( $val ) ? (string) $val : null;

            case 'designsetgo/pods':
                if ( ! function_exists( 'pods_field' ) ) {
                    return null;
                }
                $field = sanitize_text_field( (string) ( $args['field'] ?? '' ) );
                $val   = $field && $post_id ? pods_field( $field, $post_id ) : null;
                return is_scalar( $val ) ? (string) $val : null;

            case 'designsetgo/jetengine':
                if ( ! isset( jet_engine()->listings->data ) ) {
                    return null;
                }
                $key = sanitize_key( (string) ( $args['key'] ?? '' ) );
                if ( ! $key || ! $post_id ) {
                    return null;
                }
                $val = jet_engine()->listings->data->get_meta( $key )
                    ?? get_post_meta( $post_id, $key, true );
                return is_scalar( $val ) ? (string) $val : null;

            default:
                return null;
        }
    }

    /**
     * Returns the post ID for the current rendering context, honouring nested query loops.
     *
     * @return int|null
     */
    private function current_post_id(): ?int {
        $stack = $GLOBALS['designsetgo_parent_stack'] ?? [];
        if ( ! empty( $stack ) ) {
            $top = end( $stack );
            return (int) ( $top['postId'] ?? 0 ) ?: null;
        }
        $id = get_the_ID();
        return $id ? (int) $id : null;
    }
}
```

### Step 4: Register the class in class-plugin.php

In `includes/class-plugin.php`, after the other block/extension requires, add:

```php
require_once plugin_dir_path( __FILE__ ) . 'class-style-binding.php';
```

And instantiate it in the constructor or `init()`:

```php
new \DesignSetGo\StyleBinding();
```

### Step 5: Run PHP tests — expect pass

```bash
npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/extensions/style-binding-test.php
```

All expected: PASS.

### Step 6: Write failing JS test

Create `tests/unit/extensions/style-binding.test.js`:

```js
import { dispatch } from '@wordpress/data';

// Mock @wordpress/blocks getBlockType
jest.mock( '@wordpress/blocks', () => ( {
    getBlockTypes: () => [],
} ) );

describe( 'style-binding extension', () => {
    it( 'registers dsgoStyleBinding attribute on block registration', () => {
        // Import triggers the addFilter hook
        require( '../../../src/extensions/style-binding/filters' );

        // The filter must be registered (we can verify the hook exists)
        const hooks = require( '@wordpress/hooks' );
        // filters.js adds to 'blocks.registerBlockType'
        // Verify by calling the registered filter with a mock block settings object
        const result = wp.hooks.applyFilters(
            'blocks.registerBlockType',
            { attributes: {} },
            'core/paragraph'
        );
        expect( result.attributes ).toHaveProperty( 'dsgoStyleBinding' );
        expect( result.attributes.dsgoStyleBinding.type ).toBe( 'object' );
    } );
} );
```

### Step 7: Run JS test — expect failure

```bash
npx wp-scripts test-unit-js tests/unit/extensions/style-binding.test.js
```

Expected: FAIL — module not found.

### Step 8: Create the JS extension files

Create `src/extensions/style-binding/filters.js`:

```js
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Button, SelectControl, TextControl, HStack, VStack } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';

const SOURCE_OPTIONS = [
    { label: __( 'Post meta', 'designsetgo' ), value: 'designsetgo/post-meta' },
    { label: __( 'ACF', 'designsetgo' ), value: 'designsetgo/acf' },
    { label: __( 'Meta Box', 'designsetgo' ), value: 'designsetgo/metabox' },
    { label: __( 'Pods', 'designsetgo' ), value: 'designsetgo/pods' },
    { label: __( 'JetEngine', 'designsetgo' ), value: 'designsetgo/jetengine' },
];

// 1. Register dsgoStyleBinding attribute on every block type.
addFilter(
    'blocks.registerBlockType',
    'designsetgo/style-binding-attribute',
    ( settings ) => {
        if ( ! settings.attributes ) {
            settings.attributes = {};
        }
        settings.attributes.dsgoStyleBinding = {
            type: 'object',
            default: {},
        };
        return settings;
    }
);

// 2. Add inspector UI under Advanced controls.
const withStyleBindingInspector = createHigherOrderComponent( ( BlockEdit ) => {
    return function WithStyleBindingInspector( props ) {
        const { attributes, setAttributes } = props;
        const binding = attributes.dsgoStyleBinding ?? {};
        const entries = Object.entries( binding );

        const updateEntry = ( oldProp, newProp, config ) => {
            const next = { ...binding };
            if ( oldProp !== newProp ) {
                delete next[ oldProp ];
            }
            next[ newProp ] = config;
            setAttributes( { dsgoStyleBinding: next } );
        };

        const removeEntry = ( prop ) => {
            const next = { ...binding };
            delete next[ prop ];
            setAttributes( { dsgoStyleBinding: next } );
        };

        const addEntry = () => {
            const key = `--dsgo-binding-${ Date.now() }`;
            setAttributes( {
                dsgoStyleBinding: {
                    ...binding,
                    [ key ]: { source: 'designsetgo/post-meta', args: { key: '' } },
                },
            } );
        };

        return (
            <Fragment>
                <BlockEdit { ...props } />
                <InspectorControls group="advanced">
                    <PanelBody
                        title={ __( 'Style Bindings', 'designsetgo' ) }
                        initialOpen={ entries.length > 0 }
                    >
                        { entries.map( ( [ prop, config ] ) => (
                            <VStack key={ prop } spacing={ 1 } style={ { marginBottom: '12px' } }>
                                <HStack>
                                    <TextControl
                                        label={ __( 'CSS property', 'designsetgo' ) }
                                        value={ prop }
                                        placeholder="--brand-color"
                                        onChange={ ( val ) => updateEntry( prop, val, config ) }
                                        __nextHasNoMarginBottom
                                    />
                                    <Button
                                        variant="tertiary"
                                        isDestructive
                                        size="small"
                                        onClick={ () => removeEntry( prop ) }
                                        style={ { alignSelf: 'flex-end' } }
                                    >
                                        { __( 'Remove', 'designsetgo' ) }
                                    </Button>
                                </HStack>
                                <SelectControl
                                    label={ __( 'Source', 'designsetgo' ) }
                                    value={ config.source }
                                    options={ SOURCE_OPTIONS }
                                    onChange={ ( val ) => updateEntry( prop, prop, { ...config, source: val } ) }
                                    __nextHasNoMarginBottom
                                />
                                <TextControl
                                    label={ __( 'Field key / name', 'designsetgo' ) }
                                    value={ config.args?.key ?? config.args?.name ?? config.args?.id ?? config.args?.field ?? '' }
                                    onChange={ ( val ) => {
                                        const argKey = [ 'designsetgo/acf' ].includes( config.source ) ? 'name'
                                            : [ 'designsetgo/pods' ].includes( config.source ) ? 'field'
                                            : 'key';
                                        updateEntry( prop, prop, { ...config, args: { [ argKey ]: val } } );
                                    } }
                                    __nextHasNoMarginBottom
                                />
                            </VStack>
                        ) ) }
                        <Button variant="secondary" size="small" onClick={ addEntry } __next40pxDefaultSize>
                            { __( '+ Add style binding', 'designsetgo' ) }
                        </Button>
                    </PanelBody>
                </InspectorControls>
            </Fragment>
        );
    };
}, 'withStyleBindingInspector' );

addFilter(
    'editor.BlockEdit',
    'designsetgo/style-binding-inspector',
    withStyleBindingInspector
);
```

Create `src/extensions/style-binding/index.js`:

```js
import './filters';
```

### Step 9: Import in src/index.js

In `src/index.js`, add alongside the other extension imports:

```js
import './extensions/style-binding';
```

### Step 10: Run JS test — expect pass

```bash
npx wp-scripts test-unit-js tests/unit/extensions/style-binding.test.js
```

### Step 11: Build and lint

```bash
npm run build && npm run lint:js && npm run lint:php
```

### Step 12: Commit

```bash
git add src/extensions/style-binding/filters.js \
        src/extensions/style-binding/index.js \
        src/index.js \
        includes/class-style-binding.php \
        includes/class-plugin.php \
        tests/phpunit/extensions/style-binding-test.php \
        tests/unit/extensions/style-binding.test.js
git commit -m "feat(query): add dynamic CSS style bindings from meta/ACF/MetaBox/Pods/JetEngine"
```

---

## Task 7: CHANGELOG + CLAUDE.md update

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `.claude/CLAUDE.md`

**Step 1: Update CHANGELOG.md**

Under `[Unreleased]`, add:

```markdown
### Added
- Date Query Builder: inspector UI for `before`/`after`/`between` date filters with relative expression support (`-30 days`, `today`).
- Multi-level AND/OR filter groups: TaxQueryBuilder and MetaQueryBuilder now support nested `{relation, clauses}` groups at any depth.
- Hierarchical taxonomy drilldown: per-clause `include_children` toggle in TaxQueryBuilder (defaults `true`).
- Query Monitor panel: when QM is active, a "DSGo (N)" panel in the QM toolbar shows per-render query args, found-posts count, duration, and SQL.
- Dynamic CSS style bindings: `dsgoStyleBinding` attribute on every block maps CSS property names → DSGo binding source + key. Values injected as inline styles on the block root element via `render_block` filter.
```

**Step 2: Update CLAUDE.md**

In the `### Query block family (Dynamic Query v2.5)` section (add it), document:

```markdown
### Query block family (Dynamic Query v2.5)

- `dateQuery` attribute on `designsetgo/query` — shape `{ relation, clauses: [{ column, mode, after, before, inclusive }] }`. Supported modes: `after`, `before`, `between`. Date values: ISO `YYYY-MM-DD` or PHP relative expressions (`-30 days`, `today`).
- `taxQuery.clauses[]` entries may now be leaf clauses OR nested groups (`{ relation, clauses: [...] }`). PHP builder: `designsetgo_build_tax_query_entry()` (recursive). Same pattern for `metaQuery` via `designsetgo_build_meta_query_entry()`.
- `include_children` field on each `taxQuery` leaf clause — boolean, defaults `true`. Pass-through to WP_Query `tax_query`.
- `<ClauseGroupShell>` (`src/blocks/query/components/ClauseGroupShell.js`) — shared recursive group chrome (relation selector, + Clause, + Group, Remove group). Used by both TaxQueryBuilder and MetaQueryBuilder via `renderClause` render prop.
- Query Monitor panel: `includes/class-query-qm-collector.php` + `includes/class-query-qm-output.php`. Loaded only when `defined('QM_VERSION')`. Collects data via `designsetgo_query_did_render` action fired from `render-helpers.php` after each WP_Query.
- Dynamic CSS style bindings: `dsgoStyleBinding` global attribute (`src/extensions/style-binding/filters.js`) maps CSS property names (including custom properties `--foo`) → binding source+key. PHP: `DesignSetGo\StyleBinding` (`includes/class-style-binding.php`) resolves via `designsetgo_style_binding_resolve` filter and injects via `WP_HTML_Tag_Processor`. Honours `$GLOBALS['designsetgo_parent_stack']` for nested loop context. Dangerous values (`url(`, `expression(`, `javascript:`) rejected.
```

**Step 3: Commit**

```bash
git add CHANGELOG.md .claude/CLAUDE.md
git commit -m "docs: update CHANGELOG and CLAUDE.md for Dynamic Query v2.5"
```

---

## Verification checklist (ship gate)

- [ ] `npm run build` — no errors, only the pre-existing slider/modal size warnings
- [ ] `npm run lint:js && npm run lint:css && npm run lint:php` — all clean
- [ ] `npx wp-env run tests-cli vendor/bin/phpunit tests/phpunit/blocks/query/` — all pass
- [ ] `npx wp-scripts test-unit-js tests/unit/blocks/query/` — all pass
- [ ] TaxQueryBuilder: `include_children` toggle visible per clause, PHP sends correct value
- [ ] TaxQueryBuilder: "Add Group" button creates a nested group; nested group renders relation selector + its own clauses
- [ ] MetaQueryBuilder: same nested group behavior
- [ ] DateQueryBuilder: `between` mode shows both After and Before fields; `after`/`before` modes show only the relevant field
- [ ] QM panel appears in QM toolbar when Query Monitor is active and page renders a DSGo query block
- [ ] QM panel absent (no errors) when Query Monitor is not installed
- [ ] Style binding: `dsgoStyleBinding` attribute visible in Advanced inspector for every block
- [ ] Style binding: Add/remove binding rows work; CSS prop + source + key inputs save correctly
- [ ] Style binding: PHP injects inline style on block root element with resolved meta value
- [ ] Style binding: dangerous values (`url()`, `expression()`, `javascript:`) silently rejected
- [ ] Style binding: existing `style` attribute preserved when new bindings are appended
- [ ] CHANGELOG entry lives under `[Unreleased]`; plugin version stays at 2.1.0

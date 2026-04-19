# Dynamic Query v2.2 — Facets, Infinite Scroll, Editor Live Preview

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the "rival FacetWP" release of the Dynamic Query block family: persistent facet index powering sub-millisecond per-option counts, infinite-scroll pagination as a first-class kind, and editor live preview that renders real posts with the first item's inner-block template remaining editable.

**Architecture:** A new custom table `{$wpdb->prefix}dsgo_query_facet_index` materializes `(object_id, object_type, facet_key, facet_value)` tuples on `save_post` / taxonomy / user / term hooks. Facet keys auto-register when a `designsetgo/query-filter` block is saved in the editor; power users can add ad-hoc facets in Settings → DesignSetGo → Dynamic Query. A WP-CLI command + admin progress UI owns initial + targeted rebuilds. Per-option counts become an indexed `COUNT(DISTINCT object_id)` with the active facet state's intersection — fast regardless of catalog size. Infinite scroll is implemented as an `auto-advance + sentinel` extension of the existing load-more action in the Interactivity store (no new network path). Editor live preview replaces the current static placeholder by fetching records via `@wordpress/core-data` + a new `/preview` REST route, wrapping the first resolved item in editable `InnerBlocks` and rendering items 2..N as read-only `BlockPreview` with per-item context.

**Source brief:** [`docs/plans/claude-chat.md`](./claude-chat.md) (v1 research) + the v2 brainstorming transcript in this session (locks: Option 2 index table, Option 3 infinite kind, Option 1 live preview, Option 2+3 hybrid facet scope).

**Locked design decisions (from brainstorming):**
- **Facet engine:** Custom index table. Not transient-cached live queries, not naive live `COUNT(*)`. Rebuildable on demand.
- **Index scope:** *Auto-registered from filter blocks* + *Settings allowlist for ad-hoc facets*. Not scan-everything.
- **Infinite scroll:** New `paginationKind: 'infinite'` variation. Internally reuses the existing `loadMore` action + a sentinel. Auto-pauses after 3 auto-loads (reveals visible button). `prefers-reduced-motion` disables auto-advance.
- **Editor live preview:** Real data via `useEntityRecords`. First item wraps the editable `InnerBlocks`; items 2..N are `BlockPreview`. New REST route `designsetgo/v1/query/preview` resolves users/terms for the preview.
- **Skeletons:** CSS-only. Applied via `aria-busy="true"` during navigation / filter refresh. No new sibling block, no skeleton component.
- **Version bump:** `2.2.0`. Ships as one PR off `main`.

**Tech Stack:**
- WordPress 6.5+ (Block Bindings, existing baseline) + WordPress 6.6+ Interactivity API (already used).
- `dbDelta()` for schema install; `upgrade_{$from}_to_{$to}()` pattern in `includes/class-plugin.php` for migrations.
- `@wordpress/core-data` `useEntityRecords` for editor preview fetching.
- `@wordpress/block-editor` `BlockPreview` + `useInnerBlocksProps` for editable-first-item template.
- `IntersectionObserver` (native; no polyfill — baseline is evergreen browsers + Safari 12.1+).
- Tests: Jest (`npm run test:unit`), PHPUnit (`npm run test:php`), Playwright (`npm run test:e2e`).

**Non-goals for v2.2 (deferred to v2.3+):**
- Nested loops / parent-context tokens → v2.3.
- Relationship-field source → v2.3.
- Conditional inner-block visibility → v2.3.
- Group-by / partitioning → v2.3.
- JetEngine / Meta Box / Pods binding sources → v2.4.
- PHP escape-hatch UI, JSON export/import → v2.4.
- Date-query UI, multi-level AND/OR tree, hierarchical facet drilldown → v2.5+.
- Headless REST parity, Query Monitor panel, dynamic-CSS-from-meta → v2.5+.

---

## Prerequisites

- [ ] **Spin a fresh worktree off `main`.** Use `superpowers:using-git-worktrees`. Branch prefix: `claude/query-v2.2-facets`. Do not execute this plan in the current workspace.
- [ ] **Confirm WP test env.** Run `npm run wp-env start` and verify `http://localhost:8888` loads. Log in as admin.
- [ ] **Seed content.** Import the WordPress test dataset ("theme-unit-test-data") so facet counts have something to aggregate. Command: `npx wp-env run cli wp import /var/www/html/wp-content/plugins/designsetgo/tests/fixtures/theme-unit-test-data.xml --authors=create` (fixture path used in v1 tests). Fallback: `npx wp-env run cli wp post generate --count=200`.

---

## File Structure

### New files

| Path | Responsibility |
|---|---|
| `includes/blocks/class-query-facet-index.php` | `DesignSetGo\Blocks\Query\FacetIndex`. Owns the custom table lifecycle (install / upgrade / drop), single-object reindex, targeted rebuild by facet key, full rebuild, and the count query used by filter renders. |
| `includes/blocks/class-query-facet-registry.php` | `DesignSetGo\Blocks\Query\FacetRegistry`. In-memory registry (backed by `dsgo_query_facets` option) of `{facet_key, facet_type, facet_source, label}`. Auto-populated when a `designsetgo/query-filter` block is saved; extensible via the `designsetgo_query_registered_facets` filter. |
| `includes/blocks/class-query-facet-cli.php` | `DesignSetGo\Blocks\Query\FacetCLI`. Registers `wp dsgo query index` subcommands (`rebuild`, `rebuild-facet`, `status`, `drop`). Loaded only when `defined('WP_CLI')`. |
| `includes/admin/class-query-facet-admin.php` | `DesignSetGo\Admin\QueryFacetAdmin`. Settings screen at Settings → DesignSetGo → Dynamic Query with (a) ad-hoc facet allowlist (key, type, source), (b) "Rebuild index" button with real-time progress via AJAX polling. |
| `src/blocks/query/hooks/useFacetRegistration.js` | Client-side hook invoked by `designsetgo/query-filter` on block save: POSTs to `designsetgo/v1/query/facet-register` so the PHP registry stays in sync with editor state. |
| `src/blocks/query/components/EditorPreviewList.js` | Editor-side list renderer: calls `useEntityRecords` for Posts source, the new `/preview` REST route for Users/Terms, wraps item 0 in editable `InnerBlocks`, wraps items 1..N in `BlockPreview` with per-item context. ≤300 lines. |
| `src/blocks/query-pagination/components/InfiniteScrollControls.js` | Inspector controls specific to `paginationKind: 'infinite'`: auto-pause threshold (default 3), button-label-when-paused, sentinel-offset-px. |
| `src/admin/query-facet-dashboard/index.js` | Admin screen React app: rebuild button, live progress bar polling `designsetgo/v1/query/facet-status`, ad-hoc facet editor. Built by existing `@wordpress/scripts` webpack entry. |
| `src/admin/query-facet-dashboard/style.scss` | Admin-only styles. |
| `src/blocks/query/skeletons.scss` | CSS-only loading skeletons. `@use`d from `src/blocks/query/style.scss`. |
| `tests/php/unit/blocks/query/test-facet-index.php` | PHPUnit coverage for `FacetIndex`: install, reindex-one, rebuild-facet, count queries. |
| `tests/php/unit/blocks/query/test-facet-registry.php` | PHPUnit coverage for `FacetRegistry`. |
| `tests/e2e/query-infinite-scroll.spec.js` | Playwright: inserts Dynamic Query + Pagination (infinite variation), verifies auto-advance and auto-pause. |
| `tests/e2e/query-facet-counts.spec.js` | Playwright: seeds 20 posts across 3 categories, verifies `(N)` counts and correct intersection behavior. |

### Modified files

| Path | Change |
|---|---|
| `designsetgo.php` | Bump `Version: 2.2.0`. Bump `DESIGNSETGO_VERSION` constant. Add install hook wiring for facet index on activation. |
| `package.json` | `"version": "2.2.0"`. Add webpack entry `src/admin/query-facet-dashboard/index.js → build/admin/query-facet-dashboard.js`. |
| `includes/class-plugin.php` | Instantiate `Blocks\Query\FacetIndex`, `Blocks\Query\FacetRegistry`, `Blocks\Query\FacetCLI` (under `WP_CLI` guard), `Admin\QueryFacetAdmin`. Add `upgrade_2_1_0_to_2_2_0()` calling `FacetIndex::install()` once. |
| `includes/blocks/class-query.php` | Register three new REST routes: `POST /facet-register`, `GET /facet-status`, `POST /facet-rebuild`, `POST /preview`. Share permission callback pattern. |
| `src/blocks/query/block.json` | No attribute changes here (preview is editor-only). |
| `src/blocks/query/edit.js` | Swap static placeholder/preview path for `<EditorPreviewList>` when `source !== 'manual'` and attributes are valid. |
| `src/blocks/query/render-helpers.php` | Route filter-option count requests through `FacetIndex::count_for_options()` (fall back to legacy live query when a facet key is not yet indexed — logged `_doing_it_wrong` once). |
| `src/blocks/query/view.js` | Add `infiniteObserver` action + sentinel-driven auto-call of `loadMore`. Track `ctx.autoLoadCount`; auto-pause at threshold. Respect `prefers-reduced-motion`. |
| `src/blocks/query-pagination/block.json` | Add `paginationKind` enum value `"infinite"`. Add `autoPauseAfter` (number, default 3), `sentinelOffsetPx` (number, default 200), `buttonLabelWhenPaused` (string). |
| `src/blocks/query-pagination/edit.js` | When `paginationKind === 'infinite'`, show `<InfiniteScrollControls>`. Render a sentinel + fallback button in the editor placeholder. |
| `src/blocks/query-pagination/render.php` | When `paginationKind === 'infinite'`, emit sentinel div + button, data-attributes for sentinel offset + auto-pause threshold. |
| `src/blocks/query-pagination/variations.js` | Add "Infinite Scroll" variation. Icon: scroll-vertical. |
| `src/blocks/query-filter/render.php` | Render `(N)` after option labels when `FacetIndex::is_available()` and `showCounts !== false`. |
| `src/blocks/query-filter/block.json` | Add `showCounts` boolean attribute (default `true`). |
| `src/blocks/query-filter/edit.js` | Inspector toggle for `showCounts`. |
| `src/blocks/query-filter/save.js` | POST to `/facet-register` on `onChange` of `paramName` / `taxonomy` / `metaKey` (via `useFacetRegistration`). |
| `src/styles/style.scss` | `@forward '../admin/query-facet-dashboard/style';` is **NOT** added (admin styles enqueue separately). Keep as-is. |
| `src/styles/editor.scss` | No changes. |
| `readme.txt` | Add `== Changelog ==` entry for 2.2.0. |
| `.claude/CLAUDE.md` | Append facet-index quick-reference under the "Query block family" section (once CLI commands + hooks are real). |

---

## Phase A — Facet Index Foundation

### Task A1: Install the facet index schema

**Files:**
- Create: `includes/blocks/class-query-facet-index.php`
- Modify: `includes/class-plugin.php` (constructor wiring + upgrade path)
- Test: `tests/php/unit/blocks/query/test-facet-index.php`

**Step 1: Write the failing schema test**

```php
namespace DesignSetGo\Tests\Blocks\Query;

use DesignSetGo\Blocks\Query\FacetIndex;
use WP_UnitTestCase;

class Test_Facet_Index extends WP_UnitTestCase {
	public function test_install_creates_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'dsgo_query_facet_index';

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		FacetIndex::install();

		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
		);
	}

	public function test_install_creates_indexes() {
		global $wpdb;
		$table = $wpdb->prefix . 'dsgo_query_facet_index';

		FacetIndex::install();
		$indexes = $wpdb->get_col( "SHOW INDEX FROM {$table}" );

		$this->assertContains( 'PRIMARY', $indexes );
		$this->assertContains( 'facet_key_value', $indexes );
		$this->assertContains( 'object_lookup', $indexes );
	}
}
```

**Step 2: Run test to verify it fails**

```bash
npm run test:php -- --filter Test_Facet_Index
```
Expected: FAIL — `Class DesignSetGo\Blocks\Query\FacetIndex not found`.

**Step 3: Implement `FacetIndex::install()`**

```php
<?php
namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

class FacetIndex {

	const SCHEMA_VERSION = '1';
	const OPTION_SCHEMA  = 'dsgo_query_facet_index_schema';
	const OPTION_STATUS  = 'dsgo_query_facet_index_status';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'dsgo_query_facet_index';
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			object_id BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(20) NOT NULL,
			facet_key VARCHAR(190) NOT NULL,
			facet_value VARCHAR(190) NOT NULL,
			indexed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY facet_key_value (facet_key, facet_value),
			KEY object_lookup (object_type, object_id)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, false );
	}
}
```

**Step 4: Run the test to verify it passes**

```bash
npm run test:php -- --filter Test_Facet_Index
```
Expected: PASS.

**Step 5: Wire install into the plugin bootstrap**

Modify `includes/class-plugin.php`:
- In `run()`, detect version change: `if ( get_option( 'designsetgo_version' ) !== DESIGNSETGO_VERSION ) { $this->maybe_upgrade(); }`.
- Add `maybe_upgrade()` calling `FacetIndex::install()` when moving from `< 2.2.0` to `>= 2.2.0`.
- Persist `update_option( 'designsetgo_version', DESIGNSETGO_VERSION )` after upgrade.

**Step 6: Commit**

```bash
git add includes/blocks/class-query-facet-index.php includes/class-plugin.php tests/php/unit/blocks/query/test-facet-index.php
git commit -m "feat(query): install dsgo_query_facet_index table on upgrade"
```

---

### Task A2: Index single-object reindex API

**Files:**
- Modify: `includes/blocks/class-query-facet-index.php`
- Test: `tests/php/unit/blocks/query/test-facet-index.php`

**Step 1: Write failing tests**

```php
public function test_reindex_post_writes_taxonomy_rows() {
	$cat_id = $this->factory->category->create( array( 'name' => 'News' ) );
	$post_id = $this->factory->post->create( array(
		'post_title'    => 'Hello',
		'post_category' => array( $cat_id ),
	) );

	// Auto-register the 'category' facet.
	FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

	FacetIndex::reindex_object( 'post', $post_id );

	global $wpdb;
	$table = FacetIndex::table_name();
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT facet_key, facet_value FROM {$table} WHERE object_id = %d AND object_type = 'post'",
			$post_id
		)
	);

	$values = wp_list_pluck( $rows, 'facet_value' );
	$this->assertContains( (string) $cat_id, $values );
}

public function test_reindex_is_idempotent() {
	// ... call reindex_object twice, assert row count doesn't double.
}
```

**Step 2: Run the tests to verify they fail**

```bash
npm run test:php -- --filter Test_Facet_Index::test_reindex_post_writes_taxonomy_rows
```
Expected: FAIL.

**Step 3: Implement `reindex_object()`**

```php
public static function reindex_object( string $object_type, int $object_id ): void {
	global $wpdb;
	$table = self::table_name();

	// Delete existing rows for this object (idempotency).
	$wpdb->delete( $table, array(
		'object_id'   => $object_id,
		'object_type' => $object_type,
	), array( '%d', '%s' ) );

	$facets = FacetRegistry::all();
	$rows   = array();

	foreach ( $facets as $key => $config ) {
		$values = self::resolve_facet_values( $object_type, $object_id, $key, $config );
		foreach ( $values as $value ) {
			$rows[] = array(
				'object_id'   => $object_id,
				'object_type' => $object_type,
				'facet_key'   => $key,
				'facet_value' => (string) $value,
			);
		}
	}

	if ( empty( $rows ) ) return;

	// Bulk insert.
	$placeholders = array();
	$values       = array();
	foreach ( $rows as $row ) {
		$placeholders[] = '(%d, %s, %s, %s)';
		array_push( $values, $row['object_id'], $row['object_type'], $row['facet_key'], $row['facet_value'] );
	}

	$sql = "INSERT INTO {$table} (object_id, object_type, facet_key, facet_value) VALUES "
		. implode( ',', $placeholders );

	$wpdb->query( $wpdb->prepare( $sql, $values ) );
}

private static function resolve_facet_values( string $object_type, int $object_id, string $key, array $config ): array {
	if ( 'post' !== $object_type ) return array(); // Phase A: posts only. Users/terms in v2.4.

	if ( 'taxonomy' === ( $config['type'] ?? '' ) ) {
		$term_ids = wp_get_post_terms( $object_id, $config['source'], array( 'fields' => 'ids' ) );
		return is_array( $term_ids ) ? $term_ids : array();
	}

	if ( 'meta' === ( $config['type'] ?? '' ) ) {
		$meta = get_post_meta( $object_id, $config['source'], false );
		return is_array( $meta ) ? array_filter( $meta, 'strlen' ) : array();
	}

	return array();
}
```

**Step 4: Run the tests to verify they pass**

```bash
npm run test:php -- --filter Test_Facet_Index
```
Expected: PASS.

**Step 5: Commit**

```bash
git add includes/blocks/class-query-facet-index.php tests/php/unit/blocks/query/test-facet-index.php
git commit -m "feat(query): add FacetIndex::reindex_object()"
```

---

### Task A3: Facet registry with persistence

**Files:**
- Create: `includes/blocks/class-query-facet-registry.php`
- Test: `tests/php/unit/blocks/query/test-facet-registry.php`

**Step 1: Write failing tests**

```php
public function test_register_persists_to_option() {
	FacetRegistry::register( 'category', array(
		'type'   => 'taxonomy',
		'source' => 'category',
		'label'  => 'Category',
	) );

	$stored = get_option( 'dsgo_query_facets', array() );
	$this->assertArrayHasKey( 'category', $stored );
	$this->assertSame( 'taxonomy', $stored['category']['type'] );
}

public function test_register_is_filterable() {
	add_filter( 'designsetgo_query_registered_facets', function ( $facets ) {
		$facets['price'] = array( 'type' => 'meta', 'source' => '_price' );
		return $facets;
	} );

	$this->assertArrayHasKey( 'price', FacetRegistry::all() );
}

public function test_unregister_removes_key() {
	FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
	FacetRegistry::unregister( 'category' );

	$this->assertArrayNotHasKey( 'category', get_option( 'dsgo_query_facets', array() ) );
}
```

**Step 2: Implement `FacetRegistry`**

```php
class FacetRegistry {
	const OPTION = 'dsgo_query_facets';

	public static function register( string $key, array $config ): void {
		$facets = get_option( self::OPTION, array() );
		$facets[ sanitize_key( $key ) ] = array(
			'type'   => sanitize_key( $config['type'] ?? '' ),
			'source' => sanitize_text_field( $config['source'] ?? '' ),
			'label'  => sanitize_text_field( $config['label'] ?? $key ),
		);
		update_option( self::OPTION, $facets, false );
	}

	public static function unregister( string $key ): void {
		$facets = get_option( self::OPTION, array() );
		unset( $facets[ sanitize_key( $key ) ] );
		update_option( self::OPTION, $facets, false );
	}

	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		return apply_filters( 'designsetgo_query_registered_facets', $stored );
	}

	public static function get( string $key ): ?array {
		$all = self::all();
		return $all[ $key ] ?? null;
	}
}
```

**Step 3: Run tests**

```bash
npm run test:php -- --filter Test_Facet_Registry
```
Expected: PASS.

**Step 4: Commit**

```bash
git add includes/blocks/class-query-facet-registry.php tests/php/unit/blocks/query/test-facet-registry.php
git commit -m "feat(query): add FacetRegistry for dynamic facet config"
```

---

### Task A4: Save hooks for incremental indexing

**Files:**
- Modify: `includes/blocks/class-query-facet-index.php` (add `::register_hooks()`)
- Modify: `includes/class-plugin.php` (call `FacetIndex::register_hooks()` in constructor)
- Test: `tests/php/unit/blocks/query/test-facet-index.php`

**Step 1: Write failing integration test**

```php
public function test_save_post_triggers_reindex() {
	FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
	FacetIndex::register_hooks();

	$cat_id  = $this->factory->category->create();
	$post_id = $this->factory->post->create( array( 'post_category' => array( $cat_id ) ) );

	// The save_post hook should have fired reindex_object(). Give taxonomy a beat to propagate.
	wp_set_post_categories( $post_id, array( $cat_id ) );

	global $wpdb;
	$count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . FacetIndex::table_name() . " WHERE object_id = %d",
		$post_id
	) );

	$this->assertGreaterThan( 0, $count );
}
```

**Step 2: Implement hook wiring**

```php
public static function register_hooks(): void {
	add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 2 );
	add_action( 'deleted_post', array( __CLASS__, 'on_deleted_post' ), 20, 1 );
	add_action( 'set_object_terms', array( __CLASS__, 'on_set_object_terms' ), 20, 4 );
	add_action( 'added_post_meta', array( __CLASS__, 'on_post_meta_changed' ), 20, 3 );
	add_action( 'updated_post_meta', array( __CLASS__, 'on_post_meta_changed' ), 20, 3 );
	add_action( 'deleted_post_meta', array( __CLASS__, 'on_post_meta_changed' ), 20, 3 );
}

public static function on_save_post( int $post_id, \WP_Post $post ): void {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
	if ( 'publish' !== $post->post_status ) {
		self::remove_object( 'post', $post_id );
		return;
	}
	self::reindex_object( 'post', $post_id );
}

public static function on_deleted_post( int $post_id ): void {
	self::remove_object( 'post', $post_id );
}

public static function on_set_object_terms( int $object_id, array $terms, array $tt_ids, string $taxonomy ): void {
	// Only trigger if the taxonomy is actually a registered facet source.
	foreach ( FacetRegistry::all() as $config ) {
		if ( 'taxonomy' === ( $config['type'] ?? '' ) && $taxonomy === ( $config['source'] ?? '' ) ) {
			self::reindex_object( 'post', $object_id );
			return;
		}
	}
}

public static function on_post_meta_changed( $meta_id, int $object_id, string $meta_key ): void {
	foreach ( FacetRegistry::all() as $config ) {
		if ( 'meta' === ( $config['type'] ?? '' ) && $meta_key === ( $config['source'] ?? '' ) ) {
			self::reindex_object( 'post', $object_id );
			return;
		}
	}
}

public static function remove_object( string $object_type, int $object_id ): void {
	global $wpdb;
	$wpdb->delete( self::table_name(), array(
		'object_id'   => $object_id,
		'object_type' => $object_type,
	), array( '%d', '%s' ) );
}
```

**Step 3: Run tests**

```bash
npm run test:php -- --filter Test_Facet_Index::test_save_post_triggers_reindex
```
Expected: PASS.

**Step 4: Commit**

```bash
git add includes/blocks/class-query-facet-index.php includes/class-plugin.php tests/php/unit/blocks/query/test-facet-index.php
git commit -m "feat(query): reindex facets on save_post / taxonomy / meta changes"
```

---

### Task A5: Count-for-options query

**Files:**
- Modify: `includes/blocks/class-query-facet-index.php` (add `::count_for_options()`)
- Test: `tests/php/unit/blocks/query/test-facet-index.php`

**Step 1: Write failing tests**

```php
public function test_count_for_options_returns_counts_per_value() {
	FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
	FacetIndex::register_hooks();

	$news   = $this->factory->category->create( array( 'slug' => 'news' ) );
	$events = $this->factory->category->create( array( 'slug' => 'events' ) );

	for ( $i = 0; $i < 3; $i++ ) {
		$this->factory->post->create( array( 'post_category' => array( $news ) ) );
	}
	for ( $i = 0; $i < 2; $i++ ) {
		$this->factory->post->create( array( 'post_category' => array( $events ) ) );
	}

	$counts = FacetIndex::count_for_options( 'category', array( $news, $events ), array() );
	$this->assertSame( 3, $counts[ $news ] );
	$this->assertSame( 2, $counts[ $events ] );
}

public function test_count_for_options_respects_active_filters() {
	// Intersection test: 3 posts in News, 2 of them also in 2026.
	// With active_filters = { 'year' => '2026' }, count for 'news' should be 2.
	// ... setup ...
	$counts = FacetIndex::count_for_options( 'category', array( $news ), array( 'year' => array( '2026' ) ) );
	$this->assertSame( 2, $counts[ $news ] );
}
```

**Step 2: Implement**

```php
public static function count_for_options( string $facet_key, array $option_values, array $active_filters ): array {
	global $wpdb;
	if ( empty( $option_values ) ) return array();

	$table = self::table_name();
	$key   = sanitize_key( $facet_key );

	// Build subquery that matches the active-filter intersection.
	$intersect_sql   = '';
	$intersect_where = '';

	if ( ! empty( $active_filters ) ) {
		// Ignore self-facet when computing counts — OR semantics inside a single facet group.
		unset( $active_filters[ $facet_key ] );

		foreach ( $active_filters as $f_key => $f_values ) {
			if ( empty( $f_values ) ) continue;
			$f_values    = array_map( 'strval', (array) $f_values );
			$placeholder = implode( ',', array_fill( 0, count( $f_values ), '%s' ) );
			$params      = array_merge( array( sanitize_key( $f_key ) ), $f_values );
			$intersect_sql .= $wpdb->prepare(
				" AND object_id IN (
					SELECT object_id FROM {$table}
					WHERE facet_key = %s AND facet_value IN ({$placeholder})
				)",
				$params
			);
		}
	}

	$value_placeholder = implode( ',', array_fill( 0, count( $option_values ), '%s' ) );
	$sql = "SELECT facet_value, COUNT(DISTINCT object_id) as cnt
			FROM {$table}
			WHERE facet_key = %s AND facet_value IN ({$value_placeholder})
			{$intersect_sql}
			GROUP BY facet_value";

	$params  = array_merge( array( $key ), array_map( 'strval', $option_values ) );
	$rows    = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	$counts  = array_fill_keys( array_map( 'strval', $option_values ), 0 );
	foreach ( $rows as $row ) $counts[ $row->facet_value ] = (int) $row->cnt;
	return $counts;
}

public static function is_available( string $facet_key ): bool {
	return null !== FacetRegistry::get( $facet_key );
}
```

**Step 3: Run tests**

```bash
npm run test:php -- --filter Test_Facet_Index::test_count
```
Expected: PASS.

**Step 4: Commit**

```bash
git add includes/blocks/class-query-facet-index.php tests/php/unit/blocks/query/test-facet-index.php
git commit -m "feat(query): add FacetIndex::count_for_options() for facet counts"
```

---

### Task A6: Full + targeted rebuild APIs

**Files:**
- Modify: `includes/blocks/class-query-facet-index.php` (add `::rebuild_all()`, `::rebuild_facet()`, `::status()`)
- Test: `tests/php/unit/blocks/query/test-facet-index.php`

**Step 1: Write failing tests**

```php
public function test_rebuild_all_populates_from_scratch() {
	FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

	$cat = $this->factory->category->create();
	$this->factory->post->create_many( 5, array( 'post_category' => array( $cat ) ) );

	global $wpdb;
	$wpdb->query( 'TRUNCATE ' . FacetIndex::table_name() );

	$result = FacetIndex::rebuild_all( array( 'batch_size' => 2 ) );

	$this->assertGreaterThanOrEqual( 5, (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT object_id) FROM ' . FacetIndex::table_name() ) );
	$this->assertSame( 'complete', $result['status'] );
}

public function test_status_returns_progress_state() {
	$status = FacetIndex::status();
	$this->assertArrayHasKey( 'last_rebuilt_at', $status );
	$this->assertArrayHasKey( 'total_rows', $status );
	$this->assertArrayHasKey( 'in_progress', $status );
}
```

**Step 2: Implement rebuild + status**

```php
public static function rebuild_all( array $args = array() ): array {
	global $wpdb;
	$batch = max( 50, (int) ( $args['batch_size'] ?? 200 ) );
	$start = microtime( true );

	update_option( self::OPTION_STATUS, array(
		'in_progress'     => true,
		'started_at'      => time(),
		'processed'       => 0,
		'total_rows'      => 0,
	), false );

	$wpdb->query( 'TRUNCATE ' . self::table_name() );

	$processed = 0;
	$offset    = 0;
	do {
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' ORDER BY ID ASC LIMIT %d OFFSET %d",
			$batch,
			$offset
		) );

		foreach ( $ids as $id ) {
			self::reindex_object( 'post', (int) $id );
			$processed++;
		}

		$offset += $batch;

		update_option( self::OPTION_STATUS, array(
			'in_progress' => count( $ids ) === $batch,
			'processed'   => $processed,
			'updated_at'  => time(),
		), false );

	} while ( count( $ids ) === $batch );

	$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );

	update_option( self::OPTION_STATUS, array(
		'in_progress'     => false,
		'last_rebuilt_at' => time(),
		'duration_ms'     => (int) ( ( microtime( true ) - $start ) * 1000 ),
		'processed'       => $processed,
		'total_rows'      => $total,
	), false );

	return array( 'status' => 'complete', 'processed' => $processed, 'total_rows' => $total );
}

public static function rebuild_facet( string $facet_key ): array {
	// Same shape, but DELETE only rows for this key, then re-scan all published posts.
	// ... mirrors rebuild_all with a narrower DELETE ...
}

public static function status(): array {
	global $wpdb;
	$status = get_option( self::OPTION_STATUS, array() );
	$status['total_rows']      = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	$status['last_rebuilt_at'] = $status['last_rebuilt_at'] ?? null;
	$status['in_progress']     = (bool) ( $status['in_progress'] ?? false );
	return $status;
}
```

**Step 3: Run tests**

```bash
npm run test:php -- --filter Test_Facet_Index
```
Expected: PASS.

**Step 4: Commit**

```bash
git add includes/blocks/class-query-facet-index.php tests/php/unit/blocks/query/test-facet-index.php
git commit -m "feat(query): add rebuild_all / rebuild_facet / status APIs"
```

---

### Task A7: WP-CLI commands

**Files:**
- Create: `includes/blocks/class-query-facet-cli.php`
- Modify: `includes/class-plugin.php` (conditionally load under `WP_CLI`)

**Step 1: Implement CLI**

```php
<?php
namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) return;

/**
 * Facet index management commands.
 */
class FacetCLI {

	public static function register(): void {
		\WP_CLI::add_command( 'dsgo query index', __CLASS__ );
	}

	/**
	 * Rebuild the full facet index.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<n>]
	 * : How many posts to process per batch. Default 200.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dsgo query index rebuild
	 *     wp dsgo query index rebuild --batch-size=500
	 */
	public function rebuild( $args, $assoc_args ): void {
		$batch = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 200 );
		$result = FacetIndex::rebuild_all( array( 'batch_size' => $batch ) );
		\WP_CLI::success( sprintf( 'Indexed %d objects (%d rows).', $result['processed'], $result['total_rows'] ) );
	}

	/**
	 * Rebuild a single facet.
	 *
	 * ## OPTIONS
	 *
	 * <facet_key>
	 * : The facet key (e.g. 'category', 'post_tag', 'meta:_price').
	 */
	public function rebuild_facet( $args ): void {
		$result = FacetIndex::rebuild_facet( $args[0] );
		\WP_CLI::success( sprintf( 'Rebuilt facet "%s" (%d rows).', $args[0], $result['total_rows'] ) );
	}

	/**
	 * Show current facet index status.
	 */
	public function status(): void {
		$status = FacetIndex::status();
		\WP_CLI\Utils\format_items( 'table', array( $status ), array_keys( $status ) );
	}

	/**
	 * Drop the facet index table.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation.
	 */
	public function drop( $args, $assoc_args ): void {
		\WP_CLI::confirm( 'This will drop the facet index table. Continue?', $assoc_args );
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . FacetIndex::table_name() );
		delete_option( FacetIndex::OPTION_SCHEMA );
		delete_option( FacetIndex::OPTION_STATUS );
		\WP_CLI::success( 'Facet index table dropped.' );
	}
}
```

**Step 2: Wire into plugin bootstrap**

In `includes/class-plugin.php`, around the existing service instantiation block:
```php
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/blocks/class-query-facet-cli.php';
	Blocks\Query\FacetCLI::register();
}
```

**Step 3: Manual verification**

```bash
npx wp-env run cli wp dsgo query index status
npx wp-env run cli wp dsgo query index rebuild --batch-size=100
```
Expected: `Success: Indexed N objects (M rows).`

**Step 4: Commit**

```bash
git add includes/blocks/class-query-facet-cli.php includes/class-plugin.php
git commit -m "feat(query): add wp dsgo query index CLI commands"
```

---

## Phase B — Filter Wiring + Auto-Registration

### Task B1: REST route for facet registration from editor

**Files:**
- Modify: `includes/blocks/class-query.php` (add route)
- Test: `tests/php/integration/blocks/query/test-facet-register-route.php`

**Step 1: Write failing route test**

```php
public function test_facet_register_route_stores_config() {
	wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );
	$nonce = wp_create_nonce( 'wp_rest' );

	$request = new \WP_REST_Request( 'POST', '/designsetgo/v1/query/facet-register' );
	$request->set_header( 'X-WP-Nonce', $nonce );
	$request->set_param( 'facet_key', 'category' );
	$request->set_param( 'config', array( 'type' => 'taxonomy', 'source' => 'category' ) );

	$response = rest_do_request( $request );
	$this->assertSame( 200, $response->get_status() );
	$this->assertArrayHasKey( 'category', get_option( 'dsgo_query_facets' ) );
}
```

**Step 2: Implement the route** in `class-query.php::register_routes()` alongside existing `/render` route. Permission: `current_user_can( 'edit_posts' )`.

**Step 3: Run test**

```bash
npm run test:php -- --filter Test_Facet_Register_Route
```
Expected: PASS.

**Step 4: Commit**

```bash
git add includes/blocks/class-query.php tests/php/integration/blocks/query/test-facet-register-route.php
git commit -m "feat(query): add /facet-register REST route"
```

---

### Task B2: `useFacetRegistration` hook + wiring into query-filter

**Files:**
- Create: `src/blocks/query/hooks/useFacetRegistration.js`
- Modify: `src/blocks/query-filter/edit.js`

**Step 1: Implement the hook**

```js
import { useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export function useFacetRegistration( { facetKey, config } ) {
	const lastSent = useRef( null );

	useEffect( () => {
		if ( ! facetKey || ! config?.type || ! config?.source ) return;
		const fingerprint = `${ facetKey }::${ config.type }::${ config.source }`;
		if ( fingerprint === lastSent.current ) return;
		lastSent.current = fingerprint;

		apiFetch( {
			path: '/designsetgo/v1/query/facet-register',
			method: 'POST',
			data: { facet_key: facetKey, config },
		} ).catch( () => {
			lastSent.current = null;
		} );
	}, [ facetKey, config?.type, config?.source ] );
}
```

**Step 2: Wire into filter edit.js**

In `src/blocks/query-filter/edit.js`, call:
```js
useFacetRegistration( {
	facetKey: attributes.paramName,
	config: {
		type: attributes.facetType || ( attributes.taxonomy ? 'taxonomy' : 'meta' ),
		source: attributes.taxonomy || attributes.metaKey,
	},
} );
```

**Step 3: Manual verification**

1. Start wp-env, open the editor.
2. Insert `designsetgo/query` + `designsetgo/query-filter` (checkbox variation, taxonomy = "category").
3. Check `wp option get dsgo_query_facets` — should contain `{ "category": { "type": "taxonomy", "source": "category" } }`.

**Step 4: Commit**

```bash
git add src/blocks/query/hooks/useFacetRegistration.js src/blocks/query-filter/edit.js
git commit -m "feat(query): auto-register facets on filter block save"
```

---

### Task B3: Surface counts in filter renders

**Files:**
- Modify: `src/blocks/query-filter/render.php`
- Modify: `src/blocks/query-filter/block.json`
- Modify: `src/blocks/query-filter/edit.js`
- Test: `tests/e2e/query-facet-counts.spec.js`

**Step 1: Add `showCounts` attribute** to `block.json` with default `true`.

**Step 2: Modify `render.php` option loop**

Around each option render, when `FacetIndex::is_available( $facet_key )` and `$attributes['showCounts']`:

```php
$option_values = array_map( fn( $t ) => (string) $t->term_id, $terms );
$counts = FacetIndex::count_for_options( $facet_key, $option_values, $active_filters );
// ...
foreach ( $terms as $term ) {
	$count = $counts[ (string) $term->term_id ] ?? 0;
	$label = $attributes['showCounts']
		? sprintf( '%s <span class="dsgo-query-filter__count">(%d)</span>', esc_html( $term->name ), $count )
		: esc_html( $term->name );
	// emit checkbox/option with $label
}
```

**Step 3: Add inspector toggle** in filter `edit.js`:

```js
<ToggleControl
	label={ __( 'Show counts next to options', 'designsetgo' ) }
	checked={ showCounts }
	onChange={ ( v ) => setAttributes( { showCounts: v } ) }
	__nextHasNoMarginBottom
/>
```

**Step 4: Write Playwright coverage**

```js
// tests/e2e/query-facet-counts.spec.js
test( 'facet counts reflect published posts', async ({ admin, editor, page }) => {
	await admin.createNewPost();
	// Insert query + filter (checkbox variation on category)
	// Seed via REST: 3 posts in News, 2 in Events
	// View post on frontend
	await expect( page.locator( '.dsgo-query-filter__count' ).first() ).toContainText( '(3)' );
} );
```

**Step 5: Run the tests**

```bash
npm run build
npm run test:e2e -- query-facet-counts.spec.js
```
Expected: PASS.

**Step 6: Commit**

```bash
git add src/blocks/query-filter tests/e2e/query-facet-counts.spec.js
git commit -m "feat(query-filter): render per-option counts from facet index"
```

---

### Task B4: Count-intersection recomputation on filter change

**Files:**
- Modify: `src/blocks/query/view.js` (IAPI refresh path)
- Modify: `src/blocks/query/render-helpers.php` (pass `active_filters` to count calls)

**Step 1: Pass active filter state into the render call**

In `designsetgo_query_render_region()` in `render-helpers.php`, extract `$active = self::extract_active_filters( $context )` and pass through to every `FacetIndex::count_for_options()` call.

**Step 2: Invalidate rendered counts on IAPI refresh**

In `view.js::dsgoQueryRefresh`, the existing code already replaces the list HTML from the REST response. Extend the replace to also swap the **filter** region's HTML when the response includes a `filters_html` field (server emits it; client swaps `[data-dsgo-filter-region]`).

**Step 3: Manual verification**

1. Load a frontend page with 3 posts in News, 2 in Events, 1 tagged "hot".
2. Check "hot" filter → News count should drop if "hot" posts only intersect some of them.

**Step 4: Commit**

```bash
git add src/blocks/query/view.js src/blocks/query/render-helpers.php
git commit -m "feat(query): recompute facet counts on filter change (intersection)"
```

---

### Task B5: Admin dashboard — rebuild UI + ad-hoc facet editor

**Files:**
- Create: `includes/admin/class-query-facet-admin.php`
- Create: `src/admin/query-facet-dashboard/index.js`
- Create: `src/admin/query-facet-dashboard/style.scss`
- Modify: `package.json` (webpack entry)
- Modify: `includes/class-plugin.php`

**Step 1: Add admin screen** at Settings → DesignSetGo → Dynamic Query via `add_submenu_page`.

**Step 2: Build the React dashboard** — a single-page app with:
- "Facet Index Status" card: last rebuilt, total rows, Rebuild button.
- "Registered Facets" table: list from `FacetRegistry::all()`, with source badge (auto / manual).
- "Add Facet" form: facet_key, type (taxonomy | meta), source (taxonomy slug or meta_key).

**Step 3: Add `/facet-status` and `/facet-rebuild` REST routes** in `class-query.php` (auth: `manage_options`). Rebuild runs async via `wp_schedule_single_event` + returns the status option immediately; dashboard polls `/facet-status` every 2s until `in_progress === false`.

**Step 4: Add webpack entry** in `package.json`:

```json
"wp-scripts": {
	"entry": {
		"admin/query-facet-dashboard": "src/admin/query-facet-dashboard/index.js"
	}
}
```

**Step 5: Manual verification**

1. `npm run build`.
2. Navigate to Settings → DesignSetGo → Dynamic Query.
3. Click "Rebuild Index". Progress bar should animate; final status shows `N rows`.
4. Add ad-hoc facet `meta:_price` with source `_price`. Confirm it appears in `wp option get dsgo_query_facets`.

**Step 6: Commit**

```bash
git add includes/admin src/admin/query-facet-dashboard includes/class-plugin.php package.json
git commit -m "feat(query): admin dashboard for facet index rebuild + facet registry"
```

---

## Phase C — Infinite Scroll

### Task C1: Add `paginationKind: 'infinite'` variation + attributes

**Files:**
- Modify: `src/blocks/query-pagination/block.json`
- Modify: `src/blocks/query-pagination/variations.js`

**Step 1:** Extend `paginationKind` enum in `block.json` with `"infinite"`. Add three new attributes:

```json
"autoPauseAfter":      { "type": "number", "default": 3 },
"sentinelOffsetPx":    { "type": "number", "default": 200 },
"buttonLabelWhenPaused": { "type": "string", "default": "Load more" }
```

**Step 2: Add variation**

```js
{
	name: 'infinite-scroll',
	title: __( 'Infinite Scroll', 'designsetgo' ),
	description: __( 'Loads the next page automatically when the user scrolls to the end.', 'designsetgo' ),
	icon: 'scroll',
	attributes: { paginationKind: 'infinite' },
}
```

**Step 3: Commit**

```bash
git add src/blocks/query-pagination/block.json src/blocks/query-pagination/variations.js
git commit -m "feat(query-pagination): add infinite-scroll variation + attrs"
```

---

### Task C2: Inspector controls for infinite scroll

**Files:**
- Create: `src/blocks/query-pagination/components/InfiniteScrollControls.js`
- Modify: `src/blocks/query-pagination/edit.js`

**Step 1: Build the controls component** — three `NumberControl`/`TextControl` pairs inside a `DsgoInspectorPanel` Settings item, only rendered when `paginationKind === 'infinite'`.

**Step 2: Commit**

```bash
git add src/blocks/query-pagination/components/InfiniteScrollControls.js src/blocks/query-pagination/edit.js
git commit -m "feat(query-pagination): inspector controls for infinite variant"
```

---

### Task C3: Server render for infinite scroll

**Files:**
- Modify: `src/blocks/query-pagination/render.php`

**Step 1:** When `paginationKind === 'infinite'`, emit:

```html
<div class="dsgo-query-pagination dsgo-query-pagination--infinite"
     data-dsgo-query-id="<?php echo esc_attr( $query_id ); ?>"
     data-dsgo-pagination="infinite"
     data-dsgo-auto-pause-after="<?php echo (int) $attrs['autoPauseAfter']; ?>"
     data-dsgo-sentinel-offset="<?php echo (int) $attrs['sentinelOffsetPx']; ?>"
     data-wp-interactive="designsetgo/query">

  <!-- Visible fallback button for keyboard users + auto-pause state. -->
  <button type="button"
          class="dsgo-query-pagination__loadmore"
          data-wp-on--click="actions.loadMore"
          data-dsgo-label-idle="<?php echo esc_attr( $attrs['buttonLabelWhenPaused'] ); ?>"
          data-dsgo-label-loading="<?php echo esc_attr__( 'Loading...', 'designsetgo' ); ?>"
          hidden>
    <?php echo esc_html( $attrs['buttonLabelWhenPaused'] ); ?>
  </button>

  <!-- Sentinel: IntersectionObserver target. -->
  <div class="dsgo-query-pagination__sentinel"
       aria-hidden="true"
       data-wp-init="callbacks.initInfiniteObserver">
  </div>
</div>
```

**Step 2: Commit**

```bash
git add src/blocks/query-pagination/render.php
git commit -m "feat(query-pagination): render infinite sentinel + fallback button"
```

---

### Task C4: IAPI observer + auto-pause

**Files:**
- Modify: `src/blocks/query/view.js`

**Step 1:** Add the init callback and auto-advance logic:

```js
store( 'designsetgo/query', {
	actions: {
		// ... existing actions ...
	},
	callbacks: {
		initInfiniteObserver() {
			const { ref } = getElement(); // sentinel
			const ctx = getContext();

			const prefersReduced = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			if ( prefersReduced ) {
				// Reveal button; user must click.
				const btn = ref.closest( '[data-dsgo-pagination="infinite"]' )?.querySelector( '.dsgo-query-pagination__loadmore' );
				if ( btn ) btn.hidden = false;
				return;
			}

			const wrapper = ref.closest( '[data-dsgo-pagination="infinite"]' );
			const offset = parseInt( wrapper.dataset.dsgoSentinelOffset || '200', 10 );
			const threshold = parseInt( wrapper.dataset.dsgoAutoPauseAfter || '3', 10 );

			ctx.autoLoadCount = ctx.autoLoadCount || 0;

			const observer = new IntersectionObserver( ( entries ) => {
				entries.forEach( ( entry ) => {
					if ( ! entry.isIntersecting ) return;
					if ( ctx.autoLoadCount >= threshold ) {
						// Reveal the button — user opts in to further auto-loads.
						const btn = wrapper.querySelector( '.dsgo-query-pagination__loadmore' );
						if ( btn ) btn.hidden = false;
						observer.disconnect();
						return;
					}
					ctx.autoLoadCount++;
					// Reuse the existing loadMore action.
					store( 'designsetgo/query' ).actions.loadMore.call( { ref: wrapper.querySelector( '.dsgo-query-pagination__loadmore' ) } );
				} );
			}, { rootMargin: `${ offset }px` } );

			observer.observe( ref );
		},
	},
} );
```

**Step 2: Write Playwright E2E**

```js
// tests/e2e/query-infinite-scroll.spec.js
test( 'infinite scroll auto-advances 3 times then reveals button', async ({ admin, editor, page }) => {
	await admin.createNewPost();
	// Insert query + pagination (infinite variation)
	// Seed 24 posts, perPage=4
	// View frontend, scroll to end
	await page.evaluate( () => window.scrollTo( 0, document.body.scrollHeight ) );
	await page.waitForSelector( '.dsgo-query-item:nth-child(8)' );
	await page.evaluate( () => window.scrollTo( 0, document.body.scrollHeight ) );
	await page.waitForSelector( '.dsgo-query-item:nth-child(16)' );
	await page.evaluate( () => window.scrollTo( 0, document.body.scrollHeight ) );
	// 4th scroll should NOT auto-load — button visible.
	await expect( page.locator( '.dsgo-query-pagination__loadmore' ) ).toBeVisible();
} );
```

**Step 3: Run the tests**

```bash
npm run build
npm run test:e2e -- query-infinite-scroll.spec.js
```

**Step 4: Commit**

```bash
git add src/blocks/query/view.js tests/e2e/query-infinite-scroll.spec.js
git commit -m "feat(query): IntersectionObserver-backed infinite scroll with auto-pause"
```

---

## Phase D — Editor Live Preview

### Task D1: `/preview` REST route

**Files:**
- Modify: `includes/blocks/class-query.php`
- Test: `tests/php/integration/blocks/query/test-preview-route.php`

**Step 1:** Add route `GET /designsetgo/v1/query/preview?attributes=...`. Runs the same `designsetgo_query_render()` path but returns a JSON payload of item contexts (not HTML) — specifically `{ items: [ { id, type, title, fields: {...} }, ... ], total }`. Permission: `edit_posts`.

**Step 2: Test** — verify it returns the expected structure for Posts / Users / Terms sources.

**Step 3: Commit**

```bash
git add includes/blocks/class-query.php tests/php/integration/blocks/query/test-preview-route.php
git commit -m "feat(query): add /preview REST route for editor live preview"
```

---

### Task D2: `<EditorPreviewList>` for Posts source

**Files:**
- Create: `src/blocks/query/components/EditorPreviewList.js`
- Modify: `src/blocks/query/edit.js`

**Step 1:** Use `@wordpress/core-data` `useEntityRecords( 'postType', postType, queryArgs )`:

```js
import { useEntityRecords } from '@wordpress/core-data';
import { BlockPreview, useInnerBlocksProps } from '@wordpress/block-editor';

export default function EditorPreviewList( { attributes, clientId, innerBlocks } ) {
	const queryArgs = useMemo( () => buildCoreDataQuery( attributes ), [ attributes ] );
	const { records, hasResolved } = useEntityRecords( 'postType', attributes.postType || 'post', queryArgs );

	if ( ! hasResolved ) return <Placeholder label={ __( 'Loading preview...', 'designsetgo' ) } />;
	if ( ! records?.length ) return <Placeholder label={ __( 'No results.', 'designsetgo' ) } />;

	return (
		<ul className="dsgo-query__items" data-preview="live">
			{ records.map( ( post, idx ) => (
				<PreviewItem key={ post.id } post={ post } isFirst={ idx === 0 } innerBlocks={ innerBlocks } />
			) ) }
		</ul>
	);
}

function PreviewItem( { post, isFirst, innerBlocks } ) {
	const context = { postId: post.id, postType: post.type };
	if ( isFirst ) {
		// Editable template.
		return (
			<li className="dsgo-query__item is-template-source">
				<InnerBlocksWithContext context={ context } />
			</li>
		);
	}
	return (
		<li className="dsgo-query__item is-read-only" aria-hidden="true">
			<BlockPreview blocks={ innerBlocks } viewportWidth={ 1000 } additionalStyles={ [] } />
		</li>
	);
}
```

Note: Pass `context` through `<BlockContextProvider value={ context }>` so Block Bindings resolve with the correct `postId`.

**Step 2: Swap the placeholder in `edit.js`**

Replace the current static placeholder call with `<EditorPreviewList ... />` when `source !== 'manual'`.

**Step 3: Manual verification**

1. Open editor, insert Dynamic Query on a page.
2. Confirm real post titles appear.
3. Edit the template (change heading color) — confirm items 2..N reflect the change after BlockPreview re-renders.

**Step 4: Commit**

```bash
git add src/blocks/query/components/EditorPreviewList.js src/blocks/query/edit.js
git commit -m "feat(query): editor live preview with editable first-item template"
```

---

### Task D3: Users + Terms preview via REST

**Files:**
- Modify: `src/blocks/query/components/EditorPreviewList.js`

**Step 1:** When `attributes.source === 'users'` or `'terms'`, use `useSelect` against `/designsetgo/v1/query/preview` (via `apiFetch` + a local cache) instead of `useEntityRecords`. Map the response into the same `records` shape.

**Step 2: Commit**

```bash
git add src/blocks/query/components/EditorPreviewList.js
git commit -m "feat(query): editor preview for users/terms sources"
```

---

### Task D4: CSS-only loading skeletons

**Files:**
- Create: `src/blocks/query/skeletons.scss`
- Modify: `src/blocks/query/style.scss` (add `@use 'skeletons';`)
- Modify: `src/blocks/query/view.js` (toggle `aria-busy` before/after refresh)

**Step 1:** Implement CSS using a pulsing gradient on `[aria-busy="true"] .dsgo-query-item` pseudo-elements. No DOM changes.

```scss
// src/blocks/query/skeletons.scss
@keyframes dsgo-query-skeleton-pulse {
	0%, 100% { opacity: 0.6; }
	50%      { opacity: 1; }
}

.wp-block-designsetgo-query[aria-busy="true"] {
	.dsgo-query-item {
		position: relative;
		min-height: 200px;
		&::after {
			content: '';
			position: absolute;
			inset: 0;
			background: linear-gradient(90deg, #f2f2f2, #e8e8e8, #f2f2f2);
			background-size: 200% 100%;
			animation: dsgo-query-skeleton-pulse 1.5s ease-in-out infinite;
			border-radius: inherit;
		}
	}
}
```

**Step 2:** Ensure `view.js` sets `aria-busy="true"` on the list container at the start of `dsgoQueryRefresh` and removes it on success/error (already does this — verify).

**Step 3: Manual verification**

Throttle network to "Slow 3G" in DevTools, click a filter — confirm skeleton shows during the request.

**Step 4: Commit**

```bash
git add src/blocks/query/skeletons.scss src/blocks/query/style.scss
git commit -m "feat(query): CSS-only loading skeletons on aria-busy state"
```

---

## Phase E — Polish, Docs, Release

### Task E1: Update docs + changelog

**Files:**
- Modify: `readme.txt` (Changelog 2.2.0 entry)
- Modify: `.claude/CLAUDE.md` (append facet + infinite-scroll notes under "Query block family")
- Create: `.claude/docs/QUERY-BLOCK-V2.2-GUIDE.md` — recipes: "Adding a custom facet", "Enabling infinite scroll", "Invalidating the index after a bulk import".

**Step 1: Commit**

```bash
git add readme.txt .claude/CLAUDE.md .claude/docs/QUERY-BLOCK-V2.2-GUIDE.md
git commit -m "docs: v2.2 facet index + infinite scroll guide"
```

---

### Task E2: Verification sweep

**Files:** (no edits — verification only)

**Step 1: Build + lint**

```bash
npm run build
npm run lint:js
npm run lint:css
npm run lint:php
```
Expected: zero errors.

**Step 2: Full test suite**

```bash
npm run test:unit
npm run test:php
npm run test:e2e
```
Expected: all pass. Any test flakes → open a follow-up, don't paper over.

**Step 3: Performance smoke**

Seed 5,000 posts via `wp post generate --count=5000`. Run `wp dsgo query index rebuild`. Measure: rebuild time < 60s, count query < 50ms.

```bash
npx wp-env run cli wp post generate --count=5000
time npx wp-env run cli wp dsgo query index rebuild
```

**Step 4: Manual UX smoke**

- Insert Dynamic Query + Pagination (infinite) + Filter (checkbox on category) on a page.
- Verify counts, infinite scroll auto-pause, filter-count intersection, editor live preview.
- Test in Twenty Twenty-Five and Twenty Twenty-Four to confirm no theme-specific regressions.

**Step 5: Commit version bump**

```bash
# package.json → "version": "2.2.0"
# designsetgo.php → "Version: 2.2.0" + DESIGNSETGO_VERSION = '2.2.0'
git add package.json designsetgo.php
git commit -m "chore: bump to 2.2.0"
```

---

### Task E3: Open PR

**Step 1:** Push branch and open PR against `main` with body summarizing the three headline features (facet index + counts, infinite scroll, editor live preview) and linking v1 PR #364 + this plan doc.

```bash
git push -u origin claude/query-v2.2-facets
gh pr create --title "feat(query): v2.2 — facets, infinite scroll, editor live preview" --body "$(cat <<'EOF'
## Summary

- Persistent facet index (`wp_dsgo_query_facet_index`) powering sub-ms per-option counts.
- Infinite scroll as `paginationKind: 'infinite'` with auto-pause + reduced-motion respect.
- Editor live preview: real posts, editable first-item template, read-only `BlockPreview` for rest.

## Test plan

- [ ] `npm run test:unit`, `test:php`, `test:e2e` all green.
- [ ] Rebuild 5k-post index in < 60s.
- [ ] Counts update on filter change (intersection behavior).
- [ ] Infinite scroll pauses after 3 auto-loads.
- [ ] Editor preview reflects real data for Posts / Users / Terms sources.

Follow-up: v2.3 (nested loops, relationships, group-by) — see `docs/plans/2026-04-19-dynamic-query-v2.2-facets.md` non-goals.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Execution Order Summary

Phase A (Tasks A1–A7): Facet index foundation. Ship-able on its own (no user-facing change yet but CLI works).
Phase B (Tasks B1–B5): Hook the index up to filter renders + admin UI. This is the FacetWP-parity moment.
Phase C (Tasks C1–C4): Infinite scroll. Independent of Phase A/B — can be parallelized if needed.
Phase D (Tasks D1–D4): Editor live preview + skeletons.
Phase E (Tasks E1–E3): Docs + verification + PR.

Phases A→B are sequential (B depends on A's APIs). Phases C, D are independent of A/B and of each other — if subagent-driven, dispatch C and D in parallel.

---

## Out of scope (v2.3+)

- **Nested loops / relationships / conditional visibility / group-by** → v2.3 "Rival Bricks".
- **JetEngine / Meta Box / Pods bindings + PHP escape-hatch + JSON export/import** → v2.4.
- **Headless REST / Query Monitor / multi-level AND-OR / hierarchical drilldown / dynamic CSS from meta** → v2.5+.

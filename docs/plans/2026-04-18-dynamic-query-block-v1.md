# Dynamic Query Block v1 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a native DesignSetGo Dynamic Query block family — a "blank canvas" container that iterates any `WP_Query`/`WP_User_Query`/`get_terms()` result with free `innerBlocks`, plus pagination, filter, and no-results sub-blocks — so users can build dynamic listings (blogs, portfolios, teams, related posts, etc.) without reaching for Elementor Loop Grid, Bricks Query Loop, or FacetWP.

**Architecture:** One container block (`designsetgo/query`) owns query state via attributes + Query ID; child blocks render once per item with post/user/term data resolved through the WP 6.5+ Block Bindings API. Sibling blocks (`designsetgo/query-pagination`, `designsetgo/query-filter`, `designsetgo/query-no-results`) bind to the container via Query ID and coordinate via a shared Interactivity API store. Server render in `render.php` handles the actual query; Block Bindings resolve dynamic text/image/link values through registered sources (`designsetgo/post-meta`, auto-detected `designsetgo/acf`). Load-more + filter/sort AJAX round-trips through one REST endpoint (`designsetgo/v1/query/render`) that re-executes `render.php` server-side for parity.

**Source brief:** [`docs/plans/claude-chat.md`](./claude-chat.md) — research synthesis from 6 parallel agents (Elementor Loop Grid, Bricks Query Loop, Oxygen Repeater, Etch Loop, core Query Loop, cross-platform user sentiment) plus confirmed design in the brainstorming transcript.

**Locked design decisions (from brainstorming):**
- Block slug: `designsetgo/query` (short, composes with sub-blocks).
- Anatomy: **Hybrid C** — blank canvas with a default inner template injected on insert (row → card → heading → image → excerpt).
- Scope: **Ambitious MVP** — Posts/Users/Terms/Manual/Current sources, meta_query + tax_query builders, filter sub-blocks (checkbox/select/search/sort/active/reset), numbered + load-more pagination, 6 variations, editor result-count preview.
- Dynamic data: Block Bindings only. Register native post-meta source; auto-detect ACF/SCF if installed. No proprietary token parser.
- Frontend stack: **Interactivity API** for load-more + filter state + URL sync (this is the plugin's first IAPI block — sets the precedent).
- Branch: dedicated worktree off `main`. This plan is written from `claude/theme-2-authoring-pr-bm8WN` but **must not be executed on that branch**. Spin a fresh worktree before Task 1 (see `superpowers:using-git-worktrees`).

**Tech Stack:**
- WordPress 6.5+ (Block Bindings API), WordPress 6.6+ recommended (Interactivity API `actions` API maturity).
- `@wordpress/scripts` build (`npm run build` / `npm run start`).
- `@wordpress/interactivity` for client state, routing, and AJAX hydration.
- `@wordpress/block-editor`, `@wordpress/components` (`ToolsPanel` via `<DsgoInspectorPanel>`), `@wordpress/core-data` (for the inspector's post-type/taxonomy pickers).
- Tests: Jest via `wp-scripts` (`npm run test:unit`), PHPUnit (`npm run test:php`), Playwright (`npm run test:e2e`).

**Non-goals for v1 (deferred to v2+):**
- Nested loops with parent-context tokens (Bricks 1.8 parity) — stub the context shape but don't ship nesting.
- JetEngine / Meta Box / Pods bindings — only native post-meta + ACF auto-detect land in v1.
- Visual meta-query tree beyond 1 level of AND/OR — 1-level only.
- Filter indexer / per-option count aggregation — v1 counts are live-queried (good enough for moderate catalogs; flag as known limit).
- PHP escape-hatch UI (reveal of generated args) — ship the `designsetgo_query_args` + `designsetgo/query/{id}/args` filter hooks but no inspector toggle yet.
- JSON export/import of query configs.
- Headless REST parity beyond the internal `designsetgo/v1/query/render` endpoint.
- No WooCommerce-specific block — shop/product listings use Posts source with `post_type=product`.

---

## File Structure

### New blocks (`src/blocks/`)

| Path | Responsibility |
|---|---|
| `src/blocks/query/block.json` | Metadata: container block, `providesContext`, `render: file:./render.php`, `viewScriptModule` for IAPI. |
| `src/blocks/query/index.js` | `registerBlockType` + `./editor.scss` + `./style.scss` + variations import. |
| `src/blocks/query/edit.js` | Inspector (Settings/Style/Advanced) + default inner-template logic. ≤300 lines; split inspector into `components/`. |
| `src/blocks/query/save.js` | Returns `null` — dynamic block. |
| `src/blocks/query/render.php` | Dispatches to source-specific renderer (`render-posts.php`, `render-users.php`, `render-terms.php`); delegates common HTML wrapping to `render-helpers.php`. |
| `src/blocks/query/render-posts.php` | Posts source: `WP_Query`, `setup_postdata()`, iterates innerBlocks per post with `render_block()`. |
| `src/blocks/query/render-users.php` | Users source: `WP_User_Query` iteration. |
| `src/blocks/query/render-terms.php` | Terms source: `get_terms()` iteration. |
| `src/blocks/query/render-helpers.php` | Shared wrapper, `<ul>/<li>` emission, aria-live hooks, aria-busy, empty-state fallback (renders `designsetgo/query-no-results` child if present). |
| `src/blocks/query/view.js` | Interactivity API store + directives (load-more, filter sync, URL state). Compiled as `viewScriptModule`. |
| `src/blocks/query/editor.scss` | Editor-only styles. |
| `src/blocks/query/style.scss` | Frontend styles (`@forward`ed into `src/styles/style.scss`). |
| `src/blocks/query/variations.js` | 6 variations (blog-index / team / testimonials / portfolio / related-posts / events). |
| `src/blocks/query/components/QuerySourcePanel.js` | Source + post-type + per-page + offset + orderby controls. |
| `src/blocks/query/components/TaxQueryBuilder.js` | Taxonomy multi-term picker + AND/OR relation. |
| `src/blocks/query/components/MetaQueryBuilder.js` | Meta clauses repeater (`{key, compare, value, type}`) + AND/OR relation. |
| `src/blocks/query/components/AdvancedPanel.js` | Search bind, author, exclude-current, ignore-sticky, manual IDs. |
| `src/blocks/query/components/ResultCountBadge.js` | Live "N posts match" badge shown in editor toolbar slot. |
| `src/blocks/query/hooks/useQueryPreview.js` | `@wordpress/core-data` `useSelect` wrapper that runs the same args via the REST endpoint for the editor preview + count. |
| `src/blocks/query-pagination/` | Pagination sibling block: `block.json`, `index.js`, `edit.js`, `save.js`, `render.php`, `view.js`, `editor.scss`, `style.scss`. |
| `src/blocks/query-filter/` | Filter sibling block: same file set + `variations.js` (checkbox / select / search / sort / active / reset). |
| `src/blocks/query-no-results/` | No-results sibling block: simple container, `innerBlocks` default a paragraph. |

### New PHP (`includes/`)

| Path | Responsibility |
|---|---|
| `includes/blocks/class-query.php` | Class `DesignSetGo\Blocks\Query\Controller`: REST endpoint `designsetgo/v1/query/render`, `render_callback`-style helper used by both `render.php` and the REST route (so AJAX load-more and first render produce identical HTML). |
| `includes/blocks/class-query-bindings.php` | Class `DesignSetGo\Blocks\Query\Bindings`: registers Block Bindings sources — `designsetgo/post-meta`, and conditionally `designsetgo/acf` when `function_exists('get_field')`. |

### Modified

| Path | Change |
|---|---|
| `includes/class-plugin.php:553-560` | Instantiate `new Blocks\Query\Controller()` and `new Blocks\Query\Bindings()` alongside existing `Blocks\Loader`, `Blocks\Form_Handler`, etc. |
| `src/styles/style.scss` | `@forward '../blocks/query/style'; @forward '../blocks/query-pagination/style'; @forward '../blocks/query-filter/style'; @forward '../blocks/query-no-results/style';` |
| `src/styles/editor.scss` | Mirror `@forward` lines for editor styles. |
| `.claude/CLAUDE.md` | Add a **Query block family** section under `## Architecture` documenting: Query ID binding, `providesContext`/`usesContext` shape, Interactivity API store name `designsetgo/query`, filter hook names. |

### Tests

| Path | Purpose |
|---|---|
| `tests/phpunit/blocks/query/test-render-posts.php` | Covers `render-posts.php` arg assembly for each source + tax_query/meta_query combo. |
| `tests/phpunit/blocks/query/test-render-users.php` | Users source args. |
| `tests/phpunit/blocks/query/test-render-terms.php` | Terms source args. |
| `tests/phpunit/blocks/query/test-bindings.php` | `designsetgo/post-meta` source resolves meta; ACF source only registers when ACF active. |
| `tests/phpunit/blocks/query/test-rest-render.php` | REST endpoint returns same HTML as `render.php` for equivalent args; validates nonce/permissions. |
| `tests/unit/blocks/query/edit.test.js` | Inspector reflects attributes; default inner template injects on insert of an empty block; TaxQueryBuilder / MetaQueryBuilder round-trip. |
| `tests/unit/blocks/query/variations.test.js` | Each variation has the expected attribute overrides + inner-blocks template. |
| `tests/e2e/query-block.spec.js` | Playwright: insert block → set source → configure tax_query → save post → verify frontend renders posts, pagination works, load-more appends items, filter toggles update results. |

---

## Phased delivery

| Phase | Tasks | Outcome |
|---|---|---|
| **Phase 0 — Worktree & branch** | T1 | Clean branch off `main`. |
| **Phase 1 — Server foundations** | T2–T4 | REST endpoint + Block Bindings sources. No UI yet. |
| **Phase 2 — Query container (skeleton)** | T5–T7 | Block registers; `render.php` runs Posts source and outputs raw markup. Editor shows placeholder. |
| **Phase 3 — Query container (full surface)** | T8–T12 | tax_query + meta_query + all sources + editor inspector + result-count badge + default inner template + context wiring. |
| **Phase 4 — Sibling blocks** | T13–T15 | Pagination (numbered + load-more), Filter (6 variations), No-results. |
| **Phase 5 — Variations + polish** | T16–T18 | 6 pattern variations, PHP filter hooks, a11y/SEO polish. |
| **Phase 6 — Verification** | T19–T20 | Full e2e pass, docs, changelog, `CLAUDE.md` update. |

---

## Task 1 — Worktree + branch setup

**Files:**
- Create: worktree at `../designsetgo-query-v1` on branch `claude/query-block-v1`.
- Modify: none (branch only).

- [ ] **Step 1.1: Confirm `main` is clean and up to date**

```bash
git fetch origin main
git log --oneline -5 origin/main
```

Expected: clean output, no uncommitted staged files that would taint the new branch.

- [ ] **Step 1.2: Create worktree per superpowers convention**

Use the `superpowers:using-git-worktrees` skill to pick a location and create the worktree. Suggested branch name: `claude/query-block-v1`. From this point forward, all `Bash` commands assume the worktree cwd.

- [ ] **Step 1.3: Install deps + baseline build**

```bash
npm ci
npm run build
```

Expected: `build/` populates, no errors.

- [ ] **Step 1.4: Baseline test runs (snapshot current green state)**

```bash
npm run test:unit -- --watchAll=false
npm run test:php
```

Expected: both suites pass on untouched `main`. If anything fails on baseline, resolve or document before starting — otherwise every later red test will be ambiguous.

- [ ] **Step 1.5: Commit plan file onto the new branch**

```bash
git add docs/plans/2026-04-18-dynamic-query-block-v1.md
git commit -m "docs(query): add v1 implementation plan"
```

---

## Task 2 — REST controller scaffold + render dispatcher

**Files:**
- Create: `includes/blocks/class-query.php`
- Create: `tests/phpunit/blocks/query/test-rest-render.php`
- Modify: `includes/class-plugin.php` (wire up instantiation)

This lands the server skeleton **before** the block itself so later render tests can assert parity between first-paint and REST-rendered HTML.

- [ ] **Step 2.1: Write the failing REST test**

Create `tests/phpunit/blocks/query/test-rest-render.php`:

```php
<?php
/**
 * @group query-block
 */
class DesignSetGo_Query_Rest_Test extends WP_UnitTestCase {

	public function test_route_registers() {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/designsetgo/v1/query/render', $routes );
	}

	public function test_requires_nonce_for_write_like_scope() {
		$request  = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
		$request->set_param( 'queryId', 'abc' );
		$request->set_param( 'attributes', array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 3 ) );
		$request->set_param( 'page', 2 );
		// No nonce.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_returns_html_shell_for_valid_request() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'queryId', 'abc' );
		$request->set_param( 'attributes', array(
			'source'   => 'posts',
			'postType' => 'post',
			'perPage'  => 3,
		) );
		$request->set_param( 'page', 1 );
		$request->set_param( 'innerBlocks', '' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'html', $data );
		$this->assertArrayHasKey( 'totalPages', $data );
		$this->assertArrayHasKey( 'totalItems', $data );
		$this->assertIsString( $data['html'] );
	}
}
```

- [ ] **Step 2.2: Run it to confirm failure**

```bash
npm run test:php -- --filter=DesignSetGo_Query_Rest_Test
```

Expected: FAIL (route not registered).

- [ ] **Step 2.3: Write the controller**

Create `includes/blocks/class-query.php`:

```php
<?php
/**
 * Dynamic Query Block — REST controller + shared render helper.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

class Controller {

	const REST_NAMESPACE = 'designsetgo/v1';
	const REST_ROUTE     = '/query/render';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_render' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'queryId'     => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
					'attributes'  => array( 'type' => 'object', 'required' => true ),
					'page'        => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
					'innerBlocks' => array( 'type' => 'string', 'default' => '' ),
					'params'      => array( 'type' => 'object', 'default' => array() ),
				),
			)
		);
	}

	public function check_permission( \WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Invalid nonce.', 'designsetgo' ), array( 'status' => 401 ) );
		}
		return true;
	}

	public function handle_render( \WP_REST_Request $request ) {
		$query_id    = $request->get_param( 'queryId' );
		$attributes  = (array) $request->get_param( 'attributes' );
		$page        = max( 1, (int) $request->get_param( 'page' ) );
		$inner_html  = (string) $request->get_param( 'innerBlocks' );
		$params      = (array) $request->get_param( 'params' );

		$result = self::render(
			$attributes,
			array(
				'query_id'    => $query_id,
				'page'        => $page,
				'inner_html'  => $inner_html,
				'params'      => $params,
			)
		);

		return rest_ensure_response( $result );
	}

	/**
	 * Shared render entrypoint used by both REST and first-paint render.php.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $context    Keys: query_id, page, inner_html (string serialized innerBlocks), params (filter state).
	 * @return array { html: string, totalPages: int, totalItems: int }
	 */
	public static function render( array $attributes, array $context ) {
		require_once __DIR__ . '/../../src/blocks/query/render-helpers.php'; // noop; real include path below.
		// The real file loads from build/blocks/query/render-helpers.php after build.
		if ( function_exists( 'designsetgo_query_render' ) ) {
			return designsetgo_query_render( $attributes, $context );
		}
		return array(
			'html'       => '',
			'totalPages' => 0,
			'totalItems' => 0,
		);
	}
}
```

> **Note:** Step 2.3's `require_once` uses a placeholder path. Task 6 establishes the real `build/blocks/query/render-helpers.php` + `designsetgo_query_render()` function — before Task 6 lands, the REST endpoint returns the empty shell and the Step 2.1 shell test still passes.

- [ ] **Step 2.4: Wire into the plugin bootstrap**

In `includes/class-plugin.php`, near line 553–560 (inside `init()`):

```php
$this->form_handler        = new Blocks\Form_Handler();
$this->form_submissions    = new Blocks\Form_Submissions();
$this->query_controller    = new Blocks\Query\Controller(); // NEW
```

Declare the property at the top of the class alongside `$form_handler`:

```php
/** @var Blocks\Query\Controller */
private $query_controller;
```

Autoload: confirm the project's autoloader picks up `DesignSetGo\Blocks\Query\Controller`. If it uses PSR-4 via Composer (`composer.json` > `autoload.psr-4`), the file at `includes/blocks/class-query.php` must match the mapping. Run `composer dump-autoload` after creating the file.

- [ ] **Step 2.5: Re-run test**

```bash
npm run test:php -- --filter=DesignSetGo_Query_Rest_Test
```

Expected: PASS all three cases.

- [ ] **Step 2.6: Commit**

```bash
git add includes/blocks/class-query.php includes/class-plugin.php tests/phpunit/blocks/query/test-rest-render.php composer.json composer.lock
git commit -m "feat(query): REST controller scaffold for dynamic query block"
```

---

## Task 3 — Block Bindings: `designsetgo/post-meta`

**Files:**
- Create: `includes/blocks/class-query-bindings.php`
- Create: `tests/phpunit/blocks/query/test-bindings.php`
- Modify: `includes/class-plugin.php` (instantiate)

- [ ] **Step 3.1: Write failing test**

Create `tests/phpunit/blocks/query/test-bindings.php`:

```php
<?php
/**
 * @group query-block
 */
class DesignSetGo_Query_Bindings_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		( new \DesignSetGo\Blocks\Query\Bindings() )->register();
	}

	public function test_post_meta_source_registered() {
		$sources = get_all_registered_block_bindings_sources();
		$this->assertArrayHasKey( 'designsetgo/post-meta', $sources );
	}

	public function test_post_meta_resolves_value_for_current_post() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'subtitle', 'Hello world' );

		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$source_callback = get_all_registered_block_bindings_sources()['designsetgo/post-meta']->get_value_callback;
		$value = call_user_func( $source_callback, array( 'key' => 'subtitle' ), null, 'content' );

		$this->assertSame( 'Hello world', $value );
	}

	public function test_acf_source_only_registered_when_acf_present() {
		$sources = get_all_registered_block_bindings_sources();
		if ( function_exists( 'get_field' ) ) {
			$this->assertArrayHasKey( 'designsetgo/acf', $sources );
		} else {
			$this->assertArrayNotHasKey( 'designsetgo/acf', $sources );
		}
	}
}
```

- [ ] **Step 3.2: Run, confirm failure**

```bash
npm run test:php -- --filter=DesignSetGo_Query_Bindings_Test
```

Expected: FAIL (class missing).

- [ ] **Step 3.3: Implement the bindings class**

Create `includes/blocks/class-query-bindings.php`:

```php
<?php
/**
 * Dynamic Query Block — Block Bindings sources.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

class Bindings {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 5 );
	}

	public function register() {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return; // WP < 6.5.
		}

		register_block_bindings_source(
			'designsetgo/post-meta',
			array(
				'label'              => __( 'Post meta (DesignSetGo)', 'designsetgo' ),
				'get_value_callback' => array( $this, 'get_post_meta_value' ),
				'uses_context'       => array( 'postId' ),
			)
		);

		if ( function_exists( 'get_field' ) ) {
			register_block_bindings_source(
				'designsetgo/acf',
				array(
					'label'              => __( 'ACF Field (DesignSetGo)', 'designsetgo' ),
					'get_value_callback' => array( $this, 'get_acf_value' ),
					'uses_context'       => array( 'postId' ),
				)
			);
		}
	}

	public function get_post_meta_value( array $args, $block = null, $attribute_name = 'content' ) {
		$key = isset( $args['key'] ) ? (string) $args['key'] : '';
		if ( '' === $key ) {
			return null;
		}

		$post_id = 0;
		if ( $block && isset( $block->context['postId'] ) ) {
			$post_id = (int) $block->context['postId'];
		}
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) {
			return null;
		}

		$value = get_post_meta( $post_id, $key, true );
		return '' === $value ? null : $value;
	}

	public function get_acf_value( array $args, $block = null, $attribute_name = 'content' ) {
		if ( ! function_exists( 'get_field' ) ) {
			return null;
		}
		$key = isset( $args['key'] ) ? (string) $args['key'] : '';
		if ( '' === $key ) {
			return null;
		}
		$post_id = 0;
		if ( $block && isset( $block->context['postId'] ) ) {
			$post_id = (int) $block->context['postId'];
		}
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		$value = get_field( $key, $post_id ?: false );
		if ( is_array( $value ) || is_object( $value ) ) {
			return null; // caller should use a more specific render path.
		}
		return '' === $value || null === $value ? null : (string) $value;
	}
}
```

- [ ] **Step 3.4: Wire into the plugin bootstrap**

In `includes/class-plugin.php`:

```php
$this->query_controller    = new Blocks\Query\Controller();
$this->query_bindings      = new Blocks\Query\Bindings(); // NEW
```

With matching `/** @var Blocks\Query\Bindings */ private $query_bindings;` declaration.

- [ ] **Step 3.5: Re-run test**

```bash
npm run test:php -- --filter=DesignSetGo_Query_Bindings_Test
```

Expected: PASS.

- [ ] **Step 3.6: Commit**

```bash
git add includes/blocks/class-query-bindings.php includes/class-plugin.php tests/phpunit/blocks/query/test-bindings.php
git commit -m "feat(query): register post-meta + ACF block bindings sources"
```

---

## Task 4 — Attribute schema + `block.json` for `designsetgo/query`

**Files:**
- Create: `src/blocks/query/block.json`
- Create: `src/blocks/query/index.js`
- Create: `src/blocks/query/save.js`
- Create: `src/blocks/query/edit.js` (minimal placeholder; full inspector comes in Task 8)
- Create: `src/blocks/query/editor.scss` (empty placeholder)
- Create: `src/blocks/query/style.scss` (empty placeholder)
- Create: `src/blocks/query/render.php` (dispatch stub; real implementation in Task 5)
- Modify: `src/styles/style.scss`, `src/styles/editor.scss` (`@forward`)

- [ ] **Step 4.1: `block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "designsetgo/query",
	"version": "1.0.0",
	"title": "Dynamic Query",
	"category": "design",
	"description": "Query any posts, users, or terms and render them with your own block design. Supports filters, pagination, and Block Bindings.",
	"keywords": ["query", "loop", "posts", "dynamic", "grid", "listing"],
	"textdomain": "designsetgo",
	"icon": "editor-table",
	"supports": {
		"anchor": true,
		"align": ["wide", "full"],
		"html": false,
		"inserter": true,
		"color": {
			"background": true,
			"text": true,
			"__experimentalDefaultControls": { "background": true, "text": true }
		},
		"spacing": {
			"margin": true,
			"padding": true,
			"blockGap": true,
			"__experimentalDefaultControls": { "blockGap": true }
		},
		"typography": {
			"fontSize": true,
			"lineHeight": true,
			"__experimentalDefaultControls": { "fontSize": true }
		}
	},
	"providesContext": {
		"designsetgo/queryId":    "queryId",
		"designsetgo/querySource": "source",
		"postId":                 "designsetgo/currentItemId",
		"postType":               "designsetgo/currentItemType"
	},
	"attributes": {
		"queryId":       { "type": "string", "default": "" },
		"source":        { "type": "string", "default": "posts", "enum": ["posts","users","terms","manual","current"] },
		"postType":      { "type": "string", "default": "post" },
		"perPage":       { "type": "number", "default": 6 },
		"offset":        { "type": "number", "default": 0 },
		"orderBy":       { "type": "string", "default": "date" },
		"orderByMetaKey":{ "type": "string", "default": "" },
		"order":         { "type": "string", "default": "DESC", "enum": ["ASC","DESC"] },
		"search":        { "type": "string", "default": "" },
		"bindSearchTo":  { "type": "string", "default": "" },
		"author":        { "type": "array",  "default": [] },
		"excludeCurrent":{ "type": "boolean","default": false },
		"ignoreSticky":  { "type": "boolean","default": true },
		"manualIds":     { "type": "array",  "default": [] },
		"taxQuery": {
			"type": "object",
			"default": { "relation": "AND", "clauses": [] }
		},
		"metaQuery": {
			"type": "object",
			"default": { "relation": "AND", "clauses": [] }
		},
		"tagName":       { "type": "string", "default": "ul" },
		"itemTagName":   { "type": "string", "default": "li" },
		"showPlaceholder": { "type": "boolean", "default": true }
	},
	"example": { "attributes": { "perPage": 3 } },
	"editorScript": "file:./index.js",
	"editorStyle":  "file:./index.css",
	"style":        "file:./style-index.css",
	"render":       "file:./render.php",
	"viewScriptModule": "file:./view.js"
}
```

> `designsetgo/currentItemId` and `designsetgo/currentItemType` are context keys the server-side renderer overrides per iteration (see Task 5, Step 5.4) so core blocks that `usesContext: ["postId","postType"]` (paragraph with bindings, post-title, etc.) pick up the iterated item automatically. The attribute defaults `{ "default": "" }` are irrelevant at render time; what matters is that the keys exist in `providesContext`.

- [ ] **Step 4.2: `index.js`**

```js
import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import save from './save';
import metadata from './block.json';
import variations from './variations';

import './editor.scss';
import './style.scss';

registerBlockType(metadata.name, {
	...metadata,
	variations,
	edit,
	save,
});
```

- [ ] **Step 4.3: `save.js`**

```js
export default function save() {
	return null; // Dynamic block — server-rendered.
}
```

- [ ] **Step 4.4: Minimal placeholder `edit.js`**

```js
import { __ } from '@wordpress/i18n';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

export default function QueryEdit() {
	const blockProps = useBlockProps({ className: 'dsgo-query' });
	return (
		<div {...blockProps}>
			<Placeholder
				icon="editor-table"
				label={__('Dynamic Query', 'designsetgo')}
				instructions={__('Inspector coming in Task 8.', 'designsetgo')}
			/>
			<InnerBlocks />
		</div>
	);
}
```

- [ ] **Step 4.5: Placeholder `variations.js`**

```js
// Full variations land in Task 16. Export empty array so block.json's 'variations' import resolves.
export default [];
```

- [ ] **Step 4.6: Placeholder `render.php`**

```php
<?php
/**
 * Dynamic Query — first render dispatcher.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

defined( 'ABSPATH' ) || exit;

// Implementation lands in Task 5. Render a visible placeholder so editors see the block in preview during Phase 2.
$wrapper = get_block_wrapper_attributes( array( 'class' => 'dsgo-query' ) );
echo '<div ' . $wrapper . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	. '<!-- designsetgo/query: render pending (Task 5) -->'
	. '</div>';
```

- [ ] **Step 4.7: Forward styles**

In `src/styles/style.scss`, add next to other block forwards:

```scss
@forward '../blocks/query/style';
```

In `src/styles/editor.scss`:

```scss
@forward '../blocks/query/editor';
```

- [ ] **Step 4.8: Build + verify registration**

```bash
npm run build
```

Expected: `build/blocks/query/block.json` exists. Start wp-env, open a new post, search the inserter for "Dynamic Query", confirm the block inserts and shows the placeholder.

```bash
npm run wp-env:start
```

- [ ] **Step 4.9: Commit**

```bash
git add src/blocks/query/ src/styles/style.scss src/styles/editor.scss
git commit -m "feat(query): scaffold designsetgo/query block + placeholder render"
```

---

## Task 5 — `render.php` for the Posts source

**Files:**
- Replace: `src/blocks/query/render.php` (with dispatcher)
- Create: `src/blocks/query/render-helpers.php` (declares `designsetgo_query_render()`)
- Create: `src/blocks/query/render-posts.php`
- Create: `tests/phpunit/blocks/query/test-render-posts.php`

This is the engine. Keep it small — each source file is only the query + the iteration; shared HTML shell lives in `render-helpers.php`.

- [ ] **Step 5.1: Write failing PHP test**

Create `tests/phpunit/blocks/query/test-render-posts.php`:

```php
<?php
/**
 * @group query-block
 */
class DesignSetGo_Query_Render_Posts_Test extends WP_UnitTestCase {

	public function test_renders_a_list_item_per_post() {
		$ids = self::factory()->post->create_many( 4, array( 'post_status' => 'publish' ) );

		$attributes = array(
			'source'      => 'posts',
			'postType'    => 'post',
			'perPage'     => 4,
			'tagName'     => 'ul',
			'itemTagName' => 'li',
		);

		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$result = designsetgo_query_render(
			$attributes,
			array(
				'query_id'   => 'test',
				'page'       => 1,
				'inner_html' => '<!-- wp:paragraph --><p>Item</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertSame( 4, substr_count( $result['html'], '<li' ) );
		$this->assertSame( 4, $result['totalItems'] );
	}

	public function test_respects_per_page_and_pagination() {
		self::factory()->post->create_many( 7, array( 'post_status' => 'publish' ) );

		$attributes = array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 3 );

		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$page1 = designsetgo_query_render( $attributes, array( 'query_id' => 't', 'page' => 1, 'inner_html' => '' ) );
		$page2 = designsetgo_query_render( $attributes, array( 'query_id' => 't', 'page' => 2, 'inner_html' => '' ) );
		$page3 = designsetgo_query_render( $attributes, array( 'query_id' => 't', 'page' => 3, 'inner_html' => '' ) );

		$this->assertSame( 7, $page1['totalItems'] );
		$this->assertSame( 3, $page1['totalPages'] );
		$this->assertSame( 3, substr_count( $page1['html'], '<li' ) );
		$this->assertSame( 3, substr_count( $page2['html'], '<li' ) );
		$this->assertSame( 1, substr_count( $page3['html'], '<li' ) );
	}

	public function test_taxonomy_filter_narrows_results() {
		$cat = self::factory()->category->create();
		$matched = self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		foreach ( $matched as $id ) {
			wp_set_post_categories( $id, array( $cat ) );
		}
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$attributes = array(
			'source' => 'posts',
			'postType' => 'post',
			'perPage' => 10,
			'taxQuery' => array(
				'relation' => 'AND',
				'clauses'  => array(
					array( 'taxonomy' => 'category', 'terms' => array( $cat ), 'operator' => 'IN' ),
				),
			),
		);

		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$result = designsetgo_query_render( $attributes, array( 'query_id' => 't', 'page' => 1, 'inner_html' => '' ) );

		$this->assertSame( 2, $result['totalItems'] );
	}

	public function test_meta_query_filters_results() {
		$m = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );
		update_post_meta( $m[0], 'featured', '1' );

		$attributes = array(
			'source' => 'posts',
			'postType' => 'post',
			'perPage' => 10,
			'metaQuery' => array(
				'relation' => 'AND',
				'clauses'  => array(
					array( 'key' => 'featured', 'compare' => '=', 'value' => '1', 'type' => 'CHAR' ),
				),
			),
		);

		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$result = designsetgo_query_render( $attributes, array( 'query_id' => 't', 'page' => 1, 'inner_html' => '' ) );

		$this->assertSame( 1, $result['totalItems'] );
	}
}
```

- [ ] **Step 5.2: Run, confirm failure**

```bash
npm run build && npm run test:php -- --filter=DesignSetGo_Query_Render_Posts_Test
```

Expected: FAIL with "file not found" or "function not defined".

- [ ] **Step 5.3: Implement `render-helpers.php`**

Create `src/blocks/query/render-helpers.php`:

```php
<?php
/**
 * Dynamic Query — render dispatcher + shared helpers.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_render' ) ) :

	/**
	 * Render a dynamic query block (shared between render.php and REST).
	 *
	 * @param array $attributes Block attributes.
	 * @param array $context    Keys: query_id (string), page (int), inner_html (string), params (array).
	 * @return array { html: string, totalPages: int, totalItems: int }
	 */
	function designsetgo_query_render( array $attributes, array $context ) {
		$defaults = array(
			'source'         => 'posts',
			'postType'       => 'post',
			'perPage'        => 6,
			'offset'         => 0,
			'orderBy'        => 'date',
			'orderByMetaKey' => '',
			'order'          => 'DESC',
			'search'         => '',
			'bindSearchTo'   => '',
			'author'         => array(),
			'excludeCurrent' => false,
			'ignoreSticky'   => true,
			'manualIds'      => array(),
			'taxQuery'       => array( 'relation' => 'AND', 'clauses' => array() ),
			'metaQuery'      => array( 'relation' => 'AND', 'clauses' => array() ),
			'tagName'        => 'ul',
			'itemTagName'    => 'li',
		);
		$attributes = wp_parse_args( $attributes, $defaults );

		$context = wp_parse_args( $context, array(
			'query_id'   => '',
			'page'       => 1,
			'inner_html' => '',
			'params'     => array(),
		) );

		$source = $attributes['source'];
		switch ( $source ) {
			case 'posts':
			case 'current':
			case 'manual':
				require_once __DIR__ . '/render-posts.php';
				return designsetgo_query_render_posts( $attributes, $context );
			case 'users':
				require_once __DIR__ . '/render-users.php';
				return designsetgo_query_render_users( $attributes, $context );
			case 'terms':
				require_once __DIR__ . '/render-terms.php';
				return designsetgo_query_render_terms( $attributes, $context );
			default:
				return array( 'html' => '', 'totalPages' => 0, 'totalItems' => 0 );
		}
	}

	/**
	 * Emit the block's list wrapper.
	 *
	 * @param string $inner   Accumulated <li>…</li> markup.
	 * @param array  $atts    Block attributes.
	 * @param array  $context Render context.
	 * @return string
	 */
	function designsetgo_query_wrap( $inner, array $atts, array $context ) {
		$tag = in_array( $atts['tagName'], array( 'ul', 'ol', 'div' ), true ) ? $atts['tagName'] : 'ul';
		$query_id = sanitize_key( (string) ( $context['query_id'] ?? '' ) );
		$source   = sanitize_key( (string) $atts['source'] );

		$wrapper = get_block_wrapper_attributes( array(
			'class'             => 'dsgo-query dsgo-query--source-' . $source,
			'data-dsgo-query-id'=> $query_id,
			'data-wp-interactive' => 'designsetgo/query',
			'data-wp-context'   => wp_json_encode( array(
				'queryId' => $query_id,
				'source'  => $source,
				'page'    => (int) $context['page'],
				'busy'    => false,
			) ),
			'aria-live'         => 'polite',
		) );

		return sprintf( '<%1$s %2$s>%3$s</%1$s>', $tag, $wrapper, $inner );
	}

	/**
	 * Render a single iterated item. Provides postId/postType context so bound
	 * core blocks (paragraph w/ binding, image, button) resolve per-item data.
	 *
	 * @param string $inner_html     Serialized innerBlocks HTML.
	 * @param array  $item_context   e.g. [ 'postId' => 12, 'postType' => 'post', 'designsetgo/currentItemId' => 12 ].
	 * @param string $item_tag       li / div.
	 * @return string
	 */
	function designsetgo_query_render_item( $inner_html, array $item_context, $item_tag ) {
		$tag = in_array( $item_tag, array( 'li', 'div', 'article' ), true ) ? $item_tag : 'li';

		// Parse and re-render innerBlocks with overridden context so bindings resolve.
		$parsed = parse_blocks( $inner_html );
		$html   = '';
		foreach ( $parsed as $parsed_block ) {
			if ( empty( $parsed_block['blockName'] ) ) {
				continue;
			}
			$html .= render_block( array_merge( $parsed_block, array(
				'context' => array_merge( (array) ( $parsed_block['context'] ?? array() ), $item_context ),
			) ) );
		}

		return sprintf( '<%1$s class="dsgo-query__item">%2$s</%1$s>', $tag, $html );
	}

endif;
```

- [ ] **Step 5.4: Implement `render-posts.php`**

Create `src/blocks/query/render-posts.php`:

```php
<?php
/**
 * Dynamic Query — Posts source renderer.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_render_posts' ) ) :

	function designsetgo_query_render_posts( array $atts, array $context ) {
		global $post;
		$saved_post = $post;

		$args = designsetgo_query_build_posts_args( $atts, $context );

		/**
		 * Filter the WP_Query args for a DesignSetGo Dynamic Query.
		 *
		 * @param array  $args       WP_Query args.
		 * @param array  $atts       Block attributes.
		 * @param array  $context    Render context.
		 */
		$args = apply_filters( 'designsetgo_query_args', $args, $atts, $context );

		if ( ! empty( $context['query_id'] ) ) {
			$args = apply_filters( 'designsetgo/query/' . $context['query_id'] . '/args', $args, $atts, $context );
		}

		$query = new WP_Query( $args );

		$items_html = '';
		while ( $query->have_posts() ) {
			$query->the_post();
			$items_html .= designsetgo_query_render_item(
				$context['inner_html'],
				array(
					'postId'                        => get_the_ID(),
					'postType'                      => get_post_type(),
					'designsetgo/currentItemId'     => get_the_ID(),
					'designsetgo/currentItemType'   => get_post_type(),
				),
				$atts['itemTagName']
			);
		}

		wp_reset_postdata();
		$post = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		return array(
			'html'       => designsetgo_query_wrap( $items_html, $atts, $context ),
			'totalPages' => (int) $query->max_num_pages,
			'totalItems' => (int) $query->found_posts,
		);
	}

	function designsetgo_query_build_posts_args( array $atts, array $context ) {
		$args = array(
			'post_type'      => 'manual' === $atts['source'] ? 'any' : sanitize_key( $atts['postType'] ),
			'posts_per_page' => max( 1, (int) $atts['perPage'] ),
			'offset'         => max( 0, (int) $atts['offset'] ) + ( ( max( 1, (int) $context['page'] ) - 1 ) * max( 1, (int) $atts['perPage'] ) ),
			'orderby'        => sanitize_key( $atts['orderBy'] ),
			'order'          => 'ASC' === strtoupper( $atts['order'] ) ? 'ASC' : 'DESC',
			'ignore_sticky_posts' => (bool) $atts['ignoreSticky'],
		);

		if ( 'meta_value' === $atts['orderBy'] || 'meta_value_num' === $atts['orderBy'] ) {
			$args['meta_key'] = sanitize_key( $atts['orderByMetaKey'] );
		}

		// Search — attribute-bound or URL-bound.
		$search = (string) $atts['search'];
		if ( ! empty( $atts['bindSearchTo'] ) && isset( $context['params'][ $atts['bindSearchTo'] ] ) ) {
			$search = (string) $context['params'][ $atts['bindSearchTo'] ];
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( ! empty( $atts['author'] ) ) {
			$args['author__in'] = array_map( 'absint', (array) $atts['author'] );
		}

		if ( ! empty( $atts['excludeCurrent'] ) && is_singular() ) {
			$args['post__not_in'] = array( get_the_ID() );
		}

		// Tax query.
		$tax = isset( $atts['taxQuery']['clauses'] ) ? (array) $atts['taxQuery']['clauses'] : array();
		if ( ! empty( $tax ) ) {
			$tax_query = array( 'relation' => 'AND' === ( $atts['taxQuery']['relation'] ?? 'AND' ) ? 'AND' : 'OR' );
			foreach ( $tax as $clause ) {
				if ( empty( $clause['taxonomy'] ) || empty( $clause['terms'] ) ) {
					continue;
				}
				$tax_query[] = array(
					'taxonomy' => sanitize_key( $clause['taxonomy'] ),
					'terms'    => array_map( 'absint', (array) $clause['terms'] ),
					'operator' => in_array( ( $clause['operator'] ?? 'IN' ), array( 'IN', 'NOT IN', 'AND' ), true ) ? $clause['operator'] : 'IN',
				);
			}
			if ( count( $tax_query ) > 1 ) {
				$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			}
		}

		// Meta query.
		$meta = isset( $atts['metaQuery']['clauses'] ) ? (array) $atts['metaQuery']['clauses'] : array();
		if ( ! empty( $meta ) ) {
			$meta_query = array( 'relation' => 'AND' === ( $atts['metaQuery']['relation'] ?? 'AND' ) ? 'AND' : 'OR' );
			foreach ( $meta as $clause ) {
				if ( empty( $clause['key'] ) ) {
					continue;
				}
				$meta_query[] = array(
					'key'     => sanitize_key( $clause['key'] ),
					'value'   => (string) ( $clause['value'] ?? '' ),
					'compare' => in_array( ( $clause['compare'] ?? '=' ), array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS' ), true ) ? $clause['compare'] : '=',
					'type'    => in_array( ( $clause['type'] ?? 'CHAR' ), array( 'CHAR', 'NUMERIC', 'DATE' ), true ) ? $clause['type'] : 'CHAR',
				);
			}
			if ( count( $meta_query ) > 1 ) {
				$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			}
		}

		// Manual source: override with specific post IDs.
		if ( 'manual' === $atts['source'] && ! empty( $atts['manualIds'] ) ) {
			$args['post__in']   = array_map( 'absint', (array) $atts['manualIds'] );
			$args['orderby']    = 'post__in';
			$args['post_type']  = 'any';
			unset( $args['posts_per_page'] ); // show all manually-picked.
			$args['posts_per_page'] = count( $args['post__in'] );
		}

		// Current-archive inheritance.
		if ( 'current' === $atts['source'] && isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query']->query_vars ) {
			$inherited = array_intersect_key(
				$GLOBALS['wp_query']->query_vars,
				array_flip( array( 'post_type', 'category_name', 'tag', 'author_name', 'year', 'monthnum', 's' ) )
			);
			$args = array_merge( $args, $inherited );
		}

		return $args;
	}

endif;
```

- [ ] **Step 5.5: Replace the placeholder `render.php`**

```php
<?php
/**
 * Dynamic Query — first-paint render.
 *
 * @package DesignSetGo
 * @since 2.1.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    innerBlocks (serialized template).
 * @param WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/render-helpers.php';

$context = array(
	'query_id'   => isset( $attributes['queryId'] ) ? sanitize_key( $attributes['queryId'] ) : '',
	'page'       => max( 1, (int) get_query_var( 'paged' ) ),
	'inner_html' => $content,
	'params'     => designsetgo_query_extract_params_from_request(),
);

$result = designsetgo_query_render( $attributes, $context );
echo $result['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- internally wrapped with get_block_wrapper_attributes().
```

Add `designsetgo_query_extract_params_from_request()` to `render-helpers.php`:

```php
if ( ! function_exists( 'designsetgo_query_extract_params_from_request' ) ) :
	function designsetgo_query_extract_params_from_request() {
		// Whitelisted GET params fed into filters/search.
		$allowed = apply_filters( 'designsetgo_query_url_params', array( 'q', 'sort', 'filter' ) );
		$params  = array();
		foreach ( $allowed as $key ) {
			$key = sanitize_key( $key );
			if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read.
				$params[ $key ] = is_array( $_GET[ $key ] )
					? array_map( 'sanitize_text_field', wp_unslash( (array) $_GET[ $key ] ) )
					: sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) );
			}
		}
		return $params;
	}
endif;
```

- [ ] **Step 5.6: Re-run the PHP test**

```bash
npm run build && npm run test:php -- --filter=DesignSetGo_Query_Render_Posts_Test
```

Expected: all 4 cases PASS.

- [ ] **Step 5.7: Commit**

```bash
git add src/blocks/query/render-helpers.php src/blocks/query/render-posts.php src/blocks/query/render.php tests/phpunit/blocks/query/test-render-posts.php
git commit -m "feat(query): posts source renderer + WP_Query args builder"
```

---

## Task 6 — Users + Terms source renderers

**Files:**
- Create: `src/blocks/query/render-users.php`
- Create: `src/blocks/query/render-terms.php`
- Create: `tests/phpunit/blocks/query/test-render-users.php`
- Create: `tests/phpunit/blocks/query/test-render-terms.php`
- Modify: `src/blocks/query/render-helpers.php` (add matching item-context shape for user/term iterations)

- [ ] **Step 6.1: Write the users test**

```php
<?php
/** @group query-block */
class DesignSetGo_Query_Render_Users_Test extends WP_UnitTestCase {

	public function test_renders_users() {
		self::factory()->user->create_many( 3, array( 'role' => 'author' ) );

		$atts = array( 'source' => 'users', 'perPage' => 10 );
		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';

		$result = designsetgo_query_render( $atts, array( 'query_id' => 't', 'page' => 1, 'inner_html' => '' ) );
		$this->assertGreaterThanOrEqual( 3, $result['totalItems'] );
	}
}
```

- [ ] **Step 6.2: Write the terms test**

```php
<?php
/** @group query-block */
class DesignSetGo_Query_Render_Terms_Test extends WP_UnitTestCase {

	public function test_renders_terms() {
		self::factory()->term->create_many( 3, array( 'taxonomy' => 'category' ) );

		$atts = array( 'source' => 'terms', 'taxQuery' => array( 'clauses' => array( array( 'taxonomy' => 'category' ) ) ), 'perPage' => 10 );
		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';

		$result = designsetgo_query_render( $atts, array( 'query_id' => 't', 'page' => 1, 'inner_html' => '' ) );
		$this->assertGreaterThanOrEqual( 3, $result['totalItems'] );
	}
}
```

- [ ] **Step 6.3: Run, confirm failure**

```bash
npm run test:php -- --filter='DesignSetGo_Query_Render_(Users|Terms)_Test'
```

- [ ] **Step 6.4: Implement `render-users.php`**

```php
<?php
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_render_users' ) ) :

	function designsetgo_query_render_users( array $atts, array $context ) {
		$per_page = max( 1, (int) $atts['perPage'] );
		$page     = max( 1, (int) $context['page'] );

		$query = new WP_User_Query( apply_filters( 'designsetgo_query_args', array(
			'number'  => $per_page,
			'offset'  => max( 0, (int) $atts['offset'] ) + ( ( $page - 1 ) * $per_page ),
			'orderby' => sanitize_key( $atts['orderBy'] ),
			'order'   => 'ASC' === strtoupper( $atts['order'] ) ? 'ASC' : 'DESC',
			'search'  => '' !== $atts['search'] ? '*' . $atts['search'] . '*' : '',
		), $atts, $context ) );

		$users       = (array) $query->get_results();
		$total_users = function_exists( 'count_users' ) ? (int) count_users()['total_users'] : count( $users );

		$items_html = '';
		foreach ( $users as $user ) {
			$items_html .= designsetgo_query_render_item(
				$context['inner_html'],
				array(
					'designsetgo/currentItemId'   => $user->ID,
					'designsetgo/currentItemType' => 'user',
				),
				$atts['itemTagName']
			);
		}

		return array(
			'html'       => designsetgo_query_wrap( $items_html, $atts, $context ),
			'totalPages' => (int) ceil( $total_users / $per_page ),
			'totalItems' => $total_users,
		);
	}

endif;
```

- [ ] **Step 6.5: Implement `render-terms.php`**

```php
<?php
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_render_terms' ) ) :

	function designsetgo_query_render_terms( array $atts, array $context ) {
		$per_page = max( 1, (int) $atts['perPage'] );
		$page     = max( 1, (int) $context['page'] );

		$taxonomies = array();
		foreach ( (array) ( $atts['taxQuery']['clauses'] ?? array() ) as $clause ) {
			if ( ! empty( $clause['taxonomy'] ) ) {
				$taxonomies[] = sanitize_key( $clause['taxonomy'] );
			}
		}
		if ( empty( $taxonomies ) ) {
			$taxonomies = array( 'category' );
		}

		$args = apply_filters( 'designsetgo_query_args', array(
			'taxonomy'   => $taxonomies,
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => max( 0, (int) $atts['offset'] ) + ( ( $page - 1 ) * $per_page ),
			'orderby'    => sanitize_key( $atts['orderBy'] === 'date' ? 'name' : $atts['orderBy'] ),
			'order'      => 'ASC' === strtoupper( $atts['order'] ) ? 'ASC' : 'DESC',
		), $atts, $context );

		$terms = get_terms( $args );
		$total = (int) wp_count_terms( array( 'taxonomy' => $taxonomies, 'hide_empty' => false ) );

		$items_html = '';
		foreach ( (array) $terms as $term ) {
			if ( is_wp_error( $term ) ) {
				continue;
			}
			$items_html .= designsetgo_query_render_item(
				$context['inner_html'],
				array(
					'designsetgo/currentItemId'    => $term->term_id,
					'designsetgo/currentItemType'  => 'term:' . $term->taxonomy,
				),
				$atts['itemTagName']
			);
		}

		return array(
			'html'       => designsetgo_query_wrap( $items_html, $atts, $context ),
			'totalPages' => (int) ceil( $total / $per_page ),
			'totalItems' => $total,
		);
	}

endif;
```

- [ ] **Step 6.6: Re-run, verify pass**

```bash
npm run build && npm run test:php -- --filter='DesignSetGo_Query_Render_(Users|Terms)_Test'
```

- [ ] **Step 6.7: Commit**

```bash
git add src/blocks/query/render-users.php src/blocks/query/render-terms.php tests/phpunit/blocks/query/test-render-users.php tests/phpunit/blocks/query/test-render-terms.php
git commit -m "feat(query): users + terms source renderers"
```

---

## Task 7 — Hook shared render helper into REST controller

**Files:**
- Modify: `includes/blocks/class-query.php` (replace the placeholder `require_once` in Task 2's `Controller::render()` with the real path)
- Modify: `tests/phpunit/blocks/query/test-rest-render.php` (add parity-with-render.php assertion)

- [ ] **Step 7.1: Point the controller at the real helper**

In `includes/blocks/class-query.php`, replace `Controller::render()` body:

```php
public static function render( array $attributes, array $context ) {
	$helpers = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
	if ( ! file_exists( $helpers ) ) {
		return array( 'html' => '', 'totalPages' => 0, 'totalItems' => 0 );
	}
	require_once $helpers;
	return designsetgo_query_render( $attributes, $context );
}
```

- [ ] **Step 7.2: Add parity test**

Append to `test-rest-render.php`:

```php
public function test_rest_output_matches_render_php() {
	$ids = self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
	wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

	$attributes = array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 5 );
	$inner      = '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->';

	require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
	$direct = designsetgo_query_render( $attributes, array( 'query_id' => 'x', 'page' => 1, 'inner_html' => $inner ) );

	$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$request->set_param( 'queryId', 'x' );
	$request->set_param( 'attributes', $attributes );
	$request->set_param( 'page', 1 );
	$request->set_param( 'innerBlocks', $inner );
	$response = rest_get_server()->dispatch( $request );

	$this->assertSame( 200, $response->get_status() );
	$this->assertSame( $direct['html'], $response->get_data()['html'] );
	$this->assertSame( $direct['totalItems'], $response->get_data()['totalItems'] );
}
```

- [ ] **Step 7.3: Run all query tests**

```bash
npm run build && npm run test:php -- --group=query-block
```

Expected: all pass.

- [ ] **Step 7.4: Commit**

```bash
git add includes/blocks/class-query.php tests/phpunit/blocks/query/test-rest-render.php
git commit -m "feat(query): REST + render.php share single render helper"
```

---

## Task 8 — Editor inspector: QuerySourcePanel (Settings panel)

**Files:**
- Modify: `src/blocks/query/edit.js`
- Create: `src/blocks/query/components/QuerySourcePanel.js`
- Create: `src/blocks/query/hooks/useQueryId.js` (wrapper around `useUniqueBlockId` from `src/hooks/`)
- Create: `tests/unit/blocks/query/edit.test.js`

- [ ] **Step 8.1: Write failing unit test**

```js
// tests/unit/blocks/query/edit.test.js
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import QueryEdit from '../../../../src/blocks/query/edit';

jest.mock('@wordpress/block-editor', () => {
	const actual = jest.requireActual('@wordpress/block-editor');
	return {
		...actual,
		useBlockProps: () => ({ className: 'wp-block-designsetgo-query' }),
		InspectorControls: ({ children }) => <div data-testid="inspector">{children}</div>,
		InnerBlocks: () => <div data-testid="inner-blocks" />,
	};
});

describe('QueryEdit — Settings panel', () => {
	it('renders a post type selector', () => {
		render(
			<QueryEdit
				attributes={{ source: 'posts', postType: 'post', perPage: 6, orderBy: 'date', order: 'DESC' }}
				setAttributes={jest.fn()}
				clientId="x"
				context={{}}
			/>
		);
		expect(screen.getByLabelText(/post type/i)).toBeInTheDocument();
	});

	it('renders the 5 source options', () => {
		render(
			<QueryEdit
				attributes={{ source: 'posts' }}
				setAttributes={jest.fn()}
				clientId="x"
				context={{}}
			/>
		);
		expect(screen.getByLabelText(/source/i)).toBeInTheDocument();
	});
});
```

- [ ] **Step 8.2: Run, confirm failure**

```bash
npm run test:unit -- --testPathPattern=query --watchAll=false
```

- [ ] **Step 8.3: Implement `useQueryId`**

```js
// src/blocks/query/hooks/useQueryId.js
import useUniqueBlockId from '../../../hooks/useUniqueBlockId';

export default function useQueryId({ clientId, queryId, setAttributes }) {
	useUniqueBlockId({
		clientId,
		attributeName: 'queryId',
		value: queryId,
		setAttributes,
		prefix: 'q',
		length: 8,
	});
}
```

- [ ] **Step 8.4: Implement `QuerySourcePanel.js`**

```js
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import {
	SelectControl,
	RangeControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import DsgoInspectorPanel from '../../../components/shared/DsgoInspectorPanel';

const SOURCES = [
	{ value: 'posts',   label: __('Posts', 'designsetgo') },
	{ value: 'users',   label: __('Users', 'designsetgo') },
	{ value: 'terms',   label: __('Terms', 'designsetgo') },
	{ value: 'manual',  label: __('Manual picks', 'designsetgo') },
	{ value: 'current', label: __('Current archive', 'designsetgo') },
];

const ORDER_BY = [
	{ value: 'date',           label: __('Date', 'designsetgo') },
	{ value: 'title',          label: __('Title', 'designsetgo') },
	{ value: 'menu_order',     label: __('Menu order', 'designsetgo') },
	{ value: 'rand',           label: __('Random', 'designsetgo') },
	{ value: 'comment_count',  label: __('Comment count', 'designsetgo') },
	{ value: 'meta_value',     label: __('Meta value (text)', 'designsetgo') },
	{ value: 'meta_value_num', label: __('Meta value (numeric)', 'designsetgo') },
];

export default function QuerySourcePanel({ attributes, setAttributes, clientId }) {
	const { source, postType, perPage, offset, orderBy, orderByMetaKey, order } = attributes;

	const postTypes = useSelect(
		(select) => select(coreStore).getPostTypes({ per_page: -1 }) || [],
		[]
	);
	const postTypeOptions = postTypes
		.filter((pt) => pt.viewable)
		.map((pt) => ({ label: pt.labels.singular_name, value: pt.slug }));

	return (
		<DsgoInspectorPanel
			title={__('Settings', 'designsetgo')}
			panelName="settings"
			panelId={clientId}
		>
			<DsgoInspectorPanel.Item
				label={__('Source', 'designsetgo')}
				hasValue={() => source !== 'posts'}
				onDeselect={() => setAttributes({ source: 'posts' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Source', 'designsetgo')}
					value={source}
					options={SOURCES}
					onChange={(v) => setAttributes({ source: v })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{source === 'posts' && (
				<DsgoInspectorPanel.Item
					label={__('Post type', 'designsetgo')}
					hasValue={() => postType !== 'post'}
					onDeselect={() => setAttributes({ postType: 'post' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Post type', 'designsetgo')}
						value={postType}
						options={postTypeOptions}
						onChange={(v) => setAttributes({ postType: v })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			<DsgoInspectorPanel.Item
				label={__('Items per page', 'designsetgo')}
				hasValue={() => perPage !== 6}
				onDeselect={() => setAttributes({ perPage: 6 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Items per page', 'designsetgo')}
					value={perPage}
					min={1}
					max={48}
					onChange={(v) => setAttributes({ perPage: v })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Offset', 'designsetgo')}
				hasValue={() => offset !== 0}
				onDeselect={() => setAttributes({ offset: 0 })}
			>
				<NumberControl
					label={__('Offset', 'designsetgo')}
					value={offset}
					min={0}
					onChange={(v) => setAttributes({ offset: Number(v) || 0 })}
					__next40pxDefaultSize
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Order by', 'designsetgo')}
				hasValue={() => orderBy !== 'date'}
				onDeselect={() => setAttributes({ orderBy: 'date' })}
			>
				<SelectControl
					label={__('Order by', 'designsetgo')}
					value={orderBy}
					options={ORDER_BY}
					onChange={(v) => setAttributes({ orderBy: v })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{['meta_value', 'meta_value_num'].includes(orderBy) && (
				<DsgoInspectorPanel.Item
					label={__('Order by meta key', 'designsetgo')}
					hasValue={() => orderByMetaKey !== ''}
					onDeselect={() => setAttributes({ orderByMetaKey: '' })}
					isShownByDefault
				>
					<NumberControl
						label={__('Meta key', 'designsetgo')}
						value={orderByMetaKey}
						onChange={(v) => setAttributes({ orderByMetaKey: String(v || '') })}
						__next40pxDefaultSize
					/>
				</DsgoInspectorPanel.Item>
			)}

			<DsgoInspectorPanel.Item
				label={__('Order direction', 'designsetgo')}
				hasValue={() => order !== 'DESC'}
				onDeselect={() => setAttributes({ order: 'DESC' })}
			>
				<SelectControl
					label={__('Order direction', 'designsetgo')}
					value={order}
					options={[
						{ value: 'DESC', label: __('Descending', 'designsetgo') },
						{ value: 'ASC',  label: __('Ascending', 'designsetgo') },
					]}
					onChange={(v) => setAttributes({ order: v })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</DsgoInspectorPanel>
	);
}
```

- [ ] **Step 8.5: Replace `edit.js` with wiring**

```js
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, InnerBlocks } from '@wordpress/block-editor';
import useQueryId from './hooks/useQueryId';
import QuerySourcePanel from './components/QuerySourcePanel';

const DEFAULT_TEMPLATE = [
	['designsetgo/row', {}, [
		['core/post-featured-image'],
		['core/post-title', { level: 3 }],
		['core/post-excerpt'],
	]],
];

export default function QueryEdit({ attributes, setAttributes, clientId }) {
	const blockProps = useBlockProps({ className: 'dsgo-query' });
	useQueryId({ clientId, queryId: attributes.queryId, setAttributes });

	return (
		<div {...blockProps}>
			<InspectorControls>
				<QuerySourcePanel
					attributes={attributes}
					setAttributes={setAttributes}
					clientId={clientId}
				/>
			</InspectorControls>
			<InnerBlocks template={DEFAULT_TEMPLATE} templateLock={false} />
		</div>
	);
}
```

- [ ] **Step 8.6: Build + run tests**

```bash
npm run build && npm run test:unit -- --testPathPattern=query --watchAll=false
```

Expected: all pass.

- [ ] **Step 8.7: Commit**

```bash
git add src/blocks/query/edit.js src/blocks/query/components/QuerySourcePanel.js src/blocks/query/hooks/useQueryId.js tests/unit/blocks/query/edit.test.js
git commit -m "feat(query): Settings panel w/ source + post-type + ordering"
```

---

## Task 9 — TaxQueryBuilder component

**Files:**
- Create: `src/blocks/query/components/TaxQueryBuilder.js`
- Modify: `src/blocks/query/edit.js` (mount it inside Settings)
- Modify: `tests/unit/blocks/query/edit.test.js` (assert builder renders when source=posts)

- [ ] **Step 9.1: Extend test**

Add to `edit.test.js`:

```js
it('renders tax query builder when source=posts and taxonomies available', () => {
	render(
		<QueryEdit
			attributes={{ source: 'posts', postType: 'post', taxQuery: { relation: 'AND', clauses: [] }, metaQuery: { relation: 'AND', clauses: [] } }}
			setAttributes={jest.fn()}
			clientId="x"
			context={{}}
		/>
	);
	expect(screen.getByText(/taxonomy filters/i)).toBeInTheDocument();
});
```

- [ ] **Step 9.2: Implement TaxQueryBuilder**

```js
// src/blocks/query/components/TaxQueryBuilder.js
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import {
	Button,
	SelectControl,
	FormTokenField,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';

export default function TaxQueryBuilder({ postType, taxQuery, setAttributes }) {
	const taxonomies = useSelect(
		(select) => select(coreStore).getTaxonomies({ per_page: -1 }) || [],
		[]
	);
	const relevant = taxonomies.filter((t) => t.types.includes(postType));

	const updateClause = (idx, patch) => {
		const next = [...taxQuery.clauses];
		next[idx] = { ...next[idx], ...patch };
		setAttributes({ taxQuery: { ...taxQuery, clauses: next } });
	};

	const removeClause = (idx) => {
		const next = taxQuery.clauses.filter((_, i) => i !== idx);
		setAttributes({ taxQuery: { ...taxQuery, clauses: next } });
	};

	const addClause = () => {
		if (!relevant[0]) return;
		setAttributes({
			taxQuery: {
				...taxQuery,
				clauses: [...taxQuery.clauses, { taxonomy: relevant[0].slug, terms: [], operator: 'IN' }],
			},
		});
	};

	return (
		<VStack spacing={3}>
			<strong>{__('Taxonomy filters', 'designsetgo')}</strong>

			{taxQuery.clauses.length > 1 && (
				<SelectControl
					label={__('Relation', 'designsetgo')}
					value={taxQuery.relation}
					options={[
						{ value: 'AND', label: __('AND (match all)', 'designsetgo') },
						{ value: 'OR',  label: __('OR (match any)', 'designsetgo') },
					]}
					onChange={(v) => setAttributes({ taxQuery: { ...taxQuery, relation: v } })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			)}

			{taxQuery.clauses.map((clause, idx) => (
				<VStack key={idx} spacing={2} className="dsgo-query-tax-clause">
					<SelectControl
						label={__('Taxonomy', 'designsetgo')}
						value={clause.taxonomy}
						options={relevant.map((t) => ({ label: t.labels.singular_name, value: t.slug }))}
						onChange={(v) => updateClause(idx, { taxonomy: v, terms: [] })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<TermPicker
						taxonomy={clause.taxonomy}
						selected={clause.terms}
						onChange={(ids) => updateClause(idx, { terms: ids })}
					/>
					<HStack>
						<SelectControl
							label={__('Operator', 'designsetgo')}
							value={clause.operator || 'IN'}
							options={[
								{ value: 'IN',     label: __('In', 'designsetgo') },
								{ value: 'NOT IN', label: __('Not in', 'designsetgo') },
								{ value: 'AND',    label: __('All of', 'designsetgo') },
							]}
							onChange={(v) => updateClause(idx, { operator: v })}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<Button isDestructive variant="tertiary" onClick={() => removeClause(idx)}>
							{__('Remove', 'designsetgo')}
						</Button>
					</HStack>
				</VStack>
			))}

			<Button variant="secondary" onClick={addClause} disabled={!relevant.length}>
				{__('Add taxonomy filter', 'designsetgo')}
			</Button>
		</VStack>
	);
}

function TermPicker({ taxonomy, selected, onChange }) {
	const terms = useSelect(
		(select) => select(coreStore).getEntityRecords('taxonomy', taxonomy, { per_page: -1 }) || [],
		[taxonomy]
	);
	const suggestions = terms.map((t) => t.name);
	const selectedNames = terms.filter((t) => selected.includes(t.id)).map((t) => t.name);

	return (
		<FormTokenField
			label={__('Terms', 'designsetgo')}
			value={selectedNames}
			suggestions={suggestions}
			onChange={(names) => {
				const ids = terms.filter((t) => names.includes(t.name)).map((t) => t.id);
				onChange(ids);
			}}
			__experimentalExpandOnFocus
			__nextHasNoMarginBottom
		/>
	);
}
```

- [ ] **Step 9.3: Mount inside `edit.js`'s Settings panel**

Update `edit.js` to render TaxQueryBuilder inside the Settings panel below the source controls (only when `source === 'posts'` or `source === 'terms'`). Wrap in a `<DsgoInspectorPanel.Item label={__('Taxonomy filters', 'designsetgo')} hasValue={() => attributes.taxQuery.clauses.length > 0} onDeselect={() => setAttributes({ taxQuery: { relation: 'AND', clauses: [] } })}>…</DsgoInspectorPanel.Item>`.

- [ ] **Step 9.4: Build + test**

```bash
npm run build && npm run test:unit -- --testPathPattern=query --watchAll=false
```

- [ ] **Step 9.5: Commit**

```bash
git add src/blocks/query/components/TaxQueryBuilder.js src/blocks/query/edit.js tests/unit/blocks/query/edit.test.js
git commit -m "feat(query): taxonomy query builder"
```

---

## Task 10 — MetaQueryBuilder component

**Files:**
- Create: `src/blocks/query/components/MetaQueryBuilder.js`
- Modify: `src/blocks/query/edit.js` (mount)
- Modify: `tests/unit/blocks/query/edit.test.js` (add coverage)

Mirrors TaxQueryBuilder shape. Attributes: `{ key, compare, value, type }` per clause. Compare options: `=`, `!=`, `>`, `>=`, `<`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `EXISTS`, `NOT EXISTS`. Type options: `CHAR`, `NUMERIC`, `DATE`.

- [ ] **Step 10.1: Write test**

```js
it('meta query builder adds and removes clauses', async () => {
	const setAttributes = jest.fn();
	render(
		<QueryEdit
			attributes={{ source: 'posts', postType: 'post', taxQuery: { relation: 'AND', clauses: [] }, metaQuery: { relation: 'AND', clauses: [] } }}
			setAttributes={setAttributes}
			clientId="x"
			context={{}}
		/>
	);
	expect(screen.getByText(/meta query/i)).toBeInTheDocument();
});
```

- [ ] **Step 10.2: Implement MetaQueryBuilder**

```js
import { __ } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	TextControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';

const COMPARE = ['=','!=','>','>=','<','<=','LIKE','NOT LIKE','IN','NOT IN','EXISTS','NOT EXISTS']
	.map((c) => ({ value: c, label: c }));

const TYPE = [
	{ value: 'CHAR',    label: __('Text', 'designsetgo') },
	{ value: 'NUMERIC', label: __('Numeric', 'designsetgo') },
	{ value: 'DATE',    label: __('Date', 'designsetgo') },
];

export default function MetaQueryBuilder({ metaQuery, setAttributes }) {
	const updateClause = (i, patch) => {
		const next = [...metaQuery.clauses];
		next[i] = { ...next[i], ...patch };
		setAttributes({ metaQuery: { ...metaQuery, clauses: next } });
	};
	const removeClause = (i) => {
		setAttributes({ metaQuery: { ...metaQuery, clauses: metaQuery.clauses.filter((_, idx) => idx !== i) } });
	};
	const addClause = () => {
		setAttributes({
			metaQuery: {
				...metaQuery,
				clauses: [...metaQuery.clauses, { key: '', compare: '=', value: '', type: 'CHAR' }],
			},
		});
	};

	return (
		<VStack spacing={3}>
			<strong>{__('Meta query', 'designsetgo')}</strong>
			{metaQuery.clauses.length > 1 && (
				<SelectControl
					label={__('Relation', 'designsetgo')}
					value={metaQuery.relation}
					options={[{ value: 'AND', label: 'AND' }, { value: 'OR', label: 'OR' }]}
					onChange={(v) => setAttributes({ metaQuery: { ...metaQuery, relation: v } })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			)}
			{metaQuery.clauses.map((c, i) => (
				<VStack key={i} spacing={2}>
					<TextControl label={__('Key', 'designsetgo')} value={c.key} onChange={(v) => updateClause(i, { key: v })} __next40pxDefaultSize __nextHasNoMarginBottom />
					<HStack>
						<SelectControl label={__('Compare', 'designsetgo')} value={c.compare} options={COMPARE} onChange={(v) => updateClause(i, { compare: v })} __next40pxDefaultSize __nextHasNoMarginBottom />
						<SelectControl label={__('Type', 'designsetgo')} value={c.type} options={TYPE} onChange={(v) => updateClause(i, { type: v })} __next40pxDefaultSize __nextHasNoMarginBottom />
					</HStack>
					{ ! ['EXISTS', 'NOT EXISTS'].includes(c.compare) && (
						<TextControl label={__('Value', 'designsetgo')} value={c.value} onChange={(v) => updateClause(i, { value: v })} __next40pxDefaultSize __nextHasNoMarginBottom />
					)}
					<Button isDestructive variant="tertiary" onClick={() => removeClause(i)}>{__('Remove', 'designsetgo')}</Button>
				</VStack>
			))}
			<Button variant="secondary" onClick={addClause}>{__('Add meta condition', 'designsetgo')}</Button>
		</VStack>
	);
}
```

- [ ] **Step 10.3: Mount in edit.js as another Settings panel item.**

- [ ] **Step 10.4: Build + test + commit**

```bash
npm run build && npm run test:unit -- --testPathPattern=query --watchAll=false
git add src/blocks/query/components/MetaQueryBuilder.js src/blocks/query/edit.js tests/unit/blocks/query/edit.test.js
git commit -m "feat(query): meta query builder (1-level AND/OR)"
```

---

## Task 11 — AdvancedPanel + result-count badge

**Files:**
- Create: `src/blocks/query/components/AdvancedPanel.js`
- Create: `src/blocks/query/components/ResultCountBadge.js`
- Create: `src/blocks/query/hooks/useQueryPreview.js`
- Modify: `src/blocks/query/edit.js`

- [ ] **Step 11.1: Implement `useQueryPreview`**

```js
// src/blocks/query/hooks/useQueryPreview.js
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

export default function useQueryPreview({ attributes, queryId }) {
	const [state, setState] = useState({ loading: false, totalItems: null, error: null });

	useEffect(() => {
		if (!queryId) return;
		let cancelled = false;
		setState((s) => ({ ...s, loading: true }));

		apiFetch({
			path: '/designsetgo/v1/query/render',
			method: 'POST',
			data: { queryId, attributes, page: 1, innerBlocks: '' },
		})
			.then((res) => !cancelled && setState({ loading: false, totalItems: res.totalItems, error: null }))
			.catch((err) => !cancelled && setState({ loading: false, totalItems: null, error: err }));

		return () => { cancelled = true; };
	}, [JSON.stringify(attributes), queryId]);

	return state;
}
```

- [ ] **Step 11.2: Implement `ResultCountBadge`**

```js
// src/blocks/query/components/ResultCountBadge.js
import { __, sprintf, _n } from '@wordpress/i18n';

export default function ResultCountBadge({ totalItems, loading }) {
	if (loading) return <span className="dsgo-query__count is-loading">…</span>;
	if (totalItems === null) return null;
	return (
		<span className="dsgo-query__count" aria-live="polite">
			{sprintf(
				/* translators: %d: number of matched items. */
				_n('%d match', '%d matches', totalItems, 'designsetgo'),
				totalItems
			)}
		</span>
	);
}
```

- [ ] **Step 11.3: Implement `AdvancedPanel`**

Inspector panel with: `search`, `bindSearchTo` (URL param name), `author` (FormTokenField), `excludeCurrent` toggle, `ignoreSticky` toggle, `manualIds` (serialized list only visible when `source==='manual'`), `tagName` selector (ul/ol/div), `itemTagName` (li/div).

Full code follows the same shape as `QuerySourcePanel.js`. Use `DsgoInspectorPanel.Item` for each control. Gate `manualIds` field behind `source === 'manual'`.

- [ ] **Step 11.4: Integrate into `edit.js`**

```js
// edit.js — relevant slice
const preview = useQueryPreview({ attributes, queryId: attributes.queryId });

return (
	<div {...blockProps}>
		<InspectorControls>
			<QuerySourcePanel ... />
			{attributes.source === 'posts' && (
				<DsgoInspectorPanel
					title={__('Filters', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
				>
					<TaxQueryBuilder ... />
					<MetaQueryBuilder ... />
				</DsgoInspectorPanel>
			)}
			<AdvancedPanel ... />
		</InspectorControls>
		<div className="dsgo-query__editor-header">
			<ResultCountBadge totalItems={preview.totalItems} loading={preview.loading} />
		</div>
		<InnerBlocks template={DEFAULT_TEMPLATE} templateLock={false} />
	</div>
);
```

- [ ] **Step 11.5: Editor SCSS**

In `editor.scss`:

```scss
.wp-block-designsetgo-query {
	&__editor-header {
		display: flex;
		justify-content: flex-end;
		padding: 4px 8px;
		border-bottom: 1px dashed rgba(0, 0, 0, 0.08);
		margin-bottom: 8px;
		font-size: 12px;
		color: rgba(0, 0, 0, 0.6);
	}
	&__count {
		&.is-loading { opacity: 0.5; }
	}
}
```

- [ ] **Step 11.6: Build + manually verify**

```bash
npm run build && npm run wp-env:start
```

Load editor, insert Dynamic Query, confirm "N matches" updates live as you tweak per-page / source / filters.

- [ ] **Step 11.7: Commit**

```bash
git add src/blocks/query/components/ src/blocks/query/hooks/ src/blocks/query/edit.js src/blocks/query/editor.scss
git commit -m "feat(query): advanced panel + live result-count preview"
```

---

## Task 12 — Default inner template + Style/Advanced panels wrap-up

**Files:**
- Modify: `src/blocks/query/edit.js`
- Create: `src/blocks/query/edit-template.js` (exports `DEFAULT_TEMPLATE`)

- [ ] **Step 12.1: Extract template**

Move `DEFAULT_TEMPLATE` from `edit.js` into `edit-template.js`:

```js
// src/blocks/query/edit-template.js
export const DEFAULT_TEMPLATE = [
	['designsetgo/row', { align: 'wide' }, [
		['core/image'], // Works with binding in variations later.
		['core/post-title', { level: 3, isLink: true }],
		['core/post-excerpt'],
	]],
];
```

- [ ] **Step 12.2: Confirm `InnerBlocks` receives the template only when empty**

Use `useSelect` to check the block's innerBlocks count; if empty, pass `template`; if populated, don't (so unlocking doesn't clobber user content on reload).

```js
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

const hasInner = useSelect(
	(select) => select(blockEditorStore).getBlock(clientId)?.innerBlocks?.length > 0,
	[clientId]
);

<InnerBlocks
	template={hasInner ? undefined : DEFAULT_TEMPLATE}
	templateLock={false}
/>
```

- [ ] **Step 12.3: Confirm context flows**

Build + open editor. Insert Dynamic Query. Confirm the default row appears, `post-title` shows the first published post's title (because the editor's own query provider is the current post; this is a known editor-preview quirk — frontend iterates correctly).

- [ ] **Step 12.4: Commit**

```bash
git add src/blocks/query/edit.js src/blocks/query/edit-template.js
git commit -m "feat(query): default inner template + lock-aware seeding"
```

---

## Task 13 — `designsetgo/query-pagination` block

**Files:**
- Create: `src/blocks/query-pagination/` (full file set).
- Create: `tests/phpunit/blocks/query/test-pagination-render.php`.
- Modify: `src/styles/style.scss`, `src/styles/editor.scss`.

### 13A — block.json + scaffold

- [ ] **Step 13.1: block.json**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "designsetgo/query-pagination",
	"version": "1.0.0",
	"title": "Query Pagination",
	"category": "design",
	"parent": ["designsetgo/query"],
	"description": "Pagination for the Dynamic Query block.",
	"keywords": ["pagination", "load more"],
	"textdomain": "designsetgo",
	"icon": "ellipsis",
	"supports": {
		"html": false,
		"align": false,
		"color": { "background": true, "text": true, "link": true },
		"typography": { "fontSize": true },
		"spacing": { "margin": true, "padding": true, "blockGap": true }
	},
	"usesContext": ["designsetgo/queryId"],
	"attributes": {
		"mode":        { "type": "string", "default": "numbered", "enum": ["numbered","loadmore"] },
		"labelLoadMore": { "type": "string", "default": "Load more" },
		"labelLoading":  { "type": "string", "default": "Loading…" },
		"showPrevNext":  { "type": "boolean", "default": true }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./index.css",
	"style":       "file:./style-index.css",
	"render":      "file:./render.php",
	"viewScriptModule": "file:./view.js"
}
```

- [ ] **Step 13.2: index.js / edit.js / save.js**

`save.js` returns `null`. `edit.js` shows Inspector with mode toggle + labels; preview renders either "← 1 2 3 →" (numbered) or a "Load more" button.

- [ ] **Step 13.3: render.php (numbered)**

```php
<?php
/**
 * Query Pagination — numbered.
 */

defined( 'ABSPATH' ) || exit;

$mode     = $attributes['mode'] ?? 'numbered';
$query_id = isset( $block->context['designsetgo/queryId'] ) ? sanitize_key( $block->context['designsetgo/queryId'] ) : '';

if ( ! $query_id ) {
	return;
}

// The parent's render.php sets a transient-less shared registry so pagination
// can inspect the most recent query-id render's totalPages.
$state = designsetgo_query_get_last_state( $query_id );
if ( ! $state || $state['totalPages'] < 2 ) {
	return;
}

$current = max( 1, (int) get_query_var( 'paged' ) );

$wrapper = get_block_wrapper_attributes( array(
	'class'             => 'dsgo-query-pagination dsgo-query-pagination--' . $mode,
	'data-wp-interactive' => 'designsetgo/query',
	'data-dsgo-query-id' => $query_id,
) );

if ( 'loadmore' === $mode ) {
	printf(
		'<div %1$s><button type="button" class="dsgo-query-pagination__loadmore" data-wp-on--click="actions.loadMore">%2$s</button></div>',
		$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_html( $attributes['labelLoadMore'] ?? __( 'Load more', 'designsetgo' ) )
	);
	return;
}

// Numbered.
$links = paginate_links( array(
	'total'     => $state['totalPages'],
	'current'   => $current,
	'type'      => 'array',
	'prev_next' => ! empty( $attributes['showPrevNext'] ),
) );
echo '<nav ' . $wrapper . ' aria-label="' . esc_attr__( 'Query pagination', 'designsetgo' ) . '">'; // phpcs:ignore
echo '<ul class="dsgo-query-pagination__list">';
foreach ( (array) $links as $link ) {
	echo '<li>' . $link . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() escapes.
}
echo '</ul></nav>';
```

- [ ] **Step 13.4: Add state registry to `render-helpers.php`**

```php
if ( ! function_exists( 'designsetgo_query_set_last_state' ) ) :
	function designsetgo_query_set_last_state( $query_id, array $state ) {
		static $cache = array();
		$cache[ $query_id ] = $state;
		$GLOBALS['designsetgo_query_states'] = $cache;
	}
	function designsetgo_query_get_last_state( $query_id ) {
		$cache = $GLOBALS['designsetgo_query_states'] ?? array();
		return $cache[ $query_id ] ?? null;
	}
endif;
```

Then inside `render-posts.php` (and users/terms variants), after computing the result, call:

```php
designsetgo_query_set_last_state( $context['query_id'] ?? '', array(
	'totalPages' => (int) $query->max_num_pages,
	'totalItems' => (int) $query->found_posts,
	'page'       => (int) $context['page'],
) );
```

### 13B — Load-more via Interactivity API

- [ ] **Step 13.5: `view.js` for query + query-pagination**

Create `src/blocks/query/view.js`:

```js
import { store, getContext, getElement } from '@wordpress/interactivity';

const { state, actions } = store('designsetgo/query', {
	state: {
		get isBusy() { return getContext().busy; },
	},
	actions: {
		*loadMore() {
			const ctx = getContext();
			if (ctx.busy) return;
			ctx.busy = true;

			const { ref } = getElement();
			const root = ref.closest('[data-dsgo-query-id]');
			const queryId = root?.dataset.dsgoQueryId;
			const attrsEl = root?.querySelector('script[type="application/json"][data-dsgo-attrs]');
			if (!queryId || !attrsEl) { ctx.busy = false; return; }
			const attributes = JSON.parse(attrsEl.textContent);
			const innerEl = root?.querySelector('script[type="application/json"][data-dsgo-inner]');
			const innerBlocks = innerEl ? JSON.parse(innerEl.textContent) : '';

			const nextPage = ctx.page + 1;

			try {
				const res = yield fetch('/wp-json/designsetgo/v1/query/render', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.wpApiSettings?.nonce || '',
					},
					body: JSON.stringify({ queryId, attributes, page: nextPage, innerBlocks }),
				});
				const data = yield res.json();
				const parser = new DOMParser();
				const doc = parser.parseFromString(data.html, 'text/html');
				const newItems = doc.querySelectorAll('.dsgo-query__item');
				const container = root.querySelector(':scope > ul, :scope > ol, :scope > div');
				newItems.forEach((el) => container.appendChild(el));

				ctx.page = nextPage;
				if (data.totalPages <= nextPage) {
					// Remove the load-more button (no more pages).
					const btn = root.closest('.wp-block')?.parentElement?.querySelector('.dsgo-query-pagination__loadmore');
					btn?.remove();
				}
			} finally {
				ctx.busy = false;
			}
		},
	},
});
```

- [ ] **Step 13.6: Emit the bootstrap scripts in `designsetgo_query_wrap`**

Update the wrapper to emit two `<script type="application/json">` blobs after the wrapper open tag:

```php
$json_attrs = '<script type="application/json" data-dsgo-attrs>' . wp_json_encode( $atts ) . '</script>';
$json_inner = '<script type="application/json" data-dsgo-inner>' . wp_json_encode( $context['inner_html'] ) . '</script>';
return sprintf( '<%1$s %2$s>%3$s%4$s%5$s</%1$s>', $tag, $wrapper, $json_attrs . $json_inner, $inner, '' );
```

- [ ] **Step 13.7: Pagination-render test**

```php
<?php
/** @group query-block */
class DesignSetGo_Query_Pagination_Test extends WP_UnitTestCase {

	public function test_numbered_pagination_emits_links() {
		self::factory()->post->create_many( 7, array( 'post_status' => 'publish' ) );

		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$result = designsetgo_query_render(
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 3 ),
			array( 'query_id' => 'p', 'page' => 1, 'inner_html' => '' )
		);
		$state  = designsetgo_query_get_last_state( 'p' );
		$this->assertSame( 3, $state['totalPages'] );
	}
}
```

- [ ] **Step 13.8: Build + test**

```bash
npm run build && npm run test:php -- --filter=DesignSetGo_Query_Pagination_Test
```

- [ ] **Step 13.9: Manual smoke test**

```bash
npm run wp-env:start
```

Open the editor, insert Dynamic Query → append Query Pagination. Publish. On the frontend, verify numbered links. Switch to load-more mode → verify clicking appends items without a page reload.

- [ ] **Step 13.10: Commit**

```bash
git add src/blocks/query-pagination/ src/blocks/query/view.js src/blocks/query/render-helpers.php tests/phpunit/blocks/query/test-pagination-render.php src/styles/style.scss src/styles/editor.scss
git commit -m "feat(query): pagination block — numbered + interactive load-more"
```

---

## Task 14 — `designsetgo/query-filter` block + 6 variations

**Files:**
- Create: `src/blocks/query-filter/` (full file set).
- Create: `src/blocks/query-filter/variations.js` — checkbox, select, search, sort, active, reset.
- Create: `tests/phpunit/blocks/query/test-filter-render.php`.
- Modify: `src/blocks/query/view.js` (add filter actions).
- Modify: `src/blocks/query/render-helpers.php` (`designsetgo_query_extract_params_from_request` honors all known filter params).

### 14A — Block scaffold

- [ ] **Step 14.1: block.json**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "designsetgo/query-filter",
	"version": "1.0.0",
	"title": "Query Filter",
	"category": "design",
	"parent": ["designsetgo/query"],
	"description": "Filter, search, or sort a Dynamic Query.",
	"keywords": ["filter", "facet", "search", "sort"],
	"textdomain": "designsetgo",
	"icon": "filter",
	"supports": { "html": false, "color": true, "spacing": { "margin": true, "padding": true } },
	"usesContext": ["designsetgo/queryId"],
	"attributes": {
		"filterKind": { "type": "string", "default": "checkbox", "enum": ["checkbox","select","search","sort","active","reset"] },
		"taxonomy":   { "type": "string", "default": "category" },
		"metaKey":    { "type": "string", "default": "" },
		"paramName":  { "type": "string", "default": "filter" },
		"label":      { "type": "string", "default": "" },
		"sortOptions": { "type": "array", "default": [
			{ "value": "date.DESC",  "label": "Newest" },
			{ "value": "date.ASC",   "label": "Oldest" },
			{ "value": "title.ASC",  "label": "A–Z" },
			{ "value": "title.DESC", "label": "Z–A" }
		] }
	},
	"editorScript": "file:./index.js",
	"editorStyle":  "file:./index.css",
	"style":        "file:./style-index.css",
	"render":       "file:./render.php"
}
```

- [ ] **Step 14.2: variations.js**

```js
import { __ } from '@wordpress/i18n';

export default [
	{ name: 'checkbox', title: __('Taxonomy checkboxes', 'designsetgo'), icon: 'list-view', attributes: { filterKind: 'checkbox', paramName: 'filter_cat' }, isDefault: true, scope: ['inserter', 'transform'] },
	{ name: 'select',   title: __('Taxonomy dropdown', 'designsetgo'),   icon: 'menu',     attributes: { filterKind: 'select',   paramName: 'filter_cat' } },
	{ name: 'search',   title: __('Search input', 'designsetgo'),        icon: 'search',   attributes: { filterKind: 'search',   paramName: 'q' } },
	{ name: 'sort',     title: __('Sort dropdown', 'designsetgo'),       icon: 'sort',     attributes: { filterKind: 'sort',     paramName: 'sort' } },
	{ name: 'active',   title: __('Active filters', 'designsetgo'),      icon: 'tag',      attributes: { filterKind: 'active',   paramName: '' } },
	{ name: 'reset',    title: __('Reset button', 'designsetgo'),        icon: 'undo',     attributes: { filterKind: 'reset',    paramName: '' } },
];
```

- [ ] **Step 14.3: render.php**

Branch on `filterKind`. Each emits a form control wired with `data-wp-on--change` / `data-wp-on--click` / `data-wp-on--submit` targeting the IAPI store. All controls share the same container attributes:

```php
$wrapper = get_block_wrapper_attributes( array(
	'class' => 'dsgo-query-filter dsgo-query-filter--' . $kind,
	'data-wp-interactive' => 'designsetgo/query',
	'data-dsgo-query-id'  => $query_id,
	'data-dsgo-param'     => $param_name,
) );
```

Key renderers:

- **checkbox** — iterate `get_terms($taxonomy)`, emit `<input type="checkbox" name="{$param_name}[]" value="{$slug}" data-wp-on--change="actions.toggleFilter">`.
- **select** — `<select data-wp-on--change="actions.setFilter">` with an `<option value="">All</option>` + term options.
- **search** — `<input type="search" data-wp-on--input="actions.setFilterDebounced" data-wp-on--keydown="actions.submitOnEnter">`.
- **sort** — `<select data-wp-on--change="actions.setSort">` mapping `sortOptions` to `orderBy.order` pairs.
- **active** — reads current `$_GET` filter params, renders removable chips linking to `?param=<without-this-value>` (no-JS fallback).
- **reset** — `<a href="<clean URL>">` that also calls `actions.resetAll` on click.

Encapsulate each in a helper function for testability.

- [ ] **Step 14.4: URL-param whitelist**

Update `designsetgo_query_extract_params_from_request` to include all filter param names. Because variations set `paramName` at insert-time, the render-side whitelist needs server-side registration. Simplest approach: allow any `_GET` param whose key starts with `filter_` or is one of `q`, `sort`, plus user-customizable via the `designsetgo_query_url_params` filter.

```php
function designsetgo_query_extract_params_from_request() {
	$allowed = apply_filters( 'designsetgo_query_url_params', array( 'q', 'sort' ) );
	$params  = array();
	foreach ( (array) $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = sanitize_key( $key );
		if ( in_array( $key, $allowed, true ) || 0 === strpos( $key, 'filter_' ) ) {
			$params[ $key ] = is_array( $value )
				? array_map( 'sanitize_text_field', wp_unslash( (array) $value ) )
				: sanitize_text_field( wp_unslash( (string) $value ) );
		}
	}
	return $params;
}
```

- [ ] **Step 14.5: Wire filter params into the args builder**

Extend `designsetgo_query_build_posts_args` to read `$context['params']`:

```php
// Keyword search → s.
if ( isset( $context['params']['q'] ) && '' !== $context['params']['q'] ) {
	$args['s'] = $context['params']['q'];
}

// Taxonomy filters (filter_<taxonomy> → taxonomy clause).
foreach ( $context['params'] as $key => $value ) {
	if ( 0 !== strpos( $key, 'filter_' ) ) continue;
	$taxonomy = substr( $key, strlen( 'filter_' ) );
	if ( ! taxonomy_exists( $taxonomy ) ) continue;
	$terms = is_array( $value ) ? $value : array_filter( explode( ',', (string) $value ) );
	if ( empty( $terms ) ) continue;
	$args['tax_query'][] = array(
		'taxonomy' => $taxonomy,
		'terms'    => array_map( 'sanitize_title', $terms ),
		'field'    => 'slug',
		'operator' => 'IN',
	);
	if ( ! isset( $args['tax_query']['relation'] ) ) {
		$args['tax_query']['relation'] = 'AND';
	}
}

// Sort override.
if ( ! empty( $context['params']['sort'] ) ) {
	list( $orderby, $dir ) = array_pad( explode( '.', $context['params']['sort'] ), 2, 'DESC' );
	$args['orderby'] = sanitize_key( $orderby );
	$args['order']   = 'ASC' === strtoupper( $dir ) ? 'ASC' : 'DESC';
}
```

- [ ] **Step 14.6: Extend IAPI store with filter actions**

Append to `src/blocks/query/view.js`:

```js
let debounceT;

Object.assign(actions, {
	*toggleFilter(event) {
		const ctx = getContext();
		const root = event.target.closest('[data-dsgo-query-id]');
		const paramName = event.target.closest('[data-dsgo-param]')?.dataset.dsgoParam || 'filter';
		const url = new URL(window.location.href);
		const values = url.searchParams.getAll(paramName + '[]');
		if (event.target.checked) {
			if (!values.includes(event.target.value)) url.searchParams.append(paramName + '[]', event.target.value);
		} else {
			url.searchParams.delete(paramName + '[]');
			values.filter((v) => v !== event.target.value).forEach((v) => url.searchParams.append(paramName + '[]', v));
		}
		url.searchParams.delete('paged');
		yield refresh(root, url);
	},
	*setFilter(event) {
		const ctx = getContext();
		const root = event.target.closest('[data-dsgo-query-id]');
		const paramName = event.target.closest('[data-dsgo-param]')?.dataset.dsgoParam || 'filter';
		const url = new URL(window.location.href);
		if (event.target.value) url.searchParams.set(paramName, event.target.value);
		else url.searchParams.delete(paramName);
		url.searchParams.delete('paged');
		yield refresh(root, url);
	},
	setFilterDebounced(event) {
		clearTimeout(debounceT);
		const ev = event;
		debounceT = setTimeout(() => actions.setFilter(ev), 250);
	},
	*resetAll(event) {
		event.preventDefault?.();
		const root = event.target.closest('[data-dsgo-query-id]');
		const url = new URL(window.location.href);
		[...url.searchParams.keys()].forEach((k) => {
			if (k === 'q' || k === 'sort' || k.startsWith('filter_')) url.searchParams.delete(k);
		});
		url.searchParams.delete('paged');
		yield refresh(root, url);
	},
});

function* refresh(root, url) {
	const ctx = getContext();
	ctx.busy = true;
	root.setAttribute('aria-busy', 'true');

	const queryId = root.dataset.dsgoQueryId;
	const attrsEl = root.querySelector('script[type="application/json"][data-dsgo-attrs]');
	const innerEl = root.querySelector('script[type="application/json"][data-dsgo-inner]');
	const attributes = attrsEl ? JSON.parse(attrsEl.textContent) : {};
	const innerBlocks = innerEl ? JSON.parse(innerEl.textContent) : '';
	const params = Object.fromEntries(url.searchParams.entries());

	try {
		const res = yield fetch('/wp-json/designsetgo/v1/query/render', {
			method: 'POST', credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.wpApiSettings?.nonce || '',
			},
			body: JSON.stringify({ queryId, attributes, page: 1, innerBlocks, params }),
		});
		const data = yield res.json();
		// Replace list content only.
		const doc = new DOMParser().parseFromString(data.html, 'text/html');
		const newList = doc.querySelector('.dsgo-query ul, .dsgo-query ol, .dsgo-query > div');
		const oldList = root.querySelector(':scope > ul, :scope > ol, :scope > div:not([data-dsgo-attrs]):not([data-dsgo-inner])');
		if (newList && oldList) oldList.replaceWith(newList);

		// Announce update to AT.
		root.setAttribute('aria-busy', 'false');
		// Move focus to list start (for keyboard users).
		oldList?.querySelector('a, button, input')?.focus?.();

		// Sync URL.
		window.history.replaceState({}, '', url.toString());
		ctx.page = 1;
	} finally {
		ctx.busy = false;
	}
}
```

- [ ] **Step 14.7: No-JS fallback**

For filter blocks, wrap controls in a `<form method="get" action="">` so submission without JS still filters via full page reload. IAPI actions call `event.preventDefault()` only after successful fetch initiation.

- [ ] **Step 14.8: Filter render test**

```php
<?php
/** @group query-block */
class DesignSetGo_Query_Filter_Test extends WP_UnitTestCase {

	public function test_checkbox_filter_narrows_results() {
		$cat = self::factory()->category->create( array( 'slug' => 'news' ) );
		$m   = self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		foreach ( $m as $id ) wp_set_post_categories( $id, array( $cat ) );
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$result = designsetgo_query_render(
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 10 ),
			array( 'query_id' => 'f', 'page' => 1, 'inner_html' => '', 'params' => array( 'filter_category' => array( 'news' ) ) )
		);
		$this->assertSame( 2, $result['totalItems'] );
	}
}
```

- [ ] **Step 14.9: Build + test + commit**

```bash
npm run build && npm run test:php -- --filter=DesignSetGo_Query_Filter_Test
git add src/blocks/query-filter/ src/blocks/query/render-helpers.php src/blocks/query/render-posts.php src/blocks/query/view.js tests/phpunit/blocks/query/test-filter-render.php src/styles/
git commit -m "feat(query): filter sibling block (checkbox/select/search/sort/active/reset)"
```

---

## Task 15 — `designsetgo/query-no-results` block

**Files:**
- Create: `src/blocks/query-no-results/` (full file set).
- Modify: `src/blocks/query/render-helpers.php` (fallback rendering when `totalItems === 0`).

- [ ] **Step 15.1: block.json**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "designsetgo/query-no-results",
	"version": "1.0.0",
	"title": "No Results",
	"category": "design",
	"parent": ["designsetgo/query"],
	"description": "Content shown when the Dynamic Query returns no items.",
	"textdomain": "designsetgo",
	"icon": "no",
	"supports": { "html": false, "color": true, "spacing": { "margin": true, "padding": true } },
	"usesContext": ["designsetgo/queryId"],
	"attributes": {},
	"editorScript": "file:./index.js",
	"style":       "file:./style-index.css",
	"render":      "file:./render.php"
}
```

- [ ] **Step 15.2: edit.js**

```js
import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const TEMPLATE = [['core/paragraph', { placeholder: __('Nothing found. Try another search.', 'designsetgo') }]];

export default function NoResultsEdit() {
	const blockProps = useBlockProps({ className: 'dsgo-query-no-results' });
	const innerBlocksProps = useInnerBlocksProps(blockProps, { template: TEMPLATE, templateLock: false });
	return <div {...innerBlocksProps} />;
}
```

- [ ] **Step 15.3: render.php**

```php
<?php
defined( 'ABSPATH' ) || exit;

$query_id = isset( $block->context['designsetgo/queryId'] ) ? sanitize_key( $block->context['designsetgo/queryId'] ) : '';
if ( ! $query_id ) return;

$state = designsetgo_query_get_last_state( $query_id );
if ( ! $state || $state['totalItems'] > 0 ) return; // only show when parent's last render had 0 items.

$wrapper = get_block_wrapper_attributes( array( 'class' => 'dsgo-query-no-results' ) );
echo '<div ' . $wrapper . '>' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
```

- [ ] **Step 15.4: Build + test manually**

```bash
npm run build && npm run wp-env:start
```

Insert Dynamic Query → append Query No Results → set search to something unmatched → publish → verify the fallback shows on the frontend.

- [ ] **Step 15.5: Commit**

```bash
git add src/blocks/query-no-results/ src/styles/
git commit -m "feat(query): no-results sibling block"
```

---

## Task 16 — 6 block variations (blog / team / testimonials / portfolio / related-posts / events)

**Files:**
- Modify: `src/blocks/query/variations.js` (replace the empty placeholder).
- Create: `tests/unit/blocks/query/variations.test.js`.

Each variation is a `{ name, title, icon, description, attributes, innerBlocks, scope: ['block'] }` object. `attributes` sets the query (postType, perPage, ordering); `innerBlocks` defines the card layout.

- [ ] **Step 16.1: Write the variations test**

```js
import variations from '../../../../src/blocks/query/variations';

describe('Query block variations', () => {
	it('ships six variations', () => {
		expect(variations).toHaveLength(6);
	});

	it.each(variations.map((v) => [v.name, v]))('%s — has required shape', (_name, v) => {
		expect(v.name).toBeTruthy();
		expect(v.title).toBeTruthy();
		expect(v.attributes).toMatchObject({ source: expect.any(String) });
		expect(Array.isArray(v.innerBlocks)).toBe(true);
	});
});
```

- [ ] **Step 16.2: Implement variations**

Example for `blog-index`:

```js
{
	name: 'blog-index',
	title: __('Blog index', 'designsetgo'),
	description: __('Latest posts grid with featured image, title, excerpt.', 'designsetgo'),
	icon: 'admin-post',
	attributes: {
		source: 'posts',
		postType: 'post',
		perPage: 9,
		orderBy: 'date',
		order: 'DESC',
		tagName: 'ul',
		itemTagName: 'li',
	},
	innerBlocks: [
		['designsetgo/grid', { columns: 3 }, [
			['core/image', { align: 'wide' }], // resolve via binding/post-featured-image at runtime
			['core/post-title', { level: 3, isLink: true }],
			['core/post-date'],
			['core/post-excerpt'],
		]],
		['designsetgo/query-pagination', { mode: 'numbered' }],
	],
	scope: ['block'],
}
```

Repeat with sensible attributes for: `team`, `testimonials`, `portfolio`, `related-posts`, `events`. See [`docs/plans/claude-chat.md:581`](./claude-chat.md#L581) for the confirmed variation list and the competitor-gap analysis in [`docs/plans/claude-chat.md:422`](./claude-chat.md#L422) for use-case defaults.

For `related-posts`, set `excludeCurrent: true` and rely on the `designsetgo/query/{id}/args` filter hook documentation (Task 17) so users can implement "related by shared taxonomy."

- [ ] **Step 16.3: Build + test + commit**

```bash
npm run build && npm run test:unit -- --testPathPattern=variations --watchAll=false
git add src/blocks/query/variations.js tests/unit/blocks/query/variations.test.js
git commit -m "feat(query): 6 block variations (blog/team/testimonials/portfolio/related/events)"
```

---

## Task 17 — PHP filter hook documentation + example

**Files:**
- Create: `.claude/docs/QUERY-BLOCK-GUIDE.md`
- Modify: `.claude/CLAUDE.md` (link the guide)
- Modify: `readme.txt` (changelog entry)

- [ ] **Step 17.1: Write guide**

Cover:
1. Block family overview + Query ID pattern.
2. `designsetgo_query_args` + `designsetgo/query/{queryId}/args` filter recipes: "related by shared taxonomy," "featured first," "exclude last 7 days."
3. Block Bindings usage: binding paragraph content to `designsetgo/post-meta` or `designsetgo/acf` with `{"source":"designsetgo/post-meta","args":{"key":"subtitle"}}`.
4. Interactivity API store name + extending actions.
5. Performance: when to add `update_post_meta_cache(false)`, when the live-count filter is too slow (redirect to v2 indexer roadmap).

- [ ] **Step 17.2: Commit**

```bash
git add .claude/docs/QUERY-BLOCK-GUIDE.md .claude/CLAUDE.md readme.txt
git commit -m "docs(query): filter hooks + Block Bindings usage guide"
```

---

## Task 18 — Accessibility + SEO polish

**Files:**
- Modify: `src/blocks/query/render-helpers.php` (aria-live already set; add schema.org JSON-LD when `source=posts` + `tagName=ul`).
- Modify: `src/blocks/query/view.js` (focus management after refresh + load-more).
- Modify: `src/blocks/query/style.scss` (prefers-reduced-motion).
- Create: `tests/e2e/query-block.spec.js`.

- [ ] **Step 18.1: JSON-LD ItemList**

In `designsetgo_query_wrap`, if `source === 'posts'`, emit alongside the list:

```php
$ld = array(
	'@context' => 'https://schema.org',
	'@type'    => 'ItemList',
	'itemListElement' => array_map(
		function( $i, $post_id ) {
			return array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'url'      => get_permalink( $post_id ),
			);
		},
		array_keys( $post_ids ),
		$post_ids
	),
);
echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES ) . '</script>';
```

Only emit when the block attribute `emitSchema` (add to `block.json`, default `true`) is set.

- [ ] **Step 18.2: Focus management**

Already added in Task 14's `refresh()` helper. Extend load-more: after appending new items, move focus to the first newly-added item's first focusable element.

- [ ] **Step 18.3: Reduced motion**

```scss
.dsgo-query__item {
	transition: opacity 0.2s ease;
}
@media (prefers-reduced-motion: reduce) {
	.dsgo-query__item { transition: none; }
}
```

- [ ] **Step 18.4: Write Playwright e2e**

```js
// tests/e2e/query-block.spec.js
import { test, expect } from '@playwright/test';

test.describe('designsetgo/query', () => {
	test('renders configured posts, filters, paginates', async ({ page, admin, editor }) => {
		await admin.createNewPost();
		await editor.insertBlock({ name: 'designsetgo/query', attributes: { perPage: 3 } });
		await editor.saveDraft();

		// Frontend verify.
		const postUrl = await admin.publishPost();
		await page.goto(postUrl);
		await expect(page.locator('.dsgo-query__item')).toHaveCount(3);

		// Pagination (if > 3 posts exist).
		await page.locator('.dsgo-query-pagination a[rel="next"]').click();
		await expect(page.locator('.dsgo-query__item')).toBeVisible();
	});
});
```

- [ ] **Step 18.5: Run**

```bash
npm run test:e2e -- --grep "designsetgo/query"
```

- [ ] **Step 18.6: Commit**

```bash
git add src/blocks/query/ tests/e2e/query-block.spec.js
git commit -m "feat(query): a11y focus management, reduced motion, ItemList schema"
```

---

## Task 19 — Full-suite verification + CLAUDE.md update

**Files:**
- Modify: `.claude/CLAUDE.md` (document Query block family under Architecture).
- Modify: `readme.txt`, `package.json` (bump version).

- [ ] **Step 19.1: Full test sweep**

```bash
npm run build && npm run lint:js && npm run lint:css && npm run lint:php && npm run test:unit -- --watchAll=false && npm run test:php && npm run test:e2e
```

Expected: all green. If anything fails, resolve before shipping.

- [ ] **Step 19.2: Update CLAUDE.md**

Under `## Architecture`, add:

```markdown
### Query block family

- Container: `designsetgo/query`. Sibling blocks bind via `queryId` + `<InspectorControls>` context key `designsetgo/queryId`.
- Siblings: `designsetgo/query-pagination`, `designsetgo/query-filter` (variations: checkbox/select/search/sort/active/reset), `designsetgo/query-no-results`.
- Dynamic data: WP 6.5+ Block Bindings API. Sources `designsetgo/post-meta` (always) and `designsetgo/acf` (auto-registered when ACF active). Do not invent a token parser.
- Server render: `src/blocks/query/render-helpers.php` owns `designsetgo_query_render()`; REST endpoint `designsetgo/v1/query/render` reuses it so first-paint and AJAX are byte-identical.
- Interactivity API store: `designsetgo/query`. Actions: `loadMore`, `toggleFilter`, `setFilter`, `setFilterDebounced`, `resetAll`.
- Filter hooks: `designsetgo_query_args` (all queries), `designsetgo/query/{queryId}/args` (per query).
- See `.claude/docs/QUERY-BLOCK-GUIDE.md`.
```

- [ ] **Step 19.3: Version bump**

Bump `package.json:version` and `designsetgo.php` plugin-header version to `2.1.0`. Add a changelog entry in `readme.txt`:

```
= 2.1.0 =
* New: Dynamic Query block family (designsetgo/query) — Posts/Users/Terms/Manual/Current sources, tax_query + meta_query builders, Block Bindings for native post meta + ACF, Interactivity API load-more, filter sub-blocks (checkbox/select/search/sort/active/reset), 6 starter variations.
* New: REST endpoint `designsetgo/v1/query/render` for headless consumption.
* Dev: filter hooks `designsetgo_query_args` + `designsetgo/query/{queryId}/args`.
```

- [ ] **Step 19.4: Commit**

```bash
git add .claude/CLAUDE.md readme.txt package.json designsetgo.php
git commit -m "chore: release 2.1.0 — dynamic query block family"
```

---

## Task 20 — Open PR

- [ ] **Step 20.1: Confirm worktree branch is up to date with origin/main**

```bash
git fetch origin main
git log --oneline main..HEAD
```

Expected: `~20` commits, one per task, on top of `main`.

- [ ] **Step 20.2: Push**

```bash
git push -u origin claude/query-block-v1
```

- [ ] **Step 20.3: Open PR via `gh`**

```bash
gh pr create --title "feat(query): Dynamic Query block family v1" --body "$(cat <<'EOF'
## Summary

- Introduces the Dynamic Query block family — container, pagination, filter, no-results — as DesignSetGo's answer to Elementor Loop Grid / Bricks Query Loop / Oxygen Repeater / Etch Loop.
- Composes with existing blocks (Hybrid C blank-canvas shape) instead of shipping a new layout primitive.
- First block family on the Interactivity API + Block Bindings API — sets the plugin-wide standard.

See [`docs/plans/2026-04-18-dynamic-query-block-v1.md`](./docs/plans/2026-04-18-dynamic-query-block-v1.md) for the full plan.

## Test plan

- [x] `npm run test:php` — PHPUnit query-block group all green
- [x] `npm run test:unit` — Jest suites all green
- [x] `npm run test:e2e -- --grep "designsetgo/query"`
- [x] Manual: insert Dynamic Query + Query Pagination (numbered) → publish → paginate
- [x] Manual: switch pagination to load-more → verify IAPI AJAX append
- [x] Manual: add Query Filter (checkbox) → click options → results update, URL stays shareable
- [x] Manual: add Query No Results → set search to unmatched string → verify fallback
- [x] Manual: variations insert → blog-index / team / testimonials / portfolio / related-posts / events
- [x] Manual: Block Bindings binding on `core/paragraph` resolves per-item post meta

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-review checklist (run before declaring the plan done)

- [x] **Spec coverage:** every locked decision from brainstorming maps to a task.
	- Hybrid C blank canvas → Task 4 + 12.
	- 5 sources (Posts/Users/Terms/Manual/Current) → Tasks 5, 6, plus Manual/Current branches inside `render-posts.php` Task 5.
	- tax_query + meta_query builders → Tasks 9, 10.
	- Search/author/excludeCurrent/ignoreSticky/manualIds/offset → Task 11.
	- Filter sub-blocks (6 variations) → Task 14.
	- Numbered + load-more pagination → Task 13.
	- Block Bindings w/ ACF auto-detect → Task 3.
	- Accessibility (ul/li, aria-live, focus) → Tasks 5 (wrap), 14 (refresh), 18.
	- 6 variations → Task 16.
	- REST endpoint for load-more → Tasks 2, 7, 14.
	- Filter hooks + docs → Tasks 5.4, 17.
	- IAPI store precedent → Tasks 13.5, 14.6.
- [x] **Placeholder scan:** no "TBD" / "similar to Task N" / unspecified code. Each step shows the code needed.
- [x] **Type consistency:** `designsetgo_query_render()` signature `(array $attributes, array $context)` used identically in Tasks 2, 5, 6, 7, 13, 14, 15. Context keys (`query_id`, `page`, `inner_html`, `params`) identical everywhere. Attribute names match `block.json`.
- [x] **Scope:** v1-only. Explicit non-goals section above prevents drift into v2 work (filter indexer / nested loops / JetEngine / escape-hatch UI / JSON export).

---

## Execution handoff

Plan complete and saved to [`docs/plans/2026-04-18-dynamic-query-block-v1.md`](./2026-04-18-dynamic-query-block-v1.md). Two execution options:

**1. Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration. Uses `superpowers:subagent-driven-development`.

**2. Inline Execution** — execute tasks in the current session using `superpowers:executing-plans`, batch execution with review checkpoints.

**Which approach?**

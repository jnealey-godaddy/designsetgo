# Dynamic Query v2.4 — Third-Party Bindings + Template I/O Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to execute this plan task-by-task (same session) OR superpowers:executing-plans for a parallel session.

**Goal:** Close the remaining "bring your own fields" gap with Bricks/Elementor Pro/JetEngine by adding first-class binding sources for JetEngine, Meta Box, and Pods; documenting the custom-source escape hatch; and adding JSON export/import of Query block templates.

**Architecture:**
- **Three new binding sources** — `designsetgo/jetengine`, `designsetgo/metabox`, `designsetgo/pods` — each registered only when its host plugin is active. Each delegates to the plugin's canonical formatting API (`jet_engine()->meta_boxes->get_field_value()`, `rwmb_meta()`, `pods_field()`) rather than raw `get_post_meta`, so formatted output (date formats, file URLs, related-post IDs) works correctly.
- **Custom-source helper** — a thin `designsetgo_register_bindings_source( $slug, $callback, $args )` wrapper around `register_block_bindings_source()` that adds the DSGo security gates (post-password, viewable, protected-meta) + `scope` arg support automatically. This is the "PHP escape hatch" — authors can register their own sources in a child theme or mu-plugin without reimplementing the gates.
- **JSON export/import** — a new admin-facing REST route `POST /designsetgo/v1/query/template` that serializes a Query block's attributes (including innerBlocks) to a JSON blob, and `GET` that rehydrates one. Inspector gets an "Export template" + "Import template" button pair, downloads/uploads a `.json` file (portable across sites).

**Tech Stack:**
- WordPress 6.5+ Block Bindings API.
- Plugin detection via `function_exists`/`class_exists` at `init` priority 5.
- REST API controllers following the existing `/v1/query/*` pattern.
- File download via the browser's `Blob` + `URL.createObjectURL` (no external upload path).

---

## Pre-flight

Worktree already created at `.worktrees/query-v2.4-bindings/` on `claude/query-v2.4-bindings`, based on `origin/main` (includes v2.3 merge). Port stays at `9451`.

```bash
cd .worktrees/query-v2.4-bindings
npm ci
composer install
npx wp-env start
```

For testing Meta Box / Pods / JetEngine locally, install at least one via `wp plugin install metabox --activate`. A complete test-matrix is easier to run against a staging site with all three installed.

---

## Phase A — Custom-source helper (the escape hatch)

Ships first because all three plugin integrations build on it. The helper is the public surface for future third-party bindings.

### Task A1: `designsetgo_register_bindings_source()` helper

**Why:** Everyone registering a DSGo binding source (us + plugin authors) wants the same security gates (post-password, viewable, protected-meta) and the same `scope` arg plumbing. Extract it.

**Files:**
- Create: `includes/blocks/class-query-bindings-helpers.php`
- Modify: `includes/class-plugin.php` (require the new file)
- Test: `tests/integration/blocks/query/BindingsHelperTest.php`

**Step 1: Write the failing test**

```php
<?php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_Block;
use WP_UnitTestCase;

class BindingsHelperTest extends WP_UnitTestCase {

    public function test_registers_source_with_shared_gates() {
        $called_with = null;
        designsetgo_register_bindings_source( 'designsetgo/test-source', function ( $args, $block ) use ( &$called_with ) {
            $called_with = $args;
            return 'OK-' . $args['key'];
        }, array( 'label' => 'Test source' ) );

        $this->assertNotNull( get_block_bindings_source( 'designsetgo/test-source' ) );

        $post_id = self::factory()->post->create();
        $block   = new WP_Block(
            array( 'blockName' => 'core/paragraph' ),
            array( 'postId' => $post_id, 'postType' => 'post' )
        );

        $source = get_block_bindings_source( 'designsetgo/test-source' );
        $value  = call_user_func( $source->get_value_callback, array( 'key' => 'x' ), $block, 'content' );
        $this->assertSame( 'OK-x', $value );
    }

    public function test_applies_password_gate() {
        $post_id = self::factory()->post->create( array( 'post_password' => 'secret' ) );
        designsetgo_register_bindings_source( 'designsetgo/test-gated', function ( $args ) {
            return 'LEAK-' . $args['key'];
        } );

        $block = new WP_Block(
            array( 'blockName' => 'core/paragraph' ),
            array( 'postId' => $post_id, 'postType' => 'post' )
        );
        $source = get_block_bindings_source( 'designsetgo/test-gated' );
        $value  = call_user_func( $source->get_value_callback, array( 'key' => 'x' ), $block, 'content' );
        $this->assertNull( $value );
    }
}
```

**Step 2: Run and verify it fails**

```bash
composer test -- --filter=BindingsHelperTest
```

**Step 3: Implement the helper**

`includes/blocks/class-query-bindings-helpers.php`:

```php
<?php
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_register_bindings_source' ) ) :

    /**
     * Register a Block Bindings source with DSGo's shared gates.
     *
     * Wraps register_block_bindings_source() and applies post-password,
     * is_post_publicly_viewable, and is_protected_meta checks to the
     * resolved post id before calling the developer's callback.
     *
     * Also normalizes the `scope` arg ('self' | 'parent' | 'root') so the
     * callback always receives a post id resolved from the correct level
     * of $GLOBALS['designsetgo_parent_stack'].
     *
     * @param string   $slug     Binding source name (e.g. 'designsetgo/acf').
     * @param callable $callback Receives ( array $args, WP_Block $block, string $attribute_name ).
     *                           Must return a string|null.
     * @param array    $options  Forwarded to register_block_bindings_source()
     *                           (label, uses_context, etc.). 'get_value_callback'
     *                           is overwritten.
     */
    function designsetgo_register_bindings_source( $slug, callable $callback, array $options = array() ) {
        if ( ! function_exists( 'register_block_bindings_source' ) ) {
            return;
        }
        if ( get_block_bindings_source( $slug ) ) {
            return;
        }

        $options['uses_context']       = array_values( array_unique( array_merge( $options['uses_context'] ?? array(), array( 'postId' ) ) ) );
        $options['get_value_callback'] = function ( $args, $block = null, $attribute_name = 'content' ) use ( $callback ) {
            $post_id = designsetgo_resolve_bindings_post_id( $args, $block );
            if ( ! $post_id ) {
                return null;
            }

            $post = get_post( $post_id );
            if ( ! $post ) {
                return null;
            }
            if ( post_password_required( $post ) ) {
                return null;
            }
            if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) {
                return null;
            }

            $key = isset( $args['key'] ) ? sanitize_text_field( (string) $args['key'] ) : '';
            if ( '' !== $key && is_protected_meta( $key, 'post' ) ) {
                return null;
            }

            $args['__dsgo_post_id'] = $post_id;
            return call_user_func( $callback, $args, $block, $attribute_name );
        };

        if ( ! isset( $options['label'] ) ) {
            $options['label'] = $slug;
        }

        register_block_bindings_source( $slug, $options );
    }

    /**
     * Resolve the effective post ID for a binding call, honoring the scope arg.
     *
     * Moved into a free function so consumer code (plugin integrations that
     * don't go through designsetgo_register_bindings_source) can reuse it.
     *
     * @param array          $args  Binding args including optional 'scope'.
     * @param \WP_Block|null $block Current block instance.
     * @return int
     */
    function designsetgo_resolve_bindings_post_id( array $args, $block ) {
        $scope = isset( $args['scope'] ) ? (string) $args['scope'] : 'self';

        if ( 'parent' === $scope || 'root' === $scope ) {
            $stack = isset( $GLOBALS['designsetgo_parent_stack'] ) && is_array( $GLOBALS['designsetgo_parent_stack'] )
                ? $GLOBALS['designsetgo_parent_stack']
                : array();
            if ( empty( $stack ) ) {
                return 0;
            }
            $entry = 'parent' === $scope ? end( $stack ) : reset( $stack );
            if ( is_array( $entry ) && ! empty( $entry['postId'] ) ) {
                return (int) $entry['postId'];
            }
            return 0;
        }

        if ( $block && isset( $block->context['postId'] ) ) {
            return (int) $block->context['postId'];
        }

        $current = get_the_ID();
        return $current ? (int) $current : 0;
    }

endif;
```

In `includes/class-plugin.php::load_dependencies()`, add the require before the existing `class-query-bindings.php` require:

```php
require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-bindings-helpers.php';
```

**Step 4: Verify tests pass**

```bash
composer test -- --filter=BindingsHelperTest
```

**Step 5: Commit**

```bash
git add includes/blocks/class-query-bindings-helpers.php includes/class-plugin.php tests/integration/blocks/query/BindingsHelperTest.php
git commit -m "feat(bindings): public designsetgo_register_bindings_source() helper"
```

---

### Task A2: Refactor existing sources to use the helper

**Why:** The existing `designsetgo/post-meta` and `designsetgo/acf` sources reimplement the gates and scope resolution. Migrate them to use the helper so there's one truth.

**Files:**
- Modify: `includes/blocks/class-query-bindings.php`

**Step 1: Read the current file** and confirm you understand the two `get_value_callback` methods.

**Step 2: Replace the registration blocks**

Convert:

```php
register_block_bindings_source(
    'designsetgo/post-meta',
    array(
        'label'              => __( 'Post meta (DesignSetGo)', 'designsetgo' ),
        'get_value_callback' => array( $this, 'get_post_meta_value' ),
        'uses_context'       => array( 'postId' ),
    )
);
```

To:

```php
designsetgo_register_bindings_source(
    'designsetgo/post-meta',
    function ( $args ) {
        $post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
        $key     = isset( $args['key'] ) ? sanitize_text_field( (string) $args['key'] ) : '';
        if ( ! $post_id || '' === $key ) {
            return null;
        }
        $value = get_post_meta( $post_id, $key, true );
        return '' === $value ? null : $value;
    },
    array( 'label' => __( 'Post meta (DesignSetGo)', 'designsetgo' ) )
);
```

Same refactor for `designsetgo/acf` — delegate to `get_field()` when present.

Delete the now-unused `get_post_meta_value()` and `get_acf_value()` methods. Delete `resolve_scoped_post_id()` + `resolve_post_id_from_block()` + `resolve_post_id_from_block_reflection()` methods if no longer referenced (verify with grep). The helper already handles scope resolution.

**Step 3: Run existing tests to confirm no regression**

```bash
composer test -- --filter=BindingsScopeTest
composer test -- --filter=BindingsHelperTest
npm run test:unit -- --testPathPattern=query
```

All must stay green.

**Step 4: Commit**

```bash
git add includes/blocks/class-query-bindings.php
git commit -m "refactor(bindings): migrate post-meta + acf sources to shared helper"
```

---

## Phase B — Third-party plugin integrations

### Task B1: Meta Box binding source

**Why:** Meta Box's `rwmb_meta()` returns already-formatted values (dates as formatted strings, file fields as URLs, etc.). Plain `get_post_meta` on a Meta Box field returns the raw value which is usually wrong to display.

**Files:**
- Create: `includes/blocks/class-query-bindings-metabox.php`
- Modify: `includes/class-plugin.php` (require + instantiate)
- Test: `tests/integration/blocks/query/BindingsMetaBoxTest.php`

**Step 1: Write the failing test**

```php
<?php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_Block;
use WP_UnitTestCase;

class BindingsMetaBoxTest extends WP_UnitTestCase {

    public function test_source_skipped_when_metabox_absent() {
        $this->assertNull( get_block_bindings_source( 'designsetgo/metabox' ) );
    }

    public function test_falls_back_to_post_meta_for_scalar_fields() {
        // Stub rwmb_meta() for this test only.
        if ( ! function_exists( 'rwmb_meta' ) ) {
            function rwmb_meta( $field_id, $args = array(), $post_id = null ) {
                return 'FORMATTED-' . $field_id;
            }
        }

        // Force re-registration after the stub is defined.
        \DesignSetGo\Blocks\Query\MetaBoxBindings::register();

        $post_id = self::factory()->post->create();
        $block   = new WP_Block(
            array( 'blockName' => 'core/paragraph' ),
            array( 'postId' => $post_id, 'postType' => 'post' )
        );

        $source = get_block_bindings_source( 'designsetgo/metabox' );
        $this->assertNotNull( $source );
        $value = call_user_func( $source->get_value_callback, array( 'key' => 'my_field' ), $block, 'content' );
        $this->assertSame( 'FORMATTED-my_field', $value );
    }
}
```

**Step 2: Run and verify it fails**

**Step 3: Implement**

`includes/blocks/class-query-bindings-metabox.php`:

```php
<?php
namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

class MetaBoxBindings {

    public static function bootstrap() {
        add_action( 'init', array( __CLASS__, 'register' ), 5 );
    }

    public static function register() {
        if ( ! function_exists( 'rwmb_meta' ) ) {
            return;
        }
        if ( ! function_exists( 'designsetgo_register_bindings_source' ) ) {
            return;
        }

        designsetgo_register_bindings_source(
            'designsetgo/metabox',
            function ( $args ) {
                $post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
                $key     = isset( $args['key'] ) ? sanitize_text_field( (string) $args['key'] ) : '';
                if ( ! $post_id || '' === $key ) {
                    return null;
                }

                $value = rwmb_meta( $key, array(), $post_id );
                if ( is_array( $value ) || is_object( $value ) ) {
                    return null; // Complex fields need a render path of their own.
                }
                return '' === $value || null === $value || false === $value ? null : (string) $value;
            },
            array( 'label' => __( 'Meta Box field (DesignSetGo)', 'designsetgo' ) )
        );
    }
}
```

In `class-plugin.php::load_dependencies()`:

```php
require_once DESIGNSETGO_PATH . 'includes/blocks/class-query-bindings-metabox.php';
\DesignSetGo\Blocks\Query\MetaBoxBindings::bootstrap();
```

**Step 4: Verify tests pass**

**Step 5: Commit**

```bash
git add includes/blocks/class-query-bindings-metabox.php includes/class-plugin.php tests/integration/blocks/query/BindingsMetaBoxTest.php
git commit -m "feat(bindings): add designsetgo/metabox source for Meta Box fields"
```

---

### Task B2: Pods binding source

**Why:** Pods's `pods_field()` returns post-processed values.

**Files:**
- Create: `includes/blocks/class-query-bindings-pods.php`
- Modify: `includes/class-plugin.php`
- Test: `tests/integration/blocks/query/BindingsPodsTest.php`

Follow the same pattern as B1. Use `function_exists( 'pods_field' )` as the gate. Callback:

```php
$value = pods_field( get_post_type( $post_id ), $post_id, $key );
```

Handle arrays/objects by returning null (scalar-only source, same policy as ACF/Meta Box).

Commit message: `feat(bindings): add designsetgo/pods source for Pods fields`

---

### Task B3: JetEngine binding source

**Why:** JetEngine's `jet_engine()->listings->data->get_meta( $field_id, $post_id )` or `jet_engine()->meta_boxes->get_field_value()` returns formatted values for dynamic fields. Support at least `jet_engine()->listings->data->get_meta` since it's the stable-API call.

**Files:**
- Create: `includes/blocks/class-query-bindings-jetengine.php`
- Modify: `includes/class-plugin.php`
- Test: `tests/integration/blocks/query/BindingsJetEngineTest.php`

Gate: `class_exists( 'Jet_Engine' )` and `function_exists( 'jet_engine' )`. Callback should resolve through `jet_engine()->listings->data->get_meta( $key, $post_id )` when available and fall back to `get_post_meta` if the API shape differs on the installed version.

Commit message: `feat(bindings): add designsetgo/jetengine source for JetEngine fields`

---

## Phase C — JSON template export/import

### Task C1: REST export endpoint

**Why:** Authors who want to share a working Query configuration (all attributes + innerBlocks) need a portable format. The existing `wp:block` copy-paste works but carries opaque IDs; a JSON payload with a schema version is friendlier.

**Files:**
- Create: `includes/blocks/class-query-template-controller.php`
- Modify: `includes/blocks/class-query.php` (register the controller)
- Test: `tests/integration/blocks/query/QueryTemplateControllerTest.php`

**Endpoint:** `GET /designsetgo/v1/query/template?post_id=123&client_id=abc`

Returns:

```json
{
    "schemaVersion": 1,
    "exportedAt": "2026-04-20T12:00:00Z",
    "blockName": "designsetgo/query",
    "attributes": { /* full block attributes */ },
    "innerBlocks": [ /* serialized inner blocks (string) */ ]
}
```

Auth: `edit_posts` capability check on the referenced post. Bad post_id / missing block → 404.

**Step 1: Write the failing test**

Minimal — just register a post with a Query block in content, call the endpoint, assert shape.

**Step 2: Controller skeleton**

Follow the existing REST controller pattern in `class-query.php` (register_routes in a `rest_api_init` hook). New class `Template_Controller` registered under `/v1/query/template`.

Walk the parsed post content looking for the target `designsetgo/query` block by `clientId` attribute (v2.1 added `queryId` — reuse it as the targeting key so it survives serialize/deserialize round-trips).

**Step 3: Implement + commit**

Commit: `feat(query): REST export of query block template as JSON`

---

### Task C2: REST import endpoint

**Why:** Counterpart to export. Takes the JSON blob, validates schema, returns the serialized `wp:block` markup the editor can paste into a post.

**Endpoint:** `POST /designsetgo/v1/query/template`

Body: the JSON from export.

Returns:

```json
{
    "blockMarkup": "<!-- wp:designsetgo/query {\"queryId\":\"new-slug\"} --> …"
}
```

Server-side: generate a fresh `queryId` (never reuse the exported one — that would conflict with any existing instance), serialize attributes + innerBlocks back into block markup via `serialize_block()`.

Validation:
- `schemaVersion === 1` (hard fail otherwise).
- `blockName === 'designsetgo/query'` (hard fail).
- Attribute allowlist — only accept keys from the current `block.json` schema (ignores unknown keys silently).

**Step 1-5:** TDD with assertions on `blockMarkup` shape + attribute allowlisting.

Commit: `feat(query): REST import of JSON template → block markup`

---

### Task C3: Inspector UI — Export / Import buttons

**Why:** The REST endpoints alone are developer-facing. Authors need buttons in the block inspector.

**Files:**
- Create: `src/blocks/query/components/TemplateIO.js`
- Modify: `src/blocks/query/edit.js` (import + render at bottom of Settings panel)
- Test: `tests/unit/blocks/query/TemplateIO.test.js`

**Behavior:**

- "Export template" button — calls `apiFetch` to `/v1/query/template?post_id=X&client_id=Y`, converts response to `Blob`, triggers a download named `query-template-{queryId}.json`.
- "Import template" button — opens a file picker; on file select, reads as text, POSTs to `/v1/query/template`, then uses `@wordpress/blocks` `parse()` + `dispatch('core/block-editor').replaceBlocks()` to swap the current block's content.

**Library imports:**

- `apiFetch` from `@wordpress/api-fetch`.
- `parse` from `@wordpress/blocks`.
- `dispatch('core/block-editor').replaceBlocks` via `useDispatch`.

**Accessibility:** both buttons use `<Button variant="secondary" __next40pxDefaultSize>`, with descriptive labels.

**Unit test:** mock `apiFetch` to return a known response, click the "Export" button, assert `apiFetch` called with the right path. For import, mock file input and assert `replaceBlocks` called with a parsed block.

Commit: `feat(query): inspector Export + Import template buttons`

---

## Phase D — Docs + release

### Task D1: Update the Query guide + CLAUDE.md

**Files:**
- Modify: `.claude/docs/QUERY-BLOCK-GUIDE.md`
- Modify: `.claude/CLAUDE.md`

Add:

- **Recipe: "Render a Meta Box date field in a Query item"** — example binding markup using `designsetgo/metabox` with `key = 'event_date'`.
- **Recipe: "Share a Query block across sites"** — use Export/Import.
- **Extension point: `designsetgo_register_bindings_source()`** — sample code for a custom source reading from an external API.
- **CLAUDE.md v2.4 subsection** summarizing the four new sources + REST template routes.

Commit: `docs: v2.4 recipes + bindings escape-hatch`

---

### Task D2: CHANGELOG entry under `[Unreleased]`

**Files:**
- Modify: `CHANGELOG.md`

Keep plugin version at 2.1.0 (follow v2.3's precedent — unreleased). Add an `## [Unreleased]` / `### Added` bullet list:

```markdown
### Added
- **Dynamic Query — Meta Box, Pods, JetEngine bindings** — three new Block Bindings sources (`designsetgo/metabox`, `designsetgo/pods`, `designsetgo/jetengine`) that delegate to each plugin's canonical formatting API; registered only when the host plugin is active.
- **`designsetgo_register_bindings_source()` helper** — public PHP function for registering custom DSGo-compatible binding sources. Wraps `register_block_bindings_source()` with the shared password/viewable/protected-meta gates + `scope` arg support.
- **Dynamic Query — Template export/import** — REST routes `/designsetgo/v1/query/template` (GET/POST) plus inspector buttons to save/restore a Query block's full configuration as a portable JSON file.
```

Commit: `chore(changelog): v2.4 unreleased entry`

---

### Task D3: Run all checks + push + open PR

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

2. Start wp-env and sanity-check the new features in a browser at `http://localhost:9451/`.

3. Push:

```bash
git push -u origin claude/query-v2.4-bindings
```

4. Open PR against `main`:

```bash
gh pr create --title "feat(query): Dynamic Query v2.4 — JetEngine/Meta Box/Pods bindings + template I/O" --body "$(cat <<'EOF'
## Summary

v2.4 extends the bindings surface area + adds template portability:

- **Three new Block Bindings sources** — `designsetgo/jetengine`, `designsetgo/metabox`, `designsetgo/pods` — each delegates to the host plugin's canonical formatting API and is registered only when that plugin is active.
- **`designsetgo_register_bindings_source()`** — public PHP helper wrapping `register_block_bindings_source()` with the shared security gates + `scope` arg, so custom sources (child themes, mu-plugins) can match DSGo's built-in behavior in one line.
- **Template export/import** — new REST routes `/v1/query/template` (GET for export, POST for import) plus inspector Export/Import buttons. JSON format with `schemaVersion`, attribute allowlisting, fresh `queryId` on import.

No breaking changes. Existing `designsetgo/post-meta` and `designsetgo/acf` sources refactored to use the new helper internally — output identical.

Plan: [\`docs/plans/2026-04-20-dynamic-query-v2.4-bindings.md\`](docs/plans/2026-04-20-dynamic-query-v2.4-bindings.md).

## Test plan
- [ ] Jest unit tests green.
- [ ] PHPUnit integration tests green (BindingsHelper, BindingsMetaBox, BindingsPods, BindingsJetEngine, QueryTemplateController).
- [ ] `npm run build`, `lint:js`, `lint:css`, `lint:php` clean.
- [ ] Manual: install Meta Box free, add a date field, bind a Heading via `designsetgo/metabox`, confirm formatted date renders.
- [ ] Manual: Export a configured Query → download a JSON file → Import into a blank Query on another page → confirm attributes round-trip.
- [ ] Regression: existing `designsetgo/post-meta` + `designsetgo/acf` bindings continue to work unchanged.

Follow-up: v2.5 (date-query UI, multi-level AND/OR tree, hierarchical taxonomy drilldown).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

5. Return the PR URL.

---

## Out of scope (v2.5+)

- **Date-query UI** — inspector pickers for `date_query`. Authors can still hand-compose via `designsetgo/query/{queryId}/args` filter.
- **Multi-level AND/OR tree** — nested relation groups beyond the current single-level `{relation, clauses}`.
- **Hierarchical taxonomy drilldown** — "show posts in this term OR any descendant term".
- **Headless REST consumers** — standalone consumer library.
- **Query Monitor panel integration** — debug panel for DSGo queries.
- **Dynamic CSS from meta** — binding source output flowing into CSS custom properties.

---

## Verification checklist (ship gate)

- [ ] All three host-plugin binding sources register ONLY when their host is active — grep confirmed with `function_exists`/`class_exists` guards.
- [ ] Bindings helper's test asserts the gate returns null for password-protected / unpublished posts.
- [ ] Template export omits transient editor-only fields (e.g. `queryId` is regenerated on import).
- [ ] Template import rejects `schemaVersion !== 1` with a 400.
- [ ] `composer test` + `npm run test:unit` both 100% green.
- [ ] No regression on existing `designsetgo/post-meta` + `designsetgo/acf` sources (5 BindingsScope tests still pass).
- [ ] CHANGELOG entry lives under `[Unreleased]`, plugin version stays at 2.1.0.

# Theme Block-Type Animation Defaults — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a site set per-block-type animation defaults (e.g. `core/button → Fade In Up`) once, so every block of that type animates automatically unless it overrides or opts out — authorable in both theme.json global styles and the plugin's Admin Settings UI.

**Architecture:** A PHP resolver (`Animation_Defaults`) merges per-block-type defaults from the admin option and from `wp_get_global_settings('custom.designsetgo.blockAnimations')` (theme.json + Style Kits). A `render_block` injector applies the resolved animation's classes/`data-*` to blocks that are in the default "inherit" state, reusing the exact markup the save path produces. A new `dsgoAnimationOptOut` block attribute plus the existing `dsgoAnimationEnabled` give each block a tri-state (Inherit / Custom / Off) with **no deprecation** — existing content's stored HTML never changes.

**Tech Stack:** WordPress block editor (`@wordpress/*`), PHP 7.4+, `WP_HTML_Tag_Processor`, Jest (`wp-scripts test-unit-js`), PHPUnit (`composer run-script test`).

## Global Constraints

- **Design spec:** `docs/plans/2026-07-23-theme-block-type-animation-defaults.md` — this plan implements it.
- **Prefixes:** `dsgo-` for CSS/data attributes, `dsgoAttributeName` for JS attributes, `designsetgo_` for PHP functions, `DesignSetGo\` namespace for PHP classes.
- **Indentation:** tabs for JS/PHP.
- **Text domain:** `designsetgo`. All user-facing strings via `__()` / `esc_html__()`.
- **Direct-access guard:** every PHP file starts with `defined( 'ABSPATH' ) || exit;`.
- **No `console.log`** in committed JS.
- **File size:** keep files focused; the render.php/named-function PCP convention does not apply to feature classes.
- **Backward compatibility (hard requirement):** existing animated blocks (Custom state) must serialize byte-identically — no block may become invalid and no deprecation is introduced. A round-trip test pins this.
- **Precedence (approved, one-line-flippable):** for a given block type, an **admin-option** entry overrides a **theme.json / Style-Kit** entry; when the admin list is empty the theme.json/Style-Kit value applies. The feature is enabled when either the admin gate `block_animations_enabled` OR the global `blockAnimationsEnabled` is true. Per-block **Custom/Off always wins** over any global default.

### Deviation from spec (intentional, improves robustness)

The spec tentatively projected the admin option into theme.json via `Admin\Global_Styles::extend_theme_json()`. This plan **does not** touch `class-global-styles.php`. theme.json authorability is achieved by the resolver reading `wp_get_global_settings('custom.designsetgo.blockAnimations')` directly (the `Icon_Injector` precedent) — a theme/Style-Kit that defines that key is picked up with zero plugin projection. Skipping projection avoids `WP_Theme_JSON::update_with()` merging two entry **lists by numeric index** (which would splice admin and theme entries together). Per-block precedence is instead resolved explicitly in `Animation_Defaults::get_effective()` by keying on block name.

---

## File structure

**Create:**
- `includes/features/class-animation-defaults.php` — `DesignSetGo\Animation_Defaults` resolver (static). Reads option + global settings, merges per block name, exact→wildcard lookup.
- `includes/features/class-animation-defaults-injector.php` — `DesignSetGo\Animation_Defaults_Injector`. `render_block` hook; applies inherited animation markup.
- `tests/phpunit/animation-defaults-test.php` — resolver precedence + wildcard tests.
- `tests/phpunit/animation-defaults-injector-test.php` — injector output/skip tests.
- `tests/phpunit/settings-block-animations-test.php` — option sanitization + list-replace-on-save tests.
- `tests/unit/extensions/block-animations-optout.test.js` — `dsgoAnimationOptOut` attribute registration.
- `tests/unit/extensions/animation-panel-tristate.test.js` — editor tri-state control + inherited indicator.
- `src/extensions/block-animations/resolve-default.js` — editor-side helper reading `window.dsgoSettings.blockAnimations`.

**Modify:**
- `includes/data/block-animation-attributes.php` — add `designsetgo_get_animation_parts()`; refactor the existing string function to build from it.
- `includes/admin/class-settings.php` — defaults, sanitization schema + `block_animations` sanitizer, list-replace on save.
- `includes/admin/class-settings-schema.php` — Abilities JSON schema for the new keys.
- `includes/core/class-assets.php` — localize resolved effective defaults to the editor iframe.
- `includes/class-plugin.php` — require + instantiate the injector.
- `src/extensions/block-animations/attributes.js` — add `dsgoAnimationOptOut`.
- `includes/extension-configs/block-animations.php` — add `dsgoAnimationOptOut` to server schema.
- `src/extensions/block-animations/editor.js` — pass block `name` into `AnimationPanel`; keep `AnimationToolbar` consistent with the tri-state.
- `src/extensions/block-animations/components/AnimationPanel.js` — tri-state control + inherited indicator.
- `src/admin/components/settings-panels/AnimationsPanel.js` — per-block-type repeater UI.
- `CHANGELOG.md` — New Features entry.

---

## Task 1: `designsetgo_get_animation_parts()` structured helper (PHP)

Extract a structured (arrays, not strings) view of the animation classes/attributes so the injector can feed a `WP_HTML_Tag_Processor`. The existing string helper is refactored to build on it, keeping its output byte-identical (guarded by the existing test file).

**Files:**
- Modify: `includes/data/block-animation-attributes.php`
- Test: `tests/phpunit/block-animation-attributes-test.php` (existing — extend)

**Interfaces:**
- Produces: `designsetgo_get_animation_parts( array $attributes ): array` → `array( 'classes' => string[], 'attrs' => array<string,string> )`. Returns empty arrays when `dsgoAnimationEnabled` is falsey. Classes/attrs are **unescaped** raw values (caller escapes).
- `designsetgo_get_animation_attributes()` keeps its existing signature/return shape.

- [ ] **Step 1: Write the failing test**

Append to `tests/phpunit/block-animation-attributes-test.php` (inside the test class):

```php
	/**
	 * Parts helper returns empty arrays when animation is disabled.
	 */
	public function test_parts_empty_when_disabled() {
		$parts = designsetgo_get_animation_parts( array( 'dsgoAnimationEnabled' => false ) );
		$this->assertSame( array(), $parts['classes'] );
		$this->assertSame( array(), $parts['attrs'] );
	}

	/**
	 * Parts helper emits entrance class + enabled flag; omits default-valued data attrs.
	 */
	public function test_parts_entrance_and_nondefaults() {
		$parts = designsetgo_get_animation_parts(
			array(
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => 'fadeInUp',
				'dsgoAnimationDuration' => 800, // non-default -> present
				'dsgoAnimationTrigger'  => 'scroll', // default -> absent
			)
		);
		$this->assertContains( 'has-dsgo-animation', $parts['classes'] );
		$this->assertContains( 'dsgo-animation-fadeInUp', $parts['classes'] );
		$this->assertSame( 'true', $parts['attrs']['data-dsgo-animation-enabled'] );
		$this->assertSame( 'fadeInUp', $parts['attrs']['data-dsgo-entrance-animation'] );
		$this->assertSame( '800', $parts['attrs']['data-dsgo-animation-duration'] );
		$this->assertArrayNotHasKey( 'data-dsgo-animation-trigger', $parts['attrs'] );
	}

	/**
	 * Refactored string helper still matches the parts it is built from.
	 */
	public function test_string_helper_matches_parts() {
		$attrs  = array( 'dsgoAnimationEnabled' => true, 'dsgoEntranceAnimation' => 'zoomIn', 'dsgoAnimationOnce' => false );
		$string = designsetgo_get_animation_attributes( $attrs );
		$this->assertStringContainsString( 'has-dsgo-animation', $string['classes'] );
		$this->assertStringContainsString( 'dsgo-animation-zoomIn', $string['classes'] );
		$this->assertStringContainsString( 'data-dsgo-animation-enabled="true"', $string['attrs'] );
		$this->assertStringContainsString( 'data-dsgo-animation-once="false"', $string['attrs'] );
	}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `composer run-script test -- --filter Block_Animation_Attributes` (or the existing class name in that file)
Expected: FAIL — `Call to undefined function designsetgo_get_animation_parts()`.

- [ ] **Step 3: Add the parts helper and refactor the string helper**

In `includes/data/block-animation-attributes.php`, add `designsetgo_get_animation_parts()` **above** `designsetgo_get_animation_attributes()`:

```php
/**
 * Get animation classes/attributes as structured arrays.
 *
 * Raw (unescaped) values — callers are responsible for escaping. Returns
 * empty arrays unless dsgoAnimationEnabled is truthy.
 *
 * @param array $attributes Block attributes array.
 * @return array{classes: string[], attrs: array<string,string>}
 */
function designsetgo_get_animation_parts( $attributes ) {
	$enabled = isset( $attributes['dsgoAnimationEnabled'] ) ? $attributes['dsgoAnimationEnabled'] : false;
	if ( ! $enabled ) {
		return array(
			'classes' => array(),
			'attrs'   => array(),
		);
	}

	$classes = array( 'has-dsgo-animation' );
	$attrs   = array( 'data-dsgo-animation-enabled' => 'true' );

	$entrance = isset( $attributes['dsgoEntranceAnimation'] ) ? (string) $attributes['dsgoEntranceAnimation'] : '';
	if ( '' !== $entrance ) {
		$classes[]                               = 'dsgo-animation-' . $entrance;
		$attrs['data-dsgo-entrance-animation']   = $entrance;
	}

	$exit = isset( $attributes['dsgoExitAnimation'] ) ? (string) $attributes['dsgoExitAnimation'] : '';
	if ( '' !== $exit ) {
		$classes[]                          = 'dsgo-animation-exit-' . $exit;
		$attrs['data-dsgo-exit-animation']  = $exit;
	}

	$trigger = isset( $attributes['dsgoAnimationTrigger'] ) ? (string) $attributes['dsgoAnimationTrigger'] : 'scroll';
	if ( 'scroll' !== $trigger ) {
		$attrs['data-dsgo-animation-trigger'] = $trigger;
	}

	$duration = isset( $attributes['dsgoAnimationDuration'] ) ? (int) $attributes['dsgoAnimationDuration'] : 600;
	if ( 600 !== $duration ) {
		$attrs['data-dsgo-animation-duration'] = (string) $duration;
	}

	$delay = isset( $attributes['dsgoAnimationDelay'] ) ? (int) $attributes['dsgoAnimationDelay'] : 0;
	if ( 0 !== $delay ) {
		$attrs['data-dsgo-animation-delay'] = (string) $delay;
	}

	$easing = isset( $attributes['dsgoAnimationEasing'] ) ? (string) $attributes['dsgoAnimationEasing'] : 'ease-out';
	if ( 'ease-out' !== $easing ) {
		$attrs['data-dsgo-animation-easing'] = $easing;
	}

	$offset = isset( $attributes['dsgoAnimationOffset'] ) ? (int) $attributes['dsgoAnimationOffset'] : 100;
	if ( 100 !== $offset ) {
		$attrs['data-dsgo-animation-offset'] = (string) $offset;
	}

	$once = isset( $attributes['dsgoAnimationOnce'] ) ? (bool) $attributes['dsgoAnimationOnce'] : true;
	if ( ! $once ) {
		$attrs['data-dsgo-animation-once'] = 'false';
	}

	return array(
		'classes' => $classes,
		'attrs'   => $attrs,
	);
}
```

Then replace the **body** of `designsetgo_get_animation_attributes()` (keep its docblock/signature) with:

```php
function designsetgo_get_animation_attributes( $attributes ) {
	$parts = designsetgo_get_animation_parts( $attributes );

	if ( empty( $parts['classes'] ) && empty( $parts['attrs'] ) ) {
		return array(
			'classes' => '',
			'attrs'   => '',
		);
	}

	$classes_string = implode( ' ', array_map( 'esc_attr', $parts['classes'] ) );

	$attrs_string = '';
	foreach ( $parts['attrs'] as $key => $value ) {
		$attrs_string .= ' ' . $key . '="' . esc_attr( $value ) . '"';
	}

	return array(
		'classes' => $classes_string,
		'attrs'   => $attrs_string,
	);
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `composer run-script test -- --filter Block_Animation_Attributes`
Expected: PASS (existing tests in that file still pass — output unchanged).

- [ ] **Step 5: Commit**

```bash
git add includes/data/block-animation-attributes.php tests/phpunit/block-animation-attributes-test.php
git commit -m "refactor(animations): add designsetgo_get_animation_parts() structured helper"
```

---

## Task 2: Settings option — `block_animations_enabled` + `block_animations` (PHP)

Add the storage, sanitization, list-replace-on-save, and Abilities JSON schema for the per-block-type defaults.

**Files:**
- Modify: `includes/admin/class-settings.php` (`get_defaults()` ~line 120; `get_sanitization_schema()` ~line 715; `sanitize_value()` ~line 775; `update_settings()` ~line 601)
- Modify: `includes/admin/class-settings-schema.php` (~line 83, `animations` object)
- Test: `tests/phpunit/settings-block-animations-test.php` (create)

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: option shape `designsetgo_settings['animations']['block_animations_enabled'] : bool` and `['block_animations'] : array<int, array{block,entrance,exit,trigger,duration,delay,easing,offset,once}>`. Public static `Settings::sanitize_block_animations_list( array $value ): array`.

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/settings-block-animations-test.php`:

```php
<?php
/**
 * Tests for per-block-type animation defaults in plugin settings.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Settings;

/**
 * @group settings
 */
class Settings_Block_Animations_Test extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	public function test_defaults_include_disabled_gate_and_empty_list() {
		$defaults = Settings::get_defaults();
		$this->assertFalse( $defaults['animations']['block_animations_enabled'] );
		$this->assertSame( array(), $defaults['animations']['block_animations'] );
	}

	public function test_sanitizer_drops_invalid_block_and_enum_values() {
		$clean = Settings::sanitize_block_animations_list(
			array(
				array( 'block' => 'core/button', 'entrance' => 'fadeInUp', 'trigger' => 'bogus', 'duration' => 999999 ),
				array( 'block' => 'not a block name', 'entrance' => 'fadeIn' ), // invalid name -> dropped
				array( 'entrance' => 'fadeIn' ), // no block -> dropped
				array( 'block' => 'core/quote' ), // no entrance/exit -> dropped
			)
		);
		$this->assertCount( 1, $clean );
		$this->assertSame( 'core/button', $clean[0]['block'] );
		$this->assertSame( 'scroll', $clean[0]['trigger'] ); // bogus -> default
		$this->assertSame( 5000, $clean[0]['duration'] ); // clamped to max
	}

	public function test_sanitizer_accepts_namespace_wildcard() {
		$clean = Settings::sanitize_block_animations_list(
			array( array( 'block' => 'designsetgo/*', 'entrance' => 'fadeIn' ) )
		);
		$this->assertCount( 1, $clean );
		$this->assertSame( 'designsetgo/*', $clean[0]['block'] );
	}

	public function test_saving_replaces_list_instead_of_index_merging() {
		Settings::update_settings(
			array( 'animations' => array( 'block_animations' => array(
				array( 'block' => 'core/button', 'entrance' => 'fadeInUp' ),
				array( 'block' => 'core/image', 'entrance' => 'zoomIn' ),
			) ) )
		);
		Settings::update_settings(
			array( 'animations' => array( 'block_animations' => array(
				array( 'block' => 'core/heading', 'entrance' => 'fadeIn' ),
			) ) )
		);
		$saved = Settings::get_settings()['animations']['block_animations'];
		$this->assertCount( 1, $saved );
		$this->assertSame( 'core/heading', $saved[0]['block'] );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer run-script test -- --filter Settings_Block_Animations`
Expected: FAIL — `block_animations_enabled` missing / `sanitize_block_animations_list` undefined.

- [ ] **Step 3: Add defaults**

In `includes/admin/class-settings.php` `get_defaults()`, extend the `animations` array (after `default_icon_button_hover`):

```php
			'animations'         => array(
				'enable_animations'              => true,
				'default_duration'               => 600,
				'default_easing'                 => 'ease-in-out',
				'respect_prefers_reduced_motion' => true,
				'default_icon_button_hover'      => 'fill-diagonal',
				'block_animations_enabled'       => false,
				'block_animations'               => array(),
			),
```

- [ ] **Step 4: Add sanitization schema + sanitizer case + the list sanitizer**

In `get_sanitization_schema()`, extend the `animations` group:

```php
			'animations'         => array(
				'enable_animations'              => 'bool',
				'default_duration'               => 'absint',
				'default_easing'                 => 'text',
				'respect_prefers_reduced_motion' => 'bool',
				'default_icon_button_hover'      => 'key',
				'block_animations_enabled'       => 'bool',
				'block_animations'               => 'block_animations',
			),
```

In `sanitize_value()`, add a case before `default:`:

```php
			case 'block_animations':
				return self::sanitize_block_animations_list( is_array( $value ) ? $value : array() );
```

Add the public sanitizer method (near `sanitize_css_selector()`):

```php
	/**
	 * Sanitize the per-block-type animation defaults list.
	 *
	 * Each entry must name a valid block (exact name or `namespace/*`) and
	 * carry at least an entrance or exit animation. Enum fields fall back to
	 * their defaults on an unknown value; numeric fields are clamped.
	 *
	 * @param array $value Raw list of entries.
	 * @return array Sanitized list (re-indexed).
	 */
	public static function sanitize_block_animations_list( array $value ): array {
		$entrances = array( 'fadeIn', 'fadeInUp', 'fadeInDown', 'fadeInLeft', 'fadeInRight', 'slideInUp', 'slideInDown', 'slideInLeft', 'slideInRight', 'zoomIn', 'bounceIn', 'flipInX', 'flipInY' );
		$exits     = array( 'fadeOut', 'fadeOutUp', 'fadeOutDown', 'fadeOutLeft', 'fadeOutRight', 'slideOutUp', 'slideOutDown', 'slideOutLeft', 'slideOutRight', 'zoomOut', 'bounceOut' );
		$triggers  = array( 'scroll', 'load', 'hover', 'click' );
		$easings   = array( 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'linear', 'cubic-bezier(0.68, -0.55, 0.265, 1.55)' );

		$clean = array();
		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['block'] ) ) {
				continue;
			}

			$block = (string) $entry['block'];
			// namespace/name or namespace/* — lowercase letters, digits, hyphens.
			if ( ! preg_match( '#^[a-z0-9-]+/(\*|[a-z0-9-]+)$#', $block ) ) {
				continue;
			}

			$entrance = isset( $entry['entrance'] ) && in_array( (string) $entry['entrance'], $entrances, true ) ? (string) $entry['entrance'] : '';
			$exit     = isset( $entry['exit'] ) && in_array( (string) $entry['exit'], $exits, true ) ? (string) $entry['exit'] : '';

			// An entry that animates nothing is meaningless.
			if ( '' === $entrance && '' === $exit ) {
				continue;
			}

			$trigger  = isset( $entry['trigger'] ) && in_array( (string) $entry['trigger'], $triggers, true ) ? (string) $entry['trigger'] : 'scroll';
			$easing   = isset( $entry['easing'] ) && in_array( (string) $entry['easing'], $easings, true ) ? (string) $entry['easing'] : 'ease-out';
			$duration = isset( $entry['duration'] ) ? max( 100, min( 5000, absint( $entry['duration'] ) ) ) : 600;
			$delay    = isset( $entry['delay'] ) ? max( 0, min( 5000, absint( $entry['delay'] ) ) ) : 0;
			$offset   = isset( $entry['offset'] ) ? max( 0, min( 1000, absint( $entry['offset'] ) ) ) : 100;
			$once     = isset( $entry['once'] ) ? (bool) $entry['once'] : true;

			$clean[] = array(
				'block'    => $block,
				'entrance' => $entrance,
				'exit'     => $exit,
				'trigger'  => $trigger,
				'duration' => $duration,
				'delay'    => $delay,
				'easing'   => $easing,
				'offset'   => $offset,
				'once'     => $once,
			);
		}

		return $clean;
	}
```

- [ ] **Step 5: Force list-replace on save**

In `update_settings()`, replace the body so the `block_animations` list is overwritten wholesale rather than index-merged:

```php
	public static function update_settings( array $input ): array {
		$sanitized = self::sanitize_settings( $input );

		$existing = get_option( self::OPTION_NAME, array() );
		$merged   = array_replace_recursive( $existing, $sanitized );

		// List fields must be replaced wholesale — array_replace_recursive
		// merges lists by numeric index, which would strand stale entries.
		if ( isset( $sanitized['animations']['block_animations'] ) ) {
			$merged['animations']['block_animations'] = $sanitized['animations']['block_animations'];
		}

		update_option( self::OPTION_NAME, $merged );
		self::invalidate_cache();

		return self::get_settings();
	}
```

- [ ] **Step 6: Add the Abilities JSON schema keys**

In `includes/admin/class-settings-schema.php`, inside the `animations` object `properties` (after `default_icon_button_hover`):

```php
						'block_animations_enabled'       => array( 'type' => 'boolean' ),
						'block_animations'               => array(
							'type'  => 'array',
							'items' => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => array(
									'block'    => array( 'type' => 'string' ),
									'entrance' => array( 'type' => 'string' ),
									'exit'     => array( 'type' => 'string' ),
									'trigger'  => array( 'type' => 'string' ),
									'duration' => array( 'type' => 'integer' ),
									'delay'    => array( 'type' => 'integer' ),
									'easing'   => array( 'type' => 'string' ),
									'offset'   => array( 'type' => 'integer' ),
									'once'     => array( 'type' => 'boolean' ),
								),
							),
						),
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `composer run-script test -- --filter Settings_Block_Animations`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add includes/admin/class-settings.php includes/admin/class-settings-schema.php tests/phpunit/settings-block-animations-test.php
git commit -m "feat(settings): store and sanitize per-block-type animation defaults"
```

---

## Task 3: `Animation_Defaults` resolver (PHP)

Resolve the effective per-block-type default for any block name, merging admin option over theme.json/Style-Kit, with exact→wildcard lookup.

**Files:**
- Create: `includes/features/class-animation-defaults.php`
- Modify: `includes/class-plugin.php` (require the file ~line 648)
- Test: `tests/phpunit/animation-defaults-test.php` (create)

**Interfaces:**
- Consumes: `Settings::get_settings()` shape from Task 2.
- Produces:
  - `Animation_Defaults::get_effective(): array` → `array{ enabled: bool, map: array<string, array{entrance,exit,trigger,duration,delay,easing,offset,once}> }` (map keyed by block string, incl. wildcard keys).
  - `Animation_Defaults::resolve_for_block( string $block_name ): ?array` → normalized config or `null`.

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/animation-defaults-test.php`:

```php
<?php
/**
 * Tests for the per-block-type animation defaults resolver.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Settings;
use DesignSetGo\Animation_Defaults;

/**
 * @group animations
 */
class Animation_Defaults_Test extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	private function set_option( $enabled, array $list ) {
		Settings::update_settings(
			array( 'animations' => array(
				'block_animations_enabled' => $enabled,
				'block_animations'         => $list,
			) )
		);
	}

	public function test_disabled_gate_returns_null() {
		$this->set_option( false, array( array( 'block' => 'core/button', 'entrance' => 'fadeInUp' ) ) );
		$this->assertNull( Animation_Defaults::resolve_for_block( 'core/button' ) );
	}

	public function test_exact_match_resolves_normalized_config() {
		$this->set_option( true, array( array( 'block' => 'core/button', 'entrance' => 'fadeInUp' ) ) );
		$config = Animation_Defaults::resolve_for_block( 'core/button' );
		$this->assertSame( 'fadeInUp', $config['entrance'] );
		$this->assertSame( 'scroll', $config['trigger'] ); // normalized default
		$this->assertSame( 600, $config['duration'] );
	}

	public function test_wildcard_match_when_no_exact() {
		$this->set_option( true, array( array( 'block' => 'designsetgo/*', 'entrance' => 'fadeIn' ) ) );
		$this->assertSame( 'fadeIn', Animation_Defaults::resolve_for_block( 'designsetgo/section' )['entrance'] );
		$this->assertNull( Animation_Defaults::resolve_for_block( 'core/paragraph' ) );
	}

	public function test_exact_beats_wildcard() {
		$this->set_option( true, array(
			array( 'block' => 'designsetgo/*', 'entrance' => 'fadeIn' ),
			array( 'block' => 'designsetgo/section', 'entrance' => 'zoomIn' ),
		) );
		$this->assertSame( 'zoomIn', Animation_Defaults::resolve_for_block( 'designsetgo/section' )['entrance'] );
	}

	public function test_admin_option_overrides_theme_json_for_same_block() {
		// Theme.json layer via wp_get_global_settings filter.
		add_filter( 'wp_theme_json_data_theme', function ( $data ) {
			return $data->update_with( array(
				'version'  => 2,
				'settings' => array( 'custom' => array( 'designsetgo' => array(
					'blockAnimationsEnabled' => true,
					'blockAnimations'        => array( array( 'block' => 'core/button', 'entrance' => 'fadeIn' ) ),
				) ) ),
			) );
		} );
		$this->set_option( true, array( array( 'block' => 'core/button', 'entrance' => 'zoomIn' ) ) );

		$this->assertSame( 'zoomIn', Animation_Defaults::resolve_for_block( 'core/button' )['entrance'] );
	}

	public function test_theme_json_used_when_admin_list_empty() {
		add_filter( 'wp_theme_json_data_theme', function ( $data ) {
			return $data->update_with( array(
				'version'  => 2,
				'settings' => array( 'custom' => array( 'designsetgo' => array(
					'blockAnimationsEnabled' => true,
					'blockAnimations'        => array( array( 'block' => 'core/image', 'entrance' => 'zoomIn' ) ),
				) ) ),
			) );
		} );
		// Admin list empty, admin gate off — global gate turns it on.
		$this->set_option( false, array() );

		$this->assertSame( 'zoomIn', Animation_Defaults::resolve_for_block( 'core/image' )['entrance'] );
	}
}
```

> Note: `wp_get_global_settings()` caches per request. `WP_UnitTestCase` runs each test in its own bootstrap with the theme-json cache reset between tests via the `wp_theme_json_data_theme` filter being re-added, so no manual cache flush is needed. If a test sees stale data, add `wp_clean_theme_json_cache();` after adding the filter.

- [ ] **Step 2: Run to verify it fails**

Run: `composer run-script test -- --filter Animation_Defaults_Test`
Expected: FAIL — `Class "DesignSetGo\Animation_Defaults" not found`.

- [ ] **Step 3: Create the resolver class**

Create `includes/features/class-animation-defaults.php`:

```php
<?php
/**
 * Per-block-type animation defaults resolver.
 *
 * Merges the admin-option defaults over theme.json / Style-Kit defaults
 * (read via wp_get_global_settings) and resolves the effective config for a
 * given block name, honouring exact-name-then-namespace-wildcard precedence.
 *
 * @package DesignSetGo
 * @since 2.6.0
 */

namespace DesignSetGo;

use DesignSetGo\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolver for global per-block-type animation defaults.
 */
class Animation_Defaults {

	/**
	 * Resolve the effective enabled flag + per-block-name config map.
	 *
	 * Precedence per block name: admin option overrides theme.json / Style Kit.
	 * Enabled when either the admin gate or the global gate is true.
	 *
	 * @return array{enabled: bool, map: array<string, array>}
	 */
	public static function get_effective() {
		$settings      = Settings::get_settings();
		$admin_enabled = ! empty( $settings['animations']['block_animations_enabled'] );
		$admin_list    = isset( $settings['animations']['block_animations'] ) && is_array( $settings['animations']['block_animations'] )
			? $settings['animations']['block_animations']
			: array();

		$global_list = wp_get_global_settings( array( 'custom', 'designsetgo', 'blockAnimations' ) );
		$global_list = is_array( $global_list ) ? $global_list : array();

		$global_enabled = ! empty( wp_get_global_settings( array( 'custom', 'designsetgo', 'blockAnimationsEnabled' ) ) );

		// Global (theme.json / Style Kit) first, admin overrides per block name.
		$map = array();
		foreach ( array( $global_list, $admin_list ) as $list ) {
			foreach ( $list as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['block'] ) ) {
					$map[ (string) $entry['block'] ] = self::normalize_entry( $entry );
				}
			}
		}

		return array(
			'enabled' => ( $admin_enabled || $global_enabled ),
			'map'     => $map,
		);
	}

	/**
	 * Resolve the config for a single block name, or null if none applies.
	 *
	 * @param string $block_name Block name (e.g. "core/button").
	 * @return array|null Normalized config or null.
	 */
	public static function resolve_for_block( $block_name ) {
		$effective = self::get_effective();
		if ( ! $effective['enabled'] ) {
			return null;
		}

		$map = $effective['map'];
		if ( isset( $map[ $block_name ] ) ) {
			return $map[ $block_name ];
		}

		$slash = strpos( $block_name, '/' );
		if ( false !== $slash ) {
			$wildcard = substr( $block_name, 0, $slash + 1 ) . '*';
			if ( isset( $map[ $wildcard ] ) ) {
				return $map[ $wildcard ];
			}
		}

		return null;
	}

	/**
	 * Fill missing config fields with the extension's attribute defaults.
	 *
	 * @param array $entry Raw entry.
	 * @return array Normalized config (no 'block' key).
	 */
	private static function normalize_entry( $entry ) {
		return array(
			'entrance' => isset( $entry['entrance'] ) ? (string) $entry['entrance'] : '',
			'exit'     => isset( $entry['exit'] ) ? (string) $entry['exit'] : '',
			'trigger'  => isset( $entry['trigger'] ) ? (string) $entry['trigger'] : 'scroll',
			'duration' => isset( $entry['duration'] ) ? (int) $entry['duration'] : 600,
			'delay'    => isset( $entry['delay'] ) ? (int) $entry['delay'] : 0,
			'easing'   => isset( $entry['easing'] ) ? (string) $entry['easing'] : 'ease-out',
			'offset'   => isset( $entry['offset'] ) ? (int) $entry['offset'] : 100,
			'once'     => isset( $entry['once'] ) ? (bool) $entry['once'] : true,
		);
	}
}
```

- [ ] **Step 4: Require the file**

In `includes/class-plugin.php`, next to the other `features/` requires (after the `class-svg-pattern-renderer.php` line ~648):

```php
		require_once DESIGNSETGO_PATH . 'includes/features/class-animation-defaults.php';
```

- [ ] **Step 5: Run to verify it passes**

Run: `composer run-script test -- --filter Animation_Defaults_Test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/features/class-animation-defaults.php includes/class-plugin.php tests/phpunit/animation-defaults-test.php
git commit -m "feat(animations): add Animation_Defaults resolver (option + theme.json merge)"
```

---

## Task 4: `Animation_Defaults_Injector` render_block injector (PHP)

Apply the resolved animation's classes/`data-*` to blocks in the "inherit" state at render time.

**Files:**
- Create: `includes/features/class-animation-defaults-injector.php`
- Modify: `includes/class-plugin.php` (require ~line 648; property decl ~line 464; instantiate in `init()` ~line 720)
- Test: `tests/phpunit/animation-defaults-injector-test.php` (create)

**Interfaces:**
- Consumes: `designsetgo_get_animation_parts()` (Task 1), `Animation_Defaults::resolve_for_block()` (Task 3).
- Produces: `DesignSetGo\Animation_Defaults_Injector` with `init(): void` (registers the `render_block` filter) and `inject( string $block_content, array $block ): string`.

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/animation-defaults-injector-test.php`:

```php
<?php
/**
 * Tests for the per-block-type animation render_block injector.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Settings;
use DesignSetGo\Animation_Defaults_Injector;

/**
 * @group animations
 */
class Animation_Defaults_Injector_Test extends WP_UnitTestCase {

	/** @var Animation_Defaults_Injector */
	private $injector;

	public function set_up() {
		parent::set_up();
		$this->injector = new Animation_Defaults_Injector();
		Settings::update_settings(
			array( 'animations' => array(
				'block_animations_enabled' => true,
				'block_animations'         => array( array( 'block' => 'core/button', 'entrance' => 'fadeInUp', 'duration' => 800 ) ),
			) )
		);
	}

	public function tear_down() {
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	public function test_injects_classes_and_data_for_inherit_state_block() {
		$html = '<div class="wp-block-button">x</div>';
		$out  = $this->injector->inject( $html, array( 'blockName' => 'core/button', 'attrs' => array() ) );

		$this->assertStringContainsString( 'has-dsgo-animation', $out );
		$this->assertStringContainsString( 'dsgo-animation-fadeInUp', $out );
		$this->assertStringContainsString( 'data-dsgo-animation-enabled="true"', $out );
		$this->assertStringContainsString( 'data-dsgo-animation-duration="800"', $out );
	}

	public function test_skips_custom_state_block() {
		$html = '<div class="wp-block-button">x</div>';
		$out  = $this->injector->inject( $html, array( 'blockName' => 'core/button', 'attrs' => array( 'dsgoAnimationEnabled' => true ) ) );
		$this->assertSame( $html, $out );
	}

	public function test_skips_opted_out_block() {
		$html = '<div class="wp-block-button">x</div>';
		$out  = $this->injector->inject( $html, array( 'blockName' => 'core/button', 'attrs' => array( 'dsgoAnimationOptOut' => true ) ) );
		$this->assertSame( $html, $out );
	}

	public function test_skips_block_type_without_default() {
		$html = '<p>x</p>';
		$out  = $this->injector->inject( $html, array( 'blockName' => 'core/paragraph', 'attrs' => array() ) );
		$this->assertSame( $html, $out );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer run-script test -- --filter Animation_Defaults_Injector_Test`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the injector class**

Create `includes/features/class-animation-defaults-injector.php`:

```php
<?php
/**
 * Applies global per-block-type animation defaults at render time.
 *
 * Blocks in the "inherit" state (no per-block animation, not opted out) whose
 * type has a configured default get the same classes/data-attributes the save
 * path bakes for hand-authored animations, via WP_HTML_Tag_Processor.
 *
 * @package DesignSetGo
 * @since 2.6.0
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * Render-time injector for global animation defaults.
 */
class Animation_Defaults_Injector {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'render_block', array( $this, 'inject' ), 10, 2 );
	}

	/**
	 * Inject inherited animation markup onto a rendered block.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block (blockName, attrs, ...).
	 * @return string Possibly-modified HTML.
	 */
	public function inject( $block_content, $block ) {
		if ( is_admin() ) {
			return $block_content;
		}
		if ( empty( $block['blockName'] ) || '' === trim( (string) $block_content ) ) {
			return $block_content;
		}

		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		// Custom state — block owns its animation (already baked at save).
		if ( ! empty( $attrs['dsgoAnimationEnabled'] ) ) {
			return $block_content;
		}
		// Off state — explicit opt-out.
		if ( ! empty( $attrs['dsgoAnimationOptOut'] ) ) {
			return $block_content;
		}

		$config = Animation_Defaults::resolve_for_block( $block['blockName'] );
		if ( null === $config ) {
			return $block_content;
		}

		$parts = designsetgo_get_animation_parts(
			array(
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => $config['entrance'],
				'dsgoExitAnimation'     => $config['exit'],
				'dsgoAnimationTrigger'  => $config['trigger'],
				'dsgoAnimationDuration' => $config['duration'],
				'dsgoAnimationDelay'    => $config['delay'],
				'dsgoAnimationEasing'   => $config['easing'],
				'dsgoAnimationOffset'   => $config['offset'],
				'dsgoAnimationOnce'     => $config['once'],
			)
		);

		if ( empty( $parts['classes'] ) ) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag() ) {
			return $block_content;
		}

		// Belt-and-suspenders: never double-apply.
		if ( $processor->has_class( 'has-dsgo-animation' ) ) {
			return $block_content;
		}

		foreach ( $parts['classes'] as $class ) {
			$processor->add_class( $class );
		}
		foreach ( $parts['attrs'] as $key => $value ) {
			$processor->set_attribute( $key, $value );
		}

		return $processor->get_updated_html();
	}
}
```

- [ ] **Step 4: Require, declare, and instantiate**

In `includes/class-plugin.php`:

1. Require (near the other `features/` requires, after Task 3's line):

```php
		require_once DESIGNSETGO_PATH . 'includes/features/class-animation-defaults-injector.php';
```

2. Property declaration (next to `public $svg_pattern_renderer;` ~line 464):

```php
	/**
	 * Animation defaults injector.
	 *
	 * @var Animation_Defaults_Injector
	 */
	public $animation_defaults_injector;
```

3. Instantiate in `init()` (next to `$this->svg_pattern_renderer = new SVG_Pattern_Renderer();` ~line 720):

```php
		$this->animation_defaults_injector = new Animation_Defaults_Injector();
		$this->animation_defaults_injector->init();
```

- [ ] **Step 5: Run to verify it passes**

Run: `composer run-script test -- --filter Animation_Defaults_Injector_Test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/features/class-animation-defaults-injector.php includes/class-plugin.php tests/phpunit/animation-defaults-injector-test.php
git commit -m "feat(animations): inject inherited animation defaults at render time"
```

---

## Task 5: `dsgoAnimationOptOut` block attribute (JS + PHP schema)

Give every block an explicit "Off" state distinct from the default "inherit".

**Files:**
- Modify: `src/extensions/block-animations/attributes.js`
- Modify: `includes/extension-configs/block-animations.php`
- Test: `tests/unit/extensions/block-animations-optout.test.js` (create)

**Interfaces:**
- Produces: block attribute `dsgoAnimationOptOut` (`boolean`, default `false`) on every extended block.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/extensions/block-animations-optout.test.js`:

```js
/**
 * dsgoAnimationOptOut attribute registration.
 */
jest.mock('@wordpress/hooks', () => ({ addFilter: jest.fn() }));

import { addFilter } from '@wordpress/hooks';
import '../../../src/extensions/block-animations/attributes';

function getAddAttributesFn() {
	const call = addFilter.mock.calls.find(
		([hook]) => hook === 'blocks.registerBlockType'
	);
	return call[2];
}

describe('block-animations attributes: opt-out', () => {
	it('adds dsgoAnimationOptOut default false to a normal block', () => {
		const fn = getAddAttributesFn();
		const out = fn({ attributes: {} }, 'core/button');
		expect(out.attributes.dsgoAnimationOptOut).toEqual({
			type: 'boolean',
			default: false,
		});
	});

	it('does not add attributes to excluded blocks', () => {
		const fn = getAddAttributesFn();
		const out = fn({ attributes: {} }, 'core/freeform');
		expect(out.attributes.dsgoAnimationOptOut).toBeUndefined();
	});
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `npm run test:unit -- block-animations-optout`
Expected: FAIL — `dsgoAnimationOptOut` is `undefined`.

- [ ] **Step 3: Add the attribute (JS)**

In `src/extensions/block-animations/attributes.js`, add after `dsgoAnimationOnce` inside the returned `attributes` object:

```js
			dsgoAnimationOptOut: {
				type: 'boolean',
				default: false,
			},
```

- [ ] **Step 4: Add the attribute (PHP schema)**

In `includes/extension-configs/block-animations.php`, add after `dsgoAnimationOnce`:

```php
		'dsgoAnimationOptOut'   => array(
			'type'    => 'boolean',
			'default' => false,
		),
```

- [ ] **Step 5: Run to verify it passes**

Run: `npm run test:unit -- block-animations-optout`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/extensions/block-animations/attributes.js includes/extension-configs/block-animations.php tests/unit/extensions/block-animations-optout.test.js
git commit -m "feat(animations): add dsgoAnimationOptOut attribute for explicit off state"
```

---

## Task 6: Editor tri-state control + inherited indicator + localization (JS + PHP)

Turn the panel's Enable toggle into Inherit / Custom / Off, show the inherited default as an indicator, and localize the resolved defaults into the editor iframe.

**Files:**
- Create: `src/extensions/block-animations/resolve-default.js`
- Modify: `src/extensions/block-animations/editor.js`
- Modify: `src/extensions/block-animations/components/AnimationPanel.js`
- Modify: `includes/core/class-assets.php` (`localize_extension_settings()` ~line 122)
- Test: `tests/unit/extensions/animation-panel-tristate.test.js` (create)

**Interfaces:**
- Consumes: `dsgoAnimationOptOut` (Task 5); `window.dsgoSettings.blockAnimations` / `.blockAnimationsEnabled` (localized here).
- Produces:
  - `resolveBlockAnimationDefault( blockName ): { entrance, exit, trigger, duration, delay, easing, offset, once } | null` from `src/extensions/block-animations/resolve-default.js`.
  - `AnimationPanel` now accepts a `name` prop (block name).

- [ ] **Step 1: Write the failing test**

Create `tests/unit/extensions/animation-panel-tristate.test.js`:

```js
/**
 * AnimationPanel tri-state (Inherit / Custom / Off) + inherited indicator.
 */
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({ __: (t) => t }));

// Minimal component mocks that render enough to assert against.
jest.mock('@wordpress/components', () => ({
	PanelBody: ({ children }) => <div>{children}</div>,
	ToggleControl: ({ label }) => <label>{label}</label>,
	SelectControl: ({ label }) => <label>{label}</label>,
	RangeControl: ({ label }) => <label>{label}</label>,
	Notice: ({ children }) => <div>{children}</div>,
	__experimentalToggleGroupControl: ({ label, value, children }) => (
		<div aria-label={label} data-value={value}>
			{children}
		</div>
	),
	__experimentalToggleGroupControlOption: ({ label, value }) => (
		<button data-value={value}>{label}</button>
	),
}));

import AnimationPanel from '../../../src/extensions/block-animations/components/AnimationPanel';

describe('AnimationPanel tri-state', () => {
	beforeEach(() => {
		window.dsgoSettings = {
			blockAnimationsEnabled: true,
			blockAnimations: [
				{
					block: 'core/button',
					entrance: 'fadeInUp',
					trigger: 'scroll',
					duration: 600,
				},
			],
		};
	});

	it('shows the inherited indicator for a block type with a default', () => {
		render(
			<AnimationPanel
				name="core/button"
				attributes={{}}
				setAttributes={() => {}}
			/>
		);
		expect(
			screen.getByText(/Inheriting theme animation/i)
		).toBeInTheDocument();
	});

	it('shows the no-default message when none applies', () => {
		render(
			<AnimationPanel
				name="core/paragraph"
				attributes={{}}
				setAttributes={() => {}}
			/>
		);
		expect(
			screen.getByText(/No theme animation for this block type/i)
		).toBeInTheDocument();
	});
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `npm run test:unit -- animation-panel-tristate`
Expected: FAIL — `AnimationPanel` does not accept `name` / no indicator text.

- [ ] **Step 3: Create the resolve-default helper**

Create `src/extensions/block-animations/resolve-default.js`:

```js
/**
 * Resolve the effective theme animation default for a block name from the
 * localized settings, mirroring the PHP Animation_Defaults resolver
 * (exact name, then namespace wildcard). Returns null when none applies.
 *
 * @param {string} blockName Block name.
 * @return {Object|null} Config or null.
 */
export function resolveBlockAnimationDefault(blockName) {
	const settings =
		(typeof window !== 'undefined' && window.dsgoSettings) || {};

	if (!settings.blockAnimationsEnabled) {
		return null;
	}

	const list = Array.isArray(settings.blockAnimations)
		? settings.blockAnimations
		: [];

	const exact = list.find((entry) => entry && entry.block === blockName);
	let match = exact;

	if (!match) {
		const slash = blockName.indexOf('/');
		if (slash !== -1) {
			const wildcard = `${blockName.slice(0, slash + 1)}*`;
			match = list.find((entry) => entry && entry.block === wildcard);
		}
	}

	if (!match) {
		return null;
	}

	return {
		entrance: match.entrance || '',
		exit: match.exit || '',
		trigger: match.trigger || 'scroll',
		duration:
			typeof match.duration === 'number' ? match.duration : 600,
		delay: typeof match.delay === 'number' ? match.delay : 0,
		easing: match.easing || 'ease-out',
		offset: typeof match.offset === 'number' ? match.offset : 100,
		once: typeof match.once === 'boolean' ? match.once : true,
	};
}
```

- [ ] **Step 4: Pass `name` into AnimationPanel**

In `src/extensions/block-animations/editor.js`, inside `withAnimationControls`, update the `AnimationPanel` render to pass `name`:

```jsx
						<AnimationPanel
							name={name}
							attributes={attributes}
							setAttributes={setAttributes}
						/>
```

- [ ] **Step 5: Rewrite AnimationPanel with the tri-state + indicator**

Replace `src/extensions/block-animations/components/AnimationPanel.js` with:

```jsx
/**
 * Block Animations - Settings Panel
 *
 * Panel for the per-block animation tri-state (Inherit / Custom / Off),
 * the Custom controls, and the inherited-theme-default indicator.
 *
 * @package
 * @since 1.0.0
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	RangeControl,
	Notice,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import {
	ANIMATION_TYPES,
	ANIMATION_TRIGGERS,
	ANIMATION_DURATIONS,
	ANIMATION_EASINGS,
} from '../constants';
import { resolveBlockAnimationDefault } from '../resolve-default';

/**
 * Human label for an entrance/exit value.
 *
 * @param {string} value Animation value.
 * @return {string} Label or the raw value.
 */
function animationLabel(value) {
	const all = [...ANIMATION_TYPES.entrance, ...ANIMATION_TYPES.exit];
	const found = all.find((opt) => opt.value === value);
	return found ? found.label : value;
}

/**
 * Animation Settings Panel.
 *
 * @param {Object}   props               Component props.
 * @param {string}   props.name          Block name.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element} Panel.
 */
export default function AnimationPanel({ name, attributes, setAttributes }) {
	const {
		dsgoAnimationEnabled,
		dsgoAnimationOptOut,
		dsgoEntranceAnimation,
		dsgoExitAnimation,
		dsgoAnimationTrigger,
		dsgoAnimationDuration,
		dsgoAnimationDelay,
		dsgoAnimationEasing,
		dsgoAnimationOffset,
		dsgoAnimationOnce,
	} = attributes;

	// Derive tri-state from the two attributes.
	let mode = 'inherit';
	if (dsgoAnimationEnabled) {
		mode = 'custom';
	} else if (dsgoAnimationOptOut) {
		mode = 'off';
	}

	const themeDefault = resolveBlockAnimationDefault(name);

	const onModeChange = (value) => {
		if (value === 'custom') {
			setAttributes({
				dsgoAnimationEnabled: true,
				dsgoAnimationOptOut: false,
			});
		} else if (value === 'off') {
			setAttributes({
				dsgoAnimationEnabled: false,
				dsgoAnimationOptOut: true,
			});
		} else {
			setAttributes({
				dsgoAnimationEnabled: false,
				dsgoAnimationOptOut: false,
			});
		}
	};

	return (
		<PanelBody
			title={__('Animations', 'designsetgo')}
			initialOpen={false}
			icon="video-alt3"
		>
			<ToggleGroupControl
				label={__('Animation', 'designsetgo')}
				value={mode}
				isBlock
				onChange={onModeChange}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			>
				<ToggleGroupControlOption
					value="inherit"
					label={__('Theme', 'designsetgo')}
				/>
				<ToggleGroupControlOption
					value="custom"
					label={__('Custom', 'designsetgo')}
				/>
				<ToggleGroupControlOption
					value="off"
					label={__('Off', 'designsetgo')}
				/>
			</ToggleGroupControl>

			{mode === 'inherit' && themeDefault && (
				<Notice status="info" isDismissible={false}>
					{sprintf(
						/* translators: 1: animation name, 2: trigger, 3: duration in ms. */
						__(
							'Inheriting theme animation: %1$s · %2$s · %3$dms',
							'designsetgo'
						),
						animationLabel(themeDefault.entrance || themeDefault.exit),
						themeDefault.trigger,
						themeDefault.duration
					)}
				</Notice>
			)}

			{mode === 'inherit' && !themeDefault && (
				<Notice status="info" isDismissible={false}>
					{__(
						'No theme animation for this block type.',
						'designsetgo'
					)}
				</Notice>
			)}

			{mode === 'custom' && (
				<>
					<SelectControl
						label={__('Entrance Animation', 'designsetgo')}
						value={dsgoEntranceAnimation}
						options={[
							{ label: __('None', 'designsetgo'), value: '' },
							...ANIMATION_TYPES.entrance,
						]}
						onChange={(value) =>
							setAttributes({ dsgoEntranceAnimation: value })
						}
						help={__('Animation when block appears', 'designsetgo')}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Exit Animation (Optional)', 'designsetgo')}
						value={dsgoExitAnimation}
						options={[
							{ label: __('None', 'designsetgo'), value: '' },
							...ANIMATION_TYPES.exit,
						]}
						onChange={(value) => {
							if (value && dsgoAnimationTrigger === 'scroll') {
								setAttributes({
									dsgoExitAnimation: value,
									dsgoAnimationOnce: false,
								});
							} else {
								setAttributes({ dsgoExitAnimation: value });
							}
						}}
						help={__(
							'Animation when block disappears',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Animation Trigger', 'designsetgo')}
						value={dsgoAnimationTrigger}
						options={ANIMATION_TRIGGERS}
						onChange={(value) =>
							setAttributes({ dsgoAnimationTrigger: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Duration', 'designsetgo')}
						value={dsgoAnimationDuration}
						options={ANIMATION_DURATIONS}
						onChange={(value) =>
							setAttributes({
								dsgoAnimationDuration: parseInt(value, 10),
							})
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<RangeControl
						label={__('Delay (ms)', 'designsetgo')}
						value={dsgoAnimationDelay}
						onChange={(value) =>
							setAttributes({ dsgoAnimationDelay: value })
						}
						min={0}
						max={3000}
						step={100}
						help={__(
							'Delay before animation starts',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Easing', 'designsetgo')}
						value={dsgoAnimationEasing}
						options={ANIMATION_EASINGS}
						onChange={(value) =>
							setAttributes({ dsgoAnimationEasing: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					{dsgoAnimationTrigger === 'scroll' && (
						<>
							<RangeControl
								label={__(
									'Viewport Offset (px)',
									'designsetgo'
								)}
								value={dsgoAnimationOffset}
								onChange={(value) =>
									setAttributes({
										dsgoAnimationOffset: value,
									})
								}
								min={0}
								max={500}
								step={10}
								help={__(
									'Distance from viewport to trigger animation',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							{dsgoExitAnimation && (
								<Notice status="info" isDismissible={false}>
									{__(
										'Exit animations require repeating behavior. "Animate Once" is disabled.',
										'designsetgo'
									)}
								</Notice>
							)}

							<ToggleControl
								label={__('Animate Once', 'designsetgo')}
								checked={dsgoAnimationOnce}
								onChange={(value) =>
									setAttributes({ dsgoAnimationOnce: value })
								}
								disabled={!!dsgoExitAnimation}
								help={
									dsgoExitAnimation
										? __(
												'Disabled when exit animation is set',
												'designsetgo'
											)
										: __(
												'Only animate the first time block enters viewport',
												'designsetgo'
											)
								}
								__nextHasNoMarginBottom
							/>
						</>
					)}

					{!dsgoEntranceAnimation && !dsgoExitAnimation && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'Please select at least one animation type.',
								'designsetgo'
							)}
						</Notice>
					)}
				</>
			)}
		</PanelBody>
	);
}
```

- [ ] **Step 6: Localize the resolved defaults to the editor**

In `includes/core/class-assets.php`, `localize_extension_settings()`, add the two keys to the `wp_localize_script` array (after `defaultIconButtonHover`):

```php
					'blockAnimations'        => array_values(
						\DesignSetGo\Animation_Defaults::get_effective()['map']
							? self::block_animations_for_editor()
							: array()
					),
					'blockAnimationsEnabled' => \DesignSetGo\Animation_Defaults::get_effective()['enabled'],
```

Then add a small private helper to that class that flattens the map back to a list the JS resolver expects (it keys by `block`), placed near `localize_extension_settings()`:

```php
	/**
	 * Flatten the resolved animation-defaults map to a list for the editor.
	 *
	 * @return array<int, array> List of entries with a `block` key.
	 */
	private static function block_animations_for_editor() {
		$map  = \DesignSetGo\Animation_Defaults::get_effective()['map'];
		$list = array();
		foreach ( $map as $block => $config ) {
			$list[] = array_merge( array( 'block' => $block ), $config );
		}
		return $list;
	}
```

> Simplify: replace the two localize lines above with a single precomputed variable to avoid calling `get_effective()` three times. At the top of `localize_extension_settings()` add `$anim = \DesignSetGo\Animation_Defaults::get_effective();` and use `'blockAnimations' => self::block_animations_for_editor(),` and `'blockAnimationsEnabled' => $anim['enabled'],`. (The helper recomputes; acceptable — `get_effective()` reads cached option/global data.)

Final localize additions (use this form):

```php
					'blockAnimations'        => self::block_animations_for_editor(),
					'blockAnimationsEnabled' => \DesignSetGo\Animation_Defaults::get_effective()['enabled'],
```

- [ ] **Step 7: Run to verify it passes**

Run: `npm run test:unit -- animation-panel-tristate`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add src/extensions/block-animations/resolve-default.js src/extensions/block-animations/editor.js src/extensions/block-animations/components/AnimationPanel.js includes/core/class-assets.php tests/unit/extensions/animation-panel-tristate.test.js
git commit -m "feat(animations): editor tri-state control + inherited theme indicator"
```

---

## Task 7: Admin Settings repeater UI (JS)

Add the per-block-type defaults editor to the plugin Settings → Animations panel.

**Files:**
- Modify: `src/admin/components/settings-panels/AnimationsPanel.js`
- Test: `tests/unit/components/animations-panel-defaults.test.js` (create)

**Interfaces:**
- Consumes: `settings.animations.block_animations_enabled` and `settings.animations.block_animations` (Task 2); `updateSetting(group, key, value)` (existing prop).
- Produces: no new exports; edits the option via the existing `updateSetting`.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/components/animations-panel-defaults.test.js`:

```js
/**
 * Admin AnimationsPanel — per-block-type defaults repeater.
 */
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({ __: (t) => t }));

jest.mock('@wordpress/components', () => {
	const React = require('react');
	return {
		Card: ({ children }) => <div>{children}</div>,
		CardHeader: ({ children }) => <div>{children}</div>,
		CardBody: ({ children }) => <div>{children}</div>,
		ToggleControl: ({ label, checked, onChange }) => (
			<label>
				{label}
				<input
					type="checkbox"
					checked={!!checked}
					onChange={(e) => onChange(e.target.checked)}
				/>
			</label>
		),
		RangeControl: ({ label }) => <label>{label}</label>,
		SelectControl: ({ label, value, options = [], onChange }) => (
			<label>
				{label}
				<select
					value={value}
					onChange={(e) => onChange(e.target.value)}
				>
					{options.map((o) => (
						<option key={o.value} value={o.value}>
							{o.label}
						</option>
					))}
				</select>
			</label>
		),
		TextControl: ({ label, value, onChange }) => (
			<label>
				{label}
				<input
					value={value || ''}
					onChange={(e) => onChange(e.target.value)}
				/>
			</label>
		),
		Button: ({ children, onClick }) => (
			<button onClick={onClick}>{children}</button>
		),
	};
});

import AnimationsPanel from '../../../src/admin/components/settings-panels/AnimationsPanel';

describe('Admin AnimationsPanel — block-type defaults', () => {
	it('shows the master toggle and adds a row when enabled', () => {
		const updateSetting = jest.fn();
		const settings = {
			animations: {
				enable_animations: true,
				block_animations_enabled: true,
				block_animations: [],
			},
		};

		render(
			<AnimationsPanel
				settings={settings}
				updateSetting={updateSetting}
			/>
		);

		fireEvent.click(
			screen.getByRole('button', { name: /Add block type/i })
		);

		expect(updateSetting).toHaveBeenCalledWith(
			'animations',
			'block_animations',
			[expect.objectContaining({ block: '', entrance: 'fadeInUp' })]
		);
	});
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `npm run test:unit -- animations-panel-defaults`
Expected: FAIL — no "Add block type" button.

- [ ] **Step 3: Add the repeater to the admin panel**

In `src/admin/components/settings-panels/AnimationsPanel.js`:

1. Extend the imports:

```js
import {
	Card,
	CardHeader,
	CardBody,
	ToggleControl,
	RangeControl,
	SelectControl,
	TextControl,
	Button,
} from '@wordpress/components';
```

2. Add these constants above the component (mirror `constants.js`):

```js
const ENTRANCE_OPTIONS = [
	{ label: __('Fade In', 'designsetgo'), value: 'fadeIn' },
	{ label: __('Fade In Up', 'designsetgo'), value: 'fadeInUp' },
	{ label: __('Fade In Down', 'designsetgo'), value: 'fadeInDown' },
	{ label: __('Fade In Left', 'designsetgo'), value: 'fadeInLeft' },
	{ label: __('Fade In Right', 'designsetgo'), value: 'fadeInRight' },
	{ label: __('Slide In Up', 'designsetgo'), value: 'slideInUp' },
	{ label: __('Slide In Down', 'designsetgo'), value: 'slideInDown' },
	{ label: __('Slide In Left', 'designsetgo'), value: 'slideInLeft' },
	{ label: __('Slide In Right', 'designsetgo'), value: 'slideInRight' },
	{ label: __('Zoom In', 'designsetgo'), value: 'zoomIn' },
	{ label: __('Bounce In', 'designsetgo'), value: 'bounceIn' },
	{ label: __('Flip In X', 'designsetgo'), value: 'flipInX' },
	{ label: __('Flip In Y', 'designsetgo'), value: 'flipInY' },
];

const TRIGGER_OPTIONS = [
	{ label: __('On Scroll', 'designsetgo'), value: 'scroll' },
	{ label: __('On Load', 'designsetgo'), value: 'load' },
	{ label: __('On Hover', 'designsetgo'), value: 'hover' },
	{ label: __('On Click', 'designsetgo'), value: 'click' },
];

const DURATION_OPTIONS = [
	{ label: __('Fast (300ms)', 'designsetgo'), value: 300 },
	{ label: __('Normal (600ms)', 'designsetgo'), value: 600 },
	{ label: __('Slow (1000ms)', 'designsetgo'), value: 1000 },
	{ label: __('Very Slow (2000ms)', 'designsetgo'), value: 2000 },
];

const NEW_ROW = {
	block: '',
	entrance: 'fadeInUp',
	exit: '',
	trigger: 'scroll',
	duration: 600,
	delay: 0,
	easing: 'ease-out',
	offset: 100,
	once: true,
};
```

3. Inside the component, before the closing `</CardBody>`, add the block-type defaults section. It renders whenever `enable_animations` is on (it lives inside the existing `settings?.animations?.enable_animations && (...)` block — add it just before that block's closing `</div>`):

```jsx
						<div className="designsetgo-settings-section">
							<h3 className="designsetgo-section-heading">
								{__(
									'Theme Animation Defaults',
									'designsetgo'
								)}
							</h3>

							<ToggleControl
								label={__(
									'Enable theme animation defaults',
									'designsetgo'
								)}
								help={__(
									'Automatically animate every block of a chosen type. Individual blocks can override or opt out.',
									'designsetgo'
								)}
								checked={
									settings?.animations
										?.block_animations_enabled || false
								}
								onChange={(value) =>
									updateSetting(
										'animations',
										'block_animations_enabled',
										value
									)
								}
								__nextHasNoMarginBottom
							/>

							{settings?.animations?.block_animations_enabled && (
								<div className="designsetgo-block-animations">
									{(
										settings?.animations
											?.block_animations || []
									).map((row, index) => {
										const list =
											settings.animations
												.block_animations;
										const update = (patch) => {
											const next = list.map((r, i) =>
												i === index
													? { ...r, ...patch }
													: r
											);
											updateSetting(
												'animations',
												'block_animations',
												next
											);
										};
										const remove = () =>
											updateSetting(
												'animations',
												'block_animations',
												list.filter(
													(r, i) => i !== index
												)
											);

										return (
											<div
												key={index}
												className="designsetgo-block-animations__row"
											>
												<TextControl
													label={__(
														'Block type',
														'designsetgo'
													)}
													value={row.block}
													placeholder="core/button"
													onChange={(value) =>
														update({
															block: value,
														})
													}
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												<SelectControl
													label={__(
														'Entrance',
														'designsetgo'
													)}
													value={row.entrance}
													options={ENTRANCE_OPTIONS}
													onChange={(value) =>
														update({
															entrance: value,
														})
													}
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												<SelectControl
													label={__(
														'Trigger',
														'designsetgo'
													)}
													value={row.trigger}
													options={TRIGGER_OPTIONS}
													onChange={(value) =>
														update({
															trigger: value,
														})
													}
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												<SelectControl
													label={__(
														'Duration',
														'designsetgo'
													)}
													value={row.duration}
													options={DURATION_OPTIONS}
													onChange={(value) =>
														update({
															duration:
																parseInt(
																	value,
																	10
																),
														})
													}
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												<Button
													isDestructive
													variant="tertiary"
													onClick={remove}
												>
													{__(
														'Remove',
														'designsetgo'
													)}
												</Button>
											</div>
										);
									})}

									<Button
										variant="secondary"
										onClick={() =>
											updateSetting(
												'animations',
												'block_animations',
												[
													...(settings.animations
														.block_animations ||
														[]),
													{ ...NEW_ROW },
												]
											)
										}
									>
										{__(
											'Add block type',
											'designsetgo'
										)}
									</Button>
								</div>
							)}
						</div>
```

- [ ] **Step 4: Run to verify it passes**

Run: `npm run test:unit -- animations-panel-defaults`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/admin/components/settings-panels/AnimationsPanel.js tests/unit/components/animations-panel-defaults.test.js
git commit -m "feat(admin): per-block-type animation defaults repeater UI"
```

---

## Task 8: Build, full test sweep, CHANGELOG

Verify the whole feature builds and all tests/linters pass, and document it.

**Files:**
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Add the CHANGELOG entry**

Under the top "New Features" section of `CHANGELOG.md` (match the existing format):

```markdown
- **Theme animation defaults**: set an entrance animation per block type (e.g. all Buttons fade in) once, in Settings → DesignSetGo → Animations or in theme.json (`settings.custom.designsetgo.blockAnimations`). Every block of that type inherits it automatically; individual blocks can override (Custom) or opt out (Off).
```

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: completes with no errors.

- [ ] **Step 3: Verify the frontend/editor bundles carry the new code**

Run: `grep -c "dsgoAnimationOptOut" build/*.js`
Expected: at least one match (`> 0`).

- [ ] **Step 4: Run all JS unit tests**

Run: `npm run test:unit`
Expected: PASS (no new failures).

- [ ] **Step 5: Run all PHP tests**

Run: `composer run-script test`
Expected: PASS.

- [ ] **Step 6: Lint**

Run: `npm run lint:js && npm run lint:css && npm run lint:php`
Expected: no errors.

- [ ] **Step 7: Manual smoke test (frontend opt-out behavior)**

1. `npm run build`, load the wp-env dev site.
2. Settings → DesignSetGo → Animations → enable "theme animation defaults", add `core/button → Fade In Up`, save.
3. On a page with an existing button that was never touched, confirm on the **front end** the button fades in on scroll.
4. Edit one button → Animations → set **Off**; confirm that button no longer animates while others still do.
5. Edit another button → **Custom** → Zoom In; confirm it zooms (overrides the theme default) and its stored markup is what changed (not others').

- [ ] **Step 8: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs(changelog): add theme animation defaults feature"
```

---

## Self-review

**Spec coverage:**
- Data model (theme.json array + admin option) → Tasks 2, 3.
- theme.json first-class source → Task 3 (`wp_get_global_settings` read) + tests.
- Tri-state / no-deprecation backward compat → Task 5 (`dsgoAnimationOptOut`) + Task 1/4 (Custom path unchanged) + Task 8 round-trip smoke; the invariant is that the Custom save path is untouched, so no deprecation is introduced.
- Opt-out automatic (render injector) → Task 4.
- Exact-then-wildcard resolution → Task 3.
- Editor indicator (not playback) → Task 6.
- Admin repeater UI → Task 7.
- Master gate default off → Task 2 defaults.
- Precedence (admin over theme.json; either gate enables) → Task 3 + tests.

**Placeholder scan:** no TBD/TODO; every code step has complete code; every command has expected output.

**Type consistency:** `designsetgo_get_animation_parts()` returns `{classes: string[], attrs: map}` and is consumed identically in Task 4. `Animation_Defaults::get_effective()`/`resolve_for_block()` shapes match between Tasks 3, 4, 6-localization. `resolveBlockAnimationDefault()` returns the same field set the PHP `normalize_entry()` produces. Option keys `block_animations_enabled` / `block_animations` are identical across Tasks 2, 3, 6, 7.

**Note on a spec deviation:** `class-global-styles.php` is intentionally NOT modified (see "Deviation from spec" above); theme.json authorability is preserved via direct `wp_get_global_settings` reads.

## Open item to confirm during execution

Precedence: **admin option overrides theme.json** for the same block type (approved default). To flip so theme.json wins, in `Animation_Defaults::get_effective()` swap the merge order — iterate `array( $admin_list, $global_list )` instead of `array( $global_list, $admin_list )`.

<?php
/**
 * Tests for StyleBinding class.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\StyleBinding;

/**
 * Style Binding Test Case
 */
class StyleBindingTest extends WP_UnitTestCase {

	/**
	 * StyleBinding instance.
	 *
	 * @var StyleBinding
	 */
	private StyleBinding $sb;

	/**
	 * Set up test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->sb = new StyleBinding();
	}

	/**
	 * Build a minimal block array for testing.
	 *
	 * @param array $attrs Block attributes.
	 * @return array
	 */
	private function make_block( array $attrs ): array {
		return array(
			'blockName'   => 'core/paragraph',
			'attrs'       => $attrs,
			'innerBlocks' => array(),
			'innerHTML'   => '',
		);
	}

	/**
	 * No binding returns HTML unchanged.
	 */
	public function test_no_binding_returns_html_unchanged() {
		$html = '<p class="wp-block">Hello</p>';
		$this->assertSame( $html, $this->sb->apply_style_bindings( $html, $this->make_block( array() ) ) );
	}

	/**
	 * Invalid CSS property name is skipped.
	 */
	public function test_invalid_css_prop_is_skipped() {
		$html  = '<p>Hello</p>';
		$block = $this->make_block(
			array(
				'dsgoStyleBinding' => array(
					'bad prop!' => array(
						'source' => 'designsetgo/post-meta',
						'args'   => array( 'key' => 'x' ),
					),
				),
			)
		);
		$this->assertStringNotContainsString( 'bad prop!', $this->sb->apply_style_bindings( $html, $block ) );
	}

	/**
	 * Value containing dangerous URL is rejected.
	 */
	public function test_dangerous_value_is_rejected() {
		add_filter(
			'designsetgo_style_binding_resolve',
			function ( $val, $source, $args ) {
				return 'url(javascript:alert(1))';
			},
			10,
			3
		);
		$html  = '<div class="wp-block">X</div>';
		$block = $this->make_block(
			array(
				'dsgoStyleBinding' => array(
					'--color' => array(
						'source' => 'designsetgo/post-meta',
						'args'   => array( 'key' => 'c' ),
					),
				),
			)
		);
		$result = $this->sb->apply_style_bindings( $html, $block );
		$this->assertStringNotContainsString( 'javascript', $result );
		remove_all_filters( 'designsetgo_style_binding_resolve' );
	}

	/**
	 * Valid binding injects style attribute.
	 */
	public function test_valid_binding_injects_style_attribute() {
		add_filter(
			'designsetgo_style_binding_resolve',
			function ( $val, $source, $args ) {
				return '#ff0000';
			},
			10,
			3
		);
		$html  = '<div class="wp-block">X</div>';
		$block = $this->make_block(
			array(
				'dsgoStyleBinding' => array(
					'--brand' => array(
						'source' => 'designsetgo/post-meta',
						'args'   => array( 'key' => 'color' ),
					),
				),
			)
		);
		$result = $this->sb->apply_style_bindings( $html, $block );
		$this->assertStringContainsString( '--brand:#ff0000', $result );
		remove_all_filters( 'designsetgo_style_binding_resolve' );
	}

	/**
	 * Resolver passes the iterated post id and the `key` arg to source plugins.
	 *
	 * Locks in the contract that every built-in source reads `args['key']` (not
	 * a per-source synonym like `id`/`name`/`field`) and resolves against the
	 * current post id from `$GLOBALS['designsetgo_parent_stack']`. Regression
	 * for editor → resolver mismatch and JetEngine dropping the post id.
	 */
	public function test_resolver_uses_key_arg_and_iterated_post_id() {
		$post_id              = self::factory()->post->create();
		$captured             = array();
		$GLOBALS['designsetgo_parent_stack'] = array(
			array( 'id' => $post_id, 'type' => 'post' ),
		);

		add_filter(
			'designsetgo_style_binding_resolve',
			function ( $val, $source, $args ) use ( &$captured ) {
				$captured[ $source ] = $args;
				return $val ?? 'sentinel';
			},
			10,
			3
		);

		foreach ( array( 'designsetgo/metabox', 'designsetgo/pods', 'designsetgo/jetengine' ) as $source ) {
			$block = $this->make_block(
				array(
					'dsgoStyleBinding' => array(
						'--brand' => array(
							'source' => $source,
							'args'   => array( 'key' => 'brand_color' ),
						),
					),
				)
			);
			$this->sb->apply_style_bindings( '<p>X</p>', $block );
		}

		unset( $GLOBALS['designsetgo_parent_stack'] );
		remove_all_filters( 'designsetgo_style_binding_resolve' );

		foreach ( array( 'designsetgo/metabox', 'designsetgo/pods', 'designsetgo/jetengine' ) as $source ) {
			$this->assertSame( 'brand_color', $captured[ $source ]['key'] ?? null, "Source $source must receive args.key" );
		}
	}

	/**
	 * Password-protected posts must not leak meta values via style bindings.
	 * Mirrors the gate the v2.4 block-bindings adapter applies.
	 */
	public function test_password_protected_post_blocks_resolution() {
		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
		update_post_meta( $post_id, 'brand_color', '#ff0000' );

		$GLOBALS['designsetgo_parent_stack'] = array(
			array( 'id' => $post_id, 'type' => 'post' ),
		);

		$block = $this->make_block(
			array(
				'dsgoStyleBinding' => array(
					'--brand' => array(
						'source' => 'designsetgo/post-meta',
						'args'   => array( 'key' => 'brand_color' ),
					),
				),
			)
		);
		$result = $this->sb->apply_style_bindings( '<div>X</div>', $block );

		unset( $GLOBALS['designsetgo_parent_stack'] );

		$this->assertStringNotContainsString( '#ff0000', $result );
		$this->assertStringNotContainsString( '--brand', $result );
	}

	/**
	 * Protected meta keys (prefixed with `_`) must be rejected.
	 */
	public function test_protected_meta_key_blocks_resolution() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $post_id, '_internal_color', '#ff0000' );

		$GLOBALS['designsetgo_parent_stack'] = array(
			array( 'id' => $post_id, 'type' => 'post' ),
		);

		$block = $this->make_block(
			array(
				'dsgoStyleBinding' => array(
					'--brand' => array(
						'source' => 'designsetgo/post-meta',
						'args'   => array( 'key' => '_internal_color' ),
					),
				),
			)
		);
		$result = $this->sb->apply_style_bindings( '<div>X</div>', $block );

		unset( $GLOBALS['designsetgo_parent_stack'] );

		$this->assertStringNotContainsString( '#ff0000', $result );
	}

	/**
	 * CSS values containing `{`, `}`, or `data:` are rejected as injection
	 * vectors, in addition to `url(`, `expression(`, `javascript:`, and `;`.
	 */
	public function test_blocklist_rejects_braces_and_data_uri() {
		$dangerous = array(
			'red} body { display:none',
			'expression(alert(1))',
			'javascript:alert(1)',
			'data:image/svg+xml;base64,PHN2Zy8+',
			'red; display:none',
			'{color:red}',
		);
		foreach ( $dangerous as $value ) {
			add_filter( 'designsetgo_style_binding_resolve', fn() => $value, 10, 3 );
			$block = $this->make_block(
				array(
					'dsgoStyleBinding' => array(
						'--brand' => array(
							'source' => 'designsetgo/post-meta',
							'args'   => array( 'key' => 'c' ),
						),
					),
				)
			);
			$result = $this->sb->apply_style_bindings( '<div>X</div>', $block );
			remove_all_filters( 'designsetgo_style_binding_resolve' );
			$this->assertStringNotContainsString( $value, $result, "Should reject: $value" );
		}
	}

	/**
	 * Vendor-prefixed and digit-bearing CSS property names are accepted.
	 * Regression: previous regex excluded `-webkit-text-fill-color` and
	 * any property with a digit.
	 */
	public function test_vendor_prefixed_and_digit_props_accepted() {
		add_filter( 'designsetgo_style_binding_resolve', fn() => 'red', 10, 3 );
		$block = $this->make_block(
			array(
				'dsgoStyleBinding' => array(
					'-webkit-text-fill-color' => array(
						'source' => 'designsetgo/post-meta',
						'args'   => array( 'key' => 'c' ),
					),
				),
			)
		);
		$result = $this->sb->apply_style_bindings( '<div>X</div>', $block );
		remove_all_filters( 'designsetgo_style_binding_resolve' );
		$this->assertStringContainsString( '-webkit-text-fill-color:red', $result );
	}

	/**
	 * Existing style attribute is preserved when new styles are injected.
	 */
	public function test_existing_style_attribute_is_preserved() {
		add_filter( 'designsetgo_style_binding_resolve', fn() => 'blue', 10, 3 );
		$html  = '<div style="color:red">X</div>';
		$block = $this->make_block(
			array(
				'dsgoStyleBinding' => array(
					'background-color' => array(
						'source' => 'designsetgo/post-meta',
						'args'   => array( 'key' => 'bg' ),
					),
				),
			)
		);
		$result = $this->sb->apply_style_bindings( $html, $block );
		$this->assertStringContainsString( 'color:red', $result );
		$this->assertStringContainsString( 'background-color:blue', $result );
		remove_all_filters( 'designsetgo_style_binding_resolve' );
	}
}

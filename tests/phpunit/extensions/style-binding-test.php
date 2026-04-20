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

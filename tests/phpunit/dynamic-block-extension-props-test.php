<?php
/**
 * Dynamic blocks must receive extraProps-based extension output at render time.
 *
 * @package DesignSetGo
 * @group   extensions
 */

/**
 * Render-time mirror of responsive / max-width / grid / hover save props.
 *
 * @group extensions
 */
class DesignSetGo_Dynamic_Block_Extension_Props_Test extends WP_UnitTestCase {

	/**
	 * Class under test.
	 *
	 * @var \DesignSetGo\Dynamic_Block_Extension_Props
	 */
	private $props;

	/**
	 * Register a dynamic and a static block type.
	 */
	public function set_up() {
		parent::set_up();
		$this->props = new \DesignSetGo\Dynamic_Block_Extension_Props();

		register_block_type(
			'dsgotest/ext-dynamic',
			array(
				'render_callback' => static function () {
					return '<div class="wp-block-dsgotest-ext-dynamic" style="color:red">x</div>';
				},
			)
		);
		register_block_type( 'dsgotest/ext-static', array() );
	}

	/**
	 * Drop the test block types.
	 */
	public function tear_down() {
		unregister_block_type( 'dsgotest/ext-dynamic' );
		unregister_block_type( 'dsgotest/ext-static' );
		parent::tear_down();
	}

	/**
	 * Run the filter.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Attributes.
	 * @param string $html  Rendered HTML.
	 * @return string
	 */
	private function run_filter( $name, $attrs, $html = '<div class="wp-block-dsgotest-ext-dynamic" style="color:red">x</div>' ) {
		return $this->props->inject(
			$html,
			array(
				'blockName' => $name,
				'attrs'     => $attrs,
			)
		);
	}

	/**
	 * Hide classes are added.
	 */
	public function test_hide_classes_added_to_dynamic_block() {
		$out = $this->run_filter( 'dsgotest/ext-dynamic', array( 'dsgoHideOnMobile' => true, 'dsgoHideOnDesktop' => true ) );
		$this->assertStringContainsString( 'dsgo-hide-mobile', $out );
		$this->assertStringContainsString( 'dsgo-hide-desktop', $out );
		$this->assertStringNotContainsString( 'dsgo-hide-tablet', $out );
	}

	/**
	 * Max width appends to the existing style.
	 */
	public function test_max_width_applied_and_style_preserved() {
		$out = $this->run_filter( 'dsgotest/ext-dynamic', array( 'dsgoMaxWidth' => '300px' ) );
		$this->assertStringContainsString( 'dsgo-has-max-width', $out );
		$this->assertStringContainsString( 'color:red; max-width:300px;margin-left:auto;margin-right:auto;', $out );
	}

	/**
	 * Unsafe widths are rejected.
	 */
	public function test_unsafe_max_width_rejected() {
		$out = $this->run_filter( 'dsgotest/ext-dynamic', array( 'dsgoMaxWidth' => '1px;background:url(x)' ) );
		$this->assertStringNotContainsString( 'max-width', $out );
	}

	/**
	 * Grid span, row span, mobile order and hover.
	 */
	public function test_grid_and_hover() {
		$out = $this->run_filter(
			'dsgotest/ext-dynamic',
			array(
				'dsgoColumnSpan'  => 3,
				'dsgoMobileOrder' => 0,
			)
		);
		$this->assertStringContainsString( 'grid-column:span 3;', $out );
		$this->assertStringContainsString( '--dsgo-mobile-order:0;', $out );

		$out = $this->run_filter( 'core/group', array( 'dsgoHoverEffect' => 'lift' ), '<div class="wp-block-group">x</div>' );
		// core/group is static: untouched.
		$this->assertStringNotContainsString( 'dsgo-hover-effect', $out );
	}

	/**
	 * Static blocks are never modified.
	 */
	public function test_static_block_untouched() {
		$html = '<div class="wp-block-dsgotest-ext-static dsgo-hide-mobile">x</div>';
		$out  = $this->run_filter( 'dsgotest/ext-static', array( 'dsgoHideOnMobile' => true, 'dsgoMaxWidth' => '300px' ), $html );
		$this->assertSame( $html, $out );
	}

	/**
	 * Running twice does not duplicate anything.
	 */
	public function test_idempotent() {
		$attrs = array( 'dsgoHideOnMobile' => true, 'dsgoMaxWidth' => '300px', 'dsgoColumnSpan' => 2 );
		$once  = $this->run_filter( 'dsgotest/ext-dynamic', $attrs );
		$twice = $this->run_filter( 'dsgotest/ext-dynamic', $attrs, $once );
		$this->assertSame( $once, $twice );
	}

	/**
	 * Real render through render_block with the plugin hook.
	 */
	public function test_core_search_and_latest_posts_render() {
		$out = do_blocks( '<!-- wp:latest-posts {"dsgoHideOnMobile":true,"dsgoMaxWidth":"300px"} /-->' );
		$this->assertStringContainsString( 'dsgo-hide-mobile', $out );
		$this->assertStringContainsString( 'max-width:300px', $out );
	}
}

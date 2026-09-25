<?php
/**
 * Tests for per-feature extension frontend bundles.
 *
 * @group assets
 */

/**
 * @group assets
 */
class DesignSetGo_Extension_Bundles_Test extends WP_UnitTestCase {

	/**
	 * @var \DesignSetGo\Extension_Bundles
	 */
	private $bundles;

	/**
	 * Registries in place before the test.
	 *
	 * @var array
	 */
	private $original = array();

	public function set_up() {
		parent::set_up();
		// Fresh registries, so enqueues from other tests (or this plugin's own
		// render_block hooks) can't satisfy or pollute the assertions.
		$this->original        = array( $GLOBALS['wp_scripts'] ?? null, $GLOBALS['wp_styles'] ?? null );
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
		$this->bundles         = new \DesignSetGo\Extension_Bundles();
		$this->bundles->register();
	}

	public function tear_down() {
		list( $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'] ) = $this->original;
		parent::tear_down();
	}

	public function test_manifest_needles_are_non_empty_strings() {
		$features = \DesignSetGo\Extension_Bundles::get_features();

		$this->assertNotEmpty( $features );
		foreach ( $features as $name => $feature ) {
			$this->assertNotEmpty( $feature['needles'], $name );
			$this->assertTrue( ! empty( $feature['scripts'] ) || ! empty( $feature['style'] ), "$name ships nothing" );
			foreach ( array_merge( $feature['scripts'] ?? array(), array_filter( array( $feature['style'] ?? '' ) ) ) as $source ) {
				$this->assertFileExists( DESIGNSETGO_PATH . $source, "$name source" );
			}
		}
	}

	public function test_every_manifest_bundle_is_built() {
		foreach ( \DesignSetGo\Extension_Bundles::get_features() as $name => $feature ) {
			if ( ! empty( $feature['scripts'] ) ) {
				$this->assertFileExists( DESIGNSETGO_PATH . "build/extensions/{$name}/frontend.js", 'Run `npm run build`.' );
			}
			if ( ! empty( $feature['style'] ) ) {
				$this->assertFileExists( DESIGNSETGO_PATH . "build/extensions/{$name}/style.css", 'Run `npm run build`.' );
			}
		}
	}

	public function test_enqueues_only_the_features_present() {
		$this->bundles->maybe_enqueue( '<div class="wp-block-group dsgo-hover-effect dsgo-hover-effect--lift">x</div>', array( 'blockName' => 'core/group' ) );

		$this->assertTrue( wp_style_is( 'designsetgo-ext-hover-effects', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'designsetgo-ext-hover-effects', 'enqueued' ), 'CSS-only feature has no script.' );
		$this->assertFalse( wp_style_is( 'designsetgo-ext-block-animations', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'designsetgo-ext-vertical-scroll-parallax', 'enqueued' ) );
	}

	public function test_script_and_style_features_enqueue_both() {
		$this->bundles->maybe_enqueue( '<p class="has-dsgo-animation" data-dsgo-animation-enabled="true">x</p>', array( 'blockName' => 'core/paragraph' ) );

		$this->assertTrue( wp_script_is( 'designsetgo-ext-block-animations', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'designsetgo-ext-block-animations', 'enqueued' ) );
	}

	public function test_query_item_template_is_scanned() {
		$block = parse_blocks( '<!-- wp:designsetgo/query --><!-- wp:group {"className":"dsgo-clickable"} --><div class="wp-block-group dsgo-clickable"></div><!-- /wp:group --><!-- /wp:designsetgo/query -->' )[0];

		// First paint rendered no items (e.g. a filter matched nothing).
		$this->bundles->maybe_enqueue( '<div class="wp-block-designsetgo-query"></div>', $block );

		$this->assertTrue( wp_script_is( 'designsetgo-ext-clickable-group', 'enqueued' ) );
	}

	public function test_query_template_block_assets_are_enqueued() {
		wp_register_script( 'designsetgo-tabs-view-script', 'https://example.com/tabs.js', array(), '1', true );
		wp_register_style( 'designsetgo-tabs-style', 'https://example.com/tabs.css', array(), '1' );
		$block = parse_blocks( '<!-- wp:designsetgo/query --><!-- wp:group --><div class="wp-block-group"><!-- wp:designsetgo/tabs /--></div><!-- /wp:group --><!-- /wp:designsetgo/query -->' )[0];

		// First paint rendered no items, so Tabs never rendered.
		$this->bundles->enqueue_query_template_blocks( '<div class="wp-block-designsetgo-query"></div>', $block );

		$this->assertTrue( wp_script_is( 'designsetgo-tabs-view-script', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'designsetgo-tabs-style', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'designsetgo-form-builder-view-script', 'enqueued' ), 'Only blocks in the template.' );
	}

	public function test_runs_after_render_time_needle_injectors() {
		$this->assertSame( 11, has_filter( 'render_block', array( $this->bundles, 'maybe_enqueue' ) ) );
	}
}

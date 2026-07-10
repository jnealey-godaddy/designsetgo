<?php
/**
 * Test Plugin Initialization
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * Tests for basic plugin functionality.
 */
class Test_Plugin extends WP_UnitTestCase {

	/**
	 * Test that the plugin is loaded.
	 */
	public function test_plugin_loaded() {
		$this->assertTrue( class_exists( 'DesignSetGo\Plugin' ) );
	}

	/**
	 * Test that plugin constants are defined.
	 */
	public function test_plugin_constants() {
		$this->assertTrue( defined( 'DESIGNSETGO_VERSION' ) );
		$this->assertTrue( defined( 'DESIGNSETGO_PATH' ) );
		$this->assertTrue( defined( 'DESIGNSETGO_URL' ) );
		$this->assertTrue( defined( 'DESIGNSETGO_FILE' ) );
		$this->assertTrue( defined( 'DESIGNSETGO_BASENAME' ) );
	}

	/**
	 * Test singleton pattern.
	 */
	public function test_singleton_instance() {
		$instance1 = \DesignSetGo\Plugin::instance();
		$instance2 = \DesignSetGo\Plugin::instance();

		// Should return the same instance.
		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test plugin components are initialized.
	 */
	public function test_components_initialized() {
		$instance = \DesignSetGo\Plugin::instance();

		// Check that key components are loaded.
		$this->assertNotNull( $instance->assets );
		$this->assertNotNull( $instance->blocks );
		$this->assertNotNull( $instance->custom_css_renderer );
		$this->assertNotNull( $instance->section_styles );
	}

	/**
	 * Test that plugin version is valid.
	 */
	public function test_valid_version() {
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+\.\d+$/',
			DESIGNSETGO_VERSION,
			'Version should follow semantic versioning (X.Y.Z)'
		);
	}

	/**
	 * Test that block category is registered.
	 */
	public function test_block_category() {
		$categories = get_block_categories( get_post() );
		$category_slugs = wp_list_pluck( $categories, 'slug' );

		$this->assertContains(
			'designsetgo',
			$category_slugs,
			'DesignSetGo block category should be registered'
		);
	}

	/**
	 * Test that the safe_style_css filter is registered.
	 */
	public function test_safe_style_css_filter_registered() {
		$this->assertGreaterThan(
			0,
			has_filter( 'safe_style_css', array( \DesignSetGo\Plugin::instance(), 'allow_block_style_properties' ) ),
			'safe_style_css filter should be registered'
		);
	}

	/**
	 * Test that the safecss_filter_attr_allow_css filter is registered.
	 */
	public function test_safecss_filter_attr_allow_css_filter_registered() {
		$this->assertGreaterThan(
			0,
			has_filter( 'safecss_filter_attr_allow_css', array( \DesignSetGo\Plugin::instance(), 'allow_block_css_functions' ) ),
			'safecss_filter_attr_allow_css filter should be registered'
		);
	}

	/**
	 * Test that block layout CSS properties survive wp_kses_post().
	 *
	 * Verifies the global safe_style_css filter allows display, gap,
	 * grid-template-columns, and flex properties that blocks use in
	 * their save() output.
	 */
	public function test_kses_preserves_layout_properties() {
		$html = '<div style="display:flex;gap:20px;row-gap:10px;column-gap:15px;grid-template-columns:repeat(3, 1fr)">test</div>';

		$result = wp_kses_post( $html );

		$this->assertStringContainsString( 'display:flex', $result, 'display should survive wp_kses_post' );
		$this->assertStringContainsString( 'gap:20px', $result, 'gap should survive wp_kses_post' );
		$this->assertStringContainsString( 'row-gap:10px', $result, 'row-gap should survive wp_kses_post' );
		$this->assertStringContainsString( 'column-gap:15px', $result, 'column-gap should survive wp_kses_post' );
		$this->assertStringContainsString( 'grid-template-columns:repeat(3, 1fr)', $result, 'grid-template-columns should survive wp_kses_post' );
	}

	/**
	 * Test that flex CSS properties survive wp_kses_post().
	 */
	public function test_kses_preserves_flex_properties() {
		$html = '<div style="flex:1;flex-direction:column;flex-wrap:wrap;flex-shrink:0;align-items:center;justify-content:space-between;align-self:stretch">test</div>';

		$result = wp_kses_post( $html );

		$this->assertStringContainsString( 'flex:1', $result, 'flex should survive wp_kses_post' );
		$this->assertStringContainsString( 'flex-direction:column', $result, 'flex-direction should survive wp_kses_post' );
		$this->assertStringContainsString( 'flex-wrap:wrap', $result, 'flex-wrap should survive wp_kses_post' );
		$this->assertStringContainsString( 'flex-shrink:0', $result, 'flex-shrink should survive wp_kses_post' );
		$this->assertStringContainsString( 'align-items:center', $result, 'align-items should survive wp_kses_post' );
		$this->assertStringContainsString( 'justify-content:space-between', $result, 'justify-content should survive wp_kses_post' );
		$this->assertStringContainsString( 'align-self:stretch', $result, 'align-self should survive wp_kses_post' );
	}

	/**
	 * Test that positioning CSS properties survive wp_kses_post().
	 */
	public function test_kses_preserves_positioning_properties() {
		$html = '<div style="position:absolute;left:-9999px;overflow:hidden">test</div>';

		$result = wp_kses_post( $html );

		$this->assertStringContainsString( 'position:absolute', $result, 'position should survive wp_kses_post' );
		$this->assertStringContainsString( 'left:-9999px', $result, 'left should survive wp_kses_post' );
		$this->assertStringContainsString( 'overflow:hidden', $result, 'overflow should survive wp_kses_post' );
	}

	/**
	 * Test that visual CSS properties survive wp_kses_post().
	 */
	public function test_kses_preserves_visual_properties() {
		$html = '<div style="transform:rotate(45deg);transition:width 0.5s ease-out;aspect-ratio:16/9;object-fit:cover;object-position:50% 50%;box-sizing:border-box">test</div>';

		$result = wp_kses_post( $html );

		$this->assertStringContainsString( 'transform:rotate(45deg)', $result, 'transform should survive wp_kses_post' );
		$this->assertStringContainsString( 'transition:width 0.5s ease-out', $result, 'transition should survive wp_kses_post' );
		$this->assertStringContainsString( 'aspect-ratio:16/9', $result, 'aspect-ratio should survive wp_kses_post' );
		$this->assertStringContainsString( 'object-fit:cover', $result, 'object-fit should survive wp_kses_post' );
		$this->assertStringContainsString( 'object-position:50% 50%', $result, 'object-position should survive wp_kses_post' );
		$this->assertStringContainsString( 'box-sizing:border-box', $result, 'box-sizing should survive wp_kses_post' );
	}

	/**
	 * Test that backdrop-filter survives wp_kses_post().
	 */
	public function test_kses_preserves_backdrop_filter() {
		$html = '<div style="backdrop-filter:blur(10px)">test</div>';

		$result = wp_kses_post( $html );

		$this->assertStringContainsString( 'backdrop-filter:blur(10px)', $result, 'backdrop-filter should survive wp_kses_post' );
	}

	/**
	 * Test that display:grid and display:inline-flex survive wp_kses_post().
	 *
	 * Regression test for the specific display values used by blocks.
	 */
	public function test_kses_preserves_display_variants() {
		$values = array(
			'display:grid'       => '<div style="display:grid">test</div>',
			'display:inline-flex' => '<div style="display:inline-flex">test</div>',
			'display:none'       => '<div style="display:none">test</div>',
		);

		foreach ( $values as $expected => $html ) {
			$result = wp_kses_post( $html );
			$this->assertStringContainsString( $expected, $result, "$expected should survive wp_kses_post" );
		}
	}

	/**
	 * Test realistic block output survives wp_kses_post().
	 *
	 * Uses actual HTML that the Row and Grid blocks produce.
	 */
	public function test_kses_preserves_realistic_block_output() {
		// Row block output.
		$row_html = '<div class="wp-block-designsetgo-row dsgo-flex"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:20px">content</div></div>';

		$result = wp_kses_post( $row_html );

		$this->assertStringContainsString( 'display:flex', $result );
		$this->assertStringContainsString( 'justify-content:center', $result );
		$this->assertStringContainsString( 'align-items:center', $result );
		$this->assertStringContainsString( 'flex-wrap:wrap', $result );
		$this->assertStringContainsString( 'gap:20px', $result );

		// Grid block output.
		$grid_html = '<div class="wp-block-designsetgo-grid dsgo-grid"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(3, 1fr);row-gap:20px;column-gap:20px;align-items:stretch">content</div></div>';

		$result = wp_kses_post( $grid_html );

		$this->assertStringContainsString( 'display:grid', $result );
		$this->assertStringContainsString( 'grid-template-columns:repeat(3, 1fr)', $result );
		$this->assertStringContainsString( 'row-gap:20px', $result );
		$this->assertStringContainsString( 'column-gap:20px', $result );
	}

	/**
	 * Test that the wp_kses_allowed_html filter is registered.
	 */
	public function test_wp_kses_allowed_html_filter_registered() {
		$this->assertGreaterThan(
			0,
			has_filter( 'wp_kses_allowed_html', array( \DesignSetGo\Plugin::instance(), 'allow_block_svg_elements' ) ),
			'wp_kses_allowed_html filter should be registered'
		);
	}

	/**
	 * Test that CSS color functions survive wp_kses_post().
	 *
	 * Blocks use rgba() for semi-transparent icon backgrounds and rgb()/hsl()
	 * for color values. WordPress strips these functions by default.
	 */
	public function test_kses_preserves_color_functions() {
		$html = '<div style="background-color:rgba(99,102,241,0.1)">test</div>';
		$result = wp_kses_post( $html );
		$this->assertStringContainsString( 'background-color:rgba(99,102,241,0.1)', $result, 'rgba() should survive wp_kses_post' );

		$html = '<div style="background-color:rgb(255,0,0)">test</div>';
		$result = wp_kses_post( $html );
		$this->assertStringContainsString( 'background-color:rgb(255,0,0)', $result, 'rgb() should survive wp_kses_post' );

		$html = '<div style="color:hsl(200,50%,50%)">test</div>';
		$result = wp_kses_post( $html );
		$this->assertStringContainsString( 'color:hsl(200,50', $result, 'hsl() should survive wp_kses_post' );

		$html = '<div style="color:hsla(200,50%,50%,0.5)">test</div>';
		$result = wp_kses_post( $html );
		$this->assertStringContainsString( 'color:hsla(200,50', $result, 'hsla() should survive wp_kses_post' );
	}

	/**
	 * Test that CSS gradient functions survive wp_kses_post().
	 *
	 * Progress bar uses linear-gradient with rgba for its stripe pattern.
	 */
	public function test_kses_preserves_gradient_functions() {
		$html = '<div style="background-image:linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%)">test</div>';
		$result = wp_kses_post( $html );
		$this->assertStringContainsString( 'linear-gradient(45deg', $result, 'linear-gradient() should survive wp_kses_post' );
		$this->assertStringContainsString( 'rgba(255, 255, 255, 0.15)', $result, 'nested rgba() inside gradient should survive' );

		$html = '<div style="background-image:radial-gradient(circle, red, blue)">test</div>';
		$result = wp_kses_post( $html );
		$this->assertStringContainsString( 'radial-gradient(circle', $result, 'radial-gradient() should survive wp_kses_post' );
	}

	/**
	 * Test that SVG elements survive wp_kses_post().
	 *
	 * Shape dividers, icons, comparison table marks, timeline markers,
	 * accordion toggles, and counter decorations all use inline SVG.
	 */
	public function test_kses_preserves_svg_elements() {
		// Shape divider wave SVG.
		$html = '<div class="dsgo-shape-divider"><svg viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M0,0 C300,120 900,0 1200,80 L1200,120 L0,120 Z"></path></svg></div>';

		$result = wp_kses_post( $html );

		$this->assertStringContainsString( '<svg', $result, 'svg element should survive wp_kses_post' );
		$this->assertMatchesRegularExpression( '/view[Bb]ox="0 0 1200 120"/', $result, 'viewBox attribute should survive wp_kses_post' );
		$this->assertStringContainsString( '<path', $result, 'path element should survive wp_kses_post' );
		$this->assertStringContainsString( 'd="M0,0', $result, 'path d attribute should survive wp_kses_post' );
	}

	/**
	 * Test that SVG icon elements survive wp_kses_post().
	 *
	 * Accordion items, modals, comparison tables, and counters use inline SVG icons.
	 */
	public function test_kses_preserves_svg_icons() {
		// Accordion chevron icon.
		$html = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg>';

		$result = wp_kses_post( $html );

		$this->assertStringContainsString( '<svg', $result );
		$this->assertStringContainsString( 'fill="currentColor"', $result );
		$this->assertStringContainsString( '<path', $result );
	}

	/**
	 * Test that SVG elements with various shapes survive wp_kses_post().
	 *
	 * Timeline markers use circle, rect; comparison tables use line, polyline.
	 */
	public function test_kses_preserves_svg_shapes() {
		$html = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#fff" stroke="#000"></circle></svg>';
		$result = wp_kses_post( $html );
		$this->assertStringContainsString( '<circle', $result, 'circle element should survive' );

		$html = '<svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="2"></rect></svg>';
		$result = wp_kses_post( $html );
		$this->assertStringContainsString( '<rect', $result, 'rect element should survive' );

		$html = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18" stroke="currentColor"></line></svg>';
		$result = wp_kses_post( $html );
		$this->assertStringContainsString( '<line', $result, 'line element should survive' );

		$html = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12" stroke="currentColor"></polyline></svg>';
		$result = wp_kses_post( $html );
		$this->assertStringContainsString( '<polyline', $result, 'polyline element should survive' );
	}

	/**
	 * Test realistic icon block CSS properties with rgba background survive wp_kses_post().
	 *
	 * Icon blocks use lazy-loading (dsgo-lazy-icon with data-icon-name), not inline SVG.
	 * This tests the CSS property and data attribute preservation.
	 */
	public function test_kses_preserves_icon_block_css() {
		$html = '<div class="wp-block-designsetgo-icon dsgo-icon has-text-color has-background" style="border-radius:10px;color:#6366f1;background-color:rgba(99,102,241,0.1);padding-top:var(--wp--preset--spacing--20);display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center" data-icon-name="lightning" role="img" aria-label="Lightning"></div></div>';

		$result = wp_kses_post( $html );

		$this->assertStringContainsString( 'background-color:rgba(99,102,241,0.1)', $result, 'rgba background-color should survive' );
		$this->assertStringContainsString( 'display:flex', $result, 'display:flex should survive' );
		$this->assertStringContainsString( 'display:inline-flex', $result, 'display:inline-flex should survive' );
		$this->assertStringContainsString( 'data-icon-name="lightning"', $result, 'data attributes should survive' );
	}

	/**
	 * Test that dangerous SVG content is stripped by wp_kses_post().
	 *
	 * Verifies our SVG allowlist only permits presentation elements
	 * and blocks script injection, event handlers, and javascript: URLs.
	 */
	public function test_kses_strips_dangerous_svg_content() {
		// Script tag inside SVG.
		$html = '<svg><script>alert(1)</script><path d="M0,0"></path></svg>';
		$result = wp_kses_post( $html );
		$this->assertStringNotContainsString( '<script', $result, 'script tags should be stripped from SVG' );
		$this->assertStringContainsString( '<path', $result, 'safe path element should remain' );

		// Event handler on SVG element.
		$html = '<svg onclick="alert(1)"><path d="M0,0"></path></svg>';
		$result = wp_kses_post( $html );
		$this->assertStringNotContainsString( 'onclick', $result, 'event handlers should be stripped from SVG' );
	}
}

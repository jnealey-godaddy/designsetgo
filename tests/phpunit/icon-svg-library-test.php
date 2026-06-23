<?php
/**
 * Tests for the icon SVG library helpers.
 *
 * Regression guard for the rename dsgo_* -> designsetgo_* in
 * includes/data/icon-svg-library.php. Confirms the renamed global functions exist
 * and behave as before.
 *
 * @group icons
 */

/**
 * @group icons
 */
class DesignSetGo_Icon_Svg_Library_Test extends WP_UnitTestCase {

	public function test_get_all_icons_returns_keyed_svg_map() {
		$icons = designsetgo_get_all_icons();

		$this->assertIsArray( $icons );
		$this->assertNotEmpty( $icons );
		$this->assertArrayHasKey( 'accordion', $icons );
		$this->assertStringContainsString( '<svg', $icons['accordion'] );
	}

	public function test_get_icon_svg_returns_markup_for_known_icon() {
		$svg = designsetgo_get_icon_svg( 'arrow-down' );

		$this->assertStringContainsString( '<svg', $svg );
	}

	public function test_get_icon_svg_returns_empty_for_unknown_icon() {
		$this->assertSame( '', designsetgo_get_icon_svg( 'this-icon-does-not-exist' ) );
	}

	public function test_get_icon_aliases_returns_array() {
		$this->assertIsArray( designsetgo_get_icon_aliases() );
	}

	public function test_sanitize_icon_slug_strips_unsafe_characters() {
		$this->assertSame( 'arrow-down', designsetgo_sanitize_icon_slug( 'Arrow-Down' ) );
		$this->assertSame( 'foobar', designsetgo_sanitize_icon_slug( 'foo Bar!' ) );
		$this->assertSame( '', designsetgo_sanitize_icon_slug( '' ) );
	}

	public function test_accordion_render_icon_returns_span_with_svg() {
		$html = designsetgo_accordion_render_icon( 'chevron', false );

		$this->assertStringContainsString( 'dsgo-accordion-item__icon', $html );
		$this->assertStringContainsString( '<svg', $html );
	}

	public function test_accordion_render_icon_returns_empty_for_none_style() {
		$this->assertSame( '', designsetgo_accordion_render_icon( 'none', false ) );
	}
}

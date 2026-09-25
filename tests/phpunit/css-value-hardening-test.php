<?php
/**
 * Inline-style value hardening tests: designsetgo_safe_css_value() (Slider,
 * Scroll Slides) and Dynamic Image's aspectRatio / objectFit.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;

/**
 * CSS value hardening test case.
 */
class Test_CSS_Value_Hardening extends WP_UnitTestCase {

	/**
	 * Load the query helpers the way production does, from build/.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		$helpers = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		if ( ! function_exists( 'designsetgo_safe_css_value' ) && file_exists( $helpers ) ) {
			require_once $helpers;
		}
	}

	public function test_legitimate_values_pass_through() {
		$this->assertTrue( function_exists( 'designsetgo_safe_css_value' ) );

		foreach ( array( '20px', '16/9', 'var(--wp--preset--spacing--40)', 'calc(100vh - 2rem)', '#fff', 'rgba(0, 0, 0, 0.5)' ) as $value ) {
			$this->assertSame( $value, designsetgo_safe_css_value( $value ) );
		}
	}

	public function test_resource_loading_values_are_rejected() {
		foreach ( array(
			'url(https://example.com/beacon.gif)',
			'URL( "https://example.com/beacon.gif" )',
			'image-set("https://example.com/beacon.gif" 1x)',
			'-webkit-image-set("https://example.com/beacon.gif" 1x)',
			'expression(alert(1))',
			'javascript:alert(1)',
		) as $value ) {
			$this->assertSame( '', designsetgo_safe_css_value( $value ), $value );
		}
	}

	public function test_escape_split_url_is_rejected() {
		// Stripping the backslash is what reassembles `url(`.
		$this->assertSame( '', designsetgo_safe_css_value( 'u\\rl(https://example.com/x)' ) );
	}

	public function test_declaration_breakout_is_stripped() {
		$this->assertSame( '20px --x:1', designsetgo_safe_css_value( '20px; --x:1' ) );
	}

	/**
	 * Render a Dynamic Image with a fallback URL and the given attributes.
	 *
	 * @param array $attrs Extra attributes.
	 * @return string Rendered HTML.
	 */
	private function render_dynamic_image( array $attrs ) {
		$attrs = array_merge( array( 'fallbackUrl' => 'https://example.com/a.jpg' ), $attrs );
		return render_block(
			array(
				'blockName'    => 'designsetgo/dynamic-image',
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	public function test_dynamic_image_accepts_aspect_ratios_and_fit_keywords() {
		$html = $this->render_dynamic_image(
			array(
				'aspectRatio' => '16/9',
				'objectFit'   => 'contain',
			)
		);

		$this->assertStringContainsString( 'aspect-ratio:16/9', $html );
		$this->assertStringContainsString( 'object-fit:contain', $html );
	}

	public function test_dynamic_image_drops_injected_declarations() {
		$html = $this->render_dynamic_image(
			array(
				'aspectRatio' => '1;background:url(https://example.com/beacon.gif)',
				'objectFit'   => 'cover;background:url(https://example.com/beacon.gif)',
			)
		);

		$this->assertStringNotContainsString( 'beacon', $html );
		$this->assertStringNotContainsString( 'aspect-ratio', $html );
		$this->assertStringContainsString( 'object-fit:cover', $html );
	}
}

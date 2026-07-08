<?php
/**
 * Tests for Helper Functions
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;

/**
 * Helper Functions Test Case
 */
class Test_Helpers extends WP_UnitTestCase {
	/**
	 * Test designsetgo_get_block_class function
	 */
	public function test_get_block_class() {
		// Test basic block class.
		$this->assertEquals( 'dsgo-stack', designsetgo_get_block_class( 'stack' ) );

		// Test with unique ID.
		$class = designsetgo_get_block_class( 'flex', 'abc123' );
		$this->assertEquals( 'dsgo-flex dsgo-block-abc123', $class );

		// Test with empty unique ID.
		$this->assertEquals( 'dsgo-button', designsetgo_get_block_class( 'button', '' ) );
	}

	/**
	 * Test designsetgo_generate_block_id function
	 */
	public function test_generate_block_id() {
		$id = designsetgo_generate_block_id();

		// Should start with 'dsgo-'.
		$this->assertStringStartsWith( 'dsgo-', $id );

		// Should be longer than just 'dsgo-'.
		$this->assertGreaterThan( 4, strlen( $id ) );

		// Should generate unique IDs.
		$id2 = designsetgo_generate_block_id();
		$this->assertNotEquals( $id, $id2 );
	}

	/**
	 * Test designsetgo_is_dsg_block function
	 */
	public function test_is_dsg_block() {
		// DesignSetGo blocks.
		$this->assertTrue( designsetgo_is_dsg_block( 'designsetgo/stack' ) );
		$this->assertTrue( designsetgo_is_dsg_block( 'designsetgo/flex' ) );

		// Core blocks.
		$this->assertFalse( designsetgo_is_dsg_block( 'core/paragraph' ) );
		$this->assertFalse( designsetgo_is_dsg_block( 'core/group' ) );

		// Other plugins.
		$this->assertFalse( designsetgo_is_dsg_block( 'custom/block' ) );
	}

	/**
	 * Test CSS size sanitization with valid values
	 */
	public function test_sanitize_css_size_valid() {
		// Pixel values.
		$this->assertEquals( '24px', designsetgo_sanitize_css_size( '24px' ) );
		$this->assertEquals( '-10px', designsetgo_sanitize_css_size( '-10px' ) );
		$this->assertEquals( '1.5px', designsetgo_sanitize_css_size( '1.5px' ) );

		// Percentage.
		$this->assertEquals( '50%', designsetgo_sanitize_css_size( '50%' ) );
		$this->assertEquals( '100%', designsetgo_sanitize_css_size( '100%' ) );

		// Rem/em values.
		$this->assertEquals( '1.5rem', designsetgo_sanitize_css_size( '1.5rem' ) );
		$this->assertEquals( '2em', designsetgo_sanitize_css_size( '2em' ) );

		// Viewport units.
		$this->assertEquals( '100vh', designsetgo_sanitize_css_size( '100vh' ) );
		$this->assertEquals( '50vw', designsetgo_sanitize_css_size( '50vw' ) );
		$this->assertEquals( '10vmin', designsetgo_sanitize_css_size( '10vmin' ) );
		$this->assertEquals( '20vmax', designsetgo_sanitize_css_size( '20vmax' ) );

		// Keywords.
		$this->assertEquals( 'auto', designsetgo_sanitize_css_size( 'auto' ) );
		$this->assertEquals( 'inherit', designsetgo_sanitize_css_size( 'inherit' ) );
		$this->assertEquals( 'initial', designsetgo_sanitize_css_size( 'initial' ) );
		$this->assertEquals( 'unset', designsetgo_sanitize_css_size( 'unset' ) );
		$this->assertEquals( 'none', designsetgo_sanitize_css_size( 'none' ) );
		$this->assertEquals( '0', designsetgo_sanitize_css_size( '0' ) );

		// CSS math functions.
		$this->assertEquals( 'calc(100% - 20px)', designsetgo_sanitize_css_size( 'calc(100% - 20px)' ) );

		// Note: clamp/min/max with commas currently not supported by regex (would need to add comma to character class).
		// These should return null until the sanitize function is enhanced.
		$this->assertNull( designsetgo_sanitize_css_size( 'clamp(1rem, 2vw, 3rem)' ) );
		$this->assertNull( designsetgo_sanitize_css_size( 'min(50%, 300px)' ) );
		$this->assertNull( designsetgo_sanitize_css_size( 'max(100px, 10vw)' ) );
	}

	/**
	 * Test CSS size sanitization with invalid values (security)
	 */
	public function test_sanitize_css_size_invalid() {
		// Dangerous functions should return null.
		$this->assertNull( designsetgo_sanitize_css_size( 'expression(alert(1))' ) );
		$this->assertNull( designsetgo_sanitize_css_size( 'url(javascript:alert(1))' ) );
		$this->assertNull( designsetgo_sanitize_css_size( 'calc(var(--malicious))' ) );
		$this->assertNull( designsetgo_sanitize_css_size( 'attr(data-evil)' ) );

		// Invalid formats.
		$this->assertNull( designsetgo_sanitize_css_size( '24' ) ); // Missing unit.
		$this->assertNull( designsetgo_sanitize_css_size( 'invalid' ) );
		$this->assertNull( designsetgo_sanitize_css_size( '<script>alert(1)</script>' ) );

		// Empty values.
		$this->assertNull( designsetgo_sanitize_css_size( '' ) );
		$this->assertNull( designsetgo_sanitize_css_size( null ) );
	}

	/**
	 * Test CSS color sanitization with valid values
	 */
	public function test_sanitize_css_color_valid() {
		// Hex colors.
		$this->assertEquals( '#fff', designsetgo_sanitize_css_color( '#fff' ) );
		$this->assertEquals( '#ffffff', designsetgo_sanitize_css_color( '#ffffff' ) );
		$this->assertEquals( '#ffffffff', designsetgo_sanitize_css_color( '#ffffffff' ) );
		$this->assertEquals( '#ff0000', designsetgo_sanitize_css_color( '#FF0000' ) ); // Lowercase conversion.

		// RGB/RGBA.
		$this->assertEquals( 'rgb(255, 0, 0)', designsetgo_sanitize_css_color( 'rgb(255, 0, 0)' ) );
		$this->assertEquals( 'rgba(255, 0, 0, 0.5)', designsetgo_sanitize_css_color( 'rgba(255, 0, 0, 0.5)' ) );

		// HSL/HSLA.
		$this->assertEquals( 'hsl(120, 100%, 50%)', designsetgo_sanitize_css_color( 'hsl(120, 100%, 50%)' ) );
		$this->assertEquals( 'hsla(120, 100%, 50%, 0.5)', designsetgo_sanitize_css_color( 'hsla(120, 100%, 50%, 0.5)' ) );

		// Named colors.
		$this->assertEquals( 'red', designsetgo_sanitize_css_color( 'red' ) );
		$this->assertEquals( 'transparent', designsetgo_sanitize_css_color( 'transparent' ) );
		$this->assertEquals( 'currentcolor', designsetgo_sanitize_css_color( 'currentColor' ) ); // Lowercase.

		// WordPress custom properties.
		$this->assertEquals( 'var(--wp--preset--color--primary)', designsetgo_sanitize_css_color( 'var(--wp--preset--color--primary)' ) );
		$this->assertEquals( 'var(--dsgo--custom-color)', designsetgo_sanitize_css_color( 'var(--dsgo--custom-color)' ) );
	}

	/**
	 * Test CSS color sanitization with invalid values (security)
	 */
	public function test_sanitize_css_color_invalid() {
		// Invalid formats.
		$this->assertNull( designsetgo_sanitize_css_color( '#gg0000' ) ); // Invalid hex.

		// Note: rgb(256, 0, 0) passes regex validation (doesn't check numeric ranges).
		// This is a known limitation - the sanitizer validates format, not RGB value ranges.
		$this->assertEquals( 'rgb(256, 0, 0)', designsetgo_sanitize_css_color( 'rgb(256, 0, 0)' ) );

		$this->assertNull( designsetgo_sanitize_css_color( 'invalid-color' ) );

		// Dangerous content.
		$this->assertNull( designsetgo_sanitize_css_color( 'url(javascript:alert(1))' ) );
		$this->assertNull( designsetgo_sanitize_css_color( 'expression(alert(1))' ) );

		// Custom properties from non-WP sources.
		$this->assertNull( designsetgo_sanitize_css_color( 'var(--malicious)' ) );
		$this->assertNull( designsetgo_sanitize_css_color( 'var(--custom-evil)' ) );

		// Empty values.
		$this->assertNull( designsetgo_sanitize_css_color( '' ) );
		$this->assertNull( designsetgo_sanitize_css_color( null ) );
	}

	/**
	 * Test generic CSS value sanitization
	 */
	public function test_sanitize_css_value() {
		// Size type.
		$this->assertEquals( '24px', designsetgo_sanitize_css_value( '24px', 'size' ) );

		// Color type.
		$this->assertEquals( '#ffffff', designsetgo_sanitize_css_value( '#ffffff', 'color' ) );

		// URL type.
		$url = designsetgo_sanitize_css_value( 'https://example.com/image.jpg', 'url' );
		$this->assertStringContainsString( 'example.com', $url );

		// Default (text).
		$this->assertEquals( 'some text', designsetgo_sanitize_css_value( 'some text', 'unknown' ) );

		// Empty values.
		$this->assertNull( designsetgo_sanitize_css_value( '' ) );

		// Zero should be allowed.
		$this->assertEquals( '0', designsetgo_sanitize_css_value( '0', 'size' ) );
	}

	/**
	 * Non-preset values pass through designsetgo_resolve_preset_color unchanged.
	 */
	public function test_resolve_preset_color_passes_through_raw_values() {
		// Raw colors are returned as-is (legacy hex, rgb, named).
		$this->assertEquals( '#e74c3c', designsetgo_resolve_preset_color( '#e74c3c' ) );
		$this->assertEquals( 'rgb(1, 2, 3)', designsetgo_resolve_preset_color( 'rgb(1, 2, 3)' ) );
		$this->assertEquals( 'rebeccapurple', designsetgo_resolve_preset_color( 'rebeccapurple' ) );

		// Empty / non-string input is returned untouched.
		$this->assertEquals( '', designsetgo_resolve_preset_color( '' ) );
		$this->assertNull( designsetgo_resolve_preset_color( null ) );
	}

	/**
	 * An unresolvable preset reference honors the optional fallback.
	 */
	public function test_resolve_preset_color_unresolved_preset() {
		$missing = 'var:preset|color|definitely-not-a-real-slug';

		// Without a fallback the original token is returned.
		$this->assertEquals( $missing, designsetgo_resolve_preset_color( $missing ) );

		// With a fallback the safe default is substituted.
		$this->assertEquals( '#e74c3c', designsetgo_resolve_preset_color( $missing, '#e74c3c' ) );
	}

	/**
	 * A preset reference whose slug exists in the palette resolves to its color.
	 */
	public function test_resolve_preset_color_resolves_from_palette() {
		$inject = static function ( $theme_json ) {
			return $theme_json->update_with(
				array(
					'version'  => 2,
					'settings' => array(
						'color' => array(
							'palette' => array(
								array(
									'slug'  => 'accent-2',
									'color' => '#F5B684',
									'name'  => 'Accent 2',
								),
							),
						),
					),
				)
			);
		};

		add_filter( 'wp_theme_json_data_theme', $inject );
		\WP_Theme_JSON_Resolver::clean_cached_data();

		try {
			// Both the block-attribute shorthand and the CSS-var form resolve.
			$this->assertEquals(
				'#F5B684',
				designsetgo_resolve_preset_color( 'var:preset|color|accent-2', '#e74c3c' )
			);
			$this->assertEquals(
				'#F5B684',
				designsetgo_resolve_preset_color( 'var(--wp--preset--color--accent-2)' )
			);
		} finally {
			remove_filter( 'wp_theme_json_data_theme', $inject );
			\WP_Theme_JSON_Resolver::clean_cached_data();
		}
	}
}

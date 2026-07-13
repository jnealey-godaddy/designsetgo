<?php
/**
 * CSS escape-sequence bypass regression tests.
 *
 * A browser resolves CSS escape sequences BEFORE applying a declaration, so
 * `\75\72\6c(//evil.test)` is `url(//evil.test)` as far as the page is concerned.
 * A sanitizer that pattern-matches the raw text therefore blocks nothing — the
 * attacker just escapes one character.
 *
 * Custom_CSS_Renderer already normalized escapes before matching. The plugin's
 * two OTHER CSS sanitizers did not, because the normalization was a private
 * method rather than a shared one:
 *
 *   - DesignSetGo\Abilities\CSS_Sanitizer::sanitize()
 *   - DesignSetGo\StyleBinding (inline style injection for dynamic bindings)
 *
 * These tests pin the bypass closed in all three, via the now-shared
 * designsetgo_normalize_css_escapes().
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\CSS_Sanitizer;

/**
 * @group security
 * @group css-escapes
 */
class CSS_Escape_Bypass_Test extends WP_UnitTestCase {

	/**
	 * The decoder itself.
	 *
	 * @dataProvider escape_provider
	 *
	 * @param string $input    Escaped CSS text.
	 * @param string $expected What a browser would actually see.
	 */
	public function test_normalizer_decodes_what_a_browser_would_see( $input, $expected ) {
		$this->assertSame( $expected, designsetgo_normalize_css_escapes( $input ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function escape_provider() {
		return array(
			'hex-escaped url('     => array( '\75\72\6c(//evil.test)', 'url(//evil.test)' ),
			'hex-escaped js scheme' => array( '\6a\61\76\61script:alert(1)', 'javascript:alert(1)' ),
			'null byte in token'   => array( "java\0script:alert(1)", 'javascript:alert(1)' ),
			// `\000073` is the full 6-hex-digit form of U+0073 's', so this is
			// `javascript:` — the maximally-padded way to hide the scheme.
			'zero-padded escape'   => array( 'java\000073cript:alert(1)', 'javascript:alert(1)' ),
			'escaped expression('  => array( 'expression\28 1\29', 'expression(1)' ),
			'backslash-escaped ASCII' => array( '\u\r\l(x)', 'url(x)' ),
			'plain text untouched' => array( '10px', '10px' ),
			'empty string'         => array( '', '' ),
		);
	}

	public function test_normalizer_tolerates_non_strings() {
		$this->assertSame( '', designsetgo_normalize_css_escapes( null ) );
		$this->assertSame( '', designsetgo_normalize_css_escapes( array() ) );
	}

	/**
	 * Abilities\CSS_Sanitizer must not be fooled by escapes.
	 *
	 * @dataProvider dangerous_css_provider
	 *
	 * @param string $css Attacker-supplied CSS.
	 */
	public function test_css_sanitizer_blocks_escaped_payloads( $css ) {
		$sanitized = CSS_Sanitizer::sanitize( '.a{color:red;background:' . $css . ';}' );

		$this->assertStringContainsString(
			'blocked',
			$sanitized,
			'Escaped payload slipped through CSS_Sanitizer: ' . $css
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function dangerous_css_provider() {
		return array(
			'escaped javascript:' => array( '\6a\61\76\61script:alert(1)' ),
			'escaped expression(' => array( 'expression\28 alert(1)\29' ),
			'null-byte javascript' => array( "java\0script:alert(1)" ),
		);
	}

	/**
	 * Encoding layers must be peeled to a fixed point BEFORE tag-stripping.
	 *
	 * There are two independent layers — HTML entities and CSS escapes — and each
	 * can hide the other. `\3c script\3e` does not look like a tag to
	 * wp_strip_all_tags(), so if escapes are decoded AFTER the strip, a literal
	 * <script> reappears in the output with nothing left to catch it. Conversely
	 * `&#92;75rl(` hides a CSS escape behind an entity. Neither ordering alone is
	 * sufficient, which is why sanitize() now decodes until the string is stable.
	 *
	 * @dataProvider nested_encoding_provider
	 *
	 * @param string $css       Attacker-supplied CSS.
	 * @param string $forbidden Text that must not survive.
	 */
	public function test_css_sanitizer_peels_nested_encodings( $css, $forbidden ) {
		$sanitized = CSS_Sanitizer::sanitize( '.a{color:red;}' . $css );

		$this->assertStringNotContainsString(
			$forbidden,
			$sanitized,
			'A nested encoding survived sanitize(): ' . $css
		);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function nested_encoding_provider() {
		return array(
			// CSS escape hiding a tag from wp_strip_all_tags().
			'escaped script tag'   => array( '\3c script\3e alert(1)\3c /script\3e', '<script' ),
			'escaped img onerror'  => array( '\3c img src=x onerror=alert(1)\3e', '<img' ),
			// HTML entity hiding a CSS escape hiding a dangerous token.
			'entity-wrapped escape' => array( '&#92;65xpression(alert(1))', 'expression(' ),
		);
	}

	public function test_css_sanitizer_still_passes_ordinary_css() {
		$sanitized = CSS_Sanitizer::sanitize( '.a{color:red;margin:10px;}' );

		$this->assertStringNotContainsString( 'blocked', $sanitized );
		$this->assertStringContainsString( 'color:red', $sanitized );
	}

	/**
	 * StyleBinding: an escaped url() must never reach the style attribute.
	 *
	 * StyleBinding resolves a value through the
	 * `designsetgo_style_binding_resolve` filter, so this drives the real code
	 * path by making that filter return the attacker's payload.
	 *
	 * @dataProvider binding_payload_provider
	 *
	 * @param string $payload Value the binding source resolves to.
	 */
	public function test_style_binding_rejects_escaped_payloads( $payload ) {
		$filter = static function () use ( $payload ) {
			return $payload;
		};

		add_filter( 'designsetgo_style_binding_resolve', $filter, 10, 1 );

		$block = array(
			'attrs' => array(
				'dsgoStyleBinding' => array(
					'background-image' => array(
						'source' => 'designsetgo/post-meta',
						'key'    => 'whatever',
					),
				),
			),
		);

		$html = ( new \DesignSetGo\StyleBinding() )->apply_style_bindings( '<div class="x"></div>', $block );

		remove_filter( 'designsetgo_style_binding_resolve', $filter, 10 );

		$this->assertStringNotContainsString(
			'url(',
			$html,
			'Escaped url() reached the style attribute: ' . $payload
		);
		$this->assertStringNotContainsString(
			$payload,
			$html,
			'Raw escaped payload reached the style attribute: ' . $payload
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function binding_payload_provider() {
		return array(
			'escaped url('       => array( '\75\72\6c(//evil.test/x.png)' ),
			'partially escaped'  => array( 'u\72l(//evil.test/x.png)' ),
			'escaped javascript' => array( '\6a\61\76\61script:alert(1)' ),
			'raw url( (already blocked)' => array( 'url(//evil.test/x.png)' ),
		);
	}

	/**
	 * The fix must not break the feature it protects.
	 */
	public function test_style_binding_still_applies_an_ordinary_value() {
		$filter = static function () {
			return '#ff0000';
		};

		add_filter( 'designsetgo_style_binding_resolve', $filter, 10, 1 );

		$block = array(
			'attrs' => array(
				'dsgoStyleBinding' => array(
					'color' => array(
						'source' => 'designsetgo/post-meta',
						'key'    => 'whatever',
					),
				),
			),
		);

		$html = ( new \DesignSetGo\StyleBinding() )->apply_style_bindings( '<div class="x"></div>', $block );

		remove_filter( 'designsetgo_style_binding_resolve', $filter, 10 );

		$this->assertStringContainsString( 'color:#ff0000', $html );
	}
}

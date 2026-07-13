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
	 * A lone surrogate is not a Unicode scalar value.
	 *
	 * mb_chr() returns false for U+D800..U+DFFF, and preg_replace_callback()
	 * requires a string back — so `\d800` coerces false to '' today and would be
	 * a TypeError under stricter settings. This function is the shared boundary
	 * for three sanitizers, so it must not be fragile on hostile input.
	 *
	 * @dataProvider surrogate_provider
	 *
	 * @param string $input Escape sequence.
	 */
	public function test_normalizer_drops_lone_surrogates_without_error( $input ) {
		$result = designsetgo_normalize_css_escapes( $input . 'url(x)' );

		$this->assertIsString( $result );
		// The surrogate is dropped; the rest of the value is untouched.
		$this->assertSame( 'url(x)', $result );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function surrogate_provider() {
		return array(
			'high surrogate'    => array( '\d800' ),
			'low surrogate'     => array( '\dfff' ),
			'mid surrogate'     => array( '\dc00' ),
			'above Unicode max' => array( '\110000' ),
		);
	}

	/**
	 * Pin the platform hazard the surrogate guard exists for.
	 *
	 * Be honest about what the test above can and cannot prove: WITHOUT the guard,
	 * `\d800` still yields '' — because mb_chr() returns FALSE and
	 * preg_replace_callback() coerces that bool to an empty string. Same output,
	 * by accident. So no output-level assertion can fail on the unguarded code
	 * today; the guard makes correct behaviour intentional rather than incidental,
	 * and keeps the callback's contract (return a string) honest for stricter PHP.
	 *
	 * This test pins the underlying fact, so if a future PHP/mbstring changes it,
	 * we find out here rather than through a TypeError in a sanitizer.
	 */
	public function test_mb_chr_returns_false_for_a_surrogate() {
		$this->assertFalse( mb_chr( 0xD800, 'UTF-8' ) );
		$this->assertFalse( mb_chr( 0xDFFF, 'UTF-8' ) );
		$this->assertIsString( mb_chr( 0x73, 'UTF-8' ) );
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

	/**
	 * Deeply-nested entity encoding must be REJECTED, not returned half-decoded.
	 *
	 * html_entity_decode() peels exactly one layer per call, so an attacker can
	 * pick a nesting depth that outruns any fixed pass count:
	 *
	 *   &amp;amp;amp;amp;amp;#106;avascript:alert(1)   (6 layers -> javascript:)
	 *
	 * The danger is not the depth, it is what a bounded loop does when it runs
	 * out: returning `&#106;avascript:` looks harmless to the pattern checks here
	 * but a browser finishes the decode once it lands in an HTML attribute. So an
	 * unconverged value is dropped wholesale rather than passed through partly
	 * decoded.
	 *
	 * @dataProvider deep_nesting_provider
	 *
	 * @param string $css       Attacker-supplied CSS.
	 * @param string $forbidden Token that must not survive in any form.
	 */
	public function test_css_sanitizer_rejects_unconverged_deep_nesting( $css, $forbidden ) {
		$sanitized = CSS_Sanitizer::sanitize( $css );

		$this->assertStringNotContainsString( $forbidden, $sanitized, $css );
		// The residual half-decoded entity must not leak either.
		$this->assertStringNotContainsString( 'avascript', $sanitized, $css );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function deep_nesting_provider() {
		return array(
			// Six entity layers — one more than the old cap of five.
			'6-layer entity javascript' => array(
				'.a{color:red} &amp;amp;amp;amp;amp;#106;avascript:alert(1)',
				'javascript:',
			),
			// Well past the ceiling, to prove the cap-then-reject holds.
			'12-layer entity expression' => array(
				'.a{} ' . str_repeat( 'amp;', 12 ) . '&#101;xpression(alert(1))',
				'expression(',
			),
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

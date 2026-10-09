<?php
/**
 * Tests for Pattern Placeholder Images
 *
 * Tests the token replacement function used by the Patterns Loader
 * to swap {{dsgo:placeholder-*}} tokens for local image URLs.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;

/**
 * Placeholder Images Test Case
 */
class Test_Placeholder_Images extends WP_UnitTestCase {

	/**
	 * Test known tokens are replaced with URLs.
	 */
	public function test_known_tokens_replaced() {
		$content = '<img src="{{dsgo:placeholder-avatar}}" />';
		$result  = designsetgo_replace_pattern_placeholders( $content );

		$this->assertStringNotContainsString( '{{dsgo:placeholder-avatar}}', $result );
		$this->assertStringContainsString( 'assets/images/patterns/placeholder-avatar.jpg', $result );
	}

	/**
	 * Test all map entries produce valid URLs.
	 */
	public function test_all_types_produce_urls() {
		$map = designsetgo_get_placeholder_map();

		foreach ( array_keys( $map ) as $type ) {
			$token   = '{{dsgo:placeholder-' . $type . '}}';
			$result  = designsetgo_replace_pattern_placeholders( $token );

			$this->assertStringNotContainsString( '{{', $result, "Token for '$type' was not replaced." );
			$this->assertStringContainsString( 'http', $result, "Replacement for '$type' is not a URL." );
		}
	}

	/**
	 * Test unknown tokens pass through unchanged.
	 */
	public function test_unknown_tokens_unchanged() {
		$content = '<img src="{{dsgo:placeholder-unknown}}" />';
		$result  = designsetgo_replace_pattern_placeholders( $content );

		$this->assertStringContainsString( '{{dsgo:placeholder-unknown}}', $result );
	}

	/**
	 * Test non-token content is not modified.
	 */
	public function test_non_token_content_preserved() {
		$content = '<p>Hello world</p><img src="https://example.com/img.jpg" />';
		$result  = designsetgo_replace_pattern_placeholders( $content );

		$this->assertSame( $content, $result );
	}

	/**
	 * Test multiple tokens in a single string are all replaced.
	 */
	public function test_multiple_tokens_replaced() {
		$content = '{{dsgo:placeholder-avatar}} and {{dsgo:placeholder-landscape}} and {{dsgo:placeholder-logo}}';
		$result  = designsetgo_replace_pattern_placeholders( $content );

		$this->assertStringNotContainsString( '{{dsgo:', $result );
		$this->assertStringContainsString( 'placeholder-avatar.jpg', $result );
		$this->assertStringContainsString( 'placeholder-landscape.jpg', $result );
		$this->assertStringContainsString( 'placeholder-logo.svg', $result );
	}

	/**
	 * Test replacement URLs are properly escaped.
	 */
	public function test_urls_are_escaped() {
		$content = '{{dsgo:placeholder-landscape}}';
		$result  = designsetgo_replace_pattern_placeholders( $content );

		// esc_url should produce a clean URL with protocol.
		$this->assertMatchesRegularExpression( '#^https?://#', $result );
		$this->assertStringNotContainsString( '<', $result );
		$this->assertStringNotContainsString( '>', $result );
	}

	/**
	 * Test designsetgo_get_pattern_placeholder returns fallback for unknown type.
	 */
	public function test_get_placeholder_fallback() {
		$result = designsetgo_get_pattern_placeholder( 'nonexistent' );

		$this->assertStringContainsString( 'placeholder-landscape.jpg', $result );
	}

	/**
	 * Test designsetgo_get_pattern_placeholder returns correct file for valid type.
	 */
	public function test_get_placeholder_valid_type() {
		$result = designsetgo_get_pattern_placeholder( 'portrait' );

		$this->assertStringContainsString( 'placeholder-portrait.jpg', $result );
	}

	/**
	 * Test the countdown target is midnight UTC, 30 days ahead.
	 */
	public function test_countdown_target_is_thirty_days_ahead() {
		$now = gmmktime( 15, 42, 7, 10, 9, 2026 );

		$this->assertSame( '2026-11-08T00:00:00.000Z', designsetgo_get_pattern_countdown_target( $now ) );
	}

	/**
	 * Test the countdown token resolves to the same future date everywhere.
	 *
	 * The countdown block stores the date in its comment attribute and in
	 * the saved data attribute; both must match or the block is invalid.
	 */
	public function test_countdown_token_replaced_consistently() {
		$content = '<!-- wp:designsetgo/countdown-timer {"targetDateTime":"{{dsgo:countdown-target}}"} --><div data-target-datetime="{{dsgo:countdown-target}}"></div>';
		$result  = designsetgo_replace_pattern_placeholders( $content );

		$this->assertStringNotContainsString( '{{dsgo:', $result );
		$this->assertSame( 1, preg_match( '/"targetDateTime":"([^"]+)"/', $result, $attr ) );
		$this->assertSame( 1, preg_match( '/data-target-datetime="([^"]+)"/', $result, $data ) );
		$this->assertSame( $attr[1], $data[1] );
		$this->assertGreaterThan( time(), strtotime( $attr[1] ) );
	}

	/**
	 * Test no bundled pattern hardcodes a countdown date, which would expire.
	 */
	public function test_bundled_countdown_patterns_use_the_token() {
		$files = glob( DESIGNSETGO_PATH . 'patterns/*/*.php' );

		$this->assertNotEmpty( $files );

		foreach ( $files as $file ) {
			$source = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Reading a local plugin file in a test.

			if ( false === strpos( $source, 'targetDateTime' ) ) {
				continue;
			}

			$this->assertDoesNotMatchRegularExpression(
				'/"targetDateTime":"\d{4}-/',
				$source,
				basename( $file ) . ' hardcodes a countdown date; use {{dsgo:countdown-target}}.'
			);
			$this->assertDoesNotMatchRegularExpression(
				'/data-target-datetime="\d{4}-/',
				$source,
				basename( $file ) . ' hardcodes a countdown date; use {{dsgo:countdown-target}}.'
			);
		}
	}
}

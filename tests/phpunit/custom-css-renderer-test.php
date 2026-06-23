<?php
/**
 * Tests for Custom CSS Renderer
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\Custom_CSS_Renderer;
use ReflectionClass;
use ReflectionMethod;

/**
 * Custom CSS Renderer Test Case
 */
class Test_Custom_CSS_Renderer extends WP_UnitTestCase {
	/**
	 * Custom CSS Renderer instance.
	 *
	 * @var Custom_CSS_Renderer
	 */
	private $renderer;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->renderer = new Custom_CSS_Renderer();
	}

	/**
	 * Helper to call private methods for testing
	 *
	 * @param object $object     Object instance.
	 * @param string $method     Method name.
	 * @param array  $parameters Method parameters.
	 * @return mixed Method result.
	 */
	private function call_private_method( $object, $method, $parameters = array() ) {
		$reflection = new ReflectionClass( get_class( $object ) );
		$method     = $reflection->getMethod( $method );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $parameters );
	}

	/**
	 * Test hash_code generates consistent hashes
	 */
	public function test_hash_code() {
		$hash1 = $this->call_private_method( $this->renderer, 'hash_code', array( 'test-string' ) );
		$hash2 = $this->call_private_method( $this->renderer, 'hash_code', array( 'test-string' ) );

		// Same input should produce same hash.
		$this->assertEquals( $hash1, $hash2 );

		// Different input should produce different hash.
		$hash3 = $this->call_private_method( $this->renderer, 'hash_code', array( 'different' ) );
		$this->assertNotEquals( $hash1, $hash3 );

		// Hash should be a valid base36 string.
		$this->assertMatchesRegularExpression( '/^[0-9a-z]+$/', $hash1 );
	}

	/**
	 * Test replace_selector replaces selector keyword
	 */
	public function test_replace_selector() {
		// Basic selector replacement.
		$css    = 'selector { color: red; }';
		$result = $this->call_private_method( $this->renderer, 'replace_selector', array( $css, 'test-class' ) );
		$this->assertEquals( '.test-class { color: red; }', $result );

		// Selector with pseudo-class.
		$css    = 'selector:hover { background: blue; }';
		$result = $this->call_private_method( $this->renderer, 'replace_selector', array( $css, 'test-class' ) );
		$this->assertEquals( '.test-class:hover { background: blue; }', $result );

		// Selector with descendant.
		$css    = 'selector h3 { font-size: 24px; }';
		$result = $this->call_private_method( $this->renderer, 'replace_selector', array( $css, 'test-class' ) );
		$this->assertEquals( '.test-class h3 { font-size: 24px; }', $result );

		// Multiple selectors.
		$css    = 'selector, selector:hover { color: red; }';
		$result = $this->call_private_method( $this->renderer, 'replace_selector', array( $css, 'test-class' ) );
		$this->assertEquals( '.test-class, .test-class:hover { color: red; }', $result );

		// Empty CSS.
		$result = $this->call_private_method( $this->renderer, 'replace_selector', array( '', 'test-class' ) );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test sanitize_css removes dangerous content
	 */
	public function test_sanitize_css_security() {
		// Remove script tags.
		$css    = 'selector { color: red; } <script>alert("XSS")</script>';
		$result = $this->call_private_method( $this->renderer, 'sanitize_css', array( $css ) );
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( 'alert', $result );

		// Remove HTML tags.
		$css    = '<div>selector { color: red; }</div>';
		$result = $this->call_private_method( $this->renderer, 'sanitize_css', array( $css ) );
		$this->assertStringNotContainsString( '<div>', $result );

		// Remove event handlers.
		$css    = 'selector { onclick="alert(1)" color: red; }';
		$result = $this->call_private_method( $this->renderer, 'sanitize_css', array( $css ) );
		$this->assertStringNotContainsString( 'onclick', $result );

		// Remove javascript: protocol.
		$css    = 'selector { background: url(javascript:alert(1)); }';
		$result = $this->call_private_method( $this->renderer, 'sanitize_css', array( $css ) );
		$this->assertStringNotContainsString( 'javascript:', $result );

		// Remove data: URI.
		$css    = 'selector { background: url(data:text/html,<script>alert(1)</script>); }';
		$result = $this->call_private_method( $this->renderer, 'sanitize_css', array( $css ) );
		$this->assertStringNotContainsString( 'data:', $result );

		// Remove expression() (IE).
		$css    = 'selector { width: expression(alert(1)); }';
		$result = $this->call_private_method( $this->renderer, 'sanitize_css', array( $css ) );
		$this->assertStringNotContainsString( 'expression(', $result );

		// Remove -moz-binding.
		$css    = 'selector { -moz-binding: url(xss.xml#xss); }';
		$result = $this->call_private_method( $this->renderer, 'sanitize_css', array( $css ) );
		$this->assertStringNotContainsString( '-moz-binding', $result );

		// Remove behavior (IE).
		$css    = 'selector { behavior: url(xss.htc); }';
		$result = $this->call_private_method( $this->renderer, 'sanitize_css', array( $css ) );
		$this->assertStringNotContainsString( 'behavior:', $result );
	}

	/**
	 * Test sanitize_css preserves valid CSS
	 */
	public function test_sanitize_css_preserves_valid() {
		// Valid CSS should remain unchanged.
		$css    = 'selector { color: red; font-size: 24px; margin: 10px; }';
		$result = $this->call_private_method( $this->renderer, 'sanitize_css', array( $css ) );
		$this->assertStringContainsString( 'color: red', $result );
		$this->assertStringContainsString( 'font-size: 24px', $result );
		$this->assertStringContainsString( 'margin: 10px', $result );

		// Media queries.
		$css    = '@media (min-width: 768px) { selector { color: blue; } }';
		$result = $this->call_private_method( $this->renderer, 'sanitize_css', array( $css ) );
		$this->assertStringContainsString( '@media', $result );
		$this->assertStringContainsString( 'min-width: 768px', $result );

		// Pseudo-classes and pseudo-elements.
		$css    = 'selector:hover { color: blue; } selector::before { content: ""; }';
		$result = $this->call_private_method( $this->renderer, 'sanitize_css', array( $css ) );
		$this->assertStringContainsString( ':hover', $result );
		$this->assertStringContainsString( '::before', $result );
	}

	/**
	 * Test collect_custom_css collects CSS from blocks
	 */
	public function test_collect_custom_css() {
		$block_content = '<div class="wp-block-test">Test</div>';
		$block         = array(
			'blockName' => 'designsetgo/test',
			'attrs'     => array(
				'dsgoCustomCSS' => 'selector { color: red; }',
			),
		);

		$result = $this->renderer->collect_custom_css( $block_content, $block );

		// Should return original content.
		$this->assertEquals( $block_content, $result );

		// CSS should be collected (check by triggering output).
		ob_start();
		$this->renderer->render_custom_css();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'DesignSetGo Custom CSS', $output );
		$this->assertStringContainsString( 'color: red', $output );
	}

	/**
	 * Test collect_custom_css ignores blocks without custom CSS
	 */
	public function test_collect_custom_css_empty() {
		$block_content = '<div class="wp-block-test">Test</div>';
		$block         = array(
			'blockName' => 'designsetgo/test',
			'attrs'     => array(),
		);

		$result = $this->renderer->collect_custom_css( $block_content, $block );

		// Should return original content.
		$this->assertEquals( $block_content, $result );

		// No CSS should be output.
		ob_start();
		$this->renderer->render_custom_css();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test that a malicious custom_css_output filter cannot inject markup.
	 *
	 * The filter runs after per-block sanitization, so its result is re-stripped
	 * before being echoed inside the <style> block.
	 */
	public function test_custom_css_output_filter_result_is_resanitized() {
		$block = array(
			'blockName' => 'designsetgo/test',
			'attrs'     => array( 'dsgoCustomCSS' => 'selector { color: red; }' ),
		);
		$this->renderer->collect_custom_css( '<div>Test</div>', $block );

		$inject = static function () {
			return '</style><script>alert(1)</script>selector{color:blue}javascript:foo';
		};
		add_filter( 'designsetgo/custom_css_output', $inject );

		ob_start();
		$this->renderer->render_custom_css();
		$output = ob_get_clean();

		remove_filter( 'designsetgo/custom_css_output', $inject );

		// The injected script tag and protocol must be stripped from the output.
		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringNotContainsString( '</script>', $output );
		$this->assertStringNotContainsString( 'javascript:', $output );
	}

	/**
	 * Test render_custom_css deduplicates identical CSS
	 */
	public function test_render_custom_css_deduplication() {
		$block_content = '<div>Test</div>';

		// Add same CSS twice.
		$block1 = array(
			'blockName' => 'designsetgo/test',
			'attrs'     => array( 'dsgoCustomCSS' => 'selector { color: red; }' ),
		);
		$this->renderer->collect_custom_css( $block_content, $block1 );
		$this->renderer->collect_custom_css( $block_content, $block1 );

		ob_start();
		$this->renderer->render_custom_css();
		$output = ob_get_clean();

		// Should only appear once.
		$this->assertEquals( 1, substr_count( $output, 'color: red' ) );
	}
}

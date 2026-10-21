<?php
/**
 * Per-header sticky controls must reach the markup of the (dynamic)
 * core/template-part block.
 *
 * @package DesignSetGo
 * @group   extensions
 */

/**
 * Render-time mirror of applyStickyHeaderClasses().
 *
 * @group extensions
 */
class DesignSetGo_Sticky_Header_Template_Part_Test extends WP_UnitTestCase {

	/**
	 * Run the render filter.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $html  Rendered HTML.
	 * @return string
	 */
	private function run_filter( array $attrs, $html = '<header class="wp-block-template-part"><p>Nav</p></header>' ) {
		$sticky = new \DesignSetGo\Sticky_Header();

		return $sticky->apply_template_part_options(
			$html,
			array(
				'blockName' => 'core/template-part',
				'attrs'     => $attrs,
			)
		);
	}

	/**
	 * A header that did not opt in is untouched.
	 */
	public function test_not_enabled_is_untouched() {
		$html = '<header class="wp-block-template-part"><p>Nav</p></header>';
		$this->assertSame( $html, $this->run_filter( array( 'dsgoStickyShadow' => 'large' ) ) );
	}

	/**
	 * Defaults are applied when attributes equal their default (and so are
	 * absent from the stored block comment).
	 */
	public function test_defaults_apply() {
		$out = $this->run_filter( array( 'dsgoStickyEnabled' => true ) );

		$this->assertStringContainsString( 'dsgo-sticky-header-enabled', $out );
		$this->assertStringContainsString( 'dsgo-sticky-shadow-medium', $out );
		$this->assertStringContainsString( 'dsgo-sticky-shrink-logo', $out );
		$this->assertStringContainsString( 'dsgo-sticky-skip-top-bar', $out );
		$this->assertStringContainsString( 'data-dsgo-shrink-amount="50"', $out );
		$this->assertStringNotContainsString( 'hide-on-scroll', $out );
		$this->assertStringNotContainsString( 'bg-on-scroll', $out );
	}

	/**
	 * Explicit choices are honoured.
	 */
	public function test_explicit_options() {
		$out = $this->run_filter(
			array(
				'dsgoStickyEnabled'      => true,
				'dsgoStickyShadow'       => 'none',
				'dsgoStickyShrink'       => false,
				'dsgoStickyHideOnScroll' => true,
				'dsgoStickyBackground'   => true,
				'dsgoStickySkipTopBar'   => false,
			)
		);

		$this->assertStringContainsString( 'dsgo-sticky-hide-on-scroll-down', $out );
		$this->assertStringContainsString( 'dsgo-sticky-bg-on-scroll', $out );
		$this->assertStringNotContainsString( 'dsgo-sticky-shadow', $out );
		$this->assertStringNotContainsString( 'shrink-logo', $out );
		$this->assertStringNotContainsString( 'skip-top-bar', $out );
		$this->assertStringNotContainsString( 'data-dsgo-shrink-amount', $out );
	}

	/**
	 * A footer is never made sticky.
	 */
	public function test_footer_is_skipped() {
		$html = '<footer class="wp-block-template-part"><p>x</p></footer>';
		$this->assertSame( $html, $this->run_filter( array( 'dsgoStickyEnabled' => true ), $html ) );
	}

	/**
	 * Sibling-root output has no single header to mark.
	 */
	public function test_multi_root_output_is_skipped() {
		$html = '<div>a</div><div>b</div>';
		$this->assertSame( $html, $this->run_filter( array( 'dsgoStickyEnabled' => true ), $html ) );
	}
}

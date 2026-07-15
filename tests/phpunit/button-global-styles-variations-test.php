<?php
/**
 * Regression tests for Button_Global_Styles variation projection.
 *
 * `Button_Global_Styles` projects each `core/button` block-style variation onto
 * DSGo's single-element button primitives (icon-button, form submit) at a
 * specificity that beats the base button rule. The variation slug is
 * interpolated straight into a CSS selector, so `sanitize_variation_name()` is a
 * security boundary: these tests pin it closed and cover the emission rules
 * (per-primitive selectors, skip list, dedup, numeric slugs).
 *
 * @package DesignSetGo
 */

use DesignSetGo\Button_Global_Styles;

/**
 * Tests for projecting core/button style variations onto DSGo button primitives.
 *
 * @group buttons
 * @group global-styles
 */
class Button_Global_Styles_Variations_Test extends WP_UnitTestCase {

	/**
	 * Invoke a private method on a fresh instance.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Positional arguments.
	 * @return mixed
	 */
	private function call( $method, array $args = array() ) {
		$instance = new Button_Global_Styles();
		$ref      = new ReflectionMethod( $instance, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $instance, $args );
	}

	/**
	 * Build a minimal variations payload keyed by slug.
	 *
	 * @param array $variations Map of slug => style array.
	 * @return array Shaped like wp_get_global_styles( core/button ).
	 */
	private function block_styles( array $variations ) {
		return array( 'variations' => $variations );
	}

	/**
	 * The slug sanitiser strips anything that could break out of a selector.
	 *
	 * @dataProvider slug_provider
	 *
	 * @param int|string $input    Raw variation key.
	 * @param string     $expected Sanitised slug.
	 */
	public function test_sanitize_variation_name( $input, $expected ) {
		$this->assertSame( $expected, $this->call( 'sanitize_variation_name', array( $input ) ) );
	}

	/**
	 * Slug sanitiser cases.
	 *
	 * @return array
	 */
	public function slug_provider() {
		return array(
			'plain'              => array( 'primary', 'primary' ),
			'hyphenated'         => array( 'header-cta', 'header-cta' ),
			'uppercase lowered'  => array( 'Primary', 'primary' ),
			'numeric int key'    => array( 2024, '2024' ),
			'numeric string'     => array( '2024', '2024' ),
			'selector breakout'  => array( 'evil"} body{display:none', 'evilbodydisplaynone' ),
			'combinator/space'   => array( 'a b', 'ab' ),
			'strips underscores' => array( 'header_cta', 'headercta' ),
			'empty'              => array( '', '' ),
			'all-invalid'        => array( '{}();', '' ),
		);
	}

	/**
	 * A normal variation emits one rule per primitive with the sanitised slug.
	 */
	public function test_emits_per_primitive_selectors() {
		$css = $this->call(
			'build_variation_css',
			array(
				$this->block_styles(
					array(
						'secondary' => array(
							'color'  => array( 'background' => 'transparent' ),
							'border' => array(
								'color' => 'currentColor',
								'width' => '2px',
								'style' => 'solid',
							),
						),
					)
				),
			)
		);

		// Icon Button: variation class on the wrapper, styling the inner button.
		$this->assertStringContainsString(
			'.wp-block-designsetgo-icon-button.is-style-secondary .dsgo-icon-button.wp-block-button__link',
			$css
		);
		// Form submit: variation class compounded on the button.
		$this->assertStringContainsString(
			'.dsgo-form__submit.is-style-secondary.wp-element-button',
			$css
		);
		$this->assertStringContainsString( 'background-color:transparent', $css );
		// Modal Trigger is deliberately NOT projected (own buttonStyle system).
		$this->assertStringNotContainsString( 'dsgo-modal-trigger', $css );
	}

	/**
	 * A sanitised slug is what actually lands in the selector — no raw breakout.
	 */
	public function test_injection_slug_is_neutralised() {
		$css = $this->call(
			'build_variation_css',
			array(
				$this->block_styles(
					array( 'evil"} body{display:none' => array( 'color' => array( 'background' => 'red' ) ) )
				),
			)
		);

		$this->assertStringNotContainsString( '"}', $css );
		$this->assertStringNotContainsString( 'display:none', $css );
		$this->assertStringContainsString( 'is-style-evilbodydisplaynone', $css );
	}

	/**
	 * `fill` and `outline` are owned elsewhere and never projected here.
	 */
	public function test_skips_fill_and_outline() {
		$css = $this->call(
			'build_variation_css',
			array(
				$this->block_styles(
					array(
						'fill'    => array( 'color' => array( 'background' => '#111' ) ),
						'outline' => array( 'color' => array( 'background' => '#222' ) ),
					)
				),
			)
		);

		$this->assertSame( '', $css );
	}

	/**
	 * A variation whose slug matches a `dsgo-form__submit--*` modifier now emits a
	 * form-submit rule safely, because variations live in the separate
	 * `is-style-*` namespace — there is nothing left to collide with.
	 *
	 * The block/plugin stamp `dsgo-form__submit--{x}` on real buttons for hover
	 * animations (`--lift`, …) and layout/state (`--inline`, `--loading`,
	 * `--no-hover`). A like-named variation must still land in `is-style-{x}` and
	 * never in that BEM modifier namespace, so it can't repaint a button that
	 * merely carries the modifier.
	 *
	 * @dataProvider modifier_named_slug_provider
	 *
	 * @param string $slug A slug that used to collide with a form-submit modifier.
	 */
	public function test_modifier_named_slug_stays_in_is_style_namespace( $slug ) {
		$css = $this->call(
			'build_variation_css',
			array( $this->block_styles( array( $slug => array( 'color' => array( 'background' => '#f00' ) ) ) ) )
		);

		// Emitted for the form submit, in the is-style namespace...
		$this->assertStringContainsString(
			'.dsgo-form__submit.is-style-' . $slug . '.wp-element-button',
			$css,
			'Form-submit variation should be emitted in the is-style namespace.'
		);
		// ...and never in the modifier namespace it used to share.
		$this->assertStringNotContainsString(
			'.dsgo-form__submit--' . $slug,
			$css,
			'Variation must not reuse the dsgo-form__submit--* modifier namespace.'
		);
	}

	/**
	 * Slugs that once collided with a form-submit modifier: hover animations +
	 * layout/state.
	 *
	 * @return array
	 */
	public function modifier_named_slug_provider() {
		return array(
			'animation lift'   => array( 'lift' ),
			'animation shrink' => array( 'shrink' ),
			'layout inline'    => array( 'inline' ),
			'state loading'    => array( 'loading' ),
			'state no-hover'   => array( 'no-hover' ),
		);
	}

	/**
	 * Two raw names that normalise to the same slug emit a single rule set.
	 */
	public function test_dedupes_slugs() {
		$css = $this->call(
			'build_variation_css',
			array(
				$this->block_styles(
					array(
						'Primary' => array( 'color' => array( 'background' => '#111' ) ),
						'primary' => array( 'color' => array( 'background' => '#222' ) ),
					)
				),
			)
		);

		$this->assertSame(
			1,
			substr_count( $css, '.wp-block-designsetgo-icon-button.is-style-primary ' ),
			'A duplicate slug must not emit its selector twice.'
		);
	}

	/**
	 * A hover node on a variation emits a matching :hover rule.
	 */
	public function test_emits_hover_rule() {
		$css = $this->call(
			'build_variation_css',
			array(
				$this->block_styles(
					array(
						'header-cta' => array(
							'color'  => array( 'background' => '#e0653a' ),
							':hover' => array( 'color' => array( 'background' => '#c94f28' ) ),
						),
					)
				),
			)
		);

		$this->assertStringContainsString(
			'.wp-block-designsetgo-icon-button.is-style-header-cta .dsgo-icon-button.wp-block-button__link:hover',
			$css
		);
		$this->assertStringContainsString( 'background-color:#c94f28', $css );
	}

	/**
	 * No variations node yields no CSS.
	 */
	public function test_no_variations_yields_empty() {
		$this->assertSame( '', $this->call( 'build_variation_css', array( array() ) ) );
		$this->assertSame( '', $this->call( 'build_variation_css', array( array( 'variations' => 'nope' ) ) ) );
	}
}

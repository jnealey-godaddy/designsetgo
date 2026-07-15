<?php
/**
 * AI-inserted forms must honour submitButtonVariation, matching save.js.
 *
 * Block_Inserter::generate_form_builder_html() hardcodes the submit-button
 * markup, so it has to mirror the block's `submitButtonVariation` → class
 * mapping or an AI-inserted Secondary/Outline form would be invalid content
 * (its stored HTML would not match save()). The value is also validated against
 * the block.json enum before it reaches the class attribute.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * Tests that AI-inserted form submit buttons mirror submitButtonVariation.
 *
 * @group abilities
 * @group form-builder
 */
class Block_Inserter_Form_Variation_Test extends WP_UnitTestCase {

	/**
	 * Invoke the private form-builder HTML generator and return the submit-button
	 * markup (in the `closing` fragment).
	 *
	 * @param array $attributes Block attributes.
	 * @return string Generated closing HTML (contains the submit button).
	 */
	private function submit_html( array $attributes ) {
		$method = new ReflectionMethod( Block_Inserter::class, 'generate_form_builder_html' );
		$method->setAccessible( true );

		$result = $method->invoke( null, 'wp-block-designsetgo-form-builder dsgo-form-builder', $attributes );

		return $result['closing'];
	}

	/**
	 * The default variation adds no modifier class.
	 */
	public function test_default_emits_no_variation_class() {
		$html = $this->submit_html( array( 'formId' => 't' ) );
		$this->assertStringContainsString( 'class="dsgo-form__submit wp-element-button"', $html );
		$this->assertStringNotContainsString( 'is-style-secondary', $html );
		$this->assertStringNotContainsString( 'is-style-outline', $html );
	}

	/**
	 * A valid enum variation emits the matching modifier class.
	 *
	 * @dataProvider variation_provider
	 *
	 * @param string $variation submitButtonVariation value.
	 */
	public function test_valid_variation_emits_matching_class( $variation ) {
		$html = $this->submit_html(
			array(
				'formId'                => 't',
				'submitButtonVariation' => $variation,
			)
		);
		// Order must match save.js: `is-style-{variation}` before `wp-element-button`.
		$this->assertStringContainsString(
			'class="dsgo-form__submit is-style-' . $variation . ' wp-element-button"',
			$html
		);
	}

	/**
	 * Valid variation slugs.
	 *
	 * @return array
	 */
	public function variation_provider() {
		return array(
			'secondary' => array( 'secondary' ),
			'outline'   => array( 'outline' ),
		);
	}

	/**
	 * Inline position keeps the variation class after the --inline modifier.
	 */
	public function test_inline_position_keeps_variation_after_inline_modifier() {
		$html = $this->submit_html(
			array(
				'formId'                => 't',
				'submitButtonPosition'  => 'inline',
				'submitButtonVariation' => 'secondary',
			)
		);
		$this->assertStringContainsString(
			'class="dsgo-form__submit dsgo-form__submit--inline is-style-secondary wp-element-button"',
			$html
		);
	}

	/**
	 * Out-of-enum or hostile values are dropped, not emitted into the class.
	 *
	 * @dataProvider rejected_provider
	 *
	 * @param string $variation An out-of-enum / hostile value.
	 */
	public function test_out_of_enum_values_are_dropped( $variation ) {
		$html = $this->submit_html(
			array(
				'formId'                => 't',
				'submitButtonVariation' => $variation,
			)
		);
		// Falls back to the plain submit class; no variation class, no breakout.
		$this->assertStringContainsString( 'class="dsgo-form__submit wp-element-button"', $html );
		$this->assertStringNotContainsString( 'is-style-' . $variation, $html );
	}

	/**
	 * Values outside the block.json enum, including a class-attribute breakout.
	 *
	 * @return array
	 */
	public function rejected_provider() {
		return array(
			'default explicit' => array( 'default' ),
			'unknown slug'     => array( 'primary' ),
			'breakout attempt' => array( 'x" onmouseover="alert(1)' ),
		);
	}
}

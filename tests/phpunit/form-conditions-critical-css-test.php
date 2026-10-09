<?php
/**
 * The conditional-field pre-hide ships as critical inline CSS.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;

/**
 * Critical CSS test case.
 */
class Test_Form_Conditions_Critical_CSS extends WP_UnitTestCase {

	/**
	 * Form markup with or without a conditional field.
	 *
	 * @param bool $conditional Whether a field carries active conditions.
	 * @return string
	 */
	private function form( $conditional ) {
		$attrs = $conditional
			? '{"fieldName":"b","dsgoConditions":{"operator":"and","rules":[{"field":"a","op":"is","value":"x"}]}}'
			: '{"fieldName":"b"}';
		return '<!-- wp:designsetgo/form-builder --><form class="dsgo-form-builder"><!-- wp:designsetgo/form-text-field ' . $attrs . ' /--></form><!-- /wp:designsetgo/form-builder -->';
	}

	public function test_style_printed_before_form_markup() {
		$html = do_blocks( $this->form( true ) );

		$style = strpos( $html, '<style class="dsgo-form-conditions-critical">' );
		$this->assertNotFalse( $style );
		$this->assertLessThan( strpos( $html, 'dsgo-form-builder' ), $style );
		$this->assertStringContainsString( 'dsgo-conditions-pending', $html );
		$this->assertStringContainsString( '.dsgo-form-field[hidden]', $html );
		$this->assertMatchesRegularExpression( '/scripting:\s*enabled/', $html );
	}

	public function test_each_conditional_form_gets_its_own_style() {
		$html = do_blocks( $this->form( true ) ) . do_blocks( $this->form( true ) );

		$this->assertSame( 2, substr_count( $html, 'class="dsgo-form-conditions-critical"' ) );
		$this->assertStringNotContainsString( 'id="dsgo-form-conditions-critical"', $html );
	}

	public function test_plain_form_does_not_consume_or_get_style() {
		$plain = do_blocks( $this->form( false ) );
		$cond  = do_blocks( $this->form( true ) );

		$this->assertStringNotContainsString( 'dsgo-form-conditions-critical', $plain );
		$this->assertSame( 1, substr_count( $cond, 'class="dsgo-form-conditions-critical"' ) );
		$this->assertLessThan( strpos( $cond, 'dsgo-form-builder' ), strpos( $cond, '<style class=' ) );
	}

	public function test_no_style_without_conditional_fields() {
		$html = do_blocks( $this->form( false ) );

		$this->assertStringNotContainsString( 'dsgo-form-conditions-critical', $html );
	}
}

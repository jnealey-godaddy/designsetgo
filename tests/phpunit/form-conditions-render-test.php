<?php
/**
 * Field wrappers expose their name and conditions to the view script.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;

/**
 * Conditional render test case.
 */
class Test_Form_Conditions_Render extends WP_UnitTestCase {

	/**
	 * Every field block type.
	 *
	 * @return array
	 */
	public function field_blocks() {
		$out = array();
		foreach ( array( 'text', 'email', 'textarea', 'number', 'phone', 'url', 'date', 'time', 'select', 'checkbox', 'hidden' ) as $type ) {
			$out[ $type ] = array( 'designsetgo/form-' . $type . '-field' );
		}
		return $out;
	}

	/**
	 * @dataProvider field_blocks
	 *
	 * @param string $block Block name.
	 */
	public function test_attribute_registered_and_name_exposed( $block ) {
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $block );
		$this->assertSame( 'object', $type->attributes['dsgoConditions']['type'] );
		$this->assertNull( $type->attributes['dsgoConditions']['default'] );

		$html = do_blocks( '<!-- wp:' . $block . ' {"fieldName":"pick_me"} /-->' );
		$this->assertStringContainsString( 'data-dsgo-field="pick_me"', $html );
		$this->assertStringNotContainsString( 'data-dsgo-conditions', $html );
		$this->assertStringNotContainsString( 'dsgo-form-field--conditional', $html );
	}

	/**
	 * @dataProvider field_blocks
	 *
	 * @param string $block Block name.
	 */
	public function test_active_rules_are_exposed_sanitized( $block ) {
		$html = do_blocks(
			'<!-- wp:' . $block . ' {"fieldName":"f","dsgoConditions":{"operator":"or","evil":"x","rules":[{"field":"type","op":"is","value":"<script>","junk":1},{"field":"t","op":"bogus"}]}} /-->'
		);

		$this->assertStringContainsString( 'dsgo-form-field--conditional', $html );
		$this->assertMatchesRegularExpression( '/data-dsgo-conditions="([^"]*)"/', $html );
		preg_match( '/data-dsgo-conditions="([^"]*)"/', $html, $m );
		$this->assertSame(
			array(
				'operator' => 'OR',
				'rules'    => array( array( 'field' => 'type', 'op' => 'is', 'value' => '<script>' ) ),
			),
			json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true )
		);
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_text_field_markup_otherwise_unchanged() {
		$html = do_blocks( '<!-- wp:designsetgo/form-text-field {"fieldName":"a","fieldWidth":"50"} /-->' );
		$this->assertStringContainsString( 'class="dsgo-form-field dsgo-form-field--text wp-block-designsetgo-form-text-field"', $html );
		$this->assertStringContainsString( 'style="flex-basis:', $html );
	}
}

<?php
/**
 * Conditional field logic evaluator — parity cases shared with Jest.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\Blocks\Form_Conditions;

/**
 * Form conditions evaluator test case.
 */
class Test_Form_Conditions extends WP_UnitTestCase {

	/**
	 * Fixture cases.
	 *
	 * @return array
	 */
	public function parity_cases() {
		$fixture = json_decode(
			file_get_contents( dirname( __DIR__ ) . '/fixtures/form-conditions-cases.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			true
		);
		$cases   = array();
		foreach ( $fixture['cases'] as $case ) {
			$cases[ $case['description'] ] = array( $case );
		}
		return $cases;
	}

	/**
	 * @dataProvider parity_cases
	 *
	 * @param array $case Fixture case.
	 */
	public function test_parity_case( array $case ) {
		$this->assertSame(
			$case['expectedVisible'],
			Form_Conditions::visible_fields( $case['fields'], $case['conditions'], $case['values'] )
		);
	}

	/**
	 * Normalization keeps only the known shape.
	 */
	public function test_normalize_rules_keeps_known_shape_only() {
		$this->assertSame(
			array(
				'operator' => 'OR',
				'rules'    => array( array( 'field' => 'a', 'op' => 'is', 'value' => '5' ) ),
			),
			Form_Conditions::normalize_rules(
				array(
					'operator' => 'or',
					'extra'    => 1,
					'rules'    => array(
						array( 'field' => 'a', 'op' => 'is', 'value' => 5, 'junk' => true ),
						array( 'field' => '', 'op' => 'is', 'value' => 'x' ),
						array( 'field' => 'b', 'op' => 'bogus' ),
					),
				)
			)
		);
		$this->assertNull( Form_Conditions::normalize_rules( null ) );
		$this->assertNull( Form_Conditions::normalize_rules( array( 'rules' => array() ) ) );
	}

	/**
	 * extract() walks nested blocks in document order.
	 */
	public function test_extract_reads_field_blocks_recursively_in_document_order() {
		$blocks = parse_blocks(
			'<!-- wp:designsetgo/form-text-field {"fieldName":"first"} /-->'
			. '<!-- wp:group --><div class="wp-block-group">'
			. '<!-- wp:designsetgo/form-select-field {"fieldName":"type"} /-->'
			. '<!-- wp:designsetgo/form-text-field {"fieldName":"company","dsgoConditions":{"operator":"AND","rules":[{"field":"type","op":"is","value":"business"}]}} /-->'
			. '</div><!-- /wp:group -->'
			. '<!-- wp:designsetgo/form-email-field {"fieldName":"email","dsgoConditions":{"rules":[]}} /-->'
		);

		$this->assertSame(
			array(
				'fields'     => array( 'first', 'type', 'company', 'email' ),
				'conditions' => array(
					'company' => array(
						'operator' => 'AND',
						'rules'    => array( array( 'field' => 'type', 'op' => 'is', 'value' => 'business' ) ),
					),
				),
			),
			Form_Conditions::extract( $blocks )
		);
	}
}

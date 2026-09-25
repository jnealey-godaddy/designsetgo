<?php
/**
 * Server-side form field constraint tests.
 *
 * Each field's render.php turns its attributes into HTML constraints the
 * browser enforces. These tests pin that the server enforces the same ones,
 * and never more strictly than the browser would.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_REST_Request;
use DesignSetGo\Blocks\Form_Field_Rules;
use DesignSetGo\Blocks\Form_Handler;

/**
 * Form field rules test case.
 */
class Test_Form_Field_Rules extends WP_UnitTestCase {

	/**
	 * Rules for a single field block.
	 *
	 * @param string $block_name Block name without namespace.
	 * @param array  $attrs      Attributes, fieldName added.
	 * @return array
	 */
	private function rules_for( $block_name, array $attrs ) {
		$rules = Form_Field_Rules::extract(
			array(
				array(
					'blockName'   => 'designsetgo/' . $block_name,
					'attrs'       => array_merge( array( 'fieldName' => 'f' ), $attrs ),
					'innerBlocks' => array(),
				),
			)
		);
		return isset( $rules['f'] ) ? $rules['f'] : array();
	}

	/**
	 * Assert a value passes the rules.
	 *
	 * @param mixed $value Value.
	 * @param array $rules Rules.
	 */
	private function assert_accepts( $value, array $rules ) {
		$this->assertTrue( Form_Field_Rules::check( $value, $rules ), 'Expected ' . var_export( $value, true ) . ' to be accepted.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
	}

	/**
	 * Assert a value fails the rules.
	 *
	 * @param mixed $value Value.
	 * @param array $rules Rules.
	 */
	private function assert_rejects( $value, array $rules ) {
		$this->assertWPError( Form_Field_Rules::check( $value, $rules ), 'Expected ' . var_export( $value, true ) . ' to be rejected.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
	}

	public function test_text_length_limits() {
		$rules = $this->rules_for( 'form-text-field', array( 'minLength' => 3, 'maxLength' => 5 ) );

		$this->assert_rejects( 'ab', $rules );
		$this->assert_accepts( 'abc', $rules );
		$this->assert_accepts( 'abcde', $rules );
		$this->assert_rejects( 'abcdef', $rules );
		// Empty optional fields are valid whatever the constraints.
		$this->assert_accepts( '', $rules );
	}

	public function test_length_counts_utf16_units_like_the_browser() {
		$rules = $this->rules_for( 'form-text-field', array( 'maxLength' => 3 ) );

		// Three code points, three UTF-16 units.
		$this->assert_accepts( 'äöü', $rules );
		// One emoji is two UTF-16 units, so two of them exceed maxlength=3.
		$this->assert_rejects( "\u{1F600}\u{1F600}", $rules );
	}

	public function test_textarea_counts_crlf_as_one_character() {
		$rules = $this->rules_for( 'form-textarea-field', array( 'maxLength' => 3 ) );

		$this->assert_accepts( "a\r\nb", $rules );
		$this->assert_rejects( "ab\r\ncd", $rules );
	}

	public function test_text_validation_presets_are_anchored() {
		$rules = $this->rules_for( 'form-text-field', array( 'validation' => 'numbers' ) );

		$this->assert_accepts( '12345', $rules );
		$this->assert_rejects( '123abc', $rules );
		$this->assert_rejects( 'abc123', $rules );
	}

	public function test_custom_pattern_is_enforced() {
		$rules = $this->rules_for(
			'form-text-field',
			array(
				'validation'        => 'custom',
				'validationPattern' => '[A-Z]{2}-\d{3}',
			)
		);

		$this->assert_accepts( 'AB-123', $rules );
		$this->assert_rejects( 'AB-1234', $rules );
		$this->assert_rejects( 'ab-123', $rules );
	}

	public function test_pattern_without_escaping_needs_is_embedded_verbatim() {
		$rules = $this->rules_for(
			'form-text-field',
			array(
				'validation'        => 'custom',
				'validationPattern' => 'a/b#c~d',
			)
		);

		$this->assert_accepts( 'a/b#c~d', $rules );
		$this->assert_rejects( 'a/b#c~e', $rules );
	}

	public function test_uncompilable_pattern_is_ignored_like_the_browser_does() {
		$rules = $this->rules_for(
			'form-text-field',
			array(
				'validation'        => 'custom',
				'validationPattern' => '(unclosed',
			)
		);

		$this->assert_accepts( 'anything at all', $rules );
	}

	public function test_number_min_max_and_integer_step() {
		$rules = $this->rules_for( 'form-number-field', array( 'min' => 1, 'max' => 10 ) );

		$this->assert_rejects( '0', $rules );
		$this->assert_accepts( '1', $rules );
		$this->assert_accepts( '10', $rules );
		$this->assert_rejects( '11', $rules );
		// Without allowDecimals, render.php forces step 1.
		$this->assert_rejects( '2.5', $rules );
	}

	public function test_number_decimal_step_uses_min_as_base() {
		$rules = $this->rules_for(
			'form-number-field',
			array(
				'min'           => 0.1,
				'step'          => 0.5,
				'allowDecimals' => true,
			)
		);

		$this->assert_accepts( '0.1', $rules );
		$this->assert_accepts( '0.6', $rules );
		$this->assert_accepts( '1.1', $rules );
		$this->assert_rejects( '1', $rules );
	}

	public function test_number_zero_min_is_enforced() {
		$rules = $this->rules_for( 'form-number-field', array( 'min' => 0 ) );

		$this->assert_accepts( '0', $rules );
		$this->assert_rejects( '-1', $rules );
	}

	public function test_date_bounds() {
		$rules = $this->rules_for(
			'form-date-field',
			array(
				'minDate' => '2026-01-01',
				'maxDate' => '2026-12-31',
			)
		);

		$this->assert_rejects( '2025-12-31', $rules );
		$this->assert_accepts( '2026-06-15', $rules );
		$this->assert_rejects( '2027-01-01', $rules );
		$this->assert_rejects( 'next tuesday', $rules );
	}

	public function test_time_bounds_and_default_step() {
		$rules = $this->rules_for(
			'form-time-field',
			array(
				'minTime' => '09:00',
				'maxTime' => '17:00',
			)
		);

		$this->assert_rejects( '08:59', $rules );
		$this->assert_accepts( '09:00', $rules );
		$this->assert_accepts( '16:30', $rules );
		$this->assert_rejects( '17:01', $rules );
		// Default step is 60 seconds, so seconds must be :00.
		$this->assert_rejects( '10:00:30', $rules );
	}

	public function test_time_range_may_wrap_past_midnight() {
		$rules = $this->rules_for(
			'form-time-field',
			array(
				'minTime' => '22:00',
				'maxTime' => '02:00',
			)
		);

		$this->assert_accepts( '23:00', $rules );
		$this->assert_accepts( '01:00', $rules );
		$this->assert_rejects( '12:00', $rules );
	}

	public function test_array_value_for_a_constrained_field_is_rejected() {
		$rules = $this->rules_for( 'form-text-field', array( 'maxLength' => 5 ) );

		$this->assert_rejects( array( 'a', 'b' ), $rules );
	}

	public function test_fields_without_constraints_get_no_rules() {
		$this->assertSame( array(), $this->rules_for( 'form-text-field', array() ) );
		$this->assertSame( array(), $this->rules_for( 'form-email-field', array() ) );
	}

	public function test_nested_fields_are_found() {
		$rules = Form_Field_Rules::extract(
			array(
				array(
					'blockName'   => 'core/group',
					'attrs'       => array(),
					'innerBlocks' => array(
						array(
							'blockName'   => 'designsetgo/form-textarea-field',
							'attrs'       => array(
								'fieldName' => 'message',
								'maxLength' => 10,
							),
							'innerBlocks' => array(),
						),
					),
				),
			)
		);

		$this->assertSame( array( 'message' => array( 'maxLength' => 10 ) ), $rules );
	}

	public function test_submission_breaking_a_rendered_constraint_is_refused() {
		$form_id = 'rules-max-length-form';
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '","enableEmail":false} --><div class="wp-block-designsetgo-form-builder">'
					. '<!-- wp:designsetgo/form-text-field {"fieldName":"code","maxLength":4} /-->'
					. '</div><!-- /wp:designsetgo/form-builder -->',
			)
		);

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_param( 'formId', $form_id );
		$request->set_param( 'fields', array( array( 'name' => 'code', 'value' => 'too long', 'type' => 'text' ) ) );
		$request->set_param( 'honeypot', '' );
		$request->set_param( 'timestamp', '' );

		$result = ( new Form_Handler() )->handle_form_submission( $request );

		$this->assertWPError( $result );
		$this->assertSame( 'validation_error', $result->get_error_code() );
	}
}

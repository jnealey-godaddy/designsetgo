<?php
/**
 * Conditional field logic is enforced on submission.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_REST_Request;
use DesignSetGo\Blocks\Form_Handler;

/**
 * Conditional submission test case.
 */
class Test_Form_Conditions_Submission extends WP_UnitTestCase {

	/**
	 * Handler.
	 *
	 * @var Form_Handler
	 */
	private $handler;

	public function set_up() {
		parent::set_up();
		$this->handler = new Form_Handler();
	}

	/**
	 * Publish a business/personal form.
	 *
	 * Fields: type (select, required), company (text, required, shown when type is business),
	 * vat (text, shown when company is not empty), phone (phone, shown when type is business).
	 *
	 * @param string $form_id Form ID.
	 * @return int Post ID.
	 */
	private function publish_form( $form_id ) {
		$business = '{"rules":[{"field":"type","op":"is","value":"business"}]}';
		return self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => wp_slash(
					'<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '","enableEmail":false} --><div class="wp-block-designsetgo-form-builder">'
					. '<!-- wp:designsetgo/form-select-field {"fieldName":"type","required":true,"options":[{"label":"Business","value":"business"},{"label":"Personal","value":"personal"}]} /-->'
					. '<!-- wp:designsetgo/form-text-field {"fieldName":"company","required":true,"dsgoConditions":' . $business . '} /-->'
					. '<!-- wp:designsetgo/form-text-field {"fieldName":"vat","dsgoConditions":{"rules":[{"field":"company","op":"not_empty","value":""}]}} /-->'
					. '<!-- wp:designsetgo/form-phone-field {"fieldName":"phone","dsgoConditions":' . $business . '} /-->'
					. '</div><!-- /wp:designsetgo/form-builder -->'
				),
			)
		);
	}

	private function submit( $form_id, $post_id, array $fields ) {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_param( 'formId', $form_id );
		$request->set_param( 'sourcePostId', $post_id );
		$request->set_param( 'fields', $fields );
		$request->set_param( 'honeypot', '' );
		$request->set_param( 'timestamp', '' );
		return $this->handler->handle_form_submission( $request );
	}

	private function stored( $result ) {
		$this->assertNotWPError( $result, is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : '' );
		return (array) get_post_meta( $result->get_data()['submissionId'], '_dsg_form_fields', true );
	}

	public function test_hidden_required_field_does_not_block() {
		$post = $this->publish_form( 'cond1' );
		$stored = $this->stored( $this->submit( 'cond1', $post, array( array( 'name' => 'type', 'value' => 'personal', 'type' => 'select' ) ) ) );
		$this->assertSame( array( 'type' ), array_keys( $stored ) );
	}

	public function test_visible_required_field_is_enforced() {
		$post   = $this->publish_form( 'cond2' );
		$result = $this->submit( 'cond2', $post, array( array( 'name' => 'type', 'value' => 'business', 'type' => 'select' ) ) );
		$this->assertWPError( $result );
		$this->assertSame( 'required_field_missing', $result->get_error_code() );
	}

	public function test_values_smuggled_into_hidden_fields_are_dropped_with_chain_and_country_code() {
		$post     = $this->publish_form( 'cond3' );
		$captured = null;
		$listener = function ( $id, $form_id, $fields ) use ( &$captured ) {
			$captured = $fields;
		};
		add_action( 'designsetgo_form_submitted', $listener, 10, 3 );

		$stored = $this->stored(
			$this->submit(
				'cond3',
				$post,
				array(
					array( 'name' => 'type', 'value' => 'personal', 'type' => 'select' ),
					array( 'name' => 'company', 'value' => 'Smuggled Inc', 'type' => 'text' ),
					array( 'name' => 'vat', 'value' => 'GB123', 'type' => 'text' ),
					array( 'name' => 'phone', 'value' => '555 123 4567', 'type' => 'tel' ),
					array( 'name' => 'phone_country_code', 'value' => '+1', 'type' => 'country_code' ),
				)
			)
		);
		remove_action( 'designsetgo_form_submitted', $listener, 10 );

		// company hidden (type is personal) => vat hidden too (its source is hidden) => phone + country code hidden.
		$this->assertSame( array( 'type' ), array_keys( $stored ) );
		$this->assertSame( array( 'type' ), array_keys( $captured ) );
	}

	public function test_visible_conditional_fields_are_kept() {
		$post   = $this->publish_form( 'cond4' );
		$stored = $this->stored(
			$this->submit(
				'cond4',
				$post,
				array(
					array( 'name' => 'type', 'value' => 'business', 'type' => 'select' ),
					array( 'name' => 'company', 'value' => 'Acme', 'type' => 'text' ),
					array( 'name' => 'vat', 'value' => 'GB123', 'type' => 'text' ),
				)
			)
		);
		$this->assertSame( array( 'type', 'company', 'vat' ), array_keys( $stored ) );
	}

	public function test_form_without_conditions_is_unchanged() {
		$post = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"cond5","enableEmail":false} --><div class="wp-block-designsetgo-form-builder"><!-- wp:designsetgo/form-text-field {"fieldName":"a","required":true} /--></div><!-- /wp:designsetgo/form-builder -->',
			)
		);
		$result = $this->submit( 'cond5', $post, array() );
		$this->assertWPError( $result );
		$this->assertSame( 'required_field_missing', $result->get_error_code() );
	}

	public function test_definition_cache_keys_were_bumped() {
		$this->assertSame( 'dsgo_form_definition_v5_', Form_Handler::DEFINITION_CACHE_PREFIX );
		$this->assertSame( 'dsgo_form_external_definitions_v4', Form_Handler::EXTERNAL_DEFINITIONS_CACHE );
	}
}

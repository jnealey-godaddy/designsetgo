<?php
/**
 * Fields inside form steps are found and enforced on submission.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_REST_Request;
use DesignSetGo\Blocks\Form_Handler;

/**
 * Form step submission test case.
 */
class Test_Form_Step_Submission extends WP_UnitTestCase {

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
	 * Publish a two-step form.
	 *
	 * @param string $form_id Form ID.
	 * @return int Post ID.
	 */
	private function publish_form( $form_id ) {
		return self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => wp_slash(
					'<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '","enableEmail":false} --><div class="wp-block-designsetgo-form-builder">'
					. '<!-- wp:designsetgo/form-step {"title":"One"} --><!-- wp:designsetgo/form-text-field {"fieldName":"name","label":"Name","required":true} /--><!-- /wp:designsetgo/form-step -->'
					. '<!-- wp:designsetgo/form-step {"title":"Two"} --><!-- wp:designsetgo/form-select-field {"fieldName":"type","options":[{"label":"Business","value":"business"},{"label":"Personal","value":"personal"}]} /--><!-- wp:designsetgo/form-text-field {"fieldName":"company","required":true,"minLength":3,"dsgoConditions":{"rules":[{"field":"type","op":"is","value":"business"}]}} /--><!-- /wp:designsetgo/form-step -->'
					. '</div><!-- /wp:designsetgo/form-builder -->'
				),
			)
		);
	}

	private function submit( $form_id, $post_id, array $values ) {
		$fields = array();
		foreach ( $values as $name => $value ) {
			$fields[] = array(
				'name'  => $name,
				'value' => $value,
				'type'  => 'type' === $name ? 'select' : 'text',
			);
		}
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_param( 'formId', $form_id );
		$request->set_param( 'sourcePostId', $post_id );
		$request->set_param( 'fields', $fields );
		$request->set_param( 'honeypot', '' );
		$request->set_param( 'timestamp', '' );
		return $this->handler->handle_form_submission( $request );
	}

	public function test_required_field_in_a_step_is_enforced() {
		$post   = $this->publish_form( 'step1' );
		$result = $this->submit( 'step1', $post, array( 'type' => 'personal' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'required_field_missing', $result->get_error_code() );
	}

	public function test_fields_in_steps_are_stored_with_labels() {
		$post   = $this->publish_form( 'step2' );
		$result = $this->submit( 'step2', $post, array( 'name' => 'Pat', 'type' => 'personal' ) );
		$this->assertNotWPError( $result, is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : '' );
		$stored = (array) get_post_meta( $result->get_data()['submissionId'], '_dsg_form_fields', true );
		$this->assertSame( array( 'name', 'type' ), array_keys( $stored ) );
		$this->assertSame( 'Name', $stored['name']['label'] );
	}

	public function test_condition_and_required_work_through_a_step() {
		$post   = $this->publish_form( 'step3' );
		$result = $this->submit( 'step3', $post, array( 'name' => 'Pat', 'type' => 'business' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'required_field_missing', $result->get_error_code() );
	}

	public function test_min_length_rule_applies_through_a_step() {
		$post   = $this->publish_form( 'step4' );
		$result = $this->submit( 'step4', $post, array( 'name' => 'Pat', 'type' => 'business', 'company' => 'AB' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'validation_error', $result->get_error_code() );
	}
}

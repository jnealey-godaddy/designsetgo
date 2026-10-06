<?php
/**
 * Field labels captured with each stored submission, and the block attributes
 * passed to designsetgo_form_submitted.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_Block_Type_Registry;
use DesignSetGo\Blocks\Form_Handler;

/**
 * Form submission labels test case.
 */
class Test_Form_Submission_Labels extends WP_UnitTestCase {

	/**
	 * Handler under test.
	 *
	 * @var Form_Handler
	 */
	private $handler;

	/**
	 * Set up the handler.
	 */
	public function set_up() {
		parent::set_up();
		$this->handler = new Form_Handler();
	}

	/**
	 * Publish a post containing a form.
	 *
	 * @param string $form_id    Form ID.
	 * @param string $fields     Serialized field blocks.
	 * @param string $extra_attr Extra JSON members for the form-builder comment (leading comma included).
	 * @return int Post ID.
	 */
	private function publish_form( $form_id, $fields, $extra_attr = '' ) {
		return self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				// wp_insert_post() unslashes; slash so the JSON \u escapes survive.
					'post_content' => wp_slash(
						'<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '","enableEmail":false' . $extra_attr . '} -->'
						. '<div class="wp-block-designsetgo-form-builder">' . $fields . '</div>'
						. '<!-- /wp:designsetgo/form-builder -->'
					),
			)
		);
	}

	/**
	 * Submit through the shared handler.
	 *
	 * @param string $form_id Form ID.
	 * @param array  $fields  List of { name, value, type }.
	 * @param int    $post_id Source post ID.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function submit( $form_id, array $fields, $post_id ) {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_param( 'formId', $form_id );
		$request->set_param( 'fields', $fields );
		$request->set_param( 'honeypot', '' );
		$request->set_param( 'timestamp', '' );
		$request->set_param( 'sourcePostId', $post_id );

		return $this->handler->handle_form_submission( $request );
	}

	/**
	 * Return stored fields for a successful result.
	 *
	 * @param mixed $result Handler result.
	 * @return array Stored field map.
	 */
	private function stored( $result ) {
		$this->assertNotWPError( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		return (array) get_post_meta( $result->get_data()['submissionId'], '_dsg_form_fields', true );
	}

	public function test_labels_are_stored_as_plain_text() {
		$post_id = $this->publish_form(
			'labels1',
			'<!-- wp:designsetgo/form-text-field {"fieldName":"your_name","label":"Your \u003cstrong\u003ename\u003c/strong\u003e \u0026amp; title"} /-->'
			. '<!-- wp:designsetgo/form-email-field {"fieldName":"email"} /-->'
		);

		$stored = $this->stored(
			$this->submit(
				'labels1',
				array(
					array( 'name' => 'your_name', 'value' => 'Pat', 'type' => 'text' ),
					array( 'name' => 'email', 'value' => 'pat@example.com', 'type' => 'email' ),
				),
				$post_id
			)
		);

		$this->assertSame( 'Your name & title', $stored['your_name']['label'] );
		$this->assertSame( 'Pat', $stored['your_name']['value'] );

		// A label left at its block.json default is not in the block comment; the default is used.
		$default = WP_Block_Type_Registry::get_instance()->get_registered( 'designsetgo/form-email-field' )->attributes['label']['default'];
		$this->assertSame( $default, $stored['email']['label'] );
	}

	public function test_phone_country_code_gets_a_derived_label() {
		$post_id = $this->publish_form( 'labels2', '<!-- wp:designsetgo/form-phone-field {"fieldName":"phone","label":"Mobile"} /-->' );

		$stored = $this->stored(
			$this->submit(
				'labels2',
				array(
					array( 'name' => 'phone', 'value' => '555 123 4567', 'type' => 'tel' ),
					array( 'name' => 'phone_country_code', 'value' => '+1', 'type' => 'country_code' ),
				),
				$post_id
			)
		);

		$this->assertSame( 'Mobile (country code)', $stored['phone_country_code']['label'] );
	}

	public function test_hidden_field_has_no_label_key() {
		$post_id = $this->publish_form( 'labels3', '<!-- wp:designsetgo/form-hidden-field {"fieldName":"source","value":"ad"} /-->' );

		$stored = $this->stored(
			$this->submit( 'labels3', array( array( 'name' => 'source', 'value' => 'ad', 'type' => 'hidden' ) ), $post_id )
		);

		$this->assertArrayNotHasKey( 'label', $stored['source'] );
	}

	public function test_submitted_action_receives_block_attributes() {
		$captured = null;
		$listener = function ( $submission_id, $form_id, $fields, $attrs = null ) use ( &$captured ) {
			$captured = $attrs;
		};
		add_action( 'designsetgo_form_submitted', $listener, 10, 4 );

		$post_id = $this->publish_form( 'labels4', '<!-- wp:designsetgo/form-text-field {"fieldName":"a"} /-->' );
		$this->stored( $this->submit( 'labels4', array( array( 'name' => 'a', 'value' => 'x', 'type' => 'text' ) ), $post_id ) );

		remove_action( 'designsetgo_form_submitted', $listener, 10 );

		$this->assertIsArray( $captured );
		$this->assertSame( 'labels4', $captured['formId'] );
		$this->assertFalse( $captured['enableEmail'] );
		$this->assertArrayHasKey( 'successMessage', $captured, 'Defaults are merged in.' );
	}
}

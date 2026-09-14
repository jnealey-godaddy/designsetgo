<?php
/**
 * Forms that share a form ID resolve to the copy the visitor submitted.
 *
 * DSGo patterns ship fixed form IDs (`contact-professional` is in two of them),
 * so a site that uses both — or copies a form between pages — ends up with
 * several published forms under one ID. The handler used to take whichever of
 * those the database returned first, then validated the submission against,
 * and emailed it using, a form on a different page. Reported on wordpress.org
 * as "form submits, success message shows, no email arrives".
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Response;
use DesignSetGo\Blocks\Form_Handler;

/**
 * Duplicate form ID resolution test case.
 *
 * @group forms
 */
class Test_Form_Duplicate_Form_Id extends WP_UnitTestCase {

	const FORM_ID = 'contact-professional';

	/**
	 * Form handler under test.
	 *
	 * @var Form_Handler
	 */
	private $handler;

	/**
	 * Captured wp_mail() arguments.
	 *
	 * @var array<string, mixed>|null
	 */
	private $sent;

	/**
	 * Set up the handler and capture outgoing mail.
	 */
	public function set_up() {
		parent::set_up();
		$this->handler = new Form_Handler();
		$this->sent    = null;

		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $atts ) {
				$this->sent = $atts;
				return true;
			},
			10,
			2
		);

		// Block markup is an HTML comment, which kses strips without unfiltered_html.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Tear down request state.
	 */
	public function tear_down() {
		unset( $_SERVER['HTTP_REFERER'] );
		parent::tear_down();
	}

	/**
	 * Create a page holding a form under the shared ID.
	 *
	 * @param string $email_to    Notification recipient for this copy.
	 * @param string $fields      Serialized field blocks.
	 * @param string $post_status Post status.
	 * @return int Page ID.
	 */
	private function create_form_page( $email_to, $fields = '', $post_status = 'publish' ) {
		$fields = $fields ? $fields : '<!-- wp:designsetgo/form-text-field {"fieldName":"your_name"} /-->';

		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => $post_status,
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"' . self::FORM_ID . '","emailTo":"' . $email_to . '"} -->'
					. '<div class="wp-block-designsetgo-form-builder">' . $fields . '</div>'
					. '<!-- /wp:designsetgo/form-builder -->',
			)
		);
	}

	/**
	 * Submit through the REST callback.
	 *
	 * @param int $source_post_id Page the form was submitted from, or 0 to omit.
	 * @return WP_REST_Response|\WP_Error
	 */
	private function submit( $source_post_id = 0 ) {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_param( 'formId', self::FORM_ID );
		$request->set_param(
			'fields',
			array(
				array(
					'name'  => 'your_name',
					'value' => 'Pat',
					'type'  => 'text',
				),
			)
		);
		$request->set_param( 'honeypot', '' );
		$request->set_param( 'timestamp', '' );
		if ( $source_post_id ) {
			$request->set_param( 'sourcePostId', $source_post_id );
		}

		return $this->handler->handle_form_submission( $request );
	}

	/**
	 * Assert the submission succeeded.
	 *
	 * @param WP_REST_Response|\WP_Error $result Handler result.
	 */
	private function assert_accepted( $result ) {
		$this->assertNotWPError( $result, is_wp_error( $result ) ? $result->get_error_code() : '' );
		$this->assertInstanceOf( WP_REST_Response::class, $result );
	}

	/**
	 * The email goes out with the settings of the form the visitor used.
	 */
	public function test_email_uses_the_form_on_the_source_page() {
		$this->create_form_page( 'other-page@example.com' );
		$source = $this->create_form_page( 'contact-page@example.com' );

		$this->assert_accepted( $this->submit( $source ) );

		$this->assertNotNull( $this->sent, 'A notification should be sent.' );
		$this->assertSame( 'contact-page@example.com', $this->sent['to'] );
	}

	/**
	 * Validation uses the field schema of the form the visitor used.
	 *
	 * The other copy requires a field the source page's form doesn't have, so
	 * validating against the wrong copy rejects a complete submission.
	 */
	public function test_validation_uses_the_form_on_the_source_page() {
		$this->create_form_page( 'other-page@example.com', '<!-- wp:designsetgo/form-text-field {"fieldName":"company","required":true} /-->' );
		$source = $this->create_form_page( 'contact-page@example.com' );

		$this->assert_accepted( $this->submit( $source ) );
	}

	/**
	 * The page is also taken from the referer when the request doesn't name one.
	 *
	 * The no-JS admin-post path always carries a referer, and cached pages may
	 * run an older view.js that doesn't send sourcePostId.
	 */
	public function test_source_page_falls_back_to_the_referer() {
		$this->create_form_page( 'other-page@example.com' );
		$source = $this->create_form_page( 'contact-page@example.com' );

		$_SERVER['HTTP_REFERER'] = get_permalink( $source );

		$this->assert_accepted( $this->submit() );

		$this->assertSame( 'contact-page@example.com', $this->sent['to'] );
	}

	/**
	 * A source page that isn't published can't supply the form.
	 *
	 * The page ID comes from the client. Naming a draft must not make its form
	 * reachable, so resolution falls back to the published copy.
	 */
	public function test_unpublished_source_page_is_ignored() {
		$this->create_form_page( 'published@example.com' );
		$draft = $this->create_form_page( 'draft@example.com', '', 'draft' );

		$this->assert_accepted( $this->submit( $draft ) );

		$this->assertSame( 'published@example.com', $this->sent['to'] );
	}

	/**
	 * A source page without the form falls back to the site-wide lookup.
	 *
	 * A form in a footer template part is submitted from a page whose own
	 * content has no form at all.
	 */
	public function test_source_page_without_the_form_falls_back() {
		$this->create_form_page( 'published@example.com' );
		$unrelated = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assert_accepted( $this->submit( $unrelated ) );

		$this->assertSame( 'published@example.com', $this->sent['to'] );
	}
}

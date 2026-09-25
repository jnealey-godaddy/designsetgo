<?php
/**
 * Form submission contract tests.
 *
 * The server resolves each form's field schema from the saved block, so every
 * control the rendered form submits has to be something that schema knows
 * about. These tests submit what a browser actually sends — read from the
 * rendered field markup — rather than a hand-picked payload, which is how a
 * default Phone field shipped rejecting every submission.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use DOMDocument;
use DOMXPath;
use DesignSetGo\Blocks\Form_Handler;

/**
 * Form submission contract test case.
 */
class Test_Form_Submission_Contract extends WP_UnitTestCase {

	/**
	 * Names form-builder/view.js strips before it sends the field list.
	 */
	const CLIENT_SKIPPED_NAMES = array( 'dsg_website', 'dsg_form_id', 'dsg_timestamp', 'dsg_turnstile_token' );

	/**
	 * Form handler under test.
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
	 * Wrap field block markup in a form-builder block.
	 *
	 * @param string $form_id Form ID.
	 * @param string $fields  Serialized field blocks.
	 * @return string Serialized form block.
	 */
	private function form_markup( $form_id, $fields ) {
		return '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '","enableEmail":false} -->'
			. '<div class="wp-block-designsetgo-form-builder">' . $fields . '</div>'
			. '<!-- /wp:designsetgo/form-builder -->';
	}

	/**
	 * Submit a field list through the same handler the REST and AJAX paths use.
	 *
	 * @param string $form_id Form ID.
	 * @param array  $fields  List of { name, value, type } entries.
	 * @return WP_REST_Response|WP_Error
	 */
	private function submit( $form_id, array $fields ) {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_param( 'formId', $form_id );
		$request->set_param( 'fields', $fields );
		$request->set_param( 'honeypot', '' );
		$request->set_param( 'timestamp', '' );

		return $this->handler->handle_form_submission( $request );
	}

	/**
	 * Assert a submission succeeded and return the stored field map.
	 *
	 * @param WP_REST_Response|WP_Error $result Handler result.
	 * @return array Stored fields keyed by name.
	 */
	private function assert_stored( $result ) {
		$this->assertNotWPError( $result, is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : '' );
		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$data = $result->get_data();
		$this->assertTrue( $data['success'] );

		return (array) get_post_meta( $data['submissionId'], '_dsg_form_fields', true );
	}

	/**
	 * Build the field list a browser submits for rendered field markup.
	 *
	 * Mirrors FormData plus form-builder/view.js: every named, enabled control
	 * is sent except the system names view.js skips, and a country-code select
	 * carries the code form-phone-field/view.js pre-selects when it fills in
	 * the options.
	 *
	 * @param string $html Rendered field markup.
	 * @return array List of { name, value, type } entries.
	 */
	private function browser_fields( $html ) {
		$sample = array(
			'email'  => 'pat@example.com',
			'tel'    => '555 123 4567',
			'number' => '3',
			'url'    => 'https://example.com',
			'date'   => '2026-09-10',
			'time'   => '10:30',
		);

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();

		$fields = array();
		foreach ( ( new DOMXPath( $dom ) )->query( '//input[@name] | //select[@name] | //textarea[@name]' ) as $control ) {
			$name = $control->getAttribute( 'name' );
			if ( '' === $name || $control->hasAttribute( 'disabled' ) || in_array( $name, self::CLIENT_SKIPPED_NAMES, true ) ) {
				continue;
			}

			$type = strtolower( $control->getAttribute( 'type' ) );
			if ( 'select' === $control->nodeName ) {
				$value = $control->getAttribute( 'data-dsgo-country-code' );
				if ( '' === $value ) {
					foreach ( $control->getElementsByTagName( 'option' ) as $option ) {
						if ( '' !== $option->getAttribute( 'value' ) ) {
							$value = $option->getAttribute( 'value' );
							break;
						}
					}
				}
				$type = 'select-one';
			} elseif ( 'textarea' === $control->nodeName ) {
				$value = 'Hello there';
				$type  = 'textarea';
			} elseif ( in_array( $type, array( 'checkbox', 'radio', 'hidden' ), true ) ) {
				$value = $control->getAttribute( 'value' );
			} else {
				$value = isset( $sample[ $type ] ) ? $sample[ $type ] : 'Pat';
			}

			$fields[] = array(
				'name'  => $name,
				'value' => $value,
				'type'  => $control->getAttribute( 'data-field-type' ) ? $control->getAttribute( 'data-field-type' ) : $type,
			);
		}

		return $fields;
	}

	/**
	 * Every control a rendered form submits with its default settings is accepted.
	 */
	public function test_every_control_a_rendered_form_submits_is_accepted() {
		$form_id = 'contract-every-field';
		$fields  = '<!-- wp:designsetgo/form-text-field {"fieldName":"your_name","required":true} /-->'
			. '<!-- wp:designsetgo/form-email-field {"fieldName":"email"} /-->'
			. '<!-- wp:designsetgo/form-phone-field {"fieldName":"phone"} /-->'
			. '<!-- wp:designsetgo/form-select-field {"fieldName":"topic","options":[{"label":"Sales","value":"sales"},{"label":"Support","value":"support"}]} /-->'
			. '<!-- wp:designsetgo/form-checkbox-field {"fieldName":"agree"} /-->'
			. '<!-- wp:designsetgo/form-hidden-field {"fieldName":"source","value":"landing"} /-->'
			. '<!-- wp:designsetgo/form-textarea-field {"fieldName":"message"} /-->'
			. '<!-- wp:designsetgo/form-number-field {"fieldName":"seats"} /-->'
			. '<!-- wp:designsetgo/form-url-field {"fieldName":"site"} /-->'
			. '<!-- wp:designsetgo/form-date-field {"fieldName":"day"} /-->'
			. '<!-- wp:designsetgo/form-time-field {"fieldName":"at"} /-->';
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->form_markup( $form_id, $fields ),
			)
		);

		$browser_fields = $this->browser_fields( do_blocks( $fields ) );
		$this->assertContains( 'phone_country_code', wp_list_pluck( $browser_fields, 'name' ), 'The default Phone field renders a country-code select.' );

		$stored = $this->assert_stored( $this->submit( $form_id, $browser_fields ) );

		$this->assertSame( '+1', $stored['phone_country_code']['value'] );
		$this->assertSame( 'Pat', $stored['your_name']['value'] );
	}

	/**
	 * The country code is stored, so it must look like a dialling code.
	 */
	public function test_phone_country_code_must_be_a_dialling_code() {
		$form_id = 'contract-country-code';
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->form_markup( $form_id, '<!-- wp:designsetgo/form-phone-field {"fieldName":"phone"} /-->' ),
			)
		);

		$result = $this->submit(
			$form_id,
			array(
				array( 'name' => 'phone', 'value' => '5551234567', 'type' => 'tel' ),
				array( 'name' => 'phone_country_code', 'value' => '<b>+1</b>', 'type' => 'select-one' ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'validation_error', $result->get_error_code() );
	}

	/**
	 * A Phone field with its country selector turned off declares no country code.
	 */
	public function test_phone_field_without_country_selector_stores_no_country_code() {
		$form_id = 'contract-no-country-code';
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->form_markup( $form_id, '<!-- wp:designsetgo/form-phone-field {"fieldName":"phone","showCountryCode":false} /-->' ),
			)
		);

		$stored = $this->assert_stored(
			$this->submit(
				$form_id,
				array(
					array( 'name' => 'phone', 'value' => '5551234567', 'type' => 'tel' ),
					array( 'name' => 'phone_country_code', 'value' => '+44', 'type' => 'select-one' ),
				)
			)
		);

		$this->assertArrayNotHasKey( 'phone_country_code', $stored );
	}

	/**
	 * Controls the saved form doesn't declare are dropped rather than failing the submission.
	 *
	 * Cloudflare Turnstile injects cf-turnstile-response inside the form, and
	 * other plugins add their own hidden inputs; none of that is the visitor's
	 * data, and none of it should make the form unusable.
	 */
	public function test_names_the_form_does_not_declare_are_dropped_not_stored() {
		$form_id = 'contract-undeclared-names';
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->form_markup( $form_id, '<!-- wp:designsetgo/form-text-field {"fieldName":"your_name"} /-->' ),
			)
		);

		$stored = $this->assert_stored(
			$this->submit(
				$form_id,
				array(
					array( 'name' => 'your_name', 'value' => 'Pat', 'type' => 'text' ),
					array( 'name' => 'cf-turnstile-response', 'value' => 'XXXX.DUMMY.TOKEN.XXXX', 'type' => 'hidden' ),
					array( 'name' => 'injected', 'value' => 'attacker@example.com', 'type' => 'email' ),
				)
			)
		);

		$this->assertSame( array( 'your_name' ), array_keys( $stored ) );
	}

	/**
	 * A form placed in a block widget accepts submissions.
	 */
	public function test_form_in_a_block_widget_accepts_submissions() {
		$form_id = 'contract-widget-form';
		update_option(
			'widget_block',
			array(
				2              => array( 'content' => $this->form_markup( $form_id, '<!-- wp:designsetgo/form-text-field {"fieldName":"your_name"} /-->' ) ),
				'_multiwidget' => 1,
			)
		);

		$stored = $this->assert_stored( $this->submit( $form_id, array( array( 'name' => 'your_name', 'value' => 'Pat', 'type' => 'text' ) ) ) );

		$this->assertSame( 'Pat', $stored['your_name']['value'] );
	}

	/**
	 * A form in a block template that isn't saved to the database accepts submissions.
	 *
	 * Theme-file templates and plugin-registered templates never appear in
	 * wp_posts until someone edits them in the Site Editor.
	 */
	public function test_form_in_a_registered_block_template_accepts_submissions() {
		$form_id = 'contract-template-form';
		$name    = 'designsetgo//contract-form-template';
		register_block_template(
			$name,
			array(
				'title'   => 'Contract form template',
				'content' => $this->form_markup( $form_id, '<!-- wp:designsetgo/form-text-field {"fieldName":"your_name"} /-->' ),
			)
		);

		try {
			$stored = $this->assert_stored( $this->submit( $form_id, array( array( 'name' => 'your_name', 'value' => 'Pat', 'type' => 'text' ) ) ) );
		} finally {
			unregister_block_template( $name );
		}

		$this->assertSame( 'Pat', $stored['your_name']['value'] );
	}

	/**
	 * A form in a registered pattern — rendered in templates via wp:pattern — accepts submissions.
	 */
	public function test_form_in_a_registered_pattern_accepts_submissions() {
		$form_id = 'contract-pattern-form';
		$name    = 'designsetgo-test/contract-form-pattern';
		register_block_pattern(
			$name,
			array(
				'title'   => 'Contract form pattern',
				'content' => $this->form_markup( $form_id, '<!-- wp:designsetgo/form-text-field {"fieldName":"your_name"} /-->' ),
			)
		);

		try {
			$stored = $this->assert_stored( $this->submit( $form_id, array( array( 'name' => 'your_name', 'value' => 'Pat', 'type' => 'text' ) ) ) );
		} finally {
			unregister_block_pattern( $name );
		}

		$this->assertSame( 'Pat', $stored['your_name']['value'] );
	}

	/**
	 * A form on a private page stays closed to submissions.
	 *
	 * Its ID never reaches the public, so accepting it would only open a
	 * submission endpoint nobody is meant to reach.
	 */
	public function test_form_on_a_private_page_is_refused() {
		$form_id = 'contract-private-form';
		self::factory()->post->create(
			array(
				'post_status'  => 'private',
				'post_content' => $this->form_markup( $form_id, '<!-- wp:designsetgo/form-text-field {"fieldName":"your_name"} /-->' ),
			)
		);

		$result = $this->submit( $form_id, array( array( 'name' => 'your_name', 'value' => 'Pat', 'type' => 'text' ) ) );

		$this->assertWPError( $result );
		$this->assertSame( 'unknown_form', $result->get_error_code() );
	}

	/**
	 * The site-wide lookup (no source page) must not find a form on a
	 * password-protected page: it is cached for every requester, so it cannot
	 * honour anyone's password cookie. The source-page path, which does, still
	 * serves a visitor who unlocked the page.
	 */
	public function test_form_on_a_password_protected_page_is_refused_without_the_password() {
		$form_id = 'contract-password-form';
		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_content'  => $this->form_markup( $form_id, '<!-- wp:designsetgo/form-text-field {"fieldName":"your_name"} /-->' ),
			)
		);

		$fields = array( array( 'name' => 'your_name', 'value' => 'Pat', 'type' => 'text' ) );

		$result = $this->submit( $form_id, $fields );
		$this->assertWPError( $result );
		$this->assertSame( 'unknown_form', $result->get_error_code() );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/form/submit' );
		$request->set_param( 'formId', $form_id );
		$request->set_param( 'fields', $fields );
		$request->set_param( 'sourcePostId', $post_id );
		$request->set_param( 'honeypot', '' );
		$request->set_param( 'timestamp', '' );
		$result = $this->handler->handle_form_submission( $request );
		$this->assertWPError( $result, 'Naming the page must not bypass its password either.' );
		$this->assertSame( 'unknown_form', $result->get_error_code() );
	}

	/**
	 * A form kept in a synced pattern is only reachable through the site-wide
	 * lookup, and wp_block is not a viewable post type — it must stay eligible.
	 */
	public function test_form_in_a_synced_pattern_accepts_submissions() {
		$form_id = 'contract-synced-pattern-form';
		self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => $this->form_markup( $form_id, '<!-- wp:designsetgo/form-text-field {"fieldName":"your_name"} /-->' ),
			)
		);

		$stored = $this->assert_stored( $this->submit( $form_id, array( array( 'name' => 'your_name', 'value' => 'Pat', 'type' => 'text' ) ) ) );
		$this->assertArrayHasKey( 'your_name', $stored );
	}
}

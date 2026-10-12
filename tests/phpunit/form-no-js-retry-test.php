<?php
/**
 * Form Builder: a failed submission without JavaScript.
 *
 * The visitor comes back to a fresh page. These tests submit what a browser
 * posts from real rendered markup, then render the page the handler
 * redirects to, with the answers cookie the browser would carry.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DOMDocument;
use DOMXPath;
use DesignSetGo\Blocks\Form_Handler;
use DesignSetGo\Blocks\Form_No_JS_Retry;
use DesignSetGo\Blocks\Form_No_JS_Refill;
use DesignSetGo\Abilities\Serializers\Form_Builder_Serializer;

/**
 * No-JS retry test case.
 */
class Test_Form_No_JS_Retry extends WP_UnitTestCase {

	/**
	 * Handler.
	 *
	 * @var Form_Handler
	 */
	private $handler;

	public function set_up() {
		parent::set_up();
		$this->handler = new Form_Handler();
		Form_No_JS_Retry::reset();
	}

	public function tear_down() {
		$_GET = array();
		unset( $_COOKIE[ Form_No_JS_Retry::COOKIE ] );
		Form_No_JS_Retry::reset();
		parent::tear_down();
	}

	/**
	 * Serialized form with text, email, textarea, select and checkbox fields.
	 *
	 * @param string $form_id Form ID.
	 * @return string
	 */
	private function form_block( $form_id ) {
		$attrs   = array(
			'formId'       => $form_id,
			'enableEmail'  => false,
			'errorMessage' => 'Could not send.',
		);
		$wrapper = Form_Builder_Serializer::wrapper( 'designsetgo/form-builder', $attrs );

		return '<!-- wp:designsetgo/form-builder ' . serialize_block_attributes( $attrs ) . ' -->'
			. $wrapper['opening']
			. '<!-- wp:designsetgo/form-text-field {"fieldName":"your_name","label":"Your name","required":true} /-->'
			. '<!-- wp:designsetgo/form-email-field {"fieldName":"email","label":"Email address"} /-->'
			. '<!-- wp:designsetgo/form-textarea-field {"fieldName":"message","label":"Message"} /-->'
			. '<!-- wp:designsetgo/form-select-field {"fieldName":"topic","label":"Topic","options":[{"label":"Sales","value":"sales"},{"label":"Support","value":"support"}]} /-->'
			. '<!-- wp:designsetgo/form-checkbox-field {"fieldName":"agree","label":"I agree","checkedByDefault":true} /-->'
			. $wrapper['closing']
			. '<!-- /wp:designsetgo/form-builder -->';
	}

	/**
	 * Parse rendered HTML.
	 *
	 * @param string $html Rendered HTML.
	 * @return DOMXPath
	 */
	private function xpath( $html ) {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();

		return new DOMXPath( $dom );
	}

	/**
	 * Publish the form, then post what a browser would send from it.
	 *
	 * @param string $form_id Form ID.
	 * @param array  $answers Field name => answer (overrides rendered values).
	 * @return array{0: string, 1: string, 2: array<int, array<string, string>>} Redirect location, markup and the posted answer fields.
	 */
	private function submit( $form_id, array $answers ) {
		$markup  = $this->form_block( $form_id );
		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => wp_slash( $markup ),
			)
		);
		$xpath   = $this->xpath( do_blocks( $markup ) );
		$post    = array();
		foreach ( $xpath->query( '//form//input[@name]' ) as $input ) {
			$post[ $input->getAttribute( 'name' ) ] = $input->getAttribute( 'value' );
		}
		unset( $post['agree'] );
		$post = array_merge( $post, $answers );
		// Old enough to pass the "too fast" check.
		$post['dsg_timestamp'] = (string) ( ( time() - 10 ) * 1000 );

		$_POST                   = wp_slash( $post );
		$_SERVER['HTTP_REFERER'] = get_permalink( $post_id );
		$throw                   = static function ( $location ) {
			throw new \RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only control flow.
		};
		add_filter( 'wp_redirect', $throw );
		$location = '';
		try {
			$this->handler->handle_post_submission();
		} catch ( \RuntimeException $redirect ) {
			$location = $redirect->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $throw );
			$_POST = array();
			unset( $_SERVER['HTTP_REFERER'] );
		}

		$fields = array();
		foreach ( $answers as $name => $value ) {
			$fields[] = array(
				'name'  => $name,
				'value' => $value,
				'type'  => 'text',
			);
		}
		return array( $location, $markup, $fields );
	}

	/**
	 * Render the redirect target with the browser's answers cookie.
	 *
	 * @param string $location Redirect location.
	 * @param string $markup   Form block markup.
	 * @param string $cookie   Cookie value, or ''.
	 * @return DOMXPath
	 */
	private function return_to( $location, $markup, $cookie ) {
		$query = array();
		wp_parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
		$_GET = array_merge( $_GET, $query );
		if ( '' !== $cookie ) {
			$_COOKIE[ Form_No_JS_Retry::COOKIE ] = $cookie;
		}
		Form_No_JS_Retry::capture();

		return $this->xpath( do_blocks( $markup ) );
	}

	public function test_missing_required_field_is_named_and_answers_come_back() {
		$answers                           = array(
			'your_name' => '',
			'email'     => 'pat@example.com',
			'message'   => "Line one\n<b>two</b> & \"three\"",
			'topic'     => 'support',
		);
		list( $location, $markup, $fields ) = $this->submit( 'retry-required', $answers );

		$this->assertStringContainsString( 'dsgo_form_error=required_field_missing', $location );
		$this->assertStringContainsString( 'dsgo_form_field=your_name', $location );

		// The cookie the handler sent, not one this test built.
		$cookie = (string) Form_No_JS_Retry::sent();
		$this->assertNotSame( '', $cookie );
		$decoded = Form_No_JS_Retry::decode( $cookie );
		$this->assertSame( 'retry-required', $decoded[0] ?? '' );
		foreach ( $fields as $field ) {
			if ( '' !== $field['value'] ) {
				$this->assertSame( $field['value'], $decoded[1][ $field['name'] ] ?? null, $field['name'] );
			}
		}

		$xpath   = $this->return_to( $location, $markup, $cookie );
		$message = $xpath->query( '//*[@data-dsgo-server-message]' )->item( 0 );

		$this->assertSame( 'Could not send. Please fill in “Your name”.', $message->textContent );
		$this->assertSame( '-1', $message->getAttribute( 'tabindex' ), 'Focusable, so the #anchor the redirect lands on focuses it.' );
		$this->assertSame( 'pat@example.com', $xpath->query( '//input[@name="email"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( "Line one\n<b>two</b> & \"three\"", $xpath->query( '//textarea[@name="message"]' )->item( 0 )->textContent );
		$this->assertSame( 'selected', $xpath->query( '//select[@name="topic"]/option[@value="support"]' )->item( 0 )->getAttribute( 'selected' ) );
		$this->assertFalse( $xpath->query( '//select[@name="topic"]/option[@value="sales"]' )->item( 0 )->hasAttribute( 'selected' ) );
		$this->assertFalse( $xpath->query( '//input[@name="agree"]' )->item( 0 )->hasAttribute( 'checked' ), 'A box the visitor left unticked stays unticked.' );

		$name = $xpath->query( '//input[@name="your_name"]' )->item( 0 );
		$this->assertSame( 'true', $name->getAttribute( 'aria-invalid' ) );
		$this->assertStringContainsString( $message->getAttribute( 'id' ), $name->getAttribute( 'aria-describedby' ) );
	}

	public function test_retry_from_an_error_page_drops_the_earlier_reason() {
		list( $location ) = $this->submit( 'retry-again', array( 'your_name' => '' ) );
		$this->assertStringContainsString( 'dsgo_form_field=your_name', $location );

		// The visitor fixes it and sends again from the error page's URL.
		$markup  = $this->form_block( 'retry-again' );
		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => wp_slash( $markup ),
			)
		);
		$xpath   = $this->xpath( do_blocks( $markup ) );
		$post    = array();
		foreach ( $xpath->query( '//form//input[@name]' ) as $input ) {
			$post[ $input->getAttribute( 'name' ) ] = $input->getAttribute( 'value' );
		}
		unset( $post['agree'] );
		$post['your_name']       = 'Pat';
		$post['dsg_timestamp']   = (string) ( ( time() - 10 ) * 1000 );
		$_POST                   = wp_slash( $post );
		$_SERVER['HTTP_REFERER'] = add_query_arg(
			array(
				'dsgo_form_status' => 'error',
				'dsgo_form_id'     => 'retry-again',
				'dsgo_form_error'  => 'required_field_missing',
				'dsgo_form_field'  => 'your_name',
			),
			get_permalink( $post_id )
		);
		$throw = static function ( $to ) {
			throw new \RuntimeException( $to ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only control flow.
		};
		add_filter( 'wp_redirect', $throw );
		$again = '';
		try {
			$this->handler->handle_post_submission();
		} catch ( \RuntimeException $redirect ) {
			$again = $redirect->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $throw );
			$_POST = array();
			unset( $_SERVER['HTTP_REFERER'] );
		}

		$this->assertStringContainsString( 'dsgo_form_status=success', $again );
		$this->assertStringNotContainsString( 'dsgo_form_error', $again );
		$this->assertStringNotContainsString( 'dsgo_form_field', $again );
	}

	public function test_too_fast_is_explained_without_naming_a_field() {
		$_GET['dsgo_form_status'] = 'error';
		$_GET['dsgo_form_id']     = 'retry-fast';
		$_GET['dsgo_form_error']  = 'too_fast';
		$text                     = $this->xpath( do_blocks( $this->form_block( 'retry-fast' ) ) )->query( '//*[@data-dsgo-server-message]' )->item( 0 )->textContent;

		$this->assertSame( 'Could not send. The form was sent too quickly. Please wait a few seconds and send it again.', $text );
	}

	public function test_url_text_is_never_printed() {
		$_GET['dsgo_form_status'] = 'error';
		$_GET['dsgo_form_id']     = 'retry-url';
		$_GET['dsgo_form_error']  = 'required_field_missing';
		$_GET['dsgo_form_field']  = 'Call 555-0100 to claim your prize';

		$html = do_blocks( $this->form_block( 'retry-url' ) );

		$this->assertStringNotContainsString( '555-0100', $html );
		$this->assertStringContainsString( 'Please fill in every required field.', $html );
	}

	public function test_unknown_and_spam_codes_add_no_detail() {
		$_GET['dsgo_form_status'] = 'error';
		$_GET['dsgo_form_id']     = 'retry-spam';
		foreach ( array( 'spam_detected', '<script>', 'submission_failed' ) as $code ) {
			$_GET['dsgo_form_error'] = $code;
			$text                    = $this->xpath( do_blocks( $this->form_block( 'retry-spam' ) ) )->query( '//*[@data-dsgo-server-message]' )->item( 0 )->textContent;
			$this->assertSame( 'Could not send.', $text, $code );
		}
	}

	public function test_answers_for_another_form_are_not_used() {
		$cookie = Form_No_JS_Retry::encode( 'other-form', array( array( 'name' => 'email', 'value' => 'x@example.com', 'type' => 'email' ) ) );
		$xpath  = $this->return_to( '/?dsgo_form_status=error&dsgo_form_id=retry-mine', $this->form_block( 'retry-mine' ), $cookie );

		$this->assertSame( '', $xpath->query( '//input[@name="email"]' )->item( 0 )->getAttribute( 'value' ) );
	}

	/**
	 * A cookie value signed the way Form_No_JS_Retry signs it.
	 *
	 * @param mixed $data Data to encode.
	 * @return string
	 */
	private function signed( $data ) {
		$payload = base64_encode( (string) wp_json_encode( $data ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return $payload . '.' . hash_hmac( 'sha256', Form_No_JS_Retry::COOKIE . '|' . $payload, wp_salt( 'auth' ) );
	}

	public function test_tampered_cookie_is_ignored() {
		$cookie          = Form_No_JS_Retry::encode( 'retry-tamper', array( array( 'name' => 'email', 'value' => 'a@example.com', 'type' => 'email' ) ) );
		list( $payload ) = explode( '.', $cookie );
		$forged          = base64_encode( (string) wp_json_encode( array( 'retry-tamper', array( 'email' => 'evil@example.com' ), time() + 60 ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$this->assertNotNull( Form_No_JS_Retry::decode( $cookie ) );
		$this->assertNull( Form_No_JS_Retry::decode( $forged . '.' . explode( '.', $cookie )[1] ) );
		// Signed by another of the site's signatures (wp_hash) is not enough.
		$this->assertNull( Form_No_JS_Retry::decode( $payload . '.' . wp_hash( $payload ) ) );
		$this->assertNull( Form_No_JS_Retry::decode( 'garbage' ) );
		$this->assertNull( Form_No_JS_Retry::decode( $cookie . '.extra' ) );
	}

	public function test_signed_but_malformed_or_expired_cookies_are_ignored() {
		$this->assertNotNull( Form_No_JS_Retry::decode( $this->signed( array( 'f', array( 'a' => 'b' ), time() + 60 ) ) ) );

		$this->assertNull( Form_No_JS_Retry::decode( $this->signed( array( 'f', array( 'a' => 'b' ), time() - 1 ) ) ), 'Expired.' );
		$this->assertNull( Form_No_JS_Retry::decode( $this->signed( array( 'f', array( 'a' => 'b' ) ) ) ), 'No expiry.' );
		$this->assertNull( Form_No_JS_Retry::decode( $this->signed( array( 'f', 'not answers', time() + 60 ) ) ) );
		$this->assertNull( Form_No_JS_Retry::decode( $this->signed( array( 7, array(), time() + 60 ) ) ) );
		$this->assertNull( Form_No_JS_Retry::decode( $this->signed( 'just a string' ) ) );
		$this->assertSame(
			array( 'f', array( 'a' => 'b' ) ),
			Form_No_JS_Retry::decode( $this->signed( array( 'f', array( 'a' => 'b', 'n' => 5, 'x' => array( 'y' ) ), time() + 60 ) ) ),
			'Only string answers survive.'
		);
	}

	public function test_cookie_is_read_once() {
		$_COOKIE[ Form_No_JS_Retry::COOKIE ] = Form_No_JS_Retry::encode( 'retry-once', array( array( 'name' => 'email', 'value' => 'a@example.com', 'type' => 'email' ) ) );
		Form_No_JS_Retry::capture();
		$this->assertSame( array( 'email' => 'a@example.com' ), Form_No_JS_Retry::values( 'retry-once' ) );
		$this->assertSame( '', Form_No_JS_Retry::sent(), 'The cookie is expired once read.' );
	}

	public function test_forged_cookie_fills_nothing_on_the_page() {
		$_COOKIE[ Form_No_JS_Retry::COOKIE ] = array( 'not', 'a string' );
		Form_No_JS_Retry::capture();
		$this->assertNull( Form_No_JS_Retry::sent(), 'A non-string cookie is not touched.' );

		$xpath = $this->return_to( '/?dsgo_form_status=error&dsgo_form_id=retry-forged', $this->form_block( 'retry-forged' ), $this->signed( array( 'retry-forged', array( 'email' => 'evil@example.com' ), time() - 1 ) ) );

		$this->assertSame( '', $xpath->query( '//input[@name="email"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( '', Form_No_JS_Retry::sent(), 'Still expired, so the browser drops it.' );
	}

	public function test_bad_answer_is_named_and_flagged() {
		list( $location, $markup ) = $this->submit(
			'retry-invalid',
			array(
				'your_name' => 'Pat',
				'email'     => 'not-an-email',
			)
		);

		$this->assertStringContainsString( 'dsgo_form_error=validation_error', $location );
		$this->assertStringContainsString( 'dsgo_form_field=email', $location );

		$xpath = $this->return_to( $location, $markup, (string) Form_No_JS_Retry::sent() );

		$this->assertSame( 'Could not send. Please check your answer to “Email address”.', $xpath->query( '//*[@data-dsgo-server-message]' )->item( 0 )->textContent );
		$email = $xpath->query( '//input[@name="email"]' )->item( 0 );
		$this->assertSame( 'not-an-email', $email->getAttribute( 'value' ) );
		$this->assertSame( 'true', $email->getAttribute( 'aria-invalid' ) );
	}

	public function test_success_and_spam_keep_no_answers() {
		list( $location ) = $this->submit( 'retry-ok', array( 'your_name' => 'Pat' ) );
		$this->assertStringContainsString( 'dsgo_form_status=success', $location );
		$this->assertNull( Form_No_JS_Retry::sent() );

		list( $location ) = $this->submit(
			'retry-bot',
			array(
				'your_name'   => 'Bot',
				'dsg_website' => 'https://spam.example',
			)
		);
		$this->assertStringContainsString( 'dsgo_form_status=error', $location );
		$this->assertStringNotContainsString( 'dsgo_form_error', $location );
		$this->assertNull( Form_No_JS_Retry::sent(), 'No answers are kept for a honeypot catch.' );
	}

	public function test_success_message_is_not_focusable() {
		$_GET['dsgo_form_status'] = 'success';
		$_GET['dsgo_form_id']     = 'retry-done';
		$message                  = $this->xpath( do_blocks( $this->form_block( 'retry-done' ) ) )->query( '//*[@data-dsgo-server-message]' )->item( 0 );

		$this->assertSame( 'status', $message->getAttribute( 'role' ) );
		$this->assertFalse( $message->hasAttribute( 'tabindex' ) );
	}

	public function test_hidden_fields_are_not_stored_and_long_answers_are_dropped_first() {
		$cookie = Form_No_JS_Retry::encode(
			'retry-size',
			array(
				array( 'name' => 'secret', 'value' => 'abc', 'type' => 'hidden' ),
				array( 'name' => 'email', 'value' => 'a@example.com', 'type' => 'email' ),
				array( 'name' => 'essay', 'value' => str_repeat( 'x', 5000 ), 'type' => 'textarea' ),
			)
		);

		$this->assertLessThanOrEqual( Form_No_JS_Retry::MAX_BYTES, strlen( $cookie ) );
		$this->assertSame( array( 'retry-size', array( 'email' => 'a@example.com' ) ), Form_No_JS_Retry::decode( $cookie ) );
	}

	public function test_nothing_is_stored_when_no_answer_fits() {
		$this->assertSame( '', Form_No_JS_Retry::encode( 'retry-huge', array( array( 'name' => 'essay', 'value' => str_repeat( 'é', 3000 ), 'type' => 'textarea' ) ) ) );
		$this->assertSame( '', Form_No_JS_Retry::encode( 'retry-empty', array( array( 'name' => 'email', 'value' => '', 'type' => 'email' ) ) ) );

		Form_No_JS_Retry::remember( 'retry-empty', array() );
		$this->assertNull( Form_No_JS_Retry::sent() );
	}

	public function test_field_label_without_a_label_is_empty() {
		$this->assertSame( 'City', Form_No_JS_Refill::field_label( '<label for="c">City <span class="dsgo-form-field__required">*</span></label><input id="c" name="city">', 'city' ) );
		$this->assertSame( '', Form_No_JS_Refill::field_label( '<input id="c" name="city">', 'city' ), 'No <label for>.' );
		$this->assertSame( '', Form_No_JS_Refill::field_label( '<label>City</label><input name="city">', 'city' ), 'No id.' );
		$this->assertSame( '', Form_No_JS_Refill::field_label( '<label for="c">City</label><input id="c" name="city">', 'town' ), 'No such field.' );
		$this->assertSame( '', Form_No_JS_Refill::field_label( '<label for="n">Nonce</label><input type="hidden" id="n" name="_wpnonce" value="x" data-dsgo-system>', '_wpnonce' ), 'System fields are never named.' );
	}

	public function test_mark_invalid_keeps_existing_descriptions_and_skips_system_fields() {
		$html = '<input type="hidden" name="city" value="x" data-dsgo-system><input name="city" aria-describedby="city-help">';

		$out = Form_No_JS_Refill::mark_invalid( $html, 'city', 'msg' );

		$this->assertStringContainsString( '<input type="hidden" name="city" value="x" data-dsgo-system>', $out );
		$this->assertStringContainsString( 'aria-describedby="city-help msg"', $out );
		$this->assertSame( 1, substr_count( $out, 'aria-invalid="true"' ) );
		$this->assertSame( $html, Form_No_JS_Refill::mark_invalid( $html, '', 'msg' ) );
	}

	public function test_textarea_answers_are_dropped_not_the_form_when_pcre_fails() {
		$html = '<form><textarea name="message"></textarea><input name="city" value=""></form>';
		$jit  = ini_get( 'pcre.jit' );
		$back = ini_get( 'pcre.backtrack_limit' );
		ini_set( 'pcre.jit', '0' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		ini_set( 'pcre.backtrack_limit', '1' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		try {
			$out = Form_No_JS_Refill::refill( $html, array( 'message' => 'Hi', 'city' => 'Oslo' ) );
		} finally {
			ini_set( 'pcre.jit', (string) $jit ); // phpcs:ignore WordPress.PHP.IniSet.Risky
			ini_set( 'pcre.backtrack_limit', (string) $back ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		}

		$this->assertStringContainsString( 'value="Oslo"', $out );
		$this->assertStringContainsString( '<textarea', $out );
	}

	public function test_refill_leaves_system_fields_alone() {
		$html = '<form><input type="hidden" name="_wpnonce" value="real" data-dsgo-system/><input name="city" value=""></form>';

		$out = Form_No_JS_Refill::refill( $html, array( '_wpnonce' => 'forged', 'city' => 'Oslo' ) );

		$this->assertStringContainsString( 'value="real"', $out );
		$this->assertStringContainsString( 'value="Oslo"', $out );
	}
}

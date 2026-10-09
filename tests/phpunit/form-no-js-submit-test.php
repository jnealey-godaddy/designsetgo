<?php
/**
 * Form Builder submission without JavaScript.
 *
 * Saved forms have no action attribute; view.js used to supply it and the
 * admin-post fields, so without scripting the browser posted to the page and
 * the answers vanished. These tests render real saved markup (the PHP mirror
 * of save()), submit what a browser would post from it to the real admin-post
 * handler, and render the page it redirects back to.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WPDieException;
use DOMDocument;
use DOMXPath;
use DesignSetGo\Blocks\Form_Handler;
use DesignSetGo\Blocks\Form_No_JS_Submit;
use DesignSetGo\Abilities\Serializers\Form_Builder_Serializer;

/**
 * No-JS submission test case.
 */
class Test_Form_No_JS_Submit extends WP_UnitTestCase {

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

	public function tear_down() {
		unset( $_GET['dsgo_form_status'], $_GET['dsgo_form_id'] );
		parent::tear_down();
	}

	/**
	 * Serialized form block, with the markup save() stores.
	 *
	 * @param string $form_id Form ID.
	 * @param array  $attrs   Extra block attributes.
	 * @return string
	 */
	private function form_block( $form_id, array $attrs = array() ) {
		$attrs   = array_merge(
			array(
				'formId'      => $form_id,
				'enableEmail' => false,
			),
			$attrs
		);
		$wrapper = Form_Builder_Serializer::wrapper( 'designsetgo/form-builder', $attrs );

		return '<!-- wp:designsetgo/form-builder ' . serialize_block_attributes( $attrs ) . ' -->'
			. $wrapper['opening']
			. '<!-- wp:designsetgo/form-text-field {"fieldName":"your_name","required":true} /-->'
			. '<!-- wp:designsetgo/form-email-field {"fieldName":"email"} /-->'
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
	 * Values of every input with a name in the rendered form.
	 *
	 * @param string $html Rendered HTML.
	 * @param string $name Input name.
	 * @return string[]
	 */
	private function input_values( $html, $name ) {
		$values = array();
		foreach ( $this->xpath( $html )->query( '//form//input[@name="' . $name . '"]' ) as $input ) {
			$values[] = $input->getAttribute( 'value' );
		}
		return $values;
	}

	/**
	 * What a browser without JavaScript posts from the rendered form.
	 *
	 * Every named input as rendered, text inputs filled in as a visitor
	 * would. The form is `novalidate` and nothing else runs.
	 *
	 * @param string $html    Rendered HTML.
	 * @param array  $answers Field name => typed answer.
	 * @return array{action: string, post: array<string, string>}
	 */
	private function browser_post( $html, array $answers ) {
		$xpath = $this->xpath( $html );
		$form  = $xpath->query( '//form' )->item( 0 );
		$this->assertNotNull( $form );

		$post = array();
		foreach ( $xpath->query( './/input[@name]', $form ) as $input ) {
			$name          = $input->getAttribute( 'name' );
			$post[ $name ] = isset( $answers[ $name ] ) ? $answers[ $name ] : $input->getAttribute( 'value' );
		}

		return array(
			'action' => $form->getAttribute( 'action' ),
			'post'   => $post,
		);
	}

	/**
	 * Run the admin-post handler with a browser's POST and return its redirect.
	 *
	 * @param array  $post    Unslashed POST body.
	 * @param string $referer Page the form was on.
	 * @return string Redirect location.
	 */
	private function submit( array $post, $referer ) {
		$saved_post              = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST                   = wp_slash( $post );
		$_SERVER['HTTP_REFERER'] = $referer;

		$throw = static function ( $location ) {
			throw new \RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only control flow; never output.
		};
		add_filter( 'wp_redirect', $throw );

		$location = '';
		try {
			$this->handler->handle_post_submission();
		} catch ( \RuntimeException $redirect ) {
			$location = $redirect->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $throw );
			$_POST = $saved_post;
			unset( $_SERVER['HTTP_REFERER'] );
		}

		return $location;
	}

	/**
	 * Publish a page holding the form and render it as a visitor gets it.
	 *
	 * @param string $form_id Form ID.
	 * @param array  $attrs   Extra block attributes.
	 * @return array{0: string, 1: string} Rendered HTML and the page URL.
	 */
	private function publish_and_render( $form_id, array $attrs = array() ) {
		$markup  = $this->form_block( $form_id, $attrs );
		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => wp_slash( $markup ),
			)
		);

		return array( do_blocks( $markup ), get_permalink( $post_id ) );
	}

	/**
	 * Render the page the handler redirected to.
	 *
	 * @param string $location Redirect location.
	 * @param string $markup   Form block markup.
	 * @return string
	 */
	private function render_redirect_target( $location, $markup ) {
		$query = array();
		wp_parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
		$_GET = array_merge( $_GET, $query );

		return do_blocks( $markup );
	}

	/**
	 * Every saved form gets an action and the fields the handler needs.
	 */
	public function test_rendered_form_posts_to_admin_post_with_handler_fields() {
		$html  = do_blocks( $this->form_block( 'nojs-fields' ) );
		$xpath = $this->xpath( $html );

		$this->assertSame( admin_url( 'admin-post.php' ), $xpath->query( '//form' )->item( 0 )->getAttribute( 'action' ) );
		$this->assertSame( array( Form_No_JS_Submit::ACTION ), $this->input_values( $html, 'action' ) );

		$nonce = $this->input_values( $html, '_wpnonce' );
		$this->assertCount( 1, $nonce );
		$this->assertNotFalse( wp_verify_nonce( $nonce[0], Form_No_JS_Submit::ACTION ) );

		$timestamp = $this->input_values( $html, 'dsg_timestamp' );
		$this->assertCount( 1, $timestamp );
		$this->assertEqualsWithDelta( time() * 1000, (int) $timestamp[0], 5000, 'Milliseconds, as view.js sends.' );

		// Inside the form, so they are posted with it.
		$this->assertSame( 3, $xpath->query( '//form//input[@type="hidden"][@name="action" or @name="_wpnonce" or @name="dsg_timestamp"]' )->length );
	}

	/**
	 * Fields already in the markup are not added again.
	 */
	public function test_fields_are_not_duplicated_when_rendered_twice() {
		$once  = do_blocks( $this->form_block( 'nojs-dupes' ) );
		$twice = apply_filters( 'render_block_designsetgo/form-builder', $once, array() );

		foreach ( array( 'action', '_wpnonce', 'dsg_timestamp', 'dsg_form_id' ) as $name ) {
			$this->assertCount( 1, $this->input_values( $twice, $name ), $name );
		}
	}

	/**
	 * A no-JS POST from the rendered page is stored, and the page it lands on
	 * says so in the form's message box.
	 */
	public function test_no_js_submission_from_rendered_markup_is_stored_and_confirmed() {
		list( $html, $url ) = $this->publish_and_render( 'nojs-e2e', array( 'successMessage' => 'Thanks <b>Pat</b> & co' ) );
		$form               = $this->browser_post(
			$html,
			array(
				'your_name' => 'Pat',
				'email'     => 'pat@example.com',
			)
		);
		$this->assertSame( admin_url( 'admin-post.php' ), $form['action'] );

		// The visitor spent five seconds on the page.
		$form['post']['dsg_timestamp'] = (string) ( (int) $form['post']['dsg_timestamp'] - 5000 );

		$location = $this->submit( $form['post'], $url );
		$this->assertStringContainsString( 'dsgo_form_status=success', $location );
		$this->assertStringEndsWith( '#' . Form_No_JS_Submit::message_anchor( 'nojs-e2e' ), $location );

		$submissions = get_posts(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'any',
				'meta_key'    => '_dsg_form_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
				'meta_value'  => 'nojs-e2e', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			)
		);
		$this->assertCount( 1, $submissions );
		$stored = (array) get_post_meta( $submissions[0]->ID, '_dsg_form_fields', true );
		$this->assertSame( array( 'your_name', 'email' ), array_keys( $stored ), 'System fields are not stored as answers.' );
		$this->assertSame( 'Pat', $stored['your_name']['value'] );

		$landed  = $this->render_redirect_target( $location, $this->form_block( 'nojs-e2e', array( 'successMessage' => 'Thanks <b>Pat</b> & co' ) ) );
		$message = $this->xpath( $landed )->query( '//*[@data-dsgo-server-message]' );
		$this->assertSame( 1, $message->length );
		$this->assertSame( 'Thanks <b>Pat</b> & co', $message->item( 0 )->textContent, 'Escaped text, not markup.' );
		$this->assertSame( 'status', $message->item( 0 )->getAttribute( 'role' ) );
		$this->assertSame( Form_No_JS_Submit::message_anchor( 'nojs-e2e' ), $message->item( 0 )->getAttribute( 'id' ) );
		$this->assertStringNotContainsString( '<b>Pat', $landed );
	}

	/**
	 * A bot posting the rendered fields instantly still trips the timing check.
	 */
	public function test_instant_no_js_submission_is_rejected_as_too_fast() {
		list( $html, $url ) = $this->publish_and_render( 'nojs-fast' );
		$form               = $this->browser_post( $html, array( 'your_name' => 'Bot' ) );

		$this->assertStringContainsString( 'dsgo_form_status=error', $this->submit( $form['post'], $url ) );
	}

	/**
	 * A logged-out visitor on a page cached past its nonce's life can still send.
	 */
	public function test_logged_out_submission_with_stale_nonce_is_accepted() {
		list( $html, $url ) = $this->publish_and_render( 'nojs-stale' );
		$form               = $this->browser_post( $html, array( 'your_name' => 'Pat' ) );

		$form['post']['_wpnonce']      = 'expired0';
		$form['post']['dsg_timestamp'] = (string) ( ( time() - DAY_IN_SECONDS * 2 ) * 1000 );

		$this->assertStringContainsString( 'dsgo_form_status=success', $this->submit( $form['post'], $url ) );
	}

	/**
	 * A logged-in visitor's nonce is still required.
	 */
	public function test_logged_in_submission_requires_a_valid_nonce() {
		wp_set_current_user( self::factory()->user->create() );
		list( $html, $url ) = $this->publish_and_render( 'nojs-csrf' );
		$form               = $this->browser_post( $html, array( 'your_name' => 'Pat' ) );

		$form['post']['dsg_timestamp'] = (string) ( (int) $form['post']['dsg_timestamp'] - 5000 );
		$this->assertStringContainsString( 'dsgo_form_status=success', $this->submit( $form['post'], $url ), 'The rendered nonce is valid for this user.' );

		$form['post']['_wpnonce'] = 'forged00';
		$this->expectException( WPDieException::class );
		$this->submit( $form['post'], $url );
	}

	/**
	 * An error redirect shows the form's error message as an alert.
	 */
	public function test_error_redirect_shows_error_message_for_matching_form() {
		$_GET['dsgo_form_status'] = 'error';
		$_GET['dsgo_form_id']     = 'nojs-err';

		$html    = do_blocks( $this->form_block( 'nojs-err', array( 'errorMessage' => 'Could not send.' ) ) );
		$message = $this->xpath( $html )->query( '//*[contains(@class,"dsgo-form__message")]' );

		$this->assertSame( 1, $message->length, 'The box is replaced, not duplicated.' );
		$this->assertSame( 'Could not send.', $message->item( 0 )->textContent );
		$this->assertSame( 'alert', $message->item( 0 )->getAttribute( 'role' ) );
		$this->assertStringContainsString( 'dsgo-form__message--error', $message->item( 0 )->getAttribute( 'class' ) );
		$this->assertStringNotContainsString( 'display:none', $message->item( 0 )->getAttribute( 'style' ) );
	}

	/**
	 * Another form's result is not shown in this one.
	 */
	public function test_no_message_for_another_form() {
		$_GET['dsgo_form_status'] = 'success';
		$_GET['dsgo_form_id']     = 'some-other-form';

		$html    = do_blocks( $this->form_block( 'nojs-other' ) );
		$message = $this->xpath( $html )->query( '//*[contains(@class,"dsgo-form__message")]' );

		$this->assertSame( 1, $message->length );
		$this->assertSame( '', $message->item( 0 )->textContent );
		$this->assertSame( 'display:none', $message->item( 0 )->getAttribute( 'style' ) );
		$this->assertStringNotContainsString( 'data-dsgo-server-message', $html );
	}

	/**
	 * An unknown status prints nothing.
	 */
	public function test_unknown_status_prints_nothing() {
		$_GET['dsgo_form_status'] = '<script>';
		$_GET['dsgo_form_id']     = 'nojs-bad-status';

		$this->assertStringNotContainsString( 'data-dsgo-server-message', do_blocks( $this->form_block( 'nojs-bad-status' ) ) );
	}

	/**
	 * Turnstile needs JavaScript: say so, and a no-JS POST fails visibly.
	 */
	public function test_turnstile_form_explains_and_fails_visibly_without_javascript() {
		list( $html, $url ) = $this->publish_and_render( 'nojs-turnstile', array( 'enableTurnstile' => true ) );

		$this->assertStringContainsString( '<noscript><p class="dsgo-form__noscript">', $html );

		$form                          = $this->browser_post( $html, array( 'your_name' => 'Pat' ) );
		$form['post']['dsg_timestamp'] = (string) ( (int) $form['post']['dsg_timestamp'] - 5000 );
		$location                      = $this->submit( $form['post'], $url );

		$this->assertStringContainsString( 'dsgo_form_status=error', $location );
		$this->assertStringContainsString( 'role="alert"', $this->render_redirect_target( $location, $this->form_block( 'nojs-turnstile', array( 'enableTurnstile' => true ) ) ) );
	}
}

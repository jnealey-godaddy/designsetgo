<?php
/**
 * Form notification recipients and the non-AJAX success redirect.
 *
 * @package DesignSetGo
 */

/**
 * @group forms
 */
class Form_Recipients_Redirect_Test extends WP_UnitTestCase {

	/**
	 * Captured wp_mail() arguments.
	 *
	 * @var array<string, mixed>|null
	 */
	private $sent;

	/**
	 * Handler under test.
	 *
	 * @var \DesignSetGo\Blocks\Form_Handler
	 */
	private $handler;

	public function set_up() {
		parent::set_up();
		$this->sent    = null;
		$this->handler = new \DesignSetGo\Blocks\Form_Handler();

		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $atts ) {
				$this->sent = $atts;
				return true; // Don't actually send.
			},
			10,
			2
		);

		// Block comments survive wp_insert_post() only for unfiltered_html users.
		wp_set_current_user(
			self::factory()->user->create( array( 'role' => 'administrator' ) )
		);
	}

	/**
	 * Call a private handler method.
	 *
	 * @param string $name Method name.
	 * @param array  $args Arguments.
	 * @return mixed Result.
	 */
	private function call( $name, array $args ) {
		$method = new ReflectionMethod( $this->handler, $name );
		$method->setAccessible( true );
		return $method->invokeArgs( $this->handler, $args );
	}

	/**
	 * Send a notification with the given recipient setting.
	 *
	 * @param string $email_to Raw emailTo attribute.
	 * @return string The `to` passed to wp_mail().
	 */
	private function send_to( $email_to ) {
		$this->call(
			'send_email_notification',
			array(
				'recipients-form',
				array( 'name' => 'Visitor' ),
				123,
				array(
					'enableEmail' => true,
					'emailTo'     => $email_to,
				),
			)
		);

		$this->assertNotNull( $this->sent, 'No email was produced.' );

		return $this->sent['to'];
	}

	public function test_sends_to_every_listed_recipient() {
		$to = $this->send_to( 'sales@example.org, support@example.org;owner@example.org' );

		$this->assertSame( 'sales@example.org, support@example.org, owner@example.org', $to );
	}

	public function test_drops_invalid_recipients_but_keeps_the_valid_ones() {
		$to = $this->send_to( 'not-an-email, sales@example.org' );

		$this->assertSame( 'sales@example.org', $to );
	}

	public function test_falls_back_to_admin_email_when_no_recipient_is_valid() {
		$to = $this->send_to( 'nope, also nope' );

		$this->assertSame( get_option( 'admin_email' ), $to );
	}

	/**
	 * Publish a page holding a form with the given redirect URL.
	 *
	 * @param string $form_id      Form ID.
	 * @param string $redirect_url Redirect URL attribute.
	 * @return WP_REST_Request Request naming that page as its source.
	 */
	private function request_for_form( $form_id, $redirect_url ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:designsetgo/form-builder ' . wp_json_encode(
					array(
						'formId'      => $form_id,
						'redirectUrl' => $redirect_url,
					)
				) . ' --><div class="wp-block-designsetgo-form-builder"></div><!-- /wp:designsetgo/form-builder -->',
			)
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'sourcePostId', $post_id );

		return $request;
	}

	public function test_non_ajax_success_follows_the_forms_redirect_url() {
		$request = $this->request_for_form( 'redirect-form', 'https://thanks.example.com/done' );

		$url = $this->call( 'get_success_redirect_url', array( 'redirect-form', $request ) );

		$this->assertSame( 'https://thanks.example.com/done', $url );
		$this->assertSame(
			'https://thanks.example.com/done',
			wp_validate_redirect( $url ),
			'wp_safe_redirect() must be allowed to follow the configured host.'
		);
	}

	public function test_non_ajax_success_ignores_a_non_http_redirect_url() {
		$request = $this->request_for_form( 'script-form', 'javascript:alert(1)' );

		$this->assertSame( '', $this->call( 'get_success_redirect_url', array( 'script-form', $request ) ) );
	}

	public function test_non_ajax_success_without_a_redirect_url_returns_empty() {
		$request = $this->request_for_form( 'plain-form', '' );

		$this->assertSame( '', $this->call( 'get_success_redirect_url', array( 'plain-form', $request ) ) );
	}
}

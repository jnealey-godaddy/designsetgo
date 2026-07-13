<?php
/**
 * Form notification emails must escape submitted values.
 *
 * The notification is sent with `Content-Type: text/html`, and its body is a
 * template in which `{field_name}` merge tags are replaced with whatever the
 * visitor typed. The `{all_fields}` aggregate always ran values through
 * esc_html(); the PER-FIELD tags did not — so any template using `{message}` or
 * `{name}` (the common case) let an anonymous submitter put arbitrary markup
 * into the site owner's inbox: a fake "reset your password" link, a tracking
 * pixel, a spoofed sender line.
 *
 * @package DesignSetGo
 */

/**
 * @group security
 * @group forms
 */
class Form_Email_Escaping_Test extends WP_UnitTestCase {

	/**
	 * Captured wp_mail() arguments.
	 *
	 * @var array<string, mixed>|null
	 */
	private $sent;

	public function set_up() {
		parent::set_up();
		$this->sent = null;

		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $atts ) {
				$this->sent = $atts;
				return true; // Don't actually send.
			},
			10,
			2
		);

		// Without a user who has `unfiltered_html`, wp_insert_post() runs the
		// content through wp_filter_post_kses(), which strips HTML comments — and
		// a block IS an HTML comment, so the fixture would silently save as an
		// empty post and the handler would find nothing.
		wp_set_current_user(
			self::factory()->user->create( array( 'role' => 'administrator' ) )
		);

		// The handler deliberately reads email config from the SAVED BLOCK rather
		// than the request, so a real published post has to exist for it to find.
		// The body uses a per-field {message} tag — the exact shape that was
		// interpolating submitter input into HTML unescaped.
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:designsetgo/form-builder ' . wp_json_encode(
					array(
						'formId'      => 'test-form',
						'enableEmail' => true,
						'emailTo'     => 'owner@example.org',
						'emailBody'   => '<p>Message: {message}</p><p>Topics: {topics}</p>',
					)
				) . ' --><div class="wp-block-designsetgo-form-builder"></div><!-- /wp:designsetgo/form-builder -->',
			)
		);
	}

	/**
	 * Invoke the private notification builder with a hostile submission.
	 *
	 * @param string $payload Attacker-supplied field value.
	 * @return string The rendered email body.
	 */
	private function send_with_payload( $payload ) {
		$handler = new \DesignSetGo\Blocks\Form_Handler();

		$method = new ReflectionMethod( $handler, 'send_email_notification' );
		$method->setAccessible( true );

		$method->invoke(
			$handler,
			'test-form',
			array( 'message' => $payload, 'name' => 'Visitor' ),
			123
		);

		$this->assertNotNull( $this->sent, 'No email was produced.' );

		return (string) $this->sent['message'];
	}

	/**
	 * @dataProvider payload_provider
	 *
	 * @param string $payload  Hostile value.
	 * @param string $forbidden Substring that must not appear unescaped.
	 */
	public function test_submitted_markup_is_escaped_in_the_body( $payload, $forbidden ) {
		$body = $this->send_with_payload( $payload );

		$this->assertStringNotContainsString(
			$forbidden,
			$body,
			'Submitted markup reached the HTML email body unescaped.'
		);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function payload_provider() {
		return array(
			'anchor injection' => array(
				'<a href="https://evil.test">Reset your password</a>',
				'<a href="https://evil.test">',
			),
			'image beacon'     => array(
				'<img src="https://evil.test/pixel.gif">',
				'<img src=',
			),
			'script tag'       => array(
				'<script>alert(1)</script>',
				'<script>',
			),
		);
	}

	/**
	 * The escaping must not mangle ordinary text — the owner still needs to
	 * read what the visitor actually wrote.
	 */
	public function test_ordinary_text_survives_readably() {
		$body = $this->send_with_payload( 'Hello, I need help with A & B.' );

		$this->assertStringContainsString( 'Hello, I need help with A', $body );
	}

	/**
	 * A multi-value field arrives as an array; esc_html() would raise on it.
	 */
	public function test_array_field_values_do_not_fatal() {
		$handler = new \DesignSetGo\Blocks\Form_Handler();

		$method = new ReflectionMethod( $handler, 'send_email_notification' );
		$method->setAccessible( true );

		$method->invoke(
			$handler,
			'test-form',
			array( 'topics' => array( 'value' => array( 'billing', 'sales' ) ) ),
			124
		);

		$this->assertNotNull( $this->sent );
		$this->assertStringContainsString( 'billing, sales', (string) $this->sent['message'] );
	}
}

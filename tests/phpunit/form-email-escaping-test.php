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

	}

	/**
	 * Publish a post carrying the form block.
	 *
	 * The handler deliberately reads email config from the SAVED BLOCK rather
	 * than the request (so it can't be tampered with client-side), which means a
	 * real published post has to exist for it to find. Both templates use
	 * per-field merge tags — the exact shape at issue.
	 *
	 * @param string $subject_template Subject line, may contain merge tags.
	 */
	private function configure_form( $subject_template = 'New submission' ) {
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:designsetgo/form-builder ' . wp_json_encode(
					array(
						'formId'       => 'test-form',
						'enableEmail'  => true,
						'emailTo'      => 'owner@example.org',
						'emailSubject' => $subject_template,
						'emailBody'    => '<p>Message: {message}</p><p>Topics: {topics}</p>',
					)
				) . ' --><div class="wp-block-designsetgo-form-builder"></div><!-- /wp:designsetgo/form-builder -->',
			)
		);
	}

	/**
	 * Invoke the private notification builder with a hostile submission.
	 *
	 * @param string $payload          Attacker-supplied field value.
	 * @param string $subject_template Subject line, may contain merge tags.
	 * @return string The rendered email body.
	 */
	private function send_with_payload( $payload, $subject_template = 'New submission' ) {
		$this->configure_form( $subject_template );

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
	 * The SUBJECT is a plain-text mail header, not HTML.
	 *
	 * The first cut of this fix shared one esc_html()'d merge-tag map between the
	 * body and the subject, which corrupted the subject: a site called
	 * "Bob's Bakery & Sons" arrived as "Bob&#039;s Bakery &amp; Sons". Escaping a
	 * header is not protection, it is mojibake — and the original suite only ever
	 * asserted on the body, so it sailed through.
	 */
	public function test_subject_is_not_html_escaped() {
		update_option( 'blogname', "Bob's Bakery & Sons" );

		$this->send_with_payload( 'anything', '{site_name}: new enquiry' );

		$subject = (string) $this->sent['subject'];

		$this->assertSame( "Bob's Bakery & Sons: new enquiry", $subject );
		$this->assertStringNotContainsString( '&amp;', $subject );
		$this->assertStringNotContainsString( '&#039;', $subject );
	}

	/**
	 * A field value reaching the subject must not be able to add a mail header.
	 */
	public function test_subject_merge_tags_cannot_inject_a_header() {
		$this->send_with_payload( "hi\r\nBcc: victim@example.org", 'Re: {message}' );

		$subject = (string) $this->sent['subject'];

		$this->assertStringNotContainsString( "\n", $subject );
		$this->assertStringNotContainsString( "\r", $subject );
	}

	/**
	 * Escaping the body must not have been traded away for the subject fix.
	 */
	public function test_body_is_still_escaped_while_subject_is_not() {
		$this->send_with_payload( '<script>alert(1)</script>', 'Re: {message}' );

		$this->assertStringNotContainsString( '<script>', (string) $this->sent['message'] );
		$this->assertStringContainsString( '<script>', (string) $this->sent['subject'] );
	}

	/**
	 * A multi-value field arrives as an array; esc_html() would raise on it.
	 */
	public function test_array_field_values_do_not_fatal() {
		$this->configure_form();

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

	/**
	 * A submission is attacker-shaped data, so no field shape may warn or fatal.
	 *
	 * An array with no 'value' key, or arrays nested inside arrays, would make a
	 * plain strval() emit "Array to string conversion". The helper exists
	 * precisely so that every shape is safe, so it has to actually be.
	 *
	 * @dataProvider malformed_field_provider
	 *
	 * @param mixed $field_data A field of hostile / malformed shape.
	 */
	public function test_no_field_shape_raises( $field_data ) {
		$this->configure_form();

		// Any PHP warning here (e.g. "Array to string conversion") becomes a
		// failure, which is exactly what we want to pin.
		$handler = new \DesignSetGo\Blocks\Form_Handler();
		$method  = new ReflectionMethod( $handler, 'send_email_notification' );
		$method->setAccessible( true );

		$method->invoke( $handler, 'test-form', array( 'message' => $field_data ), 125 );

		$this->assertNotNull( $this->sent );
		$this->assertIsString( $this->sent['message'] );
		$this->assertIsString( $this->sent['subject'] );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public function malformed_field_provider() {
		return array(
			'array with no value key' => array( array( 'unexpected' => 'shape' ) ),
			'nested arrays'           => array( array( 'value' => array( array( 'a', 'b' ), 'c' ) ) ),
			'deeply nested'           => array( array( array( array( 'x' ) ) ) ),
			'bool'                    => array( true ),
			'null'                    => array( null ),
			'int'                     => array( 42 ),
		);
	}
}

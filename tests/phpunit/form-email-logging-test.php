<?php
/**
 * Privacy and delivery diagnostics for optional form email logging.
 *
 * @package DesignSetGo
 */

/**
 * Verify logging privacy without changing notification delivery.
 *
 * @group forms
 */
class Form_Email_Logging_Test extends WP_UnitTestCase {

	/**
	 * Temporary destination for actual PHP error_log() output.
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Original PHP error log destination.
	 *
	 * @var string
	 */
	private $original_error_log;

	/**
	 * Capture PHP logging in an isolated test file.
	 */
	public function set_up() {
		parent::set_up();
		$this->original_error_log = ini_get( 'error_log' );
		$this->log_file           = tempnam( sys_get_temp_dir(), 'dsgo-email-log-' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam -- Isolated test log, deleted in tear_down().
		$this->assertNotFalse( $this->log_file );
		$this->assertNotFalse( ini_set( 'error_log', $this->log_file ) );
	}

	/**
	 * Restore logging configuration and remove the captured output.
	 */
	public function tear_down() {
		ini_set( 'error_log', $this->original_error_log );
		if ( $this->log_file && file_exists( $this->log_file ) ) {
			wp_delete_file( $this->log_file );
		}
		parent::tear_down();
	}

	/**
	 * Mail outcomes, simulated without contacting an email server.
	 *
	 * @return array
	 */
	public function mail_outcomes() {
		return array(
			'success' => array( true, 'sent successfully' ),
			'failure' => array( false, 'FAILED to send' ),
		);
	}

	/**
	 * Exercise the real notification handler and capture its mail arguments.
	 *
	 * @param bool|null $logging Whether logging is enabled, or null for the default.
	 * @param bool      $mail_result Simulated wp_mail() result.
	 * @return string Actual emitted PHP log.
	 */
	private function send_notification( $logging, $mail_result ) {
		$settings = get_option( 'designsetgo_settings', array() );
		if ( null === $logging ) {
			unset( $settings['forms']['enable_email_logging'] );
		} else {
			$settings['forms']['enable_email_logging'] = $logging;
		}
		update_option( 'designsetgo_settings', $settings );

		$mail_args = null;
		$capture   = static function ( $short_circuit, $atts ) use ( &$mail_args, $mail_result ) {
			$mail_args = $atts;
			return $mail_result;
		};
		add_filter( 'pre_wp_mail', $capture, PHP_INT_MAX, 2 );

		try {
			$handler = new \DesignSetGo\Blocks\Form_Handler();
			$method  = new ReflectionMethod( $handler, 'send_email_notification' );
			$method->setAccessible( true );
			$method->invoke(
				$handler,
				'email-logging-form',
				array( 'name' => 'F01 Synthetic Visitor' ),
				123,
				array(
					'enableEmail'  => true,
					'emailTo'      => 'f01.recipient@example.test, f01.second@example.test',
					'emailSubject' => 'Private F01 subject for {name}',
				)
			);
		} finally {
			remove_filter( 'pre_wp_mail', $capture, PHP_INT_MAX );
		}

		// Logging changes must not redact the actual notification email.
		$this->assertNotNull( $mail_args );
		$this->assertSame( 'f01.recipient@example.test, f01.second@example.test', $mail_args['to'] );
		$this->assertSame( 'Private F01 subject for F01 Synthetic Visitor', $mail_args['subject'] );

		return file_get_contents( $this->log_file );
	}

	/**
	 * Retain useful delivery diagnostics without logging recipient or subject data.
	 *
	 * @dataProvider mail_outcomes
	 *
	 * @param bool   $mail_result Simulated delivery result.
	 * @param string $status Expected diagnostic status.
	 */
	public function test_enabled_logging_preserves_status_without_personal_data( $mail_result, $status ) {
		$log = $this->send_notification( true, $mail_result );

		$this->assertStringContainsString( 'DesignSetGo Form: Email ' . $status . ' for submission #123', $log );
		$this->assertStringNotContainsString( 'f01.recipient@example.test', $log );
		$this->assertStringNotContainsString( 'f01.second@example.test', $log );
		$this->assertStringNotContainsString( 'Private F01 subject', $log );
		$this->assertStringNotContainsString( 'F01 Synthetic Visitor', $log );
	}

	/**
	 * Respect the logging setting for either delivery outcome.
	 *
	 * @dataProvider mail_outcomes
	 *
	 * @param bool   $mail_result Simulated delivery result.
	 * @param string $_status Unused diagnostic status.
	 */
	public function test_disabled_logging_emits_no_delivery_log( $mail_result, $_status ) {
		$this->assertSame( '', $this->send_notification( false, $mail_result ) );
	}

	/**
	 * Keep delivery logging opt-in when no setting has been saved.
	 */
	public function test_logging_is_disabled_when_setting_is_absent() {
		$this->assertSame( '', $this->send_notification( null, true ) );
	}
}

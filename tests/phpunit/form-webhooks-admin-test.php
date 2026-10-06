<?php
/**
 * Webhook admin UI on the submissions screen.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WPDieException;
use DesignSetGo\Blocks\Form_Webhooks;
use DesignSetGo\Blocks\Form_Webhooks_Admin;

/**
 * Webhook admin test case.
 */
class Test_Form_Webhooks_Admin extends WP_UnitTestCase {

	/**
	 * Admin UI under test.
	 *
	 * @var Form_Webhooks_Admin
	 */
	private $admin;

	public function set_up() {
		parent::set_up();
		$this->admin = new Form_Webhooks_Admin( new Form_Webhooks() );
	}

	public function tear_down() {
		unset( $_GET['submission'], $_GET['_wpnonce'], $_REQUEST['_wpnonce'], $_GET['dsgo_webhook_resent'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function make_submission( $with_webhook ) {
		$id = self::factory()->post->create( array( 'post_type' => 'dsgo_form_submission', 'post_status' => 'private' ) );
		update_post_meta( $id, '_dsg_form_id', 'contact-a1' );
		if ( $with_webhook ) {
			update_post_meta( $id, '_dsg_webhook_url', 'https://hooks.example.com/path/secret-token' );
			update_post_meta( $id, '_dsg_webhook_status', 'failed' );
			update_post_meta( $id, '_dsg_webhook_attempts', 5 );
			update_post_meta( $id, '_dsg_webhook_last_code', 500 );
			update_post_meta( $id, '_dsg_webhook_last_error', 'The receiver responded with HTTP 500.' );
			update_post_meta( $id, '_dsg_webhook_signed', 'no' );
		}
		return $id;
	}

	public function test_column_is_inserted_before_date() {
		$columns = $this->admin->add_column( array( 'cb' => '', 'title' => 'T', 'date' => 'D' ) );
		$this->assertSame( array( 'cb', 'title', 'dsgo_webhook', 'date' ), array_keys( $columns ) );
	}

	public function test_column_shows_dash_for_submissions_without_webhook() {
		$id = $this->make_submission( false );
		ob_start();
		$this->admin->render_column( 'dsgo_webhook', $id );
		$html = ob_get_clean();
		$this->assertStringContainsString( '<span aria-hidden="true" style="color: #646970;">—</span>', $html );
		$this->assertStringContainsString( '<span class="screen-reader-text">No webhook</span>', $html );
	}

	public function test_column_status_is_text_in_an_accessible_colour() {
		$id = $this->make_submission( true );
		update_post_meta( $id, '_dsg_webhook_status', 'delivered' );
		ob_start();
		$this->admin->render_column( 'dsgo_webhook', $id );
		$this->assertSame( '<span style="color: #007017;">Delivered</span>', ob_get_clean() );
	}

	public function test_row_action_names_the_submission() {
		$id      = $this->make_submission( true );
		$actions = $this->admin->row_actions( array(), get_post( $id ) );
		$this->assertStringContainsString( 'aria-label="Resend webhook for submission ' . $id . '"', $actions['dsgo_resend_webhook'] );
	}

	/**
	 * @dataProvider notices
	 *
	 * @param string $result Resend result flag.
	 * @param string $class  Notice class.
	 * @param string $role   ARIA role.
	 */
	public function test_resend_notice_is_announced( $result, $class, $role ) {
		set_current_screen( 'edit-dsgo_form_submission' );
		$_GET['dsgo_webhook_resent'] = $result;
		ob_start();
		$this->admin->resend_notice();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'notice-' . $class, $html );
		$this->assertStringContainsString( 'role="' . $role . '"', $html );
	}

	public function notices() {
		return array(
			'delivered' => array( 'delivered', 'success', 'status' ),
			'pending'   => array( 'pending', 'warning', 'status' ),
			'failed'    => array( 'failed', 'error', 'alert' ),
			'invalid'   => array( 'invalid', 'error', 'alert' ),
		);
	}

	public function test_resend_notice_ignores_unknown_flags_and_other_screens() {
		set_current_screen( 'edit-dsgo_form_submission' );
		$_GET['dsgo_webhook_resent'] = 'bogus';
		ob_start();
		$this->admin->resend_notice();
		$this->assertSame( '', ob_get_clean() );

		set_current_screen( 'edit-post' );
		$_GET['dsgo_webhook_resent'] = 'delivered';
		ob_start();
		$this->admin->resend_notice();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_meta_box_registered_only_for_webhook_submissions() {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();

		$this->admin->maybe_add_meta_box( get_post( $this->make_submission( false ) ) );
		$this->assertEmpty( $wp_meta_boxes );

		$this->admin->maybe_add_meta_box( get_post( $this->make_submission( true ) ) );
		$this->assertArrayHasKey( 'dsgo_webhook_delivery', $wp_meta_boxes['dsgo_form_submission']['side']['default'] );
	}

	public function test_row_action_only_when_webhook_configured() {
		$with    = get_post( $this->make_submission( true ) );
		$without = get_post( $this->make_submission( false ) );

		$this->assertArrayHasKey( 'dsgo_resend_webhook', $this->admin->row_actions( array(), $with ) );
		$this->assertArrayNotHasKey( 'dsgo_resend_webhook', $this->admin->row_actions( array(), $without ) );
		$this->assertArrayNotHasKey( 'dsgo_resend_webhook', $this->admin->row_actions( array(), get_post( self::factory()->post->create() ) ) );
	}

	public function test_meta_box_shows_host_not_full_url() {
		$id = $this->make_submission( true );
		ob_start();
		$this->admin->render_meta_box( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'hooks.example.com', $html );
		$this->assertStringNotContainsString( 'secret-token', $html );
		$this->assertStringContainsString( 'HTTP 500', $html );
		$this->assertStringContainsString( 'Unsigned', $html );
	}

	public function test_meta_box_hides_signature_row_when_never_attempted() {
		$id = $this->make_submission( true );
		delete_post_meta( $id, '_dsg_webhook_signed' );
		ob_start();
		$this->admin->render_meta_box( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'Signature', $html );
		$this->assertStringNotContainsString( 'Unsigned', $html );
	}

	public function test_meta_box_formats_delivered_date_for_the_site() {
		$id = $this->make_submission( true );
		update_post_meta( $id, '_dsg_webhook_delivered_date', '2026-10-06 14:30:00' );
		ob_start();
		$this->admin->render_meta_box( get_post( $id ) );
		$html = ob_get_clean();

		$expected = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), '2026-10-06 14:30:00' );
		$this->assertStringContainsString( esc_html( $expected ), $html );
		$this->assertStringNotContainsString( '2026-10-06 14:30:00', $html );
	}

	public function test_resent_flag_is_a_removable_query_arg() {
		$this->assertContains( 'dsgo_webhook_resent', apply_filters( 'removable_query_args', array() ) );
	}

	public function test_resend_requires_valid_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET['submission']   = $this->make_submission( true );
		$_REQUEST['_wpnonce'] = 'bad';

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		$this->admin->handle_resend();
	}

	public function test_resend_nonce_is_bound_to_its_submission() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$a                    = $this->make_submission( true );
		$b                    = $this->make_submission( true );
		$_GET['submission']   = $b;
		$_REQUEST['_wpnonce'] = wp_create_nonce( Form_Webhooks::RESEND_ACTION . '_' . $a );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		$this->admin->handle_resend();
	}

	public function test_resend_requires_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$id                   = $this->make_submission( true );
		$_GET['submission']   = $id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( Form_Webhooks::RESEND_ACTION . '_' . $id );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'not allowed to resend webhooks' );
		$this->admin->handle_resend();
	}
}

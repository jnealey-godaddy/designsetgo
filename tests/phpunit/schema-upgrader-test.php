<?php
/**
 * PHPUnit tests for the schema upgrade gate and its failure backoff (#551).
 *
 * @package DesignSetGo
 * @group query-block
 */

use DesignSetGo\Admin\SchemaUpgradeNotice;
use DesignSetGo\Blocks\Query\FilterIndex;
use DesignSetGo\Core\SchemaUpgrader;

class DesignSetGo_Schema_Upgrader_Test extends WP_UnitTestCase {

	/**
	 * @var callable|null
	 */
	private $break_create = null;

	public function set_up(): void {
		parent::set_up();
		$this->drop_table();
		delete_option( SchemaUpgrader::OPTION_VERSION );
		delete_option( SchemaUpgrader::OPTION_FAILURE );
		delete_option( FilterIndex::OPTION_SCHEMA );
	}

	public function tear_down(): void {
		set_current_screen( 'front' );
		delete_metadata( 'user', 0, SchemaUpgradeNotice::DISMISS_META, '', true );
		$this->restore_create();
		$this->drop_table();
		delete_option( SchemaUpgrader::OPTION_VERSION );
		delete_option( SchemaUpgrader::OPTION_FAILURE );
		delete_option( FilterIndex::OPTION_SCHEMA );
		parent::tear_down();
	}

	private function drop_table(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . FilterIndex::table_name() );
		FilterIndex::reset_table_cache();
	}

	/**
	 * Makes every CREATE TABLE fail, the way a server rejecting the schema does.
	 */
	private function break_create(): void {
		global $wpdb;
		$this->break_create = static function ( $query ) {
			return preg_match( '/^CREATE (TEMPORARY )?TABLE/', $query ) ? 'CREATE TABLE ( this is not valid sql' : $query;
		};
		add_filter( 'query', $this->break_create );
		$wpdb->suppress_errors( true );
	}

	private function restore_create(): void {
		global $wpdb;
		if ( $this->break_create ) {
			remove_filter( 'query', $this->break_create );
			$this->break_create = null;
		}
		$wpdb->suppress_errors( false );
	}

	private function record_failure( string $plugin_version = DESIGNSETGO_VERSION ): void {
		update_option(
			SchemaUpgrader::OPTION_FAILURE,
			array(
				'error'          => 'Specified key was too long; max key length is 1000 bytes',
				'time'           => time(),
				'plugin_version' => $plugin_version,
			)
		);
	}

	private function login_admin(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		return $user_id;
	}

	/**
	 * Mirrors admin-header.php, which calls set_parentage() before admin_notices.
	 */
	private function use_screen( string $id, string $parent_file = '' ): void {
		set_current_screen( $id );
		if ( '' !== $parent_file ) {
			get_current_screen()->set_parentage( $parent_file );
		}
	}

	private function notice_html(): string {
		return get_echo( array( SchemaUpgradeNotice::class, 'admin_notice' ) );
	}

	public function test_fresh_install_creates_table_and_seals_gate() {
		SchemaUpgrader::maybe_upgrade();

		$this->assertTrue( FilterIndex::table_exists() );
		$this->assertSame( SchemaUpgrader::SCHEMA_VERSION, get_option( SchemaUpgrader::OPTION_VERSION ) );
		$this->assertNull( SchemaUpgrader::get_failure() );
	}

	public function test_failed_install_records_error_and_leaves_gate_open() {
		$this->break_create();
		SchemaUpgrader::maybe_upgrade();
		$this->restore_create();

		$this->assertFalse( FilterIndex::table_exists() );
		$this->assertFalse( get_option( SchemaUpgrader::OPTION_VERSION ), 'Gate must not seal without a table.' );

		$failure = SchemaUpgrader::get_failure();
		$this->assertIsArray( $failure );
		$this->assertStringContainsString( 'SQL syntax', $failure['error'] );
		$this->assertSame( DESIGNSETGO_VERSION, $failure['plugin_version'] );
		$this->assertEqualsWithDelta( time(), $failure['time'], 5 );
	}

	public function test_recent_failure_blocks_retry_on_next_request() {
		$this->break_create();
		SchemaUpgrader::maybe_upgrade();
		$this->restore_create();

		// The database now accepts the schema, but the backoff must hold.
		SchemaUpgrader::maybe_upgrade();

		$this->assertFalse( FilterIndex::table_exists(), 'Must not retry on every admin request.' );
		$this->assertFalse( get_option( SchemaUpgrader::OPTION_VERSION ) );
	}

	public function test_retries_once_interval_has_elapsed() {
		update_option(
			SchemaUpgrader::OPTION_FAILURE,
			array(
				'error'          => 'Specified key was too long; max key length is 1000 bytes',
				'time'           => time() - SchemaUpgrader::RETRY_INTERVAL - 1,
				'plugin_version' => DESIGNSETGO_VERSION,
			)
		);

		SchemaUpgrader::maybe_upgrade();

		$this->assertTrue( FilterIndex::table_exists() );
		$this->assertSame( SchemaUpgrader::SCHEMA_VERSION, get_option( SchemaUpgrader::OPTION_VERSION ) );
		$this->assertNull( SchemaUpgrader::get_failure(), 'A successful retry clears the failure.' );
	}

	public function test_retries_immediately_after_plugin_update() {
		update_option(
			SchemaUpgrader::OPTION_FAILURE,
			array(
				'error'          => 'Specified key was too long; max key length is 1000 bytes',
				'time'           => time(),
				'plugin_version' => '2.7.3',
			)
		);

		SchemaUpgrader::maybe_upgrade();

		$this->assertTrue( FilterIndex::table_exists(), 'A new release may carry the schema fix; retry at once.' );
		$this->assertNull( SchemaUpgrader::get_failure() );
	}

	public function test_retry_clears_failure_and_installs() {
		update_option(
			SchemaUpgrader::OPTION_FAILURE,
			array(
				'error'          => 'x',
				'time'           => time(),
				'plugin_version' => DESIGNSETGO_VERSION,
			)
		);

		$this->assertTrue( SchemaUpgrader::retry() );
		$this->assertTrue( FilterIndex::table_exists() );
		$this->assertNull( SchemaUpgrader::get_failure() );
	}

	public function test_sealed_gate_skips_install() {
		update_option( SchemaUpgrader::OPTION_VERSION, SchemaUpgrader::SCHEMA_VERSION );

		SchemaUpgrader::maybe_upgrade();

		$this->assertFalse( FilterIndex::table_exists() );
	}

	public function test_admin_notice_shows_error_to_administrators_only() {
		update_option(
			SchemaUpgrader::OPTION_FAILURE,
			array(
				'error'          => 'Specified key was too long; max key length is 1000 bytes',
				'time'           => time(),
				'plugin_version' => DESIGNSETGO_VERSION,
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( '', get_echo( array( SchemaUpgradeNotice::class, 'admin_notice' ) ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = get_echo( array( SchemaUpgradeNotice::class, 'admin_notice' ) );
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'max key length is 1000 bytes', $html );
		$this->assertStringContainsString( 'action=' . SchemaUpgradeNotice::RETRY_ACTION, $html );
	}

	public function test_admin_notice_silent_without_failure() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( '', get_echo( array( SchemaUpgradeNotice::class, 'admin_notice' ) ) );
	}

	public function test_notice_is_dismissible_outside_designsetgo_pages() {
		$this->record_failure();
		$this->login_admin();
		$this->use_screen( 'dashboard', 'index.php' );

		$html = $this->notice_html();

		$this->assertStringContainsString( 'is-dismissible', $html );
		$this->assertStringContainsString( 'data-dsgo-dismiss-nonce="', $html );
		$this->assertTrue( wp_script_is( SchemaUpgradeNotice::DISMISS_SCRIPT, 'enqueued' ), 'The dismiss handler script must load with a dismissible notice.' );
	}

	public function test_dismissed_notice_is_hidden_outside_designsetgo_pages() {
		$this->record_failure();
		$this->login_admin();
		$this->assertTrue( SchemaUpgradeNotice::dismiss_for_current_user() );

		$this->use_screen( 'dashboard', 'index.php' );
		$this->assertSame( '', $this->notice_html() );

		$this->use_screen( 'plugins', 'plugins.php' );
		$this->assertSame( '', $this->notice_html() );
	}

	/**
	 * @dataProvider designsetgo_screens
	 */
	public function test_dismissed_notice_stays_on_designsetgo_pages( string $screen_id ) {
		$this->record_failure();
		$this->login_admin();
		SchemaUpgradeNotice::dismiss_for_current_user();

		$this->use_screen( $screen_id, 'designsetgo' );
		$html = $this->notice_html();

		$this->assertStringContainsString( 'max key length is 1000 bytes', $html );
		$this->assertStringNotContainsString( 'is-dismissible', $html, 'The notice cannot be dismissed on DesignSetGo pages.' );
	}

	public function designsetgo_screens(): array {
		return array(
			'dashboard'     => array( 'toplevel_page_designsetgo' ),
			'settings'      => array( 'designsetgo_page_designsetgo-settings' ),
			'dynamic query' => array( 'designsetgo_page_designsetgo-dynamic-query' ),
			'submissions'   => array( 'edit-dsgo_form_submission' ),
		);
	}

	public function test_designsetgo_page_is_recognised_by_id_without_parentage() {
		$this->record_failure();
		$this->login_admin();
		SchemaUpgradeNotice::dismiss_for_current_user();

		$this->use_screen( 'toplevel_page_designsetgo' );

		$this->assertStringContainsString( 'notice-error', $this->notice_html() );
	}

	public function test_dismissal_is_per_user() {
		$this->record_failure();
		$this->login_admin();
		SchemaUpgradeNotice::dismiss_for_current_user();

		$this->login_admin();
		$this->use_screen( 'dashboard', 'index.php' );

		$this->assertStringContainsString( 'is-dismissible', $this->notice_html() );
	}

	public function test_new_failure_brings_dismissed_notice_back() {
		$this->record_failure( '2.7.5' );
		$this->login_admin();
		SchemaUpgradeNotice::dismiss_for_current_user();

		// A later plugin version failed too: the dismissal no longer applies.
		$this->record_failure( '2.7.6' );
		$this->use_screen( 'dashboard', 'index.php' );

		$this->assertStringContainsString( 'is-dismissible', $this->notice_html() );
	}

	public function test_dismiss_without_failure_does_nothing() {
		$user_id = $this->login_admin();

		$this->assertFalse( SchemaUpgradeNotice::dismiss_for_current_user() );
		$this->assertSame( '', get_user_meta( $user_id, SchemaUpgradeNotice::DISMISS_META, true ) );
	}

	public function test_dismiss_requires_manage_options() {
		$this->record_failure();
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( SchemaUpgradeNotice::dismiss_for_current_user() );
		$this->assertSame( '', get_user_meta( $user_id, SchemaUpgradeNotice::DISMISS_META, true ) );
	}

	public function test_successful_install_clears_every_dismissal() {
		$this->record_failure( '2.7.3' );
		$first = $this->login_admin();
		SchemaUpgradeNotice::dismiss_for_current_user();
		$second = $this->login_admin();
		SchemaUpgradeNotice::dismiss_for_current_user();

		// Plugin version changed since the failure, so this retries and succeeds.
		SchemaUpgrader::maybe_upgrade();

		$this->assertTrue( FilterIndex::table_exists() );
		$this->assertSame( '', get_user_meta( $first, SchemaUpgradeNotice::DISMISS_META, true ) );
		$this->assertSame( '', get_user_meta( $second, SchemaUpgradeNotice::DISMISS_META, true ) );
	}
}

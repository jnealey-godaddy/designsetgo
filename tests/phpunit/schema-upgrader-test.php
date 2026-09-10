<?php
/**
 * PHPUnit tests for the schema upgrade gate and its failure backoff (#551).
 *
 * @package DesignSetGo
 * @group query-block
 */

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
		$this->assertSame( '', get_echo( array( SchemaUpgrader::class, 'admin_notice' ) ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = get_echo( array( SchemaUpgrader::class, 'admin_notice' ) );
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'max key length is 1000 bytes', $html );
		$this->assertStringContainsString( 'action=' . SchemaUpgrader::RETRY_ACTION, $html );
	}

	public function test_admin_notice_silent_without_failure() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( '', get_echo( array( SchemaUpgrader::class, 'admin_notice' ) ) );
	}
}

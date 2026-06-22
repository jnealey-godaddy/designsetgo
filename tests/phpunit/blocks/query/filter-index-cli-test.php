<?php
/**
 * PHPUnit tests for FilterIndexCLI.
 *
 * Exercises each subcommand (rebuild / rebuild-filter / status / drop) by
 * stubbing WP_CLI so success/error/warning/confirm calls are captured instead
 * of terminating the process. The CLI file is loaded explicitly (the normal
 * load path in class-plugin.php is gated behind `defined('WP_CLI')`).
 *
 * @group query-block
 */

namespace {
	if ( ! class_exists( 'Dsgo_Test_WP_CLI_Error' ) ) {
		/**
		 * Thrown by our WP_CLI::error stub in place of halting the process.
		 */
		class Dsgo_Test_WP_CLI_Error extends \RuntimeException {}
	}

	if ( ! class_exists( 'WP_CLI' ) ) {
		/**
		 * Minimal WP_CLI stub. Captures success/error/warning/confirm calls on
		 * a static ring buffer so tests can assert on them without a real CLI.
		 */
		class WP_CLI {

			/** @var array<int,array<string,mixed>> */
			public static array $calls = array();

			public static bool $confirm_yes = true;

			public static function reset_calls(): void {
				self::$calls = array();
			}

			public static function success( $msg ): void {
				self::$calls[] = array( 'type' => 'success', 'message' => $msg );
			}

			public static function warning( $msg ): void {
				self::$calls[] = array( 'type' => 'warning', 'message' => $msg );
			}

			public static function error( $msg ): void {
				self::$calls[] = array( 'type' => 'error', 'message' => $msg );
				throw new \Dsgo_Test_WP_CLI_Error( (string) $msg );
			}

			public static function confirm( $msg, $assoc = array() ): void {
				self::$calls[] = array( 'type' => 'confirm', 'message' => $msg );
				if ( ! self::$confirm_yes && empty( $assoc['yes'] ) ) {
					throw new \Dsgo_Test_WP_CLI_Error( 'confirm:no' );
				}
			}

			public static function add_command( $name, $class ): void {
				self::$calls[] = array( 'type' => 'add_command', 'name' => $name );
			}
		}
	}
}

namespace WP_CLI\Utils {
	if ( ! function_exists( 'WP_CLI\\Utils\\get_flag_value' ) ) {
		function get_flag_value( $assoc_args, $flag, $default = null ) {
			return $assoc_args[ $flag ] ?? $default;
		}
	}

	if ( ! function_exists( 'WP_CLI\\Utils\\format_items' ) ) {
		function format_items( $format, $items, $fields ): void {
			// Swallow — tests don't care about tabular output, only non-failure.
		}
	}
}

namespace {
	use DesignSetGo\Blocks\Query\FilterIndex;
	use DesignSetGo\Blocks\Query\FilterIndexCLI;
	use DesignSetGo\Blocks\Query\FilterIndexRebuilder;
	use DesignSetGo\Blocks\Query\FilterRegistry;

	if ( ! class_exists( 'DesignSetGo\\Blocks\\Query\\FilterIndexCLI' ) ) {
		require_once DESIGNSETGO_PATH . 'includes/blocks/query/class-query-filter-index-cli.php';
	}

	/**
	 * FilterIndexCLI tests.
	 */
	class DesignSetGo_Query_Filter_Index_CLI_Test extends \WP_UnitTestCase {

		public function set_up(): void {
			parent::set_up();
			\WP_CLI::reset_calls();
			\WP_CLI::$confirm_yes = true;
		}

		public function tear_down(): void {
			global $wpdb;
			parent::tear_down();
			$wpdb->query( 'COMMIT' );
			$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'dsgo_query_filter_index' );
			delete_option( FilterIndex::OPTION_SCHEMA );
			delete_option( FilterIndex::OPTION_STATUS );
			delete_option( FilterRegistry::OPTION );
			delete_option( 'designsetgo_db_version' );
			delete_option( FilterIndexRebuilder::LOCK_OPTION );
			wp_cache_flush();
			FilterIndex::reset_table_cache();
			FilterRegistry::bust_cache();
		}

		public function test_register_is_noop_when_wp_cli_not_defined() {
			$this->assertFalse( defined( 'WP_CLI' ), 'Test harness must not define the WP_CLI constant.' );
			FilterIndexCLI::register();
			$this->assertTrue( true, 'register() under no-WP_CLI must return without fatal.' );
		}

		public function test_rebuild_indexes_posts_and_reports_success() {
			FilterIndex::install();
			FilterRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

			$cat = $this->factory->category->create();
			$this->factory->post->create_many( 3, array(
				'post_status'   => 'publish',
				'post_category' => array( $cat ),
			) );

			$cli = new FilterIndexCLI();
			$cli->rebuild( array(), array() );

			$success = $this->find_call( 'success' );
			$this->assertNotNull( $success, 'rebuild must emit a success message.' );
			$this->assertStringContainsString( 'Indexed', (string) $success['message'] );
		}

		public function test_rebuild_filter_warns_on_unregistered_key() {
			FilterIndex::install();

			$cli = new FilterIndexCLI();
			$cli->rebuild_filter( array( 'unregistered_key' ), array() );

			$warning = $this->find_call( 'warning' );
			$this->assertNotNull( $warning, 'rebuild-filter on unknown key must emit a warning.' );
			$this->assertStringContainsString( 'unregistered_key', (string) $warning['message'] );

			$this->assertNull(
				$this->find_call( 'success' ),
				'rebuild-filter must NOT emit success when the key is unregistered.'
			);
		}

		public function test_rebuild_filter_requires_key() {
			$this->expectException( \Dsgo_Test_WP_CLI_Error::class );

			$cli = new FilterIndexCLI();
			$cli->rebuild_filter( array(), array() );
		}

		public function test_status_runs_without_fatal() {
			FilterIndex::install();
			$cli = new FilterIndexCLI();
			$cli->status();
			$this->assertTrue( true, 'status() must complete without a fatal.' );
		}

		public function test_drop_removes_table_and_clears_db_version_option() {
			FilterIndex::install();
			update_option( 'designsetgo_db_version', '2.2.0' );

			global $wpdb;
			$this->assertSame(
				$wpdb->prefix . 'dsgo_query_filter_index',
				$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( FilterIndex::table_name() ) ) ),
				'Table must exist before drop.'
			);

			$cli = new FilterIndexCLI();
			$cli->drop( array(), array( 'yes' => true ) );

			FilterIndex::reset_table_cache();
			$this->assertNull(
				$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( FilterIndex::table_name() ) ) ),
				'drop must remove the filter index table.'
			);
			$this->assertFalse(
				get_option( 'designsetgo_db_version' ),
				'drop must clear designsetgo_db_version so maybe_upgrade() reinstalls on next admin_init.'
			);
			$this->assertFalse(
				get_option( FilterIndex::OPTION_SCHEMA ),
				'drop must clear the schema version option.'
			);

			$success = $this->find_call( 'success' );
			$this->assertNotNull( $success, 'drop must emit a success message after confirmation.' );
		}

		public function test_drop_bails_when_user_declines_confirmation() {
			FilterIndex::install();
			\WP_CLI::$confirm_yes = false;

			try {
				$cli = new FilterIndexCLI();
				$cli->drop( array(), array() );
				$this->fail( 'drop must abort when confirm returns "no".' );
			} catch ( \Dsgo_Test_WP_CLI_Error $e ) {
				$this->assertSame( 'confirm:no', $e->getMessage() );
			}

			global $wpdb;
			FilterIndex::reset_table_cache();
			$this->assertNotNull(
				$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( FilterIndex::table_name() ) ) ),
				'Table must NOT be dropped when confirmation is declined.'
			);
		}

		// -----------------------------------------------------------------

		private function find_call( string $type ): ?array {
			foreach ( \WP_CLI::$calls as $call ) {
				if ( ( $call['type'] ?? '' ) === $type ) {
					return $call;
				}
			}
			return null;
		}
	}
}

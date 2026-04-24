<?php
/**
 * Dynamic Query — Filter Index WP-CLI commands.
 *
 * @package DesignSetGo
 * @since 2.2.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI commands for managing the filter index.
 *
 * The class is always declared so PHPUnit can exercise command methods
 * directly (with a stubbed WP_CLI). Actual command-namespace binding only
 * happens from register() when WP-CLI is the active SAPI.
 */
class FilterIndexCLI {

	/**
	 * Registers the WP-CLI command namespace. No-op outside CLI.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}
		\WP_CLI::add_command( 'dsgo query index', __CLASS__ );
	}

	/**
	 * Rebuild the full filter index.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<n>]
	 * : Posts to process per batch. Default 200, min 50.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dsgo query index rebuild
	 *     wp dsgo query index rebuild --batch-size=500
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Named arguments (batch-size).
	 */
	public function rebuild( $args, $assoc_args ): void {
		$batch  = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 200 );
		$result = FilterIndexRebuilder::rebuild_all( array( 'batch_size' => $batch ) );

		if ( 'error' === ( $result['status'] ?? '' ) ) {
			\WP_CLI::error( sprintf( 'Rebuild failed (status: %s).', $result['status'] ) );
		}

		\WP_CLI::success(
			sprintf(
				'Indexed %d objects (%d rows).',
				(int) $result['processed'],
				(int) $result['total_rows']
			)
		);
	}

	/**
	 * Rebuild a single filter.
	 *
	 * ## OPTIONS
	 *
	 * <filter_key>
	 * : The filter key to rebuild (e.g. 'category', 'post_tag', 'price').
	 *
	 * [--batch-size=<n>]
	 * : Posts to process per batch. Default 200.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dsgo query index rebuild-filter category
	 *     wp dsgo query index rebuild-filter price --batch-size=500
	 *
	 * @subcommand rebuild-filter
	 *
	 * @param array $args       Positional arguments: $args[0] = filter_key.
	 * @param array $assoc_args Named arguments (batch-size).
	 */
	public function rebuild_filter( $args, $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Filter key is required.' );
		}

		$batch  = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 200 );
		$result = FilterIndexRebuilder::rebuild_filter( $args[0], array( 'batch_size' => $batch ) );

		if ( 'skipped' === ( $result['status'] ?? '' ) ) {
			\WP_CLI::warning( sprintf( 'Filter "%s" is not registered — nothing to do.', $args[0] ) );
			return;
		}

		if ( 'error' === ( $result['status'] ?? '' ) ) {
			\WP_CLI::error( 'Rebuild failed.' );
		}

		\WP_CLI::success(
			sprintf(
				'Rebuilt filter "%s" (%d objects, %d rows).',
				$args[0],
				(int) $result['processed'],
				(int) $result['total_rows']
			)
		);
	}

	/**
	 * Show current filter index status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dsgo query index status
	 */
	public function status(): void {
		$status = FilterIndexRebuilder::status();

		if ( ! empty( $status['last_rebuilt_at'] ) && is_numeric( $status['last_rebuilt_at'] ) ) {
			$status['last_rebuilt_at'] = gmdate( 'Y-m-d H:i:s', (int) $status['last_rebuilt_at'] ) . ' UTC';
		} elseif ( empty( $status['last_rebuilt_at'] ) ) {
			$status['last_rebuilt_at'] = 'never';
		}

		$status['in_progress'] = $status['in_progress'] ? 'yes' : 'no';

		\WP_CLI\Utils\format_items( 'table', array( $status ), array_keys( $status ) );
	}

	/**
	 * Drop the filter index table and clear its options.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dsgo query index drop
	 *     wp dsgo query index drop --yes
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Named arguments (yes).
	 */
	public function drop( $args, $assoc_args ): void {
		\WP_CLI::confirm( 'This will drop the filter index table and all its data. Continue?', $assoc_args );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange -- intentional CLI drop; %i correctly escapes the identifier.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', FilterIndex::table_name() ) );
		delete_option( FilterIndex::OPTION_SCHEMA );
		delete_option( FilterIndex::OPTION_STATUS );
		// Also clear the plugin db version so the next admin_init fires
		// maybe_upgrade() and reinstalls the table. Without this, the stored
		// version is still '2.2.0' and the install logic is skipped.
		delete_option( 'designsetgo_db_version' );

		\WP_CLI::success( 'Filter index table dropped.' );
	}
}

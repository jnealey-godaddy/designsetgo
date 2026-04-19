<?php
/**
 * Dynamic Query — Facet Index WP-CLI commands.
 *
 * @package DesignSetGo
 * @since 2.2.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

// Only loaded under WP-CLI — class body is harmless if file is required in non-CLI context,
// but the `register()` call is what actually binds it to WP_CLI.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * WP-CLI commands for managing the facet index.
 */
class FacetCLI {

	/**
	 * Registers the WP-CLI command namespace.
	 *
	 * @return void
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'dsgo query index', __CLASS__ );
	}

	/**
	 * Rebuild the full facet index.
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
		$result = FacetIndexRebuilder::rebuild_all( array( 'batch_size' => $batch ) );

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
	 * Rebuild a single facet.
	 *
	 * ## OPTIONS
	 *
	 * <facet_key>
	 * : The facet key to rebuild (e.g. 'category', 'post_tag', 'price').
	 *
	 * [--batch-size=<n>]
	 * : Posts to process per batch. Default 200.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dsgo query index rebuild-facet category
	 *     wp dsgo query index rebuild-facet price --batch-size=500
	 *
	 * @subcommand rebuild-facet
	 *
	 * @param array $args       Positional arguments: $args[0] = facet_key.
	 * @param array $assoc_args Named arguments (batch-size).
	 */
	public function rebuild_facet( $args, $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Facet key is required.' );
		}

		$batch  = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 200 );
		$result = FacetIndexRebuilder::rebuild_facet( $args[0], array( 'batch_size' => $batch ) );

		if ( 'skipped' === ( $result['status'] ?? '' ) ) {
			\WP_CLI::warning( sprintf( 'Facet "%s" is not registered — nothing to do.', $args[0] ) );
			return;
		}

		if ( 'error' === ( $result['status'] ?? '' ) ) {
			\WP_CLI::error( 'Rebuild failed.' );
		}

		\WP_CLI::success(
			sprintf(
				'Rebuilt facet "%s" (%d objects, %d rows).',
				$args[0],
				(int) $result['processed'],
				(int) $result['total_rows']
			)
		);
	}

	/**
	 * Show current facet index status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dsgo query index status
	 */
	public function status(): void {
		$status = FacetIndexRebuilder::status();

		if ( ! empty( $status['last_rebuilt_at'] ) && is_numeric( $status['last_rebuilt_at'] ) ) {
			$status['last_rebuilt_at'] = gmdate( 'Y-m-d H:i:s', (int) $status['last_rebuilt_at'] ) . ' UTC';
		} elseif ( empty( $status['last_rebuilt_at'] ) ) {
			$status['last_rebuilt_at'] = 'never';
		}

		$status['in_progress'] = $status['in_progress'] ? 'yes' : 'no';

		\WP_CLI\Utils\format_items( 'table', array( $status ), array_keys( $status ) );
	}

	/**
	 * Drop the facet index table and clear its options.
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
		\WP_CLI::confirm( 'This will drop the facet index table and all its data. Continue?', $assoc_args );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared -- intentional CLI drop; table name is a safe internal method call.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . FacetIndex::table_name() );
		delete_option( FacetIndex::OPTION_SCHEMA );
		delete_option( FacetIndex::OPTION_STATUS );
		// Also clear the plugin db version so the next admin_init fires
		// maybe_upgrade() and reinstalls the table. Without this, the stored
		// version is still '2.2.0' and the install logic is skipped.
		delete_option( 'designsetgo_db_version' );

		\WP_CLI::success( 'Facet index table dropped.' );
	}
}

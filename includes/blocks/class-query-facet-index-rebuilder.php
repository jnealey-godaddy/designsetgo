<?php
/**
 * Dynamic Query — Facet Index Rebuilder.
 *
 * Bulk index operations: full rebuild, single-facet rebuild, status reporting.
 * Used by the admin dashboard (B5) and WP-CLI commands (A7).
 *
 * @package DesignSetGo
 * @since 2.2.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Provides static methods for bulk facet index operations.
 *
 * Reads FacetIndex::OPTION_STATUS (canonical location) for status tracking.
 * All methods are pure static — no hooks registered, no instantiation needed.
 */
class FacetIndexRebuilder {

	/**
	 * Default number of posts to process per batch in rebuild_all.
	 */
	const DEFAULT_BATCH_SIZE = 200;

	/**
	 * Minimum allowed batch size (enforced via max() to prevent tiny batches).
	 */
	const MIN_BATCH_SIZE = 50;

	/**
	 * Truncates the index and repopulates it from all published posts.
	 *
	 * Batch-scans the posts table in chunks of $args['batch_size'] (default 200,
	 * minimum 50). Writes progress to FacetIndex::OPTION_STATUS so callers can
	 * poll status() during a long run (A7 / B5).
	 *
	 * @param array $args {
	 *     Optional overrides.
	 *     @type int $batch_size Number of posts per iteration. Default 200, min 50.
	 * }
	 * @return array {
	 *     @type string $status     'complete'.
	 *     @type int    $processed  Number of post IDs iterated.
	 *     @type int    $total_rows Total index rows after rebuild.
	 * }
	 */
	public static function rebuild_all( array $args = array() ): array {
		global $wpdb;
		$batch_size = max( self::MIN_BATCH_SIZE, (int) ( $args['batch_size'] ?? self::DEFAULT_BATCH_SIZE ) );
		$started_at = microtime( true );

		self::write_status(
			array(
				'in_progress' => true,
				'started_at'  => time(),
				'processed'   => 0,
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional truncate for full rebuild.
		$wpdb->query( 'TRUNCATE ' . FacetIndex::table_name() );

		$processed = 0;
		$offset    = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- batched id scan over core posts table.
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' ORDER BY ID ASC LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			) );

			foreach ( $ids as $id ) {
				FacetIndex::reindex_object( 'post', (int) $id );
				$processed++;
			}

			self::write_status(
				array(
					'in_progress' => count( $ids ) === $batch_size,
					'processed'   => $processed,
					'updated_at'  => time(),
				)
			);

			$offset += $batch_size;
		} while ( count( $ids ) === $batch_size );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- final row count after rebuild.
		$total_rows  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . FacetIndex::table_name() );
		$duration_ms = (int) ( ( microtime( true ) - $started_at ) * 1000 );

		self::write_status(
			array(
				'in_progress'     => false,
				'last_rebuilt_at' => time(),
				'duration_ms'     => $duration_ms,
				'processed'       => $processed,
				'total_rows'      => $total_rows,
			)
		);

		return array(
			'status'     => 'complete',
			'processed'  => $processed,
			'total_rows' => $total_rows,
		);
	}

	/**
	 * Wipes all index rows for a single facet key and repopulates them.
	 *
	 * Because reindex_object() rewrites ALL facets for a given post, calling
	 * this method will also refresh other facets' rows for each post it touches.
	 * Per-post partial reindex is deferred to v2.5+.
	 *
	 * Returns early with status='skipped' if the key is not registered.
	 *
	 * @param string $facet_key The registered facet key to rebuild (e.g. 'category').
	 * @return array {
	 *     @type string $status     'complete' or 'skipped'.
	 *     @type int    $processed  Number of post IDs iterated (0 when skipped).
	 *     @type int    $total_rows Rows for this key after rebuild (0 when skipped).
	 * }
	 */
	public static function rebuild_facet( string $facet_key ): array {
		$key = sanitize_key( $facet_key );
		if ( '' === $key || null === FacetRegistry::get( $key ) ) {
			return array(
				'status'     => 'skipped',
				'processed'  => 0,
				'total_rows' => 0,
			);
		}

		global $wpdb;
		$table = FacetIndex::table_name();

		// Delete all rows for this facet key.
		$wpdb->delete( $table, array( 'facet_key' => $key ), array( '%s' ) );

		// Re-scan every published post; reindex_object rewrites ALL facets for
		// each post, including the one we just wiped.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- full post scan required for facet rebuild.
		$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish'" );
		foreach ( $post_ids as $id ) {
			FacetIndex::reindex_object( 'post', (int) $id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- count rows for the rebuilt key.
		$total_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE facet_key = %s", $key ) );

		return array(
			'status'     => 'complete',
			'processed'  => count( $post_ids ),
			'total_rows' => $total_rows,
		);
	}

	/**
	 * Returns the current index status.
	 *
	 * Reads FacetIndex::OPTION_STATUS and supplements it with a live row count
	 * so the caller always gets an up-to-date snapshot without needing a full
	 * rebuild to have run first.
	 *
	 * @return array {
	 *     @type int        $total_rows      Current row count in the index table.
	 *     @type bool       $in_progress     Whether a rebuild is currently running.
	 *     @type int|null   $last_rebuilt_at Unix timestamp of the last completed rebuild, or null.
	 *     @type int        $processed       Posts processed in the last (or current) rebuild.
	 * }
	 */
	public static function status(): array {
		global $wpdb;
		$status = get_option( FacetIndex::OPTION_STATUS, array() );
		if ( ! is_array( $status ) ) {
			$status = array();
		}

		// Always surface a live row count and normalise required keys.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- live count for status reporting.
		$status['total_rows']      = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . FacetIndex::table_name() );
		$status['in_progress']     = (bool) ( $status['in_progress'] ?? false );
		$status['last_rebuilt_at'] = $status['last_rebuilt_at'] ?? null;
		$status['processed']       = (int) ( $status['processed'] ?? 0 );

		return $status;
	}

	/**
	 * Merges a patch array into the stored OPTION_STATUS option.
	 *
	 * Uses autoload=false because this option changes frequently during a
	 * rebuild and does not need to be loaded on every request.
	 *
	 * @param array $patch Key-value pairs to merge into the current status.
	 * @return void
	 */
	private static function write_status( array $patch ): void {
		$current = get_option( FacetIndex::OPTION_STATUS, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		update_option( FacetIndex::OPTION_STATUS, array_merge( $current, $patch ), false );
	}
}

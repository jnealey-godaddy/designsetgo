<?php
/**
 * Dynamic Query — Filter Index Rebuilder.
 *
 * Bulk index operations: full rebuild, single-filter rebuild, status reporting.
 * Used by the admin dashboard (B5) and WP-CLI commands (A7).
 *
 * @package DesignSetGo
 * @since 2.2.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Provides static methods for bulk filter index operations.
 *
 * Reads FilterIndex::OPTION_STATUS (canonical location) for status tracking.
 * All methods are pure static — no hooks registered, no instantiation needed.
 */
class FilterIndexRebuilder {

	/**
	 * Default number of posts to process per batch in rebuild_all.
	 */
	const DEFAULT_BATCH_SIZE = 200;

	/**
	 * Minimum allowed batch size (enforced via max() to prevent tiny batches).
	 */
	const MIN_BATCH_SIZE = 50;

	/**
	 * WordPress option key used as a rebuild mutex.
	 */
	const LOCK_OPTION = 'dsgo_query_filter_rebuild_lock';

	/**
	 * Maximum seconds a rebuild lock is considered valid before being treated as stale.
	 *
	 * Kept in sync with the status() timeout (same 5-minute window) so the fast
	 * status-poll path and the lock gate agree on "idle vs busy" and don't
	 * produce a confusing "dashboard says idle, API says locked" UX.
	 */
	const LOCK_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Returns true if a rebuild lock is currently held and not stale.
	 *
	 * @return bool
	 */
	private static function is_locked(): bool {
		$locked_at = (int) get_option( self::LOCK_OPTION, 0 );
		return $locked_at > 0 && ( time() - $locked_at ) < self::LOCK_TTL;
	}

	/**
	 * Attempts to acquire the rebuild mutex atomically.
	 *
	 * `add_option` is backed by an `INSERT ... ON DUPLICATE KEY UPDATE` no-op at
	 * the MySQL level, giving us atomic gate semantics with no TOCTOU window.
	 * If the option already exists we check whether the stored timestamp is
	 * stale (older than LOCK_TTL) and retry once if so, so a crashed prior
	 * process can't deadlock future rebuilds.
	 *
	 * @return bool True when the lock was acquired; false when another active
	 *              process genuinely holds it.
	 */
	private static function acquire_lock(): bool {
		if ( add_option( self::LOCK_OPTION, time(), '', false ) ) {
			return true;
		}
		// add_option failed — option exists. Honour an active lock.
		if ( self::is_locked() ) {
			return false;
		}
		// Stale lock — clear and retry once. A concurrent retry will win the race.
		delete_option( self::LOCK_OPTION );
		return (bool) add_option( self::LOCK_OPTION, time(), '', false );
	}

	/**
	 * Pushes the lock's timestamp forward so a long rebuild never outlives it.
	 *
	 * @return void
	 */
	private static function refresh_lock(): void {
		update_option( self::LOCK_OPTION, time(), false );
	}

	/**
	 * Releases the rebuild mutex by deleting the lock option.
	 *
	 * @return void
	 */
	private static function release_lock(): void {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Repopulates the index from all published posts, then prunes stale rows.
	 *
	 * Batch-scans the posts table in chunks of $args['batch_size'] (default 200,
	 * minimum 50). Writes progress to FilterIndex::OPTION_STATUS so callers can
	 * poll status() during a long run (A7 / B5).
	 *
	 * @param array $args {
	 *     Optional overrides.
	 *     @type int $batch_size Number of posts per iteration. Default 200, min 50.
	 * }
	 * @return array {
	 *     @type string $status     'complete' or 'locked'.
	 *     @type int    $processed  Number of post IDs iterated.
	 *     @type int    $total_rows Total index rows after rebuild.
	 * }
	 */
	public static function rebuild_all( array $args = array() ): array {
		if ( ! self::acquire_lock() ) {
			return array(
				'status'     => 'locked',
				'processed'  => 0,
				'total_rows' => 0,
			);
		}

		try {
			return self::do_rebuild_all( $args );
		} finally {
			self::release_lock();
		}
	}

	/**
	 * Inner implementation of rebuild_all — called inside the mutex.
	 *
	 * @param array $args Optional overrides (batch_size).
	 * @return array Result array (status, processed, total_rows).
	 */
	private static function do_rebuild_all( array $args ): array {
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

		// Short-circuit with a clean error status if the table hasn't been
		// installed yet. Without this guard the TRUNCATE below emits a loud
		// "table doesn't exist" notice before write_status runs.
		FilterIndex::reset_table_cache();
		if ( ! FilterIndex::table_exists() ) {
			self::write_status(
				array(
					'in_progress' => false,
					'error'       => 'table_missing',
					'updated_at'  => time(),
				)
			);
			return array(
				'status'     => 'error',
				'processed'  => 0,
				'total_rows' => 0,
			);
		}

		$table = FilterIndex::table_name();

		// Keyset pagination: track the last ID seen and query `WHERE ID > $last_id`.
		// OFFSET degrades quadratically because MySQL must scan all skipped rows;
		// keyset uses the `posts.ID` PK index to jump directly to the next batch.
		$processed = 0;
		$last_id   = 0;
		$ids_count = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- batched id scan over core posts table.
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND ID > %d ORDER BY ID ASC LIMIT %d",
					$last_id,
					$batch_size
				)
			);

			foreach ( $ids as $id ) {
				$id = (int) $id;
				FilterIndex::reindex_object( 'post', $id );
				if ( $id > $last_id ) {
					$last_id = $id;
				}
				++$processed;
			}

			$ids_count = count( $ids );
			self::refresh_lock();
			self::write_status(
				array(
					'in_progress' => $ids_count === $batch_size,
					'processed'   => $processed,
					'updated_at'  => time(),
				)
			);
		} while ( $ids_count === $batch_size );

		self::prune_stale_rows( $table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- live count for the rebuild status; %i correctly escapes the identifier.
		$total_rows  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
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
	 * Removes rows the rebuild did not rewrite: posts that are gone or no
	 * longer published, and filter keys that are no longer registered.
	 *
	 * The index is rewritten object by object instead of truncated up front, so
	 * filter counts stay populated while a rebuild runs.
	 *
	 * @param string $table Index table name.
	 * @return void
	 */
	private static function prune_stale_rows( string $table ): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table maintenance; %i escapes identifiers.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE i FROM %i i LEFT JOIN {$wpdb->posts} p ON p.ID = i.object_id AND p.post_status = 'publish' WHERE i.object_type = 'post' AND p.ID IS NULL",
				$table
			)
		);
		$keys = array_map( 'strval', array_keys( FilterRegistry::all() ) );
		if ( empty( $keys ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
		} else {
			$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated from the key count.
					"DELETE FROM %i WHERE filter_key NOT IN ({$placeholders})",
					array_merge( array( $table ), $keys )
				)
			);
		}
		// phpcs:enable
		FilterIndex::bump_counts_cache();
	}

	/**
	 * Wipes all index rows for a single filter key and repopulates them.
	 *
	 * Because reindex_object() rewrites ALL filters for a given post, calling
	 * this method will also refresh other filters' rows for each post it touches.
	 * Per-post partial reindex is deferred to v2.5+.
	 *
	 * Returns early with status='skipped' if the key is not registered.
	 *
	 * Processes posts in batches to avoid max_execution_time on large sites.
	 * Writes intermediate progress to FilterIndex::OPTION_STATUS so callers can
	 * poll status() during a long run (A7 / B5).
	 *
	 * @param string $filter_key The registered filter key to rebuild (e.g. 'category').
	 * @param array  $args {
	 *     Optional overrides.
	 *     @type int $batch_size Number of posts per iteration. Default 200, min 50.
	 * }
	 * @return array {
	 *     @type string $status     'complete' or 'skipped'.
	 *     @type int    $processed  Number of post IDs iterated (0 when skipped).
	 *     @type int    $total_rows Rows for this key after rebuild (0 when skipped).
	 * }
	 */
	public static function rebuild_filter( string $filter_key, array $args = array() ): array {
		$key = sanitize_key( $filter_key );
		if ( '' === $key || null === FilterRegistry::get( $key ) ) {
			return array(
				'status'     => 'skipped',
				'processed'  => 0,
				'total_rows' => 0,
			);
		}

		if ( ! self::acquire_lock() ) {
			return array(
				'status'     => 'locked',
				'processed'  => 0,
				'total_rows' => 0,
			);
		}

		try {
			return self::do_rebuild_filter( $key, $args );
		} finally {
			self::release_lock();
		}
	}

	/**
	 * Inner implementation of rebuild_filter — called inside the mutex.
	 *
	 * @param string $key  Sanitized filter key.
	 * @param array  $args Optional overrides (batch_size).
	 * @return array Result array (status, processed, total_rows).
	 */
	private static function do_rebuild_filter( string $key, array $args ): array {
		global $wpdb;
		$table      = FilterIndex::table_name();
		$batch_size = max( self::MIN_BATCH_SIZE, (int) ( $args['batch_size'] ?? self::DEFAULT_BATCH_SIZE ) );

		self::write_status(
			array(
				'in_progress' => true,
				'started_at'  => time(),
				'processed'   => 0,
			)
		);

		// Keyset pagination (see do_rebuild_all for rationale).
		$processed = 0;
		$last_id   = 0;
		$ids_count = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- batched id scan over core posts table.
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND ID > %d ORDER BY ID ASC LIMIT %d",
					$last_id,
					$batch_size
				)
			);

			foreach ( $ids as $id ) {
				$id = (int) $id;
				FilterIndex::reindex_object( 'post', $id );
				if ( $id > $last_id ) {
					$last_id = $id;
				}
				++$processed;
			}

			$ids_count = count( $ids );
			self::refresh_lock();
			self::write_status(
				array(
					'in_progress' => $ids_count === $batch_size,
					'processed'   => $processed,
					'updated_at'  => time(),
				)
			);
		} while ( $ids_count === $batch_size );

		self::prune_stale_rows( $table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- live count for the rebuild status; %i correctly escapes the identifier.
		$total_rows = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE filter_key = %s', $table, $key ) );

		self::write_status(
			array(
				'in_progress'     => false,
				'last_rebuilt_at' => time(),
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
	 * Returns the current index status.
	 *
	 * Reads FilterIndex::OPTION_STATUS and supplements it with a live row count
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
		$status = get_option( FilterIndex::OPTION_STATUS, array() );
		if ( ! is_array( $status ) ) {
			$status = array();
		}

		// Auto-clear stale in-progress state (timeout safeguard). Releasing the
		// mutex at the same moment prevents the dashboard from showing "idle"
		// while a later Rebuild click comes back with "locked" until LOCK_TTL
		// also elapses — the two TTLs are aligned but a poll can still race
		// a new click, so unlock explicitly.
		if ( ! empty( $status['in_progress'] ) && ! empty( $status['started_at'] ) ) {
			// Idle time since the last batch, not total runtime: a big rebuild
			// legitimately runs longer than the lock TTL.
			$last_activity = max( (int) $status['started_at'], (int) ( $status['updated_at'] ?? 0 ) );
			if ( time() - $last_activity > self::LOCK_TTL ) {
				$status['in_progress'] = false;
				$status['error']       = 'timed_out';
				update_option( FilterIndex::OPTION_STATUS, $status, false );
				self::release_lock();
			}
		}

		// Always surface a live row count and normalise required keys.
		$index_table          = FilterIndex::table_name();
		$status['total_rows'] = FilterIndex::table_exists()
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- live count for status output; %i correctly escapes the identifier.
			? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $index_table ) )
			: 0;
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
		$current = get_option( FilterIndex::OPTION_STATUS, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		update_option( FilterIndex::OPTION_STATUS, array_merge( $current, $patch ), false );
	}
}

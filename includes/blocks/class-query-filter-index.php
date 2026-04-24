<?php
/**
 * Dynamic Query Block — Filter Index lifecycle.
 *
 * Owns creation, versioning and future reindex logic for the
 * {$wpdb->prefix}dsgo_query_filter_index custom table.
 *
 * @package DesignSetGo
 * @since 2.2.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the dsgo_query_filter_index database table.
 */
class FilterIndex {

	/**
	 * Current schema version.
	 *
	 * Increment when columns or indexes change and add migration logic in
	 * install() before updating the stored option.
	 *
	 * v2 (2026-04-19): adds `post_type` column so count_for_options can scope
	 * per-CPT. Upgrade TRUNCATEs the table — see install().
	 */
	const SCHEMA_VERSION = '2';

	/**
	 * Option key that stores the installed schema version.
	 */
	const OPTION_SCHEMA = 'dsgo_query_filter_index_schema';

	/**
	 * Option key used to record background index status (e.g. "indexing", "ready").
	 * Reserved for future reindex tasks (Task A2+).
	 */
	const OPTION_STATUS = 'dsgo_query_filter_index_status';

	/**
	 * Object-cache group for count_for_options() results.
	 *
	 * Cache entries carry the current epoch in their key so a bump invalidates
	 * every outstanding entry without needing to enumerate keys (which many
	 * object-cache backends do not support).
	 */
	const CACHE_GROUP = 'designsetgo_query_filter_index';

	/**
	 * Cache-epoch key. Read via wp_cache_get / bumped via wp_cache_incr so a
	 * persistent object cache (Redis/Memcached) invalidates count results
	 * atomically without hitting the options table on each write.
	 */
	const CACHE_EPOCH_KEY = 'counts_epoch';

	/**
	 * TTL (seconds) for cached count results. Counts stay correct until the
	 * next write (via the epoch), but we still TTL-cap in case the epoch
	 * counter is lost (non-persistent cache restart) — bounded staleness.
	 */
	const CACHE_TTL = 300;

	/**
	 * Per-request cache of the table existence check.
	 *
	 * Null = not yet checked; true/false = checked result.
	 *
	 * @var bool|null
	 */
	private static $table_exists = null;

	/**
	 * Returns the fully-qualified table name.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'dsgo_query_filter_index';
	}

	/**
	 * Returns true when the filter index table actually exists in the database.
	 *
	 * Result is cached for the lifetime of the request to avoid repeated
	 * SHOW TABLES queries. Call reset_table_cache() in tests between cases.
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		if ( null !== self::$table_exists ) {
			return self::$table_exists;
		}
		global $wpdb;
		$table_name          = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SHOW TABLES LIKE is the correct idiom; prepare() handles escaping.
		self::$table_exists  = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) === $table_name );
		return self::$table_exists;
	}

	/**
	 * Resets the per-request table existence cache (for tests).
	 *
	 * @return void
	 */
	public static function reset_table_cache(): void {
		self::$table_exists = null;
	}

	/**
	 * Reindexes a single object's filter values.
	 *
	 * Deletes all prior rows for this (object_type, object_id) and rewrites them
	 * based on the current FilterRegistry entries. Idempotent by design.
	 *
	 * @param string $object_type One of 'post' (A2), 'user' (v2.4+), 'term' (v2.4+).
	 * @param int    $object_id   The object's primary key.
	 */
	public static function reindex_object( string $object_type, int $object_id ): void {
		global $wpdb;
		if ( $object_id <= 0 || ! self::table_exists() ) {
			return;
		}

		// v2.2 indexes only published posts. If the post is not published,
		// remove any existing rows and bail. This covers the case where
		// taxonomy/meta hooks fire for a draft that was previously published.
		$post_type = '';
		if ( 'post' === $object_type ) {
			$post = get_post( $object_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				self::remove_object( 'post', $object_id );
				return;
			}
			$post_type = (string) $post->post_type;
		}

		$table = self::table_name();

		// Idempotency: wipe existing rows for this object before reinsert.
		$wpdb->delete(
			$table,
			array(
				'object_id'   => $object_id,
				'object_type' => $object_type,
			),
			array( '%d', '%s' )
		);

		$filters = FilterRegistry::all();
		if ( empty( $filters ) ) {
			return;
		}

		$rows = array();
		foreach ( $filters as $filter_key => $config ) {
			$values = self::resolve_filter_values( $object_type, $object_id, $config );
			foreach ( $values as $value ) {
				$rows[] = array(
					'object_id'    => $object_id,
					'object_type'  => $object_type,
					'post_type'    => $post_type,
					'filter_key'   => $filter_key,
					'filter_value' => (string) $value,
				);
			}
		}

		if ( empty( $rows ) ) {
			return;
		}

		// Bulk insert — one query regardless of filter count.
		$placeholders = array();
		$params       = array();
		foreach ( $rows as $row ) {
			$placeholders[] = '(%d, %s, %s, %s, %s)';
			$params[]       = $row['object_id'];
			$params[]       = $row['object_type'];
			$params[]       = $row['post_type'];
			$params[]       = $row['filter_key'];
			$params[]       = $row['filter_value'];
		}

		$sql = "INSERT INTO {$table} (object_id, object_type, post_type, filter_key, filter_value) VALUES "
			. implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built programmatically above.
		$wpdb->query( $wpdb->prepare( $sql, $params ) );

		self::bump_counts_cache();
	}

	/**
	 * Resolves filter values for a given object. Posts-only in v2.2.
	 *
	 * @param string $object_type One of 'post', 'user', 'term'.
	 * @param int    $object_id   Object primary key.
	 * @param array  $config      Filter config from FilterRegistry: { type, source, label }.
	 * @return array Flat array of string values to index.
	 */
	private static function resolve_filter_values( string $object_type, int $object_id, array $config ): array {
		if ( 'post' !== $object_type ) {
			return array(); // v2.4 will add user/term support.
		}

		$type   = $config['type'] ?? '';
		$source = $config['source'] ?? '';
		if ( '' === $source ) {
			return array();
		}

		if ( 'taxonomy' === $type ) {
			$term_ids = wp_get_post_terms( $object_id, $source, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
				return array();
			}
			return array_map( 'strval', $term_ids );
		}

		if ( 'meta' === $type ) {
			$meta = get_post_meta( $object_id, $source, false );
			if ( ! is_array( $meta ) || empty( $meta ) ) {
				return array();
			}
			// Filter out empty strings, non-scalars, and values exceeding the VARCHAR(190) column width.
			$clean = array();
			foreach ( $meta as $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}
				$value = (string) $value;
				if ( '' === $value ) {
					continue;
				}
				if ( mb_strlen( $value ) > 190 ) {
					continue; // Longer than the VARCHAR(190) index column; skip to avoid truncation.
				}
				$clean[] = $value;
			}
			return $clean;
		}

		return array();
	}

	/**
	 * Deletes all index rows for a given object.
	 *
	 * Scoped by both object_id and object_type to avoid cross-type collisions
	 * (e.g. a post and a user that happen to share the same numeric ID).
	 *
	 * @param string $object_type The object type (e.g. 'post').
	 * @param int    $object_id   The object's primary key.
	 * @return void
	 */
	public static function remove_object( string $object_type, int $object_id ): void {
		if ( ! self::table_exists() ) {
			return;
		}
		global $wpdb;
		$wpdb->delete(
			self::table_name(),
			array(
				'object_id'   => $object_id,
				'object_type' => $object_type,
			),
			array( '%d', '%s' )
		);
		self::bump_counts_cache();
	}

	/**
	 * Returns the count of distinct objects matching each option value for a
	 * filter key, intersected with the current active-filter state.
	 *
	 * Within-group semantics: selections inside the same filter group are OR
	 * (showing "how many objects would match if you added this value").
	 * Across-group semantics: each other active-filter group is AND.
	 * The self-filter is excluded from the intersection so users can still see
	 * counts for all options of the group they are currently filtering on.
	 *
	 * @param string $filter_key     The filter key to count options for (e.g. 'category').
	 * @param array  $option_values Option values to count. Values are (string)-cast.
	 * @param array  $active_filters Active filter state: [ filter_key => [ value, ... ] ].
	 * @param string $post_type     Optional. When non-empty, counts are restricted
	 *                              to rows with a matching post_type — and so are
	 *                              the intersection subqueries for active filters,
	 *                              so a CPT-scoped query never leaks counts from
	 *                              other post types that share the same taxonomy.
	 * @return array  [ value => count ] zero-filled for options absent from the result set.
	 */
	public static function count_for_options( string $filter_key, array $option_values, array $active_filters, string $post_type = '' ): array {
		if ( empty( $option_values ) || ! self::table_exists() ) {
			return array();
		}

		global $wpdb;
		$table = self::table_name();
		$key   = sanitize_key( $filter_key );
		if ( '' === $key ) {
			return array();
		}

		// Normalise option values to strings and build a keyed default (0-filled).
		$string_values = array_values( array_unique( array_map( 'strval', $option_values ) ) );
		$counts        = array_fill_keys( $string_values, 0 );

		// Exclude self-filter from intersection — OR semantics within a group.
		unset( $active_filters[ $key ] );

		// Cache lookup. Key is (epoch, filter_key, option_values, active_filters, post_type).
		// The epoch is bumped on every index write, so cached entries remain valid
		// until the next reindex/remove/rebuild. Skipped in tests where the
		// per-request table_exists cache can race with the drop-table teardown.
		$cache_key = self::build_counts_cache_key( $key, $string_values, $active_filters, $post_type );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		// Optional post_type scope — applied to the outer query AND each
		// intersection subquery so cross-filter counts stay within the CPT.
		$post_type_sql    = '';
		$subquery_pt_sql  = '';
		$post_type_params = array();
		if ( '' !== $post_type ) {
			$post_type_sql      = ' AND post_type = %s';
			$subquery_pt_sql    = ' AND post_type = %s';
			$post_type_params[] = $post_type;
		}

		// Build intersection subqueries, one per active-filter group.
		$intersect_sql    = '';
		$intersect_params = array();

		foreach ( $active_filters as $f_key => $f_values ) {
			$sanitized_f_key = sanitize_key( (string) $f_key );
			if ( '' === $sanitized_f_key || empty( $f_values ) ) {
				continue;
			}
			$f_strings = array_values( array_unique( array_map( 'strval', (array) $f_values ) ) );
			if ( empty( $f_strings ) ) {
				continue;
			}
			$f_placeholders     = implode( ',', array_fill( 0, count( $f_strings ), '%s' ) );
			$intersect_sql     .= " AND object_id IN (
            SELECT object_id FROM {$table}
            WHERE filter_key = %s AND filter_value IN ({$f_placeholders}){$subquery_pt_sql}
        )";
			$intersect_params[] = $sanitized_f_key;
			foreach ( $f_strings as $v ) {
				$intersect_params[] = $v;
			}
			if ( '' !== $post_type ) {
				$intersect_params[] = $post_type;
			}
		}

		$value_placeholders = implode( ',', array_fill( 0, count( $string_values ), '%s' ) );
		$sql                = "SELECT filter_value, COUNT(DISTINCT object_id) AS cnt
            FROM {$table}
            WHERE filter_key = %s AND filter_value IN ({$value_placeholders}){$post_type_sql}
            {$intersect_sql}
            GROUP BY filter_value";

		$params = array_merge( array( $key ), $string_values, $post_type_params, $intersect_params );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built programmatically above.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( array_key_exists( $row->filter_value, $counts ) ) {
					$counts[ $row->filter_value ] = (int) $row->cnt;
				}
			}
		}

		wp_cache_set( $cache_key, $counts, self::CACHE_GROUP, self::CACHE_TTL );

		return $counts;
	}

	/**
	 * Builds a cache key for count_for_options() that scopes the cached value to
	 * the current cache epoch + the full argument shape. Any index write bumps
	 * the epoch, so stale entries become unreachable without explicit deletion.
	 *
	 * @param string $filter_key     Sanitized filter key.
	 * @param array  $string_values  Sorted, unique option values.
	 * @param array  $active_filters Active filter state (minus self-filter).
	 * @param string $post_type      Post-type scope or empty string for all.
	 * @return string
	 */
	private static function build_counts_cache_key( string $filter_key, array $string_values, array $active_filters, string $post_type = '' ): string {
		$epoch = self::get_counts_epoch();
		// Sort values and active filters for stable key regardless of input order.
		sort( $string_values );
		ksort( $active_filters );
		foreach ( $active_filters as $k => $vs ) {
			if ( is_array( $vs ) ) {
				$vs = array_values( array_unique( array_map( 'strval', $vs ) ) );
				sort( $vs );
				$active_filters[ $k ] = $vs;
			}
		}
		return 'cfo:' . $epoch . ':' . md5(
			$filter_key . '|' . wp_json_encode( $string_values ) . '|' . wp_json_encode( $active_filters ) . '|pt=' . $post_type
		);
	}

	/**
	 * Returns the current counts cache epoch — an incrementing integer used as
	 * part of the cache key so bumping the epoch invalidates every outstanding
	 * cached result without key enumeration.
	 *
	 * @return int
	 */
	private static function get_counts_epoch(): int {
		$epoch = wp_cache_get( self::CACHE_EPOCH_KEY, self::CACHE_GROUP );
		if ( false === $epoch ) {
			$epoch = 1;
			wp_cache_set( self::CACHE_EPOCH_KEY, $epoch, self::CACHE_GROUP );
		}
		return (int) $epoch;
	}

	/**
	 * Bumps the counts cache epoch, invalidating all previously-cached
	 * count_for_options() results. Called from reindex_object / remove_object
	 * and by the rebuilder on completion. Object-cache-only (no DB write) so
	 * bumping inside tight loops is safe; with a persistent cache backend the
	 * bump is atomic via wp_cache_incr.
	 *
	 * @return void
	 */
	public static function bump_counts_cache(): void {
		$incremented = wp_cache_incr( self::CACHE_EPOCH_KEY, 1, self::CACHE_GROUP );
		if ( false === $incremented ) {
			// Non-persistent caches (or no cache yet) — seed then bump.
			$current = (int) wp_cache_get( self::CACHE_EPOCH_KEY, self::CACHE_GROUP );
			wp_cache_set( self::CACHE_EPOCH_KEY, $current + 1, self::CACHE_GROUP );
		}
	}

	/**
	 * Returns true if the given filter key is registered in FilterRegistry.
	 *
	 * @param string $filter_key The filter key to check (e.g. 'category').
	 * @return bool
	 */
	public static function is_available( string $filter_key ): bool {
		return null !== FilterRegistry::get( $filter_key );
	}

	/**
	 * Creates or upgrades the filter index table via dbDelta.
	 *
	 * Safe to call multiple times — dbDelta is idempotent.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table          = self::table_name();
		$charset        = $wpdb->get_charset_collate();
		$stored_schema  = (string) get_option( self::OPTION_SCHEMA, '0' );
		$needs_truncate = self::table_exists() && version_compare( $stored_schema, '2', '<' );

		// Note: PRIMARY KEY requires two spaces before the column name — dbDelta quirk.
		// post_type is VARCHAR(40) to match WP's wp_posts.post_type column width.
		// Empty string for non-post object_types (users/terms in v2.4+).
		$sql = "CREATE TABLE {$table} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	object_id BIGINT UNSIGNED NOT NULL,
	object_type VARCHAR(20) NOT NULL,
	post_type VARCHAR(40) NOT NULL DEFAULT '',
	filter_key VARCHAR(190) NOT NULL,
	filter_value VARCHAR(190) NOT NULL,
	indexed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY filter_key_value (filter_key, filter_value),
	KEY filter_scope (post_type, filter_key, filter_value),
	KEY object_lookup (object_type, object_id)
) {$charset};";

		dbDelta( $sql );

		// On a v1 → v2 upgrade, pre-existing rows have post_type='' (dbDelta's
		// DEFAULT fills new columns). A per-CPT count query would miss them,
		// so TRUNCATE and rely on the admin dashboard / WP-CLI rebuild to
		// reindex with correct post_type values. v2.2 has not shipped yet so
		// no production data is lost.
		if ( $needs_truncate ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- deliberate upgrade-time schema reset.
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) );
			self::bump_counts_cache();
		}

		// Reset the per-request cache so subsequent calls see the new table.
		self::$table_exists = null;

		update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, false );
	}
}

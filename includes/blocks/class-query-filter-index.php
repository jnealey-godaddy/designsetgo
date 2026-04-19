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
	 */
	const SCHEMA_VERSION = '1';

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
		if ( 'post' === $object_type ) {
			$status = get_post_status( $object_id );
			if ( 'publish' !== $status ) {
				self::remove_object( 'post', $object_id );
				return;
			}
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
					'object_id'   => $object_id,
					'object_type' => $object_type,
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
			$placeholders[] = '(%d, %s, %s, %s)';
			$params[]       = $row['object_id'];
			$params[]       = $row['object_type'];
			$params[]       = $row['filter_key'];
			$params[]       = $row['filter_value'];
		}

		$sql = "INSERT INTO {$table} (object_id, object_type, filter_key, filter_value) VALUES "
			. implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built programmatically above.
		$wpdb->query( $wpdb->prepare( $sql, $params ) );
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
	 * @return array  [ value => count ] zero-filled for options absent from the result set.
	 */
	public static function count_for_options( string $filter_key, array $option_values, array $active_filters ): array {
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
            WHERE filter_key = %s AND filter_value IN ({$f_placeholders})
        )";
			$intersect_params[] = $sanitized_f_key;
			foreach ( $f_strings as $v ) {
				$intersect_params[] = $v;
			}
		}

		$value_placeholders = implode( ',', array_fill( 0, count( $string_values ), '%s' ) );
		$sql                = "SELECT filter_value, COUNT(DISTINCT object_id) AS cnt
            FROM {$table}
            WHERE filter_key = %s AND filter_value IN ({$value_placeholders})
            {$intersect_sql}
            GROUP BY filter_value";

		$params = array_merge( array( $key ), $string_values, $intersect_params );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built programmatically above.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( array_key_exists( $row->filter_value, $counts ) ) {
					$counts[ $row->filter_value ] = (int) $row->cnt;
				}
			}
		}

		return $counts;
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

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		// Note: PRIMARY KEY requires two spaces before the column name — dbDelta quirk.
		$sql = "CREATE TABLE {$table} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	object_id BIGINT UNSIGNED NOT NULL,
	object_type VARCHAR(20) NOT NULL,
	filter_key VARCHAR(190) NOT NULL,
	filter_value VARCHAR(190) NOT NULL,
	indexed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY filter_key_value (filter_key, filter_value),
	KEY object_lookup (object_type, object_id)
) {$charset};";

		dbDelta( $sql );

		// Reset the per-request cache so subsequent calls see the new table.
		self::$table_exists = null;

		update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, false );
	}
}

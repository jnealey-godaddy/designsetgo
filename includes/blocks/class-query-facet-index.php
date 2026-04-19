<?php
/**
 * Dynamic Query Block — Facet Index lifecycle.
 *
 * Owns creation, versioning and future reindex logic for the
 * {$wpdb->prefix}dsgo_query_facet_index custom table.
 *
 * @package DesignSetGo
 * @since 2.2.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the dsgo_query_facet_index database table.
 */
class FacetIndex {

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
	const OPTION_SCHEMA = 'dsgo_query_facet_index_schema';

	/**
	 * Option key used to record background index status (e.g. "indexing", "ready").
	 * Reserved for future reindex tasks (Task A2+).
	 */
	const OPTION_STATUS = 'dsgo_query_facet_index_status';

	/**
	 * Returns the fully-qualified table name.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'dsgo_query_facet_index';
	}

	/**
	 * Reindexes a single object's facet values.
	 *
	 * Deletes all prior rows for this (object_type, object_id) and rewrites them
	 * based on the current FacetRegistry entries. Idempotent by design.
	 *
	 * @param string $object_type One of 'post' (A2), 'user' (v2.4+), 'term' (v2.4+).
	 * @param int    $object_id   The object's primary key.
	 */
	public static function reindex_object( string $object_type, int $object_id ): void {
		global $wpdb;
		if ( $object_id <= 0 ) {
			return;
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

		$facets = FacetRegistry::all();
		if ( empty( $facets ) ) {
			return;
		}

		$rows = array();
		foreach ( $facets as $facet_key => $config ) {
			$values = self::resolve_facet_values( $object_type, $object_id, $config );
			foreach ( $values as $value ) {
				$rows[] = array(
					'object_id'   => $object_id,
					'object_type' => $object_type,
					'facet_key'   => $facet_key,
					'facet_value' => (string) $value,
				);
			}
		}

		if ( empty( $rows ) ) {
			return;
		}

		// Bulk insert — one query regardless of facet count.
		$placeholders = array();
		$params       = array();
		foreach ( $rows as $row ) {
			$placeholders[] = '(%d, %s, %s, %s)';
			$params[]       = $row['object_id'];
			$params[]       = $row['object_type'];
			$params[]       = $row['facet_key'];
			$params[]       = $row['facet_value'];
		}

		$sql = "INSERT INTO {$table} (object_id, object_type, facet_key, facet_value) VALUES "
			. implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built programmatically above.
		$wpdb->query( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Resolves facet values for a given object. Posts-only in v2.2.
	 *
	 * @param string $object_type One of 'post', 'user', 'term'.
	 * @param int    $object_id   Object primary key.
	 * @param array  $config      Facet config from FacetRegistry: { type, source, label }.
	 * @return array Flat array of string values to index.
	 */
	private static function resolve_facet_values( string $object_type, int $object_id, array $config ): array {
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
			// Filter out empty strings and non-scalars (objects/arrays aren't indexable as facet values).
			$clean = array();
			foreach ( $meta as $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}
				$value = (string) $value;
				if ( '' === $value ) {
					continue;
				}
				$clean[] = $value;
			}
			return $clean;
		}

		return array();
	}

	/**
	 * Creates or upgrades the facet index table via dbDelta.
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
	facet_key VARCHAR(190) NOT NULL,
	facet_value VARCHAR(190) NOT NULL,
	indexed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY facet_key_value (facet_key, facet_value),
	KEY object_lookup (object_type, object_id)
) {$charset};";

		dbDelta( $sql );

		update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, false );
	}
}

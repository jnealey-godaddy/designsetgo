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

		update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION );
	}
}

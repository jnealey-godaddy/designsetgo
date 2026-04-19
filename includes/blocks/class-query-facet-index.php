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
	 * Registers WordPress lifecycle hooks so the index stays current.
	 *
	 * Idempotent — a second call is a no-op because we guard on has_action().
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		if ( has_action( 'save_post', array( __CLASS__, 'on_save_post' ) ) ) {
			return;
		}
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 2 );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted_post' ), 20, 1 );
		add_action( 'set_object_terms', array( __CLASS__, 'on_set_object_terms' ), 20, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_post_meta_changed' ), 20, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_post_meta_changed' ), 20, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_post_meta_changed' ), 20, 3 );
	}

	/**
	 * Reindexes (or removes) a post when it is saved.
	 *
	 * Revisions and auto-saves are skipped because they do not represent
	 * canonical published content. Drafts, trashed posts, and other
	 * non-published statuses are removed from the index so only live
	 * content is findable via facet queries.
	 *
	 * @param int      $post_id The post ID that was saved.
	 * @param \WP_Post $post    The post object.
	 * @return void
	 */
	public static function on_save_post( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			self::remove_object( 'post', $post_id );
			return;
		}
		self::reindex_object( 'post', $post_id );
	}

	/**
	 * Removes a post's index rows when the post is force-deleted.
	 *
	 * @param int $post_id The post ID that was deleted.
	 * @return void
	 */
	public static function on_deleted_post( int $post_id ): void {
		self::remove_object( 'post', $post_id );
	}

	/**
	 * Reindexes a post when its taxonomy terms are changed, but only when
	 * the taxonomy is tracked by at least one registered facet.
	 *
	 * @param int    $object_id The post ID whose terms changed.
	 * @param array  $terms     Unused — new term IDs (passed by WP hook).
	 * @param array  $tt_ids    Unused — new term-taxonomy IDs (passed by WP hook).
	 * @param string $taxonomy  The taxonomy slug that was updated.
	 * @return void
	 */
	public static function on_set_object_terms( int $object_id, array $terms, array $tt_ids, string $taxonomy ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		foreach ( FacetRegistry::all() as $config ) {
			if ( 'taxonomy' === ( $config['type'] ?? '' ) && $taxonomy === ( $config['source'] ?? '' ) ) {
				self::reindex_object( 'post', $object_id );
				return;
			}
		}
	}

	/**
	 * Reindexes a post when one of its meta values changes, but only when
	 * the meta key is tracked by at least one registered facet.
	 *
	 * The first parameter ($meta_id) is required by the WordPress hook
	 * signature but is not used here.
	 *
	 * @param mixed  $meta_id   The meta ID (unused; required by hook signature).
	 * @param int    $object_id The post ID whose meta changed.
	 * @param string $meta_key  The meta key that was added/updated/deleted.
	 * @return void
	 */
	public static function on_post_meta_changed( $meta_id, int $object_id, string $meta_key ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		foreach ( FacetRegistry::all() as $config ) {
			if ( 'meta' === ( $config['type'] ?? '' ) && $meta_key === ( $config['source'] ?? '' ) ) {
				self::reindex_object( 'post', $object_id );
				return;
			}
		}
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

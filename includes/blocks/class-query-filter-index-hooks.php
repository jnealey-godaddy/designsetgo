<?php
/**
 * Dynamic Query — Filter Index Hook Layer.
 *
 * Wires FilterIndex read/write primitives into WordPress lifecycle events
 * (save_post, deleted_post, set_object_terms, post meta changes) so the
 * filter index stays fresh automatically.
 *
 * @package DesignSetGo
 * @since 2.2.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and dispatches WordPress hooks that keep the filter index in sync.
 */
class FilterIndexHooks {

	/**
	 * Tracks whether the hook set has been registered in this request.
	 * A single flag (not per-hook has_action checks) so a caller that removes
	 * just one hook — e.g. removing save_post during a bulk import — cannot
	 * trigger accidental duplicate-registration of the other four hooks.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Registers WordPress lifecycle hooks so the index stays current.
	 *
	 * Idempotent — a second call is a no-op.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 2 );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted_post' ), 20, 1 );
		add_action( 'set_object_terms', array( __CLASS__, 'on_set_object_terms' ), 20, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_post_meta_changed' ), 20, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_post_meta_changed' ), 20, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_post_meta_changed' ), 20, 3 );

		// Queue a background backfill the first time each filter key is seen.
		// Runs out-of-band via WP-Cron so the post-save request that triggered
		// registration isn't blocked iterating the posts table.
		add_action( 'designsetgo_query_filter_registered', array( __CLASS__, 'on_filter_registered' ) );
		add_action( 'designsetgo_query_filter_backfill', array( __CLASS__, 'on_filter_backfill' ) );
	}

	/**
	 * Handles a first-time filter registration by scheduling a single-event
	 * backfill. Deduplicates via `wp_next_scheduled` so rapid repeat saves of
	 * a post containing the same filter don't pile up cron jobs.
	 *
	 * @param string $filter_key The freshly-registered filter key.
	 * @return void
	 */
	public static function on_filter_registered( string $filter_key ): void {
		$filter_key = sanitize_key( $filter_key );
		if ( '' === $filter_key ) {
			return;
		}
		$args = array( $filter_key );
		if ( false !== wp_next_scheduled( 'designsetgo_query_filter_backfill', $args ) ) {
			return;
		}
		wp_schedule_single_event( time() + 5, 'designsetgo_query_filter_backfill', $args );
	}

	/**
	 * Cron handler — runs the actual index rebuild for the given filter key.
	 *
	 * @param string $filter_key The filter key to rebuild.
	 * @return void
	 */
	public static function on_filter_backfill( string $filter_key ): void {
		if ( class_exists( FilterIndexRebuilder::class ) ) {
			FilterIndexRebuilder::rebuild_filter( sanitize_key( $filter_key ) );
		}
	}

	/**
	 * Reindexes (or removes) a post when it is saved, and registers any
	 * query-filter entries found in the post's block content.
	 *
	 * Revisions and auto-saves are skipped because they do not represent
	 * canonical published content. Drafts, trashed posts, and other
	 * non-published statuses are removed from the index so only live
	 * content is findable via filter queries.
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
			FilterIndex::remove_object( 'post', $post_id );
			return;
		}
		FilterIndex::reindex_object( 'post', $post_id );
		self::register_filters_from_post_blocks( $post );
	}

	/**
	 * Parses a published post's block content and registers any
	 * designsetgo/query-filter blocks' filters into the FilterRegistry.
	 *
	 * Moving registration to save time (rather than the editor) ensures that
	 * only filters belonging to actually-published posts end up in the registry.
	 * Abandoned drafts no longer pollute the registry.
	 *
	 * @param \WP_Post $post The published post whose content to scan.
	 * @return void
	 */
	private static function register_filters_from_post_blocks( \WP_Post $post ): void {
		if ( ! function_exists( 'parse_blocks' ) ) {
			return;
		}
		$blocks = parse_blocks( $post->post_content );
		self::walk_blocks_for_filters( $blocks );
	}

	/**
	 * Recursively walks a block tree and registers taxonomy filters for any
	 * designsetgo/query-filter blocks found.
	 *
	 * @param array $blocks Parsed block array from parse_blocks().
	 * @return void
	 */
	private static function walk_blocks_for_filters( array $blocks ): void {
		foreach ( $blocks as $block ) {
			if ( 'designsetgo/query-filter' === ( $block['blockName'] ?? '' ) ) {
				$attrs    = $block['attrs'] ?? array();
				$taxonomy = sanitize_key( (string) ( $attrs['taxonomy'] ?? '' ) );
				if ( '' !== $taxonomy ) {
					FilterRegistry::register(
						$taxonomy,
						array(
							'type'   => 'taxonomy',
							'source' => $taxonomy,
						)
					);
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk_blocks_for_filters( $block['innerBlocks'] );
			}
		}
	}

	/**
	 * Removes a post's index rows when the post is force-deleted.
	 *
	 * @param int $post_id The post ID that was deleted.
	 * @return void
	 */
	public static function on_deleted_post( int $post_id ): void {
		FilterIndex::remove_object( 'post', $post_id );
	}

	/**
	 * Reindexes a post when its taxonomy terms are changed, but only when
	 * the taxonomy is tracked by at least one registered filter.
	 *
	 * Set_object_terms fires for any taxonomy-capable object; we only index
	 * posts in v2.2, so we short-circuit when the object ID does not resolve
	 * to a real post.
	 *
	 * @param int    $object_id The object ID whose terms changed.
	 * @param array  $terms     Unused — new term IDs (passed by WP hook).
	 * @param array  $tt_ids    Unused — new term-taxonomy IDs (passed by WP hook).
	 * @param string $taxonomy  The taxonomy slug that was updated.
	 * @return void
	 */
	public static function on_set_object_terms( int $object_id, array $terms, array $tt_ids, string $taxonomy ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		// set_object_terms fires for any taxonomy-capable object; we only index posts in v2.2.
		if ( ! get_post( $object_id ) ) {
			return;
		}
		foreach ( FilterRegistry::all() as $config ) {
			if ( 'taxonomy' === ( $config['type'] ?? '' ) && ( $config['source'] ?? '' ) === $taxonomy ) {
				FilterIndex::reindex_object( 'post', $object_id );
				return;
			}
		}
	}

	/**
	 * Reindexes a post when one of its meta values changes, but only when
	 * the meta key is tracked by at least one registered filter.
	 *
	 * @param int|string $meta_id   Meta row ID (unused; required by hook signature).
	 * @param int        $object_id Post ID whose meta changed.
	 * @param string     $meta_key  The changed meta key.
	 * @return void
	 */
	public static function on_post_meta_changed( $meta_id, int $object_id, string $meta_key ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		foreach ( FilterRegistry::all() as $config ) {
			if ( 'meta' === ( $config['type'] ?? '' ) && ( $config['source'] ?? '' ) === $meta_key ) {
				FilterIndex::reindex_object( 'post', $object_id );
				return;
			}
		}
	}
}

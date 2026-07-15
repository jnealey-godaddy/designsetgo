<?php
/**
 * Draft Mode Class
 *
 * Provides "draft mode" functionality for published pages, allowing users
 * to work on changes without affecting the live site until ready to publish.
 *
 * @package DesignSetGo
 * @since 1.4.0
 */

namespace DesignSetGo\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Draft Mode Class - Manages draft versions of published pages
 */
class Draft_Mode {
	/**
	 * Meta key for storing original post ID on draft.
	 */
	const META_DRAFT_OF = '_dsgo_draft_of';

	/**
	 * Meta key for storing draft post ID on original.
	 */
	const META_HAS_DRAFT = '_dsgo_has_draft';

	/**
	 * Meta key for storing draft creation timestamp.
	 */
	const META_DRAFT_CREATED = '_dsgo_draft_created';

	/**
	 * Default maximum nesting depth scanned by meta_rejection_reason().
	 *
	 * Anything deeper is rejected (fail closed), capping recursion so a
	 * maliciously deep payload cannot overflow the stack. Raise it for a
	 * specific site with the designsetgo_draft_max_object_scan_depth filter.
	 */
	const MAX_OBJECT_SCAN_DEPTH = 20;

	/**
	 * Hard ceiling on the scan depth, whatever the filter asks for.
	 *
	 * The depth cap's whole purpose is to bound recursion, so the filter that
	 * relaxes it must not be able to remove that bound entirely. This sits far
	 * above any plausible real structure and far below a stack overflow, so the
	 * guarantee holds no matter what an integrator passes.
	 */
	const ABSOLUTE_MAX_OBJECT_SCAN_DEPTH = 200;

	/**
	 * Rejection reason: the value is, or contains, a PHP object.
	 *
	 * A hostile payload. Never copied anywhere.
	 */
	const REJECT_OBJECT = 'object';

	/**
	 * Rejection reason: the value nests deeper than the resolved scan cap.
	 *
	 * The cap defaults to MAX_OBJECT_SCAN_DEPTH but is filterable, so the bound
	 * that actually applies is whatever get_max_object_scan_depth() resolves.
	 * Unlike REJECT_OBJECT this says nothing about whether the value is
	 * hostile — only that it is too deep to vet within that cap. It may well be
	 * legitimate data (deeply nested page-builder or ACF configuration), so such
	 * a key is left untouched rather than deleted.
	 */
	const REJECT_DEPTH = 'depth';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize REST API handler (hooks registered in constructor).
		new Draft_Mode_REST( $this );

		// Initialize admin UI handler (hooks registered in constructor).
		new Draft_Mode_Admin( $this );

		// Initialize frontend preview mode (serves draft content to logged-in admins).
		new Draft_Mode_Preview( $this );

		// Always clean up meta when posts are deleted.
		add_action( 'before_delete_post', array( $this, 'cleanup_draft_meta' ) );
	}

	/**
	 * Get draft mode settings
	 *
	 * @return array Draft mode settings.
	 */
	public function get_settings() {
		$defaults = array(
			'enable'                 => true,
			'show_page_list_actions' => true,
			'show_page_list_column'  => true,
			'show_frontend_preview'  => true,
			'auto_save_enabled'      => true,
			'auto_save_interval'     => 60,
		);

		$all_settings = Settings::get_settings();
		$stored       = isset( $all_settings['draft_mode'] ) ? $all_settings['draft_mode'] : array();

		return wp_parse_args( $stored, $defaults );
	}

	/**
	 * Check if draft mode is enabled
	 *
	 * @return bool True if enabled.
	 */
	public function is_enabled() {
		$settings = $this->get_settings();
		return ! empty( $settings['enable'] );
	}

	/**
	 * Create a draft copy of a published page
	 *
	 * @param int   $post_id   The published post ID.
	 * @param array $overrides Optional content overrides (title, content, excerpt).
	 * @return int|\WP_Error Draft post ID on success, WP_Error on failure.
	 */
	public function create_draft( $post_id, $overrides = array() ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error(
				'invalid_post',
				__( 'Post not found.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		if ( 'page' !== $post->post_type ) {
			return new \WP_Error(
				'invalid_post_type',
				__( 'Draft mode is only available for pages.', 'designsetgo' ),
				array( 'status' => 400 )
			);
		}

		if ( 'publish' !== $post->post_status ) {
			return new \WP_Error(
				'invalid_status',
				__( 'Only published pages can have a draft version.', 'designsetgo' ),
				array( 'status' => 400 )
			);
		}

		if ( $this->has_draft( $post_id ) ) {
			$existing_draft_id = get_post_meta( $post_id, self::META_HAS_DRAFT, true );
			return new \WP_Error(
				'draft_exists',
				__( 'A draft version already exists for this page.', 'designsetgo' ),
				array(
					'status'   => 400,
					'draft_id' => $existing_draft_id,
					'edit_url' => get_edit_post_link( $existing_draft_id, 'raw' ),
				)
			);
		}

		$draft_data = array(
			'post_title'   => isset( $overrides['title'] ) ? $overrides['title'] : $post->post_title,
			'post_content' => isset( $overrides['content'] ) ? $overrides['content'] : $post->post_content,
			'post_excerpt' => isset( $overrides['excerpt'] ) ? $overrides['excerpt'] : $post->post_excerpt,
			'post_status'  => 'draft',
			'post_type'    => 'page',
			'post_author'  => get_current_user_id(),
			'post_parent'  => $post_id,
			'menu_order'   => $post->menu_order,
		);

		// wp_slash() is required because wp_insert_post() expects slashed data.
		$draft_id = wp_insert_post( wp_slash( $draft_data ), true );

		if ( is_wp_error( $draft_id ) ) {
			return $draft_id;
		}

		$this->copy_post_meta( $post_id, $draft_id );

		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			set_post_thumbnail( $draft_id, $thumbnail_id );
		}

		update_post_meta( $draft_id, self::META_DRAFT_OF, $post_id );
		update_post_meta( $draft_id, self::META_DRAFT_CREATED, current_time( 'mysql' ) );
		update_post_meta( $post_id, self::META_HAS_DRAFT, $draft_id );

		/**
		 * Fires after a draft is created.
		 *
		 * @param int $draft_id    The new draft post ID.
		 * @param int $original_id The original published post ID.
		 */
		do_action( 'designsetgo_draft_created', $draft_id, $post_id );

		return $draft_id;
	}

	/**
	 * Publish (merge) a draft into its original post
	 *
	 * @param int $draft_id The draft post ID.
	 * @return int|\WP_Error Original post ID on success, WP_Error on failure.
	 */
	public function publish_draft( $draft_id ) {
		$draft = get_post( $draft_id );

		if ( ! $draft ) {
			return new \WP_Error(
				'invalid_draft',
				__( 'Draft not found.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		$original_id = get_post_meta( $draft_id, self::META_DRAFT_OF, true );

		if ( ! $original_id ) {
			return new \WP_Error(
				'not_a_draft',
				__( 'This post is not a draft version.', 'designsetgo' ),
				array( 'status' => 400 )
			);
		}

		$original = get_post( $original_id );

		if ( ! $original ) {
			return new \WP_Error(
				'original_not_found',
				__( 'The original page no longer exists.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		// Step 1: Update the original post content.
		// wp_slash() is required because wp_update_post() expects slashed data
		// and internally calls wp_unslash(). Without it, backslashes in block
		// content (e.g. JSON attributes in block comments) are stripped, which
		// corrupts entities like & and breaks block parsing.
		$update_data = array(
			'ID'           => $original_id,
			'post_title'   => $draft->post_title,
			'post_content' => $draft->post_content,
			'post_excerpt' => $draft->post_excerpt,
		);

		$result = wp_update_post( wp_slash( $update_data ), true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Step 2: Sync post meta (this operation doesn't return errors).
		$this->sync_post_meta( $draft_id, $original_id );

		// Step 3: Update featured image.
		$draft_thumbnail = get_post_thumbnail_id( $draft_id );
		if ( $draft_thumbnail ) {
			set_post_thumbnail( $original_id, $draft_thumbnail );
		} else {
			delete_post_thumbnail( $original_id );
		}

		// Step 4: Clean up relationship meta BEFORE deletion.
		// This ensures the relationship is removed even if deletion fails.
		delete_post_meta( $original_id, self::META_HAS_DRAFT );
		delete_post_meta( $draft_id, self::META_DRAFT_OF );

		/**
		 * Fires after a draft is published (merged into original).
		 *
		 * @param int $original_id The original published post ID.
		 * @param int $draft_id    The draft post ID (about to be deleted).
		 */
		do_action( 'designsetgo_draft_published', $original_id, $draft_id );

		// Step 5: Delete the draft post.
		// If this fails, at least the relationship meta has been cleaned up.
		wp_delete_post( $draft_id, true );

		return $original_id;
	}

	/**
	 * Discard a draft without publishing
	 *
	 * @param int $draft_id The draft post ID.
	 * @return int|\WP_Error Original post ID on success, WP_Error on failure.
	 */
	public function discard_draft( $draft_id ) {
		$draft = get_post( $draft_id );

		if ( ! $draft ) {
			return new \WP_Error(
				'invalid_draft',
				__( 'Draft not found.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		$original_id = get_post_meta( $draft_id, self::META_DRAFT_OF, true );

		if ( ! $original_id ) {
			return new \WP_Error(
				'not_a_draft',
				__( 'This post is not a draft version.', 'designsetgo' ),
				array( 'status' => 400 )
			);
		}

		delete_post_meta( $original_id, self::META_HAS_DRAFT );

		/**
		 * Fires after a draft is discarded.
		 *
		 * @param int $draft_id    The draft post ID (about to be deleted).
		 * @param int $original_id The original published post ID.
		 */
		do_action( 'designsetgo_draft_discarded', $draft_id, $original_id );

		wp_delete_post( $draft_id, true );

		return $original_id;
	}

	/**
	 * Get the draft for a published post
	 *
	 * @param int $post_id The published post ID.
	 * @return \WP_Post|null Draft post or null.
	 */
	public function get_draft( $post_id ) {
		$draft_id = get_post_meta( $post_id, self::META_HAS_DRAFT, true );

		if ( ! $draft_id ) {
			return null;
		}

		$draft = get_post( $draft_id );

		if ( ! $draft || 'draft' !== $draft->post_status ) {
			delete_post_meta( $post_id, self::META_HAS_DRAFT );
			return null;
		}

		return $draft;
	}

	/**
	 * Check if a post has an active draft
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if draft exists.
	 */
	public function has_draft( $post_id ) {
		return null !== $this->get_draft( $post_id );
	}

	/**
	 * Copy post meta from one post to another
	 *
	 * @param int $source_id Source post ID.
	 * @param int $target_id Target post ID.
	 */
	private function copy_post_meta( $source_id, $target_id ) {
		$meta = get_post_meta( $source_id );

		// Resolve both filters once for the whole copy, not per key or per value.
		$excluded_keys = self::get_excluded_meta_keys( $source_id );
		$max_depth     = self::get_max_object_scan_depth();

		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, $excluded_keys, true ) ) {
				continue;
			}

			foreach ( $values as $value ) {
				$unserialized = maybe_unserialize( $value );
				// Reject PHP objects (including objects nested inside arrays)
				// to prevent object injection, and anything too deep to vet.
				$reason = self::meta_rejection_reason( $unserialized, 0, $max_depth );

				if ( '' !== $reason ) {
					self::log_skipped_meta( $key, $reason, $source_id, 'the draft will not carry this key', $max_depth );
					continue;
				}

				add_post_meta( $target_id, $key, $unserialized );
			}
		}
	}

	/**
	 * Sync post meta from draft to original (for publishing)
	 *
	 * @param int $draft_id    Draft post ID.
	 * @param int $original_id Original post ID.
	 */
	private function sync_post_meta( $draft_id, $original_id ) {
		$preserve_keys = array(
			'_edit_lock',
			'_edit_last',
			self::META_DRAFT_OF,
			self::META_HAS_DRAFT,
			self::META_DRAFT_CREATED,
			'_wp_old_slug',
			'_wp_old_date',
			'_wp_page_template',
		);

		/**
		 * Filter the meta keys to preserve on the original when publishing.
		 *
		 * @param array $preserve_keys Keys to preserve (not overwrite).
		 * @param int   $original_id   Original post ID.
		 */
		$preserve_keys = apply_filters( 'designsetgo_draft_preserve_meta_keys', $preserve_keys, $original_id );

		$draft_meta    = get_post_meta( $draft_id );
		$original_meta = get_post_meta( $original_id );

		// Resolve both filters once for the whole publish, not per key or per value.
		$excluded_keys = self::get_excluded_meta_keys( $original_id );
		$max_depth     = self::get_max_object_scan_depth();

		foreach ( array_keys( $original_meta ) as $key ) {
			if ( in_array( $key, $preserve_keys, true ) ) {
				continue;
			}

			if ( isset( $draft_meta[ $key ] ) ) {
				continue;
			}

			// The key is on the original but not on the draft. Normally that
			// means the author removed it, and the deletion should be synced.
			// But it may never have been copied in the first place — either
			// because an integrator excluded it, or because the scanner refused
			// it — in which case the author never saw the key, let alone removed
			// it, and deleting here would destroy meta they never touched. Only
			// sync the deletion when the key really was copyable.
			if ( in_array( $key, $excluded_keys, true ) ) {
				continue;
			}

			$reason = self::values_rejection_reason( $original_meta[ $key ], $max_depth );

			if ( '' !== $reason ) {
				self::log_skipped_meta( $key, $reason, $original_id, 'it was left untouched on the original rather than deleted', $max_depth );
				continue;
			}

			delete_post_meta( $original_id, $key );
		}

		foreach ( $draft_meta as $key => $values ) {
			if ( in_array( $key, $preserve_keys, true ) ) {
				continue;
			}

			// Vet every value for this key before touching the original.
			// delete_post_meta() below is destructive, so a key with even one
			// unwritable value must be left alone entirely — deleting it and
			// then declining to write the replacement back would wipe meta the
			// original already had. Reject PHP objects (including objects
			// nested inside arrays) to prevent object injection, and anything
			// too deep to vet.
			$reason = self::values_rejection_reason( $values, $max_depth );

			if ( '' !== $reason ) {
				self::log_skipped_meta( $key, $reason, $draft_id, 'the original keeps its existing value', $max_depth );
				continue;
			}

			delete_post_meta( $original_id, $key );

			foreach ( $values as $value ) {
				add_post_meta( $original_id, $key, maybe_unserialize( $value ) );
			}
		}
	}

	/**
	 * Clean up draft meta when a post is deleted
	 *
	 * @param int $post_id Post ID being deleted.
	 */
	public function cleanup_draft_meta( $post_id ) {
		// If this post is a draft, clean up the original's meta.
		$original_id = get_post_meta( $post_id, self::META_DRAFT_OF, true );
		if ( $original_id ) {
			delete_post_meta( $original_id, self::META_HAS_DRAFT );
		}

		// If this post has a draft, validate and delete it.
		$draft_id = get_post_meta( $post_id, self::META_HAS_DRAFT, true );
		if ( $draft_id ) {
			// Security: Validate the draft relationship before deleting.
			$draft_post = get_post( $draft_id );

			// Only delete if:
			// 1. The draft post exists.
			// 2. The draft post is actually a draft status.
			// 3. The draft's META_DRAFT_OF meta points back to this post.
			if ( $draft_post && 'draft' === $draft_post->post_status ) {
				$draft_original_id = get_post_meta( $draft_id, self::META_DRAFT_OF, true );

				if ( (int) $draft_original_id === (int) $post_id ) {
					delete_post_meta( $draft_id, self::META_DRAFT_OF );
					wp_delete_post( $draft_id, true );
				}
			}
		}
	}

	/**
	 * Meta keys that are never copied from a post to its draft.
	 *
	 * Shared by copy_post_meta() and sync_post_meta(): the copy step uses it to
	 * decide what to leave behind, and the publish step uses it to recognise
	 * that such a key's absence from the draft means "never copied" rather than
	 * "the author deleted it". Reading it from one place is what keeps those two
	 * answers in agreement.
	 *
	 * @param int $post_id Post the meta is read from.
	 * @return array Meta keys to exclude from copying.
	 */
	private static function get_excluded_meta_keys( $post_id ) {
		$excluded_keys = array(
			'_edit_lock',
			'_edit_last',
			self::META_DRAFT_OF,
			self::META_HAS_DRAFT,
			self::META_DRAFT_CREATED,
			'_wp_old_slug',
			'_wp_old_date',
		);

		/**
		 * Filter the meta keys to copy when creating a draft.
		 *
		 * The callback must be a pure function of $post_id: it has to return the
		 * same list for a given post when the draft is created and again when it
		 * is published. copy_post_meta() consults it to decide what to leave off
		 * the draft, and sync_post_meta() consults it again on publish to tell an
		 * excluded key's absence from the draft ("never copied") apart from a key
		 * the author deleted. A callback that varies with mutable state (an
		 * option, a transient, the clock) can excise a key at copy time and admit
		 * it at publish time, at which point the deletion sync destroys it — the
		 * same meta loss this exclusion is meant to prevent.
		 *
		 * @param array $excluded_keys Keys to exclude from copying.
		 * @param int   $post_id       Source post ID.
		 */
		return apply_filters( 'designsetgo_draft_excluded_meta_keys', $excluded_keys, $post_id );
	}

	/**
	 * Maximum nesting depth the object scanner will descend.
	 *
	 * Filterable so a site with legitimately deep but object-free data (some
	 * ACF repeater / flexible-content and page-builder configurations nest well
	 * past the default) can opt into copying it, rather than having it withheld
	 * from every draft with no recourse.
	 *
	 * The result is clamped to ABSOLUTE_MAX_OBJECT_SCAN_DEPTH. The cap exists to
	 * bound recursion, and a filter must not be able to hand that guarantee back
	 * to an attacker — so raising it is allowed, but only within a range that is
	 * still nowhere near a stack overflow.
	 *
	 * @return int Depth to scan, at least 1 and at most ABSOLUTE_MAX_OBJECT_SCAN_DEPTH.
	 */
	private static function get_max_object_scan_depth() {
		/**
		 * Filter the maximum nesting depth scanned when vetting meta for objects.
		 *
		 * Anything deeper is rejected (fail closed) and withheld from the draft.
		 * Values are clamped to the range 1..ABSOLUTE_MAX_OBJECT_SCAN_DEPTH.
		 *
		 * Like designsetgo_draft_excluded_meta_keys, this must be stable across a
		 * draft's create->publish lifetime: a key withheld as too deep at copy
		 * time is recognised as "never copied" on publish by re-vetting it at the
		 * current cap. Raising the cap between the two calls (for example a deploy
		 * that changes the value while a draft is open) makes that key vet as
		 * copyable on publish, and the deletion sync then destroys it. Prefer a
		 * constant over reading mutable state here.
		 *
		 * @param int $depth Default self::MAX_OBJECT_SCAN_DEPTH.
		 */
		$depth = (int) apply_filters( 'designsetgo_draft_max_object_scan_depth', self::MAX_OBJECT_SCAN_DEPTH );

		return max( 1, min( $depth, self::ABSOLUTE_MAX_OBJECT_SCAN_DEPTH ) );
	}

	/**
	 * Reason a meta value must not be copied, or '' when it is safe to copy.
	 *
	 * A plain is_object() check misses objects nested inside arrays (e.g. a
	 * serialized payload like `a:1:{i:0;O:8:"stdClass":0:{}}`), which would
	 * still trigger object injection when stored and later unserialized.
	 *
	 * Recursion is bounded by the resolved depth cap ($max_depth, which defaults
	 * to self::MAX_OBJECT_SCAN_DEPTH but is filterable). A structure deeper than
	 * that is rejected (fail closed), eliminating any stack-overflow risk from a
	 * maliciously deep payload. The two rejection reasons are reported
	 * separately because they mean different things: REJECT_OBJECT is a hostile
	 * payload, while REJECT_DEPTH may be perfectly legitimate data that is
	 * simply too deep to vet, and so must be preserved rather than destroyed.
	 *
	 * @param mixed    $value     Value to inspect (already passed through maybe_unserialize()).
	 * @param int      $depth     Current recursion depth (internal).
	 * @param int|null $max_depth Resolved depth cap; looked up once on entry and
	 *                            passed down, so the filter runs once per scan
	 *                            rather than once per nested element.
	 * @return string self::REJECT_OBJECT, self::REJECT_DEPTH, or '' when safe.
	 */
	private static function meta_rejection_reason( $value, $depth = 0, $max_depth = null ) {
		if ( null === $max_depth ) {
			$max_depth = self::get_max_object_scan_depth();
		}

		if ( is_object( $value ) ) {
			return self::REJECT_OBJECT;
		}

		if ( is_array( $value ) ) {
			if ( $depth >= $max_depth ) {
				return self::REJECT_DEPTH;
			}

			foreach ( $value as $item ) {
				$reason = self::meta_rejection_reason( $item, $depth + 1, $max_depth );

				if ( '' !== $reason ) {
					return $reason;
				}
			}
		}

		return '';
	}

	/**
	 * Reason none of a meta key's stored values may be copied, or '' when safe.
	 *
	 * @param array    $values    Raw values as returned by get_post_meta( $id ) (still serialized).
	 * @param int|null $max_depth Resolved depth cap. Pass the value already
	 *                            resolved for the surrounding loop so the filter
	 *                            is not re-run per key; null resolves it once here.
	 * @return string self::REJECT_OBJECT, self::REJECT_DEPTH, or '' when safe.
	 */
	private static function values_rejection_reason( $values, $max_depth = null ) {
		if ( null === $max_depth ) {
			$max_depth = self::get_max_object_scan_depth();
		}

		foreach ( (array) $values as $value ) {
			$reason = self::meta_rejection_reason( maybe_unserialize( $value ), 0, $max_depth );

			if ( '' !== $reason ) {
				return $reason;
			}
		}

		return '';
	}

	/**
	 * Record a meta key that the object scanner refused to copy.
	 *
	 * Skipping is deliberate, but otherwise invisible: the symptom an author
	 * reports is only ever "some meta didn't carry over." Fire an action so the
	 * decision is observable, and under WP_DEBUG emit a notice naming the key
	 * and the reason. The value itself is never logged — it may hold a secret.
	 *
	 * @param string   $key       Meta key that was skipped.
	 * @param string   $reason    self::REJECT_OBJECT or self::REJECT_DEPTH.
	 * @param int      $post_id   Post the meta was read from.
	 * @param string   $outcome   Human-readable description of what was done instead.
	 * @param int|null $max_depth Resolved depth cap for the message; null resolves it once here.
	 */
	private static function log_skipped_meta( $key, $reason, $post_id, $outcome, $max_depth = null ) {
		/**
		 * Fires when the object scanner refuses to copy a meta key.
		 *
		 * @param string $key     Meta key that was skipped.
		 * @param string $reason  'object' (PHP object rejected) or 'depth'
		 *                        (nests deeper than the resolved scan cap).
		 * @param int    $post_id Post the meta was read from.
		 * @param string $outcome What happened to the key instead.
		 */
		do_action( 'designsetgo_draft_meta_skipped', $key, $reason, $post_id, $outcome );

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		if ( null === $max_depth ) {
			$max_depth = self::get_max_object_scan_depth();
		}

		$because = self::REJECT_DEPTH === $reason
			? sprintf(
				'it nests deeper than the %d-level scan cap (raise it with the designsetgo_draft_max_object_scan_depth filter)',
				$max_depth
			)
			: 'it contains a PHP object';

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only diagnostic; the alternative is a silent skip that is impossible to support.
			sprintf(
				'DesignSetGo: meta key "%1$s" on post %2$d was not copied because %3$s; %4$s.',
				$key,
				(int) $post_id,
				$because,
				$outcome
			)
		);
	}
}

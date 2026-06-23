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
		 * @param array $excluded_keys Keys to exclude from copying.
		 * @param int   $source_id     Source post ID.
		 */
		$excluded_keys = apply_filters( 'designsetgo_draft_excluded_meta_keys', $excluded_keys, $source_id );

		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, $excluded_keys, true ) ) {
				continue;
			}

			foreach ( $values as $value ) {
				$unserialized = maybe_unserialize( $value );
				// Reject PHP objects (including objects nested inside arrays)
				// to prevent object injection.
				if ( self::contains_object( $unserialized ) ) {
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

		foreach ( array_keys( $original_meta ) as $key ) {
			if ( in_array( $key, $preserve_keys, true ) ) {
				continue;
			}
			if ( ! isset( $draft_meta[ $key ] ) ) {
				delete_post_meta( $original_id, $key );
			}
		}

		foreach ( $draft_meta as $key => $values ) {
			if ( in_array( $key, $preserve_keys, true ) ) {
				continue;
			}

			delete_post_meta( $original_id, $key );

			foreach ( $values as $value ) {
				$unserialized = maybe_unserialize( $value );
				// Reject PHP objects (including objects nested inside arrays)
				// to prevent object injection.
				if ( self::contains_object( $unserialized ) ) {
					continue;
				}
				add_post_meta( $original_id, $key, $unserialized );
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
	 * Recursively determine whether a value is, or contains, a PHP object.
	 *
	 * A plain is_object() check misses objects nested inside arrays (e.g. a
	 * serialized payload like `a:1:{i:0;O:8:"stdClass":0:{}}`), which would
	 * still trigger object injection when stored and later unserialized.
	 *
	 * @param mixed $value Value to inspect (already passed through maybe_unserialize()).
	 * @return bool True if the value is an object or any nested array element is.
	 */
	private static function contains_object( $value ) {
		if ( is_object( $value ) ) {
			return true;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::contains_object( $item ) ) {
					return true;
				}
			}
		}

		return false;
	}
}

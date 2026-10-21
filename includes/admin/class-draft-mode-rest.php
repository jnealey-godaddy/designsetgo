<?php
/**
 * Draft Mode REST API Class
 *
 * Handles REST API endpoints for draft mode operations.
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
 * Draft Mode REST API Class
 */
class Draft_Mode_REST {
	/**
	 * Draft Mode instance.
	 *
	 * @var Draft_Mode
	 */
	private $draft_mode;

	/**
	 * Constructor
	 *
	 * @param Draft_Mode $draft_mode Draft Mode instance.
	 */
	public function __construct( Draft_Mode $draft_mode ) {
		$this->draft_mode = $draft_mode;
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		// Create draft of a published page.
		register_rest_route(
			'designsetgo/v1',
			'/draft-mode/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_draft_endpoint' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'The ID of the published page to create a draft of.', 'designsetgo' ),
					),
					'content' => array(
						'required'    => false,
						'type'        => 'string',
						// No sanitize_callback - wp_kses breaks block content (strips CSS, corrupts unicode).
						'description' => __( 'Optional content to use instead of published content (captures unsaved edits).', 'designsetgo' ),
					),
					'title'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Optional title to use instead of published title.', 'designsetgo' ),
					),
					'excerpt' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
						'description'       => __( 'Optional excerpt to use instead of published excerpt.', 'designsetgo' ),
					),
				),
			)
		);

		// Publish (merge) a draft into original.
		register_rest_route(
			'designsetgo/v1',
			'/draft-mode/(?P<id>\d+)/publish',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'publish_draft_endpoint' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'The draft post ID.', 'designsetgo' ),
					),
					'force' => array(
						'required'    => false,
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Publish even though the live page changed after the draft was created.', 'designsetgo' ),
					),
				),
			)
		);

		// Discard a draft.
		register_rest_route(
			'designsetgo/v1',
			'/draft-mode/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'discard_draft_endpoint' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'The draft post ID.', 'designsetgo' ),
					),
				),
			)
		);

		// Get draft status for a post.
		register_rest_route(
			'designsetgo/v1',
			'/draft-mode/status/(?P<post_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status_endpoint' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'The post ID to check status for.', 'designsetgo' ),
					),
				),
			)
		);
	}

	/**
	 * Check permission for draft operations
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error True if user has permission, WP_Error otherwise.
	 */
	public function check_permission( $request ) {
		if ( ! current_user_can( 'publish_pages' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage drafts.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'invalid_nonce',
				__( 'Invalid security token.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check read permission
	 *
	 * @param \WP_REST_Request $_request Request object (unused).
	 * @return bool True if user has permission, false otherwise.
	 */
	public function check_read_permission( $_request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- REST API callback signature
		return current_user_can( 'edit_pages' );
	}

	/**
	 * Create draft endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_draft_endpoint( $request ) {
		if ( ! $this->draft_mode->is_enabled() ) {
			return new \WP_Error(
				'draft_mode_disabled',
				__( 'Draft mode is disabled.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$post_id = $request->get_param( 'post_id' );

		// Check object-level permission for the specific post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to edit this page.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$overrides = array_filter(
			array(
				'content' => $request->get_param( 'content' ),
				'title'   => $request->get_param( 'title' ),
				'excerpt' => $request->get_param( 'excerpt' ),
			),
			function ( $v ) {
				return null !== $v;
			}
		);

		// Content is deliberately NOT run through a custom sanitizer. create_draft()
		// stores it with wp_insert_post(), so it gets exactly the treatment a normal
		// save gets: users with unfiltered_html are trusted, everyone else goes
		// through core's post kses (kses_init_filters). A stricter private
		// allowlist only ever stripped attributes the blocks' save() emits
		// (aria-modal, aria-valuenow, ...), leaving the draft with invalid blocks.

		$result = $this->draft_mode->create_draft( $post_id, $overrides );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$draft_post = get_post( $result );

		return rest_ensure_response(
			array(
				'success'     => true,
				'draft_id'    => $result,
				'edit_url'    => get_edit_post_link( $result, 'raw' ),
				'message'     => __( 'Draft created successfully.', 'designsetgo' ),
				'draft_title' => $draft_post->post_title,
				'original_id' => $post_id,
			)
		);
	}

	/**
	 * Publish draft endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function publish_draft_endpoint( $request ) {
		$draft_id = $request->get_param( 'id' );

		// Check object-level permissions for the draft and original posts.
		if ( ! current_user_can( 'delete_post', $draft_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage this draft.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$original_id = get_post_meta( $draft_id, Draft_Mode::META_DRAFT_OF, true );
		if ( $original_id && ! current_user_can( 'publish_post', $original_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to publish changes to this page.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$result = $this->draft_mode->publish_draft( $draft_id, (bool) $request->get_param( 'force' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'original_id' => $result,
				'edit_url'    => get_edit_post_link( $result, 'raw' ),
				'view_url'    => get_permalink( $result ),
				'message'     => __( 'Changes published successfully.', 'designsetgo' ),
			)
		);
	}

	/**
	 * Discard draft endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function discard_draft_endpoint( $request ) {
		$draft_id = $request->get_param( 'id' );

		// Check object-level permission for the specific draft.
		if ( ! current_user_can( 'delete_post', $draft_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to delete this draft.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$result = $this->draft_mode->discard_draft( $draft_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'original_id' => $result,
				'message'     => __( 'Draft discarded.', 'designsetgo' ),
			)
		);
	}

	/**
	 * Get status endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_status_endpoint( $request ) {
		$post_id = $request->get_param( 'post_id' );
		$post    = get_post( $post_id );

		// Check object-level permission for the specific post.
		if ( $post && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view this page.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$base_response = array(
			'settings' => array(
				'enabled' => $this->draft_mode->is_enabled(),
			),
		);

		if ( ! $post ) {
			return rest_ensure_response(
				array_merge(
					$base_response,
					array(
						'exists'      => false,
						'is_draft'    => false,
						'has_draft'   => false,
						'draft_id'    => null,
						'original_id' => null,
					)
				)
			);
		}

		// Check if this post is a draft of another.
		$original_id = get_post_meta( $post_id, Draft_Mode::META_DRAFT_OF, true );

		if ( $original_id ) {
			$original = get_post( $original_id );
			return rest_ensure_response(
				array_merge(
					$base_response,
					array(
						'exists'            => true,
						'is_draft'          => true,
						'has_draft'         => false,
						'draft_id'          => $post_id,
						'original_id'       => (int) $original_id,
						'original_title'    => $original ? $original->post_title : '',
						'original_edit_url' => $original ? get_edit_post_link( $original_id, 'raw' ) : '',
						'original_view_url' => $original ? get_permalink( $original_id ) : '',
						'draft_created'     => get_post_meta( $post_id, Draft_Mode::META_DRAFT_CREATED, true ),
					)
				)
			);
		}

		// Check if this post has a draft.
		$draft_id = get_post_meta( $post_id, Draft_Mode::META_HAS_DRAFT, true );

		if ( $draft_id ) {
			$draft = get_post( $draft_id );
			if ( $draft && 'draft' === $draft->post_status ) {
				return rest_ensure_response(
					array_merge(
						$base_response,
						array(
							'exists'         => true,
							'is_draft'       => false,
							'has_draft'      => true,
							'draft_id'       => (int) $draft_id,
							'draft_edit_url' => get_edit_post_link( $draft_id, 'raw' ),
							'original_id'    => $post_id,
							'draft_created'  => get_post_meta( $draft_id, Draft_Mode::META_DRAFT_CREATED, true ),
						)
					)
				);
			} else {
				delete_post_meta( $post_id, Draft_Mode::META_HAS_DRAFT );
			}
		}

		return rest_ensure_response(
			array_merge(
				$base_response,
				array(
					'exists'      => true,
					'is_draft'    => false,
					'has_draft'   => false,
					'draft_id'    => null,
					'original_id' => null,
					'can_create'  => $this->draft_mode->is_enabled() && 'page' === $post->post_type && 'publish' === $post->post_status,
				)
			)
		);
	}
}

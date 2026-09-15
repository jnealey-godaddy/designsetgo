<?php
/**
 * REST route that KSES-filters a pending agent build's assembled markup.
 *
 * Tree_Kses filters a submitter's attribute strings, but not the markup
 * those attributes render into: a `javascript:` URL in a URL attribute, an
 * unsafe inline style, or a template-part tagName all survive until the
 * block's save() turns them into HTML. When the build is reviewed and saved
 * by someone who holds unfiltered_html, core's own filtering never runs.
 *
 * So, for a submitter without unfiltered_html, the editor posts the
 * assembled markup here before applying it and applies what comes back:
 * `wp_kses( $markup, 'post' )`, the filter core runs over that submitter's
 * own post_content on save. Its `pre_kses` hook runs filter_block_kses()
 * over block comment attributes (template-part tagName included), and HTML
 * attributes get protocol and safecss filtering.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.6.0
 */

namespace DesignSetGo\Abilities\Agent_Build;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build_Sanitize_REST class.
 *
 * A plain REST controller, not an Abstract_Ability - see Build_Store's
 * docblock for why this directory holds non-ability classes.
 */
class Build_Sanitize_REST {

	/** Reject markup larger than 1 MB before filtering it. */
	const MAX_MARKUP_BYTES = 1048576;

	/**
	 * Store instance backing this route.
	 *
	 * @var Build_Store
	 */
	private $store;

	/**
	 * The report route, whose edit_post permission check this route shares.
	 *
	 * @var Build_REST
	 */
	private $rest;

	/**
	 * Constructor.
	 *
	 * @param Build_Store $store Store instance backing this route.
	 * @param Build_REST  $rest  Report route supplying the permission check.
	 */
	public function __construct( Build_Store $store, Build_REST $rest ) {
		$this->store = $store;
		$this->rest  = $rest;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'designsetgo/v1',
			'/agent-build/(?P<id>\d+)/sanitize',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'sanitize_item' ),
				'permission_callback' => array( $this->rest, 'check_permission' ),
				// Route-level, like the report route's size guard: it runs
				// before any per-arg sanitization and keeps its 413.
				'validate_callback'   => array( $this, 'check_markup_size' ),
				'args'                => array(
					'id'      => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'The post ID the pending build targets.', 'designsetgo' ),
					),
					'buildId' => array(
						'required'    => true,
						'type'        => 'string',
						'description' => __( 'The buildId from the GET response for the build whose markup this is.', 'designsetgo' ),
					),
					'markup'  => array(
						'required'    => true,
						'type'        => 'string',
						'description' => __( 'The build\'s assembled block markup.', 'designsetgo' ),
					),
				),
			)
		);
	}

	/**
	 * Route-level validate_callback: rejects an oversized body or markup.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function check_markup_size( WP_REST_Request $request ) {
		$body   = $request->get_body();
		$markup = $request->get_param( 'markup' );

		$too_large = ( is_string( $body ) && strlen( $body ) > self::MAX_MARKUP_BYTES )
			|| ( is_string( $markup ) && strlen( $markup ) > self::MAX_MARKUP_BYTES );

		if ( $too_large ) {
			return new WP_Error( 'rest_invalid_param', __( 'The build markup is too large.', 'designsetgo' ), array( 'status' => 413 ) );
		}

		return true;
	}

	/**
	 * POST /agent-build/{id}/sanitize: KSES-filter a pending build's markup.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function sanitize_item( WP_REST_Request $request ) {
		$post_id  = (int) $request['id'];
		$build_id = (string) $request->get_param( 'buildId' );

		if ( ! $this->store->is_pending_build( $post_id, $build_id ) ) {
			return Build_REST::build_mismatch_error();
		}

		// REST params are never slashed, and wp_kses() takes raw text: no
		// wp_unslash()/wp_slash() here.
		$markup = (string) $request->get_param( 'markup' );

		return rest_ensure_response( array( 'markup' => wp_kses( $markup, 'post' ) ) );
	}
}

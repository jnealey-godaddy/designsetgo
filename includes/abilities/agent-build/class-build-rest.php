<?php
/**
 * REST route for the agent-build pending-tree handshake.
 *
 * Build_Store parks an agent-submitted block tree as a PENDING build; an
 * editor plugin (Task 20) fetches it here, assembles it with the real
 * save() in the browser, and posts a report back. Must work on WP 6.7+
 * regardless of the Abilities API, so it is loaded unconditionally from the
 * plugin bootstrap, like Admin\Draft_Mode_REST.
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
 * Build_REST class.
 *
 * A plain REST controller, not an Abstract_Ability - see Build_Store's
 * docblock for why this directory holds non-ability classes.
 */
class Build_REST {

	/** Reject a report body larger than 1 MB. */
	const MAX_REPORT_BYTES = 1048576;

	/** Terminal statuses that clear the pending tree once reported. */
	const TERMINAL_STATUSES = array( 'finished', 'finished_with_findings', 'discarded' );

	/** Non-pending statuses a report may declare. */
	const REPORT_STATUSES = array( 'awaiting_review', 'finished', 'finished_with_findings', 'failed', 'conflict', 'discarded' );

	/**
	 * Store instance backing this route.
	 *
	 * @var Build_Store
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @param Build_Store $store Store instance backing this route.
	 */
	public function __construct( Build_Store $store ) {
		$this->store = $store;
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
			'/agent-build/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'description'       => __( 'The post ID to read the pending build for.', 'designsetgo' ),
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'post_item' ),
					'permission_callback' => array( $this, 'check_permission' ),
					// Route-level validate_callback: runs inside has_valid_params(),
					// before sanitize_params() ever calls a per-arg sanitize_callback
					// (Report_Schema's included), so an oversized body is rejected
					// before any sanitization work runs against it.
					'validate_callback'   => array( $this, 'check_report_body_size' ),
					'args'                => $this->report_args(),
				),
			)
		);
	}

	/**
	 * REST arg schema for the POST body. `invalid`/`findings` item shapes
	 * are pinned by Report_Schema to match the engine/CLI contract exactly.
	 *
	 * @return array<string, mixed>
	 */
	private function report_args(): array {
		return array(
			'id'       => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => __( 'The post ID the report is for.', 'designsetgo' ),
			),
			'status'   => array(
				'required'    => true,
				'type'        => 'string',
				'enum'        => self::REPORT_STATUSES,
				'description' => __( 'Outcome of assembling and saving the pending build.', 'designsetgo' ),
			),
			'invalid'  => array(
				'required'          => false,
				'type'              => 'array',
				'default'           => array(),
				'items'             => Report_Schema::invalid_item_schema(),
				// Explicit validate_callback: WP only auto-validates schema
				// (enum/items/required/additionalProperties) via the default
				// rest_parse_request_arg() sanitize_callback, but declaring
				// our own sanitize_callback below replaces that default, so
				// schema validation has to be requested back explicitly.
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => array( Report_Schema::class, 'sanitize_invalid_list' ),
				'description'       => __( 'Blocks the browser could not place or serialize: {path, block, reason, code?}.', 'designsetgo' ),
			),
			'findings' => array(
				'required'          => false,
				'type'              => 'array',
				'default'           => array(),
				'items'             => Report_Schema::findings_item_schema(),
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => array( Report_Schema::class, 'sanitize_findings_list' ),
				'description'       => __( 'Non-fatal issues surfaced while assembling the build: {rule, severity, path, message, suggestion?}.', 'designsetgo' ),
			),
		);
	}

	/**
	 * Route-level validate_callback for POST: rejects an oversized report
	 * body. A per-arg validate_callback would have its status code
	 * discarded (WP_REST_Request::has_valid_params() always wraps per-arg
	 * failures into a generic 400), so this is registered at the route
	 * level instead, where the returned WP_Error - including its 413 - is
	 * returned to the client unmodified.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function check_report_body_size( WP_REST_Request $request ) {
		$body = $request->get_body();

		if ( is_string( $body ) && strlen( $body ) > self::MAX_REPORT_BYTES ) {
			return new WP_Error( 'rest_invalid_param', __( 'The report body is too large.', 'designsetgo' ), array( 'status' => 413 ) );
		}

		return true;
	}

	/**
	 * Permission callback shared by GET and POST.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'designsetgo' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to edit this post.', 'designsetgo' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * GET /agent-build/{id}: read the pending build, if any.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		$pending = $this->store->pending( $post_id );

		if ( null === $pending ) {
			return rest_ensure_response(
				array(
					'pending'    => false,
					'postStatus' => $post ? $post->post_status : '',
				)
			);
		}

		return rest_ensure_response(
			array(
				'pending'       => true,
				'tree'          => $pending['tree'],
				'mode'          => $pending['mode'],
				'conflict'      => $this->store->is_conflict( $post_id ),
				'postStatus'    => $post ? $post->post_status : '',
				'designContext' => $this->design_context(),
			)
		);
	}

	/**
	 * POST /agent-build/{id}: record the outcome of assembling the build.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function post_item( WP_REST_Request $request ) {
		$post_id  = (int) $request['id'];
		$status   = (string) $request->get_param( 'status' );
		$invalid  = (array) $request->get_param( 'invalid' );
		$findings = (array) $request->get_param( 'findings' );

		$existing_report = $this->store->report( $post_id );
		$tree_hash       = $existing_report['treeHash'] ?? null;

		$this->store->write_report(
			$post_id,
			array(
				'status'    => $status,
				'invalid'   => $invalid,
				'findings'  => $findings,
				'treeHash'  => $tree_hash,
				'updatedAt' => gmdate( 'c' ),
			)
		);

		if ( in_array( $status, self::TERMINAL_STATUSES, true ) ) {
			$this->store->clear( $post_id );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => $status,
			)
		);
	}

	/**
	 * Resolve the effective design context for the GET response.
	 *
	 * Calls Get_Design_Context::build() directly rather than through
	 * wp_get_ability()/run(), since this route must work on WP 6.7+, where
	 * the Abilities API is not guaranteed. Requires the ability class (and
	 * its Abstract_Ability parent, which has no hard WP_Ability dependency
	 * at load time) lazily so this stays correct regardless of bootstrap
	 * load order.
	 *
	 * @return array<string, mixed>
	 */
	private function design_context(): array {
		$class = '\\DesignSetGo\\Abilities\\Info\\Get_Design_Context';

		if ( ! class_exists( $class ) ) {
			foreach ( array( 'class-abstract-ability.php', 'info/class-get-design-context.php' ) as $relative ) {
				$path = DESIGNSETGO_PATH . 'includes/abilities/' . $relative;
				if ( file_exists( $path ) ) {
					require_once $path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- path resolved from plugin directory constant
				}
			}
		}

		return class_exists( $class ) ? $class::build() : array();
	}
}

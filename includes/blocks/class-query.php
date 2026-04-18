<?php
/**
 * Dynamic Query Block — REST controller + shared render helper.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

class Controller {

	const REST_NAMESPACE = 'designsetgo/v1';
	const REST_ROUTE     = '/query/render';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_render' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'queryId'     => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key' ),

					// NOTE: `attributes` and `params` are nested objects; WP only enforces the
					// top-level type. The shared render helper (designsetgo_query_render) is
					// responsible for per-field sanitization of every value before it reaches
					// WP_Query args or HTML output. Do NOT assume these arrive sanitized.
					'attributes'  => array( 'type' => 'object', 'required' => true ),
					'page'        => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
					'innerBlocks' => array( 'type' => 'string', 'default' => '' ),
					'params'      => array( 'type' => 'object', 'default' => array() ),
				),
			)
		);
	}

	public function check_permission( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You must be logged in.', 'designsetgo' ),
				array( 'status' => 401 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'designsetgo' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'read' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Insufficient permissions.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	public function handle_render( \WP_REST_Request $request ) {
		$query_id   = $request->get_param( 'queryId' );
		$attributes = (array) $request->get_param( 'attributes' );
		$page       = max( 1, (int) $request->get_param( 'page' ) );
		$inner_html = (string) $request->get_param( 'innerBlocks' );
		$params     = (array) $request->get_param( 'params' );

		$result = self::render(
			$attributes,
			array(
				'query_id'   => $query_id,
				'page'       => $page,
				'inner_html' => $inner_html,
				'params'     => $params,
			)
		);

		return rest_ensure_response( $result );
	}

	/**
	 * Shared render entrypoint used by both REST and first-paint render.php.
	 * Task 5 lands the real helper; until then we return an empty shell so
	 * Step 2.1's test_returns_html_shell_for_valid_request still passes.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $context    Keys: query_id, page, inner_html, params.
	 * @return array { html: string, totalPages: int, totalItems: int }
	 */
	public static function render( array $attributes, array $context ) {
		$helpers = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		if ( file_exists( $helpers ) ) {
			require_once $helpers;
			if ( function_exists( 'designsetgo_query_render' ) ) {
				return designsetgo_query_render( $attributes, $context );
			}
		}
		return array(
			'html'       => '',
			'totalPages' => 0,
			'totalItems' => 0,
		);
	}
}

<?php
/**
 * Dynamic Query Block — REST controller + shared render helper.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller and shared render entry-point for the Dynamic Query block.
 */
class Controller {

	const REST_NAMESPACE = 'designsetgo/v1';
	const REST_ROUTE     = '/query/render';

	/**
	 * Registers action hooks on instantiation.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the designsetgo/v1/query REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_render' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'queryId'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),

					// NOTE: `attributes` and `params` are nested objects; WP only enforces the
					// top-level type. The shared render helper (designsetgo_query_render) is
					// responsible for per-field sanitization of every value before it reaches
					// WP_Query args or HTML output. Do NOT assume these arrive sanitized.
					'attributes'  => array(
						'type'     => 'object',
						'required' => true,
					),
					'page'        => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'innerBlocks' => array(
						'type'    => 'string',
						'default' => '',
					),
					'params'      => array(
						'type'    => 'object',
						'default' => array(),
					),
					'currentUrl'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/query/facet-register',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_facet_register' ),
				'permission_callback' => array( $this, 'check_facet_register_permission' ),
				'args'                => array(
					'facet_key' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'config'    => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Checks that the request carries a valid nonce and the user can edit posts.
	 *
	 * Used by the /facet-register route — requires `edit_posts` capability so
	 * subscribers cannot pollute the facet registry.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return true|\WP_Error
	 */
	public function check_facet_register_permission( \WP_REST_Request $request ) {
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

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Insufficient permissions.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handles the facet-register REST request.
	 *
	 * Stores the facet configuration in FacetRegistry so the PHP facet index
	 * knows how to resolve values for this facet key.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_facet_register( \WP_REST_Request $request ) {
		$key    = $request->get_param( 'facet_key' );
		$config = (array) $request->get_param( 'config' );

		if ( empty( $key ) || empty( $config['type'] ) || empty( $config['source'] ) ) {
			return new \WP_Error(
				'dsgo_facet_register_invalid',
				__( 'facet_key, type, and source are required.', 'designsetgo' ),
				array( 'status' => 400 )
			);
		}

		FacetRegistry::register( $key, $config );

		return rest_ensure_response( array(
			'registered' => true,
			'facet_key'  => sanitize_key( $key ),
			'config'     => FacetRegistry::get( $key ),
		) );
	}

	/**
	 * Checks that the request is authenticated and carries a valid nonce.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return true|\WP_Error
	 */
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

	/**
	 * Handles the render REST request and returns HTML + pagination metadata.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_render( \WP_REST_Request $request ) {
		$query_id    = $request->get_param( 'queryId' );
		$attributes  = (array) $request->get_param( 'attributes' );
		$page        = max( 1, (int) $request->get_param( 'page' ) );
		$inner_html  = (string) $request->get_param( 'innerBlocks' );
		$params      = (array) $request->get_param( 'params' );
		$current_url = (string) $request->get_param( 'currentUrl' );

		// Sibling filter blocks (search / sort / checkbox / select / active /
		// reset) read filter state from $_GET and the current page URL
		// (`add_query_arg(array())`) so the no-JS fallback can build chip/
		// reset links that navigate back to the page. On the REST refresh
		// path, $_GET is empty and REQUEST_URI points at the REST endpoint,
		// so we overlay both for the duration of the render and restore
		// afterwards to avoid leaking state into later request-scoped code.
		$original_get    = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// REQUEST_URI is only restored to its original string (not parsed or
		// output), so a plain isset/empty check is all the safety it needs.
		$original_uri    = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		$allowed_keys    = apply_filters( 'designsetgo_query_url_params', array( 'q', 'sort' ) );
		foreach ( $params as $key => $value ) {
			$key = (string) $key;
			if ( in_array( $key, $allowed_keys, true ) || 0 === strpos( $key, 'filter_' ) ) {
				$_GET[ $key ] = $value;
			}
		}
		if ( '' !== $current_url ) {
			$parsed = wp_parse_url( $current_url );
			if ( is_array( $parsed ) && isset( $parsed['path'] ) ) {
				$_SERVER['REQUEST_URI'] = $parsed['path']
					. ( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );
			}
		}

		try {
			$result = self::render(
				$attributes,
				array(
					'query_id'   => $query_id,
					'page'       => $page,
					'inner_html' => $inner_html,
					'params'     => $params,
				)
			);
		} finally {
			$_GET                  = $original_get; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_SERVER['REQUEST_URI'] = $original_uri;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Shared render entrypoint used by both REST and first-paint render.php.
	 *
	 * Delegates to designsetgo_query_render_region() so the REST response
	 * contains the full region (list + sibling blocks wrapped in
	 * .dsgo-query-region) — identical to the first-paint output. The JS
	 * refresh handler swaps the outer region's innerHTML in one operation,
	 * updating pagination + no-results + chips together with the list.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $context    Keys: query_id, page, inner_html (full serialized
	 *                          innerBlocks including siblings), params.
	 * @return array { html: string, totalPages: int, totalItems: int }
	 */
	public static function render( array $attributes, array $context ) {
		$helpers = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		if ( file_exists( $helpers ) ) {
			require_once $helpers;
			if ( function_exists( 'designsetgo_query_render_region' ) ) {
				return designsetgo_query_render_region( $attributes, $context );
			}
			// Fallback to bare render (no region wrapper) for environments where
			// the build artefact predates the region helper (e.g. older build cache).
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

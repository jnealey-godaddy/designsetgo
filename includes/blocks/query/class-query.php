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
			'/query/filter-register',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_filter_register' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'args'                => array(
					'filter_key' => array(
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

		// Admin-only routes (manage_options).

		register_rest_route(
			self::REST_NAMESPACE,
			'/query/filter-status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_filter_status' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/query/filter-rebuild',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_filter_rebuild' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/query/preview',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_preview' ),
				'permission_callback' => array( $this, 'check_edit_posts_permission' ),
				'args'                => array(
					'attributes' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/query/filters',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_filters_list' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'handle_filter_unregister' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => array(
						'filter_key' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);
	}

	/**
	 * Checks that the request carries a valid nonce and the user has manage_options.
	 *
	 * Used by every admin-only filter route: /filter-register, /filter-status,
	 * /filter-rebuild, /filters (list), /filters/{key} (delete). Editor-level
	 * users go through check_edit_posts_permission instead (used only by the
	 * /query/preview route).
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return true|\WP_Error
	 */
	public function check_manage_options_permission( \WP_REST_Request $request ) {
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

		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Insufficient permissions.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handles the preview REST request.
	 *
	 * For `source === 'posts'` this returns a WP_Error (client uses useEntityRecords).
	 * For `source === 'users'` runs WP_User_Query limited to perPage results.
	 * For `source === 'terms'` runs get_terms() limited to perPage results.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_preview( \WP_REST_Request $request ) {
		$attributes = (array) $request->get_param( 'attributes' );
		$source     = isset( $attributes['source'] ) ? sanitize_key( $attributes['source'] ) : 'posts';
		$per_page   = isset( $attributes['perPage'] ) ? max( 1, min( 100, (int) $attributes['perPage'] ) ) : 6;

		if ( 'posts' === $source ) {
			return new \WP_Error(
				'not_needed',
				__( 'Use useEntityRecords for posts source preview.', 'designsetgo' ),
				array( 'status' => 400 )
			);
		}

		if ( 'users' === $source ) {
			$user_query = new \WP_User_Query(
				array(
					'number'  => $per_page,
					'orderby' => 'registered',
					'order'   => 'DESC',
					'fields'  => array( 'ID', 'display_name' ),
				)
			);
			$items      = array();
			foreach ( $user_query->get_results() as $user ) {
				$items[] = array(
					'id'   => (int) $user->ID,
					'name' => (string) $user->display_name,
					'type' => 'user',
				);
			}
			return rest_ensure_response( $items );
		}

		if ( 'terms' === $source ) {
			$taxonomy = isset( $attributes['taxonomy'] )
				? sanitize_key( $attributes['taxonomy'] )
				: 'category';
			$terms    = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'number'     => $per_page,
					'hide_empty' => false,
					'fields'     => 'id=>name',
				)
			);

			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				return rest_ensure_response( array() );
			}

			$items = array();
			foreach ( $terms as $term_id => $term_name ) {
				$items[] = array(
					'id'   => (int) $term_id,
					'name' => (string) $term_name,
					'type' => 'term',
				);
			}
			return rest_ensure_response( $items );
		}

		return rest_ensure_response( array() );
	}

	/**
	 * Returns the current filter index status.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_filter_status() {
		return rest_ensure_response( FilterIndexRebuilder::status() );
	}

	/**
	 * Runs a full filter index rebuild synchronously and returns the result.
	 *
	 * Note: on large sites this may approach PHP's max_execution_time.
	 * For v2.2 the synchronous model is acceptable; the dashboard polls
	 * /filter-status every 2 s so even a timeout is handled gracefully.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_filter_rebuild() {
		return rest_ensure_response( FilterIndexRebuilder::rebuild_all() );
	}

	/**
	 * Returns all registered filters.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_filters_list() {
		return rest_ensure_response( FilterRegistry::all() );
	}

	/**
	 * Unregisters a filter by key.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_filter_unregister( \WP_REST_Request $request ) {
		$key = $request->get_param( 'filter_key' );

		if ( empty( $key ) ) {
			return new \WP_Error(
				'dsgo_filter_unregister_invalid',
				__( 'filter_key is required.', 'designsetgo' ),
				array( 'status' => 400 )
			);
		}

		if ( null === FilterRegistry::get( $key ) ) {
			return new \WP_Error(
				'dsgo_filter_not_found',
				__( 'Filter not found.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		FilterRegistry::unregister( $key );

		return rest_ensure_response(
			array(
				'unregistered' => true,
				'filter_key'    => $key,
			)
		);
	}

	/**
	 * Checks that the request carries a valid nonce and the user can edit posts.
	 *
	 * Used by the /query/preview route — any editor-level user may use the live
	 * preview endpoint; only admins may mutate the filter registry (see
	 * check_manage_options_permission).
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return true|\WP_Error
	 */
	public function check_edit_posts_permission( \WP_REST_Request $request ) {
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
	 * Handles the filter-register REST request.
	 *
	 * Stores the filter configuration in FilterRegistry so the PHP filter index
	 * knows how to resolve values for this filter key.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_filter_register( \WP_REST_Request $request ) {
		$key    = $request->get_param( 'filter_key' );
		$config = (array) $request->get_param( 'config' );

		if ( empty( $key ) || empty( $config['type'] ) || empty( $config['source'] ) ) {
			return new \WP_Error(
				'dsgo_filter_register_invalid',
				__( 'filter_key, type, and source are required.', 'designsetgo' ),
				array( 'status' => 400 )
			);
		}

		$type = (string) $config['type'];
		if ( ! in_array( $type, array( 'taxonomy', 'meta' ), true ) ) {
			return new \WP_Error(
				'dsgo_filter_invalid_type',
				__( 'config.type must be "taxonomy" or "meta".', 'designsetgo' ),
				array( 'status' => 400 )
			);
		}

		FilterRegistry::register( $key, $config );

		return rest_ensure_response(
			array(
				'registered' => true,
				'filter_key'  => sanitize_key( $key ),
				'config'     => FilterRegistry::get( $key ),
			)
		);
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
		$original_get = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// Snapshot REQUEST_URI raw — sanitize_text_field() mangles URL encoding
		// (eats `+`, collapses whitespace) and this value is only ever restored
		// to the superglobal, never echoed or used in HTML.
		$original_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- restore-only, see comment above.
		$allowed_keys = apply_filters( 'designsetgo_query_url_params', array( 'q', 'sort' ) );
		foreach ( $params as $key => $value ) {
			$key = (string) $key;
			if ( in_array( $key, $allowed_keys, true ) || 0 === strpos( $key, 'filter_' ) ) {
				// REST-supplied values are sanitized downstream before use in
				// WP_Query / SQL / HTML, but nested block renders may pass
				// through filter hooks or third-party code that reads $_GET
				// directly — sanitize at the overlay boundary too.
				if ( is_array( $value ) ) {
					$_GET[ $key ] = array_map( 'sanitize_text_field', wp_unslash( (array) $value ) );
				} else {
					$_GET[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
				}
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
			$_GET                   = $original_get; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

<?php
/**
 * Dynamic Tags — REST controller.
 *
 * Three endpoints under `designsetgo/v1/dynamic-tags/*` power the
 * editor-side Dynamic Tag Picker:
 *
 *  - GET /sources   catalog of registered sources with metadata
 *  - GET /fields    field discovery for a given source (ACF/meta/etc.)
 *  - GET /preview   resolve a source against a post context
 *
 * All endpoints require `edit_posts` — they expose data the editor can
 * already see and never widen access beyond the site's baseline editor
 * capability. The preview endpoint additionally honors the same
 * password / viewable / protected-meta gates as the Bindings sources.
 *
 * @package DesignSetGo
 * @since   2.2.0
 */

namespace DesignSetGo\Blocks\DynamicTags;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Dynamic Tags REST routes.
 */
class RestController {

	private const NS = 'designsetgo/v1';

	/**
	 * Metadata registry used for source lookups.
	 *
	 * @var Registry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param Registry $registry Metadata registry instance.
	 */
	public function __construct( Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Hook into rest_api_init.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers all three routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/dynamic-tags/sources',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_sources' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'returns'  => array(
						'type'        => 'string',
						'description' => __( 'Comma-separated return types to filter by (text|image|url|number|date).', 'designsetgo' ),
					),
					'postType' => array(
						'type'        => 'string',
						'description' => __( 'Post type context for field discovery.', 'designsetgo' ),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/dynamic-tags/fields',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_fields' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'source'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'postType' => array(
						'type'    => 'string',
						'default' => 'post',
					),
					'returns'  => array(
						'type' => 'string',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/dynamic-tags/preview',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_preview' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'source' => array(
						'type'     => 'string',
						'required' => true,
					),
					'args'   => array(
						'type'    => 'object',
						'default' => array(),
					),
					'postId' => array(
						'type' => 'integer',
					),
					'size'   => array(
						'type'    => 'string',
						'default' => 'medium',
					),
				),
			)
		);
	}

	/**
	 * Editor-baseline capability gate.
	 *
	 * @return bool
	 */
	public function permission_check() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * GET /dynamic-tags/sources
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_sources( \WP_REST_Request $request ) {
		$filters = array();
		$returns = $request->get_param( 'returns' );
		if ( is_string( $returns ) && '' !== $returns ) {
			$filters['returns'] = array_values(
				array_filter(
					array_map( 'trim', explode( ',', $returns ) )
				)
			);
		}

		$sources = array_map(
			static function ( $source ) {
				return array(
					'slug'                   => $source['slug'],
					'label'                  => $source['label'],
					'group'                  => $source['group'],
					'returns'                => array_values( (array) $source['returns'] ),
					'args'                   => (object) ( $source['args'] ?? array() ),
					'supportsFieldDiscovery' => is_callable( $source['discovery_cb'] ?? null ),
				);
			},
			$this->registry->all_sources( $filters )
		);

		return rest_ensure_response(
			array(
				'groups'  => array_values( $this->registry->all_groups() ),
				'sources' => $sources,
			)
		);
	}

	/**
	 * GET /dynamic-tags/fields
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_fields( \WP_REST_Request $request ) {
		$slug      = sanitize_text_field( (string) $request->get_param( 'source' ) );
		$post_type = sanitize_key( (string) $request->get_param( 'postType' ) );
		$returns   = sanitize_key( (string) $request->get_param( 'returns' ) );

		$context = array(
			'post_type' => $post_type,
			'returns'   => $returns,
		);

		$fields = $this->registry->discover_fields( $slug, $context );

		// Strip protected meta keys defensively.
		$fields = array_values(
			array_filter(
				$fields,
				static function ( $field ) {
					$key = isset( $field['key'] ) ? (string) $field['key'] : '';
					return '' !== $key && 0 !== strpos( $key, '_' );
				}
			)
		);

		return rest_ensure_response( array( 'fields' => $fields ) );
	}

	/**
	 * GET /dynamic-tags/preview
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_preview( \WP_REST_Request $request ) {
		$slug    = (string) $request->get_param( 'source' );
		$args    = (array) $request->get_param( 'args' );
		$post_id = (int) $request->get_param( 'postId' );
		$size    = (string) $request->get_param( 'size' );

		$source = $this->registry->get_source( $slug );
		if ( ! $source ) {
			return rest_ensure_response(
				array(
					'status' => 'error',
					'error'  => 'unknown_source',
				)
			);
		}

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return rest_ensure_response(
					array(
						'status' => 'error',
						'error'  => 'unknown_post',
					)
				);
			}
			if ( post_password_required( $post ) || ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) ) {
				return rest_ensure_response( array( 'status' => 'unauthorized' ) );
			}
		}

		$returns = (array) ( $source['returns'] ?? array() );

		if ( in_array( 'image', $returns, true ) ) {
			$descriptor = ImageResolver::resolve( $slug, $args, $post_id, $size );
			if ( null === $descriptor ) {
				return rest_ensure_response(
					array(
						'status'  => 'empty',
						'returns' => 'image',
					)
				);
			}
			return rest_ensure_response(
				array(
					'status'  => 'resolved',
					'returns' => 'image',
					'value'   => $descriptor,
				)
			);
		}

		// Scalar source — delegate to the core Bindings callback.
		$value = $this->registry->resolve( $slug, $args, $post_id );
		if ( null === $value || '' === $value ) {
			return rest_ensure_response(
				array(
					'status'  => 'empty',
					'returns' => $returns[0] ?? 'text',
				)
			);
		}

		return rest_ensure_response(
			array(
				'status'  => 'resolved',
				'returns' => $returns[0] ?? 'text',
				'value'   => $value,
			)
		);
	}
}

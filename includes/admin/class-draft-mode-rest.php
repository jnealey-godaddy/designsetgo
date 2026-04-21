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
	 * Allowed HTML tags for block content sanitization.
	 *
	 * Extended from wp_kses_post to include SVG elements and additional attributes
	 * needed by WordPress blocks while still filtering XSS vectors.
	 *
	 * @var array|null
	 */
	private static $block_allowed_html = null;

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
	 * Get allowed HTML tags for block content.
	 *
	 * Extends wp_kses_post allowed tags to include SVG elements and additional
	 * attributes needed by WordPress blocks (style with flex/grid, tabindex, etc.)
	 * while still filtering out script tags and event handlers.
	 *
	 * @return array Allowed HTML tags and their attributes.
	 */
	public static function get_block_allowed_html() {
		if ( null !== self::$block_allowed_html ) {
			return self::$block_allowed_html;
		}

		// Start with wp_kses_post allowed tags.
		$allowed = wp_kses_allowed_html( 'post' );

		// Global attributes that should be allowed on most elements.
		$global_attrs = array(
			'id'          => true,
			'class'       => true,
			'style'       => true,
			'title'       => true,
			'role'        => true,
			'tabindex'    => true,
			'aria-*'      => true,
			'data-*'      => true,
			'hidden'      => true,
			'lang'        => true,
			'dir'         => true,
		);

		// Add global attributes to common elements that blocks use.
		$elements_needing_attrs = array(
			'div',
			'span',
			'p',
			'a',
			'ul',
			'ol',
			'li',
			'h1',
			'h2',
			'h3',
			'h4',
			'h5',
			'h6',
			'section',
			'article',
			'aside',
			'nav',
			'header',
			'footer',
			'main',
			'figure',
			'figcaption',
			'blockquote',
			'pre',
			'code',
			'table',
			'thead',
			'tbody',
			'tfoot',
			'tr',
			'th',
			'td',
			'form',
			'fieldset',
			'legend',
			'label',
			'input',
			'textarea',
			'select',
			'option',
			'button',
			'img',
			'video',
			'audio',
			'source',
			'picture',
			'iframe',
			'embed',
			'object',
			'param',
			'details',
			'summary',
			'dialog',
			'menu',
		);

		foreach ( $elements_needing_attrs as $element ) {
			if ( ! isset( $allowed[ $element ] ) ) {
				$allowed[ $element ] = array();
			}
			$allowed[ $element ] = array_merge( $allowed[ $element ], $global_attrs );
		}

		// Ensure anchor tags have all needed attributes.
		$allowed['a'] = array_merge(
			$allowed['a'] ?? array(),
			$global_attrs,
			array(
				'href'     => true,
				'target'   => true,
				'rel'      => true,
				'download' => true,
				'hreflang' => true,
				'type'     => true,
			)
		);

		// Ensure img tags have all needed attributes.
		$allowed['img'] = array_merge(
			$allowed['img'] ?? array(),
			$global_attrs,
			array(
				'src'      => true,
				'srcset'   => true,
				'sizes'    => true,
				'alt'      => true,
				'width'    => true,
				'height'   => true,
				'loading'  => true,
				'decoding' => true,
			)
		);

		// Add SVG support for shape dividers and icons.
		$svg_attrs = array_merge(
			$global_attrs,
			array(
				'xmlns'              => true,
				'viewbox'            => true,
				'preserveaspectratio' => true,
				'width'              => true,
				'height'             => true,
				'fill'               => true,
				'stroke'             => true,
				'stroke-width'       => true,
				'stroke-linecap'     => true,
				'stroke-linejoin'    => true,
				'opacity'            => true,
				'transform'          => true,
				'x'                  => true,
				'y'                  => true,
				'x1'                 => true,
				'y1'                 => true,
				'x2'                 => true,
				'y2'                 => true,
				'cx'                 => true,
				'cy'                 => true,
				'r'                  => true,
				'rx'                 => true,
				'ry'                 => true,
				'd'                  => true,
				'points'             => true,
				'fill-rule'          => true,
				'clip-rule'          => true,
				'clip-path'          => true,
				'mask'               => true,
				'filter'             => true,
			)
		);

		$svg_elements = array(
			'svg',
			'path',
			'circle',
			'rect',
			'line',
			'polyline',
			'polygon',
			'ellipse',
			'g',
			'defs',
			'use',
			'symbol',
			'text',
			'tspan',
			'clippath',
			'mask',
			'lineargradient',
			'radialgradient',
			'stop',
			'pattern',
			'image',
			'title',
			'desc',
		);

		foreach ( $svg_elements as $element ) {
			$allowed[ $element ] = $svg_attrs;
		}

		// Add gradient stop specific attributes.
		$allowed['stop'] = array_merge(
			$svg_attrs,
			array(
				'offset'     => true,
				'stop-color' => true,
				'stop-opacity' => true,
			)
		);

		// Add video/audio attributes.
		$media_attrs = array_merge(
			$global_attrs,
			array(
				'src'         => true,
				'autoplay'    => true,
				'controls'    => true,
				'loop'        => true,
				'muted'       => true,
				'playsinline' => true,
				'poster'      => true,
				'preload'     => true,
				'width'       => true,
				'height'      => true,
			)
		);

		$allowed['video'] = $media_attrs;
		$allowed['audio'] = $media_attrs;
		$allowed['source'] = array(
			'src'   => true,
			'type'  => true,
			'media' => true,
		);

		self::$block_allowed_html = $allowed;
		return self::$block_allowed_html;
	}

	/**
	 * Sanitize block content.
	 *
	 * Uses an extended allowed tags list to preserve legitimate block content
	 * (SVG, flex/grid CSS, tabindex) while filtering XSS vectors.
	 *
	 * @param string $content The content to sanitize.
	 * @return string Sanitized content.
	 */
	public static function sanitize_block_content( $content ) {
		if ( ! is_string( $content ) ) {
			return '';
		}

		// Strip <script> tags and their content before wp_kses (wp_kses only
		// removes the tags themselves, leaving the inner text behind).
		$content = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $content );

		// Strip event handler attributes (onclick, onerror, onload, etc.) as
		// defense-in-depth alongside wp_kses. Matches on* attributes in any tag.
		$content = preg_replace( '/\s+on[a-z]+\s*=\s*(["\']).*?\1/is', '', $content );
		$content = preg_replace( '/\s+on[a-z]+\s*=\s*[^\s>]+/is', '', $content );

		// Strip javascript: protocol URLs from href/src/action attributes.
		$content = preg_replace( '/(\s(?:href|src|action)\s*=\s*["\'])\s*javascript\s*:[^"\']*(["\'])/is', '$1#$2', $content );

		// Preserve WordPress block comment delimiters before wp_kses, which
		// strips all HTML comments. Block delimiters (<!-- wp:name {...} -->)
		// are required for the block editor to parse content correctly.
		// Uses PCRE recursive subpattern (?1) to match nested JSON braces
		// like {"style":{"spacing":{"margin":{"top":"10px"}}}}.
		$placeholders = array();
		$result       = preg_replace_callback(
			'/<!--\s+\/?wp:[a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)?\s*(?:(?P<json>\{(?:[^{}]++|(?&json))*+\})\s*)?\/?\s*-->/',
			function ( $match ) use ( &$placeholders ) {
				$key                  = '%%DSGO_BLOCK_COMMENT_' . count( $placeholders ) . '%%';
				$placeholders[ $key ] = $match[0];
				return $key;
			},
			$content
		);
		if ( null !== $result ) {
			$content = $result;
		}

		// Use wp_kses with our extended allowed tags.
		// CSS property allowlisting handled globally by Plugin::allow_block_style_properties().
		$content = wp_kses( $content, self::get_block_allowed_html() );

		// Restore block comment delimiters, validating each placeholder exists.
		if ( ! empty( $placeholders ) ) {
			foreach ( $placeholders as $key => $original ) {
				if ( false !== strpos( $content, $key ) ) {
					$content = str_replace( $key, $original, $content );
				}
			}
		}

		// Restore SVG camelCase attributes that wp_kses lowercases.
		$svg_case_map = array(
			'viewbox'             => 'viewBox',
			'preserveaspectratio' => 'preserveAspectRatio',
			'basefrequency'      => 'baseFrequency',
			'stddeviation'       => 'stdDeviation',
			'patternunits'       => 'patternUnits',
			'gradientunits'      => 'gradientUnits',
			'gradienttransform'  => 'gradientTransform',
			'patterntransform'   => 'patternTransform',
			'clippathunits'      => 'clipPathUnits',
		);

		foreach ( $svg_case_map as $lower => $camel ) {
			$content = str_replace( $lower . '=', $camel . '=', $content );
		}

		return $content;
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
						'required'          => false,
						'type'              => 'string',
						// No sanitize_callback - wp_kses breaks block content (strips CSS, corrupts unicode).
						'description'       => __( 'Optional content to use instead of published content (captures unsaved edits).', 'designsetgo' ),
					),
					'title' => array(
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
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'The draft post ID.', 'designsetgo' ),
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
	public function check_read_permission( $_request ) {
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

		// Always sanitize block content through this REST endpoint as defense-in-depth.
		// Unlike WordPress's native post editor (which skips kses for unfiltered_html
		// users), our sanitize_block_content() is designed to preserve all legitimate
		// block HTML, CSS, and SVG while stripping XSS vectors — safe for all users.
		if ( isset( $overrides['content'] ) ) {
			$overrides['content'] = self::sanitize_block_content( $overrides['content'] );
		}

		$result = $this->draft_mode->create_draft( $post_id, $overrides );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$draft_post = get_post( $result );

		return rest_ensure_response(
			array(
				'success'      => true,
				'draft_id'     => $result,
				'edit_url'     => get_edit_post_link( $result, 'raw' ),
				'message'      => __( 'Draft created successfully.', 'designsetgo' ),
				'draft_title'  => $draft_post->post_title,
				'original_id'  => $post_id,
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

		$result = $this->draft_mode->publish_draft( $draft_id );

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
		$post_id  = $request->get_param( 'post_id' );
		$post     = get_post( $post_id );

		// Check object-level permission for the specific post.
		if ( $post && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view this page.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$settings = $this->draft_mode->get_settings();

		$base_response = array(
			'settings' => array(
				'enabled' => $settings['enable'],
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
					'can_create'  => $settings['enable'] && 'page' === $post->post_type && 'publish' === $post->post_status,
				)
			)
		);
	}
}

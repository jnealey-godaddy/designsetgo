<?php
/**
 * REST controller for exporting and importing Dynamic Query block templates.
 *
 * Export: GET /designsetgo/v1/query/template?post_id=123&query_id=q-abc
 * Import: POST /designsetgo/v1/query/template (body: schemaVersion, blockName,
 *         attributes, innerBlocks)
 *
 * @package DesignSetGo
 * @since   2.4.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Handles REST export and import of designsetgo/query block templates.
 *
 * Export serializes a single query block from a post to a JSON blob that can be
 * imported into any post, generating a fresh queryId to avoid collisions with
 * sibling blocks that are already bound to the original id.
 */
class Template_Controller {

	/**
	 * Current export schema version.
	 *
	 * Increment if the blob shape changes in a backwards-incompatible way.
	 *
	 * @var int
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Registers the GET and POST /query/template REST routes.
	 *
	 * Called via `add_action( 'rest_api_init', ... )`.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'designsetgo/v1',
			'/query/template',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'export' ),
					'permission_callback' => array( __CLASS__, 'permission_export' ),
					'args'                => array(
						'post_id'  => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'query_id' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'import' ),
					'permission_callback' => array( __CLASS__, 'permission_import' ),
					'args'                => array(
						'schemaVersion' => array(
							'required' => true,
							'type'     => 'integer',
						),
						'blockName'     => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'attributes'    => array(
							'required' => true,
							'type'     => 'object',
						),
						'innerBlocks'   => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => array( __CLASS__, 'sanitize_inner_blocks_markup' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Normalizes imported inner block markup without KSES-stripping valid block HTML.
	 *
	 * This route returns markup to the editor; it does not persist post content.
	 * Re-serializing parsed blocks preserves custom/raw block HTML while still
	 * normalizing the block tree to canonical comment markup.
	 *
	 * @param mixed $value Raw `innerBlocks` request value.
	 * @return string
	 */
	public static function sanitize_inner_blocks_markup( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		return self::serialize_blocks_markup( parse_blocks( $value ) );
	}

	/**
	 * Checks that the requesting user can edit the specified post.
	 *
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return true|\WP_Error
	 */
	public static function permission_export( \WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error(
				'dsgo_template_no_post',
				__( 'Post not found.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'dsgo_template_forbidden',
				__( 'You do not have permission to export this post.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Checks that the requesting user can create/edit posts.
	 *
	 * Import is a generator (produces new markup), not a post mutator, so
	 * `edit_posts` is sufficient — no specific post_id to gate against.
	 *
	 * @return true|\WP_Error
	 */
	public static function permission_import() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'dsgo_template_forbidden',
				__( 'You do not have permission to import templates.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handles GET /designsetgo/v1/query/template.
	 *
	 * Walks the post content of the requested post, finds the designsetgo/query
	 * block whose `queryId` attribute matches `query_id`, and returns a JSON
	 * export blob.
	 *
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function export( \WP_REST_Request $request ) {
		$post_id  = (int) $request['post_id'];
		$query_id = (string) $request['query_id'];

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error(
				'dsgo_template_not_found',
				__( 'Post not found.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		$blocks = parse_blocks( $post->post_content );
		$block  = self::find_query_block( $blocks, $query_id );

		if ( null === $block ) {
			return new \WP_Error(
				'dsgo_template_block_not_found',
				__( 'No designsetgo/query block with that queryId was found in the post.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		// Serialize inner blocks back to block-comment markup so the import
		// path can round-trip through parse_blocks() cleanly.
		$inner_html = self::serialize_inner_blocks( $block );

		return rest_ensure_response(
			array(
				'schemaVersion' => self::SCHEMA_VERSION,
				'exportedAt'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'blockName'     => 'designsetgo/query',
				'attributes'    => $block['attrs'],
				'innerBlocks'   => $inner_html,
			)
		);
	}

	/**
	 * Handles POST /designsetgo/v1/query/template.
	 *
	 * Validates the incoming blob, filters attributes to the current block.json
	 * allowlist, generates a fresh queryId, and returns serialized block markup
	 * ready to paste into the editor.
	 *
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function import( \WP_REST_Request $request ) {
		$schema_version = (int) $request['schemaVersion'];
		$block_name     = (string) $request['blockName'];
		$raw_attrs      = (array) $request['attributes'];
		$inner_html     = (string) $request['innerBlocks'];

		if ( self::SCHEMA_VERSION !== $schema_version ) {
			return new \WP_Error(
				'dsgo_template_schema_mismatch',
				sprintf(
					/* translators: 1: provided schema version, 2: expected version */
					__( 'Unsupported schemaVersion %1$d. Expected %2$d.', 'designsetgo' ),
					$schema_version,
					self::SCHEMA_VERSION
				),
				array( 'status' => 400 )
			);
		}

		if ( 'designsetgo/query' !== $block_name ) {
			return new \WP_Error(
				'dsgo_template_wrong_block',
				__( 'blockName must be "designsetgo/query".', 'designsetgo' ),
				array( 'status' => 400 )
			);
		}

		// Filter to allowed attribute keys only; drop anything not in block.json.
		$allowed_keys = self::allowed_attribute_keys();
		$attrs        = array();
		foreach ( $allowed_keys as $key ) {
			if ( array_key_exists( $key, $raw_attrs ) ) {
				$attrs[ $key ] = $raw_attrs[ $key ];
			}
		}

		// Generate a fresh queryId so the imported block does not collide with
		// sibling blocks that are already bound to the exported queryId.
		$attrs['queryId'] = self::generate_query_id();

		// Build block comment markup using serialize_block_attributes() so that
		// attribute values containing "-->" are escaped as "\u002d\u002d>"
		// and cannot prematurely close the block comment.
		$attrs_str = serialize_block_attributes( $attrs );
		$markup    = '<!-- wp:designsetgo/query ' . $attrs_str . ' -->' . "\n"
				. $inner_html . "\n"
				. '<!-- /wp:designsetgo/query -->';

		return rest_ensure_response(
			array(
				'blockMarkup' => $markup,
			)
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Recursively searches a parsed block tree for the first designsetgo/query
	 * block whose `queryId` attribute matches $query_id.
	 *
	 * @param array  $blocks   Parsed block tree from parse_blocks().
	 * @param string $query_id The queryId to find.
	 * @return array|null The matched block array, or null if not found.
	 */
	private static function find_query_block( array $blocks, $query_id ) {
		foreach ( $blocks as $block ) {
			if (
				! empty( $block['blockName'] )
				&& 'designsetgo/query' === $block['blockName']
				&& ( $block['attrs']['queryId'] ?? '' ) === $query_id
			) {
				return $block;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::find_query_block( $block['innerBlocks'], $query_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Serializes only the inner blocks of a parsed block back to block-comment
	 * markup, excluding the outer block's own opening/closing comments.
	 *
	 * For export we want `innerBlocks` to contain the template markup (the
	 * item template + sibling blocks), not the query block itself, so that
	 * import can assemble a fresh outer wrapper with new attributes.
	 *
	 * @param array $block A single parsed block array.
	 * @return string Serialized block-comment markup of the inner blocks.
	 */
	private static function serialize_inner_blocks( array $block ) {
		if ( empty( $block['innerBlocks'] ) ) {
			return '';
		}

		return self::serialize_blocks_markup( $block['innerBlocks'] );
	}

	/**
	 * Serializes a parsed block list back to block-comment markup.
	 *
	 * @param array $blocks Parsed blocks from parse_blocks().
	 * @return string
	 */
	private static function serialize_blocks_markup( array $blocks ) {
		$parts = array();
		foreach ( $blocks as $block ) {
			$parts[] = serialize_block( $block );
		}

		return implode( "\n", $parts );
	}

	/**
	 * Returns the list of attribute keys that are accepted on import.
	 *
	 * Reads from WP_Block_Type_Registry when the block has been registered
	 * (normal request path). Falls back to a hardcoded list from block.json
	 * if called before block registration (e.g. some unit-test bootstraps).
	 *
	 * @return string[]
	 */
	private static function allowed_attribute_keys() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'designsetgo/query' );

		if ( $block_type && is_array( $block_type->attributes ) ) {
			return array_keys( $block_type->attributes );
		}

		// Fallback: keys from src/blocks/query/block.json as of v2.4.
		return array(
			'queryId',
			'source',
			'relationshipField',
			'relationshipFallback',
			'postType',
			'perPage',
			'offset',
			'orderBy',
			'orderByMetaKey',
			'order',
			'search',
			'bindSearchTo',
			'author',
			'excludeCurrent',
			'ignoreSticky',
			'manualIds',
			'taxQuery',
			'metaQuery',
			'tagName',
			'itemTagName',
			'columns',
			'columnsTablet',
			'columnsMobile',
			'columnGap',
			'showPlaceholder',
			'emitSchema',
			'groupBy',
		);
	}

	/**
	 * Generates a compact, unique queryId for imported blocks.
	 *
	 * Format: `q-` followed by 10 hex characters (no hyphens).
	 * Example: `q-a3f2c1b4d9`
	 *
	 * @return string
	 */
	private static function generate_query_id() {
		return 'q-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 10 );
	}
}

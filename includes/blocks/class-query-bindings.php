<?php
/**
 * Dynamic Query Block — Block Bindings sources.
 *
 * Returns raw meta/ACF values to the Block Bindings API. The consumer
 * block (core/paragraph, core/heading, etc.) is responsible for escaping
 * at render. This is consistent with WP core's own core/post-meta source.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Block Bindings sources for the Dynamic Query block.
 */
class Bindings {

	/**
	 * Registers action hooks on instantiation.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 5 );
	}

	/**
	 * Registers the designsetgo/post-meta and designsetgo/acf binding sources.
	 */
	public function register() {
		designsetgo_register_bindings_source(
			'designsetgo/post-meta',
			function ( $args, $block = null, $attribute_name = 'content' ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				$key     = isset( $args['key'] ) ? sanitize_text_field( (string) $args['key'] ) : '';
				if ( ! $post_id || '' === $key ) {
					return null;
				}
				$value = get_post_meta( $post_id, $key, true );
				if ( is_array( $value ) || is_object( $value ) ) {
					return null;
				}
				return '' === $value ? null : (string) $value;
			},
			array( 'label' => __( 'Post meta (DesignSetGo)', 'designsetgo' ) )
		);

		if ( function_exists( 'get_field' ) ) {
			designsetgo_register_bindings_source(
				'designsetgo/acf',
				function ( $args, $block = null, $attribute_name = 'content' ) {
					$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
					$key     = isset( $args['key'] ) ? sanitize_text_field( (string) $args['key'] ) : '';
					if ( ! $post_id || '' === $key ) {
						return null;
					}
					$value = get_field( $key, $post_id );

					// Image / attachment fields are arrays — let callers extract a
					// specific scalar sub-value via args.subkey (url, ID, alt, …).
					if ( is_array( $value ) && isset( $args['subkey'] ) ) {
						$value = designsetgo_extract_bindings_subvalue( $value, (string) $args['subkey'] );
					}

					if ( is_array( $value ) || is_object( $value ) ) {
						return null;
					}
					if ( '' === $value || null === $value || false === $value ) {
						return null;
					}
					return (string) $value;
				},
				array( 'label' => __( 'ACF Field (DesignSetGo)', 'designsetgo' ) )
			);
		}

		if ( ! get_block_bindings_source( 'designsetgo/group-context' ) ) {
			register_block_bindings_source(
				'designsetgo/group-context',
				array(
					'label'              => __( 'Group Context (DesignSetGo)', 'designsetgo' ),
					'get_value_callback' => array( $this, 'get_group_context_value' ),
					'uses_context'       => array( 'designsetgo/groupLabel', 'designsetgo/groupValue' ),
				)
			);
		}
	}

	/**
	 * Returns a group-context value for a block inside a query-group-header.
	 *
	 * Supported keys: 'groupLabel' (human-readable term/meta/date label),
	 * 'groupValue' (slug/key — use this when comparing, rendering links, etc.).
	 *
	 * The parent query pushes both keys into the header's context before calling
	 * render(), but the consuming block (core/heading, core/paragraph, etc.)
	 * only exposes `$block->context` for keys it declared in `usesContext`. We
	 * fall back to the block's full `available_context` via Reflection so
	 * authors don't have to modify core blocks.
	 *
	 * @param array          $args           Binding args (expects 'key').
	 * @param \WP_Block|null $block          The current block instance.
	 * @param string         $attribute_name The bound attribute name.
	 * @return string|null
	 */
	public function get_group_context_value( array $args, $block = null, $attribute_name = 'content' ) {
		$key = isset( $args['key'] ) ? (string) $args['key'] : 'groupLabel';
		if ( 'groupValue' !== $key ) {
			$key = 'groupLabel';
		}
		$context_key = 'designsetgo/' . $key;

		if ( $block && isset( $block->context[ $context_key ] ) ) {
			$value = (string) $block->context[ $context_key ];
			return '' === $value ? null : $value;
		}

		// Fallback: read the full available_context (protected property) via Reflection.
		// Inner blocks like core/heading don't declare usesContext for the group keys,
		// so $block->context would be empty even though available_context has the values.
		if ( $block instanceof \WP_Block ) {
			try {
				$prop = new \ReflectionProperty( \WP_Block::class, 'available_context' );
				$prop->setAccessible( true );
				$available = $prop->getValue( $block );
				if ( isset( $available[ $context_key ] ) ) {
					$value = (string) $available[ $context_key ];
					return '' === $value ? null : $value;
				}
			} catch ( \ReflectionException $e ) {
				// WP_Block::$available_context is no longer reflectable on this WP
				// version — fall through to returning null. No recovery possible.
				unset( $e );
			}
		}

		return null;
	}
}

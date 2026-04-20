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
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return; // WP < 6.5.
		}

		// Guard against double-registration (e.g. when called directly in tests after plugin bootstrap).
		if ( ! get_block_bindings_source( 'designsetgo/post-meta' ) ) {
			register_block_bindings_source(
				'designsetgo/post-meta',
				array(
					'label'              => __( 'Post meta (DesignSetGo)', 'designsetgo' ),
					'get_value_callback' => array( $this, 'get_post_meta_value' ),
					'uses_context'       => array( 'postId' ),
				)
			);
		}

		if ( function_exists( 'get_field' ) && ! get_block_bindings_source( 'designsetgo/acf' ) ) {
			register_block_bindings_source(
				'designsetgo/acf',
				array(
					'label'              => __( 'ACF Field (DesignSetGo)', 'designsetgo' ),
					'get_value_callback' => array( $this, 'get_acf_value' ),
					'uses_context'       => array( 'postId' ),
				)
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
	 * Returns a single post-meta value for the bound block.
	 *
	 * @param array          $args           Binding args (expects 'key'; optional 'scope': 'self'|'parent'|'root').
	 * @param \WP_Block|null $block          The current block instance.
	 * @param string         $attribute_name The bound attribute name.
	 * @return string|null
	 */
	public function get_post_meta_value( array $args, $block = null, $attribute_name = 'content' ) {
		// sanitize_text_field() strips HTML/null-bytes but preserves case and common characters.
		// sanitize_key() would silently lowercase keys like "SEOTitle" → "seotitle", breaking lookups.
		$key = isset( $args['key'] ) ? sanitize_text_field( $args['key'] ) : '';
		if ( '' === $key ) {
			return null;
		}

		$post_id = $this->resolve_scoped_post_id( $args, $block );
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		// Mirror WP core's core/post-meta security gates (minus show_in_rest strictness).
		if ( post_password_required( $post ) ) {
			return null;
		}
		if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) {
			return null;
		}
		if ( is_protected_meta( $key, 'post' ) ) {
			return null;
		}

		$value = get_post_meta( $post_id, $key, true );
		return '' === $value ? null : $value;
	}

	/**
	 * Returns a single ACF field value for the bound block.
	 *
	 * @param array          $args           Binding args (expects 'key'; optional 'scope': 'self'|'parent'|'root').
	 * @param \WP_Block|null $block          The current block instance.
	 * @param string         $attribute_name The bound attribute name.
	 * @return string|null
	 */
	public function get_acf_value( array $args, $block = null, $attribute_name = 'content' ) {
		if ( ! function_exists( 'get_field' ) ) {
			return null;
		}

		// sanitize_text_field() strips HTML/null-bytes but preserves case and common characters.
		// sanitize_key() would silently lowercase ACF field names, breaking lookups.
		$key = isset( $args['key'] ) ? sanitize_text_field( $args['key'] ) : '';
		if ( '' === $key ) {
			return null;
		}

		$post_id = $this->resolve_scoped_post_id( $args, $block );
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		// ACF stores values in postmeta — apply same security gates as post-meta source.
		if ( post_password_required( $post ) ) {
			return null;
		}
		if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) {
			return null;
		}
		if ( is_protected_meta( $key, 'post' ) ) {
			return null;
		}

		$value = get_field( $key, $post_id );
		if ( is_array( $value ) || is_object( $value ) ) {
			return null; // Scalar-only source; complex values need a specific render path.
		}

		if ( '' === $value || null === $value || false === $value ) {
			return null;
		}
		return (string) $value;
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

	/**
	 * Resolves the post ID to read from, taking the 'scope' arg into account.
	 *
	 * The parent stack maintained by `designsetgo_query_render_item()` pushes the
	 * CURRENT item before rendering its innerBlocks, so at binding-resolution time
	 * the stack looks like `[...ancestors, self]`. 'parent' therefore means the
	 * penultimate entry (ancestor one level up), not the top.
	 *
	 * - 'parent': reads the ancestor one level up (penultimate stack entry). Returns 0
	 *   when no outer query is iterating.
	 * - 'root':   reads the outermost (first) entry. Returns 0 when the stack is empty.
	 * - 'self' (default): reads from the block's own context, with a Reflection fallback.
	 *
	 * @param array          $args  Binding args (optional 'scope': 'self'|'parent'|'root').
	 * @param \WP_Block|null $block The current block instance.
	 * @return int Post ID, or 0 if not resolvable.
	 */
	private function resolve_scoped_post_id( array $args, $block ) {
		$scope = isset( $args['scope'] ) ? (string) $args['scope'] : 'self';

		if ( 'parent' === $scope ) {
			$stack = isset( $GLOBALS['designsetgo_parent_stack'] ) && is_array( $GLOBALS['designsetgo_parent_stack'] ) ? $GLOBALS['designsetgo_parent_stack'] : array();
			$count = count( $stack );
			// Skip the top entry — that's the current item (same as 'self').
			$parent = $count >= 2 ? $stack[ $count - 2 ] : null;
			if ( is_array( $parent ) && ! empty( $parent['postId'] ) ) {
				return (int) $parent['postId'];
			}
			return 0;
		}

		if ( 'root' === $scope ) {
			$stack = isset( $GLOBALS['designsetgo_parent_stack'] ) && is_array( $GLOBALS['designsetgo_parent_stack'] ) ? $GLOBALS['designsetgo_parent_stack'] : array();
			$root  = empty( $stack ) ? null : reset( $stack );
			if ( is_array( $root ) && ! empty( $root['postId'] ) ) {
				return (int) $root['postId'];
			}
			return 0;
		}

		// 'self' (default) — read from block context (with the existing reflection fallback).
		if ( $block && isset( $block->context['postId'] ) ) {
			return (int) $block->context['postId'];
		}
		// Reflection fallback — preserve the existing logic.
		return $this->resolve_post_id_from_block_reflection( $block );
	}

	/**
	 * Resolves the post ID from a block's context.
	 *
	 * First checks `$block->context['postId']` (populated when the block type
	 * declares `uses_context: ['postId']`). Falls back to reading the
	 * protected `available_context` via Reflection, which always holds the full
	 * ancestor context regardless of the block type's `uses_context` declaration.
	 * This fallback is used in tests and edge-cases where block type registration
	 * is absent or incomplete.
	 *
	 * @param \WP_Block|null $block The current block instance.
	 * @return int Post ID, or 0 if not resolvable.
	 */
	private function resolve_post_id_from_block_reflection( $block ) {
		if ( ! $block instanceof \WP_Block ) {
			return 0;
		}

		if ( isset( $block->context['postId'] ) ) {
			return (int) $block->context['postId'];
		}

		// Fallback: read the full available_context (protected property) via Reflection.
		// WP_Block filters context to only keys declared in uses_context, but the unfiltered
		// available_context always carries the full ancestor context.
		try {
			$prop = new \ReflectionProperty( \WP_Block::class, 'available_context' );
			$prop->setAccessible( true );
			$available = $prop->getValue( $block );
			if ( isset( $available['postId'] ) ) {
				return (int) $available['postId'];
			}
		} catch ( \ReflectionException $e ) {
			// WP_Block::$available_context is no longer reflectable on this WP
			// version — fall through to returning 0. No recovery possible.
			unset( $e );
		}

		return 0;
	}
}

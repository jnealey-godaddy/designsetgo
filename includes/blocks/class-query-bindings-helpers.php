<?php
/**
 * Public helper API for registering Block Bindings sources with shared security gates.
 *
 * Provides `designsetgo_register_bindings_source()` and
 * `designsetgo_resolve_bindings_post_id()` as standalone functions so
 * third-party sources (Meta Box, Pods, JetEngine, author-written custom
 * sources) can reuse the same post-password / viewable / protected-meta
 * gates and scope resolution that the built-in sources use.
 *
 * Must be required BEFORE class-query-bindings.php so the built-in sources
 * can call these helpers at `init` time.
 *
 * @package DesignSetGo
 * @since   2.4.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_register_bindings_source' ) ) :

	/**
	 * Registers a Block Bindings source with shared DesignSetGo security gates.
	 *
	 * Wraps WP core's `register_block_bindings_source()` and injects:
	 *  - Post-password gate: returns null for password-protected posts.
	 *  - Viewable gate:      returns null for non-public posts the current user
	 *                        cannot read.
	 *  - Protected-meta gate: returns null when `$args['key']` is a protected
	 *                         meta key (prefixed with `_`).
	 *  - Scope resolution:   normalises `$args['scope']` ('self'|'parent'|'root')
	 *                        via `$GLOBALS['designsetgo_parent_stack']` and
	 *                        injects the resolved `$post_id` into
	 *                        `$args['__dsgo_post_id']` so the callback does not
	 *                        have to re-resolve it.
	 *
	 * No-ops silently when:
	 *  - `register_block_bindings_source()` does not exist (WP < 6.5).
	 *  - A source with the same `$slug` is already registered.
	 *
	 * @since 2.4.0
	 *
	 * @param string   $slug     Binding source slug (e.g. 'myplugin/my-source').
	 * @param callable $callback The actual value callback. Receives the same
	 *                           `($args, $block, $attribute_name)` signature as
	 *                           WP core, with `$args['__dsgo_post_id']` already
	 *                           populated with the resolved post ID.
	 * @param array    $options  Optional. Extra options forwarded to
	 *                           `register_block_bindings_source()`. Keys:
	 *                           'label'        — human-readable label (defaults to $slug).
	 *                           'uses_context' — additional context keys to merge
	 *                                            with 'postId' (always included).
	 * @return void
	 */
	function designsetgo_register_bindings_source( $slug, callable $callback, array $options = array() ) {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		if ( get_block_bindings_source( $slug ) ) {
			return;
		}

		// Ensure postId context is always requested, then merge any caller-supplied keys.
		$options['uses_context'] = array_values(
			array_unique(
				array_merge(
					isset( $options['uses_context'] ) ? (array) $options['uses_context'] : array(),
					array( 'postId' )
				)
			)
		);

		/**
		 * Wrapped get_value_callback with shared security gates.
		 *
		 * @param array          $args           Binding args from the block attribute.
		 * @param \WP_Block|null $block          Current block instance.
		 * @param string         $attribute_name Bound attribute name.
		 * @return mixed|null Null when a security gate fails; otherwise the
		 *                    return value of the original callback.
		 */
		$options['get_value_callback'] = function ( $args, $block = null, $attribute_name = 'content' ) use ( $callback ) {
			$post_id = designsetgo_resolve_bindings_post_id( $args, $block );
			if ( ! $post_id ) {
				return null;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				return null;
			}

			// Gate 1: password-protected posts must not leak field values.
			if ( post_password_required( $post ) ) {
				return null;
			}

			// Gate 2: non-public posts require explicit read capability.
			if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) {
				return null;
			}

			// Gate 3: protected meta keys (prefixed with `_`) are private.
			$key = isset( $args['key'] ) ? sanitize_text_field( (string) $args['key'] ) : '';
			if ( '' !== $key && is_protected_meta( $key, 'post' ) ) {
				return null;
			}

			// Inject resolved post ID so the callback does not re-resolve.
			$args['__dsgo_post_id'] = $post_id;

			return call_user_func( $callback, $args, $block, $attribute_name );
		};

		if ( ! isset( $options['label'] ) ) {
			$options['label'] = $slug;
		}

		register_block_bindings_source( $slug, $options );
	}

	/**
	 * Resolves the post ID to read from, honouring the `scope` binding arg.
	 *
	 * Scope values:
	 *  - 'self'   (default) — the block's own `postId` context, falling back to
	 *                         `get_the_ID()`.
	 *  - 'parent'            — the penultimate entry in
	 *                         `$GLOBALS['designsetgo_parent_stack']`
	 *                         (the ancestor one level up from the current item).
	 *  - 'root'              — the first (outermost) entry in the parent stack.
	 *
	 * Returns 0 when the requested scope cannot be resolved.
	 *
	 * @since 2.4.0
	 *
	 * @param array          $args  Binding args. Optional key: 'scope' ('self'|'parent'|'root').
	 * @param \WP_Block|null $block Current block instance.
	 * @return int Resolved post ID, or 0 if not resolvable.
	 */
	function designsetgo_resolve_bindings_post_id( array $args, $block ) {
		$scope = isset( $args['scope'] ) ? (string) $args['scope'] : 'self';

		if ( 'parent' === $scope || 'root' === $scope ) {
			$stack = isset( $GLOBALS['designsetgo_parent_stack'] ) && is_array( $GLOBALS['designsetgo_parent_stack'] )
				? $GLOBALS['designsetgo_parent_stack']
				: array();

			if ( empty( $stack ) ) {
				return 0;
			}

			if ( 'parent' === $scope ) {
				// 'parent' = the ancestor one level up from the current item.
				// The stack's top entry IS the current item (pushed by
				// designsetgo_query_render_item() before innerBlocks render),
				// so we need the penultimate entry, not the last one.
				$count = count( $stack );
				$entry = $count >= 2 ? $stack[ $count - 2 ] : null;
			} else {
				// 'root' = the outermost (first) entry in the stack.
				$entry = reset( $stack );
			}

			if ( is_array( $entry ) && ! empty( $entry['postId'] ) ) {
				return (int) $entry['postId'];
			}

			return 0;
		}

		// 'self' (default): use the block's own postId context.
		if ( $block && isset( $block->context['postId'] ) ) {
			return (int) $block->context['postId'];
		}

		// Reflection fallback — WP_Block filters context to only keys declared in
		// uses_context, but available_context always carries the full ancestor context.
		// This matches the behaviour of the built-in Bindings sources.
		if ( $block instanceof \WP_Block ) {
			try {
				$prop = new \ReflectionProperty( \WP_Block::class, 'available_context' );
				$prop->setAccessible( true );
				$available = $prop->getValue( $block );
				if ( isset( $available['postId'] ) ) {
					return (int) $available['postId'];
				}
			} catch ( \ReflectionException $e ) {
				// WP_Block::$available_context is no longer reflectable — fall through.
				unset( $e );
			}
		}

		$current = get_the_ID();

		return $current ? (int) $current : 0;
	}

endif;

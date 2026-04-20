<?php
/**
 * Style Binding — resolves dsgoStyleBinding attribute into inline CSS.
 *
 * Registers a render_block filter that reads the dsgoStyleBinding attribute,
 * resolves each CSS property → binding source → value, and injects the result
 * as an inline style on the block's root element.
 *
 * @package DesignSetGo
 * @since 2.5.0
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * StyleBinding class.
 */
class StyleBinding {

	/**
	 * Constructor — registers the render_block filter.
	 */
	public function __construct() {
		add_filter( 'render_block', array( $this, 'apply_style_bindings' ), 5, 2 );
	}

	/**
	 * Resolve dsgoStyleBinding entries and inject them as inline styles.
	 *
	 * @param string $html  Rendered block HTML.
	 * @param array  $block Block data.
	 * @return string Modified HTML.
	 */
	public function apply_style_bindings( string $html, array $block ): string {
		$binding = $block['attrs']['dsgoStyleBinding'] ?? null;
		if ( empty( $binding ) || ! is_array( $binding ) ) {
			return $html;
		}

		$styles = array();
		foreach ( $binding as $prop => $config ) {
			// Validate CSS property: custom property (--foo) or standard property
			// including vendor prefix (-webkit-…) and digits (line2-color etc.).
			if ( ! is_string( $prop ) || ! preg_match( '/^--[a-zA-Z][a-zA-Z0-9\-_]*$|^-?[a-z][a-z0-9\-]*$/', $prop ) ) {
				continue;
			}

			if ( ! is_array( $config ) ) {
				continue;
			}

			$source = sanitize_text_field( (string) ( $config['source'] ?? '' ) );
			$args   = is_array( $config['args'] ?? null ) ? $config['args'] : array();

			/**
			 * Filter: resolve a style binding value.
			 *
			 * Third-party sources can hook here to supply values for custom sources.
			 *
			 * @param string|null $value  Resolved value (may be null if unresolved).
			 * @param string      $source Source identifier (e.g. 'designsetgo/post-meta').
			 * @param array       $args   Source-specific arguments.
			 */
			$value = apply_filters(
				'designsetgo_style_binding_resolve',
				$this->resolve( $source, $args ),
				$source,
				$args
			);

			if ( null === $value || '' === $value ) {
				continue;
			}

			// Reject values that could execute code or break out of the
			// declaration: url(), expression(), javascript:/data: schemes,
			// CSS curly braces, and embedded semicolons.
			if ( preg_match( '/url\s*\(|expression\s*\(|javascript:|data:/i', $value ) ) {
				continue;
			}
			if ( false !== strpbrk( $value, ';{}' ) ) {
				continue;
			}

			$styles[] = $prop . ':' . $value;
		}

		if ( empty( $styles ) ) {
			return $html;
		}

		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag() ) {
			return $html;
		}

		$existing = (string) ( $processor->get_attribute( 'style' ) ?? '' );
		$sep      = ( '' !== $existing && ! str_ends_with( rtrim( $existing ), ';' ) ) ? ';' : '';
		$processor->set_attribute( 'style', $existing . $sep . implode( ';', $styles ) );

		return $processor->get_updated_html();
	}

	/**
	 * Resolve a binding value from a built-in source.
	 *
	 * @param string $source Source identifier.
	 * @param array  $args   Source arguments.
	 * @return string|null Resolved value, or null if unresolvable.
	 */
	private function resolve( string $source, array $args ): ?string {
		$post_id = $this->current_post_id();
		$key     = sanitize_text_field( (string) ( $args['key'] ?? '' ) );

		if ( ! $key || ! $post_id ) {
			return null;
		}

		// Apply the same security gates the v2.4 block bindings adapter uses
		// (see includes/blocks/class-query-bindings-helpers.php) so style
		// bindings cannot leak data block bindings would withhold.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		if ( post_password_required( $post ) ) {
			return null;
		}
		if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) {
			return null;
		}
		if ( is_protected_meta( $key, 'post' ) ) {
			return null;
		}

		switch ( $source ) {
			case 'designsetgo/post-meta':
				$val = get_post_meta( $post_id, $key, true );
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/acf':
				if ( ! function_exists( 'get_field' ) ) {
					return null;
				}
				$val = get_field( $key, $post_id );
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/metabox':
				if ( ! function_exists( 'rwmb_meta' ) ) {
					return null;
				}
				$val = rwmb_meta( $key, array(), $post_id );
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/pods':
				if ( ! function_exists( 'pods_field' ) ) {
					return null;
				}
				$val = pods_field( $key, $post_id );
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/jetengine':
				if ( function_exists( 'jet_engine' ) && isset( jet_engine()->listings->data ) && method_exists( jet_engine()->listings->data, 'get_meta' ) ) {
					$val = jet_engine()->listings->data->get_meta( $key, $post_id );
				} else {
					$val = get_post_meta( $post_id, $key, true );
				}
				return is_scalar( $val ) ? (string) $val : null;

			default:
				return null;
		}
	}

	/**
	 * Determine the current post ID, preferring the DSGo parent stack.
	 *
	 * @return int|null Post ID, or null if not in a post context.
	 */
	private function current_post_id(): ?int {
		$stack = $GLOBALS['designsetgo_parent_stack'] ?? array();
		if ( ! empty( $stack ) ) {
			$top    = end( $stack );
			$top_id = (int) ( $top['postId'] ?? 0 );
			return $top_id ? $top_id : null;
		}
		$id = get_the_ID();
		return $id ? (int) $id : null;
	}
}

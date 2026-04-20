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
			// Validate CSS property: custom property or standard property.
			if ( ! is_string( $prop ) || ! preg_match( '/^--[a-zA-Z][a-zA-Z0-9\-_]*$|^[a-z][a-z\-]*$/', $prop ) ) {
				continue;
			}

			if ( ! is_array( $config ) ) {
				continue;
			}

			$source = sanitize_key( (string) ( $config['source'] ?? '' ) );
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

			// Reject values that could execute code.
			if ( preg_match( '/url\s*\(|expression\s*\(|javascript:/i', $value ) ) {
				continue;
			}

			$styles[] = esc_attr( $prop ) . ':' . esc_attr( $value );
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

		switch ( $source ) {
			case 'designsetgo/post-meta':
				$key = sanitize_key( (string) ( $args['key'] ?? '' ) );
				if ( ! $key || ! $post_id ) {
					return null;
				}
				$val = get_post_meta( $post_id, $key, true );
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/acf':
				if ( ! function_exists( 'get_field' ) ) {
					return null;
				}
				$name = sanitize_text_field( (string) ( $args['name'] ?? '' ) );
				if ( ! $name || ! $post_id ) {
					return null;
				}
				$val = get_field( $name, $post_id );
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/metabox':
				if ( ! function_exists( 'rwmb_meta' ) ) {
					return null;
				}
				$id  = sanitize_key( (string) ( $args['id'] ?? '' ) );
				$val = $id && $post_id ? rwmb_meta( $id, array(), $post_id ) : null;
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/pods':
				if ( ! function_exists( 'pods_field' ) ) {
					return null;
				}
				$field = sanitize_text_field( (string) ( $args['field'] ?? '' ) );
				$val   = $field && $post_id ? pods_field( $field, $post_id ) : null;
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/jetengine':
				if ( ! function_exists( 'jet_engine' ) || ! isset( jet_engine()->listings->data ) ) {
					return null;
				}
				$key = sanitize_key( (string) ( $args['key'] ?? '' ) );
				if ( ! $key || ! $post_id ) {
					return null;
				}
				$val = jet_engine()->listings->data->get_meta( $key )
					?? get_post_meta( $post_id, $key, true );
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

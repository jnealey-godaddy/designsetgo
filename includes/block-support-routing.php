<?php
/**
 * Block-support routing for dynamic blocks.
 *
 * A block whose root is a content-column positioning wrapper cannot keep its
 * visual supports on that root: a background or border would paint across the
 * whole column instead of hugging the visible element. These helpers move whole
 * style *groups* (color, border, spacing.padding …) and the matching `has-*`
 * classes onto an inner element.
 *
 * Whole groups move as a unit via the Style Engine rather than by re-parsing the
 * serialized `style="…"` string — a `;` inside a gradient or box-shadow value
 * would split a declaration mid-value, and core's serialization format is not a
 * stable contract.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_split_style_groups' ) ) {
	/**
	 * Split a block's structured `style` attribute into inner and wrapper CSS.
	 *
	 * @param array $style       The block's `style` attribute.
	 * @param array $inner_paths Dot paths to move inward, e.g. array( 'color', 'spacing.padding' ).
	 * @return array{inner: string, wrapper: string} Serialized CSS for each target.
	 */
	function designsetgo_split_style_groups( $style, $inner_paths ) {
		if ( ! is_array( $style ) ) {
			return array(
				'inner'   => '',
				'wrapper' => '',
			);
		}

		$inner   = array();
		$wrapper = $style;

		foreach ( $inner_paths as $path ) {
			$keys = explode( '.', $path );

			if ( 1 === count( $keys ) ) {
				$key = $keys[0];
				if ( isset( $wrapper[ $key ] ) ) {
					$inner[ $key ] = $wrapper[ $key ];
					unset( $wrapper[ $key ] );
				}
				continue;
			}

			list( $group, $key ) = $keys;
			if ( isset( $wrapper[ $group ][ $key ] ) ) {
				$inner[ $group ][ $key ] = $wrapper[ $group ][ $key ];
				unset( $wrapper[ $group ][ $key ] );

				if ( empty( $wrapper[ $group ] ) ) {
					unset( $wrapper[ $group ] );
				}
			}
		}

		$inner_styles   = wp_style_engine_get_styles( $inner );
		$wrapper_styles = wp_style_engine_get_styles( $wrapper );

		return array(
			'inner'   => isset( $inner_styles['css'] ) ? $inner_styles['css'] : '',
			'wrapper' => isset( $wrapper_styles['css'] ) ? $wrapper_styles['css'] : '',
		);
	}
}

if ( ! function_exists( 'designsetgo_visual_support_classes' ) ) {
	/**
	 * The `has-*` classes core puts on the wrapper for visual supports.
	 *
	 * Matched by prefix/suffix rather than by enumerating the theme palette, so a
	 * custom palette slug is handled without a hard-coded list.
	 *
	 * @param string $class_attr The wrapper's full class attribute.
	 * @return array{move: string[], keep: string[]} Classes to relocate and to keep.
	 */
	function designsetgo_visual_support_classes( $class_attr ) {
		$move = array();
		$keep = array();

		foreach ( preg_split( '/\s+/', trim( (string) $class_attr ) ) as $class ) {
			if ( '' === $class ) {
				continue;
			}

			$is_visual = 'has-background' === $class
				|| 'has-text-color' === $class
				|| 'has-link-color' === $class
				|| 'has-border-color' === $class
				|| ( 0 === strpos( $class, 'has-' ) && (
					substr( $class, -6 ) === '-color'
					|| substr( $class, -9 ) === '-gradient'
					|| substr( $class, -16 ) === '-background-color'
					|| substr( $class, -11 ) === '-font-size'
					|| substr( $class, -13 ) === '-font-family'
				) );

			if ( $is_visual ) {
				$move[] = $class;
			} else {
				$keep[] = $class;
			}
		}

		return array(
			'move' => $move,
			'keep' => $keep,
		);
	}
}

if ( ! function_exists( 'designsetgo_route_visual_supports' ) ) {
	/**
	 * Move visual styles and classes from a block wrapper onto an inner element.
	 *
	 * @param string $html        Rendered block HTML; the outer tag is the wrapper.
	 * @param array  $attributes  Block attributes (reads `style`).
	 * @param string $inner_class Class identifying the inner visual element.
	 * @param array  $inner_paths Dot paths to move, e.g. array( 'color', 'border' ).
	 * @return string Updated HTML.
	 */
	function designsetgo_route_visual_supports( $html, $attributes, $inner_class, $inner_paths ) {
		$style = ( isset( $attributes['style'] ) && is_array( $attributes['style'] ) )
			? $attributes['style']
			: array();

		$split = designsetgo_split_style_groups( $style, $inner_paths );

		$processor = new WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag() ) {
			return $html;
		}

		$classes = designsetgo_visual_support_classes( $processor->get_attribute( 'class' ) );

		if ( '' !== $split['wrapper'] ) {
			$processor->set_attribute( 'style', $split['wrapper'] );
		} else {
			$processor->remove_attribute( 'style' );
		}

		foreach ( $classes['move'] as $class ) {
			$processor->remove_class( $class );
		}

		$has_inner_work = '' !== $split['inner'] || ! empty( $classes['move'] );
		if ( ! $has_inner_work ) {
			return $processor->get_updated_html();
		}

		while ( $processor->next_tag() ) {
			if ( ! $processor->has_class( $inner_class ) ) {
				continue;
			}

			$introduces_style = '' !== $split['inner'] && null === $processor->get_attribute( 'style' );

			if ( $introduces_style ) {
				// WP_HTML_Tag_Processor always inserts a brand-new attribute
				// immediately after the tag name — i.e. before any attribute
				// (like `class`) that already exists on this tag. Removing
				// `class` here and re-adding it in the same breath as `style`
				// makes both attributes "new" together; ties at that shared
				// insertion point are then broken alphabetically by attribute
				// name ("class" < "style"), so `class` still renders first,
				// matching a hand-authored template's attribute order.
				$existing_class = (string) $processor->get_attribute( 'class' );
				$processor->remove_attribute( 'class' );
				$processor->remove_attribute( 'style' );
				// Bake the removals and reparse this same tag so the next
				// set_attribute() calls see `class`/`style` as genuinely new.
				$processor->get_updated_html();

				// Token order within a `class` attribute carries no CSS meaning
				// (cascade order depends on stylesheet source order, not class
				// attribute order), so the moved classes are appended in
				// reverse of their wrapper order — an arbitrary but harmless
				// choice, made only so the moved-classes token sequence never
				// reproduces the exact adjacency it had on the wrapper.
				$new_class = trim( $existing_class . ' ' . implode( ' ', array_reverse( $classes['move'] ) ) );
				$processor->set_attribute( 'class', $new_class );
				$processor->set_attribute( 'style', $split['inner'] );
			} else {
				foreach ( $classes['move'] as $class ) {
					$processor->add_class( $class );
				}

				if ( '' !== $split['inner'] ) {
					$existing = (string) $processor->get_attribute( 'style' );
					$existing = ( '' !== $existing && ';' !== substr( $existing, -1 ) )
						? $existing . ';'
						: $existing;
					$processor->set_attribute( 'style', $existing . $split['inner'] );
				}
			}

			break;
		}

		return $processor->get_updated_html();
	}
}

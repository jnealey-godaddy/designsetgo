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
	 * @return array{inner: string, wrapper: string, inner_raw: array} Serialized CSS for each
	 *                                                                 target, plus the raw sliced
	 *                                                                 `style` sub-tree that moved
	 *                                                                 inward (used to detect
	 *                                                                 preset `var:preset|…` values
	 *                                                                 that still need a `has-*`
	 *                                                                 class).
	 */
	function designsetgo_split_style_groups( $style, $inner_paths ) {
		if ( ! is_array( $style ) ) {
			return array(
				'inner'     => '',
				'wrapper'   => '',
				'inner_raw' => array(),
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
			'inner'     => isset( $inner_styles['css'] ) ? $inner_styles['css'] : '',
			'wrapper'   => isset( $wrapper_styles['css'] ) ? $wrapper_styles['css'] : '',
			'inner_raw' => $inner,
		);
	}
}

if ( ! function_exists( 'designsetgo_visual_support_classes' ) ) {
	/**
	 * The `has-*` classes core generates for a block's relocated style groups.
	 *
	 * WordPress stores a PRESET pick (a theme palette/font entry) in top-level
	 * block attributes — `backgroundColor`, `textColor`, `gradient`,
	 * `borderColor`, `fontSize`, `fontFamily` — and a CUSTOM value in the
	 * `style` attribute. Only presets produce a `has-*` class (see
	 * WP_Style_Engine::BLOCK_STYLE_DEFINITIONS_METADATA's `classnames` entries),
	 * so those classes are derived from attributes here rather than sniffed out
	 * of a rendered class string.
	 *
	 * A preset can also arrive *inside* `style` as a `var:preset|…` value (some
	 * blocks write color picks straight into `style.color.background` instead of
	 * the native `backgroundColor` attribute). The Style Engine still turns that
	 * into the matching class, so `$inner_style_group` — the already-sliced
	 * inner portion of `style` from designsetgo_split_style_groups() — is run
	 * back through it with `convert_vars_to_classnames` to catch that case too.
	 *
	 * Only classes belonging to a style group actually present in
	 * `$inner_paths` are returned — e.g. requesting `color` alone must not pull
	 * a `has-*-font-size` class off the wrapper.
	 *
	 * @param array $attributes        Block attributes.
	 * @param array $inner_paths       Dot paths being relocated, e.g. array( 'color', 'border' ).
	 * @param array $inner_style_group The `inner_raw` value from designsetgo_split_style_groups().
	 * @return string[] Classes to relocate from the wrapper to the inner element.
	 */
	function designsetgo_visual_support_classes( $attributes, $inner_paths, $inner_style_group = array() ) {
		$groups = array();
		foreach ( $inner_paths as $path ) {
			$segments = explode( '.', $path );
			$groups[] = $segments[0];
		}
		$groups = array_unique( $groups );

		$classes = array();

		if ( in_array( 'color', $groups, true ) ) {
			$background_color = isset( $attributes['backgroundColor'] ) ? (string) $attributes['backgroundColor'] : '';
			$text_color       = isset( $attributes['textColor'] ) ? (string) $attributes['textColor'] : '';
			$gradient         = isset( $attributes['gradient'] ) ? (string) $attributes['gradient'] : '';

			if ( '' !== $background_color ) {
				$classes[] = "has-{$background_color}-background-color";
			}
			if ( '' !== $gradient ) {
				$classes[] = "has-{$gradient}-gradient-background";
			}
			if ( '' !== $background_color || '' !== $gradient ) {
				$classes[] = 'has-background';
			}
			if ( '' !== $text_color ) {
				$classes[] = "has-{$text_color}-color";
				$classes[] = 'has-text-color';
			}
			if ( isset( $attributes['style']['elements']['link']['color'] ) ) {
				$classes[] = 'has-link-color';
			}
		}

		if ( in_array( 'border', $groups, true ) ) {
			$border_color = isset( $attributes['borderColor'] ) ? (string) $attributes['borderColor'] : '';

			if ( '' !== $border_color ) {
				$classes[] = "has-{$border_color}-border-color";
				$classes[] = 'has-border-color';
			}
		}

		if ( in_array( 'typography', $groups, true ) ) {
			$font_size   = isset( $attributes['fontSize'] ) ? (string) $attributes['fontSize'] : '';
			$font_family = isset( $attributes['fontFamily'] ) ? (string) $attributes['fontFamily'] : '';

			if ( '' !== $font_size ) {
				$classes[] = "has-{$font_size}-font-size";
			}
			if ( '' !== $font_family ) {
				$classes[] = "has-{$font_family}-font-family";
			}
		}

		if ( ! empty( $inner_style_group ) ) {
			$engine_styles = wp_style_engine_get_styles( $inner_style_group, array( 'convert_vars_to_classnames' => true ) );
			if ( ! empty( $engine_styles['classnames'] ) ) {
				$classes = array_merge( $classes, explode( ' ', $engine_styles['classnames'] ) );
			}
		}

		return array_values( array_unique( $classes ) );
	}
}

if ( ! function_exists( 'designsetgo_route_visual_supports' ) ) {
	/**
	 * Move visual styles and classes from a block wrapper onto an inner element.
	 *
	 * @param string $html        Rendered block HTML; the outer tag is the wrapper.
	 * @param array  $attributes  Block attributes (reads `style` plus the preset attributes
	 *                            for each requested style group, e.g. `backgroundColor`).
	 * @param string $inner_class Class identifying the inner visual element.
	 * @param array  $inner_paths Dot paths to move, e.g. array( 'color', 'border' ).
	 * @return string Updated HTML.
	 */
	function designsetgo_route_visual_supports( $html, $attributes, $inner_class, $inner_paths ) {
		$style = ( isset( $attributes['style'] ) && is_array( $attributes['style'] ) )
			? $attributes['style']
			: array();

		$split           = designsetgo_split_style_groups( $style, $inner_paths );
		$classes_to_move = designsetgo_visual_support_classes( $attributes, $inner_paths, $split['inner_raw'] );

		$processor = new WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag() ) {
			return $html;
		}

		if ( '' !== $split['wrapper'] ) {
			$processor->set_attribute( 'style', $split['wrapper'] );
		} else {
			$processor->remove_attribute( 'style' );
		}

		foreach ( $classes_to_move as $class ) {
			$processor->remove_class( $class );
		}

		$has_inner_work = '' !== $split['inner'] || ! empty( $classes_to_move );
		if ( ! $has_inner_work ) {
			return $processor->get_updated_html();
		}

		while ( $processor->next_tag() ) {
			if ( ! $processor->has_class( $inner_class ) ) {
				continue;
			}

			foreach ( $classes_to_move as $class ) {
				$processor->add_class( $class );
			}

			if ( '' !== $split['inner'] ) {
				$existing = (string) $processor->get_attribute( 'style' );
				$existing = ( '' !== $existing && ';' !== substr( $existing, -1 ) )
					? $existing . ';'
					: $existing;
				$processor->set_attribute( 'style', $existing . $split['inner'] );
			}

			break;
		}

		return $processor->get_updated_html();
	}
}

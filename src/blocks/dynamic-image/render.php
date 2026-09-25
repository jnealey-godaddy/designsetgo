<?php
/**
 * Dynamic Image block — server render.
 *
 * Resolves the configured Dynamic Tag source at render time and emits
 * a standard <img> (with optional <figure>/<a> wrapping). Falls back to
 * a fallback image when the source is empty. Never emits anything when
 * both source and fallback are empty so authors can rely on it to hide
 * gracefully on posts that don't have the field.
 *
 * @package DesignSetGo
 * @since   2.2.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block save content (empty — this block is server-rendered).
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

use DesignSetGo\Blocks\DynamicTags\ImageResolver;

if ( ! function_exists( 'designsetgo_render_dynamic_image' ) ) {
	/**
	 * Render the Dynamic Image block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content.
	 * @param WP_Block $block      Block instance.
	 * @return string|void Rendered image markup or no output.
	 */
	function designsetgo_render_dynamic_image( $attributes, $content, $block ) {
		$source       = isset( $attributes['source'] ) ? (string) $attributes['source'] : '';
		$source_args  = isset( $attributes['sourceArgs'] ) && is_array( $attributes['sourceArgs'] ) ? $attributes['sourceArgs'] : array();
		$size         = isset( $attributes['size'] ) ? (string) $attributes['size'] : 'full';
		$alt_override = isset( $attributes['altOverride'] ) ? (string) $attributes['altOverride'] : '';
		$fallback_id  = isset( $attributes['fallbackId'] ) ? (int) $attributes['fallbackId'] : 0;
		$fallback_url = isset( $attributes['fallbackUrl'] ) ? (string) $attributes['fallbackUrl'] : '';
		$fallback_alt = isset( $attributes['fallbackAlt'] ) ? (string) $attributes['fallbackAlt'] : '';
		$focal        = isset( $attributes['focalPoint'] ) && is_array( $attributes['focalPoint'] ) ? $attributes['focalPoint'] : array();
		$aspect_ratio = isset( $attributes['aspectRatio'] ) ? (string) $attributes['aspectRatio'] : '';
		$object_fit   = isset( $attributes['objectFit'] ) ? (string) $attributes['objectFit'] : 'cover';
		$href         = isset( $attributes['href'] ) ? (string) $attributes['href'] : '';
		$link_target  = isset( $attributes['linkTarget'] ) ? (string) $attributes['linkTarget'] : '';
		$rel          = isset( $attributes['rel'] ) ? (string) $attributes['rel'] : '';

		$post_id = 0;
		if ( isset( $block->context['postId'] ) ) {
			$post_id = (int) $block->context['postId'];
		} elseif ( get_the_ID() ) {
			$post_id = (int) get_the_ID();
		}

		$descriptor = null;
		if ( '' !== $source ) {
			$descriptor = ImageResolver::resolve( $source, $source_args, $post_id, $size );
		}

		// Fallback lookup.
		if ( null === $descriptor ) {
			if ( $fallback_id ) {
				$descriptor = ImageResolver::descriptor_from_id( $fallback_id, $size );
			} elseif ( '' !== $fallback_url ) {
				$descriptor = array(
					'id'     => 0,
					'url'    => $fallback_url,
					'alt'    => $fallback_alt,
					'width'  => 0,
					'height' => 0,
				);
			}
		}

		if ( null === $descriptor || empty( $descriptor['url'] ) ) {
			return '';
		}

		$alt = '' !== $alt_override ? $alt_override : (string) ( $descriptor['alt'] ?? '' );

		$figure_style = array();
		if ( '' !== $aspect_ratio ) {
			$figure_style[] = 'aspect-ratio:' . $aspect_ratio;
		}

		$img_style = array(
			'object-fit:' . $object_fit,
		);
		if ( isset( $focal['x'], $focal['y'] ) ) {
			$img_style[] = sprintf(
				'object-position:%s%% %s%%',
				round( (float) $focal['x'] * 100 ),
				round( (float) $focal['y'] * 100 )
			);
		}

		$wrapper_attrs = get_block_wrapper_attributes(
			array(
				'class' => 'wp-block-designsetgo-dynamic-image',
				'style' => implode( ';', $figure_style ),
			)
		);

		$img_attrs = array(
			'src' => $descriptor['url'],
			'alt' => $alt,
		);
		if ( ! empty( $descriptor['width'] ) ) {
			$img_attrs['width'] = (int) $descriptor['width'];
		}
		if ( ! empty( $descriptor['height'] ) ) {
			$img_attrs['height'] = (int) $descriptor['height'];
		}

		// Let the browser pick a candidate that fits instead of always
		// downloading the chosen size (the default is `full`).
		$attachment_id = (int) $descriptor['id'];
		if ( $attachment_id ) {
			$srcset = wp_get_attachment_image_srcset( $attachment_id, $size );
			$sizes  = wp_get_attachment_image_sizes( $attachment_id, $size );
			if ( $srcset && $sizes ) {
				$img_attrs['srcset'] = $srcset;
				$img_attrs['sizes']  = $sizes;
			}
		}

		// Core decides lazy vs. eager + fetchpriority, so a Dynamic Image used
		// as the hero is not lazy-loaded (which would delay LCP).
		$img_attrs = array_merge(
			$img_attrs,
			wp_get_loading_optimization_attributes( 'img', $img_attrs, 'wp_get_attachment_image' )
		);
		// Core only reasons about images with known dimensions; an external
		// fallback URL has none, so keep the previous lazy default for it.
		if ( empty( $img_attrs['width'] ) && empty( $img_attrs['loading'] ) && empty( $img_attrs['fetchpriority'] ) ) {
			$img_attrs['loading'] = 'lazy';
		}
		if ( empty( $img_attrs['decoding'] ) ) {
			$img_attrs['decoding'] = 'async';
		}
		$img_attrs['style'] = implode( ';', $img_style );

		$img_html = '<img';
		foreach ( $img_attrs as $key => $val ) {
			$val       = 'src' === $key ? esc_url( (string) $val ) : esc_attr( (string) $val );
			$img_html .= ' ' . $key . '="' . $val . '"';
		}
		$img_html .= ' />';

		if ( '' !== $href ) {
			$link_attrs = array(
				'href' => esc_url( $href ),
			);
			if ( '' !== $link_target ) {
				$link_attrs['target'] = esc_attr( $link_target );
			}
			if ( '' !== $rel ) {
				$link_attrs['rel'] = esc_attr( $rel );
			} elseif ( '_blank' === $link_target ) {
				$link_attrs['rel'] = 'noopener noreferrer';
			}
			$link_open = '<a';
			foreach ( $link_attrs as $key => $val ) {
				$link_open .= ' ' . $key . '="' . $val . '"';
			}
			$link_open .= '>';
			$img_html = $link_open . $img_html . '</a>';
		}

		printf(
			'<figure %s>%s</figure>',
			$wrapper_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is pre-escaped.
			$img_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all attribute values above are esc_* escaped.
		);
	}
}

designsetgo_render_dynamic_image( $attributes, $content, $block );

<?php
/**
 * Icon Block - Server-side Rendering
 *
 * Dynamic render. Emits the inline SVG (from the shared PHP icon library) inside
 * the same wrapper structure the block used before it became dynamic, so
 * existing content renders identically — but with no client-side icon injection
 * (no `.dsgo-lazy-icon` placeholder, no data-* attributes).
 *
 * The block root (`.dsgo-icon`) is a plain block-level positioning wrapper —
 * core's constrained layout caps it at the theme's content width, exactly like
 * a paragraph. The visible icon is the inner `.dsgo-icon__wrapper` element,
 * which shrink-wraps and is positioned inside the wrapper via `justify-content`
 * (`.dsgo-justify`, driven by the `justification` attribute). All visual block
 * supports (color, background, border, padding) are routed from the wrapper to
 * that element by the shared `designsetgo_route_visual_supports()` helper —
 * otherwise a background or border would paint across the whole content column
 * instead of hugging the icon.
 *
 * `align` support narrowed from `left|center|right|wide|full` to `wide|full`
 * when `justification` replaced the left/center/right values, but WordPress's
 * `wp_register_alignment_support()` always registers the `align` ATTRIBUTE
 * with the full historical enum regardless of what `supports.align` lists —
 * the block.json subset only trims the editor's toolbar buttons. So a
 * published Icon that has never been re-opened and re-saved (deprecations
 * only run in the editor) can still carry a stored `align: "left"` here.
 * That value is read as a legacy justification fallback below. It is
 * intentionally NOT passed through to `get_block_wrapper_attributes()`'s
 * `class` argument for wide/full handling — `align` stays in `$attributes`
 * (untouched) so WordPress's own alignment-support code (invoked internally
 * by `get_block_wrapper_attributes()`) still emits `alignwide`/`alignfull`
 * for the current, legitimate use of those two values. It ALSO, unavoidably,
 * emits a stale `alignleft`/`aligncenter`/`alignright` class from that same
 * mechanism for un-migrated legacy content. That class is redundant — the
 * legacy justification fallback below already put the correct
 * `dsgo-justify--{left|center|right}` class on this same wrapper — and
 * actively wrong: `margin-left/right: auto` (from core's own alignment CSS)
 * overrides `align-self` in flexbox, so it recentres the wrapper regardless
 * of `justify-content`. It is stripped from the wrapper below rather than
 * neutralized in CSS.
 *
 * @package DesignSetGo
 * @since 2.4.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused for dynamic blocks).
 * @param WP_Block $block      Block instance.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'designsetgo_render_icon' ) ) {
	/**
	 * Render the Icon block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content (unused).
	 * @param WP_Block $block      Block instance.
	 * @return string Icon markup.
	 */
	function designsetgo_render_icon( $attributes, $content, $block ) {
		unset( $content, $block );

		// Theme-level defaults (settings.custom.designsetgo.icon), used when an
		// attribute is left unset — mirrors the frontend injector's behavior.
		$defaults = array();
		if ( class_exists( '\\DesignSetGo\\Icon_Injector' ) ) {
			$defaults = \DesignSetGo\Icon_Injector::get_icon_defaults();
		}
		$default_size  = isset( $defaults['size'] ) ? (int) $defaults['size'] : 48;
		$default_style = isset( $defaults['style'] ) ? (string) $defaults['style'] : 'filled';

		$icon              = ! empty( $attributes['icon'] ) ? (string) $attributes['icon'] : 'star';
		$icon_style        = ! empty( $attributes['iconStyle'] ) ? (string) $attributes['iconStyle'] : $default_style;
		$stroke_width      = isset( $attributes['strokeWidth'] ) ? (float) $attributes['strokeWidth'] : 1.5;
		$has_explicit_size = isset( $attributes['iconSize'] );
		$icon_size         = $has_explicit_size ? (int) $attributes['iconSize'] : $default_size;
		$rotation          = isset( $attributes['rotation'] ) ? (int) $attributes['rotation'] : 0;
		$is_decorative     = ! empty( $attributes['isDecorative'] );
		$aria_label        = isset( $attributes['ariaLabel'] ) ? (string) $attributes['ariaLabel'] : '';

		// Trusted SVG markup from the shared library (filled, or outlined wrapper).
		$svg = designsetgo_render_icon_svg( $icon, $icon_style, $stroke_width );
		if ( '' === $svg ) {
			return '';
		}

		// Wrapper inline styles. Width/height are baked inline only when the author
		// set an explicit iconSize; left unset, sizing falls to the kit-tunable
		// `--wp--custom--designsetgo--icon--default-size` CSS var (see style.scss),
		// mirroring the pre-dynamic lazy save's hasExplicitSize behaviour so a Style
		// Kit / theme.json icon size is not silently overridden by an inline style.
		$wrapper_style = 'display:inline-flex;align-items:center;justify-content:center;border-radius:inherit;';
		if ( $has_explicit_size ) {
			$wrapper_style = sprintf( 'width:%1$dpx;height:%1$dpx;', $icon_size ) . $wrapper_style;
		}
		if ( 0 !== $rotation ) {
			$wrapper_style .= sprintf( 'transform:rotate(%ddeg);', $rotation );
		}

		// ARIA: decorative → hidden from AT; otherwise labelled with the custom
		// label or a humanized icon name (e.g. "circle-check" → "Circle Check").
		if ( $is_decorative ) {
			$aria = ' role="presentation" aria-hidden="true"';
		} else {
			$label = '' !== $aria_label ? $aria_label : ucwords( str_replace( '-', ' ', $icon ) );
			$aria  = sprintf( ' role="img" aria-label="%s"', esc_attr( $label ) );
		}

		$icon_html = sprintf(
			'<div class="dsgo-icon__wrapper" style="%s"%s>%s</div>',
			esc_attr( $wrapper_style ),
			$aria,
			$svg
		);

		// Optional link wrap (mirrors save.js: plain <a>, noopener on _blank).
		$url = ! empty( $attributes['linkUrl'] ) ? esc_url( $attributes['linkUrl'] ) : '';
		if ( '' !== $url ) {
			$target = isset( $attributes['linkTarget'] ) ? (string) $attributes['linkTarget'] : '_self';
			$rel    = isset( $attributes['linkRel'] ) ? (string) $attributes['linkRel'] : '';
			if ( '_blank' === $target && '' === $rel ) {
				$rel = 'noopener noreferrer';
			}
			$rel_attr  = '' !== $rel ? sprintf( ' rel="%s"', esc_attr( $rel ) ) : '';
			$icon_html = sprintf(
				'<a href="%s" target="%s"%s>%s</a>',
				$url,
				esc_attr( $target ),
				$rel_attr,
				$icon_html
			);
		}

		// A stored `align` of left/center/right only ever exists on un-migrated
		// legacy content (the deprecation strips it on first editor re-save,
		// and a fresh icon never writes one) — so ITS presence, not
		// `justification`'s value, is what identifies "not yet migrated"
		// here. `justification` always reads back as a value (its own
		// block.json default fills in when the key is missing), so it cannot
		// be used to detect that. `align: wide|full` is unrelated (still a
		// live, current feature) and is left alone.
		$legacy_align = isset( $attributes['align'] ) ? (string) $attributes['align'] : '';
		$justification = in_array( $legacy_align, array( 'left', 'center', 'right' ), true )
			? $legacy_align
			: ( isset( $attributes['justification'] ) ? (string) $attributes['justification'] : 'center' );
		$justify_class = in_array( $justification, array( 'left', 'center', 'right' ), true )
			? ' dsgo-justify--' . $justification
			: '';

		$wrapper_attributes = get_block_wrapper_attributes(
			array( 'class' => 'dsgo-icon dsgo-justify' . $justify_class )
		);

		$html = sprintf( '<div %s>%s</div>', $wrapper_attributes, $icon_html );

		// Strip the stale alignleft/aligncenter/alignright class WordPress's
		// align support unavoidably adds for un-migrated legacy content (see
		// docblock above) — the `dsgo-justify--{value}` class already
		// positions the wrapper correctly, and the stale class's `margin:
		// auto` fights it. alignwide/alignfull are left untouched: those
		// remain a live, current feature and must keep escaping the column.
		$align_processor = new WP_HTML_Tag_Processor( $html );
		if ( $align_processor->next_tag() ) {
			$align_processor->remove_class( 'alignleft' );
			$align_processor->remove_class( 'aligncenter' );
			$align_processor->remove_class( 'alignright' );
			$html = $align_processor->get_updated_html();
		}

		// The wrapper is now a full-column-width positioning box, so a background,
		// border or padding on it would paint across the column instead of hugging
		// the icon. Move them to the icon box.
		return designsetgo_route_visual_supports(
			$html,
			$attributes,
			'dsgo-icon__wrapper',
			array( 'color', 'border', 'spacing.padding' )
		);
	}
}

// Output directly (WordPress captures echo'd output from render callbacks).
echo designsetgo_render_icon( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

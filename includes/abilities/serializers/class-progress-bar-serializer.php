<?php
/**
 * Serializer for designsetgo/progress-bar.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/progress-bar
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Progress_Bar_Serializer.
 */
class Progress_Bar_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$percentage        = isset( $attributes['percentage'] ) ? Serializer_Support::numeric_attribute( $attributes['percentage'] ) : 75;
		$bar_color         = isset( $attributes['barColor'] ) ? $attributes['barColor'] : '#2563eb';
		$bar_bg_color      = isset( $attributes['barBackgroundColor'] ) ? $attributes['barBackgroundColor'] : '#e5e7eb';
		$height            = isset( $attributes['height'] ) ? $attributes['height'] : '20px';
		$border_radius     = isset( $attributes['borderRadius'] ) ? $attributes['borderRadius'] : '4px';
		$show_percentage   = isset( $attributes['showPercentage'] ) ? $attributes['showPercentage'] : true;
		$label_position    = isset( $attributes['labelPosition'] ) ? $attributes['labelPosition'] : 'top';
		$animate_on_scroll = isset( $attributes['animateOnScroll'] ) ? $attributes['animateOnScroll'] : true;
		$animation_dur     = isset( $attributes['animationDuration'] ) ? floatval( $attributes['animationDuration'] ) : 1.5;

		// Clamp percentage.
		$bar_width = min( max( $percentage, 0 ), 100 );

		// Build classes.
		$class_parts = array( 'wp-block-designsetgo-progress-bar', 'dsgo-progress-bar' );
		if ( $animate_on_scroll ) {
			$class_parts[] = 'dsgo-progress-bar--animate';
		}

		// Data attributes for animation.
		$data_attrs = '';
		if ( $animate_on_scroll ) {
			$data_attrs = ' data-percentage="' . esc_attr( (string) $bar_width ) . '" data-duration="' . esc_attr( (string) $animation_dur ) . '"';
		}

		// Label. save.js joins the label text and the percentage with
		// ' - ', including either only when its own toggle is on. The
		// mirror printed the percentage alone, so a labelled bar lost
		// its label.
		$label_parts = array();
		if ( ! empty( $attributes['showLabel'] ) && ! empty( $attributes['labelText'] ) && is_string( $attributes['labelText'] ) ) {
			$label_parts[] = $attributes['labelText'];
		}
		if ( $show_percentage ) {
			$label_parts[] = $bar_width . '%';
		}
		$label_display = implode( ' - ', $label_parts );

		$label_html = '';
		if ( $show_percentage && 'top' === $label_position ) {
			$label_html = '<div class="dsgo-progress-bar__label dsgo-progress-bar__label--top">' . esc_html( $label_display ) . '</div>';
		}

		// Container styles.
		// save.js writes `backgroundColor: barTrackColor || undefined`, and
		// React omits an undefined style property entirely. Emitting
		// `background-color:` with an empty value produced a declaration
		// save() never writes, so an unstyled progress bar was invalid.
		$container_style = 'width:100%;height:' . esc_attr( $height ) .
			( '' !== $bar_bg_color ? ';background-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $bar_bg_color ) ) : '' ) .
			';border-radius:' . esc_attr( $border_radius ) . ';overflow:hidden;position:relative';

		// Fill styles. When not animating, the width is a CSS custom-property
		// formula (not a literal percentage) so a `dsgoStyleBinding` on
		// `--dsgo-progress` can drive it from the frontend render_block
		// filter — see save.js and progress-bar/deprecated.js's v2 entry.
		// With no binding, `--dsgo-progress`/`--dsgo-progress-max` fall back
		// to the literals baked into the expression, resolving to exactly
		// `$bar_width%`, matching save.js's STATIC_WIDTH_FORMULA byte-for-byte.
		$fill_width = $animate_on_scroll
			? '0%'
			: 'clamp(0%, calc(100% * var(--dsgo-progress, ' . $bar_width . ') / var(--dsgo-progress-max, 100)), 100%)';
		$fill_style = 'width:' . $fill_width . ';height:100%' .
			( '' !== $bar_color ? ';background-color:' . esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $bar_color ) ) : '' ) .
			';transition:width ' . esc_attr( (string) $animation_dur ) . 's ease-out;border-radius:' . esc_attr( $border_radius );

		// Striped fill. save.js appends these two declarations after
		// border-radius for both striped variants; the mirror wrote
		// neither, so a striped bar rendered flat and failed validation.
		$bar_style_value = isset( $attributes['barStyle'] ) ? (string) $attributes['barStyle'] : 'solid';
		if ( 'striped' === $bar_style_value || 'striped-animated' === $bar_style_value ) {
			$fill_style .= ';background-image:linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent);background-size:1rem 1rem';
		}

		// The animated modifier comes from EITHER the striped-animated
		// variant or the standalone stripedAnimation toggle.
		$fill_classes = 'dsgo-progress-bar__fill';
		if ( 'striped-animated' === $bar_style_value || ! empty( $attributes['stripedAnimation'] ) ) {
			$fill_classes .= ' dsgo-progress-bar__fill--animated';
		}

		$inner_html  = $label_html;
		$inner_html .= '<div class="dsgo-progress-bar__container" style="' . esc_attr( $container_style ) . '">';
		$inner_html .= '<div class="' . esc_attr( $fill_classes ) . '" style="' . esc_attr( $fill_style ) . '"></div>';
		$inner_html .= '</div>';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $data_attrs . '>' . $inner_html,
			'closing' => '</div>',
		);
	}
}

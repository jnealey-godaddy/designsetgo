<?php
/**
 * Serializer for designsetgo/text-path.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/text-path
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TextPath_Serializer.
 */
class TextPath_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Mirrors src/blocks/text-path/save.js and its TextPathGraphic
		// component. `pathType: "custom"` is refused by
		// find_invalid_attribute_values() rather than mirrored: it runs
		// caller-supplied path data through a tokenizer whose rules are
		// the security boundary, and a second implementation of that is
		// a liability, not a feature.
		$tp_type       = isset( $attributes['pathType'] ) ? (string) $attributes['pathType'] : 'wave';
		$tp_text       = isset( $attributes['text'] ) ? (string) $attributes['text'] : '';
		$tp_unique_id  = isset( $attributes['uniqueId'] ) && '' !== $attributes['uniqueId'] ? (string) $attributes['uniqueId'] : 'path';
		$tp_path_id    = 'dsgo-text-path-' . $tp_unique_id;
		$tp_alignment  = isset( $attributes['pathAlignment'] ) ? (string) $attributes['pathAlignment'] : 'left';
		$tp_show_path  = ! empty( $attributes['showPath'] );
		$tp_direction  = isset( $attributes['direction'] ) ? (string) $attributes['direction'] : 'ltr';
		$tp_motion     = ! empty( $attributes['motion'] );
		$tp_motion_dir = isset( $attributes['motionDirection'] ) ? (string) $attributes['motionDirection'] : 'forward';

		$tp_rotation     = Serializer_Support::clamp_number( $attributes['rotation'] ?? 0, -360, 360, 0 );
		$tp_opacity      = Serializer_Support::clamp_number( $attributes['guideOpacity'] ?? 0.35, 0, 1, 0.35 );
		$tp_stroke       = Serializer_Support::clamp_number( $attributes['guideStrokeWidth'] ?? 2, 0, 24, 2 );
		$tp_width        = Serializer_Support::clamp_number( $attributes['pathWidth'] ?? 100, 25, 100, 100 );
		$tp_start_offset = Serializer_Support::clamp_number( $attributes['startOffset'] ?? 0, -100, 100, 0 );
		$tp_font_size    = Serializer_Support::clamp_number( $attributes['pathFontSize'] ?? 54, 1, 400, 54 );
		$tp_word_spacing = Serializer_Support::clamp_number( $attributes['wordSpacing'] ?? 0, -40, 100, 0 );
		$tp_padding      = Serializer_Support::clamp_number( $attributes['pathPadding'] ?? 0, -200, 200, 0 );

		$tp_guide_color  = Serializer_Support::safe_text_path_color( $attributes['guideColor'] ?? '' );
		$tp_circle_color = Serializer_Support::safe_text_path_color( $attributes['circleBackgroundColor'] ?? '' );
		$tp_url          = Serializer_Support::safe_text_path_url( $attributes['url'] ?? '' );

		$tp_styles = array(
			'--dsgo-text-path-rotation:' . $tp_rotation . 'deg',
			'--dsgo-text-path-guide-opacity:' . Serializer_Support::format_js_number( (float) $tp_opacity ),
			'--dsgo-text-path-guide-stroke-width:' . Serializer_Support::format_js_number( (float) $tp_stroke ),
			'--dsgo-text-path-width:' . $tp_width . '%',
		);
		if ( '' !== $tp_guide_color ) {
			$tp_styles[] = '--dsgo-text-path-guide-color:' . Serializer_Support::convert_color_value_to_css_var( $tp_guide_color );
		}
		if ( '' !== $tp_circle_color ) {
			$tp_styles[] = '--dsgo-text-path-circle-background:' . Serializer_Support::convert_color_value_to_css_var( $tp_circle_color );
		}

		$class_parts = array( 'wp-block-designsetgo-text-path' );
		if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
			$class_parts[] = 'align' . $attributes['align'];
		}
		$class_parts[] = 'dsgo-text-path';
		if ( 'center' === $tp_alignment || 'right' === $tp_alignment ) {
			$class_parts[] = 'dsgo-text-path--align-' . $tp_alignment;
		}

		$tp_motion_attrs = '';
		if ( $tp_motion ) {
			$tp_duration      = is_numeric( $attributes['motionDuration'] ?? null ) ? (float) $attributes['motionDuration'] : 12;
			$tp_duration      = 0.0 === $tp_duration ? 12 : $tp_duration;
			$tp_duration      = max( 2, min( 120, $tp_duration ) );
			$tp_motion_attrs  = ' data-dsgo-text-path-motion="true"';
			$tp_motion_attrs .= ' data-dsgo-text-path-motion-duration="' . esc_attr( Serializer_Support::format_js_number( (float) $tp_duration ) ) . '"';
			$tp_motion_attrs .= ' data-dsgo-text-path-motion-direction="' . ( 'reverse' === $tp_motion_dir ? 'reverse' : 'forward' ) . '"';
		}

		$tp_path = Serializer_Support::get_text_path_data( $tp_type, $attributes['arcSize'] ?? 100 );

		$tp_svg  = '<svg viewBox="' . esc_attr( $tp_path['viewBox'] ) . '" role="img"';
		$tp_svg .= '' !== $tp_text ? ' aria-label="' . esc_attr( $tp_text ) . '"' : '';
		$tp_svg .= '>';

		if ( 'circle' === $tp_type && '' !== $tp_circle_color ) {
			$tp_svg .= '<circle class="dsgo-text-path__circle-background" cx="500" cy="500" r="500" aria-hidden="true"></circle>';
		}

		$tp_svg .= '<defs><path id="' . esc_attr( $tp_path_id ) . '" d="' . esc_attr( $tp_path['d'] ) . '"></path></defs>';

		if ( $tp_show_path ) {
			$tp_svg .= '<path class="dsgo-text-path__guide" d="' . esc_attr( $tp_path['d'] ) . '"></path>';
		}

		$tp_offset = $tp_start_offset . '%';

		$tp_svg .= '<text direction="' . ( 'rtl' === $tp_direction ? 'rtl' : 'ltr' ) . '"' .
			' style="' . esc_attr( 'font-size:' . $tp_font_size . 'px;word-spacing:' . $tp_word_spacing . 'px' ) . '">' .
			'<textPath href="#' . esc_attr( $tp_path_id ) . '" startOffset="' . esc_attr( $tp_offset ) . '"' .
			' data-dsgo-text-path-offset="' . esc_attr( (string) $tp_start_offset ) . '">';

		$tp_svg .= ( 0 === (int) $tp_padding && is_int( $tp_padding + 0 ) && 0.0 === (float) $tp_padding )
			? esc_html( $tp_text )
			: '<tspan dy="' . esc_attr( (string) $tp_padding ) . '">' . esc_html( $tp_text ) . '</tspan>';

		$tp_svg .= '</textPath></text></svg>';

		if ( '' !== $tp_url ) {
			$tp_target = ! empty( $attributes['target'] ) ? ' target="_blank"' : '';
			$tp_svg    = '<a href="' . esc_url( $tp_url ) . '"' . $tp_target . ' rel="noopener noreferrer">' . $tp_svg . '</a>';
		}

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( implode( ';', $tp_styles ) ) . '"' .
				$tp_motion_attrs . '>' . $tp_svg,
			'closing' => '</div>',
		);
	}
}

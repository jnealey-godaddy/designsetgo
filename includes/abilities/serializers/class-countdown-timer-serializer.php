<?php
/**
 * Serializer for designsetgo/countdown-timer.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/countdown-timer
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CountdownTimer_Serializer.
 */
class CountdownTimer_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$target_datetime    = isset( $attributes['targetDateTime'] ) ? $attributes['targetDateTime'] : '';
		$timezone           = isset( $attributes['timezone'] ) ? $attributes['timezone'] : '';
		$show_days          = isset( $attributes['showDays'] ) ? $attributes['showDays'] : true;
		$show_hours         = isset( $attributes['showHours'] ) ? $attributes['showHours'] : true;
		$show_minutes       = isset( $attributes['showMinutes'] ) ? $attributes['showMinutes'] : true;
		$show_seconds       = isset( $attributes['showSeconds'] ) ? $attributes['showSeconds'] : true;
		$layout             = isset( $attributes['layout'] ) ? $attributes['layout'] : 'boxed';
		$completion_action  = isset( $attributes['completionAction'] ) ? $attributes['completionAction'] : 'message';
		$completion_message = isset( $attributes['completionMessage'] ) ? $attributes['completionMessage'] : 'The countdown has ended!';
		$number_color       = isset( $attributes['numberColor'] ) ? $attributes['numberColor'] : '';
		$label_color        = isset( $attributes['labelColor'] ) ? $attributes['labelColor'] : '';
		$unit_bg_color      = isset( $attributes['unitBackgroundColor'] ) ? $attributes['unitBackgroundColor'] : '';
		$unit_border        = isset( $attributes['unitBorder'] ) ? $attributes['unitBorder'] : array();
		$unit_border_radius = isset( $attributes['unitBorderRadius'] ) ? Serializer_Support::numeric_attribute( $attributes['unitBorderRadius'] ) : 12;
		$unit_gap           = isset( $attributes['unitGap'] ) ? $attributes['unitGap'] : '1rem';
		$unit_padding       = isset( $attributes['unitPadding'] ) ? $attributes['unitPadding'] : '1.5rem';

		// Build unit style.
		$border_color = isset( $unit_border['color'] ) && $unit_border['color'] ? $unit_border['color'] : 'var(--wp--preset--color--accent-2, currentColor)';
		$border_width = isset( $unit_border['width'] ) ? $unit_border['width'] : '2px';
		$border_style = isset( $unit_border['style'] ) ? $unit_border['style'] : 'solid';

		$unit_style_parts = array(
			'background-color:' . ( $unit_bg_color ? esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $unit_bg_color ) ) : 'transparent' ),
			'border-color:' . esc_attr( $border_color ),
			'border-width:' . esc_attr( $border_width ),
			'border-style:' . esc_attr( $border_style ),
			'border-radius:' . $unit_border_radius . 'px',
			'padding:' . esc_attr( $unit_padding ),
		);
		$unit_style       = implode( ';', $unit_style_parts );

		$number_style = 'color:' . ( $number_color ? esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $number_color ) ) : 'var(--wp--preset--color--accent-2, currentColor)' );
		$label_style  = 'color:' . ( $label_color ? esc_attr( Serializer_Support::convert_color_value_to_css_var( (string) $label_color ) ) : 'currentColor' );

		// Build units HTML.
		$units = array();
		if ( $show_days ) {
			$units[] = array(
				'type'  => 'days',
				'label' => 'Days',
			);
		}
		if ( $show_hours ) {
			$units[] = array(
				'type'  => 'hours',
				'label' => 'Hours',
			);
		}
		if ( $show_minutes ) {
			$units[] = array(
				'type'  => 'minutes',
				'label' => 'Min',
			);
		}
		if ( $show_seconds ) {
			$units[] = array(
				'type'  => 'seconds',
				'label' => 'Sec',
			);
		}

		$units_html = '';
		foreach ( $units as $unit ) {
			$units_html .= '<div class="dsgo-countdown-timer__unit" data-unit-type="' . esc_attr( $unit['type'] ) . '" style="' . esc_attr( $unit_style ) . '">';
			$units_html .= '<div class="dsgo-countdown-timer__number" style="' . esc_attr( $number_style ) . '">00</div>';
			$units_html .= '<div class="dsgo-countdown-timer__label" style="' . esc_attr( $label_style ) . '">' . esc_html( $unit['label'] ) . '</div>';
			$units_html .= '</div>';
		}

		// Build data attributes.
		$data_attrs  = ' data-target-datetime="' . esc_attr( $target_datetime ) . '"';
		$data_attrs .= ' data-timezone="' . esc_attr( $timezone ) . '"';
		$data_attrs .= ' data-show-days="' . ( $show_days ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-show-hours="' . ( $show_hours ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-show-minutes="' . ( $show_minutes ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-show-seconds="' . ( $show_seconds ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-completion-action="' . esc_attr( $completion_action ) . '"';
		// completionMessage is sourced from the message div's text (below),
		// not a wrapper attribute — save.js no longer emits
		// data-completion-message, so emitting it here would produce markup
		// save() never generates and fail validation.

		$container_style = 'gap:' . esc_attr( $unit_gap );
		$outer_class     = 'wp-block-designsetgo-countdown-timer dsgo-countdown-timer dsgo-countdown-timer--' . esc_attr( $layout );

		$inner_html = '<div class="dsgo-countdown-timer__units">' . $units_html . '</div>';
		// No inline display:none — style.scss hides this by default (view.js
		// reveals it by setting an inline display:block, which wins either
		// way). save.js stopped serializing it, so emitting it here would
		// produce markup save() never generates and fail validation.
		$inner_html .= '<div class="dsgo-countdown-timer__completion-message">' . esc_html( $completion_message ) . '</div>';

		return array(
			'opening' => '<div class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $container_style ) . '"' . $data_attrs . '>' . $inner_html,
			'closing' => '</div>',
		);
	}
}

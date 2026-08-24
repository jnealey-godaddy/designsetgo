<?php
/**
 * Star Rating — server render.
 *
 * Dynamic on purpose. `rating` and `ratingCount` are opted into the native
 * Block Bindings API (see includes/bindings/class-block-bindings-support.php),
 * and a bound value only reaches the page through `$block->attributes` at
 * render time — a static save() would freeze whatever number the author last
 * typed. That is the whole point of the block: a hard-coded rating is the
 * least interesting version of it.
 *
 * The fractional icon is CSS, not markup: two identical icon rows, the upper
 * one clipped to `--dsgo-star-rating-fill`. So the same markup serves 4, 4.5
 * and 4.3 without a half-icon asset.
 *
 * `.dsgo-star-rating` is a block-level positioning wrapper capped at the
 * content column; the visible element is `.dsgo-star-rating__inner`, which
 * shrink-wraps. Colour, border and padding are routed there by
 * `designsetgo_route_visual_supports()` — left on the wrapper, a background
 * would paint across the whole column instead of hugging the stars. The CSS
 * custom properties therefore go on the INNER element too: that helper
 * rewrites the wrapper's `style` attribute wholesale, so anything set there
 * would be discarded.
 *
 * @package DesignSetGo
 * @since   2.8.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block save content (empty — this block is server-rendered).
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_star_rating_css_color' ) ) {
	/**
	 * Turn a stored colour attribute into a CSS value.
	 *
	 * Preset references keep their `var()` form rather than being resolved to
	 * a hex value, so the block follows a theme's palette when it changes.
	 * Everything else goes through the shared sanitiser, which rejects
	 * `url()`, `expression()` and friends.
	 *
	 * @param string $value Stored attribute value.
	 * @return string CSS colour, or '' when the value is unusable.
	 */
	function designsetgo_star_rating_css_color( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^var:preset\|color\|([A-Za-z0-9_-]+)$/', $value, $matches ) ) {
			return 'var(--wp--preset--color--' . $matches[1] . ')';
		}

		$sanitized = designsetgo_sanitize_css_color( $value );

		return is_string( $sanitized ) ? $sanitized : '';
	}
}

if ( ! function_exists( 'designsetgo_star_rating_icon_row' ) ) {
	/**
	 * One row of icons.
	 *
	 * Track and fill render identical rows — the fill row is clipped by width,
	 * which is what produces a partial icon.
	 *
	 * @param string $class      Row class name.
	 * @param int    $count      Number of icons.
	 * @param string $icon       Icon name from the shared library.
	 * @param string $icon_style 'filled' or 'outlined'.
	 * @return string Row markup. SVG comes from the hardcoded library, so it is
	 *                trusted output.
	 */
	function designsetgo_star_rating_icon_row( $class, $count, $icon, $icon_style ) {
		$svg = designsetgo_render_icon_svg( $icon, $icon_style );

		$stars = str_repeat(
			'<span class="dsgo-star-rating__star">' . $svg . '</span>',
			max( 0, (int) $count )
		);

		return '<span class="' . esc_attr( $class ) . '">' . $stars . '</span>';
	}
}

if ( ! function_exists( 'designsetgo_render_star_rating' ) ) {
	/**
	 * Render the Star Rating block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content (unused).
	 * @param WP_Block $block      Block instance (unused).
	 * @return string Block markup.
	 */
	function designsetgo_render_star_rating( $attributes, $content, $block ) {
		unset( $content, $block );

		$max       = designsetgo_star_rating_clamp_max( isset( $attributes['maxRating'] ) ? $attributes['maxRating'] : 5 );
		$raw       = isset( $attributes['rating'] ) ? $attributes['rating'] : 4.5;
		$rating    = designsetgo_star_rating_clamp( $raw, $max );
		$precision = isset( $attributes['precision'] ) ? (string) $attributes['precision'] : 'half';
		$fill      = designsetgo_star_rating_fill_percent( $raw, $max, $precision );

		$icon       = ! empty( $attributes['icon'] ) ? (string) $attributes['icon'] : 'star';
		$icon_style = ( isset( $attributes['iconStyle'] ) && 'outlined' === $attributes['iconStyle'] ) ? 'outlined' : 'filled';
		$icon_size  = isset( $attributes['iconSize'] ) ? (int) $attributes['iconSize'] : 24;
		$icon_gap   = isset( $attributes['iconGap'] ) ? (int) $attributes['iconGap'] : 4;

		$show_value = ! empty( $attributes['showValue'] );
		$show_max   = ! empty( $attributes['showMax'] );
		$show_count = ! empty( $attributes['showCount'] );
		$count      = isset( $attributes['ratingCount'] ) && is_numeric( $attributes['ratingCount'] )
			? max( 0, (int) $attributes['ratingCount'] )
			: 0;
		$template   = isset( $attributes['countTemplate'] ) ? (string) $attributes['countTemplate'] : '(%s)';

		$justification = isset( $attributes['justification'] ) ? (string) $attributes['justification'] : 'left';
		$justify_class = in_array( $justification, array( 'left', 'center', 'right' ), true )
			? ' dsgo-justify--' . $justification
			: '';

		$rating_color = designsetgo_star_rating_css_color( isset( $attributes['ratingColor'] ) ? $attributes['ratingColor'] : '' );
		$track_color  = designsetgo_star_rating_css_color( isset( $attributes['trackColor'] ) ? $attributes['trackColor'] : '' );

		// Custom properties, not utility classes: the values are author-set
		// numbers and colours, so there is no finite class set to emit.
		$vars = array(
			'--dsgo-star-rating-fill'        => $fill . '%',
			'--dsgo-star-rating-size'        => max( 1, $icon_size ) . 'px',
			'--dsgo-star-rating-gap'         => max( 0, $icon_gap ) . 'px',
			'--dsgo-star-rating-color'       => $rating_color,
			'--dsgo-star-rating-track-color' => $track_color,
		);

		$inline = '';
		foreach ( $vars as $property => $value ) {
			if ( '' !== $value ) {
				$inline .= $property . ':' . $value . ';';
			}
		}

		$parts = array(
			'<span class="dsgo-star-rating__sr-text">'
				. esc_html( designsetgo_star_rating_label( $rating, $max, $show_count ? $count : 0 ) )
				. '</span>',
			'<span class="dsgo-star-rating__stars" aria-hidden="true">'
				. designsetgo_star_rating_icon_row( 'dsgo-star-rating__track', $max, $icon, $icon_style )
				. '<span class="dsgo-star-rating__fill-clip">'
				. designsetgo_star_rating_icon_row( 'dsgo-star-rating__fill', $max, $icon, $icon_style )
				. '</span></span>',
		);

		if ( $show_value ) {
			$value_html = esc_html( designsetgo_star_rating_format_value( $rating ) );

			if ( $show_max ) {
				$value_html .= '<span class="dsgo-star-rating__max">/'
					. esc_html( designsetgo_star_rating_format_value( $max ) )
					. '</span>';
			}

			$parts[] = '<span class="dsgo-star-rating__value" aria-hidden="true">' . $value_html . '</span>';
		}

		if ( $show_count ) {
			$parts[] = '<span class="dsgo-star-rating__count" aria-hidden="true">'
				. esc_html( designsetgo_star_rating_format_count( $template, $count ) )
				. '</span>';
		}

		$wrapper_attributes = get_block_wrapper_attributes(
			array( 'class' => 'dsgo-star-rating dsgo-justify' . $justify_class )
		);

		$html = sprintf(
			'<div %1$s><div class="dsgo-star-rating__inner" style="%2$s">%3$s</div></div>',
			$wrapper_attributes,
			esc_attr( $inline ),
			implode( '', $parts )
		);

		return designsetgo_route_visual_supports(
			$html,
			$attributes,
			'dsgo-star-rating__inner',
			array( 'color', 'border', 'spacing.padding' )
		);
	}
}

// Output directly (WordPress captures echo'd output from render callbacks).
echo designsetgo_render_star_rating( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup assembled above with per-part escaping; SVG comes from the hardcoded icon library.

<?php
/**
 * Star Rating — value math.
 *
 * The PHP half of `src/blocks/star-rating/utils/rating.js`. Three consumers
 * need to agree on these numbers: the editor preview, render.php, and the
 * JSON-LD builder. Keeping them in one place is what stops a rating displayed
 * as 4.5 from being published as 4.4 in structured data.
 *
 * Lives in `includes/` rather than beside the block because the schema builder
 * runs on `wp_head`, long before any block renders — a helper defined inside
 * `src/blocks/star-rating/render.php` would not exist yet.
 *
 * @package DesignSetGo
 * @since   2.8.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_star_rating_clamp_max' ) ) {
	/**
	 * Clamp the icon count to a renderable range.
	 *
	 * The ceiling of ten is not a style choice. A bound source can return
	 * anything, and without it one bad meta value emits thousands of SVGs into
	 * the page. Mirrors MIN_MAX_RATING / MAX_MAX_RATING in
	 * src/blocks/star-rating/utils/rating.js.
	 *
	 * @param mixed $value Requested maximum.
	 * @return int Whole number of icons between 1 and 10.
	 */
	function designsetgo_star_rating_clamp_max( $value ) {
		if ( ! is_numeric( $value ) ) {
			return 5;
		}

		return (int) min( 10, max( 1, round( (float) $value ) ) );
	}
}

if ( ! function_exists( 'designsetgo_star_rating_clamp' ) ) {
	/**
	 * Clamp a rating into the 0..max range.
	 *
	 * A bound value arrives as a string more often than not — WooCommerce
	 * returns '4.00', post meta returns whatever was saved — so numeric
	 * strings are accepted and anything else reads as no rating at all.
	 *
	 * @param mixed $value      Raw rating.
	 * @param int   $max_rating Maximum, already clamped.
	 * @return float Rating within range.
	 */
	function designsetgo_star_rating_clamp( $value, $max_rating ) {
		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}

		return (float) min( (float) $max_rating, max( 0, (float) $value ) );
	}
}

if ( ! function_exists( 'designsetgo_star_rating_snap' ) ) {
	/**
	 * Snap a rating to the configured display precision.
	 *
	 * Only the drawn icons snap. The printed number, and the number handed to
	 * structured data, stay exact: rounding 4.4 up to 4.5 for the icons is a
	 * display convention, while claiming a 4.5 average in JSON-LD when the
	 * source says 4.4 is a false statement to a search engine.
	 *
	 * @param float  $value     Clamped rating.
	 * @param string $precision 'exact', 'half' or 'full'.
	 * @return float Snapped rating.
	 */
	function designsetgo_star_rating_snap( $value, $precision ) {
		if ( 'full' === $precision ) {
			return (float) round( $value );
		}

		if ( 'half' === $precision ) {
			return round( $value * 2 ) / 2;
		}

		return (float) $value;
	}
}

if ( ! function_exists( 'designsetgo_star_rating_fill_percent' ) ) {
	/**
	 * Width of the filled icon row, as a percentage.
	 *
	 * @param mixed  $rating     Raw rating.
	 * @param mixed  $max_rating Raw maximum.
	 * @param string $precision  Display precision.
	 * @return float 0–100, rounded to four decimals.
	 */
	function designsetgo_star_rating_fill_percent( $rating, $max_rating, $precision ) {
		$max     = designsetgo_star_rating_clamp_max( $max_rating );
		$snapped = designsetgo_star_rating_snap(
			designsetgo_star_rating_clamp( $rating, $max ),
			$precision
		);

		return round( ( $snapped / $max ) * 100, 4 );
	}
}

if ( ! function_exists( 'designsetgo_star_rating_format_value' ) ) {
	/**
	 * Format a rating for display.
	 *
	 * Whole numbers lose the decimal ("4", not "4.0"); everything else keeps
	 * one place, which is as much as a row of icons can express.
	 *
	 * @param float $value Rating.
	 * @return string Localised number.
	 */
	function designsetgo_star_rating_format_value( $value ) {
		$rounded = round( (float) $value, 1 );

		return number_format_i18n( $rounded, ( (float) (int) $rounded === $rounded ) ? 0 : 1 );
	}
}

if ( ! function_exists( 'designsetgo_star_rating_format_count' ) ) {
	/**
	 * Apply the author's count template.
	 *
	 * `str_replace()` rather than `sprintf()`: the template is author input,
	 * and a stray `%d` — or the lone `%` in "50% recommend" — would make
	 * sprintf throw a ValueError on PHP 8 and take the page down with it.
	 *
	 * @param string $template Template containing `%s`.
	 * @param int    $count    Rating count.
	 * @return string Rendered text.
	 */
	function designsetgo_star_rating_format_count( $template, $count ) {
		$number = number_format_i18n( (int) $count );

		if ( ! is_string( $template ) || false === strpos( $template, '%s' ) ) {
			return $number;
		}

		return str_replace( '%s', $number, $template );
	}
}

if ( ! function_exists( 'designsetgo_star_rating_label' ) ) {
	/**
	 * The sentence a screen reader gets in place of the icons.
	 *
	 * @param float $value      Clamped rating.
	 * @param int   $max_rating Clamped maximum.
	 * @param int   $count      Rating count, 0 when not shown.
	 * @return string Screen-reader text.
	 */
	function designsetgo_star_rating_label( $value, $max_rating, $count = 0 ) {
		$label = sprintf(
			/* translators: 1: rating value, 2: maximum rating. */
			__( 'Rated %1$s out of %2$s', 'designsetgo' ),
			designsetgo_star_rating_format_value( $value ),
			designsetgo_star_rating_format_value( $max_rating )
		);

		if ( $count <= 0 ) {
			return $label;
		}

		return sprintf(
			/* translators: 1: "Rated 4.5 out of 5", 2: number of ratings. */
			__( '%1$s, based on %2$s ratings', 'designsetgo' ),
			$label,
			number_format_i18n( (int) $count )
		);
	}
}

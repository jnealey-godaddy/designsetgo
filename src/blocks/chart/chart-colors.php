<?php
/**
 * Chart block colour resolution.
 *
 * Turning an author's stored colour — a raw CSS value, a theme preset token,
 * or nothing at all — into something safe to write into an SVG presentation
 * attribute or a style attribute.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_chart_safe_color' ) ) {
	/**
	 * Reject a colour string that could smuggle behaviour into an attribute.
	 *
	 * Charts accept raw CSS colours so authors can use `var()` references, so
	 * the value cannot simply be run through `sanitize_hex_color()`.
	 *
	 * The character allowlist is what actually holds the line. Escaping alone
	 * does not: the legend swatch writes this into `style="background:%s"`, so
	 * a value carrying a semicolon appends whole declarations of its own —
	 * `red;position:fixed;inset:0;z-index:99999` turns a 0.75em swatch into a
	 * full-viewport overlay, which is a clickjacking surface for anyone able to
	 * edit a post. `esc_attr()` does not stop that, because nothing about it is
	 * invalid markup. A colour needs letters, digits, and `#(),.%/_- ` and
	 * nothing else, so everything else goes — including `;`, `{}`, quotes,
	 * backslashes (unicode escapes) and `:` (which alone kills `javascript:`).
	 *
	 * @param string $color Candidate colour.
	 * @return string The colour, or an empty string when unsafe.
	 */
	function designsetgo_chart_safe_color( $color ) {
		$color = trim( sanitize_text_field( (string) $color ) );

		if ( '' === $color ) {
			return '';
		}

		// Presets arrive as `var:preset|color|{slug}` and carry pipes, so they
		// are resolved to real CSS before the allowlist judges the value.
		$color = designsetgo_chart_preset_to_css( $color );

		if ( '' === $color ) {
			return '';
		}

		if ( ! preg_match( '#^[a-zA-Z0-9\#(),.%/_\- ]+$#', $color ) ) {
			return '';
		}

		// Kept as defence in depth: these are spellable within the allowlist.
		$lower = strtolower( $color );

		foreach ( array( 'expression(', 'url(', 'image(', '@import' ) as $needle ) {
			if ( false !== strpos( $lower, $needle ) ) {
				return '';
			}
		}

		return $color;
	}
}

if ( ! function_exists( 'designsetgo_chart_preset_to_css' ) ) {
	/**
	 * Convert WordPress's stored preset format into a CSS value.
	 *
	 * The colour picker stores theme presets as `var:preset|color|{slug}` so a
	 * chart follows the site palette when the theme changes. Custom colours are
	 * stored raw and pass through untouched.
	 *
	 * @param string $color Stored colour value.
	 * @return string CSS colour value.
	 */
	function designsetgo_chart_preset_to_css( $color ) {
		if ( 0 !== strpos( $color, 'var:preset|color|' ) ) {
			return $color;
		}

		$slug = substr( $color, strlen( 'var:preset|color|' ) );
		$slug = preg_replace( '/[^a-zA-Z0-9_-]/', '', $slug );

		return '' === $slug ? '' : 'var(--wp--preset--color--' . $slug . ')';
	}
}

if ( ! function_exists( 'designsetgo_chart_palette' ) ) {
	/**
	 * Resolve the series colours.
	 *
	 * Falls back to theme.json custom properties so charts inherit the site
	 * palette rather than hard-coding brand colours.
	 *
	 * @param array $attributes Block attributes.
	 * @param int   $count      Number of series needed.
	 * @return array List of CSS colour strings.
	 */
	function designsetgo_chart_palette( array $attributes, $count ) {
		$chosen = isset( $attributes['palette'] ) && is_array( $attributes['palette'] )
			? array_values( $attributes['palette'] )
			: array();

		$defaults = array(
			'var(--wp--preset--color--primary, #3858e9)',
			'var(--wp--preset--color--secondary, #4ab866)',
			'var(--wp--preset--color--tertiary, #f0b849)',
			'var(--wp--preset--color--accent, #d94f4f)',
		);

		$out = array();

		for ( $i = 0; $i < $count; $i++ ) {
			// Positional, not cycled: colouring one series must leave the
			// others on their defaults.
			$color = isset( $chosen[ $i ] ) ? designsetgo_chart_safe_color( $chosen[ $i ] ) : '';

			$out[] = '' !== $color ? $color : $defaults[ $i % count( $defaults ) ];
		}

		return $out;
	}
}

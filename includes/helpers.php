<?php
/**
 * Helper Functions
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get block CSS class name.
 *
 * @param string $block_name Block name without namespace.
 * @param string $unique_id  Unique block ID.
 * @return string CSS class name.
 */
function designsetgo_get_block_class( $block_name, $unique_id = '' ) {
	$class = 'dsgo-' . $block_name;

	if ( $unique_id ) {
		$class .= ' dsgo-block-' . $unique_id;
	}

	return $class;
}

/**
 * Generate unique block ID.
 *
 * @return string Unique ID.
 */
function designsetgo_generate_block_id() {
	return 'dsgo-' . wp_generate_uuid4();
}

/**
 * Sanitize CSS value with strict validation.
 *
 * Prevents CSS injection attacks by validating against allowed patterns.
 * Blocks dangerous CSS functions like expression(), url('javascript:...'), etc.
 *
 * @param mixed  $value Value to sanitize.
 * @param string $type  Expected type: 'size', 'color', 'url'. Default 'size'.
 * @return string|null Sanitized value or null if invalid.
 */
function designsetgo_sanitize_css_value( $value, $type = 'size' ) {
	if ( empty( $value ) && '0' !== $value ) {
		return null;
	}

	switch ( $type ) {
		case 'size':
		case 'spacing':
		case 'dimension':
			return designsetgo_sanitize_css_size( $value );

		case 'color':
			return designsetgo_sanitize_css_color( $value );

		case 'url':
			return esc_url( $value );

		default:
			return sanitize_text_field( $value );
	}
}

/**
 * Sanitize CSS size/spacing value.
 *
 * Allows: px, em, rem, %, vh, vw, vmin, vmax
 * Allows: calc(), clamp(), min(), max() with safe content
 * Blocks: expression(), url(), var() with user input
 *
 * @param string $value Size value to sanitize.
 * @return string|null Sanitized size or null if invalid.
 */
function designsetgo_sanitize_css_size( $value ) {
	$value = trim( $value );

	// Allow CSS keywords.
	$keywords = array( 'auto', 'inherit', 'initial', 'unset', 'none', '0' );
	if ( in_array( strtolower( $value ), $keywords, true ) ) {
		return strtolower( $value );
	}

	// Allow simple numeric values with units (e.g., "24px", "1.5rem", "-10px").
	if ( preg_match( '/^-?\d+(\.\d+)?(px|em|rem|%|vh|vw|vmin|vmax)$/i', $value ) ) {
		return $value;
	}

	// Allow CSS math functions with safe values only.
	// Examples: calc(100% - 20px), clamp(1rem, 2vw, 3rem), min(50%, 300px), max(100px, 10vw).
	if ( preg_match( '/^(calc|clamp|min|max)\s*\([\d\s\.,\+\-\*\/\(\)%a-z]+\)$/i', $value ) ) {
		// Additional validation: no dangerous functions inside, and parens must be balanced.
		if ( ! preg_match( '/(expression|url|attr|var)/i', $value ) && substr_count( $value, '(' ) === substr_count( $value, ')' ) ) {
			return $value;
		}
	}

	wp_trigger_error( __FUNCTION__, sprintf( 'DesignSetGo: Invalid CSS size value rejected: %s', $value ), E_USER_NOTICE );

	return null;
}

/**
 * Sanitize CSS color value.
 *
 * Allows: hex (#fff, #ffffff), rgb(), rgba(), hsl(), hsla(), named colors, CSS custom properties
 * Blocks: url(), expression(), inline scripts
 *
 * @param string $value Color value to sanitize.
 * @return string|null Sanitized color or null if invalid.
 */
function designsetgo_sanitize_css_color( $value ) {
	$value = trim( $value );

	// Allow hex colors (#fff, #ffffff, #ffffffff).
	if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
		return strtolower( $value );
	}

	// Allow rgb/rgba (e.g., "rgb(255, 0, 0)", "rgba(255, 0, 0, 0.5)").
	if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*[\d\.]+\s*)?\)$/i', $value ) ) {
		return $value;
	}

	// Allow hsl/hsla (e.g., "hsl(120, 100%, 50%)", "hsla(120, 100%, 50%, 0.5)").
	if ( preg_match( '/^hsla?\(\s*\d+\s*,\s*\d+%\s*,\s*\d+%\s*(,\s*[\d\.]+\s*)?\)$/i', $value ) ) {
		return $value;
	}

	// Allow CSS custom properties for theme integration (e.g., "var(--wp--preset--color--primary)").
	// Only allow WordPress-style custom properties (--wp--*, --dsgo--*).
	if ( preg_match( '/^var\(\s*--(wp|dsgo|dsg)--[\w\-]+\s*\)$/i', $value ) ) {
		return $value;
	}

	// Allow standard CSS named colors.
	$named_colors = array(
		'transparent',
		'currentcolor',
		'black',
		'white',
		'red',
		'green',
		'blue',
		'yellow',
		'orange',
		'purple',
		'pink',
		'brown',
		'gray',
		'grey',
		'silver',
		'navy',
		'teal',
		'aqua',
		'lime',
		'maroon',
		'olive',
		'fuchsia',
	);

	if ( in_array( strtolower( $value ), $named_colors, true ) ) {
		return strtolower( $value );
	}

	wp_trigger_error( __FUNCTION__, sprintf( 'DesignSetGo: Invalid CSS color value rejected: %s', $value ), E_USER_NOTICE );

	return null;
}

/**
 * Check if block is DesignSetGo block.
 *
 * @param string $block_name Block name.
 * @return bool
 */
function designsetgo_is_dsg_block( $block_name ) {
	return strpos( $block_name, 'designsetgo/' ) === 0;
}

/**
 * Resolve a WordPress preset color reference to its concrete value.
 *
 * Color controls store theme palette selections as the WordPress preset
 * shorthand "var:preset|color|{slug}" (or the legacy CSS-variable form
 * "var(--wp--preset--color--{slug})") so the block stays in sync with theme
 * color changes. Some consumers — SVG data URIs, JavaScript map markers, or
 * any context that cannot inherit the page's CSS custom properties — need the
 * actual color value instead. This looks up the slug in the global settings
 * palette and returns the resolved color, leaving raw values untouched.
 *
 * When the value is a preset reference but the slug is not present in the
 * current palette — e.g. content authored under a theme whose palette a later
 * theme dropped — the token cannot be used as a CSS color. Pass $fallback to
 * substitute a safe default in that case; when omitted, the original token is
 * returned unchanged (matching WordPress' own pass-through behavior).
 *
 * @param mixed       $color    Color value, possibly a preset reference.
 *                              Non-string / empty input is returned as-is.
 * @param string|null $fallback Value returned when the color is a preset
 *                              reference whose slug is not found in the
 *                              palette. Default null (return the original).
 * @return mixed Resolved color string, the $fallback for an unresolvable
 *               preset, or the original value (which may be null / non-string)
 *               when it is not a preset reference.
 */
function designsetgo_resolve_preset_color( $color, $fallback = null ) {
	if ( ! is_string( $color ) || '' === $color ) {
		return $color;
	}

	// Accept both the block-attribute shorthand var:preset|color|{slug} and the
	// CSS-var form var(--wp--preset--color--{slug}).
	if ( ! preg_match( '/^var:preset\|color\|(.+)$/', $color, $matches )
		&& ! preg_match( '/^var\(--wp--preset--color--(.+)\)$/', $color, $matches ) ) {
		return $color;
	}

	$slug    = $matches[1];
	$palette = function_exists( 'wp_get_global_settings' )
		? wp_get_global_settings( array( 'color', 'palette' ) )
		: null;

	if ( is_array( $palette ) ) {
		// Palette is grouped by origin (theme, default, custom).
		foreach ( $palette as $origin_colors ) {
			if ( ! is_array( $origin_colors ) ) {
				continue;
			}
			foreach ( $origin_colors as $entry ) {
				if ( isset( $entry['slug'], $entry['color'] ) && is_string( $entry['color'] ) && $entry['slug'] === $slug ) {
					return $entry['color'];
				}
			}
		}
	}

	// Preset reference that could not be resolved to a palette color.
	return null === $fallback ? $color : $fallback;
}

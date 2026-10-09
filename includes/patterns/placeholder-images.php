<?php
/**
 * Pattern Placeholder Images
 *
 * Provides local placeholder image URLs for block patterns,
 * avoiding reliance on third-party image services.
 *
 * @package DesignSetGo
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get a local placeholder image URL for use in block patterns.
 *
 * @param string $type One of: avatar, landscape, landscape-wide, portrait, square, gallery, logo.
 * @return string Full URL to the local placeholder image.
 */
function designsetgo_get_pattern_placeholder( $type = 'landscape' ) {
	$map  = designsetgo_get_placeholder_map();
	$file = isset( $map[ $type ] ) ? $map[ $type ] : $map['landscape'];

	return esc_url( DESIGNSETGO_URL . 'assets/images/patterns/' . $file );
}

/**
 * Get the placeholder filename map.
 *
 * @return array<string, string> Type => filename.
 */
function designsetgo_get_placeholder_map() {
	return array(
		'avatar'         => 'placeholder-avatar.jpg',
		'landscape'      => 'placeholder-landscape.jpg',
		'landscape-wide' => 'placeholder-landscape-wide.jpg',
		'portrait'       => 'placeholder-portrait.jpg',
		'square'         => 'placeholder-square.jpg',
		'gallery'        => 'placeholder-gallery.jpg',
		'logo'           => 'placeholder-logo.svg',
	);
}

/**
 * Get the target date for countdown timers in bundled patterns.
 *
 * A hardcoded date expires, and an inserted pattern then opens on "The
 * countdown has ended!". Resolving the date at registration keeps every
 * countdown pattern live. Midnight UTC keeps the value stable for a whole
 * day, so pattern content doesn't change on every request.
 *
 * @param int|null $now Unix timestamp to count from. Defaults to the current time.
 * @return string ISO 8601 UTC datetime 30 days ahead, in the countdown block's format.
 */
function designsetgo_get_pattern_countdown_target( $now = null ) {
	$now = null === $now ? time() : (int) $now;

	return gmdate( 'Y-m-d\T00:00:00.000\Z', $now + 30 * DAY_IN_SECONDS );
}

/**
 * Replace placeholder tokens in pattern content with real values.
 *
 * Tokens use the format: {{dsgo:placeholder-type}}
 * e.g. {{dsgo:placeholder-avatar}}, {{dsgo:placeholder-landscape}}
 *
 * {{dsgo:countdown-target}} becomes a datetime 30 days ahead, for countdown
 * timers. Use it for both the `targetDateTime` attribute and the saved
 * `data-target-datetime` so the block stays valid.
 *
 * @param string $content Pattern content string.
 * @return string Content with tokens replaced.
 */
function designsetgo_replace_pattern_placeholders( $content ) {
	$base_url = DESIGNSETGO_URL . 'assets/images/patterns/';
	$map      = designsetgo_get_placeholder_map();

	$replacements = array();
	foreach ( $map as $type => $file ) {
		$replacements[ '{{dsgo:placeholder-' . $type . '}}' ] = esc_url( $base_url . $file );
	}

	$replacements['{{dsgo:countdown-target}}'] = designsetgo_get_pattern_countdown_target();

	return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
}

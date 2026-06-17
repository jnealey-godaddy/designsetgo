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
 * Replace placeholder tokens in pattern content with real URLs.
 *
 * Tokens use the format: {{dsgo:placeholder-type}}
 * e.g. {{dsgo:placeholder-avatar}}, {{dsgo:placeholder-landscape}}
 *
 * @param string $content Pattern content string.
 * @return string Content with tokens replaced by local image URLs.
 */
function designsetgo_replace_pattern_placeholders( $content ) {
	$base_url = DESIGNSETGO_URL . 'assets/images/patterns/';
	$map      = designsetgo_get_placeholder_map();

	$replacements = array();
	foreach ( $map as $type => $file ) {
		$replacements[ '{{dsgo:placeholder-' . $type . '}}' ] = esc_url( $base_url . $file );
	}

	return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
}

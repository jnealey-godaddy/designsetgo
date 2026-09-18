<?php
/**
 * Serializer for designsetgo/map.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/map
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map_Serializer.
 */
class Map_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		$provider    = isset( $attributes['dsgoProvider'] ) ? $attributes['dsgoProvider'] : 'openstreetmap';
		$latitude    = isset( $attributes['dsgoLatitude'] ) ? floatval( $attributes['dsgoLatitude'] ) : 40.7128;
		$longitude   = isset( $attributes['dsgoLongitude'] ) ? floatval( $attributes['dsgoLongitude'] ) : -74.006;
		$zoom        = isset( $attributes['dsgoZoom'] ) ? Serializer_Support::numeric_attribute( $attributes['dsgoZoom'] ) : 13;
		$address     = isset( $attributes['dsgoAddress'] ) ? $attributes['dsgoAddress'] : '';
		$marker_icon = isset( $attributes['dsgoMarkerIcon'] ) ? $attributes['dsgoMarkerIcon'] : '📍';
		// Treat an unset OR explicitly-cleared ('') marker color as the
		// block default, mirroring render.php — the editor now stores ''
		// on clear, and the resolver short-circuits empty strings before
		// consulting its own fallback.
		$marker_color = ( isset( $attributes['dsgoMarkerColor'] ) && '' !== $attributes['dsgoMarkerColor'] )
			? $attributes['dsgoMarkerColor']
			: '#e74c3c';
		// Resolve theme palette presets (var:preset|color|{slug}) to a
		// concrete color; the marker is drawn by view.js, which cannot
		// inherit the page's CSS custom properties. Fall back to the block
		// default when the preset's slug is missing from the palette.
		$marker_color       = designsetgo_resolve_preset_color( $marker_color, '#e74c3c' );
		$height             = isset( $attributes['dsgoHeight'] ) ? $attributes['dsgoHeight'] : '400px';
		$aspect_ratio       = isset( $attributes['dsgoAspectRatio'] ) ? $attributes['dsgoAspectRatio'] : 'custom';
		$privacy_mode       = isset( $attributes['dsgoPrivacyMode'] ) ? $attributes['dsgoPrivacyMode'] : false;
		$has_privacy_notice = array_key_exists( 'dsgoPrivacyNotice', $attributes );
		$privacy_notice     = $attributes['dsgoPrivacyNotice'] ?? __( 'This map will load content from external services. Click to load and view the map.', 'designsetgo' );
		$map_style          = isset( $attributes['dsgoMapStyle'] ) ? $attributes['dsgoMapStyle'] : 'standard';

		// Clamp coordinates.
		$safe_lat  = max( -90, min( 90, $latitude ) );
		$safe_lng  = max( -180, min( 180, $longitude ) );
		$safe_zoom = max( 1, min( 20, $zoom ) );

		// Build classes.
		$class_parts = array( 'wp-block-designsetgo-map', 'dsgo-map' );
		if ( $privacy_mode ) {
			$class_parts[] = 'dsgo-map--privacy-mode';
		}
		if ( 'custom' !== $aspect_ratio ) {
			$class_parts[] = 'dsgo-map--aspect-' . str_replace( ':', '-', $aspect_ratio );
		}

		// Style.
		$style = '';
		if ( 'custom' === $aspect_ratio ) {
			$style = 'height:' . esc_attr( $height );
		}

		// Data attributes.
		$data_attrs  = ' data-dsgo-provider="' . esc_attr( $provider ) . '"';
		$data_attrs .= ' data-dsgo-lat="' . esc_attr( (string) $safe_lat ) . '"';
		$data_attrs .= ' data-dsgo-lng="' . esc_attr( (string) $safe_lng ) . '"';
		$data_attrs .= ' data-dsgo-zoom="' . esc_attr( (string) $safe_zoom ) . '"';
		$data_attrs .= ' data-dsgo-address="' . esc_attr( $address ) . '"';
		$data_attrs .= ' data-dsgo-marker-icon="' . esc_attr( $marker_icon ) . '"';
		$data_attrs .= ' data-dsgo-marker-color="' . esc_attr( $marker_color ) . '"';
		$data_attrs .= ' data-dsgo-privacy-mode="' . ( $privacy_mode ? 'true' : 'false' ) . '"';
		$data_attrs .= ' data-dsgo-map-style="' . esc_attr( $map_style ) . '"';

		// Inner HTML.
		$aria_label = $address
			? sprintf(
				/* translators: %s: The address being shown on the map */
				__( 'Map showing %s', 'designsetgo' ),
				$address
			)
			: __( 'Interactive map', 'designsetgo' );

		if ( $privacy_mode ) {
			$privacy_text = ( $has_privacy_notice && '' === $privacy_notice )
				? __( 'Click to load map', 'designsetgo' )
				: $privacy_notice;

			$inner_html  = '<div class="dsgo-map__privacy-overlay"><div class="dsgo-map__privacy-content">';
			$inner_html .= '<svg class="dsgo-map__privacy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
			$inner_html .= '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>';
			$inner_html .= '<circle cx="12" cy="10" r="3"></circle>';
			$inner_html .= '</svg>';
			$inner_html .= '<p class="dsgo-map__privacy-text">' . esc_html( $privacy_text ) . '</p>';
			$inner_html .= '<button class="dsgo-map__load-button" type="button" aria-label="' . esc_attr__( 'Load map. This will connect to external map services.', 'designsetgo' ) . '">';
			$inner_html .= esc_html__( 'Load Map', 'designsetgo' );
			$inner_html .= '</button></div></div>';
		} else {
			$inner_html = '<div class="dsgo-map__container" role="region" aria-label="' . esc_attr( $aria_label ) . '"></div>';
		}

		$style_attr = $style ? ' style="' . esc_attr( $style ) . '"' : '';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $style_attr . $data_attrs . '>' . $inner_html,
			'closing' => '</div>',
		);
	}
}

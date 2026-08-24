<?php
/**
 * Map Block - server-side render.
 *
 * Dynamic block: save() returns null so the map wrapper (and its data-* config
 * for view.js) is produced here at render time. Two wins over the old static
 * save:
 *
 * 1. Nothing is stored to diff against, so a pattern (or the site-designer-api)
 *    can omit or change any value — e.g. the marker colour — without tripping
 *    block validation.
 * 2. The marker colour resolves per-kit: explicit attribute → theme.json
 *    settings.custom.designsetgo.map.markerColor → #e74c3c default, with a
 *    designsetgo_map_marker_color filter for programmatic control.
 *
 * @package DesignSetGo
 * @since 2.5.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused for dynamic blocks).
 * @param WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'designsetgo_map_privacy_overlay' ) ) {
	/**
	 * Builds the click-to-load overlay shared by every map provider.
	 *
	 * @param string $notice Author-supplied notice text.
	 * @return string Overlay markup.
	 */
	function designsetgo_map_privacy_overlay( $notice ) {
		// Fallback mirrors the block.json default so render.php stays self-consistent
		// even if it is ever invoked with attributes that weren't merged with defaults.
		$text = '' !== $notice
			? $notice
			: __( 'This map will load content from external services. Click to load and view the map.', 'designsetgo' );

		$html  = '<div class="dsgo-map__privacy-overlay"><div class="dsgo-map__privacy-content">';
		$html .= '<svg class="dsgo-map__privacy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>';
		$html .= '<p class="dsgo-map__privacy-text">' . esc_html( $text ) . '</p>';
		$html .= '<button class="dsgo-map__load-button" type="button" aria-label="' . esc_attr__( 'Load map. This will connect to external map services.', 'designsetgo' ) . '">' . esc_html__( 'Load Map', 'designsetgo' ) . '</button>';
		$html .= '</div></div>';

		return $html;
	}
}

if ( ! function_exists( 'designsetgo_render_map' ) ) {
	/**
	 * Renders the Map block on the front end.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content (unused).
	 * @param WP_Block $block      Block instance.
	 */
	function designsetgo_render_map( $attributes, $content, $block ) {
		$provider     = isset( $attributes['dsgoProvider'] ) ? (string) $attributes['dsgoProvider'] : 'openstreetmap';
		$latitude     = isset( $attributes['dsgoLatitude'] ) ? (float) $attributes['dsgoLatitude'] : 40.7128;
		$longitude    = isset( $attributes['dsgoLongitude'] ) ? (float) $attributes['dsgoLongitude'] : -74.006;
		$zoom         = isset( $attributes['dsgoZoom'] ) ? (int) $attributes['dsgoZoom'] : 13;
		$address      = isset( $attributes['dsgoAddress'] ) ? (string) $attributes['dsgoAddress'] : '';
		$marker_icon  = isset( $attributes['dsgoMarkerIcon'] ) && '' !== $attributes['dsgoMarkerIcon'] ? (string) $attributes['dsgoMarkerIcon'] : '📍';
		$height       = isset( $attributes['dsgoHeight'] ) ? (string) $attributes['dsgoHeight'] : '400px';
		$aspect_ratio = isset( $attributes['dsgoAspectRatio'] ) ? (string) $attributes['dsgoAspectRatio'] : 'custom';
		$privacy_mode = ! empty( $attributes['dsgoPrivacyMode'] );
		$privacy_note = isset( $attributes['dsgoPrivacyNotice'] ) ? (string) $attributes['dsgoPrivacyNotice'] : '';
		$map_style    = isset( $attributes['dsgoMapStyle'] ) ? (string) $attributes['dsgoMapStyle'] : 'standard';

		// Marker colour: explicit attribute → kit theme.json custom → default, then filter.
		$marker_color = '';
		if ( isset( $attributes['dsgoMarkerColor'] ) && '' !== $attributes['dsgoMarkerColor'] ) {
			$marker_color = (string) $attributes['dsgoMarkerColor'];
		} elseif ( function_exists( 'wp_get_global_settings' ) ) {
			$kit_color = wp_get_global_settings( array( 'custom', 'designsetgo', 'map', 'markerColor' ) );
			if ( is_string( $kit_color ) && '' !== $kit_color ) {
				$marker_color = $kit_color;
			}
		}
		if ( '' === $marker_color ) {
			$marker_color = '#e74c3c';
		}
		/**
		 * Filter the resolved map marker colour before it is written to the wrapper.
		 *
		 * @param string   $marker_color Resolved colour (CSS colour string).
		 * @param array    $attributes   Block attributes.
		 * @param WP_Block $block        Block instance.
		 */
		$marker_color = (string) apply_filters( 'designsetgo_map_marker_color', $marker_color, $attributes, $block );
		// The color control stores theme palette selections as the preset shorthand
		// "var:preset|color|{slug}" so the block tracks theme changes. Resolve it to a
		// concrete value here: the marker is drawn by view.js, which cannot inherit the
		// page's CSS custom properties. Fall back to the block default when the preset
		// slug is missing from the active palette.
		$marker_color = designsetgo_resolve_preset_color( $marker_color, '#e74c3c' );

		// Clamp coordinates / zoom to valid ranges (mirrors save.js).
		$safe_lat  = max( -90, min( 90, $latitude ) );
		$safe_lng  = max( -180, min( 180, $longitude ) );
		$safe_zoom = max( 1, min( 20, $zoom ) );

		// Wrapper classes — must mirror edit.js / the prior save().
		$classes = 'dsgo-map';
		if ( $privacy_mode ) {
			$classes .= ' dsgo-map--privacy-mode';
		}
		if ( 'custom' !== $aspect_ratio ) {
			$classes .= ' dsgo-map--aspect-' . str_replace( ':', '-', $aspect_ratio );
		}

		// Height is only inline when the author picked a custom aspect ratio.
		$wrapper_args = array( 'class' => $classes );
		if ( 'custom' === $aspect_ratio ) {
			$wrapper_args['style'] = 'height:' . $height;
		}
		$wrapper_attributes = get_block_wrapper_attributes( $wrapper_args );

		// Config consumed by view.js (kept identical to the prior static output).
		$data_attributes = array(
			'data-dsgo-provider'     => $provider,
			'data-dsgo-lat'          => (string) $safe_lat,
			'data-dsgo-lng'          => (string) $safe_lng,
			'data-dsgo-zoom'         => (string) $safe_zoom,
			'data-dsgo-address'      => $address,
			'data-dsgo-marker-icon'  => $marker_icon,
			'data-dsgo-marker-color' => $marker_color,
			'data-dsgo-privacy-mode' => $privacy_mode ? 'true' : 'false',
			'data-dsgo-map-style'    => $map_style,
		);
		$data_attr_html  = '';
		foreach ( $data_attributes as $key => $value ) {
			$data_attr_html .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}

		$aria_label = '' !== $address
			/* translators: %s: The address being shown on the map */
			? sprintf( __( 'Map showing %s', 'designsetgo' ), $address )
			: __( 'Interactive map', 'designsetgo' );

		// The keyless Google provider is a plain iframe built server-side: no
		// Maps JS API, no Leaflet, and no geocoding round-trip, so outside of
		// privacy mode the block needs no JavaScript at all. The marker icon /
		// colour and map style attributes have no effect here — Google owns the
		// rendering — which is why the inspector hides those controls.
		if ( 'googlemaps-embed' === $provider ) {
			$embed_url = designsetgo_map_embed_url( $address, $safe_lat, $safe_lng, $safe_zoom );

			// In privacy mode the URL is parked until the visitor asks for it,
			// so no request reaches Google on page load.
			$src_attribute = $privacy_mode
				? 'data-dsgo-src="' . esc_url( $embed_url ) . '"'
				: 'src="' . esc_url( $embed_url ) . '"';

			$inner = '<iframe class="dsgo-map__iframe" ' . $src_attribute
				. ' title="' . esc_attr( $aria_label ) . '"'
				. ' loading="lazy" referrerpolicy="no-referrer-when-downgrade"'
				. ' allowfullscreen></iframe>';

			if ( $privacy_mode ) {
				$inner .= designsetgo_map_privacy_overlay( $privacy_note );
			}

			echo '<div ' . $wrapper_attributes . $data_attr_html . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		if ( $privacy_mode ) {
			$inner = designsetgo_map_privacy_overlay( $privacy_note );
		} else {
			$inner = '<div class="dsgo-map__container" role="region" aria-label="' . esc_attr( $aria_label ) . '"></div>';
		}

		echo '<div ' . $wrapper_attributes . $data_attr_html . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

designsetgo_render_map( $attributes, $content, $block );

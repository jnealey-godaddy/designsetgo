<?php
/**
 * Dynamic Query — Facet Registry.
 *
 * Tracks which facets are indexed and how to resolve their values.
 * Persisted to the WP options table; extensible via filter.
 *
 * @package DesignSetGo
 * @since 2.2.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * In-memory + option-backed registry of Dynamic Query facets.
 */
class FacetRegistry {

	const OPTION = 'dsgo_query_facets';

	public static function register( string $key, array $config ): void {
		$facets = get_option( self::OPTION, array() );
		if ( ! is_array( $facets ) ) {
			$facets = array();
		}

		$sanitized_key = sanitize_key( $key );
		if ( '' === $sanitized_key ) {
			return;
		}

		$facets[ $sanitized_key ] = array(
			'type'   => sanitize_key( $config['type'] ?? '' ),
			'source' => sanitize_text_field( $config['source'] ?? '' ),
			'label'  => sanitize_text_field( $config['label'] ?? $key ),
		);

		update_option( self::OPTION, $facets, false );
	}

	public static function unregister( string $key ): void {
		$facets = get_option( self::OPTION, array() );
		if ( ! is_array( $facets ) ) {
			return;
		}

		$sanitized_key = sanitize_key( $key );
		unset( $facets[ $sanitized_key ] );

		update_option( self::OPTION, $facets, false );
	}

	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		/**
		 * Filter the registered facets.
		 *
		 * @since 2.2.0
		 *
		 * @param array $stored Keyed array of facet configs (key => { type, source, label }).
		 */
		return (array) apply_filters( 'designsetgo_query_registered_facets', $stored );
	}

	public static function get( string $key ): ?array {
		$all           = self::all();
		$sanitized_key = sanitize_key( $key );
		return $all[ $sanitized_key ] ?? null;
	}
}

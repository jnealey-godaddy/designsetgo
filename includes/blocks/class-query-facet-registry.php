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

	/**
	 * Registers or updates a facet in the option-backed registry.
	 *
	 * @param string $key    Facet registry key (e.g. 'category'). Will be sanitized.
	 * @param array  $config Facet config array with keys: type, source, label.
	 * @return void
	 */
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

	/**
	 * Removes a facet from the option-backed registry.
	 *
	 * @param string $key The facet key to remove.
	 * @return void
	 */
	public static function unregister( string $key ): void {
		$facets = get_option( self::OPTION, array() );
		if ( ! is_array( $facets ) ) {
			return;
		}

		$sanitized_key = sanitize_key( $key );
		if ( ! array_key_exists( $sanitized_key, $facets ) ) {
			return;
		}
		unset( $facets[ $sanitized_key ] );

		update_option( self::OPTION, $facets, false );
	}

	/**
	 * Returns all registered facets, merged with any registered via filter.
	 *
	 * @return array Keyed array of facet configs (key => { type, source, label }).
	 */
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

	/**
	 * Returns the config for a single registered facet, or null if not found.
	 *
	 * @param string $key Facet registry key.
	 * @return array|null Facet config array or null if the key is not registered.
	 */
	public static function get( string $key ): ?array {
		$all           = self::all();
		$sanitized_key = sanitize_key( $key );
		return $all[ $sanitized_key ] ?? null;
	}
}

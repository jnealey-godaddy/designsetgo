<?php
/**
 * Dynamic Query — Filter Registry.
 *
 * Tracks which filters are indexed and how to resolve their values.
 * Persisted to the WP options table; extensible via filter.
 *
 * @package DesignSetGo
 * @since 2.2.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * In-memory + option-backed registry of Dynamic Query filters.
 */
class FilterRegistry {

	const OPTION = 'dsgo_query_filters';

	/**
	 * Registers or updates a filter in the option-backed registry.
	 *
	 * @param string $key    Filter registry key (e.g. 'category'). Will be sanitized.
	 * @param array  $config Filter config array with keys: type, source, label.
	 * @return void
	 */
	public static function register( string $key, array $config ): void {
		$filters = get_option( self::OPTION, array() );
		if ( ! is_array( $filters ) ) {
			$filters = array();
		}

		$sanitized_key = sanitize_key( $key );
		if ( '' === $sanitized_key ) {
			return;
		}

		$filters[ $sanitized_key ] = array(
			'type'   => sanitize_key( $config['type'] ?? '' ),
			'source' => sanitize_text_field( $config['source'] ?? '' ),
			'label'  => sanitize_text_field( $config['label'] ?? $key ),
		);

		update_option( self::OPTION, $filters, false );
	}

	/**
	 * Removes a filter from the option-backed registry.
	 *
	 * @param string $key The filter key to remove.
	 * @return void
	 */
	public static function unregister( string $key ): void {
		$filters = get_option( self::OPTION, array() );
		if ( ! is_array( $filters ) ) {
			return;
		}

		$sanitized_key = sanitize_key( $key );
		if ( ! array_key_exists( $sanitized_key, $filters ) ) {
			return;
		}
		unset( $filters[ $sanitized_key ] );

		update_option( self::OPTION, $filters, false );
	}

	/**
	 * Returns all registered filters, merged with any registered via filter.
	 *
	 * @return array Keyed array of filter configs (key => { type, source, label }).
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		/**
		 * Filter the registered filters.
		 *
		 * @since 2.2.0
		 *
		 * @param array $stored Keyed array of filter configs (key => { type, source, label }).
		 */
		return (array) apply_filters( 'designsetgo_query_registered_filters', $stored );
	}

	/**
	 * Returns the config for a single registered filter, or null if not found.
	 *
	 * @param string $key Filter registry key.
	 * @return array|null Filter config array or null if the key is not registered.
	 */
	public static function get( string $key ): ?array {
		$all           = self::all();
		$sanitized_key = sanitize_key( $key );
		return $all[ $sanitized_key ] ?? null;
	}
}

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
	 * Per-request cache of the resolved registry. Bust on any mutation.
	 * Post-save meta/term hooks call all() once per tracked key, so this
	 * avoids re-running the filter chain 30+ times on a single save.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Whether option-mutation hooks have been registered. Guarded so calls
	 * from multiple request paths (admin + REST + CLI) stay idempotent.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * Wires option-update/delete hooks so direct option mutations (e.g. from
	 * tests or third-party code that bypasses register()/unregister()) still
	 * bust the per-request cache. Called lazily from all() + register().
	 *
	 * @return void
	 */
	private static function ensure_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}
		self::$hooks_registered = true;
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'bust_cache' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'bust_cache' ) );
		add_action( 'delete_option_' . self::OPTION, array( __CLASS__, 'bust_cache' ) );
	}

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

		$is_new = ! isset( $filters[ $sanitized_key ] );

		$filters[ $sanitized_key ] = array(
			'type'   => sanitize_key( $config['type'] ?? '' ),
			'source' => sanitize_text_field( $config['source'] ?? '' ),
			'label'  => sanitize_text_field( $config['label'] ?? $key ),
		);

		update_option( self::OPTION, $filters, false );
		self::$cache = null;

		// First-time registration: queue a background backfill of index rows
		// for all existing posts. Without this, posts that predate the filter
		// block (common when authors add the block to an already-populated
		// site) show "(0)" next to terms they legitimately belong to because
		// the save_post hooks only cover posts updated AFTER the filter was
		// registered.
		//
		// Queued (not inline) because `rebuild_filter` iterates the entire
		// posts table — on a large site that would stall the post-save
		// request that triggered registration and could time out. WP-Cron
		// runs the backfill on the next request instead. The hook is handled
		// by FilterIndexHooks::on_filter_registered below.
		if ( $is_new ) {
			/**
			 * Fires when a new filter key is added to the registry.
			 *
			 * @param string $filter_key The sanitized filter key that was just registered.
			 */
			do_action( 'designsetgo_query_filter_registered', $sanitized_key );
		}
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
		self::$cache = null;
	}

	/**
	 * Returns all registered filters, merged with any registered via filter.
	 *
	 * @return array Keyed array of filter configs (key => { type, source, label }).
	 */
	public static function all(): array {
		self::ensure_hooks();

		if ( null !== self::$cache ) {
			return self::$cache;
		}

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
		self::$cache = (array) apply_filters( 'designsetgo_query_registered_filters', $stored );

		return self::$cache;
	}

	/**
	 * Clears the per-request cache. Intended for test teardown and for callers
	 * that mutate the underlying option directly.
	 *
	 * @return void
	 */
	public static function bust_cache(): void {
		self::$cache = null;
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

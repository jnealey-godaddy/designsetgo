<?php
/**
 * Per-block-type animation defaults resolver.
 *
 * Merges the admin-option defaults over theme.json / Style-Kit defaults
 * (read via wp_get_global_settings) and resolves the effective config for a
 * given block name, honouring exact-name-then-namespace-wildcard precedence.
 *
 * @package DesignSetGo
 * @since 2.6.0
 */

namespace DesignSetGo;

use DesignSetGo\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolver for global per-block-type animation defaults.
 */
class Animation_Defaults {

	/**
	 * Resolve the effective enabled flag + per-block-name config map.
	 *
	 * Precedence per block name: admin option overrides theme.json / Style Kit.
	 * Enabled when either the admin gate or the global gate is true.
	 *
	 * @return array{enabled: bool, map: array<string, array>}
	 */
	public static function get_effective() {
		$settings      = Settings::get_settings();
		$admin_enabled = ! empty( $settings['animations']['block_animations_enabled'] );
		$admin_list    = isset( $settings['animations']['block_animations'] ) && is_array( $settings['animations']['block_animations'] )
			? $settings['animations']['block_animations']
			: array();

		// Fetch the 'designsetgo' custom-settings node as a whole rather than
		// requesting 'blockAnimations'/'blockAnimationsEnabled' as leaf paths
		// directly. wp_get_global_settings() falls back to returning the
		// *entire* merged settings tree — not null/false — when a requested
		// path doesn't resolve (see `_wp_array_get( $settings, $path,
		// $settings )` in wp-includes/global-styles-and-settings.php), which
		// would make `! empty( ... )` always true for an unset leaf. Reading
		// the parent node and doing plain array access below avoids that trap.
		$global_custom = wp_get_global_settings( array( 'custom', 'designsetgo' ) );
		$global_custom = is_array( $global_custom ) ? $global_custom : array();

		$global_list = isset( $global_custom['blockAnimations'] ) && is_array( $global_custom['blockAnimations'] )
			? $global_custom['blockAnimations']
			: array();

		$global_enabled = ! empty( $global_custom['blockAnimationsEnabled'] );

		// Global (theme.json / Style Kit) first, admin overrides per block name.
		// Each entry may target several block names, so it expands to one map
		// key per name; a name claimed twice resolves to the later entry.
		$map = array();
		foreach ( array( $global_list, $admin_list ) as $list ) {
			foreach ( $list as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				// Validate the target names with the same helper the admin
				// sanitizer uses, so a malformed theme.json name (stray
				// whitespace, wrong case, missing slash) is rejected outright
				// instead of sitting in the map under a key nothing can match.
				$blocks = Settings::sanitize_block_animation_targets( $entry );
				if ( empty( $blocks ) ) {
					continue;
				}

				$config = self::normalize_entry( $entry );

				// Same "an entry that animates nothing is meaningless" guard the
				// admin sanitizer applies. Without it a theme.json entrance that
				// fails the enum whitelist (a typo) falls back to '' but still
				// occupies a map key, and the injector then attaches the
				// animation machinery — has-dsgo-animation, so opacity:0 until
				// triggered — for an animation with no keyframes to run.
				// A no-op for the admin list, which is already sanitized.
				if ( '' === $config['entrance'] && '' === $config['exit'] ) {
					continue;
				}

				foreach ( $blocks as $block ) {
					$map[ $block ] = $config;
				}
			}
		}

		return array(
			'enabled' => ( $admin_enabled || $global_enabled ),
			'map'     => $map,
		);
	}

	/**
	 * Resolve the config for a single block name, or null if none applies.
	 *
	 * @param string $block_name Block name (e.g. "core/button").
	 * @return array|null Normalized config or null.
	 */
	public static function resolve_for_block( $block_name ) {
		return self::resolve_from_map( self::get_effective(), $block_name );
	}

	/**
	 * Resolve a block name against an already-computed get_effective() result.
	 *
	 * Pure lookup (no settings reads), so a caller that processes many blocks —
	 * e.g. the render_block injector — can call get_effective() once per request
	 * and reuse the result here for every block instead of recomputing the map.
	 * Exact-name match wins; otherwise the block's `namespace/*` wildcard.
	 *
	 * @param array  $effective  Result of get_effective() (`enabled` + `map`).
	 * @param string $block_name Block name (e.g. "core/button").
	 * @return array|null Normalized config or null.
	 */
	public static function resolve_from_map( array $effective, $block_name ) {
		if ( empty( $effective['enabled'] ) ) {
			return null;
		}

		$map = $effective['map'];
		if ( isset( $map[ $block_name ] ) ) {
			return $map[ $block_name ];
		}

		$slash = strpos( $block_name, '/' );
		if ( false !== $slash ) {
			$wildcard = substr( $block_name, 0, $slash + 1 ) . '*';
			if ( isset( $map[ $wildcard ] ) ) {
				return $map[ $wildcard ];
			}
		}

		return null;
	}

	/**
	 * Validate + normalize an entry's config fields.
	 *
	 * Delegates to the same validator the admin-option sanitizer uses, so
	 * theme.json / Style-Kit sourced entries get the identical enum whitelist
	 * and numeric clamps as admin-submitted ones (e.g. an out-of-range
	 * `duration` or an unknown `trigger` is corrected rather than injected raw).
	 *
	 * @param array $entry Raw entry.
	 * @return array Normalized config (no 'block' key).
	 */
	private static function normalize_entry( $entry ) {
		return Settings::sanitize_block_animation_fields( $entry );
	}
}

<?php
/**
 * Block Guidance for DesignSetGo Abilities.
 *
 * Reads hand-written per-block agent guidance (src/blocks/{block}/agent.json,
 * copied by webpack to build/blocks/{block}/agent.json) — what a block is
 * for, mistakes to avoid, and example trees — so the designsetgo/list-blocks
 * ability can surface it to agents building a page.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.7.5
 */

namespace DesignSetGo\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Guidance class.
 */
class Block_Guidance {

	/**
	 * Block name => build-directory-name lookup, built once per request.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $dir_map = null;

	/**
	 * Hand-written agent guidance for a block, or `null` when it has none.
	 *
	 * Reads `build/blocks/<dir>/agent.json` — never `src/`, which isn't
	 * present in a production install.
	 *
	 * @param string $block_name Full block name (e.g. 'designsetgo/section').
	 * @return array<string, mixed>|null Decoded agent.json content, or null.
	 */
	public static function for_block( string $block_name ): ?array {
		$map = self::get_block_dir_map();

		if ( ! isset( $map[ $block_name ] ) ) {
			return null;
		}

		$path = DESIGNSETGO_PATH . 'build/blocks/' . $map[ $block_name ] . '/agent.json';

		return Block_Schema_Loader::read_json_file( $path );
	}

	/**
	 * Build the block-name => build-directory-name lookup by scanning every
	 * `build/blocks/*\/block.json` once per request (static cache).
	 *
	 * Keyed by the name declared inside block.json, not the directory name
	 * — they don't always match (e.g. a `form-textarea-field` directory can
	 * register `designsetgo/form-textarea`).
	 *
	 * @return array<string, string> Block name => directory name (basename only).
	 */
	private static function get_block_dir_map(): array {
		if ( null !== self::$dir_map ) {
			return self::$dir_map;
		}

		self::$dir_map = array();
		$blocks_dir    = DESIGNSETGO_PATH . 'build/blocks/';

		if ( ! file_exists( $blocks_dir ) ) {
			return self::$dir_map;
		}

		foreach ( (array) glob( $blocks_dir . '*', GLOB_ONLYDIR ) as $block_dir ) {
			$metadata = Block_Schema_Loader::read_json_file( $block_dir . '/block.json' );

			if ( isset( $metadata['name'] ) && is_string( $metadata['name'] ) ) {
				self::$dir_map[ $metadata['name'] ] = basename( $block_dir );
			}
		}

		return self::$dir_map;
	}

	/**
	 * Clear the directory-name cache.
	 *
	 * Useful in tests, or if build/blocks/ changes mid-request.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$dir_map = null;
	}
}

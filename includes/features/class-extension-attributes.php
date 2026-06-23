<?php
/**
 * Extension Attributes Registration
 *
 * Registers extension-injected block attributes on the server side via
 * the `register_block_type_args` filter, so they appear in the REST API
 * at /wp-json/wp/v2/block-types.
 *
 * JS extensions add these same attributes client-side via
 * addFilter('blocks.registerBlockType'). This file mirrors those
 * definitions for server-side awareness.
 *
 * Extension schemas are loaded from individual config files in
 * includes/extension-configs/ for maintainability.
 *
 * @package DesignSetGo
 * @since   2.1.0
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extension Attributes Class
 *
 * Mirrors JS extension attribute definitions on the server so the
 * WordPress REST API block-types endpoint exposes complete schemas.
 */
class Extension_Attributes {

	/**
	 * Loaded extension definitions.
	 *
	 * Each entry maps an extension name to:
	 *   - 'blocks'     string|array  'all' for all blocks (unless excluded), or an explicit list.
	 *   - 'exclude'    array         Block names or namespace wildcards (e.g. 'core-embed/*') to
	 *                                exclude (only when blocks = 'all').
	 *   - 'attributes' array         Attribute schemas matching the JS definitions.
	 *
	 * @var array|null Null until loaded.
	 */
	private static $extensions = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'register_block_type_args', array( $this, 'inject_extension_attributes' ), 10, 2 );
	}

	/**
	 * Load extension definitions from per-extension config files.
	 *
	 * Each file in includes/extension-configs/ returns an array with
	 * 'blocks', 'exclude', and 'attributes' keys. The filename (without
	 * .php) becomes the extension key.
	 *
	 * @return array Extension definitions keyed by name.
	 */
	private static function get_extensions() {
		if ( null !== self::$extensions ) {
			return self::$extensions;
		}

		self::$extensions = array();

		$config_dir = DESIGNSETGO_PATH . 'includes/extension-configs/';
		if ( ! is_dir( $config_dir ) ) {
			return self::$extensions;
		}

		$files = glob( $config_dir . '*.php' );
		if ( empty( $files ) ) {
			return self::$extensions;
		}

		foreach ( $files as $file ) {
			$name   = basename( $file, '.php' );
			$config = include $file;

			if ( is_array( $config ) && isset( $config['attributes'] ) ) {
				self::$extensions[ $name ] = $config;
			}
		}

		return self::$extensions;
	}

	/**
	 * Inject extension attributes into registered block types.
	 *
	 * Runs during register_block_type() for each block. Checks every
	 * extension's allowlist and merges applicable attributes into the
	 * block type args so the REST API exposes them.
	 *
	 * @param array  $args       Block type registration arguments.
	 * @param string $block_type Block type name (e.g. 'designsetgo/section').
	 * @return array Modified arguments with extension attributes merged.
	 */
	public function inject_extension_attributes( $args, $block_type ) {
		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}

		foreach ( self::get_extensions() as $extension ) {
			if ( ! self::block_matches( $block_type, $extension ) ) {
				continue;
			}

			foreach ( $extension['attributes'] as $attr_name => $attr_schema ) {
				// Don't overwrite if block.json already defines this attribute.
				if ( ! isset( $args['attributes'][ $attr_name ] ) ) {
					$args['attributes'][ $attr_name ] = $attr_schema;
				}
			}
		}

		return $args;
	}

	/**
	 * Check if a block matches an extension's allowlist.
	 *
	 * Mirrors the JS shouldExtendBlock() logic including namespace wildcard
	 * support (e.g. 'core-embed/*') and user-configured exclusions from
	 * the plugin settings.
	 *
	 * @param string $block_type Block type name.
	 * @param array  $extension  Extension definition.
	 * @return bool Whether the extension applies to this block.
	 */
	private static function block_matches( $block_type, $extension ) {
		$blocks  = $extension['blocks'];
		$exclude = isset( $extension['exclude'] ) ? $extension['exclude'] : array();

		// Check extension-level exclusion list (supports exact and namespace wildcards).
		if ( self::is_excluded( $block_type, $exclude ) ) {
			return false;
		}

		// Check user-configured excluded blocks from plugin settings.
		if ( self::is_excluded( $block_type, self::get_excluded_blocks() ) ) {
			return false;
		}

		// 'all' means all blocks (core and custom), matching JS shouldExtendBlock() behavior.
		if ( 'all' === $blocks ) {
			return true;
		}

		// Explicit allowlist.
		return is_array( $blocks ) && in_array( $block_type, $blocks, true );
	}

	/**
	 * Check if a block name matches an exclusion list.
	 *
	 * Supports exact matches (e.g. 'core/freeform') and namespace
	 * wildcards (e.g. 'core-embed/*').
	 *
	 * @param string $block_type Block type name.
	 * @param array  $exclusions List of block names or namespace wildcards.
	 * @return bool Whether the block is excluded.
	 */
	private static function is_excluded( $block_type, $exclusions ) {
		if ( empty( $exclusions ) ) {
			return false;
		}

		// Exact match.
		if ( in_array( $block_type, $exclusions, true ) ) {
			return true;
		}

		// Namespace wildcard match (e.g. 'core-embed/*').
		$namespace = strtok( $block_type, '/' );
		if ( in_array( $namespace . '/*', $exclusions, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get user-configured excluded blocks from plugin settings.
	 *
	 * Cached after first call to avoid repeated option lookups.
	 *
	 * @return array List of excluded block names/patterns.
	 */
	private static function get_excluded_blocks() {
		static $excluded = null;

		if ( null === $excluded ) {
			if ( class_exists( 'DesignSetGo\Admin\Settings' ) ) {
				$settings = \DesignSetGo\Admin\Settings::get_settings();
				$excluded = isset( $settings['excluded_blocks'] ) ? (array) $settings['excluded_blocks'] : array();
			} else {
				$excluded = array();
			}
		}

		return $excluded;
	}
}

<?php
/**
 * Block Manager Class
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

namespace DesignSetGo\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Manager Class - Handles block enable/disable logic
 */
class Block_Manager {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'designsetgo_register_block', array( $this, 'should_register_block' ), 10, 2 );
		add_filter( 'designsetgo_load_extension', array( $this, 'should_load_extension' ), 10, 2 );
	}

	/**
	 * Check if a block should be registered
	 *
	 * @param bool   $should_register Default value.
	 * @param string $block_name Block name (e.g., 'designsetgo/flex').
	 * @return bool Whether the block should be registered.
	 */
	public function should_register_block( $should_register, $block_name ) {
		$settings = Settings::get_settings();

		// A denylist, so blocks added in later releases register by default.
		return ! in_array( $block_name, (array) $settings['disabled_blocks'], true );
	}

	/**
	 * Check if an extension should be loaded
	 *
	 * @param bool   $should_load Default value.
	 * @param string $extension_name Extension name (e.g., 'block-animations').
	 * @return bool Whether the extension should be loaded.
	 */
	public function should_load_extension( $should_load, $extension_name ) {
		$settings = Settings::get_settings();

		// A denylist, so extensions added in later releases load by default.
		return ! in_array( $extension_name, (array) $settings['disabled_extensions'], true );
	}

	/**
	 * Get list of all registered blocks
	 *
	 * @return array List of registered block names.
	 */
	public static function get_registered_blocks() {
		$blocks     = \WP_Block_Type_Registry::get_instance()->get_all_registered();
		$dsg_blocks = array();

		foreach ( $blocks as $name => $block ) {
			if ( strpos( $name, 'designsetgo/' ) === 0 ) {
				$dsg_blocks[] = $name;
			}
		}

		return $dsg_blocks;
	}
}

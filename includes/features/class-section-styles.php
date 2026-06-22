<?php
/**
 * Section Styles Support
 *
 * Extends theme section styles to support DesignSetGo container blocks.
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Section_Styles
 *
 * Adds DesignSetGo container blocks to theme section style variations,
 * allowing themes like Twenty Twenty-Five to apply their section styles
 * (Style 1-5) to custom container blocks.
 */
class Section_Styles {
	/**
	 * Custom container blocks that should support section styles.
	 *
	 * @var array
	 */
	private $container_blocks = array(
		'designsetgo/flex',
		'designsetgo/grid',
		'designsetgo/stack',
	);

	/**
	 * Initialize the class and set up hooks.
	 */
	public function init() {
		add_filter( 'wp_theme_json_data_theme', array( $this, 'extend_section_styles' ) );
	}

	/**
	 * Extend section styles to include custom container blocks.
	 *
	 * Filters theme.json data to add DesignSetGo container blocks to any
	 * section styles defined by the theme. Section styles typically have
	 * slugs like "section-1", "section-2", etc.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json The theme.json data object.
	 * @return \WP_Theme_JSON_Data Modified theme.json data.
	 */
	public function extend_section_styles( $theme_json ) {
		// Get the theme data as an array.
		$data = $theme_json->get_data();

		// Check if styles array exists.
		if ( ! isset( $data['styles'] ) || ! is_array( $data['styles'] ) ) {
			return $theme_json;
		}

		$modified = false;

		// Iterate through all style variations.
		foreach ( $data['styles'] as $index => $style ) {
			// Check if this is a section style (has blockTypes defined).
			if ( ! isset( $style['blockTypes'] ) || ! is_array( $style['blockTypes'] ) ) {
				continue;
			}

			// Check if this style targets core container blocks.
			$targets_containers = array_intersect(
				array( 'core/group', 'core/columns', 'core/column' ),
				$style['blockTypes']
			);

			// If it targets core containers, add our custom container blocks.
			if ( ! empty( $targets_containers ) ) {
				// Add custom blocks that aren't already in the list.
				foreach ( $this->container_blocks as $block ) {
					if ( ! in_array( $block, $style['blockTypes'], true ) ) {
						$data['styles'][ $index ]['blockTypes'][] = $block;
						$modified = true;
					}
				}
			}
		}

		// Only update if we actually made changes.
		if ( $modified ) {
			return $theme_json->update_with( $data );
		}

		return $theme_json;
	}
}

<?php
/**
 * Icon Button Styles Support
 *
 * Mirrors the block style variations of the core Button block onto the
 * DesignSetGo Icon Button, so it offers the same variations (Fill, Outline, and
 * anything a theme or plugin adds to core buttons).
 *
 * The variation *names* are mirrored in the editor by
 * src/blocks/icon-button/mirror-button-styles.js: core registers Fill/Outline
 * client-side (not in the PHP block styles registry), so they are invisible to
 * PHP and must be mirrored in JS. This class handles the other half — copying a
 * theme's button variation *styling* defined in theme.json
 * (styles.blocks.core/button.variations.*) onto the Icon Button so it renders
 * the same way. Together they mirror the section-styles mechanism (see
 * {@see \DesignSetGo\Section_Styles}) for buttons.
 *
 * @package DesignSetGo
 * @since 2.4.0
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Icon_Button_Styles
 */
class Icon_Button_Styles {
	/**
	 * DesignSetGo block that should receive the button variation styles.
	 *
	 * @var string
	 */
	private $target_block = 'designsetgo/icon-button';

	/**
	 * Core block whose variation styles are mirrored.
	 *
	 * @var string
	 */
	private $source_block = 'core/button';

	/**
	 * Initialize the class and set up hooks.
	 */
	public function init() {
		// Register the mirrored variation names for our block (editor Styles
		// panel + so the theme.json variation styles survive server-side
		// sanitization, which drops variations not registered for the block).
		add_action( 'init', array( $this, 'register_mirrored_variations' ), 20 );

		// Inject the resolved variation styles onto our block. Both layers so
		// theme.json-defined variations and Global Styles customisations apply.
		add_filter( 'wp_theme_json_data_theme', array( $this, 'mirror_variation_styles' ) );
		add_filter( 'wp_theme_json_data_user', array( $this, 'mirror_variation_styles' ) );
	}

	/**
	 * Register, for the Icon Button, every block style variation registered for
	 * core/button that carries theme.json styling.
	 *
	 * This is what lets the mirrored `styles.blocks.designsetgo/icon-button.
	 * variations.{slug}` survive WordPress's theme.json sanitization (which drops
	 * variations that are not registered block styles for the block) and renders
	 * on the front end. Core's Fill/Outline are registered client-side and carry
	 * no theme.json data, so they are handled separately by
	 * src/blocks/icon-button/mirror-button-styles.js instead.
	 */
	public function register_mirrored_variations() {
		if ( ! class_exists( '\WP_Block_Styles_Registry' ) ) {
			return;
		}

		$variations = $this->get_source_variations();

		if ( empty( $variations ) ) {
			return;
		}

		$registry   = \WP_Block_Styles_Registry::get_instance();
		$registered = $registry->get_registered_styles_for_block( $this->target_block );

		foreach ( $variations as $slug => $label ) {
			if ( array_key_exists( $slug, $registered ) ) {
				continue;
			}

			register_block_style(
				$this->target_block,
				array(
					'name'  => $slug,
					'label' => $label,
				)
			);
		}
	}

	/**
	 * Collect the style variations registered for core/button that carry
	 * theme.json styling — from theme.json partial files (which declare
	 * `blockTypes`) and from data-carrying entries in the block styles registry.
	 *
	 * @return array Map of variation slug => human-readable label.
	 */
	private function get_source_variations() {
		$variations = array();

		// Theme partials that target core/button.
		if ( method_exists( '\WP_Theme_JSON_Resolver', 'get_style_variations' ) ) {
			$partials = \WP_Theme_JSON_Resolver::get_style_variations( 'block' );

			foreach ( $partials as $partial ) {
				if ( empty( $partial['blockTypes'] ) || empty( $partial['styles'] ) ) {
					continue;
				}

				if ( ! in_array( $this->source_block, $partial['blockTypes'], true ) ) {
					continue;
				}

				$slug = isset( $partial['slug'] )
					? $partial['slug']
					: _wp_to_kebab_case( $partial['title'] );

				$variations[ $slug ] = isset( $partial['title'] ) ? $partial['title'] : $slug;
			}
		}

		// Data-carrying variations from the block styles registry.
		if ( class_exists( '\WP_Block_Styles_Registry' ) ) {
			$registry = \WP_Block_Styles_Registry::get_instance();

			foreach ( $registry->get_registered_styles_for_block( $this->source_block ) as $slug => $style ) {
				if ( empty( $style['style_data'] ) || isset( $variations[ $slug ] ) ) {
					continue;
				}

				$variations[ $slug ] = isset( $style['label'] ) ? $style['label'] : $slug;
			}
		}

		return $variations;
	}

	/**
	 * Copy the resolved variation styles from core/button onto the Icon Button.
	 *
	 * Runs on both the theme and user theme.json layers so that base variations
	 * (theme.json / plugins) and Global Styles customisations both propagate.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json The theme.json data object.
	 * @return \WP_Theme_JSON_Data Modified theme.json data.
	 */
	public function mirror_variation_styles( $theme_json ) {
		$data = $theme_json->get_data();

		if ( empty( $data['styles']['blocks'][ $this->source_block ]['variations'] ) ) {
			return $theme_json;
		}

		$variations = $data['styles']['blocks'][ $this->source_block ]['variations'];
		$modified   = false;

		foreach ( $variations as $slug => $styles ) {
			// Never clobber styles explicitly defined for our own block.
			if ( isset( $data['styles']['blocks'][ $this->target_block ]['variations'][ $slug ] ) ) {
				continue;
			}

			$data['styles']['blocks'][ $this->target_block ]['variations'][ $slug ] = $styles;
			$modified = true;
		}

		return $modified ? $theme_json->update_with( $data ) : $theme_json;
	}
}

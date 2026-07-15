<?php
/**
 * Icon Button Styles Support
 *
 * Registers the core Button block's style variations (Fill, Outline, and
 * anything a theme or plugin adds) as block styles for the DesignSetGo Icon
 * Button, so they appear in the editor Styles panel and are valid to apply.
 *
 * The variation *names* are also mirrored in the editor by
 * src/blocks/icon-button/mirror-button-styles.js (core registers Fill/Outline
 * client-side, invisible to PHP); this class registers the server-side half.
 *
 * The variation *rendering* is NOT handled here. It is owned by
 * {@see \DesignSetGo\Button_Global_Styles}, which projects
 * `styles.blocks.core/button.variations.*` onto the Icon Button (and form
 * submit) at a specificity that beats the base button rule. Copying the styling
 * into `styles.blocks.designsetgo/icon-button.variations.*` for WordPress's
 * native pipeline would only emit a `:where()`-wrapped (0,1,0) rule that loses
 * to that base rule — a dead duplicate — so it is deliberately not done.
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
		// Registration only (editor Styles panel + validity). Rendering is
		// Button_Global_Styles' job — see the class docblock for why the old
		// theme.json style-mirror was removed.
		add_action( 'init', array( $this, 'register_mirrored_variations' ), 20 );
	}

	/**
	 * Register, for the Icon Button, every block style variation registered for
	 * core/button that carries theme.json styling — so they appear in the editor
	 * Styles panel and are valid to apply. Rendering is handled elsewhere; see the
	 * class docblock. Core's Fill/Outline are registered client-side (no theme.json
	 * data) and mirrored by src/blocks/icon-button/mirror-button-styles.js instead.
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
}

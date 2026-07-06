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
 * Mirrors the block style variations ("section styles") that a theme or plugin
 * registers for the core container blocks (Group / Columns / Column) onto the
 * DesignSetGo layout blocks (Section / Row / Grid).
 *
 * By WordPress 6.6+, section styles are resolved into two places by the time any
 * `wp_theme_json_data_*` filter runs:
 *
 *   1. The variation name is registered in {@see \WP_Block_Styles_Registry} for
 *      each block in its `blockTypes` list — this is what makes it selectable in
 *      the editor Styles panel and emits the `is-style-{slug}` class.
 *   2. Its styles are injected at `styles.blocks.{blockType}.variations.{slug}`
 *      in the merged theme.json — this is what the frontend/editor render.
 *
 * So supporting a variation on our blocks means reproducing BOTH: register the
 * name for our blocks, and copy the resolved styles onto our block paths. We do
 * this for the theme layer AND the user layer, so variations defined in
 * theme.json (or a plugin) and any customisations made in Global Styles both
 * flow through to the DesignSetGo blocks. No hardcoded slugs — whatever is
 * registered for the core containers is mirrored automatically.
 */
class Section_Styles {
	/**
	 * DesignSetGo container blocks that should receive section styles.
	 *
	 * @var array
	 */
	private $container_blocks = array(
		// Top-level layout containers.
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
		// Card / surface-like containers and compound-block "items" that read
		// as their own container surface. Section styles are layout-agnostic
		// color/border presets, so they apply cleanly to these too.
		'designsetgo/card',
		'designsetgo/fifty-fifty',
		'designsetgo/modal',
		'designsetgo/slide',
		'designsetgo/scroll-slide',
		'designsetgo/tab',
		'designsetgo/accordion-item',
		'designsetgo/scroll-accordion-item',
		// Note: designsetgo/image-accordion-item is deliberately excluded. It
		// sets `color.background: false` in its block.json because its surface
		// is driven entirely by a background image + overlay. A mirrored section
		// style that sets a background color would render behind/around that
		// image with no author-facing control to undo it, so we respect the
		// block's opt-out rather than inject color it can't expose.
		'designsetgo/timeline-item',
		'designsetgo/counter',
		'designsetgo/flip-card-face',
	);

	/**
	 * Core container blocks whose section styles we mirror.
	 *
	 * @var array
	 */
	private $source_blocks = array(
		'core/group',
		'core/columns',
		'core/column',
	);

	/**
	 * Initialize the class and set up hooks.
	 */
	public function init() {
		// Register the variation names for our blocks (editor Styles panel).
		add_action( 'init', array( $this, 'register_mirrored_variations' ), 20 );

		// Inject the resolved variation styles onto our blocks. Both layers so
		// theme.json-defined variations and Global Styles customisations apply.
		add_filter( 'wp_theme_json_data_theme', array( $this, 'mirror_variation_styles' ) );
		add_filter( 'wp_theme_json_data_user', array( $this, 'mirror_variation_styles' ) );
	}

	/**
	 * Register, for each DesignSetGo container block, every block style
	 * variation that is registered for a core container block.
	 *
	 * This is what makes the variation appear in the editor Styles panel for our
	 * blocks and lets the editor add the `is-style-{slug}` class on selection.
	 */
	public function register_mirrored_variations() {
		if ( ! class_exists( '\WP_Block_Styles_Registry' ) ) {
			return;
		}

		$variations = $this->get_container_variations();

		if ( empty( $variations ) ) {
			return;
		}

		$registry = \WP_Block_Styles_Registry::get_instance();

		foreach ( $this->container_blocks as $block ) {
			$registered = $registry->get_registered_styles_for_block( $block );

			foreach ( $variations as $slug => $label ) {
				if ( array_key_exists( $slug, $registered ) ) {
					continue;
				}

				register_block_style(
					$block,
					array(
						'name'  => $slug,
						'label' => $label,
					)
				);
			}
		}
	}

	/**
	 * Collect the section-style variations registered for core container blocks.
	 *
	 * Sourced from theme.json partial files in the theme's `/styles` directory
	 * (which carry `blockTypes`) and from the block styles registry (variations
	 * registered with `style_data`, e.g. by other plugins).
	 *
	 * @return array Map of variation slug => human-readable label.
	 */
	private function get_container_variations() {
		$variations = array();

		// Theme partials, e.g. Twenty Twenty-Five's "Style 1-5".
		if ( method_exists( '\WP_Theme_JSON_Resolver', 'get_style_variations' ) ) {
			$partials = \WP_Theme_JSON_Resolver::get_style_variations( 'block' );

			foreach ( $partials as $partial ) {
				if ( empty( $partial['blockTypes'] ) || empty( $partial['styles'] ) ) {
					continue;
				}

				if ( ! array_intersect( $this->source_blocks, $partial['blockTypes'] ) ) {
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

			foreach ( $this->source_blocks as $source ) {
				foreach ( $registry->get_registered_styles_for_block( $source ) as $slug => $style ) {
					if ( empty( $style['style_data'] ) || isset( $variations[ $slug ] ) ) {
						continue;
					}

					$variations[ $slug ] = isset( $style['label'] ) ? $style['label'] : $slug;
				}
			}
		}

		return $variations;
	}

	/**
	 * Copy the resolved section-style variation styles from the core container
	 * blocks onto the DesignSetGo container blocks.
	 *
	 * Runs on both the theme and user theme.json layers so that base variations
	 * (theme.json / plugins) and Global Styles customisations both propagate.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json The theme.json data object.
	 * @return \WP_Theme_JSON_Data Modified theme.json data.
	 */
	public function mirror_variation_styles( $theme_json ) {
		$data = $theme_json->get_data();

		if ( empty( $data['styles']['blocks'] ) || ! is_array( $data['styles']['blocks'] ) ) {
			return $theme_json;
		}

		// Gather variation styles from the core containers. When more than one
		// source defines the same slug, the first (Group) wins.
		//
		// This intentionally FLATTENS the three source blocks into one slug map
		// and BROADCASTS it to all three DSGo container blocks — there is no
		// source->target pairing (e.g. group->section, columns->row). That is
		// deliberate: DSGo's section/row/grid are all general-purpose
		// containers with no strict analog to the Group/Columns/Column split,
		// and the goal is maximum coverage — a "section style" registered for
		// only one core container (commonly core/group) should still reach all
		// three DSGo blocks. Section styles in practice are layout-agnostic
		// color/border presets, so broadcasting is correct for them. The only
		// theoretical downside is a theme shipping a Column-specific variation
		// that assumes a narrow child cell; such a variation would also land on
		// the full-width Section. That is an accepted trade-off, not an
		// oversight — revisit only if a real theme ships layout-specific,
		// container-type-dependent section styles under distinct slugs.
		$variation_styles = array();
		foreach ( $this->source_blocks as $source ) {
			if ( empty( $data['styles']['blocks'][ $source ]['variations'] ) ) {
				continue;
			}

			foreach ( $data['styles']['blocks'][ $source ]['variations'] as $slug => $styles ) {
				if ( ! isset( $variation_styles[ $slug ] ) ) {
					$variation_styles[ $slug ] = $styles;
				}
			}
		}

		if ( empty( $variation_styles ) ) {
			return $theme_json;
		}

		$modified = false;
		foreach ( $this->container_blocks as $block ) {
			foreach ( $variation_styles as $slug => $styles ) {
				// Never clobber styles explicitly defined for our own block.
				if ( isset( $data['styles']['blocks'][ $block ]['variations'][ $slug ] ) ) {
					continue;
				}

				$data['styles']['blocks'][ $block ]['variations'][ $slug ] = $styles;
				$modified = true;
			}
		}

		return $modified ? $theme_json->update_with( $data ) : $theme_json;
	}
}

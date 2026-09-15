<?php
/**
 * Resolved theme design context for block composition.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Abilities\Info;

use DesignSetGo\Abilities\Abstract_Ability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Read the effective theme, plugin and user design settings. */
class Get_Design_Context extends Abstract_Ability {
	/**
	 * Get the ability name.
	 *
	 * @return string Ability name.
	 */
	public function get_name(): string {
		return 'designsetgo/get-design-context';
	}

	/**
	 * Get the ability configuration.
	 *
	 * @return array<string, mixed> Ability configuration.
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Get Theme Design Context', 'designsetgo' ),
			'description'         => __( 'Read resolved WordPress theme settings, global styles and registered block style variations. Includes theme defaults and user style-kit overrides; does not modify the theme.', 'designsetgo' ),
			'category'            => 'info',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
			),
			'output_schema'       => array( 'type' => 'object' ),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'annotations'         => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		);
	}

	/**
	 * Check access to design settings.
	 *
	 * @return bool Whether the caller can compose content.
	 */
	public function check_permission_callback(): bool {
		return $this->check_permission( 'edit_posts' );
	}

	/**
	 * Read the effective WordPress design system.
	 *
	 * @param array<string, mixed> $input Unused input.
	 * @return array<string, mixed> Effective design data.
	 */
	public function execute( array $input ): array { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required ability interface.
		$theme  = wp_get_theme();
		$styles = array();
		foreach ( \WP_Block_Styles_Registry::get_instance()->get_all_registered() as $name => $variations ) {
			if ( str_starts_with( $name, 'designsetgo/' ) || in_array( $name, array( 'core/heading', 'core/paragraph' ), true ) ) {
				$styles[ $name ] = array_values( array_map( static fn( $variation ) => array_intersect_key( $variation, array_flip( array( 'name', 'label', 'is_default' ) ) ), $variations ) );
			}
		}
		return array(
			'theme'       => array(
				'stylesheet' => $theme->get_stylesheet(),
				'name'       => $theme->get( 'Name' ),
			),
			'settings'    => (object) wp_get_global_settings(),
			'fontFaces'   => \WP_Font_Face_Resolver::get_fonts_from_theme_json(),
			'styles'      => (object) wp_get_global_styles(),
			'blockStyles' => (object) $styles,
		);
	}
}

<?php
/**
 * Configure Custom CSS Ability.
 *
 * Applies custom CSS to individual blocks for advanced styling
 * beyond built-in options.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.0.0
 */

namespace DesignSetGo\Abilities\Configurators;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Block_Configurator;
use DesignSetGo\Abilities\Responsive_CSS;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configure Custom CSS ability class.
 */
class Configure_Custom_CSS extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/configure-custom-css';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Configure Custom CSS', 'designsetgo' ),
			'description'         => __( 'Applies custom CSS to individual blocks for advanced styling. Use the "selector" placeholder to target the specific block.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => Block_Configurator::get_default_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'keywords'            => array( 'style', 'code', 'stylesheet' ),
			'annotations'         => array(
				'readonly'     => false,
				'destructive'  => false,
				'idempotent'   => true,
				'instructions' => 'Writes the canonical dsgoCustomCSS attribute. Desktop is the base CSS at all widths; tablet applies up to 1024px and mobile up to 767px. Replaces supplied breakpoint rules and preserves omitted ones. Empty strings clear supplied rules. enabled:false clears all custom CSS; enabling again does not restore it. Prefer this over update-block for the custom-css extension: it sanitizes the CSS before storing it. For site-wide CSS use update-global-css instead.',
			),
		);
	}

	/**
	 * Get input schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_input_schema(): array {
		$common = Block_Configurator::get_common_input_schema();

		return array(
			'type'                 => 'object',
			'properties'           => array_merge(
				$common,
				array(
					'block_name' => array(
						'type'        => 'string',
						'description' => __( 'Registered block type supporting the custom CSS extension', 'designsetgo' ),
					),
					'css'        => array(
						'type'                 => 'object',
						'description'          => __( 'Custom CSS settings', 'designsetgo' ),
						'additionalProperties' => false,
						'properties'           => array(
							'enabled' => array(
								'type'        => 'boolean',
								'description' => __( 'Enable custom CSS', 'designsetgo' ),
								'default'     => true,
							),
							'desktop' => array(
								'type'        => 'string',
								'description' => __( 'Base CSS at all widths (use "selector" as placeholder). Empty string clears base rules.', 'designsetgo' ),
							),
							'tablet'  => array(
								'type'        => 'string',
								'description' => __( 'CSS up to 1024px, including mobile. Empty string clears tablet rules.', 'designsetgo' ),
							),
							'mobile'  => array(
								'type'        => 'string',
								'description' => __( 'CSS up to 767px. Empty string clears mobile rules.', 'designsetgo' ),
							),
						),
					),
				)
			),
			'required'             => array( 'post_id', 'block_name', 'css' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function check_permission_callback(): bool {
		return $this->check_permission( 'edit_posts' ) && $this->check_permission( 'edit_css' );
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( array $input ) {
		if ( ! $this->check_permission_callback() ) {
			return $this->permission_error();
		}

		$post_id         = (int) ( $input['post_id'] ?? 0 );
		$block_client_id = $input['block_client_id'] ?? null;
		$update_all      = (bool) ( $input['update_all'] ?? false );
		$block_name      = $input['block_name'] ?? '';
		$css             = $input['css'] ?? array();

		// Validate required parameters.
		if ( ! $post_id ) {
			return $this->error(
				'designsetgo_missing_post_id',
				__( 'Post ID is required.', 'designsetgo' )
			);
		}

		if ( empty( $css ) ) {
			return $this->error(
				'designsetgo_missing_css',
				__( 'CSS settings are required.', 'designsetgo' )
			);
		}

		if ( ! is_array( $css ) || ! is_string( $block_name ) || '' === $block_name ) {
			return $this->error( 'designsetgo_invalid_input', __( 'A block name and CSS settings object are required.', 'designsetgo' ) );
		}
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		if ( ! $type || ! isset( $type->attributes['dsgoCustomCSS'] ) ) {
			return $this->error( 'designsetgo_invalid_input', __( 'This block does not support the custom CSS extension.', 'designsetgo' ) );
		}
		$valid = rest_validate_value_from_schema( $css, $this->get_input_schema()['properties']['css'], 'css' );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		return Block_Configurator::transform_block_attributes(
			$post_id,
			$block_name,
			static function ( $existing ) use ( $css ) {
				return array( 'dsgoCustomCSS' => Responsive_CSS::merge( $existing['dsgoCustomCSS'] ?? '', $css ) );
			},
			$block_client_id,
			$update_all
		);
	}
}

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
use DesignSetGo\Abilities\CSS_Sanitizer;
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
						'description' => __( 'Block type to configure (any block type supported)', 'designsetgo' ),
					),
					'css'        => array(
						'type'        => 'object',
						'description' => __( 'Custom CSS settings', 'designsetgo' ),
						'properties'  => array(
							'enabled' => array(
								'type'        => 'boolean',
								'description' => __( 'Enable custom CSS', 'designsetgo' ),
								'default'     => true,
							),
							'desktop' => array(
								'type'        => 'string',
								'description' => __( 'CSS for desktop (use "selector" as placeholder for block selector)', 'designsetgo' ),
							),
							'tablet'  => array(
								'type'        => 'string',
								'description' => __( 'CSS for tablet breakpoint', 'designsetgo' ),
							),
							'mobile'  => array(
								'type'        => 'string',
								'description' => __( 'CSS for mobile breakpoint', 'designsetgo' ),
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
		return $this->check_permission( 'edit_posts' );
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( array $input ) {
		$post_id         = (int) ( $input['post_id'] ?? 0 );
		$block_client_id = $input['block_client_id'] ?? null;
		$update_all      = (bool) ( $input['update_all'] ?? false );
		$block_name      = $input['block_name'] ?? '';
		$css             = $input['css'] ?? array();

		// Validate required parameters.
		if ( ! $post_id ) {
			return $this->error(
				'missing_post_id',
				__( 'Post ID is required.', 'designsetgo' )
			);
		}

		if ( empty( $css ) ) {
			return $this->error(
				'missing_css',
				__( 'CSS settings are required.', 'designsetgo' )
			);
		}

		// Sanitize CSS using comprehensive CSS_Sanitizer to prevent XSS attacks.
		$sanitized_css = array();
		if ( isset( $css['enabled'] ) ) {
			$sanitized_css['dsgoCustomCssEnabled'] = (bool) $css['enabled'];
		}
		if ( ! empty( $css['desktop'] ) ) {
			$sanitized_css['dsgoCustomCss'] = CSS_Sanitizer::sanitize( $css['desktop'] );
		}
		if ( ! empty( $css['tablet'] ) ) {
			$sanitized_css['dsgoCustomCssTablet'] = CSS_Sanitizer::sanitize( $css['tablet'] );
		}
		if ( ! empty( $css['mobile'] ) ) {
			$sanitized_css['dsgoCustomCssMobile'] = CSS_Sanitizer::sanitize( $css['mobile'] );
		}

		// Apply the configuration.
		return Block_Configurator::configure_block(
			$post_id,
			$block_name,
			$sanitized_css,
			$block_client_id,
			$update_all
		);
	}
}

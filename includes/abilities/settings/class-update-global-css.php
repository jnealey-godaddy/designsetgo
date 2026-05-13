<?php
/**
 * Update Global CSS Ability.
 *
 * Writes the site's "Additional CSS" by wrapping wp_update_custom_css_post(),
 * the same store the WordPress Customizer's Additional CSS panel uses.
 * Theme-scoped automatically (one custom_css post per stylesheet), and
 * runs through the WordPress core sanitization pipeline.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.2.0
 */

namespace DesignSetGo\Abilities\Settings;

use DesignSetGo\Abilities\Abstract_Ability;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Update Global CSS ability class.
 */
class Update_Global_CSS extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/update-global-css';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Update Global CSS', 'designsetgo' ),
			'description'         => __( 'Replaces the site\'s Additional CSS for the active theme. Equivalent to saving the WordPress Customizer\'s Additional CSS panel — value is stored on the theme\'s custom_css post and applied site-wide. The submitted CSS REPLACES the existing value entirely; call get-global-css first if you need to append.', 'designsetgo' ),
			'category'            => 'settings',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'css' ),
				'additionalProperties' => false,
				'properties'           => array(
					'css' => array(
						'type'        => 'string',
						'description' => __( 'The complete CSS to save. Pass an empty string to clear.', 'designsetgo' ),
					),
				),
			),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'css', 'styles', 'customizer', 'additional css', 'global', 'theme' ),
			'annotations'         => array(
				'idempotent'   => true,
				'instructions' => 'Submitting { "css": "..." } REPLACES the entire Additional CSS for the active theme — there is no merge. To append, call get-global-css first, concatenate, then submit. To clear, submit { "css": "" }. Sanitization is applied by WordPress core.',
			),
		);
	}

	/**
	 * Get output schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'success'    => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the update was applied.', 'designsetgo' ),
				),
				'css'        => array(
					'type'        => 'string',
					'description' => __( 'The CSS value as stored after sanitization.', 'designsetgo' ),
				),
				'stylesheet' => array(
					'type'        => 'string',
					'description' => __( 'The stylesheet (theme directory) the CSS was saved to.', 'designsetgo' ),
				),
				'post_id'    => array(
					'type'        => 'integer',
					'description' => __( 'ID of the custom_css post that stores the value.', 'designsetgo' ),
				),
			),
		);
	}

	/**
	 * Permission callback.
	 *
	 * Mirrors the capability the Customizer's Additional CSS panel uses
	 * (edit_css → maps to unfiltered_html on single-site, super-admin on
	 * multisite). Stricter than manage_options on purpose: arbitrary CSS
	 * is a higher-trust action than toggling plugin settings.
	 *
	 * @return bool
	 */
	public function check_permission_callback(): bool {
		return $this->check_permission( 'edit_css' );
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string, mixed> $input { css: string }.
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( array $input ) {
		$css        = isset( $input['css'] ) && is_string( $input['css'] ) ? $input['css'] : '';
		$stylesheet = get_stylesheet();

		$post = wp_update_custom_css_post(
			$css,
			array( 'stylesheet' => $stylesheet )
		);

		if ( is_wp_error( $post ) ) {
			return $this->error(
				'custom_css_update_failed',
				$post->get_error_message(),
				array( 'wp_error_code' => $post->get_error_code() )
			);
		}

		return $this->success(
			array(
				'css'        => wp_get_custom_css( $stylesheet ),
				'stylesheet' => $stylesheet,
				'post_id'    => (int) $post->ID,
			)
		);
	}
}

<?php
/**
 * Get Global CSS Ability.
 *
 * Reads the site's "Additional CSS" stored on the active stylesheet's
 * custom_css post — the same store the WordPress Customizer writes to.
 * Pair with designsetgo/update-global-css to read-modify-write safely.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.2.0
 */

namespace DesignSetGo\Abilities\Settings;

use DesignSetGo\Abilities\Abstract_Ability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Global CSS ability class.
 */
class Get_Global_CSS extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/get-global-css';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Get Global CSS', 'designsetgo' ),
			'description'         => __( 'Returns the site\'s Additional CSS for the active theme — the same value managed by the WordPress Customizer\'s Additional CSS panel. Returns an empty string when none has been saved.', 'designsetgo' ),
			'category'            => 'settings',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
				// REST GETs without `?input=...` arrive as null. The Abilities
				// API replaces null with the schema default before validation,
				// so an empty-object default lets nonce'd reads succeed
				// without requiring callers to pass an empty `input` param.
				'default'              => array(),
			),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'css', 'styles', 'customizer', 'additional css', 'global', 'theme' ),
			'annotations'         => array(
				'readonly'     => true,
				'idempotent'   => true,
				'instructions' => 'Call this before update-global-css when you want to append to or modify the existing Additional CSS rather than replace it. The value is scoped to the currently active theme.',
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
					'description' => __( 'Whether the read succeeded.', 'designsetgo' ),
				),
				'css'        => array(
					'type'        => 'string',
					'description' => __( 'The current Additional CSS for the active theme.', 'designsetgo' ),
				),
				'stylesheet' => array(
					'type'        => 'string',
					'description' => __( 'The stylesheet (theme directory) the CSS is scoped to.', 'designsetgo' ),
				),
				'post_id'    => array(
					'type'        => array( 'integer', 'null' ),
					'description' => __( 'ID of the underlying custom_css post, or null if no CSS has been saved yet.', 'designsetgo' ),
				),
			),
		);
	}

	/**
	 * Permission callback.
	 *
	 * Mirrors the capability the Customizer's Additional CSS panel uses
	 * (edit_css → maps to unfiltered_html on single-site, super-admin on
	 * multisite). Reading is gated on the same cap as writing so this
	 * ability never exposes CSS to roles that couldn't edit it anyway.
	 *
	 * @return bool
	 */
	public function check_permission_callback(): bool {
		return $this->check_permission( 'edit_css' );
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string, mixed> $input Ignored — no inputs.
	 * @return array<string, mixed>
	 */
	public function execute( array $input ): array {
		$stylesheet = get_stylesheet();
		$post       = wp_get_custom_css_post( $stylesheet );

		return $this->success(
			array(
				'css'        => wp_get_custom_css( $stylesheet ),
				'stylesheet' => $stylesheet,
				'post_id'    => $post ? (int) $post->ID : null,
			)
		);
	}
}

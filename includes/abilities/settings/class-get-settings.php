<?php
/**
 * Get Settings Ability.
 *
 * Returns the current DesignSetGo plugin settings merged with defaults.
 * Mirrors the GET /designsetgo/v1/settings REST endpoint so consumers of
 * the WordPress Abilities API (MCP servers, AI agents) can read plugin
 * configuration without knowing the plugin's REST surface.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.1.0
 */

namespace DesignSetGo\Abilities\Settings;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Admin\Settings;
use DesignSetGo\Admin\Settings_Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Settings ability class.
 */
class Get_Settings extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/get-settings';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Get DesignSetGo Settings', 'designsetgo' ),
			'description'         => __( 'Returns the current DesignSetGo plugin settings merged with defaults, including enabled blocks/extensions, performance, forms, animations, sticky header, draft mode, integrations, and llms.txt configuration.', 'designsetgo' ),
			'category'            => 'settings',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => Settings_Schema::get(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'settings', 'config', 'options', 'preferences' ),
			'annotations'         => array(
				'readonly'     => true,
				'idempotent'   => true,
				'instructions' => 'Call this before update-settings to see current values. Results include secrets (API keys) since the caller has already been authorized via manage_options.',
			),
		);
	}

	/**
	 * Get input schema.
	 *
	 * Settings reads take no parameters. Consumers that only want a single
	 * group should pick the group from the returned object.
	 *
	 * @return array<string, mixed>
	 */
	private function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => new \stdClass(),
			'additionalProperties' => false,
		);
	}

	/**
	 * Permission callback.
	 *
	 * Mirrors the REST endpoint — only site administrators can read
	 * settings because the payload includes integration secrets.
	 *
	 * @return bool
	 */
	public function check_permission_callback(): bool {
		return $this->check_permission( 'manage_options' );
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string, mixed> $_input Unused — settings reads take no parameters.
	 * @return array<string, mixed>
	 */
	public function execute( array $_input ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return Settings::get_settings();
	}
}

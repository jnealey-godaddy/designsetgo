<?php
/**
 * Update Settings Ability.
 *
 * Applies a partial update to DesignSetGo plugin settings. Only the keys
 * provided in input are modified — all other settings are preserved.
 * Nested groups are merged field-by-field, so sending
 * { "forms": { "retention_days": 60 } } updates only that field without
 * resetting the rest of the forms group.
 *
 * Mirrors the POST /designsetgo/v1/settings REST endpoint and shares the
 * same sanitization pipeline via Settings::update_settings().
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
 * Update Settings ability class.
 */
class Update_Settings extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/update-settings';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Update DesignSetGo Settings', 'designsetgo' ),
			'description'         => __( 'Applies a partial update to DesignSetGo plugin settings. Only the keys provided are changed; all other settings are preserved. Nested groups are merged field-by-field — sending { "forms": { "retention_days": 60 } } updates just that field.', 'designsetgo' ),
			'category'            => 'settings',
			'input_schema'        => Settings_Schema::get(),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'settings', 'config', 'configure', 'preferences', 'options' ),
			'annotations'         => array(
				'idempotent'   => true,
				'instructions' => 'Call get-settings first to see current values. Submit only the keys you want to change — omitted keys remain untouched. Empty arrays for enabled_blocks or enabled_extensions mean "all enabled". To replace a list field (enabled_blocks, enabled_extensions, excluded_blocks) entirely, fetch the current value first and resubmit the full desired array — lists are merged positionally (by index), not replaced wholesale.',
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
				'success'  => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the update was applied.', 'designsetgo' ),
				),
				'settings' => array_merge(
					Settings_Schema::get(),
					array( 'description' => __( 'The full settings object after the update has been applied.', 'designsetgo' ) )
				),
			),
		);
	}

	/**
	 * Permission callback.
	 *
	 * Settings writes require the same capability as the REST endpoint
	 * (manage_options). Ability invocations do not need the X-WP-Nonce
	 * check that the REST endpoint enforces — the Abilities API handles
	 * authentication at the transport layer (REST or MCP).
	 *
	 * @return bool
	 */
	public function check_permission_callback(): bool {
		return $this->check_permission( 'manage_options' );
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string, mixed> $input Partial settings to apply.
	 * @return array<string, mixed>
	 */
	public function execute( array $input ): array {
		$updated = Settings::update_settings( $input );

		return $this->success(
			array(
				'settings' => $updated,
			)
		);
	}
}

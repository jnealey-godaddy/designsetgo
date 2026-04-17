<?php
/**
 * Settings JSON Schema.
 *
 * Describes the shape of the DesignSetGo plugin settings object for the
 * Abilities API (get-settings, update-settings). Separated from Settings
 * so that the already-large class-settings.php does not have to grow
 * every time a new setting key is added.
 *
 * The schema mirrors the structure produced by Settings::get_defaults()
 * and sanitized by Settings::sanitize_settings(). Keep all three in sync —
 * a key missing from this schema is invisible to Abilities API clients
 * even if it is otherwise valid.
 *
 * @package DesignSetGo
 * @subpackage Admin
 * @since 2.1.0
 */

namespace DesignSetGo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings_Schema class.
 *
 * Hosts the JSON Schema for the settings payload in one place so it can
 * be consumed by the Abilities API without adding bulk to Settings.
 */
class Settings_Schema {

	/**
	 * Get the JSON Schema describing the settings object.
	 *
	 * @return array JSON Schema for the settings object.
	 */
	public static function get(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'enabled_blocks'     => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => __( 'Enabled block names. Empty array means all blocks are enabled.', 'designsetgo' ),
				),
				'enabled_extensions' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => __( 'Enabled extension names. Empty array means all extensions are enabled.', 'designsetgo' ),
				),
				'excluded_blocks'    => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => __( 'Block name patterns excluded from configuration (supports wildcards like "plugin/*").', 'designsetgo' ),
				),
				'performance'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'conditional_loading' => array( 'type' => 'boolean' ),
						'cache_duration'      => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
					),
				),
				'forms'              => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'enable_honeypot'      => array( 'type' => 'boolean' ),
						'enable_rate_limiting' => array( 'type' => 'boolean' ),
						'enable_email_logging' => array( 'type' => 'boolean' ),
						'retention_days'       => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
					),
				),
				'animations'         => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'enable_animations'              => array( 'type' => 'boolean' ),
						'default_duration'               => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'default_easing'                 => array( 'type' => 'string' ),
						'respect_prefers_reduced_motion' => array( 'type' => 'boolean' ),
						'default_icon_button_hover'      => array( 'type' => 'string' ),
					),
				),
				'security'           => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'log_ip_addresses' => array( 'type' => 'boolean' ),
						'log_user_agents'  => array( 'type' => 'boolean' ),
						'log_referrers'    => array( 'type' => 'boolean' ),
					),
				),
				'integrations'       => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'google_maps_api_key'  => array( 'type' => 'string' ),
						'turnstile_site_key'   => array( 'type' => 'string' ),
						'turnstile_secret_key' => array( 'type' => 'string' ),
					),
				),
				'sticky_header'      => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'enable'                    => array( 'type' => 'boolean' ),
						'custom_selector'           => array( 'type' => 'string' ),
						'z_index'                   => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'shadow_on_scroll'          => array( 'type' => 'boolean' ),
						'shadow_size'               => array( 'type' => 'string' ),
						'shrink_on_scroll'          => array( 'type' => 'boolean' ),
						'shrink_amount'             => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'mobile_enabled'            => array( 'type' => 'boolean' ),
						'mobile_breakpoint'         => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'transition_speed'          => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'scroll_threshold'          => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'hide_on_scroll_down'       => array( 'type' => 'boolean' ),
						'background_on_scroll'      => array( 'type' => 'boolean' ),
						'background_scroll_color'   => array(
							'type'        => 'string',
							'description' => __( 'Hex color (e.g. "#ffffff") or empty string.', 'designsetgo' ),
						),
						'background_scroll_opacity' => array(
							'type'    => 'integer',
							'minimum' => 0,
							'maximum' => 100,
						),
						'text_scroll_color'         => array(
							'type'        => 'string',
							'description' => __( 'Hex color (e.g. "#000000") or empty string.', 'designsetgo' ),
						),
					),
				),
				'draft_mode'         => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'enable'                 => array( 'type' => 'boolean' ),
						'show_page_list_actions' => array( 'type' => 'boolean' ),
						'show_page_list_column'  => array( 'type' => 'boolean' ),
						'show_frontend_preview'  => array( 'type' => 'boolean' ),
						'auto_save_enabled'      => array( 'type' => 'boolean' ),
						'auto_save_interval'     => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
					),
				),
				'llms_txt'           => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'enable'            => array( 'type' => 'boolean' ),
						'post_types'        => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'description'       => array( 'type' => 'string' ),
						'generate_full_txt' => array( 'type' => 'boolean' ),
					),
				),
			),
		);
	}
}

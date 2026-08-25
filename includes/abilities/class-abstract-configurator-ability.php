<?php
/**
 * Abstract base class for block configurator abilities.
 *
 * Provides automatic schema generation from block.json files,
 * reducing duplication and ensuring consistency with block definitions.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.1.0
 */

namespace DesignSetGo\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for configurator abilities.
 *
 * Extends Abstract_Ability with block.json schema integration.
 * Child classes can either:
 * 1. Define get_target_block_name() to auto-generate schema from block.json
 * 2. Override get_input_schema() for custom schema with mixed sources
 */
abstract class Abstract_Configurator_Ability extends Abstract_Ability {

	/**
	 * Get the target block name for this configurator.
	 *
	 * When defined, the schema will be automatically generated from
	 * the block's block.json file. Return null to use custom schema.
	 *
	 * @return string|null Block name (e.g., 'designsetgo/section') or null.
	 */
	protected function get_target_block_name(): ?string {
		return null;
	}

	/**
	 * Get attributes to include from block.json.
	 *
	 * Override to limit which attributes are exposed in the ability.
	 * Return empty array to include all attributes.
	 *
	 * @return array<string> List of attribute names to include.
	 */
	protected function get_included_attributes(): array {
		return array();
	}

	/**
	 * Get attributes to exclude from block.json.
	 *
	 * Override to remove specific attributes from the schema.
	 * Processed after get_included_attributes().
	 *
	 * @return array<string> List of attribute names to exclude.
	 */
	protected function get_excluded_attributes(): array {
		return array();
	}

	/**
	 * Get additional properties to add to the input schema.
	 *
	 * Override to add custom properties beyond block.json attributes.
	 * These are added at the top level, alongside common properties.
	 *
	 * @return array<string, mixed> Additional schema properties.
	 */
	protected function get_additional_properties(): array {
		return array();
	}

	/**
	 * Get keyword aliases for search discovery.
	 *
	 * Override to provide synonyms and related terms that an LLM
	 * might use when searching for this ability.
	 *
	 * @return array<string> Keyword aliases.
	 */
	protected function get_keywords(): array {
		return array();
	}

	/**
	 * Get the ability configuration.
	 *
	 * Builds config with auto-generated input schema from block.json
	 * when get_target_block_name() returns a valid block name.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		$config = array(
			'label'               => $this->get_label(),
			'description'         => $this->get_description(),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => Block_Configurator::get_default_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'annotations'         => array(
				'idempotent' => true,
			),
		);

		$keywords = $this->get_keywords();
		if ( ! empty( $keywords ) ) {
			$config['keywords'] = $keywords;
		}

		return $config;
	}

	/**
	 * Get the ability label.
	 *
	 * @return string Human-readable label.
	 */
	abstract protected function get_label(): string;

	/**
	 * Get the ability description.
	 *
	 * @return string Description of what this ability does.
	 */
	abstract protected function get_description(): string;

	/**
	 * Get the input schema for this ability.
	 *
	 * Automatically generates schema from block.json when
	 * get_target_block_name() returns a valid block name.
	 *
	 * @return array<string, mixed> JSON Schema for ability input.
	 */
	protected function get_input_schema(): array {
		$block_name = $this->get_target_block_name();

		if ( $block_name ) {
			return Block_Schema_Loader::get_ability_input_schema(
				$block_name,
				$this->get_included_attributes(),
				$this->get_excluded_attributes(),
				$this->get_additional_properties()
			);
		}

		// Fallback to common schema with additional properties.
		$common     = Block_Configurator::get_common_input_schema();
		$additional = $this->get_additional_properties();

		$common['block_name'] = array(
			'type'        => 'string',
			'description' => __( 'Block type to configure', 'designsetgo' ),
		);

		return array(
			'type'                 => 'object',
			'properties'           => array_merge( $common, $additional ),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Permission callback for the ability.
	 *
	 * @return bool Whether the current user has permission.
	 */
	public function check_permission_callback(): bool {
		return $this->check_permission( 'edit_posts' );
	}

	/**
	 * Execute the ability with the given input.
	 *
	 * Default implementation handles common parameter extraction
	 * and delegates to configure_block().
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error Result data or error.
	 */
	public function execute( array $input ) {
		$post_id         = (int) ( $input['post_id'] ?? 0 );
		$block_client_id = $input['block_client_id'] ?? null;
		$update_all      = (bool) ( $input['update_all'] ?? false );
		$target_block    = $this->get_target_block_name();
		$attributes      = $input['attributes'] ?? array();

		// For block-specific configurators, enforce the target block name.
		// This prevents callers from overriding the intended block type.
		if ( $target_block ) {
			$block_name = $target_block;
		} else {
			$block_name = $input['block_name'] ?? '';
		}

		// Validate required parameters.
		if ( ! $post_id ) {
			return $this->error(
				'designsetgo_missing_post_id',
				__( 'Post ID is required.', 'designsetgo' )
			);
		}

		if ( '' === $block_name ) {
			return $this->error(
				'designsetgo_missing_block_name',
				__( 'Block name is required.', 'designsetgo' )
			);
		}

		// Filter attributes against allowed list at runtime (not just schema).
		$attributes = $this->filter_attributes_at_runtime( $attributes );

		// Transform attributes if needed.
		$new_attributes = $this->transform_attributes( $attributes, $input );

		if ( is_wp_error( $new_attributes ) ) {
			return $new_attributes;
		}

		// Use count() instead of empty() to allow legitimate falsy values (0, false).
		if ( ! is_array( $new_attributes ) || 0 === count( $new_attributes ) ) {
			return $this->error(
				'designsetgo_missing_attributes',
				__( 'No attributes provided to configure.', 'designsetgo' )
			);
		}

		// Apply the configuration.
		return Block_Configurator::configure_block(
			$post_id,
			$block_name,
			$new_attributes,
			$block_client_id,
			$update_all
		);
	}

	/**
	 * Filter attributes at runtime against block.json definitions.
	 *
	 * Ensures that only attributes defined in the block's block.json
	 * and not excluded by get_excluded_attributes() are applied.
	 *
	 * @param array<string, mixed> $attributes Input attributes.
	 * @return array<string, mixed> Filtered attributes.
	 */
	protected function filter_attributes_at_runtime( array $attributes ): array {
		$block_name = $this->get_target_block_name();

		if ( ! $block_name ) {
			return $attributes;
		}

		$valid_attrs   = Block_Schema_Loader::get_attribute_names( $block_name );
		$include_attrs = $this->get_included_attributes();
		$exclude_attrs = $this->get_excluded_attributes();

		// If no valid attributes found, return as-is.
		if ( empty( $valid_attrs ) ) {
			return $attributes;
		}

		$filtered = array();

		foreach ( $attributes as $attr_name => $attr_value ) {
			// Must be a valid block attribute.
			if ( ! in_array( $attr_name, $valid_attrs, true ) ) {
				continue;
			}

			// If include list is specified, must be in it.
			if ( ! empty( $include_attrs ) && ! in_array( $attr_name, $include_attrs, true ) ) {
				continue;
			}

			// Must not be in exclude list.
			if ( in_array( $attr_name, $exclude_attrs, true ) ) {
				continue;
			}

			$filtered[ $attr_name ] = $attr_value;
		}

		return $filtered;
	}

	/**
	 * Transform input attributes to block attributes.
	 *
	 * Override to map ability input to actual block attribute names.
	 * For example, converting 'maxWidth.desktop' to 'dsgoMaxWidth'.
	 *
	 * Default implementation returns attributes as-is.
	 *
	 * @param array<string, mixed> $attributes Attributes from input.
	 * @param array<string, mixed> $_input     Full input array (unused in base; override uses it).
	 * @return array<string, mixed>|WP_Error Transformed attributes or error.
	 */
	protected function transform_attributes( array $attributes, array $_input ) {
		// By default, sanitize and return as-is.
		return Block_Configurator::sanitize_attributes( $attributes );
	}

	/**
	 * Get the block.json data for the target block.
	 *
	 * Convenience method for accessing block metadata in subclasses.
	 *
	 * @return array<string, mixed>|null Block data or null if not found.
	 */
	protected function get_block_json(): ?array {
		$block_name = $this->get_target_block_name();

		if ( ! $block_name ) {
			return null;
		}

		return Block_Schema_Loader::get_block_json( $block_name );
	}

	/**
	 * Check if the target block has a specific attribute.
	 *
	 * @param string $attr_name Attribute name.
	 * @return bool
	 */
	protected function block_has_attribute( string $attr_name ): bool {
		$block_name = $this->get_target_block_name();

		if ( ! $block_name ) {
			return false;
		}

		return Block_Schema_Loader::has_attribute( $block_name, $attr_name );
	}

	/**
	 * Get the default value for a block attribute.
	 *
	 * @param string $attr_name Attribute name.
	 * @return mixed Default value or null.
	 */
	protected function get_attribute_default( string $attr_name ) {
		$block_name = $this->get_target_block_name();

		if ( ! $block_name ) {
			return null;
		}

		return Block_Schema_Loader::get_attribute_default( $block_name, $attr_name );
	}
}

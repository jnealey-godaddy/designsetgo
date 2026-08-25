<?php
/**
 * Update Block Ability.
 *
 * Updates any block's attributes by document-order index, block name, or
 * client ID. Use get-post-blocks to find the target block index, then pass
 * the attributes to update. Supports all block attributes including style,
 * custom attributes, and WordPress supports-generated attributes.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.1.0
 */

namespace DesignSetGo\Abilities\Configurators;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Block_Configurator;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Update Block ability class.
 */
class Update_Block extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/update-block';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Update Block', 'designsetgo' ),
			'description'         => __( 'Updates any block\'s attributes by document-order index, block name, or client ID. Use get-post-blocks to find the target block_index first. Supports all block attributes including style (padding, colors, typography), custom attributes, and WordPress supports-generated attributes.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => Block_Configurator::get_default_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'keywords'            => array( 'modify', 'change', 'edit', 'set' ),
			'annotations'         => array(
				'readonly'     => false,
				'destructive'  => false,
				'idempotent'   => true,
				'instructions' => 'Merges the supplied attributes into a block that already exists — it never inserts one. Call get-post-blocks first to get the block index or client ID. Submitting the same attributes twice leaves the post in the same state.',
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
						'description' => __( 'Block type to target (e.g., "designsetgo/section"). Required when block_index is not provided. When used with block_index, serves as a safety check to validate the block type matches.', 'designsetgo' ),
					),
					'attributes' => array(
						'type'        => 'object',
						'description' => __( 'Key-value pairs of attributes to merge into the block. For style attributes use nested objects, e.g. { "style": { "spacing": { "padding": { "top": "40px" } } } }. Attribute keys are validated against the block registry when possible.', 'designsetgo' ),
					),
				)
			),
			'required'             => array( 'post_id', 'attributes' ),
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
	 * Targeting logic:
	 * - If block_index provided: target by index. If block_name also provided, validate match.
	 * - If only block_name provided: target first match (or all if update_all).
	 * - If block_client_id provided: target by client ID.
	 * - If none: return error.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( array $input ) {
		$post_id         = (int) ( $input['post_id'] ?? 0 );
		$block_index     = isset( $input['block_index'] ) ? (int) $input['block_index'] : null;
		$block_client_id = $input['block_client_id'] ?? null;
		$update_all      = (bool) ( $input['update_all'] ?? false );
		$block_name      = $input['block_name'] ?? '';
		$attributes      = $input['attributes'] ?? array();

		// Validate required parameters.
		if ( ! $post_id ) {
			return $this->error(
				'designsetgo_missing_post_id',
				__( 'Post ID is required.', 'designsetgo' )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error( 'designsetgo_invalid_post', __( 'Post not found.', 'designsetgo' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->permission_error();
		}

		if ( ! is_array( $attributes ) || empty( $attributes ) ) {
			return $this->error(
				'designsetgo_missing_settings',
				__( 'Attributes object is required and must contain at least one key-value pair to update.', 'designsetgo' )
			);
		}

		// Validate targeting: at least one targeting method must be provided.
		if ( null === $block_index && empty( $block_name ) && empty( $block_client_id ) ) {
			return $this->error(
				'designsetgo_invalid_input',
				__( 'At least one targeting parameter is required: block_index, block_name, or block_client_id.', 'designsetgo' )
			);
		}

		// Validate and sanitize attributes.
		$attributes = $this->validate_attributes( $post_id, $block_name, $block_index, $attributes );
		if ( is_wp_error( $attributes ) ) {
			return $attributes;
		}

		// Route to the appropriate targeting method.
		if ( null !== $block_index ) {
			// Target by document-order index with optional block_name safety check.
			return Block_Configurator::update_block_by_index(
				$post_id,
				$block_index,
				$attributes,
				$block_name
			);
		}

		// Target by block name and/or client ID.
		if ( empty( $block_name ) ) {
			return $this->error(
				'designsetgo_missing_block_name',
				__( 'block_name is required when block_index is not provided.', 'designsetgo' )
			);
		}

		return Block_Configurator::configure_block(
			$post_id,
			$block_name,
			$attributes,
			$block_client_id,
			$update_all
		);
	}

	/**
	 * Validate attributes against the block's registered schema.
	 *
	 * For explicitly declared attributes in WP_Block_Type_Registry, validates
	 * that the attribute keys exist. For the 'style' attribute (dynamically
	 * generated by WordPress supports), only validates it's an object - does
	 * NOT attempt deep validation of nested structure.
	 *
	 * @param int                  $post_id     Post ID.
	 * @param string               $block_name  Block name (may be empty if targeting by index).
	 * @param int|null             $block_index Block index (may be null).
	 * @param array<string, mixed> $attributes  Attributes to validate.
	 * @return array<string, mixed>|WP_Error Sanitized attributes or error.
	 */
	private function validate_attributes( int $post_id, string $block_name, ?int $block_index, array $attributes ) {
		// If we don't know the block name yet (targeting by index only), skip registry validation.
		// The block_name will be validated at update time by update_block_by_index.
		if ( empty( $block_name ) ) {
			return Block_Configurator::sanitize_attributes( $attributes );
		}

		// Attempt to get block type from registry for validation.
		$registry   = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );

		if ( ! $block_type ) {
			// Block type not registered - still allow the operation since
			// blocks may be registered dynamically or the name could be a
			// core block that's not available in this context.
			return Block_Configurator::sanitize_attributes( $attributes );
		}

		$registered_attrs = $block_type->attributes ?? array();

		// Validate attribute keys exist in the block's registered schema.
		// Skip validation for WordPress-managed attributes that may not appear
		// in the registry: 'style' (supports-generated), 'className' (always
		// available), 'lock', 'metadata'.
		$skip_validation = array( 'style', 'className', 'lock', 'metadata' );

		foreach ( $attributes as $key => $value ) {
			if ( in_array( $key, $skip_validation, true ) ) {
				// For 'style', only validate it's an array/object.
				if ( 'style' === $key && ! is_array( $value ) ) {
					return new WP_Error(
						'designsetgo_validation_failed',
						__( 'The "style" attribute must be an object.', 'designsetgo' ),
						array( 'status' => 400 )
					);
				}
				continue;
			}

			// Only validate against registry if the block has registered attributes.
			// Some blocks have empty attribute schemas and accept arbitrary attributes.
			if ( ! empty( $registered_attrs ) && ! isset( $registered_attrs[ $key ] ) ) {
				return new WP_Error(
					'designsetgo_validation_failed',
					sprintf(
						/* translators: 1: attribute key, 2: block name */
						__( 'Attribute "%1$s" is not registered for block "%2$s".', 'designsetgo' ),
						$key,
						$block_name
					),
					array( 'status' => 400 )
				);
			}
		}

		return Block_Configurator::sanitize_attributes( $attributes );
	}
}

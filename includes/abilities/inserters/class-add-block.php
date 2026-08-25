<?php
/**
 * Add Block Ability.
 *
 * Generic top-level block inserter. Adds any block to a post at the
 * top level by block name, with optional attributes and inner blocks.
 * Use list-blocks to discover available block names and their schemas.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.2.0
 */

namespace DesignSetGo\Abilities\Inserters;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Block_Configurator;
use DesignSetGo\Abilities\Block_Inserter;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Block ability class.
 */
class Add_Block extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/add-block';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Add Block', 'designsetgo' ),
			'description'         => __( 'Adds any block to a post at the top level. Provide a block_name (e.g., "designsetgo/section", "core/paragraph"), optional attributes, and optional inner_blocks. Use list-blocks to discover available blocks and their attribute schemas.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => Block_Inserter::get_default_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'insert', 'create', 'new' ),
			'annotations'         => array(
				'readonly'    => false,
				'destructive' => false,
				// Each call appends another block, so repeating it is not a no-op.
				'idempotent'  => false,
			),
		);
	}

	/**
	 * Get input schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id'      => array(
					'type'        => 'integer',
					'description' => __( 'Target post ID', 'designsetgo' ),
				),
				'block_name'   => array(
					'type'        => 'string',
					'description' => __( 'Block type to add (e.g., "designsetgo/section", "core/paragraph")', 'designsetgo' ),
				),
				'attributes'   => array(
					'type'        => 'object',
					'description' => __( 'Attributes for the new block', 'designsetgo' ),
					'default'     => array(),
				),
				'inner_blocks' => array(
					'type'        => 'array',
					'description' => __( 'Inner blocks for the new block (each with name, attributes, and optional innerBlocks)', 'designsetgo' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'name'        => array(
								'type'        => 'string',
								'description' => __( 'Block name', 'designsetgo' ),
							),
							'attributes'  => array(
								'type'        => 'object',
								'description' => __( 'Block attributes', 'designsetgo' ),
							),
							'innerBlocks' => array(
								'type'        => 'array',
								'description' => __( 'Nested inner blocks', 'designsetgo' ),
							),
						),
					),
					'default'     => array(),
				),
				'position'     => array(
					'type'        => 'integer',
					'description' => __( 'Position in the post. -1 appends to end (default), 0 prepends, or specify an index.', 'designsetgo' ),
					'default'     => -1,
				),
			),
			'required'             => array( 'post_id', 'block_name' ),
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
		$post_id      = (int) ( $input['post_id'] ?? 0 );
		$block_name   = $input['block_name'] ?? '';
		$attributes   = $input['attributes'] ?? array();
		$inner_blocks = $input['inner_blocks'] ?? array();
		$position     = (int) ( $input['position'] ?? -1 );

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

		if ( empty( $block_name ) ) {
			return $this->error(
				'designsetgo_missing_block_name',
				__( 'block_name is required.', 'designsetgo' )
			);
		}

		// Validate block name format.
		$block_name = sanitize_text_field( $block_name );
		if ( ! preg_match( '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/', $block_name ) ) {
			return $this->error(
				'designsetgo_invalid_input',
				__( 'block_name must be in "namespace/block-name" format (lowercase alphanumeric and hyphens).', 'designsetgo' )
			);
		}

		// Sanitize attributes.
		if ( ! empty( $attributes ) ) {
			$attributes = Block_Configurator::sanitize_attributes( $attributes );
		}

		// Sanitize inner blocks.
		if ( ! empty( $inner_blocks ) ) {
			$inner_blocks = $this->sanitize_inner_blocks( $inner_blocks );
		}

		// Insert the block at the top level.
		return Block_Inserter::insert_block(
			$post_id,
			$block_name,
			$attributes,
			$inner_blocks,
			$position
		);
	}

	/**
	 * Recursively sanitize inner blocks and their attributes.
	 *
	 * @param array<int, array<string, mixed>> $inner_blocks Inner blocks to sanitize.
	 * @return array<int, array<string, mixed>> Sanitized inner blocks.
	 */
	private function sanitize_inner_blocks( array $inner_blocks ): array {
		$sanitized = array();

		foreach ( $inner_blocks as $block ) {
			$clean_block = array();

			if ( isset( $block['name'] ) ) {
				$clean_block['name'] = sanitize_text_field( $block['name'] );
			}

			if ( isset( $block['attributes'] ) && is_array( $block['attributes'] ) ) {
				$clean_block['attributes'] = Block_Configurator::sanitize_attributes( $block['attributes'] );
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$clean_block['innerBlocks'] = $this->sanitize_inner_blocks( $block['innerBlocks'] );
			}

			$sanitized[] = $clean_block;
		}

		return $sanitized;
	}
}

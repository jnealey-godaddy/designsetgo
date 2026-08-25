<?php
/**
 * Batch Update Ability.
 *
 * Applies changes to multiple blocks at once, supporting batch operations
 * for animations, styles, and other configurations.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.0.0
 */

namespace DesignSetGo\Abilities\Configurators;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Block_Configurator;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch Update ability class.
 */
class Batch_Update extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/batch-update';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Batch Update Blocks', 'designsetgo' ),
			'description'         => __( 'Applies attribute changes to multiple blocks at once. Supports updating by block name across entire post or multiple specific blocks.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'keywords'            => array( 'bulk', 'multiple', 'mass' ),
			'annotations'         => array(
				'readonly'     => false,
				'destructive'  => false,
				'idempotent'   => true,
				'instructions' => 'Applies several attribute updates to one post in a single save. Operations are matched by block name and applied in order; none of them insert or remove blocks. Call get-post-blocks first to confirm the targets exist — an operation that matches nothing is skipped silently rather than failing the batch.',
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
				'post_id'    => array(
					'type'        => 'integer',
					'description' => __( 'Post ID to update blocks in', 'designsetgo' ),
				),
				'operations' => array(
					'type'        => 'array',
					'description' => __( 'Array of batch operations to perform', 'designsetgo' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'block_name' => array(
								'type'        => 'string',
								'description' => __( 'Block name to target', 'designsetgo' ),
							),
							'attributes' => array(
								'type'        => 'object',
								'description' => __( 'Attributes to set on matching blocks', 'designsetgo' ),
							),
							'filter'     => array(
								'type'        => 'object',
								'description' => __( 'Optional filter: only update blocks matching these attribute values', 'designsetgo' ),
							),
						),
						'required'   => array( 'block_name', 'attributes' ),
					),
					'minItems'    => 1,
					'maxItems'    => 20,
				),
			),
			'required'             => array( 'post_id', 'operations' ),
			'additionalProperties' => false,
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
				'success'           => array(
					'type'        => 'boolean',
					'description' => __( 'Whether all operations succeeded', 'designsetgo' ),
				),
				'post_id'           => array(
					'type'        => 'integer',
					'description' => __( 'The post ID', 'designsetgo' ),
				),
				'total_updated'     => array(
					'type'        => 'integer',
					'description' => __( 'Total number of blocks updated', 'designsetgo' ),
				),
				'operation_results' => array(
					'type'        => 'array',
					'description' => __( 'Results for each operation', 'designsetgo' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'block_name'    => array( 'type' => 'string' ),
							'updated_count' => array( 'type' => 'integer' ),
							'success'       => array( 'type' => 'boolean' ),
						),
					),
				),
			),
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
		$post_id    = (int) ( $input['post_id'] ?? 0 );
		$operations = $input['operations'] ?? array();

		// Validate post ID.
		if ( ! $post_id ) {
			return $this->error(
				'designsetgo_missing_post_id',
				__( 'Post ID is required.', 'designsetgo' )
			);
		}

		// Validate operations.
		if ( empty( $operations ) ) {
			return $this->error(
				'designsetgo_missing_operations',
				__( 'At least one operation is required.', 'designsetgo' )
			);
		}

		// Validate post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error(
				'designsetgo_invalid_post',
				__( 'Post not found.', 'designsetgo' )
			);
		}

		// Check permission for this specific post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->permission_error();
		}

		// Parse blocks.
		$blocks = parse_blocks( $post->post_content );

		// Execute each operation.
		$total_updated     = 0;
		$operation_results = array();

		foreach ( $operations as $operation ) {
			$block_name = $operation['block_name'] ?? '';
			$attributes = $operation['attributes'] ?? array();
			$filter     = $operation['filter'] ?? null;

			if ( empty( $block_name ) || empty( $attributes ) ) {
				$operation_results[] = array(
					'block_name'    => $block_name,
					'updated_count' => 0,
					'success'       => false,
					'error'         => 'Missing block_name or attributes',
				);
				continue;
			}

			// Sanitize attributes.
			$attributes = Block_Configurator::sanitize_attributes( $attributes );

			// Apply updates.
			$result = $this->update_blocks_by_name( $blocks, $block_name, $attributes, $filter );
			$blocks = $result['blocks'];

			$operation_results[] = array(
				'block_name'    => $block_name,
				'updated_count' => $result['updated'],
				'success'       => $result['updated'] > 0,
			);

			$total_updated += $result['updated'];
		}

		// Serialize and update post.
		$content = serialize_blocks( $blocks );

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				// Slash so wp_update_post()'s internal wp_unslash() doesn't strip
				// JSON-escape backslashes from block-comment attributes.
				'post_content' => wp_slash( $content ),
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return array(
			'success'           => true,
			'post_id'           => $post_id,
			'total_updated'     => $total_updated,
			'operation_results' => $operation_results,
		);
	}

	/**
	 * Update all blocks matching name with new attributes.
	 *
	 * @param array<int, array<string, mixed>> $blocks     Blocks array.
	 * @param string                           $block_name Block name to match.
	 * @param array<string, mixed>             $attributes Attributes to set.
	 * @param array<string, mixed>|null        $filter     Optional filter criteria.
	 * @return array{blocks: array, updated: int}
	 */
	private function update_blocks_by_name( array $blocks, string $block_name, array $attributes, ?array $filter = null ): array {
		$updated = 0;

		foreach ( $blocks as &$block ) {
			// Check if this block matches.
			if ( $block['blockName'] === $block_name ) {
				// Check filter if provided.
				if ( $filter && ! $this->block_matches_filter( $block, $filter ) ) {
					continue;
				}

				// Merge attributes.
				$block['attrs'] = array_merge( $block['attrs'] ?? array(), $attributes );

				// Update saved HTML markup to reflect new attributes.
				$block = Block_Configurator::update_block_markup( $block, $attributes, $block_name );
				++$updated;
			}

			// Process inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$result               = $this->update_blocks_by_name( $block['innerBlocks'], $block_name, $attributes, $filter );
				$block['innerBlocks'] = $result['blocks'];
				$updated             += $result['updated'];
			}
		}

		return array(
			'blocks'  => $blocks,
			'updated' => $updated,
		);
	}

	/**
	 * Check if a block matches the filter criteria.
	 *
	 * @param array<string, mixed> $block  Block to check.
	 * @param array<string, mixed> $filter Filter criteria.
	 * @return bool True if block matches filter.
	 */
	private function block_matches_filter( array $block, array $filter ): bool {
		$attrs = $block['attrs'] ?? array();

		foreach ( $filter as $key => $value ) {
			if ( ! isset( $attrs[ $key ] ) || $attrs[ $key ] !== $value ) {
				return false;
			}
		}

		return true;
	}
}

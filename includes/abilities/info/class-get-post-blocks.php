<?php
/**
 * Get Post Blocks Ability.
 *
 * Retrieves all blocks from a specific post, enabling AI agents to
 * understand the current content structure before making changes.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.0.0
 */

namespace DesignSetGo\Abilities\Info;

use DesignSetGo\Abilities\Abstract_Ability;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Post Blocks ability class.
 */
class Get_Post_Blocks extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/get-post-blocks';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Get Post Blocks', 'designsetgo' ),
			'description'         => __( 'Retrieves all blocks from a post with their attributes, enabling inspection of current content structure.', 'designsetgo' ),
			'category'            => 'info',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'content', 'inspect', 'read', 'page' ),
			'annotations'         => array(
				'readonly'     => true,
				'destructive'  => false,
				'idempotent'   => true,
				'instructions' => 'Use this to inspect existing blocks in a post before making changes. Returns blockIndex and blockPath values needed for configurator, delete, and add-child-block abilities. Both are positions in the complete post, so they stay valid when block_name, include_inner, or flatten narrows the response.',
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
				'post_id'       => array(
					'type'        => 'integer',
					'description' => __( 'Post ID to retrieve blocks from', 'designsetgo' ),
				),
				'block_name'    => array(
					'type'        => 'string',
					'description' => __( 'Optional: Filter to only return blocks of this type', 'designsetgo' ),
				),
				'include_inner' => array(
					'type'        => 'boolean',
					'description' => __( 'Include inner blocks in the response', 'designsetgo' ),
					'default'     => true,
				),
				'flatten'       => array(
					'type'        => 'boolean',
					'description' => __( 'Return a flat list instead of nested structure', 'designsetgo' ),
					'default'     => false,
				),
			),
			'required'             => array( 'post_id' ),
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
				'success' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the operation succeeded', 'designsetgo' ),
				),
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'The post ID', 'designsetgo' ),
				),
				'blocks'  => array(
					'type'        => 'array',
					'description' => __( 'Array of blocks', 'designsetgo' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'blockIndex'  => array(
								'type'        => 'integer',
								'description' => __( 'Document-order index. Shifts when blocks are inserted earlier in the document - prefer blockPath when planning several writes from one read.', 'designsetgo' ),
							),
							'blockPath'   => array(
								'type'        => 'string',
								'description' => __( 'Tree path, e.g. "2.1.0": the block\'s position within each ancestor. Pass as parent_block_path to designsetgo/add-child-block.', 'designsetgo' ),
							),
							'blockName'   => array( 'type' => 'string' ),
							'attrs'       => array( 'type' => 'object' ),
							'innerBlocks' => array( 'type' => 'array' ),
							'innerHTML'   => array( 'type' => 'string' ),
							'depth'       => array(
								'type'        => 'integer',
								'description' => __( 'Nesting depth in the post (0 = top level). Present only when flatten is true.', 'designsetgo' ),
							),
						),
					),
				),
				'total'   => array(
					'type'        => 'integer',
					'description' => __( 'Total number of blocks returned', 'designsetgo' ),
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
		$post_id       = (int) ( $input['post_id'] ?? 0 );
		$block_name    = $input['block_name'] ?? null;
		$include_inner = (bool) ( $input['include_inner'] ?? true );
		$flatten       = (bool) ( $input['flatten'] ?? false );

		// Validate post.
		if ( ! $post_id ) {
			return $this->error(
				'designsetgo_missing_post_id',
				__( 'Post ID is required.', 'designsetgo' )
			);
		}

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

		// Parse blocks from post content.
		$blocks = parse_blocks( $post->post_content );

		// Assign document identities before filtering, flattening, or hiding children.
		// Configurators resolve these indices and paths against the complete post.
		$blocks = $this->clean_blocks_for_output( $blocks );

		// Filter by block name if specified.
		if ( $block_name ) {
			$blocks = $this->filter_blocks_by_name( $blocks, $block_name, $include_inner );
		}

		// Remove inner blocks if not wanted.
		if ( ! $include_inner ) {
			$blocks = array_map(
				function ( $block ) {
					unset( $block['innerBlocks'] );
					return $block;
				},
				$blocks
			);
		}

		// Flatten if requested.
		if ( $flatten ) {
			$blocks = $this->flatten_blocks( $blocks );
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'blocks'  => $blocks,
			'total'   => count( $blocks ),
		);
	}

	/**
	 * Filter blocks by name, including searching inner blocks.
	 *
	 * A matching block is returned with its subtree, which is not searched
	 * further, so a match nested inside another match is not listed twice.
	 *
	 * @param array<int, array<string, mixed>> $blocks     Blocks to filter.
	 * @param string                           $block_name Block name to filter by.
	 * @param bool                             $search_inner Whether to search inner blocks.
	 * @return array<int, array<string, mixed>> Filtered blocks.
	 */
	private function filter_blocks_by_name( array $blocks, string $block_name, bool $search_inner ): array {
		$matching = array();

		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === $block_name ) {
				$matching[] = $block;
			} elseif ( $search_inner && ! empty( $block['innerBlocks'] ) ) {
				$inner_matches = $this->filter_blocks_by_name( $block['innerBlocks'], $block_name, true );
				$matching      = array_merge( $matching, $inner_matches );
			}
		}

		return $matching;
	}

	/**
	 * Flatten nested block structure to a single-level array.
	 *
	 * Depth comes from blockPath, so it is the block's depth in the post even
	 * when a block_name filter made it a root of the response.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks to flatten.
	 * @return array<int, array<string, mixed>> Flattened blocks.
	 */
	private function flatten_blocks( array $blocks ): array {
		$flat = array();

		foreach ( $blocks as $block ) {
			$block['depth'] = substr_count( $block['blockPath'], '.' );
			$inner_blocks   = $block['innerBlocks'] ?? array();
			unset( $block['innerBlocks'] );

			$flat[] = $block;

			if ( ! empty( $inner_blocks ) ) {
				$flat = array_merge( $flat, $this->flatten_blocks( $inner_blocks ) );
			}
		}

		return $flat;
	}

	/**
	 * Clean blocks for API output.
	 *
	 * Removes unnecessary properties and formats for cleaner response.
	 * Assigns a document-order blockIndex to each block via depth-first traversal.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Blocks to clean.
	 * @param int                              $counter     Document-order counter passed by reference.
	 * @param string                           $path_prefix Parent tree path; empty at the top level.
	 * @return array<int, array<string, mixed>> Cleaned blocks.
	 */
	private function clean_blocks_for_output( array $blocks, int &$counter = 0, string $path_prefix = '' ): array {
		$result  = array();
		$ordinal = 0;

		foreach ( $blocks as $block ) {
			// Match the configurators' indexing of named blocks at every depth.
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$path = '' === $path_prefix ? (string) $ordinal : $path_prefix . '.' . $ordinal;
			++$ordinal;

			$cleaned = array(
				'blockIndex' => $counter,
				'blockPath'  => $path,
				'blockName'  => $block['blockName'],
				'attrs'      => $block['attrs'] ?? array(),
			);

			++$counter;

			// Include innerHTML summary (truncated).
			if ( ! empty( $block['innerHTML'] ) ) {
				$html                 = trim( $block['innerHTML'] );
				$cleaned['innerHTML'] = strlen( $html ) > 200 ? substr( $html, 0, 200 ) . '...' : $html;
			}

			// Include cleaned inner blocks if present.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$cleaned['innerBlocks'] = $this->clean_blocks_for_output( $block['innerBlocks'], $counter, $path );
			}

			$result[] = $cleaned;
		}

		return $result;
	}
}

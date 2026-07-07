<?php
/**
 * Find Blocks Ability.
 *
 * Searches for blocks across posts matching specific criteria,
 * useful for bulk operations and content audits.
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
 * Find Blocks ability class.
 */
class Find_Blocks extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/find-blocks';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Find Blocks', 'designsetgo' ),
			'description'         => __( 'Searches for blocks of a specific type across posts, returning locations and counts.', 'designsetgo' ),
			'category'            => 'info',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'search', 'locate', 'query', 'filter' ),
			'annotations'         => array(
				'readonly'     => true,
				'instructions' => 'Searches for blocks across posts by block name. Useful for content audits and finding blocks before bulk operations.',
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
				'block_name'  => array(
					'type'        => 'string',
					'description' => __( 'Block name to search for (e.g., "designsetgo/accordion")', 'designsetgo' ),
				),
				'post_type'   => array(
					'type'        => 'string',
					'description' => __( 'Post type to search in', 'designsetgo' ),
					'default'     => 'page',
				),
				'post_status' => array(
					'type'        => 'string',
					'description' => __( 'Post status to filter by', 'designsetgo' ),
					'default'     => 'publish',
				),
				'limit'       => array(
					'type'        => 'integer',
					'description' => __( 'Maximum number of posts to search', 'designsetgo' ),
					'default'     => 50,
					'minimum'     => 1,
					'maximum'     => 100,
				),
			),
			'required'             => array( 'block_name' ),
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
				'success'     => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the search succeeded', 'designsetgo' ),
				),
				'block_name'  => array(
					'type'        => 'string',
					'description' => __( 'Block name that was searched', 'designsetgo' ),
				),
				'total_found' => array(
					'type'        => 'integer',
					'description' => __( 'Total number of matching blocks found', 'designsetgo' ),
				),
				'posts'       => array(
					'type'        => 'array',
					'description' => __( 'Posts containing matching blocks', 'designsetgo' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'     => array( 'type' => 'integer' ),
							'post_title'  => array( 'type' => 'string' ),
							'post_type'   => array( 'type' => 'string' ),
							'edit_url'    => array( 'type' => 'string' ),
							'block_count' => array( 'type' => 'integer' ),
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
		$block_name  = $input['block_name'] ?? '';
		$post_type   = $input['post_type'] ?? 'page';
		$post_status = $input['post_status'] ?? 'publish';
		$limit       = min( (int) ( $input['limit'] ?? 50 ), 100 );

		// Validate block name.
		if ( empty( $block_name ) ) {
			return $this->error(
				'missing_block_name',
				__( 'Block name is required.', 'designsetgo' )
			);
		}

		// Validate post_type exists.
		if ( ! post_type_exists( $post_type ) ) {
			return $this->error(
				'invalid_post_type',
				sprintf(
					/* translators: %s: Post type name */
					__( 'Invalid post type: %s', 'designsetgo' ),
					$post_type
				)
			);
		}

		// Validate post_status is valid.
		$valid_statuses = array_keys( get_post_stati() );
		if ( ! in_array( $post_status, $valid_statuses, true ) ) {
			return $this->error(
				'invalid_post_status',
				sprintf(
					/* translators: %s: Post status name */
					__( 'Invalid post status: %s', 'designsetgo' ),
					$post_status
				)
			);
		}

		// Query posts.
		$posts = get_posts( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- suppress_filters is false (see below)
			array(
				'post_type'        => $post_type,
				'post_status'      => $post_status,
				'posts_per_page'   => $limit,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		$results     = array();
		$total_found = 0;

		foreach ( $posts as $post ) {
			// Check if user can edit this post.
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			// Parse blocks and count matches.
			$blocks = parse_blocks( $post->post_content );
			$count  = $this->count_blocks_by_name( $blocks, $block_name );

			if ( $count > 0 ) {
				$results[] = array(
					'post_id'     => $post->ID,
					'post_title'  => $post->post_title,
					'post_type'   => $post->post_type,
					'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
					'block_count' => $count,
				);

				$total_found += $count;
			}
		}

		return array(
			'success'     => true,
			'block_name'  => $block_name,
			'total_found' => $total_found,
			'posts'       => $results,
		);
	}

	/**
	 * Count blocks by name, including nested blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks     Blocks to search.
	 * @param string                           $block_name Block name to count.
	 * @return int Number of matching blocks.
	 */
	private function count_blocks_by_name( array $blocks, string $block_name ): int {
		$count = 0;

		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === $block_name ) {
				++$count;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$count += $this->count_blocks_by_name( $block['innerBlocks'], $block_name );
			}
		}

		return $count;
	}
}

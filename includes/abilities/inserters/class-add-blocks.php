<?php
/**
 * Add Blocks Ability.
 *
 * Batch top-level block inserter. Adds several blocks to a post, in order,
 * with a single content write. Each block takes the same definition as
 * add-block: a block name, optional attributes and optional inner blocks.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.7.6
 */

namespace DesignSetGo\Abilities\Inserters;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Block_Inserter;
use DesignSetGo\Abilities\Block_Batch;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Blocks ability class.
 */
class Add_Blocks extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/add-blocks';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Add Blocks', 'designsetgo' ),
			'description'         => __( 'Adds several blocks to a post at the top level, in order, with one save. Each entry takes the same block_name, attributes and inner_blocks as add-block. All-or-nothing: if any entry is invalid, nothing is written and the error names its index.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'insert', 'create', 'new', 'batch' ),
			'annotations'         => array(
				'readonly'    => false,
				'destructive' => false,
				// Each call appends the blocks again, so repeating it is not a no-op.
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
				'post_id'  => array(
					'type'        => 'integer',
					'description' => __( 'Target post ID', 'designsetgo' ),
				),
				'blocks'   => Block_Batch::schema(),
				'position' => array(
					'type'        => 'integer',
					'description' => __( 'Position of the first block in the post. -1 appends to end (default), 0 prepends, or specify an index.', 'designsetgo' ),
					'default'     => -1,
				),
			),
			'required'             => array( 'post_id', 'blocks' ),
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
				'success'  => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the operation was successful', 'designsetgo' ),
				),
				'post_id'  => array(
					'type'        => 'integer',
					'description' => __( 'Post ID where the blocks were inserted', 'designsetgo' ),
				),
				'inserted' => array(
					'type'        => 'integer',
					'description' => __( 'Number of blocks inserted', 'designsetgo' ),
				),
				'position' => array(
					'type'        => 'integer',
					'description' => __( 'Position where the first block was inserted', 'designsetgo' ),
				),
			),
			'required'   => array( 'success' ),
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
		$post_id  = (int) ( $input['post_id'] ?? 0 );
		$blocks   = $input['blocks'] ?? array();
		$position = (int) ( $input['position'] ?? -1 );

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

		$definitions = Block_Batch::prepare( $blocks );
		if ( is_wp_error( $definitions ) || isset( $definitions['success'] ) ) {
			return $definitions;
		}

		return Block_Inserter::insert_blocks( $post_id, $definitions, $position );
	}
}

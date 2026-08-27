<?php
/**
 * Add Child Block Ability.
 *
 * Adds a block as a child of an existing block identified by its
 * document-order index. Use get-post-blocks to find the parent block
 * index, then insert into it (e.g., adding a paragraph inside a section).
 *
 * Critical: Updates both innerBlocks AND innerContent on the parent
 * block to prevent serialize_blocks() from silently dropping the new block.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.1.0
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
 * Add Child Block ability class.
 */
class Add_Child_Block extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/add-child-block';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Add Child Block', 'designsetgo' ),
			'description'         => __( 'Adds a block as a child of an existing block. Use get-post-blocks to find the parent block_index first, then specify the block_name and attributes to insert.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'nested', 'inner', 'append' ),
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
				'post_id'            => array(
					'type'        => 'integer',
					'description' => __( 'Target post ID', 'designsetgo' ),
				),
				'parent_block_index' => array(
					'type'        => 'integer',
					'description' => __( 'Document-order index of the parent block (from get-post-blocks). Prefer parent_block_path: an index shifts whenever a block is added earlier in the document, so a series of inserts planned from one read can land in the wrong parents.', 'designsetgo' ),
				),
				'parent_block_path'  => array(
					'type'        => 'string',
					'description' => __( 'Tree path of the parent block, e.g. "2.1.0" (from get-post-blocks). Preferred over parent_block_index because it does not shift when unrelated blocks are inserted. Wins if both are given.', 'designsetgo' ),
				),
				'block_name'         => array(
					'type'        => 'string',
					'description' => __( 'Block type to insert (e.g., "designsetgo/icon-button", "core/paragraph")', 'designsetgo' ),
				),
				'attributes'         => array(
					'type'        => 'object',
					'description' => __( 'Attributes for the new block', 'designsetgo' ),
					'default'     => array(),
				),
				'inner_blocks'       => array_merge(
					Block_Inserter::get_inner_blocks_schema(),
					array( 'default' => array() )
				),
				'position'           => array(
					'type'        => 'integer',
					'description' => __( 'Position within the parent\'s inner blocks. -1 appends to end (default), 0 prepends, or specify an index.', 'designsetgo' ),
					'default'     => -1,
				),
			),
			// parent_block_index is not required: parent_block_path may be
			// given instead. execute() checks that exactly one is present and
			// says so in terms the caller can act on.
			'required'             => array( 'post_id', 'block_name' ),
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
				'success'            => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the operation was successful', 'designsetgo' ),
				),
				'post_id'            => array(
					'type'        => 'integer',
					'description' => __( 'Post ID where the block was inserted', 'designsetgo' ),
				),
				'parent_block_index' => array(
					'type'        => 'integer',
					'description' => __( 'Index of the parent block', 'designsetgo' ),
				),
				'block_name'         => array(
					'type'        => 'string',
					'description' => __( 'Name of the block that was inserted', 'designsetgo' ),
				),
				'position'           => array(
					'type'        => 'integer',
					'description' => __( 'Position within the parent where the block was inserted', 'designsetgo' ),
				),
				'note'               => array(
					'type'        => 'string',
					'description' => __( 'Additional information', 'designsetgo' ),
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
		$post_id            = (int) ( $input['post_id'] ?? 0 );
		$parent_block_index = isset( $input['parent_block_index'] ) ? (int) $input['parent_block_index'] : null;
		$parent_block_path  = isset( $input['parent_block_path'] ) ? trim( (string) $input['parent_block_path'] ) : null;
		$block_name         = $input['block_name'] ?? '';
		$attributes         = $input['attributes'] ?? array();
		$inner_blocks       = $input['inner_blocks'] ?? array();
		$position           = (int) ( $input['position'] ?? -1 );

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

		if ( null === $parent_block_index && ( null === $parent_block_path || '' === $parent_block_path ) ) {
			return $this->error(
				'designsetgo_invalid_input',
				__( 'Identify the parent with parent_block_path (preferred) or parent_block_index. Both are reported by designsetgo/get-post-blocks.', 'designsetgo' )
			);
		}

		if ( null !== $parent_block_path && '' !== $parent_block_path && ! preg_match( '/^\\d+(\\.\\d+)*$/', $parent_block_path ) ) {
			return $this->error(
				'designsetgo_invalid_input',
				__( 'parent_block_path must be dot-separated positions, e.g. "0" or "2.1.0".', 'designsetgo' )
			);
		}

		if ( empty( $block_name ) ) {
			return $this->error(
				'designsetgo_missing_block_name',
				__( 'block_name is required.', 'designsetgo' )
			);
		}

		// Validate block name format: must be namespace/block-name (alphanumeric + hyphens).
		$block_name = sanitize_text_field( $block_name );
		if ( ! preg_match( '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/', $block_name ) ) {
			return $this->error(
				'designsetgo_invalid_input',
				__( 'block_name must be in "namespace/block-name" format (lowercase alphanumeric and hyphens).', 'designsetgo' )
			);
		}

		// Screen the request BEFORE sanitizing: sanitization drops keys it does
		// not recognise, so a misnamed field would be gone by the time anything
		// looked for it. That is how a nested `block_name` used to remove every
		// child in silence.
		$placement = Block_Inserter::check_child_placement( $block_name, $inner_blocks, is_array( $attributes ) ? $attributes : array() );
		if ( null !== $placement ) {
			return $placement;
		}

		// Sanitize attributes.
		if ( ! empty( $attributes ) ) {
			$attributes = Block_Configurator::sanitize_attributes( $attributes );
		}

		// Recursively sanitize inner blocks attributes.
		if ( ! empty( $inner_blocks ) ) {
			$inner_blocks = $this->sanitize_inner_blocks( $inner_blocks );
		}

		// Delegate to Block_Configurator's insert_inner_block method.
		return Block_Configurator::insert_inner_block(
			$post_id,
			(int) $parent_block_index,
			$block_name,
			$attributes,
			$inner_blocks,
			$position,
			$parent_block_path
		);
	}

	/**
	 * Recursively sanitize inner blocks and their attributes.
	 *
	 * Ensures all nested block attributes are properly sanitized to prevent
	 * XSS via malicious attribute values in deeply nested structures.
	 *
	 * Only preserves name, attributes, and innerBlocks fields. The innerHTML
	 * and innerContent fields are intentionally omitted because they are
	 * regenerated by Block_Inserter::build_block_markup() from the block
	 * name and attributes.
	 *
	 * @param array<int, array<string, mixed>> $inner_blocks Inner blocks to sanitize.
	 * @return array<int, array<string, mixed>> Sanitized inner blocks.
	 */
	private function sanitize_inner_blocks( array $inner_blocks ): array {
		return Block_Inserter::sanitize_inner_block_definitions( $inner_blocks );
	}
}

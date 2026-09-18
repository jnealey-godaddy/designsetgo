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
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Blocks ability class.
 */
class Add_Blocks extends Abstract_Ability {

	/**
	 * Most blocks one call may insert; a page's worth of top-level sections.
	 */
	private const MAX_BLOCKS = 50;

	/**
	 * Keys a top-level definition may carry.
	 */
	private const DEFINITION_KEYS = array( 'block_name', 'attributes', 'inner_blocks' );

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
				'blocks'   => array(
					'type'        => 'array',
					'description' => __( 'Blocks to add, in document order.', 'designsetgo' ),
					'minItems'    => 1,
					'maxItems'    => self::MAX_BLOCKS,
					'items'       => array(
						'type'                 => 'object',
						'properties'           => array(
							'block_name'   => array(
								'type'        => 'string',
								'description' => __( 'Block type to add (e.g., "designsetgo/section", "core/paragraph")', 'designsetgo' ),
							),
							'attributes'   => array(
								'type'        => 'object',
								'description' => __( 'Attributes for the new block', 'designsetgo' ),
								'default'     => array(),
							),
							'inner_blocks' => array_merge(
								Block_Inserter::get_inner_blocks_schema(),
								array( 'default' => array() )
							),
						),
						'required'             => array( 'block_name' ),
						'additionalProperties' => false,
					),
				),
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

		if ( ! is_array( $blocks ) || empty( $blocks ) ) {
			return $this->error(
				'designsetgo_invalid_input',
				__( 'blocks must be a non-empty array of block definitions.', 'designsetgo' )
			);
		}

		if ( count( $blocks ) > self::MAX_BLOCKS ) {
			return $this->error(
				'designsetgo_invalid_input',
				sprintf(
					/* translators: %d: maximum number of blocks */
					__( 'blocks may hold at most %d entries; split the insert into several calls.', 'designsetgo' ),
					self::MAX_BLOCKS
				)
			);
		}

		$definitions = array();
		foreach ( array_values( $blocks ) as $index => $block ) {
			$definition = $this->prepare_entry( $index, $block );
			if ( isset( $definition['success'] ) ) {
				return $definition;
			}
			$definitions[] = $definition;
		}

		return Block_Inserter::insert_blocks( $post_id, $definitions, $position );
	}

	/**
	 * Validate, screen and sanitize one entry of `blocks`.
	 *
	 * Every refusal is returned as a diagnostic array rather than a WP_Error so
	 * the entry index survives the MCP bridge, which replaces WP_Error messages
	 * with a fixed string. See Abstract_Ability::run().
	 *
	 * @param int   $index Entry index, named in every diagnostic.
	 * @param mixed $block Requested definition.
	 * @return array<string, mixed> Sanitized definition, or a diagnostic payload with `success` false.
	 */
	private function prepare_entry( int $index, $block ): array {
		if ( ! is_array( $block ) ) {
			return $this->entry_diagnostic( $index, __( 'must be an object with block_name, attributes and inner_blocks.', 'designsetgo' ) );
		}

		$unknown = array_diff( array_keys( $block ), self::DEFINITION_KEYS );
		if ( ! empty( $unknown ) ) {
			return $this->entry_diagnostic(
				$index,
				sprintf(
					/* translators: %s: comma-separated unknown keys */
					__( 'has unsupported keys: %s. Use block_name, attributes and inner_blocks.', 'designsetgo' ),
					implode( ', ', $unknown )
				)
			);
		}

		$block_name = sanitize_text_field( (string) ( $block['block_name'] ?? '' ) );
		if ( ! preg_match( Block_Inserter::BLOCK_NAME_PATTERN, $block_name ) ) {
			return $this->entry_diagnostic(
				$index,
				__( 'block_name must be in "namespace/block-name" format (lowercase alphanumeric and hyphens).', 'designsetgo' )
			);
		}

		$attributes   = $block['attributes'] ?? array();
		$inner_blocks = $block['inner_blocks'] ?? array();
		$definition   = Block_Inserter::prepare_block_definition(
			$block_name,
			is_array( $attributes ) ? $attributes : array(),
			is_array( $inner_blocks ) ? $inner_blocks : array()
		);

		if ( isset( $definition['success'] ) ) {
			$definition['block_index'] = $index;
			$definition['message']     = sprintf( 'blocks[%d]: %s', $index, $definition['message'] );
		}

		return $definition;
	}

	/**
	 * A refusal that names the offending entry.
	 *
	 * @param int    $index  Entry index.
	 * @param string $reason What is wrong with it.
	 * @return array<string, mixed> Diagnostic payload.
	 */
	private function entry_diagnostic( int $index, string $reason ): array {
		return array(
			'success'     => false,
			'error_code'  => 'designsetgo_invalid_input',
			'message'     => sprintf(
				/* translators: 1: index within blocks, 2: explanation */
				__( 'Nothing was changed. blocks[%1$d] %2$s', 'designsetgo' ),
				$index,
				$reason
			),
			'block_index' => $index,
		);
	}
}

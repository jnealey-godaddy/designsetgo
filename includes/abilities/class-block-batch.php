<?php
/**
 * Shared batch schema and preparation for insertion and pure serialization.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Screen the entire batch before generating any markup. */
class Block_Batch {
	public const MAX_BLOCKS       = 50;
	private const DEFINITION_KEYS = array( 'block_name', 'attributes', 'inner_blocks' );

	/**
	 * Shared definition schema.
	 *
	 * @return array<string, mixed> Array schema.
	 */
	public static function schema(): array {
		return array(
			'type'     => 'array',
			'minItems' => 1,
			'maxItems' => self::MAX_BLOCKS,
			'items'    => array(
				'type'                 => 'object',
				'properties'           => array(
					'block_name'   => array( 'type' => 'string' ),
					'attributes'   => array(
						'type'    => 'object',
						'default' => array(),
					),
					'inner_blocks' => array_merge( Block_Inserter::get_inner_blocks_schema(), array( 'default' => array() ) ),
				),
				'required'             => array( 'block_name' ),
				'additionalProperties' => false,
			),
		);
	}

	/**
	 * Prepare a complete batch, preserving indexed refusal diagnostics.
	 *
	 * @param mixed $blocks Requested definitions.
	 * @return array|WP_Error Prepared definitions or a diagnostic/error.
	 */
	public static function prepare( $blocks ) {
		if ( ! is_array( $blocks ) || empty( $blocks ) ) {
			return new WP_Error( 'designsetgo_invalid_input', __( 'blocks must be a non-empty array of block definitions.', 'designsetgo' ) );
		}
		if ( count( $blocks ) > self::MAX_BLOCKS ) {
			return new WP_Error(
				'designsetgo_invalid_input',
				sprintf(
				/* translators: %d: maximum number of blocks */
					__( 'blocks may hold at most %d entries; split the batch into several calls.', 'designsetgo' ),
					self::MAX_BLOCKS
				)
			);
		}
		$definitions = array();
		foreach ( array_values( $blocks ) as $index => $block ) {
			$definition = self::prepare_entry( $index, $block );
			if ( isset( $definition['success'] ) ) {
				return $definition;
			}
			$definitions[] = $definition;
		}
		return $definitions;
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
	private static function prepare_entry( int $index, $block ): array {
		if ( ! is_array( $block ) ) {
			return self::entry_diagnostic( $index, __( 'must be an object with block_name, attributes and inner_blocks.', 'designsetgo' ) );
		}

		$unknown = array_diff( array_keys( $block ), self::DEFINITION_KEYS );
		if ( ! empty( $unknown ) ) {
			return self::entry_diagnostic(
				$index,
				sprintf(
					/* translators: %s: comma-separated unknown keys */
					__( 'has unsupported keys: %s. Use block_name, attributes and inner_blocks.', 'designsetgo' ),
					implode( ', ', $unknown )
				)
			);
		}

		if ( ! is_string( $block['block_name'] ?? null ) || ( isset( $block['attributes'] ) && ! is_array( $block['attributes'] ) ) || ( isset( $block['inner_blocks'] ) && ! is_array( $block['inner_blocks'] ) ) ) {
			return self::entry_diagnostic( $index, __( 'has invalid definition field types.', 'designsetgo' ) );
		}

		$block_name = sanitize_text_field( $block['block_name'] );
		if ( ! preg_match( Block_Inserter::BLOCK_NAME_PATTERN, $block_name ) ) {
			return self::entry_diagnostic(
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
	private static function entry_diagnostic( int $index, string $reason ): array {
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

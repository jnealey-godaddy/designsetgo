<?php
/**
 * Serialize native blocks without rendering them or changing a post.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Abilities\Info;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Block_Batch;
use DesignSetGo\Abilities\Block_Inserter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Produce editor markup through the same serializers as batch insertion. */
class Serialize_Blocks extends Abstract_Ability {
	/**
	 * Ability name.
	 *
	 * @return string Ability name.
	 */
	public function get_name(): string {
		return 'designsetgo/serialize-blocks';
	}

	/**
	 * Ability registration.
	 *
	 * @return array<string, mixed> Ability registration.
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Serialize Blocks', 'designsetgo' ),
			'description'         => __( 'Returns stored WordPress block markup for a complete native block tree. Does not create or update posts, render dynamic blocks, or return frontend HTML. Refuses the entire batch on invalid input.', 'designsetgo' ),
			'category'            => 'info',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array( 'blocks' => Block_Batch::schema() ),
				'required'             => array( 'blocks' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'content'     => array( 'type' => 'string' ),
					'block_count' => array( 'type' => 'integer' ),
					'error_code'  => array( 'type' => 'string' ),
					'message'     => array( 'type' => 'string' ),
					'block_index' => array( 'type' => 'integer' ),
				),
				'required'   => array( 'success' ),
			),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'annotations'         => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		);
	}

	/**
	 * Whether the caller can compose content.
	 *
	 * @return bool Whether the caller can compose content.
	 */
	public function check_permission_callback(): bool {
		return $this->check_permission( 'edit_posts' );
	}

	/**
	 * Serialize a fully prepared tree, with indexed structural diagnostics.
	 *
	 * @param array<string, mixed> $input Requested definitions.
	 * @return array<string, mixed> Markup or refusal; never partial content.
	 */
	public function execute( array $input ): array {
		$definitions = Block_Batch::prepare( $input['blocks'] ?? null );
		if ( is_wp_error( $definitions ) ) {
			return array(
				'success'    => false,
				'error_code' => $definitions->get_error_code(),
				'message'    => $definitions->get_error_message(),
			);
		}
		if ( isset( $definitions['success'] ) ) {
			return $definitions;
		}

		$blocks = array();
		foreach ( $definitions as $index => $definition ) {
			$parsed   = parse_blocks( Block_Inserter::build_block_markup( $definition['block_name'], $definition['attributes'], $definition['inner_blocks'] ) );
			$problems = Block_Inserter::validate_block_tree( $parsed, (string) $index );
			if ( ! empty( $problems ) ) {
				return array(
					'success'     => false,
					'error_code'  => 'designsetgo_invalid_block_structure',
					'message'     => sprintf( 'blocks[%d]: %s', $index, implode( '; ', $problems ) ),
					'block_index' => $index,
					'problems'    => $problems,
				);
			}
			array_push( $blocks, ...$parsed );
		}
		return array(
			'success'     => true,
			'content'     => serialize_blocks( $blocks ),
			'block_count' => count( $definitions ),
		);
	}
}

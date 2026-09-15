<?php
/**
 * PHP mirror of the agent block tree contract owned by src/engine/tree.js.
 *
 * An agent submits a JSON block tree to the designsetgo/build-page ability
 * (Task 19). PHP cannot run a block's save(), so this class validates the
 * tree's STRUCTURE up front; a headless browser does the real serialization
 * afterward. Codes, paths, and problem order deliberately mirror
 * src/engine/tree.js's checkTreeShape().
 *
 * Orchestrates stage order and precedence only; shape checks live in
 * Tree_Shape and attribute-schema checks in Tree_Attributes (split out to
 * keep each file under the plan's line-count cap).
 *
 * A plain static helper, not an Abstract_Ability. It lives in this directory
 * so Task 18/19's abilities can reach it, but Abilities_Registry only
 * instantiates classes here that are actual Abstract_Ability subclasses, so
 * it is never registered as an ability itself.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.6.0
 */

namespace DesignSetGo\Abilities\Agent_Build;

use DesignSetGo\Abilities\Block_Inserter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tree_Validator class.
 */
class Tree_Validator {

	/** 1 MB, matching the designsetgo/build-page ability's documented limit. */
	const MAX_TREE_BYTES = 1048576;

	/**
	 * Validate an agent-submitted block tree. Never throws.
	 *
	 * Stages run in order, each only once every earlier one found nothing:
	 * version/shape, size, unknown block, then attribute schema + placement.
	 *
	 * @param mixed $tree Candidate tree, typically json_decode( $json, true ).
	 * @return array<int, array{code: string, path: string, message: string}> Problems; empty when well formed.
	 */
	public static function validate( $tree ): array {
		$shape_problems = Tree_Shape::check( $tree );
		if ( ! empty( $shape_problems ) ) {
			return $shape_problems;
		}

		$size_problems = self::check_size( $tree );
		if ( ! empty( $size_problems ) ) {
			return $size_problems;
		}

		$unknown_problems = self::check_unknown_blocks( $tree['blocks'] );
		if ( ! empty( $unknown_problems ) ) {
			return $unknown_problems;
		}

		return array_merge(
			Tree_Attributes::check( $tree['blocks'] ),
			self::check_placement( $tree['blocks'] )
		);
	}

	/**
	 * Reject a tree whose JSON encoding exceeds the 1 MB limit.
	 *
	 * @param array $tree Well-shaped tree.
	 * @return array<int, array{code: string, path: string, message: string}> Problems.
	 */
	private static function check_size( array $tree ): array {
		$json = wp_json_encode( $tree );
		$size = is_string( $json ) ? strlen( $json ) : 0;

		if ( $size <= self::MAX_TREE_BYTES ) {
			return array();
		}

		return array(
			self::problem(
				'designsetgo_tree_too_large',
				'blocks',
				sprintf(
					/* translators: 1: tree size in bytes, 2: maximum size in bytes */
					__( 'The tree is %1$s bytes, exceeding the %2$s byte limit.', 'designsetgo' ),
					number_format_i18n( $size ),
					number_format_i18n( self::MAX_TREE_BYTES )
				)
			),
		);
	}

	/**
	 * Reject block names WordPress has no registered block type for.
	 *
	 * @param array  $blocks      Well-shaped block list.
	 * @param string $parent_path Parent path, or '' for the root.
	 * @return array<int, array{code: string, path: string, message: string}> Problems.
	 */
	private static function check_unknown_blocks( array $blocks, string $parent_path = '' ): array {
		$problems = array();
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( $blocks as $index => $node ) {
			$path = Tree_Shape::child_path( $parent_path, (int) $index );

			if ( ! $registry->is_registered( $node['name'] ) ) {
				/* translators: %s: block name */
				$problems[] = self::problem( 'designsetgo_unknown_block', $path, sprintf( __( '%s is not a registered block type.', 'designsetgo' ), $node['name'] ) );
			}

			if ( ! empty( $node['innerBlocks'] ) ) {
				$problems = array_merge( $problems, self::check_unknown_blocks( $node['innerBlocks'], $path ) );
			}
		}

		return $problems;
	}

	/**
	 * Reuse Block_Inserter's own child-placement rule, converting its
	 * dot-joined paths (e.g. "1.0") to this contract's format (e.g.
	 * "blocks[1].innerBlocks[0]").
	 *
	 * @param array $blocks Well-shaped, fully-registered block list.
	 * @return array<int, array{code: string, path: string, message: string}> Problems.
	 */
	private static function check_placement( array $blocks ): array {
		$problems = array();

		foreach ( Block_Inserter::find_tree_placement_problems( $blocks ) as $entry ) {
			$path = '';
			foreach ( explode( '.', $entry['path'] ) as $segment ) {
				$path = Tree_Shape::child_path( $path, (int) $segment );
			}

			$problems[] = self::problem( 'designsetgo_invalid_child_placement', $path, $entry['reason'] );
		}

		return $problems;
	}

	/**
	 * Build a single problem entry.
	 *
	 * @param string $code    Problem code.
	 * @param string $path    Path to the offending value.
	 * @param string $message Human-readable message.
	 * @return array{code: string, path: string, message: string} Problem entry.
	 */
	private static function problem( string $code, string $path, string $message ): array {
		return array(
			'code'    => $code,
			'path'    => $path,
			'message' => $message,
		);
	}
}

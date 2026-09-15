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
 * Stage order: version/shape (Tree_Shape) -> size -> unknown block, each
 * gating the next - a problem at any of these three stops validation and
 * returns immediately, since a later stage cannot meaningfully run against
 * a tree that failed an earlier one (unknown block types, for instance,
 * have no schema to check attributes or placement against). Once all three
 * pass, attribute schema (Tree_Attributes) and child placement are
 * independent of each other and run together, their problems collected
 * into one list - this saves a remote agent a round trip it does not need,
 * since neither check's outcome depends on the other's.
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
	 * Exact stage order: version/shape -> size -> unknown block -> {
	 * attribute schema + child placement, collected together }. The first
	 * three each gate the next (a problem stops validation right there);
	 * the last two are independent of each other once the tree is known-shaped
	 * and every block type is registered, so both always run and their
	 * problems are merged into one list rather than one gating the other.
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
			Tree_Shape::problem(
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
				$problems[] = Tree_Shape::problem( 'designsetgo_unknown_block', $path, sprintf( __( '%s is not a registered block type.', 'designsetgo' ), $node['name'] ) );
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

			$problems[] = Tree_Shape::problem( 'designsetgo_invalid_child_placement', $path, $entry['reason'] );
		}

		return $problems;
	}
}

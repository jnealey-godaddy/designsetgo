<?php
/**
 * Child-placement checks for the agent block tree contract.
 *
 * Placement is judged ONLY by the block types' own block.json metadata, as
 * registered in WP_Block_Type_Registry - the same rules the block editor
 * enforces:
 *
 * - `parent`: the block must be a direct child of one of these blocks.
 * - `ancestor`: one of these blocks must be somewhere above it.
 * - `allowedBlocks` (WP_Block_Type::$allowed_blocks): the block only
 *   accepts these blocks as direct children.
 *
 * Whether a block can hold children at all is not judged here: PHP cannot
 * know what a block's save() emits. The browser engine catches children a
 * save() drops (designsetgo_dropped_inner_blocks) when it assembles the tree.
 *
 * Split out of Tree_Validator to keep each file under the line-count cap.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.6.0
 */

namespace DesignSetGo\Abilities\Agent_Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tree_Placement class.
 *
 * A plain static helper, not an Abstract_Ability - see Build_Store's
 * docblock for why this directory holds non-ability classes.
 */
class Tree_Placement {

	/**
	 * Check every node's placement against block.json metadata.
	 *
	 * @param array              $blocks      Well-shaped, fully-registered block list.
	 * @param string             $parent_path Parent path, or '' for the root.
	 * @param array<int, string> $ancestors   Names of every ancestor, nearest last.
	 * @return array<int, array{code: string, path: string, message: string}> Problems.
	 */
	public static function check( array $blocks, string $parent_path = '', array $ancestors = array() ): array {
		$problems    = array();
		$registry    = \WP_Block_Type_Registry::get_instance();
		$parent_name = empty( $ancestors ) ? null : $ancestors[ count( $ancestors ) - 1 ];
		$allowed     = null !== $parent_name ? self::list_property( $registry->get_registered( $parent_name ), 'allowed_blocks' ) : array();

		foreach ( $blocks as $index => $node ) {
			$path       = Tree_Shape::child_path( $parent_path, (int) $index );
			$name       = $node['name'];
			$block_type = $registry->get_registered( $name );

			$message = self::placement_problem( $block_type, $name, $parent_name, $ancestors, $allowed );
			if ( null !== $message ) {
				$problems[] = Tree_Shape::problem( 'designsetgo_invalid_child_placement', $path, $message );
			}

			if ( ! empty( $node['innerBlocks'] ) ) {
				$problems = array_merge(
					$problems,
					self::check( $node['innerBlocks'], $path, array_merge( $ancestors, array( $name ) ) )
				);
			}
		}

		return $problems;
	}

	/**
	 * Describe why a single node is misplaced, if it is.
	 *
	 * @param \WP_Block_Type|null $block_type The node's registered type.
	 * @param string              $name       The node's block name.
	 * @param string|null         $parent_name Direct parent's name, or null at the root.
	 * @param array<int, string>  $ancestors  Every ancestor's name.
	 * @param array<int, string>  $allowed    The direct parent's allowedBlocks (empty when unrestricted).
	 * @return string|null Message, or null when the placement is fine.
	 */
	private static function placement_problem( $block_type, string $name, ?string $parent_name, array $ancestors, array $allowed ): ?string {
		if ( ! empty( $allowed ) && ! in_array( $name, $allowed, true ) ) {
			return sprintf(
				/* translators: 1: parent block name, 2: comma-separated allowed block names, 3: the block name that was given */
				__( '%1$s only accepts these child blocks: %2$s (got %3$s).', 'designsetgo' ),
				$parent_name,
				implode( ', ', $allowed ),
				$name
			);
		}

		$parents = self::list_property( $block_type, 'parent' );
		if ( ! empty( $parents ) && ! in_array( $parent_name, $parents, true ) ) {
			return sprintf(
				/* translators: 1: block name, 2: comma-separated parent block names */
				__( '%1$s must be placed directly inside one of: %2$s.', 'designsetgo' ),
				$name,
				implode( ', ', $parents )
			);
		}

		$required_ancestors = self::list_property( $block_type, 'ancestor' );
		if ( ! empty( $required_ancestors ) && empty( array_intersect( $required_ancestors, $ancestors ) ) ) {
			return sprintf(
				/* translators: 1: block name, 2: comma-separated ancestor block names */
				__( '%1$s must be placed somewhere inside one of: %2$s.', 'designsetgo' ),
				$name,
				implode( ', ', $required_ancestors )
			);
		}

		return null;
	}

	/**
	 * Read a block-name list property (`parent`, `ancestor`, `allowed_blocks`)
	 * off a registered block type, tolerating a missing type or property.
	 *
	 * @param \WP_Block_Type|null $block_type Registered block type.
	 * @param string              $property   Property name.
	 * @return array<int, string> Block names; empty when unrestricted.
	 */
	private static function list_property( $block_type, string $property ): array {
		if ( ! $block_type instanceof \WP_Block_Type || ! isset( $block_type->{$property} ) || ! is_array( $block_type->{$property} ) ) {
			return array();
		}

		return array_values( array_filter( $block_type->{$property}, 'is_string' ) );
	}
}

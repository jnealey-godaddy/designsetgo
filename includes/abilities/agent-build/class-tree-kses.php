<?php
/**
 * KSES filtering for agent-submitted block trees.
 *
 * A pending tree is assembled and saved later, in the editor, by whoever
 * opens the post - possibly a user with far more privilege than the one who
 * submitted it. So a submitter without `unfiltered_html` has every string
 * inside every node's `attributes` run through wp_kses_post() before the
 * tree is stored, mirroring the filtering core applies to block attributes
 * when such a user saves content themselves (filter_block_kses_value(), run
 * from content_save_pre).
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
 * Tree_Kses class.
 *
 * A plain static helper, not an Abstract_Ability - see Build_Store's
 * docblock for why this directory holds non-ability classes.
 */
class Tree_Kses {

	/**
	 * Filter a well-shaped tree's attribute strings, recursively.
	 *
	 * @param array $tree Tree already accepted by Tree_Validator.
	 * @return array The same tree with every attribute string KSES-filtered.
	 */
	public static function filter_tree( array $tree ): array {
		if ( isset( $tree['blocks'] ) && is_array( $tree['blocks'] ) ) {
			$tree['blocks'] = self::filter_blocks( $tree['blocks'] );
		}

		return $tree;
	}

	/**
	 * Filter every node in a block list, recursing into innerBlocks.
	 *
	 * @param array<int, mixed> $blocks Block nodes.
	 * @return array<int, mixed> Filtered block nodes.
	 */
	private static function filter_blocks( array $blocks ): array {
		foreach ( $blocks as $index => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			if ( isset( $node['attributes'] ) && is_array( $node['attributes'] ) ) {
				$node['attributes'] = self::filter_value( $node['attributes'] );
			}

			if ( isset( $node['innerBlocks'] ) && is_array( $node['innerBlocks'] ) ) {
				$node['innerBlocks'] = self::filter_blocks( $node['innerBlocks'] );
			}

			$blocks[ $index ] = $node;
		}

		return $blocks;
	}

	/**
	 * KSES-filter a string, or every string nested inside an array.
	 * Non-string scalars (numbers, booleans, null) pass through unchanged.
	 *
	 * @param mixed $value Attribute value.
	 * @return mixed Filtered value.
	 */
	private static function filter_value( $value ) {
		if ( is_string( $value ) ) {
			return wp_kses_post( $value );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::filter_value( $item );
			}
		}

		return $value;
	}
}

<?php
/**
 * KSES filtering for agent-submitted block trees.
 *
 * A pending tree is assembled and saved later, in the editor, by whoever
 * opens the post - possibly a user with far more privilege than the one who
 * submitted it. So a submitter without `unfiltered_html` has every node's
 * `attributes` run through core's own block attribute filter,
 * filter_block_kses_value(), with the node as block context - exactly what
 * core applies to a block comment's attributes when such a user saves
 * content themselves (wp_kses() -> pre_kses -> filter_block_kses()). That
 * covers attribute keys and the template part tagName check, not just
 * string values.
 *
 * This only filters attribute values. The markup those values render into
 * is filtered separately, in the editor, through Build_Sanitize_REST.
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
				$node['attributes'] = self::filter_attributes( $node['attributes'], isset( $node['name'] ) && is_string( $node['name'] ) ? $node['name'] : '' );
			}

			if ( isset( $node['innerBlocks'] ) && is_array( $node['innerBlocks'] ) ) {
				$node['innerBlocks'] = self::filter_blocks( $node['innerBlocks'] );
			}

			$blocks[ $index ] = $node;
		}

		return $blocks;
	}

	/**
	 * Filter one node's attributes as core filters a parsed block's attrs.
	 * Non-string scalars (numbers, booleans, null) pass through unchanged.
	 *
	 * @param array  $attributes Node attributes.
	 * @param string $block_name Node block name, for core's block context.
	 * @return array Filtered attributes.
	 */
	private static function filter_attributes( array $attributes, string $block_name ): array {
		// The block context argument arrived in WP 6.5.5; this plugin
		// requires 6.7, and PHP ignores the extra argument on older cores.
		return filter_block_kses_value(
			$attributes,
			'post',
			wp_allowed_protocols(),
			array( 'blockName' => $block_name )
		);
	}
}

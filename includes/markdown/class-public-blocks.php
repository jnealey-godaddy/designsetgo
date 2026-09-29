<?php
/**
 * Recursively restrict parsed blocks before custom Markdown handlers see them.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Markdown;

defined( 'ABSPATH' ) || exit;

/** Shared visibility gates for every block and descendant. */
class Public_Blocks {
	/**
	 * Remove hidden ancestors and children, preserving parser placeholder order.
	 *
	 * @param array $blocks  Parsed blocks.
	 * @param array $context Explicit post context.
	 * @return array Visible parsed blocks.
	 */
	public static function filter( array $blocks, array $context ): array {
		$output = array();
		foreach ( $blocks as $block ) {
			if ( ! \DesignSetGo\BlockVisibility::matches( $block['attrs']['dsgoVisibility'] ?? null, $context ) ) {
				continue;
			}
			$children = $block['innerBlocks'] ?? array();
			$visible = array();
			$keep = array();
			foreach ( $children as $index => $child ) {
				$filtered = self::filter( array( $child ), $context );
				$keep[ $index ] = ! empty( $filtered );
				if ( $keep[ $index ] ) {
					$visible[] = $filtered[0];
				}
			}
			$block['innerBlocks'] = $visible;
			$index = 0;
			$inner_content = array();
			foreach ( $block['innerContent'] ?? array() as $chunk ) {
				if ( is_string( $chunk ) || ! empty( $keep[ $index++ ] ) ) {
					$inner_content[] = $chunk;
				}
			}
			$block['innerContent'] = $inner_content;
			$output[] = $block;
		}
		return $output;
	}
}

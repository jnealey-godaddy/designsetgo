<?php
/**
 * Structural (version/root/node) checks for the agent block tree contract.
 *
 * A PHP mirror of src/engine/tree.js's checkTreeShape()/checkBlocksShape().
 * Split out of Tree_Validator (Task 17) to keep each file under the plan's
 * line-count cap; Tree_Validator orchestrates stage order, this file only
 * knows the tree's shape.
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
 * Tree_Shape class.
 */
class Tree_Shape {

	/** Matches TREE_VERSION in src/engine/tree.js. */
	const TREE_VERSION = 1;

	/** Matches BLOCK_NAME_RE in src/engine/tree.js. */
	const BLOCK_NAME_PATTERN = '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/';

	/**
	 * Mirrors checkTreeShape(): an invalid root reports only
	 * designsetgo_invalid_tree and skips the version check.
	 *
	 * @param mixed $tree Candidate tree.
	 * @return array<int, array{code: string, path: string, message: string}> Problems.
	 */
	public static function check( $tree ): array {
		$problems = array();

		if ( ! self::is_object( $tree ) || ! self::is_list_like( $tree['blocks'] ?? null ) ) {
			$problems[] = self::problem( 'designsetgo_invalid_tree', 'blocks', __( 'The tree must be an object with a "blocks" array.', 'designsetgo' ) );
			return $problems;
		}

		$version = array_key_exists( 'version', $tree ) ? $tree['version'] : null;
		if ( self::TREE_VERSION !== $version ) {
			$problems[] = self::problem(
				'designsetgo_unsupported_tree_version',
				'version',
				sprintf(
					/* translators: 1: given version (JSON-encoded), 2: expected version number */
					__( 'Unsupported tree version %1$s; expected %2$d.', 'designsetgo' ),
					wp_json_encode( $version ),
					self::TREE_VERSION
				)
			);
		}

		self::check_blocks( $tree['blocks'], '', $problems );

		return $problems;
	}

	/**
	 * Path for the block at `index` beneath `parent_path`. Matches
	 * childPath() in src/engine/tree.js. Public: every other validation
	 * stage builds its own paths through this.
	 *
	 * @param string $parent_path Parent path, or '' for the root.
	 * @param int    $index       Index within the list.
	 * @return string e.g. `blocks[0]` or `blocks[0].innerBlocks[1]`.
	 */
	public static function child_path( string $parent_path, int $index ): string {
		return '' !== $parent_path
			? sprintf( '%s.innerBlocks[%d]', $parent_path, $index )
			: sprintf( 'blocks[%d]', $index );
	}

	/**
	 * Recursive structural check of a block list, appending problems in place.
	 *
	 * @param array<int, mixed> $blocks      Candidate block nodes.
	 * @param string            $parent_path Parent path, or '' for the root.
	 * @param array             $problems    Accumulator, by reference.
	 * @return void
	 */
	private static function check_blocks( array $blocks, string $parent_path, array &$problems ): void {
		foreach ( $blocks as $index => $node ) {
			$path = self::child_path( $parent_path, (int) $index );

			if ( ! self::is_object( $node ) ) {
				$problems[] = self::problem( 'designsetgo_invalid_block_definition', $path, __( 'Block definition must be an object.', 'designsetgo' ) );
				continue;
			}

			$name = $node['name'] ?? null;
			if ( ! is_string( $name ) || ! preg_match( self::BLOCK_NAME_PATTERN, $name ) ) {
				$problems[] = self::problem(
					'designsetgo_invalid_block_definition',
					$path,
					sprintf(
						/* translators: 1: expected pattern, 2: the given name (JSON-encoded) */
						__( 'Block name must match %1$s (got %2$s).', 'designsetgo' ),
						self::BLOCK_NAME_PATTERN,
						wp_json_encode( $name )
					)
				);
			}

			if ( array_key_exists( 'attributes', $node ) && ! self::is_object( $node['attributes'] ) ) {
				$problems[] = self::problem( 'designsetgo_invalid_block_definition', $path, __( '"attributes" must be a plain object when present.', 'designsetgo' ) );
			}

			$has_inner_blocks = array_key_exists( 'innerBlocks', $node );
			if ( $has_inner_blocks && ! self::is_list_like( $node['innerBlocks'] ) ) {
				$problems[] = self::problem( 'designsetgo_invalid_block_definition', $path, __( '"innerBlocks" must be an array when present.', 'designsetgo' ) );
			} elseif ( $has_inner_blocks ) {
				self::check_blocks( $node['innerBlocks'], $path, $problems );
			}
		}
	}

	/**
	 * Whether `value` is object-like: an associative array, or empty (JSON's
	 * `{}` and `[]` decode identically in PHP, so empty satisfies either
	 * shape). Mirrors isPlainObject() in src/engine/tree.js.
	 *
	 * @param mixed $value Candidate value.
	 * @return bool True when object-like.
	 */
	private static function is_object( $value ): bool {
		return is_array( $value ) && ! self::is_sequential_and_nonempty( $value );
	}

	/**
	 * Whether `value` is list-like: a sequential array, or empty.
	 *
	 * @param mixed $value Candidate value.
	 * @return bool True when list-like.
	 */
	private static function is_list_like( $value ): bool {
		return is_array( $value ) && ( array() === $value || self::is_sequential_and_nonempty( $value ) );
	}

	/**
	 * Whether `candidate` is non-empty with keys 0, 1, 2, ... in order.
	 *
	 * @param mixed $candidate Candidate value.
	 * @return bool True when a non-empty sequential array.
	 */
	private static function is_sequential_and_nonempty( $candidate ): bool {
		return is_array( $candidate ) && array() !== $candidate && array_keys( $candidate ) === range( 0, count( $candidate ) - 1 );
	}

	/**
	 * Build a single problem entry. Public: shared by every validation stage
	 * across Tree_Validator, Tree_Shape, and Tree_Attributes, so the
	 * {code, path, message} shape has exactly one implementation.
	 *
	 * @param string $code    Problem code.
	 * @param string $path    Path to the offending value.
	 * @param string $message Human-readable message.
	 * @return array{code: string, path: string, message: string} Problem entry.
	 */
	public static function problem( string $code, string $path, string $message ): array {
		return array(
			'code'    => $code,
			'path'    => $path,
			'message' => $message,
		);
	}
}

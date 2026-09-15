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
	 * Reshapes a well-formed block list for a JSON REST response: every
	 * node's `attributes` becomes an object (`stdClass`) when empty, so
	 * `wp_json_encode()` emits `{}` rather than `[]`. PHP's `json_decode(
	 * $json, true )` cannot tell an empty JSON object from an empty JSON
	 * array - both become `array()` - so a tree stored via
	 * `Build_Store::store()` and later re-encoded for `Build_REST`'s GET
	 * response would otherwise silently turn `"attributes": {}` into
	 * `"attributes": []`, which the browser's `checkTreeShape()` (the same
	 * "must be a plain object" rule `self::is_object()` mirrors here)
	 * correctly rejects.
	 *
	 * Only `attributes` is reshaped this way - `innerBlocks` stays a plain
	 * list, since an empty `[]` there is exactly what the contract expects.
	 * A non-empty `attributes` array is left as-is (a non-empty associative
	 * array always round-trips as a JSON object), except that individual
	 * attribute values matching an empty array are also promoted to
	 * `stdClass` when the block type's own registered schema says that
	 * attribute's `type` is (only) `object` - e.g. `style: {}` on
	 * `designsetgo/section`, which declares `"style": {"type": "object"}` in
	 * its block.json.
	 *
	 * Called only from `Build_REST::get_item()` - the sole consumer of a
	 * stored pending tree - so this never touches what's actually persisted
	 * in `_dsgo_pending_tree` post meta.
	 *
	 * @param array<int, mixed> $blocks Well-shaped block list (already past `check()`).
	 * @return array<int, mixed> The same list, with `attributes` reshaped for JSON encoding.
	 */
	public static function to_response_shape( array $blocks ): array {
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( $blocks as $index => $node ) {
			if ( is_array( $node ) ) {
				$blocks[ $index ] = self::normalize_node_for_response( $node, $registry );
			}
		}

		return $blocks;
	}

	/**
	 * Reshapes one node's `attributes` (if present) and recurses into
	 * `innerBlocks` (if present) via `to_response_shape()`.
	 *
	 * @param array                   $node     Well-shaped block node.
	 * @param \WP_Block_Type_Registry $registry Registry to resolve the node's attribute schema from.
	 * @return array The reshaped node.
	 */
	private static function normalize_node_for_response( array $node, \WP_Block_Type_Registry $registry ): array {
		if ( array_key_exists( 'attributes', $node ) && is_array( $node['attributes'] ) ) {
			$node['attributes'] = self::normalize_attributes_for_response(
				$node['attributes'],
				is_string( $node['name'] ?? null ) ? $node['name'] : '',
				$registry
			);
		}

		if ( array_key_exists( 'innerBlocks', $node ) && is_array( $node['innerBlocks'] ) ) {
			$node['innerBlocks'] = self::to_response_shape( $node['innerBlocks'] );
		}

		return $node;
	}

	/**
	 * Reshapes one node's `attributes` value: `stdClass` when the whole
	 * object is empty, otherwise the same array with any empty-array
	 * object-typed attribute value promoted to `stdClass` too.
	 *
	 * @param array                   $attributes Node's `attributes` array (possibly empty).
	 * @param string                  $block_name Owning block's registered name.
	 * @param \WP_Block_Type_Registry $registry   Registry to resolve the block type from.
	 * @return array|\stdClass Reshaped attributes.
	 */
	private static function normalize_attributes_for_response( array $attributes, string $block_name, \WP_Block_Type_Registry $registry ) {
		if ( empty( $attributes ) ) {
			return new \stdClass();
		}

		$block_type = '' !== $block_name ? $registry->get_registered( $block_name ) : null;

		if ( ! $block_type || empty( $block_type->attributes ) ) {
			return $attributes;
		}

		foreach ( $attributes as $key => $value ) {
			if ( is_array( $value ) && empty( $value ) && self::is_object_typed_attribute( $block_type, (string) $key ) ) {
				$attributes[ $key ] = new \stdClass();
			}
		}

		return $attributes;
	}

	/**
	 * Whether `$block_type`'s registered schema declares `$attribute_name`
	 * with a `type` that is (only) `object` - a bare `"type": "object"`, or
	 * a `type` array whose every entry is `"object"`.
	 *
	 * @param \WP_Block_Type $block_type     Registered block type.
	 * @param string         $attribute_name Attribute name.
	 * @return bool True when the attribute's declared type is object-only.
	 */
	private static function is_object_typed_attribute( \WP_Block_Type $block_type, string $attribute_name ): bool {
		$schema = $block_type->attributes[ $attribute_name ] ?? null;
		if ( ! is_array( $schema ) || ! array_key_exists( 'type', $schema ) ) {
			return false;
		}

		$types = is_array( $schema['type'] ) ? $schema['type'] : array( $schema['type'] );

		return array() !== $types && array() === array_diff( $types, array( 'object' ) );
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

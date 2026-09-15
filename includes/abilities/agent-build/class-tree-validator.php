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

	/** Matches TREE_VERSION in src/engine/tree.js. */
	const TREE_VERSION = 1;

	/** Matches BLOCK_NAME_RE in src/engine/tree.js. */
	const BLOCK_NAME_PATTERN = '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/';

	/** 1 MB, matching the designsetgo/build-page ability's documented limit. */
	const MAX_TREE_BYTES = 1048576;

	/** Schema keys describing a bindings source, stripped before validation. */
	const BINDING_KEYS = array( 'source', 'selector', 'attribute', 'query', 'role' );

	/** JSON Schema's own built-in types; anything else (e.g. core's "rich-text") can't go through rest_validate_value_from_schema(). */
	const JSON_SCHEMA_TYPES = array( 'array', 'object', 'string', 'number', 'integer', 'boolean', 'null' );

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
		$shape_problems = self::check_shape( $tree );
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
			self::check_attribute_schemas( $tree['blocks'] ),
			self::check_placement( $tree['blocks'] )
		);
	}

	/**
	 * Mirrors checkTreeShape(): an invalid root reports only
	 * designsetgo_invalid_tree and skips the version check.
	 *
	 * @param mixed $tree Candidate tree.
	 * @return array<int, array{code: string, path: string, message: string}> Problems.
	 */
	private static function check_shape( $tree ): array {
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

		self::check_blocks_shape( $tree['blocks'], '', $problems );

		return $problems;
	}

	/**
	 * Recursive structural check of a block list, appending problems in place.
	 *
	 * @param array<int, mixed> $blocks      Candidate block nodes.
	 * @param string            $parent_path Parent path, or '' for the root.
	 * @param array             $problems    Accumulator, by reference.
	 * @return void
	 */
	private static function check_blocks_shape( array $blocks, string $parent_path, array &$problems ): void {
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
				self::check_blocks_shape( $node['innerBlocks'], $path, $problems );
			}
		}
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
			$path = self::child_path( $parent_path, (int) $index );

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
	 * Validate provided attributes against the block type's own schema.
	 * Attributes the block type does not declare are allowed unchecked -
	 * extensions add attributes only JS knows about.
	 *
	 * @param array  $blocks      Well-shaped, fully-registered block list.
	 * @param string $parent_path Parent path, or '' for the root.
	 * @return array<int, array{code: string, path: string, message: string}> Problems.
	 */
	private static function check_attribute_schemas( array $blocks, string $parent_path = '' ): array {
		$problems = array();
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( $blocks as $index => $node ) {
			$path       = self::child_path( $parent_path, (int) $index );
			$block_type = $registry->get_registered( $node['name'] );
			$attributes = $node['attributes'] ?? array();

			if ( $block_type && ! empty( $block_type->attributes ) ) {
				foreach ( $attributes as $attribute_name => $value ) {
					$schema = $block_type->attributes[ $attribute_name ] ?? null;
					if ( ! is_array( $schema ) ) {
						continue;
					}

					$clean_schema = self::strip_binding_keys( $schema );
					if ( ! self::has_validatable_type( $clean_schema ) ) {
						continue; // e.g. core's "rich-text" content attribute - not a JSON Schema type rest_validate_value_from_schema() understands.
					}

					$result = rest_validate_value_from_schema( $value, $clean_schema, $attribute_name );
					if ( is_wp_error( $result ) ) {
						$problems[] = self::problem(
							'designsetgo_invalid_attribute',
							$path,
							sprintf(
								/* translators: 1: attribute name, 2: validation error message */
								__( '%1$s: %2$s', 'designsetgo' ),
								$attribute_name,
								$result->get_error_message()
							)
						);
					}
				}
			}

			if ( ! empty( $node['innerBlocks'] ) ) {
				$problems = array_merge( $problems, self::check_attribute_schemas( $node['innerBlocks'], $path ) );
			}
		}

		return $problems;
	}

	/**
	 * Whether a schema's `type` (if any) is entirely built-in JSON Schema
	 * types, i.e. safe to hand to rest_validate_value_from_schema().
	 *
	 * @param array $schema Attribute schema, binding keys already stripped.
	 * @return bool True when validatable.
	 */
	private static function has_validatable_type( array $schema ): bool {
		if ( ! array_key_exists( 'type', $schema ) ) {
			return true;
		}

		$types = is_array( $schema['type'] ) ? $schema['type'] : array( $schema['type'] );

		return array() === array_diff( $types, self::JSON_SCHEMA_TYPES );
	}

	/**
	 * Strip block-bindings descriptor keys from an attribute schema.
	 *
	 * @param array $schema Raw attribute schema from block.json.
	 * @return array Schema with binding keys removed.
	 */
	private static function strip_binding_keys( array $schema ): array {
		$clean = array_diff_key( $schema, array_flip( self::BINDING_KEYS ) );

		foreach ( array_keys( $clean ) as $key ) {
			if ( 0 === strpos( $key, '__experimental' ) ) {
				unset( $clean[ $key ] );
			}
		}

		return $clean;
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
				$path = self::child_path( $path, (int) $segment );
			}

			$problems[] = self::problem( 'designsetgo_invalid_child_placement', $path, $entry['reason'] );
		}

		return $problems;
	}

	/**
	 * Path for the block at `index` beneath `parent_path`. Matches
	 * childPath() in src/engine/tree.js.
	 *
	 * @param string $parent_path Parent path, or '' for the root.
	 * @param int    $index       Index within the list.
	 * @return string e.g. `blocks[0]` or `blocks[0].innerBlocks[1]`.
	 */
	private static function child_path( string $parent_path, int $index ): string {
		return '' !== $parent_path
			? sprintf( '%s.innerBlocks[%d]', $parent_path, $index )
			: sprintf( 'blocks[%d]', $index );
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
	 * Whether `array` is non-empty with keys 0, 1, 2, ... in order.
	 *
	 * @param mixed $candidate Candidate value.
	 * @return bool True when a non-empty sequential array.
	 */
	private static function is_sequential_and_nonempty( $candidate ): bool {
		return is_array( $candidate ) && array() !== $candidate && array_keys( $candidate ) === range( 0, count( $candidate ) - 1 );
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

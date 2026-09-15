<?php
/**
 * Attribute-schema checks for the agent block tree contract.
 *
 * Validates every provided attribute value against its block type's own
 * schema, via rest_validate_value_from_schema(). Split out of Tree_Validator
 * (Task 17) to keep each file under the plan's line-count cap;
 * Tree_Validator orchestrates stage order, this file only knows attributes.
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
 * Tree_Attributes class.
 */
class Tree_Attributes {

	/** Schema keys describing a bindings source, stripped before validation. */
	const BINDING_KEYS = array( 'source', 'selector', 'attribute', 'query', 'role' );

	/** JSON Schema's own built-in types; anything else (e.g. core's "rich-text") can't go through rest_validate_value_from_schema(). */
	const JSON_SCHEMA_TYPES = array( 'array', 'object', 'string', 'number', 'integer', 'boolean', 'null' );

	/**
	 * Validate provided attributes against the block type's own schema, and
	 * reject a name unknown to both PHP and JS.
	 *
	 * An attribute name PHP's own `WP_Block_Type->attributes` doesn't
	 * declare is checked against the committed JS-registered-attribute
	 * manifest (Attribute_Manifest) before being accepted: known to JS
	 * (a block-support attribute like `anchor`, added only by client-side
	 * block-support JS, or a DesignSetGo extension attribute) is still
	 * allowed unchecked - only a name unknown to BOTH is
	 * `designsetgo_unknown_attribute`, with a "did you mean" suggestion
	 * (Attribute_Suggest) when a known name is a plausible typo away. This
	 * is the PHP mirror of findUnknownAttributes() in
	 * src/engine/attributes.js - the browser engine's check is
	 * authoritative; this one exists to fail fast, before a headless browser
	 * round trip.
	 *
	 * @param array  $blocks      Well-shaped, fully-registered block list.
	 * @param string $parent_path Parent path, or '' for the root.
	 * @return array<int, array{code: string, path: string, message: string}> Problems.
	 */
	public static function check( array $blocks, string $parent_path = '' ): array {
		$problems = array();
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( $blocks as $index => $node ) {
			$path       = Tree_Shape::child_path( $parent_path, (int) $index );
			$block_type = $registry->get_registered( $node['name'] );
			$attributes = $node['attributes'] ?? array();

			if ( $block_type ) {
				$known_php_names = array_keys( $block_type->attributes ?? array() );
				$known_js_names  = Attribute_Manifest::names_for( $node['name'] );

				foreach ( $attributes as $attribute_name => $value ) {
					$schema = $block_type->attributes[ $attribute_name ] ?? null;

					if ( ! is_array( $schema ) ) {
						if ( ! in_array( $attribute_name, $known_js_names, true ) ) {
							$problems[] = self::unknown_attribute_problem(
								$path,
								$node['name'],
								(string) $attribute_name,
								array_values( array_unique( array_merge( $known_php_names, $known_js_names ) ) )
							);
						}
						continue;
					}

					$clean_schema = self::strip_binding_keys( $schema );
					if ( ! self::has_validatable_type( $clean_schema ) ) {
						continue; // e.g. core's "rich-text" content attribute - not a JSON Schema type rest_validate_value_from_schema() understands.
					}

					$result = rest_validate_value_from_schema( $value, $clean_schema, $attribute_name );
					if ( is_wp_error( $result ) ) {
						$problems[] = Tree_Shape::problem(
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
				$problems = array_merge( $problems, self::check( $node['innerBlocks'], $path ) );
			}
		}

		return $problems;
	}

	/**
	 * Build a `designsetgo_unknown_attribute` problem, matching the reason
	 * text `findUnknownAttributes()` in src/engine/attributes.js produces.
	 *
	 * @param string             $path           Node path.
	 * @param string             $block_name     Owning block's registered name.
	 * @param string             $attribute_name Unknown attribute name.
	 * @param array<int, string> $known_names    Every attribute name to suggest from (PHP + manifest, combined).
	 * @return array{code: string, path: string, message: string} Problem entry.
	 */
	private static function unknown_attribute_problem( string $path, string $block_name, string $attribute_name, array $known_names ): array {
		$suggestion = Attribute_Suggest::closest( $attribute_name, $known_names );

		$message = $suggestion
			? sprintf(
				/* translators: 1: attribute name, 2: block name, 3: suggested attribute name */
				__( 'unknown attribute "%1$s" for %2$s — did you mean "%3$s"?', 'designsetgo' ),
				$attribute_name,
				$block_name,
				$suggestion
			)
			: sprintf(
				/* translators: 1: attribute name, 2: block name */
				__( 'unknown attribute "%1$s" for %2$s', 'designsetgo' ),
				$attribute_name,
				$block_name
			);

		return Tree_Shape::problem( 'designsetgo_unknown_attribute', $path, $message );
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
}

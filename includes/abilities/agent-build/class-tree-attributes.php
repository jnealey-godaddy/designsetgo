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
	 * - only under a deliberately narrow, fail-open rule - reject a name
	 * that looks like a typo of a known one.
	 *
	 * PHP's up-front unknown-attribute check is NOT a full mirror of
	 * findUnknownAttributes() in src/engine/attributes.js (the browser
	 * engine's check, which stays authoritative and is the only one every
	 * unknown name is guaranteed to hit). The committed JS-registered-
	 * attribute manifest (Attribute_Manifest) only covers `designsetgo/*`
	 * and `core/*` block types; for any other registered block (a
	 * WooCommerce block, a third-party plugin's block), or for an attribute
	 * a third-party JS filter added that the Node-generated manifest never
	 * saw, PHP has no reliable way to tell "genuinely unknown" from "known
	 * only to some JS this generator didn't run" - rejecting there would be
	 * a false positive, stricter than the engine PHP is supposed to be
	 * deferring to. So PHP rejects an attribute name up front only when
	 * ALL of:
	 *
	 * (a) the block name is itself a key in the manifest (`Attribute_Manifest
	 *     ::is_covered()`) - i.e. this generator actually looked at this
	 *     block type, so an absence there means something;
	 * (b) the name is unknown to both PHP's own schema AND the manifest; and
	 * (c) `Attribute_Suggest::closest()` finds a known name close enough to
	 *     plausibly be what was meant - i.e. it looks like a typo, not a
	 *     legitimately novel attribute PHP simply doesn't know about.
	 *
	 * Everything else - an uncovered block entirely, or a name with no
	 * plausible suggestion - passes PHP unchecked and is judged by the
	 * engine at finish time, same as before this check existed.
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
				// Rule (a): the manifest must actually cover this block name.
				$is_covered      = Attribute_Manifest::is_covered( $node['name'] );
				$known_php_names = array_keys( $block_type->attributes ?? array() );
				$known_js_names  = Attribute_Manifest::names_for( $node['name'] );
				// Computed once per node (not per attribute): every attribute on
				// this node that needs a suggestion suggests from the same list.
				$known_names = array_values( array_unique( array_merge( $known_php_names, $known_js_names ) ) );

				foreach ( $attributes as $attribute_name => $value ) {
					$schema = $block_type->attributes[ $attribute_name ] ?? null;

					if ( ! is_array( $schema ) ) {
						// Rule (b): unknown to both - schema null already means
						// unknown to PHP, so only the manifest needs checking.
						$unknown_to_both = ! in_array( $attribute_name, $known_js_names, true );
						if ( $is_covered && $unknown_to_both ) {
							// Rule (c): only reject when a plausible typo target exists.
							$suggestion = Attribute_Suggest::closest( (string) $attribute_name, $known_names );
							if ( null !== $suggestion ) {
								$problems[] = self::unknown_attribute_problem( $path, $node['name'], (string) $attribute_name, $suggestion );
							}
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
	 * Only ever called once `check()` has already confirmed a suggestion
	 * exists (rule (c) above) - a PHP-side rejection always names one.
	 *
	 * @param string $path           Node path.
	 * @param string $block_name     Owning block's registered name.
	 * @param string $attribute_name Unknown attribute name.
	 * @param string $suggestion     The suggested known name.
	 * @return array{code: string, path: string, message: string} Problem entry.
	 */
	private static function unknown_attribute_problem( string $path, string $block_name, string $attribute_name, string $suggestion ): array {
		$message = sprintf(
			/* translators: 1: attribute name, 2: block name, 3: suggested attribute name */
			__( 'unknown attribute "%1$s" for %2$s — did you mean "%3$s"?', 'designsetgo' ),
			$attribute_name,
			$block_name,
			$suggestion
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

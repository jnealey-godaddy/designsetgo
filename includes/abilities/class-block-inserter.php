<?php
/**
 * Block Inserter helper for DesignSetGo abilities.
 *
 * Provides common functionality for inserting blocks into posts,
 * including validation, positioning, and inner blocks handling.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.0.0
 */

namespace DesignSetGo\Abilities;

use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Inserter helper class.
 */
class Block_Inserter {

	/**
	 * Insert a block into a post at the specified position.
	 *
	 * @param int                              $post_id Post ID.
	 * @param string                           $block_name Block name (e.g., 'designsetgo/row').
	 * @param array<string, mixed>             $attributes Block attributes.
	 * @param array<int, array<string, mixed>> $inner_blocks Inner blocks.
	 * @param int                              $position Position to insert (-1 for append, 0 for prepend, or specific index).
	 * @return array<string, mixed>|WP_Error Success data or error.
	 */
	public static function insert_block( int $post_id, string $block_name, array $attributes = array(), array $inner_blocks = array(), int $position = -1 ) {
		// Validate post.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'designsetgo_invalid_post',
				__( 'Post not found.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'designsetgo_permission_denied',
				__( 'You do not have permission to edit this post.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		// Serializer coverage is checked against what is being written, never
		// against what is already in the post.
		$coverage = self::check_serialization_coverage( $block_name, $inner_blocks );
		if ( null !== $coverage ) {
			return new WP_Error(
				'designsetgo_unsupported_block',
				$coverage['message'],
				array(
					'status'   => 400,
					'problems' => $coverage['invalid_paths'],
				)
			);
		}

		// Build block markup.
		$block_markup = self::build_block_markup( $block_name, $attributes, $inner_blocks );

		// Parse existing blocks.
		$blocks = parse_blocks( $post->post_content );

		// Parse new block.
		$new_block = parse_blocks( $block_markup )[0];

		// Insert at position.
		if ( -1 === $position ) {
			// Append to end.
			$blocks[] = $new_block;
		} elseif ( 0 === $position ) {
			// Prepend to beginning.
			array_unshift( $blocks, $new_block );
		} else {
			// Insert at specific index.
			array_splice( $blocks, $position, 0, array( $new_block ) );
		}

		// Structural check before anything is written. A tree whose children
		// sit outside their parent's wrapper serializes and reparses without
		// complaint, so only inspecting the parsed tree catches it.
		$problems = self::validate_block_tree( $blocks );
		if ( ! empty( $problems ) ) {
			return new WP_Error(
				'designsetgo_invalid_block_structure',
				sprintf(
					/* translators: %s: semicolon-separated list of structural problems */
					__( 'Refusing to save: the resulting block structure would be invalid. %s', 'designsetgo' ),
					implode( '; ', $problems )
				),
				array(
					'status'   => 500,
					'problems' => $problems,
				)
			);
		}

		// Serialize blocks back to content.
		$content = serialize_blocks( $blocks );

		// Update post. wp_update_post() runs wp_unslash() on every field, which
		// would strip the JSON-escape backslashes serialize_blocks() writes into
		// block-comment attributes (\n, \", \\, \uXXXX). Slash first so the stored
		// content is byte-identical to what we serialized — critical now that
		// dynamic blocks carry their text in attributes rather than innerHTML.
		$updated = wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => wp_slash( $content ),
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return array(
			'success'  => true,
			'post_id'  => $post->ID,
			'block_id' => wp_unique_id( 'block-' ),
			'position' => $position,
			'note'     => 'Blocks inserted successfully. Open the post in the WordPress editor to validate and save the blocks.',
		);
	}

	/**
	 * Keys a nested block definition may carry.
	 *
	 * `block_name` mirrors the top-level argument and `inner_blocks` mirrors the
	 * snake_case nesting key: a caller that uses the top-level spelling all the
	 * way down is being consistent, not wrong, so both are accepted rather than
	 * silently ignored. Anything else is reported.
	 */
	private const ALLOWED_DEFINITION_KEYS = array(
		'name',
		'block_name',
		'attributes',
		'innerBlocks',
		'inner_blocks',
	);

	/**
	 * Read a block definition's name, accepting either key spelling.
	 *
	 * A definition keyed `block_name` (the top-level argument's spelling) used
	 * to yield no name at all, and the whole child was dropped during
	 * sanitization while the ability still reported success - an entire
	 * requested section vanished that way.
	 *
	 * @param array<string, mixed> $definition Block definition.
	 * @return string Block name, or an empty string when none is present.
	 */
	public static function read_definition_name( array $definition ): string {
		foreach ( array( 'name', 'block_name' ) as $key ) {
			if ( isset( $definition[ $key ] ) && is_string( $definition[ $key ] ) && '' !== trim( $definition[ $key ] ) ) {
				return trim( $definition[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Check that every nested block definition names a block and carries only
	 * keys this inserter understands.
	 *
	 * Without this a misnamed key is dropped in silence: nothing validates
	 * nested definitions, so a child with no recognised name simply disappears
	 * and the caller is told the insert succeeded.
	 *
	 * @param array<int, mixed> $definitions Inner block definitions.
	 * @param string            $path        Internal: path prefix for messages.
	 * @return array<int, array{path: string, block: string, reason: string}> Offending entries.
	 */
	public static function find_malformed_definitions( array $definitions, string $path = '' ): array {
		$problems = array();

		foreach ( $definitions as $index => $definition ) {
			$child_path = '' === $path ? (string) $index : $path . '.' . $index;

			if ( ! is_array( $definition ) ) {
				$problems[] = array(
					'path'   => $child_path,
					'block'  => '',
					'reason' => __( 'Each entry must be an object with a "name" and optional "attributes" and "innerBlocks".', 'designsetgo' ),
				);
				continue;
			}

			$unknown = array_values( array_diff( array_keys( $definition ), self::ALLOWED_DEFINITION_KEYS ) );
			if ( ! empty( $unknown ) ) {
				$problems[] = array(
					'path'   => $child_path,
					'block'  => self::read_definition_name( $definition ),
					'reason' => sprintf(
						/* translators: 1: comma-separated unknown keys, 2: comma-separated allowed keys */
						__( 'Unknown key(s) %1$s. A nested block definition accepts only: %2$s.', 'designsetgo' ),
						implode( ', ', $unknown ),
						implode( ', ', self::ALLOWED_DEFINITION_KEYS )
					),
				);
			}

			if ( '' === self::read_definition_name( $definition ) ) {
				$problems[] = array(
					'path'   => $child_path,
					'block'  => '',
					'reason' => __( 'Missing "name". Every nested block must name its block type, e.g. "name": "designsetgo/slide".', 'designsetgo' ),
				);
				continue;
			}

			$children = self::read_nested_inner_blocks( $definition );
			if ( ! empty( $children ) ) {
				$problems = array_merge( $problems, self::find_malformed_definitions( $children, $child_path ) );
			}
		}

		return $problems;
	}

	/**
	 * Read a block definition's nested children, accepting either key spelling.
	 *
	 * The top-level ability argument is `inner_blocks` (snake_case, matching the
	 * rest of the input schema) while the WordPress parsed-block shape uses
	 * `innerBlocks`. Callers reasonably use one spelling throughout a nested
	 * payload, so both are accepted at every depth. Reading only `innerBlocks`
	 * meant a snake_case payload lost every nested child silently while the
	 * ability still reported success.
	 *
	 * @param array<string, mixed> $definition Block definition.
	 * @return array<int, mixed> Nested block definitions.
	 */
	public static function read_nested_inner_blocks( array $definition ): array {
		foreach ( array( 'innerBlocks', 'inner_blocks' ) as $key ) {
			if ( isset( $definition[ $key ] ) && is_array( $definition[ $key ] ) ) {
				return $definition[ $key ];
			}
		}

		return array();
	}

	/**
	 * Recursively sanitize inner block definitions.
	 *
	 * Only name, attributes, and nested children survive: innerHTML and
	 * innerContent are regenerated from the block name and attributes, so
	 * accepting them from a caller would be an XSS vector. Either nesting key
	 * spelling is accepted; the output always uses `innerBlocks`.
	 *
	 * @param array<int, mixed> $inner_blocks Inner block definitions.
	 * @return array<int, array<string, mixed>> Sanitized definitions.
	 */
	public static function sanitize_inner_block_definitions( array $inner_blocks ): array {
		$sanitized = array();

		foreach ( $inner_blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$clean_block = array();

			$name = self::read_definition_name( $block );
			if ( '' !== $name ) {
				$clean_block['name'] = sanitize_text_field( $name );
			}

			if ( isset( $block['attributes'] ) && is_array( $block['attributes'] ) ) {
				$clean_block['attributes'] = Block_Configurator::sanitize_attributes( $block['attributes'] );
			}

			$nested = self::read_nested_inner_blocks( $block );
			if ( ! empty( $nested ) ) {
				$clean_block['innerBlocks'] = self::sanitize_inner_block_definitions( $nested );
			}

			$sanitized[] = $clean_block;
		}

		return $sanitized;
	}

	/**
	 * Whether this inserter can produce stored markup the editor accepts for a
	 * block at all.
	 *
	 * Static blocks keep their markup in a JavaScript `save()` that PHP cannot
	 * run, so the only ones that can be inserted are those with a hand-written
	 * mirror in generate_designsetgo_wrapper_html() (or generate_core_block_html()
	 * for the two core text blocks). Without a mirror the block serializes to a
	 * bare self-closing comment: that is how `designsetgo/fifty-fifty` was
	 * written as `<!-- wp:designsetgo/fifty-fifty {...} /-->` with none of the
	 * media and content wrappers its save() emits, invalidating the whole
	 * subtree. Thirteen static blocks were in that state.
	 *
	 * Server-rendered blocks are always fine: a self-closing comment IS their
	 * correct stored form.
	 *
	 * @param string $block_name Block name.
	 * @return string|null Reason it cannot be serialized, or null when it can.
	 */
	public static function get_serialization_gap( string $block_name ): ?string {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		if ( ! $block_type ) {
			return sprintf(
				/* translators: %s: block name */
				__( '%s is not a registered block type.', 'designsetgo' ),
				$block_name
			);
		}

		// Server-rendered: no save() output to reproduce.
		if ( self::is_dynamic_block( $block_name ) ) {
			return null;
		}

		if ( 0 === strpos( $block_name, 'designsetgo/' ) ) {
			if ( null !== self::generate_designsetgo_wrapper_html( $block_name, array() ) ) {
				return null;
			}

			return sprintf(
				/* translators: %s: block name */
				__( '%s cannot be inserted yet: this inserter has no serializer mirroring its save() output, so the stored markup would be invalid. Insert it in the editor instead.', 'designsetgo' ),
				$block_name
			);
		}

		if ( in_array( $block_name, self::SERIALIZABLE_CORE_BLOCKS, true ) ) {
			return null;
		}

		return sprintf(
			/* translators: 1: block name, 2: comma-separated list of supported core blocks */
			__( '%1$s cannot be inserted: this inserter can only serialize these core blocks (%2$s). Use a designsetgo block, or add the content in the editor.', 'designsetgo' ),
			$block_name,
			implode( ', ', self::SERIALIZABLE_CORE_BLOCKS )
		);
	}

	/**
	 * Split a class attribute into individual class names.
	 *
	 * @param string $class_attribute Space-separated class attribute value.
	 * @return array<int, string> Class names.
	 */
	private static function split_class_list( string $class_attribute ): array {
		$parts = preg_split( '/\s+/', $class_attribute, -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $parts ) ? $parts : array();
	}

	/**
	 * Merge WordPress block-support classes and styles into generated markup.
	 *
	 * The wrapper generators only ever handled the `style` attribute object, so
	 * preset attributes were dropped: `backgroundColor: "base"` was written into
	 * the block comment while the markup carried none of the
	 * `has-base-background-color has-background` classes save() emits, and the
	 * block failed validation. Rather than restate those rules per block, this
	 * asks WordPress for them - the same code path get_block_wrapper_attributes()
	 * uses for dynamic blocks, and the one core keeps matched with the editor.
	 *
	 * Inline styles come from the Style Engine rather than from
	 * apply_block_supports(), because the latter also applies render-time
	 * transforms that save() never does - fluid typography turns a 20px font
	 * size into a clamp() the stored markup must not contain.
	 *
	 * @param string               $html       Generated opening markup.
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Markup with support classes and styles merged into the first tag.
	 */
	private static function apply_block_support_attributes( string $html, string $block_name, array $attributes ): string {
		if ( ! class_exists( '\WP_Block_Supports' ) || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}

		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		$previous                            = \WP_Block_Supports::$block_to_render;
		\WP_Block_Supports::$block_to_render = array(
			'blockName' => $block_name,
			'attrs'     => $attributes,
		);

		$applied = \WP_Block_Supports::get_instance()->apply_block_supports();

		\WP_Block_Supports::$block_to_render = $previous;

		// Only the support classes are taken. The wrapper generators already
		// emit the block's own `wp-block-*` and alignment classes, and the
		// support classes are exactly the `has-*` set.
		$support_classes = array_values(
			array_filter(
				self::split_class_list( (string) ( $applied['class'] ?? '' ) ),
				static function ( $class_name ) {
					return 0 === strpos( $class_name, 'has-' );
				}
			)
		);

		$declarations = array();
		if ( ! empty( $attributes['style'] ) && is_array( $attributes['style'] ) && function_exists( 'wp_style_engine_get_styles' ) ) {
			$engine       = wp_style_engine_get_styles( self::strip_skipped_style_groups( $block_type, $attributes['style'] ) );
			$declarations = $engine['declarations'] ?? array();
		}

		$processor = new \WP_HTML_Tag_Processor( $html );

		// Most blocks carry their support classes on the root. A few move them
		// to an inner element in save() - Modal transfers them onto its content
		// div - so putting them on the root there is markup save() never emits.
		$target_class = self::SUPPORTS_ON_INNER_ELEMENT[ $block_name ] ?? null;

		$found = null === $target_class
			? $processor->next_tag()
			: $processor->next_tag( array( 'class_name' => $target_class ) );

		if ( ! $found ) {
			return $html;
		}

		// Drop declarations with no value, and the style attribute itself when
		// nothing survives. React omits a style property whose value is
		// undefined and emits no style attribute for an empty object, so
		// `--dsgo-accordion-open-bg:;` or a bare `style=""` is markup save()
		// never writes — and several serializers built their style strings by
		// concatenation without checking. Doing it here fixes the whole class at
		// once rather than per block.
		$removed_style  = false;
		$existing_style = $processor->get_attribute( 'style' );
		if ( is_string( $existing_style ) ) {
			$kept = array();
			foreach ( explode( ';', $existing_style ) as $declaration ) {
				$parts = explode( ':', $declaration, 2 );
				if ( 2 !== count( $parts ) || '' === trim( $parts[1] ) ) {
					continue;
				}
				$kept[] = trim( $declaration );
			}

			if ( empty( $kept ) ) {
				$processor->remove_attribute( 'style' );
				$removed_style = true;
			} else {
				$processor->set_attribute( 'style', implode( ';', $kept ) );
			}
		}

		foreach ( $support_classes as $class_name ) {
			$processor->add_class( $class_name );
		}

		if ( ! empty( $declarations ) ) {
			$existing = (string) $processor->get_attribute( 'style' );
			$present  = array();

			foreach ( explode( ';', $existing ) as $declaration ) {
				$parts = explode( ':', $declaration, 2 );
				if ( 2 === count( $parts ) ) {
					$present[ trim( $parts[0] ) ] = true;
				}
			}

			$additions = array();
			foreach ( $declarations as $property => $value ) {
				// A generator that already wrote this property wins: it mirrors
				// its own save() and may format the value differently.
				if ( isset( $present[ $property ] ) ) {
					continue;
				}
				$additions[] = $property . ':' . $value;
			}

			if ( ! empty( $additions ) ) {
				$merged = rtrim( trim( $existing ), ';' );
				$merged = ( '' === $merged ) ? implode( ';', $additions ) : $merged . ';' . implode( ';', $additions );
				$processor->set_attribute( 'style', $merged );
			}
		}

		$updated = $processor->get_updated_html();

		// WP_HTML_Tag_Processor leaves the removed attribute's separating space
		// behind. Harmless HTML, but it is not what save() emits.
		return $removed_style ? preg_replace( '/\s+>/', '>', $updated, 1 ) : $updated;
	}

	/**
	 * Visual support classes and styles for a block that routes them inward.
	 *
	 * Some blocks skip-serialize their visual supports on the block root and
	 * re-apply them to an inner element - Icon Button's root is a positioning
	 * wrapper, so its colours belong on the <a> inside. For those,
	 * apply_block_support_attributes() correctly returns nothing (WordPress is
	 * told to skip), and the values have to be resolved here instead. Without
	 * this the attributes were stored in the block comment and no matching
	 * class ever reached the markup.
	 *
	 * Mirrors the getColorClassesAndStyles / getTypographyClassesAndStyles /
	 * getBorderClassesAndStyles helpers the save() functions use.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array{classes: array<int, string>, styles: array<int, string>} Classes and declarations.
	 */
	private static function get_routed_visual_attributes( array $attributes ): array {
		$classes = array();
		$styles  = array();

		// Preset attributes become `has-*` classes; the second entry is the
		// companion flag class WordPress adds alongside.
		$preset_classes = array(
			'textColor'       => array( 'has-%s-color', 'has-text-color' ),
			'backgroundColor' => array( 'has-%s-background-color', 'has-background' ),
			'gradient'        => array( 'has-%s-gradient-background', 'has-background' ),
			'fontSize'        => array( 'has-%s-font-size', null ),
			'fontFamily'      => array( 'has-%s-font-family', null ),
			'borderColor'     => array( 'has-%s-border-color', 'has-border-color' ),
		);

		foreach ( $preset_classes as $attribute => $definition ) {
			$value = $attributes[ $attribute ] ?? '';
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}

			$classes[] = sprintf( $definition[0], $value );
			if ( null !== $definition[1] ) {
				$classes[] = $definition[1];
			}
		}

		$style = ( isset( $attributes['style'] ) && is_array( $attributes['style'] ) ) ? $attributes['style'] : array();

		// Custom values get the flag class without a preset class.
		if ( ! empty( $style['color']['text'] ) ) {
			$classes[] = 'has-text-color';
		}
		if ( ! empty( $style['color']['background'] ) || ! empty( $style['color']['gradient'] ) ) {
			$classes[] = 'has-background';
		}
		if ( ! empty( $style['border']['color'] ) ) {
			$classes[] = 'has-border-color';
		}

		$routed = array_intersect_key(
			$style,
			array_flip( array( 'color', 'typography', 'border', 'shadow' ) )
		);

		if ( ! empty( $routed ) && function_exists( 'wp_style_engine_get_styles' ) ) {
			$engine = wp_style_engine_get_styles( $routed );
			foreach ( $engine['declarations'] ?? array() as $property => $value ) {
				$styles[] = $property . ':' . $value;
			}
		}

		return array(
			'classes' => array_values( array_unique( $classes ) ),
			'styles'  => $styles,
		);
	}

	/**
	 * Whether this inserter can nest children inside a block and still produce
	 * markup the editor will accept.
	 *
	 * Two kinds of block qualify, and both are ours:
	 *
	 * - DesignSetGo server-rendered blocks. They have no save output at all, so
	 *   children simply nest between the block comments.
	 * - DesignSetGo static blocks with a case in
	 *   generate_designsetgo_wrapper_html(), where the wrapper is reproduced.
	 *
	 * No core block qualifies. Wrapper generation is gated on the
	 * `designsetgo/` prefix, so a core block given children today emits its
	 * children with nothing around them: `core/heading` produced a block
	 * comment holding a bare `<p>` and no `<h4>` at all, and `core/group`
	 * produced its children with no `<div class="wp-block-group">`. Both are
	 * invalid the moment the editor opens them.
	 *
	 * A render callback is deliberately NOT the test. Core added one to
	 * `core/heading` and `core/list` for block bindings while both still have
	 * real save() output, so "has a render callback" would wave through
	 * exactly the blocks this check exists to catch.
	 *
	 * @param string $block_name Block name.
	 * @return bool Whether children can be nested inside this block.
	 */
	public static function supports_child_blocks( string $block_name ): bool {
		if ( 0 !== strpos( $block_name, 'designsetgo/' ) ) {
			return false;
		}

		if ( self::is_dynamic_block( $block_name ) ) {
			return true;
		}

		return null !== self::generate_designsetgo_wrapper_html( $block_name, array() );
	}

	/**
	 * Find attribute values a block's own schema does not allow.
	 *
	 * An out-of-enum value is worse than useless: the generator writes it into
	 * the markup, but WordPress replaces it with the default when it parses the
	 * block, so save() produces different markup and the block is invalid. A
	 * timeline given `layout: "left"` (its enum is alternating|right) rendered
	 * `dsgo-timeline--layout-left` against a save() that emitted
	 * `dsgo-timeline--layout-alternating`.
	 *
	 * @param array<int, mixed> $definitions Block definitions.
	 * @param string            $path        Internal: path prefix for messages.
	 * @return array<int, array{path: string, block: string, reason: string}> Offending entries.
	 */
	public static function find_invalid_attribute_values( array $definitions, string $path = '' ): array {
		$problems = array();

		// Values a serializer deliberately does not reproduce, with the reason.
		// Refusing beats approximating: the animated heading segment serializes
		// a JSON word list and an inline highlight SVG that save() builds from
		// a shape library, and a near-miss would be invalid content.
		// Attributes that must be absent entirely, with the reason.
		$unsupported_when_set = array(
			'designsetgo/advanced-heading' => array(
				'animatedHeadline' => __( 'the animated headline variant is not supported by this inserter; it serializes rotation timings and an inline highlight shape. Insert the heading in the editor, or omit animatedHeadline.', 'designsetgo' ),
			),
		);

		$unsupported = array(
			'designsetgo/text-path'       => array(
				'pathType' => array(
					'custom' => __( 'a custom path is not supported by this inserter; its path data goes through a sanitizer that is the block\'s security boundary, and a second implementation of that would be a liability. Use one of the built-in shapes, or draw the path in the editor.', 'designsetgo' ),
				),
			),
			'designsetgo/heading-segment' => array(
				'headlineRole' => array(
					'animated' => __( 'the animated headline role is not supported by this inserter; add the segment in the editor, or use headlineRole "normal".', 'designsetgo' ),
				),
			),
		);

		foreach ( $definitions as $index => $definition ) {
			$block_name = is_array( $definition ) ? self::read_definition_name( $definition ) : '';
			if ( '' === $block_name ) {
				continue;
			}

			$block_path = '' === $path ? (string) $index : $path . '.' . $index;
			$attributes = ( isset( $definition['attributes'] ) && is_array( $definition['attributes'] ) )
				? $definition['attributes']
				: array();

			$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

			foreach ( $attributes as $attribute => $value ) {
				$blocked = $unsupported_when_set[ $block_name ][ $attribute ] ?? null;
				if ( null !== $blocked && null !== $value && array() !== $value && '' !== $value ) {
					$problems[] = array(
						'path'   => $block_path,
						'block'  => $block_name,
						'reason' => sprintf(
							/* translators: 1: attribute name, 2: explanation */
							__( '%1$s: %2$s', 'designsetgo' ),
							$attribute,
							$blocked
						),
					);
				}

				if ( ! is_string( $value ) ) {
					continue;
				}
				$reason = $unsupported[ $block_name ][ $attribute ][ $value ] ?? null;
				if ( null !== $reason ) {
					$problems[] = array(
						'path'   => $block_path,
						'block'  => $block_name,
						'reason' => sprintf(
							/* translators: 1: attribute name, 2: explanation */
							__( '%1$s: %2$s', 'designsetgo' ),
							$attribute,
							$reason
						),
					);
				}
			}

			if ( $block_type && ! empty( $block_type->attributes ) ) {
				foreach ( $attributes as $attribute => $value ) {
					$schema = $block_type->attributes[ $attribute ] ?? null;

					if ( ! is_array( $schema ) || empty( $schema['enum'] ) || ! is_array( $schema['enum'] ) ) {
						continue;
					}

					if ( in_array( $value, $schema['enum'], true ) ) {
						continue;
					}

					$problems[] = array(
						'path'   => $block_path,
						'block'  => $block_name,
						'reason' => sprintf(
							/* translators: 1: attribute name, 2: the rejected value, 3: comma-separated allowed values */
							__( '%1$s does not accept %2$s. Allowed values: %3$s.', 'designsetgo' ),
							$attribute,
							wp_json_encode( $value ),
							implode( ', ', array_map( 'wp_json_encode', $schema['enum'] ) )
						),
					);
				}
			}

			$children = self::read_nested_inner_blocks( $definition );
			if ( ! empty( $children ) ) {
				$problems = array_merge( $problems, self::find_invalid_attribute_values( $children, $block_path ) );
			}
		}

		return $problems;
	}

	/**
	 * Find blocks in a definition tree this inserter cannot serialize.
	 *
	 * @param array<int, mixed> $definitions Block definitions.
	 * @param string            $path        Internal: path prefix for messages.
	 * @return array<int, array{path: string, block: string, reason: string}> Offending entries.
	 */
	public static function find_serialization_gaps( array $definitions, string $path = '' ): array {
		$gaps = array();

		foreach ( $definitions as $index => $definition ) {
			$block_name = is_array( $definition ) ? self::read_definition_name( $definition ) : '';
			if ( '' === $block_name ) {
				continue;
			}

			$block_path = '' === $path ? (string) $index : $path . '.' . $index;
			$gap        = self::get_serialization_gap( $block_name );

			if ( null !== $gap ) {
				$gaps[] = array(
					'path'   => $block_path,
					'block'  => $block_name,
					'reason' => $gap,
				);
			}

			$children = self::read_nested_inner_blocks( $definition );
			if ( ! empty( $children ) ) {
				$gaps = array_merge( $gaps, self::find_serialization_gaps( $children, $block_path ) );
			}
		}

		return $gaps;
	}

	/**
	 * Check that every block in a definition tree may hold the children given.
	 *
	 * @param array<int, mixed> $definitions Inner block definitions.
	 * @param string            $path        Internal: path prefix for messages.
	 * @return array<int, array{path: string, block: string, reason: string}> Offending entries.
	 */
	public static function find_invalid_child_placements( array $definitions, string $path = '' ): array {
		$invalid = array();

		foreach ( $definitions as $index => $definition ) {
			$block_name = is_array( $definition ) ? self::read_definition_name( $definition ) : '';
			if ( '' === $block_name ) {
				continue;
			}

			$child_path = '' === $path ? (string) $index : $path . '.' . $index;
			$children   = self::read_nested_inner_blocks( $definition );

			if ( ! empty( $children ) && ! self::supports_child_blocks( $block_name ) ) {
				$invalid[] = array(
					'path'   => $child_path,
					'block'  => $block_name,
					'reason' => self::describe_child_rejection( $block_name ),
				);
				continue;
			}

			if ( ! empty( $children ) ) {
				$invalid = array_merge( $invalid, self::find_invalid_child_placements( $children, $child_path ) );
			}
		}

		return $invalid;
	}

	/**
	 * Screen a requested insertion for children placed in blocks that cannot
	 * hold them.
	 *
	 * Returned as a diagnostic array rather than a WP_Error so the reason
	 * reaches the caller: the MCP bridge replaces every WP_Error message with a
	 * fixed string. See Abstract_Ability::run().
	 *
	 * @param string               $block_name   Block being inserted.
	 * @param array<int, mixed>    $inner_blocks Its child definitions.
	 * @param array<string, mixed> $attributes   The block's own attributes.
	 * @return array<string, mixed>|null Diagnostic payload, or null when the tree is placeable.
	 */
	public static function check_child_placement( string $block_name, array $inner_blocks, array $attributes = array() ): ?array {
		$tree = array(
			array(
				'name'        => $block_name,
				'attributes'  => $attributes,
				'innerBlocks' => $inner_blocks,
			),
		);

		$malformed = self::find_malformed_definitions( $inner_blocks );
		if ( ! empty( $malformed ) ) {
			return self::format_definition_diagnostic( $malformed );
		}

		return self::format_placement_diagnostic(
			array_merge(
				self::find_serialization_gaps( $tree ),
				self::find_invalid_child_placements( $tree ),
				self::find_invalid_attribute_values( $tree )
			),
			'designsetgo_invalid_child_placement'
		);
	}

	/**
	 * Screen a requested insertion for blocks this inserter cannot serialize.
	 *
	 * Scoped to the definitions being written. Never run over a whole document:
	 * a post may legitimately contain editor-authored blocks with no serializer
	 * here, and refusing those would make the post uneditable.
	 *
	 * @param string            $block_name   Block being inserted.
	 * @param array<int, mixed> $inner_blocks Its child definitions.
	 * @return array<string, mixed>|null Diagnostic payload, or null when everything is serializable.
	 */
	public static function check_serialization_coverage( string $block_name, array $inner_blocks ): ?array {
		return self::format_placement_diagnostic(
			self::find_serialization_gaps(
				array(
					array(
						'name'        => $block_name,
						'innerBlocks' => $inner_blocks,
					),
				)
			),
			'designsetgo_unsupported_block'
		);
	}

	/**
	 * Report nested definitions that are misnamed or carry unknown keys.
	 *
	 * @param array<int, array{path: string, block: string, reason: string}> $entries Offending entries.
	 * @return array<string, mixed> Diagnostic payload.
	 */
	private static function format_definition_diagnostic( array $entries ): array {
		$reasons = array();
		$paths   = array();

		foreach ( $entries as $entry ) {
			$paths[]   = $entry['path'];
			$reasons[] = sprintf(
				/* translators: 1: path within inner_blocks, 2: explanation */
				__( 'inner_blocks[%1$s]: %2$s', 'designsetgo' ),
				$entry['path'],
				$entry['reason']
			);
		}

		return array(
			'success'       => false,
			'error_code'    => 'designsetgo_malformed_block_definition',
			'message'       => sprintf(
				/* translators: %s: semicolon-separated list of problems */
				__( 'Nothing was changed. %s', 'designsetgo' ),
				implode( '; ', $reasons )
			),
			'invalid_paths' => $paths,
		);
	}

	/**
	 * Turn a list of offending entries into a caller-facing diagnostic.
	 *
	 * Returned as an array rather than a WP_Error so the reason survives the
	 * MCP bridge, which replaces every WP_Error message with a fixed string.
	 * See Abstract_Ability::run().
	 *
	 * @param array<int, array{path: string, block: string, reason: string}> $entries    Offending entries.
	 * @param string                                                         $error_code Machine-readable code.
	 * @return array<string, mixed>|null Diagnostic payload, or null when there is nothing to report.
	 */
	private static function format_placement_diagnostic( array $entries, string $error_code ): ?array {
		if ( empty( $entries ) ) {
			return null;
		}

		$reasons = array();
		$paths   = array();
		$seen    = array();

		foreach ( $entries as $entry ) {
			// Drop the synthetic root segment so paths are relative to
			// inner_blocks, which is what the caller actually sent.
			$relative = (string) preg_replace( '/^0\.?/', '', $entry['path'] );

			if ( isset( $seen[ $relative . $entry['reason'] ] ) ) {
				continue;
			}
			$seen[ $relative . $entry['reason'] ] = true;

			$paths[]   = $relative;
			$reasons[] = sprintf(
				/* translators: 1: path within inner_blocks, 2: explanation */
				__( 'inner_blocks[%1$s]: %2$s', 'designsetgo' ),
				'' === $relative ? '(the block itself)' : $relative,
				$entry['reason']
			);
		}

		return array(
			'success'       => false,
			'error_code'    => $error_code,
			'message'       => sprintf(
				/* translators: %s: semicolon-separated list of problems */
				__( 'Nothing was changed. %s', 'designsetgo' ),
				implode( '; ', $reasons )
			),
			'invalid_paths' => $paths,
		);
	}

	/**
	 * Explain why a block cannot hold children, and what to do instead.
	 *
	 * @param string $block_name Block name.
	 * @return string Guidance for the caller.
	 */
	private static function describe_child_rejection( string $block_name ): string {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		// A block whose text is sourced out of its own markup holds that text
		// in an attribute, not in a child block. This is the core/heading case.
		if ( $block_type && ! empty( $block_type->attributes ) ) {
			foreach ( $block_type->attributes as $attr_name => $attr ) {
				if ( in_array( $attr['source'] ?? '', array( 'html', 'rich-text', 'text' ), true ) ) {
					return sprintf(
						/* translators: 1: block name, 2: attribute name */
						__( '%1$s holds its text in attributes.%2$s, not in child blocks.', 'designsetgo' ),
						$block_name,
						$attr_name
					);
				}
			}
		}

		$alternatives = array(
			'core/group'   => 'designsetgo/section',
			'core/columns' => 'designsetgo/grid',
			'core/column'  => 'designsetgo/section',
			'core/row'     => 'designsetgo/row',
			'core/stack'   => 'designsetgo/section',
			'core/cover'   => 'designsetgo/section',
		);

		if ( isset( $alternatives[ $block_name ] ) ) {
			return sprintf(
				/* translators: 1: block name, 2: suggested block name */
				__( 'This inserter cannot generate valid markup for %1$s with children. Use %2$s instead.', 'designsetgo' ),
				$block_name,
				$alternatives[ $block_name ]
			);
		}

		return sprintf(
			/* translators: %s: block name */
			__( 'This inserter cannot generate valid markup for %s with children. Use a designsetgo container (section, row, grid) instead, or insert the blocks separately.', 'designsetgo' ),
			$block_name
		);
	}

	/**
	 * Input schema fragment describing a tree of inner block definitions.
	 *
	 * Every nested array declares its own `items` schema. Leaving that out made
	 * core validate each nested entry against an empty schema, which emits an
	 * "Undefined array key type" warning per entry on every request.
	 *
	 * @param int $depth How many levels to describe explicitly.
	 * @return array<string, mixed> Schema for an array of block definitions.
	 */
	public static function get_inner_blocks_schema( int $depth = 4 ): array {
		$properties = array(
			'name'       => array(
				'type'        => 'string',
				'description' => __( 'REQUIRED. Block name, e.g. "designsetgo/slide" or "core/paragraph". Note this is "name", not "block_name" - "block_name" is the TOP-LEVEL argument only (it is accepted here as an alias).', 'designsetgo' ),
			),
			'attributes' => array(
				'type'        => 'object',
				'description' => __( 'Block attributes. Text-bearing core blocks carry their text here (core/heading and core/paragraph use "content"), never as a child block.', 'designsetgo' ),
			),
		);

		if ( $depth > 1 ) {
			$nested = self::get_inner_blocks_schema( $depth - 1 );

			$properties['innerBlocks']  = $nested;
			$properties['inner_blocks'] = array_merge(
				$nested,
				array( 'description' => __( 'Alias of innerBlocks; either spelling is accepted at any depth.', 'designsetgo' ) )
			);
		} else {
			$properties['innerBlocks']  = array(
				'type'  => 'array',
				'items' => array( 'type' => 'object' ),
			);
			$properties['inner_blocks'] = array(
				'type'  => 'array',
				'items' => array( 'type' => 'object' ),
			);
		}

		return array(
			'type'        => 'array',
			'description' => __( 'Child blocks. Each entry REQUIRES "name". Only server-rendered blocks and DesignSetGo containers can hold children; core blocks such as core/heading and core/group cannot.', 'designsetgo' ),
			'items'       => array(
				'type'       => 'object',
				'properties' => $properties,
				// "name" is required, but deliberately NOT declared in `required`
				// here. Core validates input before the ability callback runs,
				// and the MCP bridge flattens the resulting WP_Error to
				// "Ability execution failed." - so a schema-level requirement
				// makes the failure LESS legible, not more. The requirement is
				// enforced in find_malformed_definitions(), which reports the
				// offending path and the accepted keys in a form that survives
				// the bridge. The description above still states it plainly.
			),
		);
	}

	/**
	 * Validate a parsed block tree before it is written to a post.
	 *
	 * Catches structural corruption that still round-trips through
	 * parse_blocks()/serialize_blocks() and so cannot be caught by comparing
	 * serialized strings. The invariant: a DesignSetGo block that renders a
	 * wrapper and holds children must open with HTML and close with HTML, so
	 * every child placeholder falls between them. A tree whose first
	 * innerContent entry is a child placeholder emits that child *before* the
	 * parent's opening tag, which is what left every carousel slide empty.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param string                           $path   Internal: path prefix for messages.
	 * @return array<int, string> Human-readable problems; empty when the tree is sound.
	 */
	public static function validate_block_tree( array $blocks, string $path = '' ): array {
		$problems = array();

		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) || empty( $block['blockName'] ) ) {
				continue;
			}

			$block_path    = '' === $path ? (string) $index : $path . '.' . $index;
			$inner_blocks  = $block['innerBlocks'] ?? array();
			$inner_content = $block['innerContent'] ?? array();

			// NOTE: serializer coverage is deliberately NOT checked here. This
			// runs over the whole document, which includes blocks the editor
			// authored and we are only passing through. Refusing those would
			// make a post uneditable because of content we did not write.
			// Coverage is checked against the requested definitions instead,
			// in check_serialization_coverage().

			if ( ! empty( $inner_blocks ) ) {
				$has_wrapper = false;
				foreach ( $inner_content as $entry ) {
					if ( is_string( $entry ) && '' !== trim( $entry ) ) {
						$has_wrapper = true;
						break;
					}
				}

				if ( $has_wrapper ) {
					$first = $inner_content[0] ?? null;
					$last  = $inner_content[ count( $inner_content ) - 1 ] ?? null;

					if ( ! is_string( $first ) || ! is_string( $last ) ) {
						$problems[] = sprintf(
							/* translators: 1: block name, 2: block path */
							__( 'Block %1$s at path %2$s would emit a child outside its own wrapper markup.', 'designsetgo' ),
							$block['blockName'],
							$block_path
						);
					}
				} elseif ( ! self::supports_child_blocks( (string) $block['blockName'] ) ) {
					// A static block holding children but contributing no
					// markup of its own. Its save() output lives in JavaScript
					// and was never emitted, so the children render loose
					// inside a block comment that produces nothing.
					$problems[] = sprintf(
						/* translators: 1: block name, 2: block path */
						__( 'Block %1$s at path %2$s holds child blocks but emits no markup of its own, so the editor will report it as invalid.', 'designsetgo' ),
						$block['blockName'],
						$block_path
					);
				}
			}

			$placeholders = 0;
			foreach ( $inner_content as $entry ) {
				if ( null === $entry ) {
					++$placeholders;
				}
			}

			if ( ! empty( $inner_blocks ) && count( $inner_blocks ) !== $placeholders ) {
				$problems[] = sprintf(
					/* translators: 1: block name, 2: block path, 3: placeholder count, 4: inner block count */
					__( 'Block %1$s at path %2$s has %3$d child placeholders for %4$d inner blocks; children would be dropped on save.', 'designsetgo' ),
					$block['blockName'],
					$block_path,
					$placeholders,
					count( $inner_blocks )
				);
			}

			if ( ! empty( $inner_blocks ) ) {
				$problems = array_merge( $problems, self::validate_block_tree( $inner_blocks, $block_path ) );
			}
		}

		return $problems;
	}

	/**
	 * Build block markup from block name, attributes, and inner blocks.
	 *
	 * @param string                           $block_name Block name.
	 * @param array<string, mixed>             $attributes Block attributes.
	 * @param array<int, array<string, mixed>> $inner_blocks Inner blocks.
	 * @return string Block markup.
	 */
	public static function build_block_markup( string $block_name, array $attributes = array(), array $inner_blocks = array() ): string {
		// Convert simplified block structure to WordPress block array format.
		$block = self::convert_to_block_array( $block_name, $attributes, $inner_blocks );

		// Use WordPress's native serialize_block function.
		return serialize_block( $block );
	}

	/**
	 * Convert simplified block structure to WordPress block array format.
	 *
	 * Handles extraction of innerHTML from 'content' attribute for core blocks.
	 *
	 * @param string                           $block_name Block name.
	 * @param array<string, mixed>             $attributes Block attributes.
	 * @param array<int, array<string, mixed>> $inner_blocks Inner blocks.
	 * @return array<string, mixed> WordPress block array.
	 */
	private static function convert_to_block_array( string $block_name, array $attributes = array(), array $inner_blocks = array() ): array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- WordPress block format requires camelCase.
		$innerHTML     = '';
		$innerContent  = array();
		$parsed_inners = array();

		// Coerce attribute types and normalize defaults.
		$attrs = self::coerce_attribute_types( $block_name, $attributes );
		$attrs = self::normalize_block_attributes( $block_name, $attrs );
		// Apply block.json defaults so the HTML we build matches what save()
		// would emit from the same parsed attributes, preventing block
		// validation failures on first edit.
		$attrs = self::apply_block_json_defaults( $block_name, $attrs );

		// Convert CSS var() syntax to WordPress shorthand in style attribute.
		if ( isset( $attrs['style'] ) && is_array( $attrs['style'] ) ) {
			$attrs['style'] = self::convert_style_vars( $attrs['style'] );
		}
		if ( isset( $attrs['content'] ) && 0 === strpos( $block_name, 'core/' ) ) {
			$content = $attrs['content'];
			unset( $attrs['content'] );

			// Generate innerHTML based on block type, then merge in the same
			// block-support classes and styles save() would serialize. Without
			// this a paragraph's textColor lived in the block comment while the
			// <p> carried none of the has-*-color classes, and the block failed
			// validation on open.
			$innerHTML      = self::apply_block_support_attributes(
				self::generate_core_block_html( $block_name, $content, $attrs ),
				$block_name,
				$attrs
			);
			$innerContent[] = $innerHTML;
		}

		// Process inner blocks recursively.
		if ( ! empty( $inner_blocks ) ) {
			foreach ( $inner_blocks as $inner ) {
				$inner_name       = self::read_definition_name( $inner );
				$inner_attributes = $inner['attributes'] ?? array();
				$inner_inner      = self::read_nested_inner_blocks( $inner );

				if ( $inner_name ) {
					$parsed_inners[] = self::convert_to_block_array( $inner_name, $inner_attributes, $inner_inner );
					$innerContent[]  = null; // Placeholder for inner block.
				}
			}
		}

		// Generate HTML for DesignSetGo blocks.
		// Skip dynamic blocks (those with render callbacks) - they'll be rendered server-side.
		if ( 0 === strpos( $block_name, 'designsetgo/' ) && ! self::is_dynamic_block( $block_name ) ) {
			$wrapper_html = self::generate_designsetgo_wrapper_html( $block_name, $attrs );
			// A wrapper of two empty strings means save() returns null for these
			// attributes: the block serializes as a self-closing comment with no
			// markup, so nothing is added to innerContent.
			if ( ! empty( $wrapper_html ) && '' === $wrapper_html['opening'] && '' === $wrapper_html['closing'] ) {
				$wrapper_html = null;
			}

			if ( ! empty( $wrapper_html ) ) {
				// Merge in the block-support classes and styles WordPress would
				// serialize, so preset attributes such as backgroundColor reach
				// the markup instead of being stored in the comment alone.
				$wrapper_html['opening'] = self::apply_block_support_attributes(
					$wrapper_html['opening'],
					$block_name,
					$attrs
				);

				// Opening and closing HTML are ALWAYS separate innerContent
				// entries, even with no inner blocks yet. WordPress interleaves
				// innerContent strings with null placeholders for children, so a
				// later append (Block_Configurator::insert_inner_block) inserts
				// its null between the two entries and the child nests correctly.
				// Storing the wrapper as a single combined string instead put the
				// append before the whole wrapper, emitting children outside
				// their parent and leaving the wrapper empty.
				array_unshift( $innerContent, $wrapper_html['opening'] );
				$innerContent[] = $wrapper_html['closing'];
				$innerHTML      = $wrapper_html['opening'] . $wrapper_html['closing'];
			}
		}

		// Form-field blocks (and the map) are dynamic/server-rendered, so they
		// serialize to a bare self-closing comment with no inner HTML — nothing
		// to build here. is_dynamic_block() above keeps them out of the
		// wrapper-HTML path too.

		// Strip attributes that match block.json defaults so serialize_block
		// doesn't include them in the block comment (WordPress omits defaults).
		$attrs = self::strip_default_attributes( $block_name, $attrs );

		return array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => $parsed_inners,
			'innerHTML'    => $innerHTML,
			'innerContent' => ! empty( $innerContent ) ? $innerContent : array( $innerHTML ),
		);
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
	}

	/**
	 * Generate wrapper HTML for DesignSetGo blocks.
	 *
	 * Creates opening and closing HTML that approximates the block's save output.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Array with 'opening' and 'closing' keys, or null if not supported.
	 */
	public static function generate_designsetgo_wrapper_html( string $block_name, array $attributes ): ?array {
		$block_slug  = str_replace( 'designsetgo/', '', $block_name );
		$block_class = 'wp-block-designsetgo-' . $block_slug . ' dsgo-' . $block_slug;

		switch ( $block_name ) {
			case 'designsetgo/section':
				// Section's block.json gives `style` a default carrying the page
				// padding, and its save() serializes spacing support, so that
				// padding IS in the stored markup. WordPress drops the default
				// when it registers `style` on the PHP side (it re-registers the
				// support-backed attribute as a bare object), so it has to be
				// read back from block.json. Only when the caller supplied no
				// style at all: an attribute default is replaced wholesale, not
				// deep-merged, so a caller-supplied partial style legitimately
				// has no padding.
				if ( ! isset( $attributes['style'] ) ) {
					$declared_style = Block_Schema_Loader::get_block_json( $block_name )['attributes']['style']['default'] ?? null;
					if ( is_array( $declared_style ) ) {
						$attributes['style'] = self::convert_style_vars( $declared_style );
					}
				}

				$constrain_width = isset( $attributes['constrainWidth'] ) ? $attributes['constrainWidth'] : true;
				$content_width   = isset( $attributes['contentWidth'] ) ? $attributes['contentWidth'] : '';
				$align           = isset( $attributes['align'] ) ? $attributes['align'] : 'full';
				$tag_name        = isset( $attributes['tagName'] ) && $attributes['tagName'] ? $attributes['tagName'] : 'div';

				// Build outer classes (order: wp-block-*, alignX, dsgo-*).
				$outer_class_parts = array( 'wp-block-designsetgo-section' );
				if ( 'full' === $align ) {
					$outer_class_parts[] = 'alignfull';
				} elseif ( 'wide' === $align ) {
					$outer_class_parts[] = 'alignwide';
				}
				$outer_class_parts[] = 'dsgo-stack';
				if ( self::has_overlay( $attributes ) ) {
					$outer_class_parts[] = 'dsgo-stack--has-overlay';
				}
				if ( ! $constrain_width ) {
					$outer_class_parts[] = 'dsgo-no-width-constraint';
				}

				// Process block support styles (colors, padding, etc.).
				$style             = isset( $attributes['style'] ) ? $attributes['style'] : array();
				$support_result    = self::get_block_support_styles( $style );
				$outer_class_parts = array_merge( $outer_class_parts, $support_result['classes'] );

				// Only what the style attribute actually carries. Padding used to
				// be fabricated here whenever `style.spacing.padding` was empty,
				// but a block.json attribute default is REPLACED wholesale when a
				// caller supplies the attribute, not deep-merged: a section given
				// only a colour legitimately has no padding, and save() emits
				// none. apply_block_json_defaults() supplies the default `style`
				// (which does include padding) when the caller omits it entirely.
				$outer_styles = array_merge(
					self::container_hover_styles( $attributes ),
					$support_result['styles']
				);

				// Inner div always has max-width/margin for content centering.
				$max_width   = $content_width ? $content_width : 'var(--wp--style--global--content-size, 1140px)';
				$inner_style = 'max-width:' . esc_attr( $max_width ) . ';margin-left:auto;margin-right:auto';

				return array(
					'opening' => '<' . esc_attr( $tag_name ) . ' class="' . esc_attr( implode( ' ', $outer_class_parts ) ) . '" style="' . esc_attr( implode( ';', $outer_styles ) ) . '"><div class="dsgo-stack__inner" style="' . esc_attr( $inner_style ) . '">',
					'closing' => '</div></' . esc_attr( $tag_name ) . '>',
				);

			case 'designsetgo/hotspot':
				// Mirrors src/blocks/hotspot/save.js.
				$hotspot_image_url = isset( $attributes['imageUrl'] ) ? (string) $attributes['imageUrl'] : '';
				$hotspot_image_alt = isset( $attributes['imageAlt'] ) ? (string) $attributes['imageAlt'] : '';
				$hotspot_trigger   = isset( $attributes['trigger'] ) ? (string) $attributes['trigger'] : 'click';
				$tooltip_position  = isset( $attributes['tooltipPosition'] ) ? (string) $attributes['tooltipPosition'] : 'top';
				$tooltip_width     = self::numeric_attribute( $attributes['tooltipWidth'] ?? 240, 240 );
				$hotspot_animation = isset( $attributes['animation'] ) ? (string) $attributes['animation'] : 'pulse';
				$sequence_duration = self::numeric_attribute( $attributes['sequenceDuration'] ?? 0, 0 );

				$hotspot_styles = array(
					'--dsgo-hotspot-tooltip-width:' . $tooltip_width . 'px',
					'--dsgo-hotspot-sequence-duration:' . $sequence_duration . 'ms',
				);

				// Each colour is emitted only when it passes the same allowlist
				// getSafeHotspotColor() applies, so an unrecognised value drops
				// out of both paths identically.
				$hotspot_color_vars = array(
					'markerColor'            => '--dsgo-hotspot-marker-color',
					'markerBackgroundColor'  => '--dsgo-hotspot-marker-background',
					'tooltipBackgroundColor' => '--dsgo-hotspot-tooltip-background',
					'tooltipTextColor'       => '--dsgo-hotspot-tooltip-color',
				);
				foreach ( $hotspot_color_vars as $attribute_name => $custom_property ) {
					$safe = self::safe_hotspot_color( $attributes[ $attribute_name ] ?? '' );
					if ( '' !== $safe ) {
						$hotspot_styles[] = $custom_property . ':' . self::convert_color_value_to_css_var( $safe );
					}
				}

				$class_parts = array( 'wp-block-designsetgo-hotspot' );
				if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
					$class_parts[] = 'align' . $attributes['align'];
				}
				$class_parts[] = 'dsgo-hotspot';
				$class_parts[] = 'dsgo-hotspot--position-' . $tooltip_position;
				$class_parts[] = 'dsgo-hotspot--animation-' . $hotspot_animation;

				$hotspot_image_html = '' !== $hotspot_image_url
					? '<img class="dsgo-hotspot__image" src="' . esc_url( $hotspot_image_url ) . '" alt="' . esc_attr( $hotspot_image_alt ) . '"/>'
					: '<div class="dsgo-hotspot__image dsgo-hotspot__image--empty"></div>';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( implode( ';', $hotspot_styles ) ) . '"' .
						' data-dsgo-hotspot="true"' .
						' data-dsgo-hotspot-trigger="' . esc_attr( $hotspot_trigger ) . '"' .
						' data-dsgo-hotspot-position="' . esc_attr( $tooltip_position ) . '"' .
						' data-dsgo-hotspot-animation="' . esc_attr( $hotspot_animation ) . '">' .
						'<div class="dsgo-hotspot__image-wrap">' . $hotspot_image_html .
						'<div class="dsgo-hotspot__items">',
					'closing' => '</div></div></div>',
				);

			case 'designsetgo/text-path':
				// Mirrors src/blocks/text-path/save.js and its TextPathGraphic
				// component. `pathType: "custom"` is refused by
				// find_invalid_attribute_values() rather than mirrored: it runs
				// caller-supplied path data through a tokenizer whose rules are
				// the security boundary, and a second implementation of that is
				// a liability, not a feature.
				$tp_type       = isset( $attributes['pathType'] ) ? (string) $attributes['pathType'] : 'wave';
				$tp_text       = isset( $attributes['text'] ) ? (string) $attributes['text'] : '';
				$tp_unique_id  = isset( $attributes['uniqueId'] ) && '' !== $attributes['uniqueId'] ? (string) $attributes['uniqueId'] : 'path';
				$tp_path_id    = 'dsgo-text-path-' . $tp_unique_id;
				$tp_alignment  = isset( $attributes['pathAlignment'] ) ? (string) $attributes['pathAlignment'] : 'left';
				$tp_show_path  = ! empty( $attributes['showPath'] );
				$tp_direction  = isset( $attributes['direction'] ) ? (string) $attributes['direction'] : 'ltr';
				$tp_motion     = ! empty( $attributes['motion'] );
				$tp_motion_dir = isset( $attributes['motionDirection'] ) ? (string) $attributes['motionDirection'] : 'forward';

				$tp_rotation     = self::clamp_number( $attributes['rotation'] ?? 0, -360, 360, 0 );
				$tp_opacity      = self::clamp_number( $attributes['guideOpacity'] ?? 0.35, 0, 1, 0.35 );
				$tp_stroke       = self::clamp_number( $attributes['guideStrokeWidth'] ?? 2, 0, 24, 2 );
				$tp_width        = self::clamp_number( $attributes['pathWidth'] ?? 100, 25, 100, 100 );
				$tp_start_offset = self::clamp_number( $attributes['startOffset'] ?? 0, -100, 100, 0 );
				$tp_font_size    = self::clamp_number( $attributes['pathFontSize'] ?? 54, 1, 400, 54 );
				$tp_word_spacing = self::clamp_number( $attributes['wordSpacing'] ?? 0, -40, 100, 0 );
				$tp_padding      = self::clamp_number( $attributes['pathPadding'] ?? 0, -200, 200, 0 );

				$tp_guide_color  = self::safe_text_path_color( $attributes['guideColor'] ?? '' );
				$tp_circle_color = self::safe_text_path_color( $attributes['circleBackgroundColor'] ?? '' );
				$tp_url          = self::safe_text_path_url( $attributes['url'] ?? '' );

				$tp_styles = array(
					'--dsgo-text-path-rotation:' . $tp_rotation . 'deg',
					'--dsgo-text-path-guide-opacity:' . self::format_js_number( (float) $tp_opacity ),
					'--dsgo-text-path-guide-stroke-width:' . self::format_js_number( (float) $tp_stroke ),
					'--dsgo-text-path-width:' . $tp_width . '%',
				);
				if ( '' !== $tp_guide_color ) {
					$tp_styles[] = '--dsgo-text-path-guide-color:' . self::convert_color_value_to_css_var( $tp_guide_color );
				}
				if ( '' !== $tp_circle_color ) {
					$tp_styles[] = '--dsgo-text-path-circle-background:' . self::convert_color_value_to_css_var( $tp_circle_color );
				}

				$class_parts = array( 'wp-block-designsetgo-text-path' );
				if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
					$class_parts[] = 'align' . $attributes['align'];
				}
				$class_parts[] = 'dsgo-text-path';
				if ( 'center' === $tp_alignment || 'right' === $tp_alignment ) {
					$class_parts[] = 'dsgo-text-path--align-' . $tp_alignment;
				}

				$tp_motion_attrs = '';
				if ( $tp_motion ) {
					$tp_duration      = is_numeric( $attributes['motionDuration'] ?? null ) ? (float) $attributes['motionDuration'] : 12;
					$tp_duration      = 0.0 === $tp_duration ? 12 : $tp_duration;
					$tp_duration      = max( 2, min( 120, $tp_duration ) );
					$tp_motion_attrs  = ' data-dsgo-text-path-motion="true"';
					$tp_motion_attrs .= ' data-dsgo-text-path-motion-duration="' . esc_attr( self::format_js_number( (float) $tp_duration ) ) . '"';
					$tp_motion_attrs .= ' data-dsgo-text-path-motion-direction="' . ( 'reverse' === $tp_motion_dir ? 'reverse' : 'forward' ) . '"';
				}

				$tp_path = self::get_text_path_data( $tp_type, $attributes['arcSize'] ?? 100 );

				$tp_svg  = '<svg viewBox="' . esc_attr( $tp_path['viewBox'] ) . '" role="img"';
				$tp_svg .= '' !== $tp_text ? ' aria-label="' . esc_attr( $tp_text ) . '"' : '';
				$tp_svg .= '>';

				if ( 'circle' === $tp_type && '' !== $tp_circle_color ) {
					$tp_svg .= '<circle class="dsgo-text-path__circle-background" cx="500" cy="500" r="500" aria-hidden="true"></circle>';
				}

				$tp_svg .= '<defs><path id="' . esc_attr( $tp_path_id ) . '" d="' . esc_attr( $tp_path['d'] ) . '"></path></defs>';

				if ( $tp_show_path ) {
					$tp_svg .= '<path class="dsgo-text-path__guide" d="' . esc_attr( $tp_path['d'] ) . '"></path>';
				}

				$tp_offset = $tp_start_offset . '%';

				$tp_svg .= '<text direction="' . ( 'rtl' === $tp_direction ? 'rtl' : 'ltr' ) . '"' .
					' style="' . esc_attr( 'font-size:' . $tp_font_size . 'px;word-spacing:' . $tp_word_spacing . 'px' ) . '">' .
					'<textPath href="#' . esc_attr( $tp_path_id ) . '" startOffset="' . esc_attr( $tp_offset ) . '"' .
					' data-dsgo-text-path-offset="' . esc_attr( (string) $tp_start_offset ) . '">';

				$tp_svg .= ( 0 === (int) $tp_padding && is_int( $tp_padding + 0 ) && 0.0 === (float) $tp_padding )
					? esc_html( $tp_text )
					: '<tspan dy="' . esc_attr( (string) $tp_padding ) . '">' . esc_html( $tp_text ) . '</tspan>';

				$tp_svg .= '</textPath></text></svg>';

				if ( '' !== $tp_url ) {
					$tp_target = ! empty( $attributes['target'] ) ? ' target="_blank"' : '';
					$tp_svg    = '<a href="' . esc_url( $tp_url ) . '"' . $tp_target . ' rel="noopener noreferrer">' . $tp_svg . '</a>';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( implode( ';', $tp_styles ) ) . '"' .
						$tp_motion_attrs . '>' . $tp_svg,
					'closing' => '</div>',
				);

			case 'designsetgo/comparison-table':
				// Mirrors src/blocks/comparison-table/save.js. The block holds no
				// inner blocks: the whole table is built from the columns and
				// rows attributes.
				$table_columns   = ( isset( $attributes['columns'] ) && is_array( $attributes['columns'] ) ) ? $attributes['columns'] : array();
				$table_rows      = ( isset( $attributes['rows'] ) && is_array( $attributes['rows'] ) ) ? $attributes['rows'] : array();
				$alternating     = ! empty( $attributes['alternatingRows'] );
				$responsive_mode = isset( $attributes['responsiveMode'] ) ? (string) $attributes['responsiveMode'] : 'scroll';
				$show_ctas       = ! empty( $attributes['showCtaButtons'] );
				$cta_style       = isset( $attributes['ctaStyle'] ) ? (string) $attributes['ctaStyle'] : 'filled';

				$class_parts = array( 'wp-block-designsetgo-comparison-table' );
				if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
					$class_parts[] = 'align' . $attributes['align'];
				}
				$class_parts[] = 'dsgo-comparison-table';
				if ( $alternating ) {
					$class_parts[] = 'dsgo-comparison-table--alternating';
				}
				if ( 'stack' === $responsive_mode ) {
					$class_parts[] = 'dsgo-comparison-table--responsive-stack';
				}
				if ( 'scroll' === $responsive_mode ) {
					$class_parts[] = 'dsgo-comparison-table--responsive-scroll';
				}

				$table_styles     = array();
				$table_color_vars = array(
					'featuredColumnColor'   => '--dsgo-comparison-featured-color',
					'headerBackgroundColor' => '--dsgo-comparison-header-bg',
					'headerTextColor'       => '--dsgo-comparison-header-text',
				);
				foreach ( $table_color_vars as $attribute_name => $custom_property ) {
					$colour = isset( $attributes[ $attribute_name ] ) ? (string) $attributes[ $attribute_name ] : '';
					if ( '' !== $colour ) {
						$table_styles[] = $custom_property . ':' . self::convert_color_value_to_css_var( $colour );
					}
				}

				// Header row.
				$header_cells = '<th class="dsgo-comparison-table__header-cell dsgo-comparison-table__header-cell--label"></th>';
				foreach ( $table_columns as $column ) {
					$col_featured  = ! empty( $column['featured'] );
					$col_name      = isset( $column['name'] ) ? (string) $column['name'] : '';
					$col_link      = isset( $column['link'] ) ? (string) $column['link'] : '';
					$col_link_text = isset( $column['linkText'] ) ? (string) $column['linkText'] : '';

					$header_cells .= '<th class="' . esc_attr(
						'dsgo-comparison-table__header-cell' . ( $col_featured ? ' dsgo-comparison-table__header-cell--featured' : '' )
					) . '">';

					if ( $col_featured ) {
						$header_cells .= '<span class="dsgo-comparison-table__featured-badge">' . esc_html__( 'Popular', 'designsetgo' ) . '</span>';
					}

					$header_cells .= '<span class="dsgo-comparison-table__column-name">' . wp_kses_post( $col_name ) . '</span>';

					if ( $show_ctas && '' !== $col_link ) {
						$header_cells .= '<a href="' . esc_url( $col_link ) . '" class="' .
							esc_attr( 'dsgo-comparison-table__cta dsgo-comparison-table__cta--' . $cta_style ) .
							'" rel="noopener noreferrer">' .
							esc_html( '' !== $col_link_text ? $col_link_text : __( 'Get Started', 'designsetgo' ) ) .
							'</a>';
					} elseif ( $show_ctas && '' !== $col_link_text ) {
						$header_cells .= '<span class="' .
							esc_attr( 'dsgo-comparison-table__cta dsgo-comparison-table__cta--' . $cta_style ) . '">' .
							esc_html( $col_link_text ) . '</span>';
					}

					$header_cells .= '</th>';
				}

				// Body rows.
				$body_rows = '';
				foreach ( $table_rows as $row ) {
					$row_label   = isset( $row['label'] ) ? (string) $row['label'] : '';
					$row_tooltip = isset( $row['tooltip'] ) ? (string) $row['tooltip'] : '';
					$row_cells   = ( isset( $row['cells'] ) && is_array( $row['cells'] ) ) ? $row['cells'] : array();

					$body_rows .= '<tr class="dsgo-comparison-table__row">' .
						'<td class="dsgo-comparison-table__cell dsgo-comparison-table__cell--label">' .
						'<div class="dsgo-comparison-table__label-wrapper">' .
						'<span class="dsgo-comparison-table__row-label">' . wp_kses_post( $row_label ) . '</span>';

					if ( '' !== $row_tooltip ) {
						$body_rows .= '<span class="dsgo-comparison-table__tooltip-trigger" data-tooltip="' . esc_attr( $row_tooltip ) .
							'" aria-label="' . esc_attr( $row_tooltip ) . '" role="button" tabindex="0">?</span>';
					}

					$body_rows .= '</div></td>';

					foreach ( $row_cells as $cell_index => $cell ) {
						$cell_type     = isset( $cell['type'] ) ? (string) $cell['type'] : 'text';
						$cell_value    = isset( $cell['value'] ) ? (string) $cell['value'] : '';
						$cell_column   = $table_columns[ $cell_index ] ?? array();
						$cell_featured = ! empty( $cell_column['featured'] );
						$cell_label    = isset( $cell_column['name'] ) ? (string) $cell_column['name'] : '';

						$body_rows .= '<td class="' . esc_attr(
							'dsgo-comparison-table__cell' . ( $cell_featured ? ' dsgo-comparison-table__cell--featured' : '' )
						) . '" data-label="' . esc_attr( $cell_label ) . '">' .
							'<div class="dsgo-comparison-table__cell-content">';

						if ( 'check' === $cell_type ) {
							$body_rows .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none"' .
								' stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"' .
								' class="dsgo-comparison-table__icon dsgo-comparison-table__icon--check" aria-label="Yes" role="img">' .
								'<polyline points="20 6 9 17 4 12"></polyline></svg>';
						} elseif ( 'cross' === $cell_type ) {
							$body_rows .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none"' .
								' stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"' .
								' class="dsgo-comparison-table__icon dsgo-comparison-table__icon--cross" aria-label="No" role="img">' .
								'<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
						} elseif ( 'text' === $cell_type ) {
							$body_rows .= '<span class="dsgo-comparison-table__cell-text">' . wp_kses_post( $cell_value ) . '</span>';
						}

						$body_rows .= '</div></td>';
					}

					$body_rows .= '</tr>';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' .
						( empty( $table_styles ) ? '' : ' style="' . esc_attr( implode( ';', $table_styles ) ) . '"' ) . '>' .
						'<div class="dsgo-comparison-table__wrapper">' .
						'<table class="dsgo-comparison-table__table">' .
						'<thead class="dsgo-comparison-table__header"><tr>' . $header_cells . '</tr></thead>' .
						'<tbody class="dsgo-comparison-table__body">' . $body_rows . '</tbody>' .
						'</table></div>',
					'closing' => '</div>',
				);

			case 'designsetgo/timeline-item':
				// Mirrors src/blocks/timeline-item/save.js.
				//
				// That save() reads marker styling from parent context, but
				// WordPress passes NO context to getSaveElement(), so the stored
				// markup always uses the fallbacks: a circle marker at 16px in
				// the primary preset colour. Reading the parent's attributes here
				// would produce markup save() never writes.
				$item_date       = isset( $attributes['date'] ) ? (string) $attributes['date'] : '';
				$item_title      = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
				$item_image_url  = isset( $attributes['imageUrl'] ) ? (string) $attributes['imageUrl'] : '';
				$item_is_active  = ! empty( $attributes['isActive'] );
				$item_link_url   = isset( $attributes['linkUrl'] ) ? (string) $attributes['linkUrl'] : '';
				$item_link_targ  = isset( $attributes['linkTarget'] ) ? (string) $attributes['linkTarget'] : '_self';
				$item_marker_col = isset( $attributes['customMarkerColor'] ) ? (string) $attributes['customMarkerColor'] : '';

				$item_safe_url = self::safe_hotspot_url( $item_link_url );

				$effective_marker = '' !== $item_marker_col
					? $item_marker_col
					: 'var(--wp--preset--color--primary, #2563eb)';

				$class_parts = array( 'wp-block-designsetgo-timeline-item', 'dsgo-timeline-item' );
				if ( $item_is_active ) {
					$class_parts[] = 'dsgo-timeline-item--active';
				}
				if ( '' !== $item_image_url ) {
					$class_parts[] = 'dsgo-timeline-item--has-image';
				}
				if ( '' !== $item_safe_url ) {
					$class_parts[] = 'dsgo-timeline-item--has-link';
				}

				$item_style = '' !== $item_marker_col
					? ' style="' . esc_attr( '--dsgo-timeline-item-marker-color:' . self::convert_color_value_to_css_var( $item_marker_col ) ) . '"'
					: '';

				// Marker: an image when set, otherwise the default circle SVG.
				if ( '' !== $item_image_url ) {
					$marker_inner = '<img src="' . esc_url( $item_image_url ) . '" alt="" class="dsgo-timeline-item__marker-image"' .
						' style="' . esc_attr( 'width:16px;height:16px;border-radius:50%;object-fit:cover' ) . '"/>';
				} else {
					$marker_inner = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' .
						'<circle cx="12" cy="12" r="10" fill="' . esc_attr( $effective_marker ) . '"' .
						' stroke="' . esc_attr( $effective_marker ) . '" stroke-width="2"></circle></svg>';
				}

				$marker_html = '<div class="dsgo-timeline-item__marker" aria-hidden="true">' . $marker_inner . '</div>';

				$date_html  = '' !== $item_date
					? '<span class="dsgo-timeline-item__date">' . wp_kses_post( $item_date ) . '</span>'
					: '';
				$title_html = '' !== $item_title
					? '<h3 class="dsgo-timeline-item__title">' . wp_kses_post( $item_title ) . '</h3>'
					: '';

				if ( '' !== $item_safe_url ) {
					$link_rel  = '_blank' === $item_link_targ ? ' rel="noopener noreferrer"' : '';
					$open_link = '<a href="' . esc_url( $item_safe_url ) . '" target="' . esc_attr( $item_link_targ ) . '"' . $link_rel .
						' class="dsgo-timeline-item__link">';
					$close_link = '</a>';
				} else {
					$open_link  = '';
					$close_link = '';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $item_style . '>' .
						$marker_html . $open_link .
						'<div class="dsgo-timeline-item__wrapper">' . $date_html . $title_html .
						'<div class="dsgo-timeline-item__content">',
					'closing' => '</div></div>' . $close_link . '</div>',
				);

			case 'designsetgo/hotspot-item':
				// Mirrors src/blocks/hotspot-item/save.js.
				$item_unique_id = isset( $attributes['uniqueId'] ) && '' !== $attributes['uniqueId']
					? (string) $attributes['uniqueId']
					: 'item';
				$marker_id      = 'dsgo-hotspot-marker-' . $item_unique_id;
				$tooltip_id     = 'dsgo-hotspot-tooltip-' . $item_unique_id;

				$item_x        = self::clamp_hotspot_coordinate( $attributes['x'] ?? 50 );
				$item_y        = self::clamp_hotspot_coordinate( $attributes['y'] ?? 50 );
				$origin_x      = isset( $attributes['originX'] ) ? (string) $attributes['originX'] : 'center';
				$origin_y      = isset( $attributes['originY'] ) ? (string) $attributes['originY'] : 'center';
				$item_label    = isset( $attributes['label'] ) ? (string) $attributes['label'] : '+';
				$item_icon     = isset( $attributes['icon'] ) ? (string) $attributes['icon'] : '';
				$item_tooltip  = isset( $attributes['tooltip'] ) ? (string) $attributes['tooltip'] : 'Add a description';
				$item_position = isset( $attributes['tooltipPosition'] ) ? (string) $attributes['tooltipPosition'] : 'inherit';
				$item_trigger  = isset( $attributes['trigger'] ) ? (string) $attributes['trigger'] : 'inherit';
				$item_anim     = isset( $attributes['animation'] ) ? (string) $attributes['animation'] : 'inherit';
				$item_order    = self::numeric_attribute( $attributes['sequenceOrder'] ?? 0, 0 );
				$safe_url      = self::safe_hotspot_url( $attributes['url'] ?? '' );

				$item_styles = array(
					'--dsgo-hotspot-x:' . $item_x . '%',
					'--dsgo-hotspot-y:' . $item_y . '%',
				);
				// save.js writes the width only for a real number, so an unset
				// width must not appear at all.
				if ( isset( $attributes['tooltipWidth'] ) && is_numeric( $attributes['tooltipWidth'] ) ) {
					$item_styles[] = '--dsgo-hotspot-tooltip-width:' . self::numeric_attribute( $attributes['tooltipWidth'] ) . 'px';
				}
				$item_styles[] = '--dsgo-hotspot-sequence-order:' . $item_order;
				$item_styles[] = '--dsgo-hotspot-origin-x:' . $origin_x;
				$item_styles[] = '--dsgo-hotspot-origin-y:' . $origin_y;

				$class_parts = array(
					'wp-block-designsetgo-hotspot-item',
					'dsgo-hotspot-item',
					'dsgo-hotspot-item--position-' . $item_position,
					'dsgo-hotspot-item--animation-' . $item_anim,
					'dsgo-hotspot-item--origin-x-' . $origin_x,
					'dsgo-hotspot-item--origin-y-' . $origin_y,
				);

				$is_linked = '' !== $safe_url;

				// Attribute order here matches save.js so the emitted markup is
				// byte-comparable; the validator is order-insensitive, but the
				// conditions are not interchangeable.
				$marker_attrs = ' class="dsgo-hotspot-item__marker" id="' . esc_attr( $marker_id ) . '"';
				if ( ! $is_linked && 'click' === $item_trigger ) {
					$marker_attrs .= ' aria-expanded="false" aria-controls="' . esc_attr( $tooltip_id ) . '"';
				}
				if ( $is_linked || 'hover' === $item_trigger ) {
					$marker_attrs .= ' aria-describedby="' . esc_attr( $tooltip_id ) . '"';
				}
				// save.js labels the marker only when its visible content is not
				// meaningful text.
				if ( '' !== $item_icon || '' === $item_label || '+' === $item_label ) {
					$marker_attrs .= ' aria-label="' . esc_attr__( 'Hotspot', 'designsetgo' ) . '"';
				}
				$marker_attrs .= ' data-dsgo-hotspot-marker="true"';

				$marker_content = '' !== $item_icon ? $item_icon : ( '' !== $item_label ? $item_label : '+' );

				$marker_html = $is_linked
					? '<a' . $marker_attrs . ' href="' . esc_url( $safe_url ) . '">' . esc_html( $marker_content ) . '</a>'
					: '<button' . $marker_attrs . ' type="button">' . esc_html( $marker_content ) . '</button>';

				$item_data_trigger = ( 'inherit' === $item_trigger )
					? ''
					: ' data-dsgo-hotspot-trigger="' . esc_attr( $item_trigger ) . '"';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( implode( ';', $item_styles ) ) . '"' .
						' data-dsgo-hotspot-item="true"' . $item_data_trigger . '>' .
						$marker_html .
						'<div class="dsgo-hotspot-item__tooltip" id="' . esc_attr( $tooltip_id ) . '" role="tooltip"' .
						' data-dsgo-hotspot-tooltip="true" hidden aria-hidden="true">' .
						'<span>' . wp_kses_post( $item_tooltip ) . '</span></div>',
					'closing' => '</div>',
				);

			case 'designsetgo/advanced-heading':
				// Mirrors src/blocks/advanced-heading/save.js. The animated
				// headline variant is refused by find_invalid_attribute_values()
				// rather than approximated here.
				$heading_level = self::numeric_attribute( $attributes['level'] ?? 2, 2 );
				if ( ! in_array( (int) $heading_level, array( 1, 2, 3, 4, 5, 6 ), true ) ) {
					$heading_level = 2;
				}
				$heading_tag   = 'h' . (int) $heading_level;
				$text_align    = isset( $attributes['textAlign'] ) ? (string) $attributes['textAlign'] : '';

				$class_parts   = array( 'wp-block-designsetgo-advanced-heading' );
				$heading_align = self::align_class( $block_name, $attributes );
				if ( '' !== $heading_align ) {
					$class_parts[] = $heading_align;
				}
				$class_parts[] = 'dsgo-advanced-heading';
				if ( '' !== $text_align ) {
					$class_parts[] = 'has-text-align-' . $text_align;
				}

				$block_gap   = $attributes['style']['spacing']['blockGap'] ?? '';
				$inner_style = ( is_string( $block_gap ) && '' !== $block_gap )
					? ' style="' . esc_attr( '--dsgo-segment-gap:' . self::wp_shorthand_to_css_var( $block_gap ) ) . '"'
					: '';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '">' .
						'<' . $heading_tag . ' class="dsgo-advanced-heading__inner"' . $inner_style . '>',
					'closing' => '</' . $heading_tag . '></div>',
				);

			case 'designsetgo/blobs':
				// Mirrors src/blocks/blobs/save.js.
				$blob_shape     = isset( $attributes['blobShape'] ) ? (string) $attributes['blobShape'] : 'shape-1';
				$blob_animation = isset( $attributes['blobAnimation'] ) ? (string) $attributes['blobAnimation'] : 'none';
				$blob_duration  = isset( $attributes['animationDuration'] ) ? (string) $attributes['animationDuration'] : '8s';
				$blob_easing    = isset( $attributes['animationEasing'] ) ? (string) $attributes['animationEasing'] : 'ease-in-out';
				$blob_size      = isset( $attributes['size'] ) ? (string) $attributes['size'] : '300px';
				$blob_height    = isset( $attributes['height'] ) ? (string) $attributes['height'] : '';
				$blob_max_width = isset( $attributes['maxWidth'] ) ? $attributes['maxWidth'] : null;
				$enable_overlay = ! empty( $attributes['enableOverlay'] );
				$overlay_color  = isset( $attributes['overlayColor'] ) ? (string) $attributes['overlayColor'] : '';
				$overlay_pct    = isset( $attributes['overlayOpacity'] ) && is_numeric( $attributes['overlayOpacity'] )
					? (float) $attributes['overlayOpacity']
					: 80;

				// hasExplicitString(): a non-empty trimmed string.
				$has_max_width = is_string( $blob_max_width ) && '' !== trim( $blob_max_width );

				$wrapper_classes = array( 'wp-block-designsetgo-blobs' );
				$blobs_align     = self::align_class( $block_name, $attributes );
				if ( '' !== $blobs_align ) {
					$wrapper_classes[] = $blobs_align;
				}
				$wrapper_classes[] = 'dsgo-blobs-wrapper';
				if ( $has_max_width ) {
					$wrapper_classes[] = 'dsgo-has-max-width';
				}

				$blob_classes = array( 'dsgo-blobs' );
				if ( '' !== $blob_shape ) {
					$blob_classes[] = 'dsgo-blobs--' . $blob_shape;
				}
				if ( '' !== $blob_animation && 'none' !== $blob_animation ) {
					$blob_classes[] = 'dsgo-blobs--' . $blob_animation;
				}

				// save.js writes size, duration and easing unconditionally;
				// height only when set.
				$blob_styles = array( '--dsgo-blob-size:' . $blob_size );
				if ( '' !== $blob_height ) {
					$blob_styles[] = '--dsgo-blob-height:' . $blob_height;
				}
				$blob_styles[] = '--dsgo-blob-animation-duration:' . $blob_duration;
				$blob_styles[] = '--dsgo-blob-animation-easing:' . $blob_easing;

				$overlay_html = '';
				if ( $enable_overlay ) {
					$overlay_html = '<div class="dsgo-blobs__overlay" style="' . esc_attr(
						'background-color:' . self::convert_color_value_to_css_var( $overlay_color ) .
						';opacity:' . self::format_js_number( $overlay_pct / 100 )
					) . '"></div>';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"' .
						( $has_max_width ? ' style="' . esc_attr( '--dsgo-blob-max-width:' . $blob_max_width ) . '"' : '' ) . '>' .
						'<div class="' . esc_attr( implode( ' ', $blob_classes ) ) . '" style="' . esc_attr( implode( ';', $blob_styles ) ) . '"' .
						' data-blob-animation="' . esc_attr( $blob_animation ) . '">' .
						$overlay_html .
						'<div class="dsgo-blobs__shape"><div class="dsgo-blobs__content">',
					'closing' => '</div></div></div></div>',
				);

			case 'designsetgo/heading-segment':
				// Mirrors src/blocks/heading-segment/save.js. Only the "normal"
				// role is generated: the animated role serializes a JSON word
				// list and an inline highlight SVG, which get_serialization_gap()
				// refuses rather than approximate.
				$segment_text = isset( $attributes['content'] ) && is_string( $attributes['content'] ) && '' !== trim( $attributes['content'] )
					? $attributes['content']
					: ( isset( $attributes['normalContent'] ) && is_string( $attributes['normalContent'] ) ? $attributes['normalContent'] : '' );

				// save() returns null for a non-animated segment with no text,
				// which WordPress serializes as a self-closing comment with no
				// markup at all. Emitting empty spans instead made every
				// text-less segment invalid.
				if ( '' === trim( $segment_text ) ) {
					return array(
						'opening' => '',
						'closing' => '',
					);
				}

				return array(
					'opening' => '<span class="wp-block-designsetgo-heading-segment dsgo-heading-segment">' .
						'<span class="dsgo-heading-segment__text">' . wp_kses_post( $segment_text ),
					'closing' => '</span></span>',
				);

			case 'designsetgo/timeline':
				// Mirrors src/blocks/timeline/save.js.
				$line_color         = isset( $attributes['lineColor'] ) ? (string) $attributes['lineColor'] : '';
				$line_thickness     = self::numeric_attribute( $attributes['lineThickness'] ?? 2, 2 );
				$connector_style    = isset( $attributes['connectorStyle'] ) ? (string) $attributes['connectorStyle'] : 'solid';
				$marker_style       = isset( $attributes['markerStyle'] ) ? (string) $attributes['markerStyle'] : 'circle';
				$marker_size        = self::numeric_attribute( $attributes['markerSize'] ?? 16, 16 );
				$marker_color       = isset( $attributes['markerColor'] ) ? (string) $attributes['markerColor'] : '';
				$marker_border      = isset( $attributes['markerBorderColor'] ) ? (string) $attributes['markerBorderColor'] : '';
				$item_spacing       = isset( $attributes['itemSpacing'] ) ? (string) $attributes['itemSpacing'] : '2rem';
				$animate_on_scroll  = ! empty( $attributes['animateOnScroll'] );
				$animation_duration = self::numeric_attribute( $attributes['animationDuration'] ?? 600, 600 );
				$stagger_delay      = self::numeric_attribute( $attributes['staggerDelay'] ?? 100, 100 );
				$orientation        = isset( $attributes['orientation'] ) ? (string) $attributes['orientation'] : 'vertical';
				$timeline_layout    = isset( $attributes['layout'] ) ? $attributes['layout'] : 'alternating';

				// save.js writes every custom property unconditionally, falling
				// back to the same literals used here.
				$timeline_styles = array(
					'--dsgo-timeline-line-color:' . ( '' !== $line_color ? $line_color : 'var(--wp--preset--color--contrast, #e5e7eb)' ),
					'--dsgo-timeline-line-thickness:' . $line_thickness . 'px',
					'--dsgo-timeline-connector-style:' . $connector_style,
					'--dsgo-timeline-marker-size:' . $marker_size . 'px',
					'--dsgo-timeline-marker-color:' . ( '' !== $marker_color ? $marker_color : 'var(--wp--preset--color--primary, #2563eb)' ),
					'--dsgo-timeline-marker-border-color:' . ( '' !== $marker_border ? $marker_border : ( '' !== $marker_color ? $marker_color : 'var(--wp--preset--color--primary, #2563eb)' ) ),
					'--dsgo-timeline-item-spacing:' . $item_spacing,
					'--dsgo-timeline-animation-duration:' . $animation_duration . 'ms',
				);

				$class_parts = array( 'wp-block-designsetgo-timeline' );
				if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
					$class_parts[] = 'align' . $attributes['align'];
				}
				$class_parts[] = 'dsgo-timeline';
				if ( '' !== $orientation ) {
					$class_parts[] = 'dsgo-timeline--' . $orientation;
				}
				if ( is_string( $timeline_layout ) && '' !== $timeline_layout ) {
					$class_parts[] = 'dsgo-timeline--layout-' . $timeline_layout;
				}
				if ( '' !== $marker_style ) {
					$class_parts[] = 'dsgo-timeline--marker-' . $marker_style;
				}
				if ( $animate_on_scroll ) {
					$class_parts[] = 'dsgo-timeline--animate';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( implode( ';', $timeline_styles ) ) . '"' .
						' data-animate="' . ( $animate_on_scroll ? 'true' : 'false' ) . '"' .
						' data-animation-duration="' . esc_attr( (string) $animation_duration ) . '"' .
						' data-stagger-delay="' . esc_attr( (string) $stagger_delay ) . '">' .
						'<div class="dsgo-timeline__line" aria-hidden="true"></div>' .
						'<div class="dsgo-timeline__items">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/query':
			case 'designsetgo/query-results':
				// Mirrors src/blocks/query/save.js and query-results/save.js.
				// Both are dynamic — render.php owns the frontend HTML — but
				// their save() still emits a wrapper div so WordPress persists
				// the per-item template blocks inside it. Stored markup without
				// that wrapper is invalid in the editor.
				$query_slug    = str_replace( 'designsetgo/', '', $block_name );
				$query_classes = 'wp-block-designsetgo-' . $query_slug;
				$query_align   = self::align_class( $block_name, $attributes );
				if ( '' !== $query_align ) {
					$query_classes .= ' ' . $query_align;
				}

				return array(
					'opening' => '<div class="' . esc_attr( $query_classes ) . '">',
					'closing' => '</div>',
				);

			case 'designsetgo/query-no-results':
				// Mirrors src/blocks/query-no-results/save.js.
				return array(
					'opening' => '<div class="wp-block-designsetgo-query-no-results dsgo-query-no-results">',
					'closing' => '</div>',
				);

			case 'designsetgo/scroll-slide':
				// Mirrors src/blocks/scroll-slide/save.js. A hybrid block: it has
				// a render.php AND a save.js, so the stored markup must carry the
				// wrapper the frontend looks for.
				$nav_heading = isset( $attributes['navHeading'] ) ? (string) $attributes['navHeading'] : '';

				return array(
					'opening' => '<div class="wp-block-designsetgo-scroll-slide dsgo-scroll-slide"' .
						' data-dsgo-nav-heading="' . esc_attr( $nav_heading ) . '">',
					'closing' => '</div>',
				);

			case 'designsetgo/scroll-slides':
				// Mirrors src/blocks/scroll-slides/save.js.
				$min_height       = ( isset( $attributes['minHeight'] ) && '' !== $attributes['minHeight'] ) ? (string) $attributes['minHeight'] : '100vh';
				$max_height       = isset( $attributes['maxHeight'] ) ? (string) $attributes['maxHeight'] : '';
				$constrain_width  = ! isset( $attributes['constrainWidth'] ) || $attributes['constrainWidth'];
				$content_width    = isset( $attributes['contentWidth'] ) ? (string) $attributes['contentWidth'] : '';
				$overlay_color    = isset( $attributes['overlayColor'] ) ? (string) $attributes['overlayColor'] : '';
				$nav_color        = isset( $attributes['navColor'] ) ? (string) $attributes['navColor'] : '';
				$nav_active_color = isset( $attributes['navActiveColor'] ) ? (string) $attributes['navActiveColor'] : '';

				$class_parts = array( 'wp-block-designsetgo-scroll-slides' );
				if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
					$class_parts[] = 'align' . $attributes['align'];
				}
				$class_parts[] = 'dsgo-scroll-slides';
				if ( '' !== $overlay_color ) {
					$class_parts[] = 'dsgo-scroll-slides--has-overlay';
				}
				if ( ! $constrain_width ) {
					$class_parts[] = 'dsgo-scroll-slides--no-width-constraint';
				}
				if ( '' !== $nav_color || '' !== $nav_active_color ) {
					$class_parts[] = 'dsgo-scroll-slides--has-nav-color';
				}

				$slides_styles = array();
				if ( '' !== $overlay_color ) {
					$slides_styles[] = '--dsgo-overlay-color:' . self::convert_color_value_to_css_var( $overlay_color );
					// Mirrors overlayOpacityFraction(): clamp to 0-100, default
					// 80, then divide. PHP would print 0.8 as "0.8" like JS does.
					$opacity         = isset( $attributes['overlayOpacity'] ) && is_numeric( $attributes['overlayOpacity'] )
						? min( 100, max( 0, (float) $attributes['overlayOpacity'] ) )
						: 80;
					$slides_styles[] = '--dsgo-overlay-opacity:' . self::format_js_number( $opacity / 100 );
				}
				if ( '' !== $nav_color ) {
					$slides_styles[] = '--dsgo-nav-color:' . self::convert_color_value_to_css_var( $nav_color );
				}
				if ( '' !== $nav_active_color ) {
					$slides_styles[] = '--dsgo-nav-active-color:' . self::convert_color_value_to_css_var( $nav_active_color );
				}

				$inner_styles = array();
				if ( $constrain_width ) {
					$inner_styles[] = 'max-width:' . ( '' !== $content_width ? $content_width : 'var(--wp--style--global--content-size, 1140px)' );
					$inner_styles[] = 'margin-left:auto';
					$inner_styles[] = 'margin-right:auto';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' .
						' data-dsgo-min-height="' . esc_attr( $min_height ) . '"' .
						( '' !== $max_height ? ' data-dsgo-max-height="' . esc_attr( $max_height ) . '"' : '' ) .
						( empty( $slides_styles ) ? '' : ' style="' . esc_attr( implode( ';', $slides_styles ) ) . '"' ) . '>' .
						'<div class="dsgo-scroll-slides__inner"' .
						( empty( $inner_styles ) ? '' : ' style="' . esc_attr( implode( ';', $inner_styles ) ) . '"' ) . '>' .
						'<div class="dsgo-scroll-slides__panels">',
					'closing' => '</div></div></div>',
				);

			case 'designsetgo/sticky-sections':
				// Mirrors src/blocks/sticky-sections/save.js.
				$sticky_offset = ( isset( $attributes['stickyOffset'] ) && '' !== $attributes['stickyOffset'] )
					? (string) $attributes['stickyOffset']
					: '0px';

				$class_parts = array( 'wp-block-designsetgo-sticky-sections' );
				if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
					$class_parts[] = 'align' . $attributes['align'];
				}
				$class_parts[] = 'dsgo-sticky-sections';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' .
						esc_attr( '--dsgo-sticky-offset:' . $sticky_offset ) . '">',
					'closing' => '</div>',
				);

			case 'designsetgo/section-divider':
				// Mirrors src/blocks/section-divider/save.js plus its utils/.
				$class_parts = array( 'wp-block-designsetgo-section-divider' );
				if ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
					$class_parts[] = 'align' . $attributes['align'];
				}

				// Wrapper style: background only, and only when set.
				$wrapper_styles = array();
				if ( ! empty( $attributes['backgroundColor'] ) && is_string( $attributes['backgroundColor'] ) ) {
					$wrapper_styles[] = '--dsgo-section-divider-bg:' .
						self::convert_color_value_to_css_var( $attributes['backgroundColor'] );
				}

				// Shape style: each custom property is emitted only when the
				// attribute differs from the CSS-inherited default, so a default
				// divider carries no inline style at all.
				$shape_styles = array();
				if ( ! empty( $attributes['fillColor'] ) && is_string( $attributes['fillColor'] ) ) {
					$shape_styles[] = '--dsgo-section-divider-fill:' .
						self::convert_color_value_to_css_var( $attributes['fillColor'] );
				}
				if ( self::is_explicit_shape_size( $attributes['height'] ?? null ) ) {
					$shape_styles[] = '--dsgo-shape-height:' . $attributes['height'] . 'px';
				}
				if ( self::is_explicit_shape_size( $attributes['width'] ?? null ) ) {
					$shape_styles[] = '--dsgo-shape-width:' . $attributes['width'] . '%';
				}
				if ( ! empty( $attributes['flipX'] ) ) {
					$shape_styles[] = '--dsgo-shape-flip-x:-1';
				}
				if ( ! empty( $attributes['flipY'] ) ) {
					$shape_styles[] = '--dsgo-shape-flip-y:-1';
				}

				$shape       = isset( $attributes['shape'] ) ? (string) $attributes['shape'] : 'inherit';
				$shape_class = 'inherit' === $shape ? 'is-shape-inherit' : 'is-shape-' . $shape;

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' .
						( empty( $wrapper_styles ) ? '' : ' style="' . esc_attr( implode( ';', $wrapper_styles ) ) . '"' ) . '>' .
						'<div class="' . esc_attr( 'dsgo-section-divider__shape dsgo-shape-divider ' . $shape_class ) . '"' .
						( empty( $shape_styles ) ? '' : ' style="' . esc_attr( implode( ';', $shape_styles ) ) . '"' ) .
						' aria-hidden="true"></div>',
					'closing' => '</div>',
				);

			case 'designsetgo/fifty-fifty':
				// Mirrors src/blocks/fifty-fifty/save.js.
				$media_position = ( isset( $attributes['mediaPosition'] ) && 'right' === $attributes['mediaPosition'] ) ? 'right' : 'left';
				$media_url      = isset( $attributes['mediaUrl'] ) ? (string) $attributes['mediaUrl'] : '';
				$media_alt      = isset( $attributes['mediaAlt'] ) ? (string) $attributes['mediaAlt'] : '';
				$focal_point    = ( isset( $attributes['focalPoint'] ) && is_array( $attributes['focalPoint'] ) ) ? $attributes['focalPoint'] : null;
				$min_height     = isset( $attributes['minHeight'] ) ? (string) $attributes['minHeight'] : '';
				$vertical_align = isset( $attributes['verticalAlignment'] ) ? (string) $attributes['verticalAlignment'] : '';
				$content_pad    = isset( $attributes['contentPadding'] ) ? $attributes['contentPadding'] : '';

				$class_parts = array( 'wp-block-designsetgo-fifty-fifty' );
				if ( isset( $attributes['align'] ) && 'full' === $attributes['align'] ) {
					$class_parts[] = 'alignfull';
				}
				$class_parts[] = 'dsgo-fifty-fifty';
				$class_parts[] = 'dsgo-fifty-fifty--media-' . $media_position;

				$fifty_styles = array();

				// save.js only writes minHeight when it matches this pattern.
				if ( '' !== $min_height && preg_match( '/^[\d.]+(px|vh|vw|em|rem|%)$/', $min_height ) ) {
					$fifty_styles[] = '--dsgo-fifty-fifty-min-height:' . $min_height;
				}

				$align_items_map = array(
					'top'    => 'flex-start',
					'center' => 'center',
					'bottom' => 'flex-end',
				);
				// Always written: save.js falls back to 'center'.
				$fifty_styles[] = '--dsgo-fifty-fifty-content-justify:' . ( $align_items_map[ $vertical_align ] ?? 'center' );

				$content_pad_css = is_string( $content_pad ) ? self::wp_shorthand_to_css_var( $content_pad ) : '';
				if ( '' !== $content_pad_css ) {
					$fifty_styles[] = '--dsgo-fifty-fifty-content-padding:' . $content_pad_css;
				}

				// The media <img> is emitted only for an http(s) URL, matching
				// isValidImageUrl() in save.js.
				$media_html = '';
				if ( '' !== $media_url && preg_match( '#^https?://#', $media_url ) ) {
					$object_position = '';
					if ( $focal_point && isset( $focal_point['x'], $focal_point['y'] ) ) {
						$object_position = ' style="object-position:' .
							esc_attr( ( (float) $focal_point['x'] * 100 ) . '% ' . ( (float) $focal_point['y'] * 100 ) . '%' ) . '"';
					}

					$media_html = '<img src="' . esc_url( $media_url ) . '" alt="' . esc_attr( $media_alt ) . '"' .
						$object_position . ' loading="lazy"' .
						( '' === $media_alt ? ' aria-hidden="true"' : '' ) . '/>';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( implode( ';', $fifty_styles ) ) . '">' .
						'<div class="dsgo-fifty-fifty__media">' . $media_html . '</div>' .
						'<div class="dsgo-fifty-fifty__content"><div class="dsgo-fifty-fifty__content-inner">',
					'closing' => '</div></div></div>',
				);

			case 'designsetgo/row':
				$constrain_width = isset( $attributes['constrainWidth'] ) ? $attributes['constrainWidth'] : false;
				$content_width   = isset( $attributes['contentWidth'] ) ? $attributes['contentWidth'] : '';
				$mobile_stack    = isset( $attributes['mobileStack'] ) ? $attributes['mobileStack'] : false;
				$align           = isset( $attributes['align'] ) ? $attributes['align'] : 'full';
				$layout          = isset( $attributes['layout'] ) ? $attributes['layout'] : array();
				$justify_content = isset( $layout['justifyContent'] ) ? $layout['justifyContent'] : 'left';
				$flex_wrap       = isset( $layout['flexWrap'] ) ? $layout['flexWrap'] : 'nowrap';

				// Build outer classes (order: wp-block-*, alignX, dsgo-*).
				$outer_class_parts = array( 'wp-block-designsetgo-row' );
				if ( 'full' === $align ) {
					$outer_class_parts[] = 'alignfull';
				} elseif ( 'wide' === $align ) {
					$outer_class_parts[] = 'alignwide';
				}
				$outer_class_parts[] = 'dsgo-flex';
				if ( self::has_overlay( $attributes ) ) {
					$outer_class_parts[] = 'dsgo-flex--has-overlay';
				}
				if ( $mobile_stack ) {
					$outer_class_parts[] = 'dsgo-flex--mobile-stack';
				}
				if ( ! $constrain_width ) {
					$outer_class_parts[] = 'dsgo-no-width-constraint';
				}

				// Default padding from block supports.
				$default_padding = 'padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)';

				// Inner div styles with gap.
				$inner_styles = array(
					'display:flex',
					'justify-content:' . esc_attr( $justify_content ),
					'flex-wrap:' . esc_attr( $flex_wrap ),
					'gap:var(--wp--preset--spacing--30)',
				);
				if ( $constrain_width ) {
					$max_width      = $content_width ? $content_width : 'var(--wp--style--global--content-size, 1140px)';
					$inner_styles[] = 'max-width:' . esc_attr( $max_width );
					$inner_styles[] = 'margin-left:auto';
					$inner_styles[] = 'margin-right:auto';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $outer_class_parts ) ) . '" style="' .
						esc_attr( implode( ';', array_merge( self::container_hover_styles( $attributes ), array( $default_padding ) ) ) ) .
						'"><div class="dsgo-flex__inner" style="' . esc_attr( implode( ';', $inner_styles ) ) . '">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/grid':
				$desktop_cols    = isset( $attributes['desktopColumns'] ) ? self::numeric_attribute( $attributes['desktopColumns'] ) : 3;
				$tablet_cols     = isset( $attributes['tabletColumns'] ) ? self::numeric_attribute( $attributes['tabletColumns'] ) : 2;
				$mobile_cols     = isset( $attributes['mobileColumns'] ) ? self::numeric_attribute( $attributes['mobileColumns'] ) : 1;
				$align_items     = isset( $attributes['alignItems'] ) ? $attributes['alignItems'] : 'stretch';
				$constrain_width = isset( $attributes['constrainWidth'] ) ? $attributes['constrainWidth'] : false;
				$content_width   = isset( $attributes['contentWidth'] ) ? $attributes['contentWidth'] : '';
				$align           = isset( $attributes['align'] ) ? $attributes['align'] : 'full';

				// Build outer classes (order: wp-block-*, alignX, dsgo-*).
				$outer_class_parts = array( 'wp-block-designsetgo-grid' );
				if ( 'full' === $align ) {
					$outer_class_parts[] = 'alignfull';
				} elseif ( 'wide' === $align ) {
					$outer_class_parts[] = 'alignwide';
				}
				$outer_class_parts[] = 'dsgo-grid';
				if ( self::has_overlay( $attributes ) ) {
					$outer_class_parts[] = 'dsgo-grid--has-overlay';
				}
				$outer_class_parts[] = 'dsgo-grid-cols-' . $desktop_cols;
				$outer_class_parts[] = 'dsgo-grid-cols-tablet-' . $tablet_cols;
				$outer_class_parts[] = 'dsgo-grid-cols-mobile-' . $mobile_cols;
				if ( ! $constrain_width ) {
					$outer_class_parts[] = 'dsgo-no-width-constraint';
				}

				// Default padding from block supports.
				$default_padding = 'padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)';

				// Inner div styles.
				$default_gap  = 'var(--wp--preset--spacing--50)';
				$inner_styles = array(
					'display:grid',
					'grid-template-columns:repeat(' . $desktop_cols . ', 1fr)',
					'align-items:' . esc_attr( $align_items ),
					'row-gap:' . $default_gap,
					'column-gap:' . $default_gap,
				);
				if ( $constrain_width ) {
					$max_width      = $content_width ? $content_width : 'var(--wp--style--global--content-size, 1140px)';
					$inner_styles[] = 'max-width:' . esc_attr( $max_width );
					$inner_styles[] = 'margin-left:auto';
					$inner_styles[] = 'margin-right:auto';
				}

				// save.js honours tagName; hardcoding <div> lost an author's
				// choice of <section>, <article> and so on.
				$grid_tag = ( isset( $attributes['tagName'] ) && '' !== $attributes['tagName'] )
					? preg_replace( '/[^a-z0-9]/i', '', (string) $attributes['tagName'] )
					: 'div';
				$grid_tag = '' !== $grid_tag ? $grid_tag : 'div';

				return array(
					'opening' => '<' . $grid_tag . ' class="' . esc_attr( implode( ' ', $outer_class_parts ) ) . '" style="' .
						esc_attr( implode( ';', array_merge( self::container_hover_styles( $attributes ), array( $default_padding ) ) ) ) .
						'"><div class="dsgo-grid__inner" style="' . esc_attr( implode( ';', $inner_styles ) ) . '">',
					'closing' => '</div></' . $grid_tag . '>',
				);

			case 'designsetgo/counter-group':
				// This block's own attribute names are columns/columnsTablet/
				// columnsMobile. Reading the Grid block's names meant the author's
				// column counts never reached the markup.
				$desktop_cols = isset( $attributes['columns'] ) ? self::numeric_attribute( $attributes['columns'] ) : 3;
				$tablet_cols  = isset( $attributes['columnsTablet'] ) ? self::numeric_attribute( $attributes['columnsTablet'] ) : 2;
				$mobile_cols  = isset( $attributes['columnsMobile'] ) ? self::numeric_attribute( $attributes['columnsMobile'] ) : 1;
				$gap          = isset( $attributes['gap'] ) ? intval( $attributes['gap'] ) : 32;
				$duration     = isset( $attributes['animationDuration'] ) ? floatval( $attributes['animationDuration'] ) : 2;
				$delay        = isset( $attributes['animationDelay'] ) ? floatval( $attributes['animationDelay'] ) : 0;
				$easing       = isset( $attributes['animationEasing'] ) ? $attributes['animationEasing'] : 'easeOutQuad';
				$use_grouping = isset( $attributes['useGrouping'] ) ? $attributes['useGrouping'] : true;
				$separator    = isset( $attributes['separator'] ) ? $attributes['separator'] : ',';
				$decimal      = isset( $attributes['decimal'] ) ? $attributes['decimal'] : '.';
				$align        = isset( $attributes['alignment'] ) ? $attributes['alignment'] : 'center';

				$outer_style = 'align-self:stretch;--dsgo-counter-columns-desktop:' . (string) $desktop_cols . ';--dsgo-counter-columns-tablet:' . (string) $tablet_cols . ';--dsgo-counter-columns-mobile:' . (string) $mobile_cols . ';--dsgo-counter-gap:' . (string) $gap . 'px';

				$data_attrs  = ' data-animation-duration="' . esc_attr( (string) $duration ) . '"';
				$data_attrs .= ' data-animation-delay="' . esc_attr( (string) $delay ) . '"';
				$data_attrs .= ' data-animation-easing="' . esc_attr( $easing ) . '"';
				$data_attrs .= ' data-use-grouping="' . ( $use_grouping ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-separator="' . esc_attr( $separator ) . '"';
				$data_attrs .= ' data-decimal="' . esc_attr( $decimal ) . '"';

				return array(
					'opening' => '<div class="wp-block-designsetgo-counter-group dsgo-counter-group" style="' . esc_attr( $outer_style ) . '"' . $data_attrs . '><div class="dsgo-counter-group__inner dsgo-counter-group__inner--align-' . esc_attr( $align ) . '">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/counter':
				$unique_id    = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : wp_unique_id( 'counter-' );
				$start_value  = isset( $attributes['startValue'] ) ? floatval( $attributes['startValue'] ) : 0;
				$end_value    = isset( $attributes['endValue'] ) ? floatval( $attributes['endValue'] ) : 100;
				$decimals     = isset( $attributes['decimals'] ) ? self::numeric_attribute( $attributes['decimals'] ) : 0;
				$prefix       = isset( $attributes['prefix'] ) ? $attributes['prefix'] : '';
				$suffix       = isset( $attributes['suffix'] ) ? $attributes['suffix'] : '';
				$label        = isset( $attributes['label'] ) ? $attributes['label'] : '';
				$duration     = isset( $attributes['duration'] ) ? floatval( $attributes['duration'] ) : 2;
				$delay        = isset( $attributes['delay'] ) ? floatval( $attributes['delay'] ) : 0;
				$easing       = isset( $attributes['easing'] ) ? $attributes['easing'] : 'easeOutQuad';
				$use_grouping = isset( $attributes['useGrouping'] ) ? $attributes['useGrouping'] : true;
				$separator    = isset( $attributes['separator'] ) ? $attributes['separator'] : ',';
				$decimal      = isset( $attributes['decimal'] ) ? $attributes['decimal'] : '.';

				$data_attrs  = ' data-start-value="' . esc_attr( (string) $start_value ) . '"';
				$data_attrs .= ' data-end-value="' . esc_attr( (string) $end_value ) . '"';
				$data_attrs .= ' data-decimals="' . esc_attr( (string) $decimals ) . '"';
				$data_attrs .= ' data-prefix="' . esc_attr( $prefix ) . '"';
				$data_attrs .= ' data-suffix="' . esc_attr( $suffix ) . '"';
				$data_attrs .= ' data-duration="' . esc_attr( (string) $duration ) . '"';
				$data_attrs .= ' data-delay="' . esc_attr( (string) $delay ) . '"';
				$data_attrs .= ' data-easing="' . esc_attr( $easing ) . '"';
				$data_attrs .= ' data-use-grouping="' . ( $use_grouping ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-separator="' . esc_attr( $separator ) . '"';
				$data_attrs .= ' data-decimal="' . esc_attr( $decimal ) . '"';

				$inner_html  = '<div class="dsgo-counter__content icon-top">';
				$inner_html .= '<div class="dsgo-counter__number">';
				$inner_html .= '<span class="dsgo-counter__value">' . esc_html( (string) $start_value ) . '</span>';
				$inner_html .= '</div></div>';
				if ( $label ) {
					$inner_html .= '<div class="dsgo-counter__label">' . esc_html( $label ) . '</div>';
				}

				return array(
					'opening' => '<div class="wp-block-designsetgo-counter dsgo-counter" id="' . esc_attr( $unique_id ) . '" style="text-align:center"' . $data_attrs . '>' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/flip-card':
				$flip_trigger   = isset( $attributes['flipTrigger'] ) ? $attributes['flipTrigger'] : 'hover';
				$flip_effect    = isset( $attributes['flipEffect'] ) ? $attributes['flipEffect'] : 'flip';
				$flip_direction = isset( $attributes['flipDirection'] ) ? $attributes['flipDirection'] : 'horizontal';
				$flip_duration  = isset( $attributes['flipDuration'] ) ? $attributes['flipDuration'] : '0.6s';

				$outer_class = 'wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--' . esc_attr( $flip_trigger ) . ' dsgo-flip-card--effect-' . esc_attr( $flip_effect ) . ' dsgo-flip-card--' . esc_attr( $flip_direction );
				// `width:100%` is no longer serialized — style.scss owns it (see
				// save.js). Emitting it here would produce markup save() never
				// generates, so the block would fail validation on first open.
				$outer_style = '--dsgo-flip-duration:' . esc_attr( $flip_duration );
				$data_attrs  = ' data-flip-trigger="' . esc_attr( $flip_trigger ) . '" data-flip-effect="' . esc_attr( $flip_effect ) . '" data-flip-direction="' . esc_attr( $flip_direction ) . '"';

				return array(
					'opening' => '<div class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $outer_style ) . '"' . $data_attrs . '><div class="dsgo-flip-card__container">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/flip-card-face':
				$side = isset( $attributes['side'] ) && 'back' === $attributes['side'] ? 'back' : 'front';
				return array(
					'opening' => '<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__' . esc_attr( $side ) . '">',
					'closing' => '</div>',
				);

			// Legacy — consolidated into designsetgo/flip-card-face in 2.0.51.
			// Kept so the inserter ability can still emit existing content
			// until it is transformed to the new block.
			case 'designsetgo/flip-card-front':
				return array(
					'opening' => '<div class="wp-block-designsetgo-flip-card-front dsgo-flip-card__face dsgo-flip-card__front">',
					'closing' => '</div>',
				);

			case 'designsetgo/flip-card-back':
				return array(
					'opening' => '<div class="wp-block-designsetgo-flip-card-back dsgo-flip-card__face dsgo-flip-card__back">',
					'closing' => '</div>',
				);

			case 'designsetgo/icon':
				// Dead branch: Icon is a dynamic block (render.php), so
				// is_dynamic_block() keeps this switch from ever being
				// reached for it (see convert_to_block_array() above) — the
				// block always serializes to a bare comment and is rendered
				// server-side. Kept in sync with the current markup anyway
				// (rather than deleted) in case that gate is ever revisited;
				// same treatment as the Pill case below.
				$icon_name    = isset( $attributes['icon'] ) ? $attributes['icon'] : ( isset( $attributes['iconName'] ) ? $attributes['iconName'] : 'star' );
				$icon_style   = isset( $attributes['iconStyle'] ) ? $attributes['iconStyle'] : 'filled';
				$stroke_width = isset( $attributes['strokeWidth'] ) ? $attributes['strokeWidth'] : '1.5';
				$icon_size    = isset( $attributes['iconSize'] ) ? self::numeric_attribute( $attributes['iconSize'] ) : ( isset( $attributes['size'] ) ? intval( $attributes['size'] ) : 48 );
				$aria_label   = isset( $attributes['ariaLabel'] ) ? $attributes['ariaLabel'] : ucwords( str_replace( '-', ' ', $icon_name ) );

				// `align` was removed when `justification` replaced it.
				$justification = isset( $attributes['justification'] )
					? $attributes['justification']
					: ( isset( $attributes['align'] ) ? $attributes['align'] : 'center' );
				if ( ! in_array( $justification, array( 'left', 'center', 'right' ), true ) ) {
					$justification = 'center';
				}

				$wrapper_style = 'width:' . $icon_size . 'px;height:' . $icon_size . 'px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit';

				$inner_html  = '<div class="dsgo-icon__wrapper dsgo-lazy-icon" style="' . esc_attr( $wrapper_style ) . '"';
				$inner_html .= ' data-icon-name="' . esc_attr( $icon_name ) . '"';
				$inner_html .= ' data-icon-style="' . esc_attr( $icon_style ) . '"';
				$inner_html .= ' data-icon-stroke-width="' . esc_attr( $stroke_width ) . '"';
				$inner_html .= ' role="img" aria-label="' . esc_attr( $aria_label ) . '"></div>';

				$wrapper_class = 'wp-block-designsetgo-icon dsgo-icon dsgo-justify dsgo-justify--' . $justification;

				return array(
					'opening' => '<div class="' . esc_attr( $wrapper_class ) . '">' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/accordion':
				$allow_multiple = isset( $attributes['allowMultipleOpen'] ) ? $attributes['allowMultipleOpen'] : false;
				$icon_style     = isset( $attributes['iconStyle'] ) ? $attributes['iconStyle'] : 'chevron';
				$icon_position  = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'right';
				$border_between = isset( $attributes['borderBetween'] ) ? $attributes['borderBetween'] : true;
				$item_gap       = isset( $attributes['itemGap'] ) ? $attributes['itemGap'] : '0.5rem';
				$open_bg        = isset( $attributes['openBackgroundColor'] ) ? $attributes['openBackgroundColor'] : '';
				$open_text      = isset( $attributes['openTextColor'] ) ? $attributes['openTextColor'] : '';
				$hover_bg       = isset( $attributes['hoverBackgroundColor'] ) ? $attributes['hoverBackgroundColor'] : $open_bg;
				$hover_text     = isset( $attributes['hoverTextColor'] ) ? $attributes['hoverTextColor'] : $open_text;
				$border_color   = isset( $attributes['borderBetweenColor'] ) ? $attributes['borderBetweenColor'] : '';

				// Build modifier classes (must match save.js).
				$accordion_classes = array( 'dsgo-accordion' );
				if ( $allow_multiple ) {
					$accordion_classes[] = 'dsgo-accordion--multiple';
				}
				if ( 'left' === $icon_position ) {
					$accordion_classes[] = 'dsgo-accordion--icon-left';
				} elseif ( 'right' === $icon_position ) {
					$accordion_classes[] = 'dsgo-accordion--icon-right';
				}
				if ( 'none' === $icon_style ) {
					$accordion_classes[] = 'dsgo-accordion--no-icon';
				}
				if ( $border_between ) {
					$accordion_classes[] = 'dsgo-accordion--border-between';
				}

				// Build CSS custom properties style (must match save.js).
				$style_parts = array(
					'--dsgo-accordion-open-bg:' . esc_attr( $open_bg ),
					'--dsgo-accordion-open-text:' . esc_attr( $open_text ),
					'--dsgo-accordion-hover-bg:' . esc_attr( $hover_bg ),
					'--dsgo-accordion-hover-text:' . esc_attr( $hover_text ),
					'--dsgo-accordion-gap:' . esc_attr( $item_gap ),
				);
				if ( $border_color ) {
					$style_parts[] = '--dsgo-accordion-border-color:' . esc_attr( $border_color );
				}
				$custom_style = implode( ';', $style_parts );

				$full_class = 'wp-block-designsetgo-accordion ' . implode( ' ', $accordion_classes );

				return array(
					'opening' => '<div class="' . esc_attr( $full_class ) . '" style="' . esc_attr( $custom_style ) . '" data-allow-multiple="' . ( $allow_multiple ? 'true' : 'false' ) . '" data-icon-style="' . esc_attr( $icon_style ) . '"><div class="dsgo-accordion__items">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/accordion-item':
				$title     = isset( $attributes['title'] ) ? $attributes['title'] : '';
				$is_open   = isset( $attributes['isOpen'] ) ? $attributes['isOpen'] : false;
				$unique_id = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : wp_unique_id( 'accordion-item-' );

				// Get icon style/position from context or defaults.
				$icon_style    = isset( $attributes['iconStyle'] ) ? $attributes['iconStyle'] : 'chevron';
				$icon_position = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'right';

				// Build item classes.
				$item_classes   = array( 'dsgo-accordion-item' );
				$item_classes[] = $is_open ? 'dsgo-accordion-item--open' : 'dsgo-accordion-item--closed';

				// Build trigger classes.
				$trigger_classes = array( 'dsgo-accordion-item__trigger' );
				if ( 'left' === $icon_position ) {
					$trigger_classes[] = 'dsgo-accordion-item__trigger--icon-left';
				} elseif ( 'right' === $icon_position ) {
					$trigger_classes[] = 'dsgo-accordion-item__trigger--icon-right';
				}

				// Generate icon SVG based on style.
				$icon_svg = '';
				if ( 'none' !== $icon_style ) {
					switch ( $icon_style ) {
						case 'plus-minus':
							if ( $is_open ) {
								$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4 8h8v1H4z"></path></svg>';
							} else {
								$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 4v8M4 8h8" stroke="currentColor" stroke-width="1" fill="none"></path></svg>';
							}
							break;
						case 'caret':
							$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M6 7l2 2 2-2z"></path></svg>';
							break;
						case 'chevron':
						default:
							$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg>';
							break;
					}
				}

				$header_id = esc_attr( $unique_id ) . '-header';
				$panel_id  = esc_attr( $unique_id ) . '-panel';

				// Build icon HTML.
				$icon_html = '';
				if ( $icon_svg ) {
					$icon_html = '<span class="dsgo-accordion-item__icon" aria-hidden="true">' . $icon_svg . '</span>';
				}

				// Build the full accordion item HTML structure.
				$opening  = '<div class="wp-block-designsetgo-accordion-item ' . esc_attr( implode( ' ', $item_classes ) ) . '" data-initially-open="' . ( $is_open ? 'true' : 'false' ) . '">';
				$opening .= '<div class="dsgo-accordion-item__header">';
				$opening .= '<button type="button" class="' . esc_attr( implode( ' ', $trigger_classes ) ) . '" aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" aria-controls="' . $panel_id . '" id="' . $header_id . '">';
				if ( 'left' === $icon_position ) {
					$opening .= $icon_html;
				}
				$opening .= '<span class="dsgo-accordion-item__title">' . esc_html( $title ) . '</span>';
				if ( 'right' === $icon_position ) {
					$opening .= $icon_html;
				}
				$opening .= '</button>';
				$opening .= '</div>';
				$opening .= '<div class="dsgo-accordion-item__panel" role="region" aria-labelledby="' . $header_id . '" id="' . $panel_id . '"' . ( $is_open ? '' : ' hidden' ) . '>';
				$opening .= '<div class="dsgo-accordion-item__content">';

				$closing = '</div></div></div>';

				return array(
					'opening' => $opening,
					'closing' => $closing,
				);

			case 'designsetgo/divider':
				$divider_style = isset( $attributes['dividerStyle'] ) ? $attributes['dividerStyle'] : 'solid';
				$width         = isset( $attributes['width'] ) ? $attributes['width'] : 100;
				$thickness     = isset( $attributes['thickness'] ) ? $attributes['thickness'] : 2;
				$icon_name     = isset( $attributes['iconName'] ) ? $attributes['iconName'] : '';

				$divider_class = 'wp-block-designsetgo-divider dsgo-divider dsgo-divider--' . esc_attr( $divider_style );

				$container_style = 'width:' . intval( $width ) . '%';
				$line_style      = 'height:' . intval( $thickness ) . 'px';

				if ( 'icon' === $divider_style ) {
					// Icon style with three elements.
					$inner_html  = '<div class="dsgo-divider__container" style="' . esc_attr( $container_style ) . '">';
					$inner_html .= '<div class="dsgo-divider__icon-wrapper">';
					$inner_html .= '<span class="dsgo-divider__line dsgo-divider__line--left" style="' . esc_attr( $line_style ) . '"></span>';
					$inner_html .= '<span class="dsgo-divider__icon dsgo-lazy-icon" data-icon-name="' . esc_attr( $icon_name ) . '"></span>';
					$inner_html .= '<span class="dsgo-divider__line dsgo-divider__line--right" style="' . esc_attr( $line_style ) . '"></span>';
					$inner_html .= '</div></div>';
				} else {
					// Standard divider.
					$inner_html  = '<div class="dsgo-divider__container" style="' . esc_attr( $container_style ) . '">';
					$inner_html .= '<div class="dsgo-divider__line" style="' . esc_attr( $line_style ) . '"></div>';
					$inner_html .= '</div>';
				}

				return array(
					'opening' => '<div class="' . esc_attr( $divider_class ) . '">' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/countdown-timer':
				$target_datetime    = isset( $attributes['targetDateTime'] ) ? $attributes['targetDateTime'] : '';
				$timezone           = isset( $attributes['timezone'] ) ? $attributes['timezone'] : '';
				$show_days          = isset( $attributes['showDays'] ) ? $attributes['showDays'] : true;
				$show_hours         = isset( $attributes['showHours'] ) ? $attributes['showHours'] : true;
				$show_minutes       = isset( $attributes['showMinutes'] ) ? $attributes['showMinutes'] : true;
				$show_seconds       = isset( $attributes['showSeconds'] ) ? $attributes['showSeconds'] : true;
				$layout             = isset( $attributes['layout'] ) ? $attributes['layout'] : 'boxed';
				$completion_action  = isset( $attributes['completionAction'] ) ? $attributes['completionAction'] : 'message';
				$completion_message = isset( $attributes['completionMessage'] ) ? $attributes['completionMessage'] : 'The countdown has ended!';
				$number_color       = isset( $attributes['numberColor'] ) ? $attributes['numberColor'] : '';
				$label_color        = isset( $attributes['labelColor'] ) ? $attributes['labelColor'] : '';
				$unit_bg_color      = isset( $attributes['unitBackgroundColor'] ) ? $attributes['unitBackgroundColor'] : '';
				$unit_border        = isset( $attributes['unitBorder'] ) ? $attributes['unitBorder'] : array();
				$unit_border_radius = isset( $attributes['unitBorderRadius'] ) ? self::numeric_attribute( $attributes['unitBorderRadius'] ) : 12;
				$unit_gap           = isset( $attributes['unitGap'] ) ? $attributes['unitGap'] : '1rem';
				$unit_padding       = isset( $attributes['unitPadding'] ) ? $attributes['unitPadding'] : '1.5rem';

				// Build unit style.
				$border_color = isset( $unit_border['color'] ) && $unit_border['color'] ? $unit_border['color'] : 'var(--wp--preset--color--accent-2, currentColor)';
				$border_width = isset( $unit_border['width'] ) ? $unit_border['width'] : '2px';
				$border_style = isset( $unit_border['style'] ) ? $unit_border['style'] : 'solid';

				$unit_style_parts = array(
					'background-color:' . ( $unit_bg_color ? esc_attr( $unit_bg_color ) : 'transparent' ),
					'border-color:' . esc_attr( $border_color ),
					'border-width:' . esc_attr( $border_width ),
					'border-style:' . esc_attr( $border_style ),
					'border-radius:' . $unit_border_radius . 'px',
					'padding:' . esc_attr( $unit_padding ),
				);
				$unit_style       = implode( ';', $unit_style_parts );

				$number_style = 'color:' . ( $number_color ? esc_attr( $number_color ) : 'var(--wp--preset--color--accent-2, currentColor)' );
				$label_style  = 'color:' . ( $label_color ? esc_attr( $label_color ) : 'currentColor' );

				// Build units HTML.
				$units = array();
				if ( $show_days ) {
					$units[] = array(
						'type'  => 'days',
						'label' => 'Days',
					);
				}
				if ( $show_hours ) {
					$units[] = array(
						'type'  => 'hours',
						'label' => 'Hours',
					);
				}
				if ( $show_minutes ) {
					$units[] = array(
						'type'  => 'minutes',
						'label' => 'Min',
					);
				}
				if ( $show_seconds ) {
					$units[] = array(
						'type'  => 'seconds',
						'label' => 'Sec',
					);
				}

				$units_html = '';
				foreach ( $units as $unit ) {
					$units_html .= '<div class="dsgo-countdown-timer__unit" data-unit-type="' . esc_attr( $unit['type'] ) . '" style="' . esc_attr( $unit_style ) . '">';
					$units_html .= '<div class="dsgo-countdown-timer__number" style="' . esc_attr( $number_style ) . '">00</div>';
					$units_html .= '<div class="dsgo-countdown-timer__label" style="' . esc_attr( $label_style ) . '">' . esc_html( $unit['label'] ) . '</div>';
					$units_html .= '</div>';
				}

				// Build data attributes.
				$data_attrs  = ' data-target-datetime="' . esc_attr( $target_datetime ) . '"';
				$data_attrs .= ' data-timezone="' . esc_attr( $timezone ) . '"';
				$data_attrs .= ' data-show-days="' . ( $show_days ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-show-hours="' . ( $show_hours ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-show-minutes="' . ( $show_minutes ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-show-seconds="' . ( $show_seconds ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-completion-action="' . esc_attr( $completion_action ) . '"';
				// completionMessage is sourced from the message div's text (below),
				// not a wrapper attribute — save.js no longer emits
				// data-completion-message, so emitting it here would produce markup
				// save() never generates and fail validation.

				$container_style = 'gap:' . esc_attr( $unit_gap );
				$outer_class     = 'wp-block-designsetgo-countdown-timer dsgo-countdown-timer dsgo-countdown-timer--' . esc_attr( $layout );

				$inner_html = '<div class="dsgo-countdown-timer__units">' . $units_html . '</div>';
				// No inline display:none — style.scss hides this by default (view.js
				// reveals it by setting an inline display:block, which wins either
				// way). save.js stopped serializing it, so emitting it here would
				// produce markup save() never generates and fail validation.
				$inner_html .= '<div class="dsgo-countdown-timer__completion-message">' . esc_html( $completion_message ) . '</div>';

				return array(
					'opening' => '<div class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $container_style ) . '"' . $data_attrs . '>' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/progress-bar':
				$percentage        = isset( $attributes['percentage'] ) ? self::numeric_attribute( $attributes['percentage'] ) : 75;
				$bar_color         = isset( $attributes['barColor'] ) ? $attributes['barColor'] : '#2563eb';
				$bar_bg_color      = isset( $attributes['barBackgroundColor'] ) ? $attributes['barBackgroundColor'] : '#e5e7eb';
				$height            = isset( $attributes['height'] ) ? $attributes['height'] : '20px';
				$border_radius     = isset( $attributes['borderRadius'] ) ? $attributes['borderRadius'] : '4px';
				$show_percentage   = isset( $attributes['showPercentage'] ) ? $attributes['showPercentage'] : true;
				$label_position    = isset( $attributes['labelPosition'] ) ? $attributes['labelPosition'] : 'top';
				$animate_on_scroll = isset( $attributes['animateOnScroll'] ) ? $attributes['animateOnScroll'] : true;
				$animation_dur     = isset( $attributes['animationDuration'] ) ? floatval( $attributes['animationDuration'] ) : 1.5;

				// Clamp percentage.
				$bar_width = min( max( $percentage, 0 ), 100 );

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-progress-bar', 'dsgo-progress-bar' );
				if ( $animate_on_scroll ) {
					$class_parts[] = 'dsgo-progress-bar--animate';
				}

				// Data attributes for animation.
				$data_attrs = '';
				if ( $animate_on_scroll ) {
					$data_attrs = ' data-percentage="' . esc_attr( (string) $bar_width ) . '" data-duration="' . esc_attr( (string) $animation_dur ) . '"';
				}

				// Label.
				$label_html = '';
				if ( $show_percentage && 'top' === $label_position ) {
					$label_html = '<div class="dsgo-progress-bar__label dsgo-progress-bar__label--top">' . esc_html( $bar_width . '%' ) . '</div>';
				}

				// Container styles.
				// save.js writes `backgroundColor: barTrackColor || undefined`, and
				// React omits an undefined style property entirely. Emitting
				// `background-color:` with an empty value produced a declaration
				// save() never writes, so an unstyled progress bar was invalid.
				$container_style = 'width:100%;height:' . esc_attr( $height ) .
					( '' !== $bar_bg_color ? ';background-color:' . esc_attr( $bar_bg_color ) : '' ) .
					';border-radius:' . esc_attr( $border_radius ) . ';overflow:hidden;position:relative';

				// Fill styles.
				$fill_width = $animate_on_scroll ? '0%' : $bar_width . '%';
				$fill_style = 'width:' . $fill_width . ';height:100%' .
					( '' !== $bar_color ? ';background-color:' . esc_attr( $bar_color ) : '' ) .
					';transition:width ' . esc_attr( (string) $animation_dur ) . 's ease-out;border-radius:' . esc_attr( $border_radius );

				$inner_html  = $label_html;
				$inner_html .= '<div class="dsgo-progress-bar__container" style="' . esc_attr( $container_style ) . '">';
				$inner_html .= '<div class="dsgo-progress-bar__fill" style="' . esc_attr( $fill_style ) . '"></div>';
				$inner_html .= '</div>';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $data_attrs . '>' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/pill':
				// Dead branch: Pill is a dynamic block (render.php), so
				// is_dynamic_block() keeps this switch from ever being
				// reached for it (see convert_to_block_array() above) — the
				// block always serializes to a bare comment and is rendered
				// server-side. Kept in sync with the current markup anyway
				// (rather than deleted) in case that gate is ever revisited;
				// `align` was removed when `justification` replaced it, and
				// the pre-dynamic `wp-block-designsetgo-pill align{value}
				// dsgo-pill has-small-font-size` shape below is stale.
				$content       = isset( $attributes['content'] ) ? $attributes['content'] : '';
				$justification = isset( $attributes['justification'] )
					? $attributes['justification']
					: ( isset( $attributes['align'] ) ? $attributes['align'] : 'center' );
				if ( ! in_array( $justification, array( 'left', 'center', 'right' ), true ) ) {
					$justification = 'center';
				}

				$class_parts = array( 'wp-block-designsetgo-pill', 'dsgo-pill', 'dsgo-justify', 'dsgo-justify--' . $justification );

				$inner_html = '<span class="dsgo-pill__content">' . wp_kses_post( $content ) . '</span>';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '">' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/map':
				$provider    = isset( $attributes['dsgoProvider'] ) ? $attributes['dsgoProvider'] : 'openstreetmap';
				$latitude    = isset( $attributes['dsgoLatitude'] ) ? floatval( $attributes['dsgoLatitude'] ) : 40.7128;
				$longitude   = isset( $attributes['dsgoLongitude'] ) ? floatval( $attributes['dsgoLongitude'] ) : -74.006;
				$zoom        = isset( $attributes['dsgoZoom'] ) ? self::numeric_attribute( $attributes['dsgoZoom'] ) : 13;
				$address     = isset( $attributes['dsgoAddress'] ) ? $attributes['dsgoAddress'] : '';
				$marker_icon = isset( $attributes['dsgoMarkerIcon'] ) ? $attributes['dsgoMarkerIcon'] : '📍';
				// Treat an unset OR explicitly-cleared ('') marker color as the
				// block default, mirroring render.php — the editor now stores ''
				// on clear, and the resolver short-circuits empty strings before
				// consulting its own fallback.
				$marker_color = ( isset( $attributes['dsgoMarkerColor'] ) && '' !== $attributes['dsgoMarkerColor'] )
					? $attributes['dsgoMarkerColor']
					: '#e74c3c';
				// Resolve theme palette presets (var:preset|color|{slug}) to a
				// concrete color; the marker is drawn by view.js, which cannot
				// inherit the page's CSS custom properties. Fall back to the block
				// default when the preset's slug is missing from the palette.
				$marker_color       = designsetgo_resolve_preset_color( $marker_color, '#e74c3c' );
				$height             = isset( $attributes['dsgoHeight'] ) ? $attributes['dsgoHeight'] : '400px';
				$aspect_ratio       = isset( $attributes['dsgoAspectRatio'] ) ? $attributes['dsgoAspectRatio'] : 'custom';
				$privacy_mode       = isset( $attributes['dsgoPrivacyMode'] ) ? $attributes['dsgoPrivacyMode'] : false;
				$has_privacy_notice = array_key_exists( 'dsgoPrivacyNotice', $attributes );
				$privacy_notice     = $attributes['dsgoPrivacyNotice'] ?? __( 'This map will load content from external services. Click to load and view the map.', 'designsetgo' );
				$map_style          = isset( $attributes['dsgoMapStyle'] ) ? $attributes['dsgoMapStyle'] : 'standard';

				// Clamp coordinates.
				$safe_lat  = max( -90, min( 90, $latitude ) );
				$safe_lng  = max( -180, min( 180, $longitude ) );
				$safe_zoom = max( 1, min( 20, $zoom ) );

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-map', 'dsgo-map' );
				if ( $privacy_mode ) {
					$class_parts[] = 'dsgo-map--privacy-mode';
				}
				if ( 'custom' !== $aspect_ratio ) {
					$class_parts[] = 'dsgo-map--aspect-' . str_replace( ':', '-', $aspect_ratio );
				}

				// Style.
				$style = '';
				if ( 'custom' === $aspect_ratio ) {
					$style = 'height:' . esc_attr( $height );
				}

				// Data attributes.
				$data_attrs  = ' data-dsgo-provider="' . esc_attr( $provider ) . '"';
				$data_attrs .= ' data-dsgo-lat="' . esc_attr( (string) $safe_lat ) . '"';
				$data_attrs .= ' data-dsgo-lng="' . esc_attr( (string) $safe_lng ) . '"';
				$data_attrs .= ' data-dsgo-zoom="' . esc_attr( (string) $safe_zoom ) . '"';
				$data_attrs .= ' data-dsgo-address="' . esc_attr( $address ) . '"';
				$data_attrs .= ' data-dsgo-marker-icon="' . esc_attr( $marker_icon ) . '"';
				$data_attrs .= ' data-dsgo-marker-color="' . esc_attr( $marker_color ) . '"';
				$data_attrs .= ' data-dsgo-privacy-mode="' . ( $privacy_mode ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-dsgo-map-style="' . esc_attr( $map_style ) . '"';

				// Inner HTML.
				$aria_label = $address
					? sprintf(
						/* translators: %s: The address being shown on the map */
						__( 'Map showing %s', 'designsetgo' ),
						$address
					)
					: __( 'Interactive map', 'designsetgo' );

				if ( $privacy_mode ) {
					$privacy_text = ( $has_privacy_notice && '' === $privacy_notice )
						? __( 'Click to load map', 'designsetgo' )
						: $privacy_notice;

					$inner_html  = '<div class="dsgo-map__privacy-overlay"><div class="dsgo-map__privacy-content">';
					$inner_html .= '<svg class="dsgo-map__privacy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
					$inner_html .= '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>';
					$inner_html .= '<circle cx="12" cy="10" r="3"></circle>';
					$inner_html .= '</svg>';
					$inner_html .= '<p class="dsgo-map__privacy-text">' . esc_html( $privacy_text ) . '</p>';
					$inner_html .= '<button class="dsgo-map__load-button" type="button" aria-label="' . esc_attr__( 'Load map. This will connect to external map services.', 'designsetgo' ) . '">';
					$inner_html .= esc_html__( 'Load Map', 'designsetgo' );
					$inner_html .= '</button></div></div>';
				} else {
					$inner_html = '<div class="dsgo-map__container" role="region" aria-label="' . esc_attr( $aria_label ) . '"></div>';
				}

				$style_attr = $style ? ' style="' . esc_attr( $style ) . '"' : '';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $style_attr . $data_attrs . '>' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/card':
				$layout_preset     = isset( $attributes['layoutPreset'] ) ? $attributes['layoutPreset'] : 'standard';
				$visual_style      = isset( $attributes['visualStyle'] ) ? $attributes['visualStyle'] : 'default';
				$title             = isset( $attributes['title'] ) ? $attributes['title'] : '';
				$subtitle          = isset( $attributes['subtitle'] ) ? $attributes['subtitle'] : '';
				$body_text         = isset( $attributes['bodyText'] ) ? $attributes['bodyText'] : '';
				$show_title        = isset( $attributes['showTitle'] ) ? $attributes['showTitle'] : true;
				$show_subtitle     = isset( $attributes['showSubtitle'] ) ? $attributes['showSubtitle'] : true;
				$show_body         = isset( $attributes['showBody'] ) ? $attributes['showBody'] : true;
				$show_cta          = isset( $attributes['showCta'] ) ? $attributes['showCta'] : true;
				$content_alignment = isset( $attributes['contentAlignment'] ) ? $attributes['contentAlignment'] : 'left';

				$card_align  = self::align_class( $block_name, $attributes );
				$outer_class = 'wp-block-designsetgo-card' . ( '' !== $card_align ? ' ' . $card_align : '' ) .
					' dsgo-card dsgo-card--' . esc_attr( $layout_preset ) . ' dsgo-card--style-' . esc_attr( $visual_style );

				// Build content HTML.
				$content_class = 'dsgo-card__content ';
				if ( 'background' === $layout_preset ) {
					$content_class .= 'dsgo-card__content--' . esc_attr( $content_alignment );
				}

				// title/subtitle/bodyText are DOM-sourced, so the element must stay
				// in the markup whenever the text is non-empty (matching save.js) —
				// a hidden field carries the `--hidden` modifier instead of being
				// omitted, otherwise the sourced text would be silently lost.
				$content_html = '';
				if ( $title ) {
					$title_class   = 'dsgo-card__title' . ( $show_title ? '' : ' dsgo-card__title--hidden' );
					$content_html .= '<h3 class="' . esc_attr( $title_class ) . '">' . wp_kses_post( $title ) . '</h3>';
				}
				if ( $subtitle ) {
					$subtitle_class = 'dsgo-card__subtitle' . ( $show_subtitle ? '' : ' dsgo-card__subtitle--hidden' );
					$content_html  .= '<p class="' . esc_attr( $subtitle_class ) . '">' . wp_kses_post( $subtitle ) . '</p>';
				}
				if ( $body_text ) {
					$body_class    = 'dsgo-card__body' . ( $show_body ? '' : ' dsgo-card__body--hidden' );
					$content_html .= '<p class="' . esc_attr( $body_class ) . '">' . wp_kses_post( $body_text ) . '</p>';
				}

				// CTA area for inner blocks.
				$cta_opening = '';
				$cta_closing = '';
				if ( $show_cta ) {
					$cta_opening = '<div class="dsgo-card__cta">';
					$cta_closing = '</div>';
				}

				return array(
					'opening' => '<div class="' . esc_attr( $outer_class ) . '"><div class="dsgo-card__inner"><div class="' . esc_attr( $content_class ) . '">' . $content_html . $cta_opening,
					'closing' => $cta_closing . '</div></div></div>',
				);

			case 'designsetgo/icon-list':
				$layout    = isset( $attributes['layout'] ) ? $attributes['layout'] : 'vertical';
				$gap       = isset( $attributes['gap'] ) ? $attributes['gap'] : '24px';
				$columns   = isset( $attributes['columns'] ) ? self::numeric_attribute( $attributes['columns'] ) : 2;
				$alignment = isset( $attributes['alignment'] ) ? $attributes['alignment'] : 'left';

				// Calculate alignment values.
				$align_items     = '';
				$justify_content = '';
				$flex_direction  = '';

				if ( 'vertical' === $layout ) {
					$flex_direction = 'column';
					if ( 'center' === $alignment ) {
						$align_items = 'center';
					} elseif ( 'right' === $alignment ) {
						$align_items = 'flex-end';
					} else {
						$align_items = 'flex-start';
					}
				} elseif ( 'horizontal' === $layout ) {
					$flex_direction = 'row';
					if ( 'center' === $alignment ) {
						$justify_content = 'center';
					} elseif ( 'right' === $alignment ) {
						$justify_content = 'flex-end';
					} else {
						$justify_content = 'flex-start';
					}
				}

				// Build container styles.
				$container_style_parts = array();
				if ( 'grid' === $layout ) {
					$container_style_parts[] = 'display:grid';
					$container_style_parts[] = 'grid-template-columns:repeat(' . $columns . ', 1fr)';
				} else {
					$container_style_parts[] = 'display:flex';
					$container_style_parts[] = 'flex-direction:' . $flex_direction;
				}
				$container_style_parts[] = 'gap:' . esc_attr( $gap );
				if ( $align_items ) {
					$container_style_parts[] = 'align-items:' . $align_items;
				}
				if ( $justify_content ) {
					$container_style_parts[] = 'justify-content:' . $justify_content;
				}
				$container_style_parts[] = 'width:100%';
				$container_style         = implode( ';', $container_style_parts );

				$outer_class = 'wp-block-designsetgo-icon-list dsgo-icon-list dsgo-icon-list--' . esc_attr( $layout );

				return array(
					'opening' => '<div class="' . esc_attr( $outer_class ) . '" style="width:100%"><div class="dsgo-icon-list__items" style="' . esc_attr( $container_style ) . '">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/icon-list-item':
				$icon     = isset( $attributes['icon'] ) ? $attributes['icon'] : 'star';
				$link_url = isset( $attributes['linkUrl'] ) ? $attributes['linkUrl'] : '';
				// Same as icon-button below: block.json defaults linkTarget to
				// `_self`, so save() always emits target alongside href. Defaulting
				// to '' here suppressed it and linked items failed validation.
				$link_target = isset( $attributes['linkTarget'] ) && '' !== $attributes['linkTarget']
					? $attributes['linkTarget']
					: '_self';
				$link_rel    = isset( $attributes['linkRel'] ) ? $attributes['linkRel'] : '';

				// Everything below must reproduce save.js's output for an
				// EMPTY block context, because that is the only output that
				// exists in stored markup: WordPress does not pass block context
				// to save(), so the parent Icon List's iconSize / iconPosition /
				// colours resolve to their defaults there and the item inherits
				// them from CSS at render time instead.
				//
				// Concretely that means: icon-left, no inline icon size (the
				// `--inherit-size` class hands sizing to the theme token), no
				// item gap, and the icon box's layout coming from style.scss.
				// Reading iconSize / iconPosition off $attributes here — as this
				// branch used to — produced markup save() never generates, so an
				// AI-inserted item failed validation the first time it was opened.
				// row / flex-start / left are save.js's empty-context values, not
				// choices made here — hence the literals rather than variables.
				$item_style = 'display:flex;flex-direction:row;align-items:flex-start';

				// Content gap: written inline only for an explicit author value,
				// mirroring save.js (the attribute has no default).
				$content_style = 'text-align:left;display:flex;flex-direction:column';
				if ( isset( $attributes['contentGap'] ) && is_numeric( $attributes['contentGap'] ) ) {
					$content_style .= ';gap:' . self::numeric_attribute( $attributes['contentGap'] ) . 'px';
				}

				$outer_class = 'wp-block-designsetgo-icon-list-item dsgo-icon-list-item dsgo-icon-list-item--icon-left';

				// No inline style on the icon box: layout lives in style.scss and
				// size resolves from --dsgo-icon-list-size via the inherit-size class.
				$icon_html = '<div class="dsgo-icon-list-item__icon dsgo-lazy-icon dsgo-icon-list-item__icon--inherit-size" data-icon-name="' . esc_attr( $icon ) . '"></div>';

				// Build element (div or link).
				$tag         = $link_url ? 'a' : 'div';
				$extra_attrs = '';
				if ( $link_url ) {
					$extra_attrs .= ' href="' . esc_url( $link_url ) . '"';
					if ( $link_target ) {
						$extra_attrs .= ' target="' . esc_attr( $link_target ) . '"';
					}
					if ( $link_rel ) {
						$extra_attrs .= ' rel="' . esc_attr( $link_rel ) . '"';
					}
				}

				return array(
					'opening' => '<' . $tag . ' class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $item_style ) . '"' . $extra_attrs . '>' . $icon_html . '<div class="dsgo-icon-list-item__content" style="' . esc_attr( $content_style ) . '">',
					'closing' => '</div></' . $tag . '>',
				);

			case 'designsetgo/icon-button':
				$text = isset( $attributes['text'] ) ? $attributes['text'] : '';
				$url  = isset( $attributes['url'] ) ? $attributes['url'] : '';
				// `_self`, not '', is block.json's default for linkTarget — so a
				// parsed block always has one and save() always emits
				// target="_self" alongside href. Defaulting to '' here suppressed
				// the attribute and every AI-inserted LINKED icon button failed
				// validation on first open.
				$link_target    = isset( $attributes['linkTarget'] ) && '' !== $attributes['linkTarget']
					? $attributes['linkTarget']
					: '_self';
				$rel            = isset( $attributes['rel'] ) ? $attributes['rel'] : '';
				$icon           = isset( $attributes['icon'] ) ? $attributes['icon'] : 'lightbulb';
				$icon_position  = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'start';
				$icon_size      = isset( $attributes['iconSize'] ) ? self::numeric_attribute( $attributes['iconSize'] ) : 20;
				$icon_gap       = isset( $attributes['iconGap'] ) ? $attributes['iconGap'] : '';
				$hover_anim     = isset( $attributes['hoverAnimation'] ) ? $attributes['hoverAnimation'] : 'none';
				$modal_close_id = isset( $attributes['modalCloseId'] ) ? $attributes['modalCloseId'] : '';

				// save.js omits data-icon-style / data-icon-stroke-width unless
				// the author sets them, so mirror that here. Both are validated
				// against block.json rather than passed through: callers of this
				// Ability are AI agents, so an out-of-enum iconStyle would emit a
				// data-icon-style the frontend injector doesn't understand, and a
				// non-scalar strokeWidth would stringify to "Array" (and warn).
				$icon_style_attr = '';
				if ( isset( $attributes['iconStyle'] ) && in_array( $attributes['iconStyle'], array( 'filled', 'outlined' ), true ) ) {
					$icon_style_attr = $attributes['iconStyle'];
				}
				$stroke_width = ( isset( $attributes['strokeWidth'] ) && is_numeric( $attributes['strokeWidth'] ) )
					? (float) $attributes['strokeWidth']
					: 1.5;

				// Read the current `justification`/`fullWidth` attributes; fall
				// back to the legacy `align` for callers that still pass it.
				$justification = isset( $attributes['justification'] )
					? $attributes['justification']
					: ( isset( $attributes['align'] ) ? $attributes['align'] : 'left' );
				if ( ! in_array( $justification, array( 'left', 'center', 'right' ), true ) ) {
					$justification = 'left';
				}
				$full_width = ! empty( $attributes['fullWidth'] ) || ( isset( $attributes['align'] ) && 'full' === $attributes['align'] );

				$has_icon = 'none' !== $icon_position && $icon;

				// Build the button's own classes (matches the current save.js
				// marker-class scheme: gap is themed via `--has-icon`, not baked
				// inline, unless the author sets an explicit iconGap).
				$class_parts = array( 'dsgo-icon-button', 'wp-block-button', 'wp-block-button__link', 'wp-element-button' );
				if ( $has_icon ) {
					$class_parts[] = 'dsgo-icon-button--has-icon';
				}
				if ( $full_width ) {
					$class_parts[] = 'dsgo-icon-button--full-width';
				}
				if ( 'end' === $icon_position ) {
					$class_parts[] = 'dsgo-icon-button--icon-end';
				}
				if ( $hover_anim && 'none' !== $hover_anim ) {
					$class_parts[] = 'dsgo-icon-button--' . $hover_anim;
				}

				// Layout (display/width/flex-direction) lives in style.scss now,
				// not inline — only an explicit author gap is written inline.
				$style_parts = array();
				if ( $has_icon && '' !== $icon_gap ) {
					$style_parts[] = 'gap:' . esc_attr( $icon_gap );
				}

				// Colour, typography, border and shadow are skip-serialized on
				// the block root and re-applied to the button by save.js.
				$routed      = self::get_routed_visual_attributes( $attributes );
				$class_parts = array_merge( $class_parts, $routed['classes'] );
				$style_parts = array_merge(
					$style_parts,
					$routed['styles'],
					// Padding is skip-serialized on the root and re-applied here.
					self::routed_padding_styles( $attributes, true )
				);

				$button_style = implode( ';', $style_parts );

				// Icon HTML. Must match save.js: the icon span's layout
				// (display/align-items/justify-content/flex-shrink) lives in
				// style.scss, and width/height + data-icon-size are written ONLY
				// when the caller sets an explicit numeric iconSize — otherwise
				// the theme size token owns it. Emitting them unconditionally
				// (as this did) produced markup save() would never generate, so
				// the block validator flagged AI-inserted buttons as invalid.
				$icon_html = '';
				if ( $has_icon ) {
					$has_explicit_size = isset( $attributes['iconSize'] ) && is_numeric( $attributes['iconSize'] );
					$icon_attrs        = '';
					if ( $has_explicit_size ) {
						$icon_attrs .= ' style="' . esc_attr( 'width:' . $icon_size . 'px;height:' . $icon_size . 'px' ) . '"';
					}
					$icon_attrs .= ' data-icon-name="' . esc_attr( $icon ) . '"';
					if ( $has_explicit_size ) {
						$icon_attrs .= ' data-icon-size="' . esc_attr( (string) $icon_size ) . '"';
					}
					if ( $icon_style_attr ) {
						$icon_attrs .= ' data-icon-style="' . esc_attr( $icon_style_attr ) . '"';
					}
					if ( 'outlined' === $icon_style_attr ) {
						$icon_attrs .= ' data-icon-stroke-width="' . esc_attr( (string) $stroke_width ) . '"';
					}
					$icon_html = '<span class="dsgo-icon-button__icon dsgo-lazy-icon"' . $icon_attrs . '></span>';
				}

				// Text HTML.
				$text_html = '<span class="dsgo-icon-button__text">' . wp_kses_post( $text ) . '</span>';

				// Build element (button or link).
				$tag = $url ? 'a' : 'button';

				// Additional attributes.
				$extra_attrs = '';
				if ( $url ) {
					$extra_attrs .= ' href="' . esc_url( $url ) . '"';
					if ( $link_target ) {
						$extra_attrs .= ' target="' . esc_attr( $link_target ) . '"';
					}
					$rel_value = '_blank' === $link_target ? ( $rel ? $rel : 'noopener noreferrer' ) : $rel;
					if ( $rel_value ) {
						$extra_attrs .= ' rel="' . esc_attr( $rel_value ) . '"';
					}
				} else {
					$extra_attrs .= ' type="button"';
				}
				if ( $modal_close_id ) {
					$extra_attrs .= ' data-dsgo-modal-close="' . esc_attr( $modal_close_id ) . '"';
				}

				$inner_html = $icon_html . $text_html;

				// The block root is a block-level justification wrapper — core's
				// constrained layout caps IT at the content column — with the
				// button shrink-wrapped inside it (see save.js). Matches
				// `getJustificationClass()` (src/utils/justification.js).
				$wrapper_class     = 'wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--' . $justification;
				$button_style_attr = '' !== $button_style ? ' style="' . esc_attr( $button_style ) . '"' : '';

				return array(
					'opening' => '<div class="' . esc_attr( $wrapper_class ) . '"><' . $tag . ' class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $button_style_attr . $extra_attrs . '>' . $inner_html,
					'closing' => '</' . $tag . '></div>',
				);

			case 'designsetgo/modal':
				$modal_id                = isset( $attributes['modalId'] ) ? $attributes['modalId'] : 'dsgo-modal-' . wp_generate_uuid4();
				$animation_type          = isset( $attributes['animationType'] ) ? $attributes['animationType'] : 'fade';
				$animation_duration      = isset( $attributes['animationDuration'] ) ? self::numeric_attribute( $attributes['animationDuration'] ) : 300;
				$close_on_backdrop       = isset( $attributes['closeOnBackdrop'] ) ? $attributes['closeOnBackdrop'] : true;
				$close_on_esc            = isset( $attributes['closeOnEsc'] ) ? $attributes['closeOnEsc'] : true;
				$disable_body_scroll     = isset( $attributes['disableBodyScroll'] ) ? $attributes['disableBodyScroll'] : true;
				$allow_hash_trigger      = isset( $attributes['allowHashTrigger'] ) ? $attributes['allowHashTrigger'] : true;
				$update_url_on_open      = isset( $attributes['updateUrlOnOpen'] ) ? $attributes['updateUrlOnOpen'] : false;
				$auto_trigger_type       = isset( $attributes['autoTriggerType'] ) ? $attributes['autoTriggerType'] : 'none';
				$auto_trigger_delay      = isset( $attributes['autoTriggerDelay'] ) ? self::numeric_attribute( $attributes['autoTriggerDelay'] ) : 0;
				$auto_trigger_frequency  = isset( $attributes['autoTriggerFrequency'] ) ? $attributes['autoTriggerFrequency'] : 'always';
				$cookie_duration         = isset( $attributes['cookieDuration'] ) ? self::numeric_attribute( $attributes['cookieDuration'] ) : 7;
				$exit_intent_sensitivity = isset( $attributes['exitIntentSensitivity'] ) ? $attributes['exitIntentSensitivity'] : 'medium';
				$exit_intent_min_time    = isset( $attributes['exitIntentMinTime'] ) ? self::numeric_attribute( $attributes['exitIntentMinTime'] ) : 5;
				$exit_intent_exclude_mob = isset( $attributes['exitIntentExcludeMobile'] ) ? $attributes['exitIntentExcludeMobile'] : true;
				$scroll_depth            = isset( $attributes['scrollDepth'] ) ? self::numeric_attribute( $attributes['scrollDepth'] ) : 50;
				$scroll_direction        = isset( $attributes['scrollDirection'] ) ? $attributes['scrollDirection'] : 'down';
				$time_on_page            = isset( $attributes['timeOnPage'] ) ? self::numeric_attribute( $attributes['timeOnPage'] ) : 30;
				$gallery_group_id        = isset( $attributes['galleryGroupId'] ) ? $attributes['galleryGroupId'] : '';
				$gallery_index           = isset( $attributes['galleryIndex'] ) ? self::numeric_attribute( $attributes['galleryIndex'] ) : 0;
				$show_gallery_nav        = isset( $attributes['showGalleryNavigation'] ) ? $attributes['showGalleryNavigation'] : true;
				$nav_style               = isset( $attributes['navigationStyle'] ) ? $attributes['navigationStyle'] : 'arrows';
				$nav_position            = isset( $attributes['navigationPosition'] ) ? $attributes['navigationPosition'] : 'sides';
				$width                   = isset( $attributes['width'] ) ? $attributes['width'] : '600px';
				$max_width               = isset( $attributes['maxWidth'] ) ? $attributes['maxWidth'] : '90vw';
				$display_mode            = isset( $attributes['displayMode'] ) ? $attributes['displayMode'] : 'dialog';
				$panel_edge              = isset( $attributes['panelEdge'] ) ? $attributes['panelEdge'] : 'right';
				// Mirror save.js: clamp to a known edge, or the emitted class
				// matches no CSS rule and the panel floats mid-viewport.
				if ( ! in_array( $panel_edge, array( 'left', 'right', 'top', 'bottom' ), true ) ) {
					$panel_edge = 'right';
				}
				$panel_size = isset( $attributes['panelSize'] ) ? (string) $attributes['panelSize'] : '24rem';
				// Mirror save.js: allow-list a single plain CSS length. This is
				// interpolated into `--dsgo-panel-size:<value>`, and esc_attr()
				// stops an attribute break-out but not a `;` that appends
				// further declarations to the modal root.
				if ( ! preg_match( '/^(0|\d+(\.\d+)?(px|rem|em|%|vw|vh|vmin|vmax|ch|ex|pt|pc|cm|mm|in))$/', $panel_size ) ) {
					$panel_size = '24rem';
				}
				$is_panel              = 'panel' === $display_mode;
				$overlay_color         = isset( $attributes['overlayColor'] ) ? trim( (string) $attributes['overlayColor'] ) : '';
				$overlay_opacity       = isset( $attributes['overlayOpacity'] ) ? floatval( $attributes['overlayOpacity'] ) : 80;
				$overlay_blur          = isset( $attributes['overlayBlur'] ) ? self::numeric_attribute( $attributes['overlayBlur'] ) : 0;
				$show_close_button     = isset( $attributes['showCloseButton'] ) ? $attributes['showCloseButton'] : true;
				$close_button_position = isset( $attributes['closeButtonPosition'] ) ? $attributes['closeButtonPosition'] : 'inside-top-right';
				$close_button_size     = isset( $attributes['closeButtonSize'] ) ? self::numeric_attribute( $attributes['closeButtonSize'] ) : 24;

				// Build data attributes.
				$data_attrs  = ' data-dsgo-modal="true"';
				$data_attrs .= ' data-modal-id="' . esc_attr( $modal_id ) . '"';
				$data_attrs .= ' data-animation-type="' . esc_attr( $animation_type ) . '"';
				$data_attrs .= ' data-animation-duration="' . esc_attr( (string) $animation_duration ) . '"';
				$data_attrs .= ' data-close-on-backdrop="' . ( $close_on_backdrop ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-close-on-esc="' . ( $close_on_esc ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-disable-body-scroll="' . ( $disable_body_scroll ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-allow-hash-trigger="' . ( $allow_hash_trigger ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-update-url-on-open="' . ( $update_url_on_open ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-auto-trigger-type="' . esc_attr( $auto_trigger_type ) . '"';
				$data_attrs .= ' data-auto-trigger-delay="' . esc_attr( (string) $auto_trigger_delay ) . '"';
				$data_attrs .= ' data-auto-trigger-frequency="' . esc_attr( $auto_trigger_frequency ) . '"';
				$data_attrs .= ' data-cookie-duration="' . esc_attr( (string) $cookie_duration ) . '"';
				$data_attrs .= ' data-exit-intent-sensitivity="' . esc_attr( (string) $exit_intent_sensitivity ) . '"';
				$data_attrs .= ' data-exit-intent-min-time="' . esc_attr( (string) $exit_intent_min_time ) . '"';
				$data_attrs .= ' data-exit-intent-exclude-mobile="' . ( $exit_intent_exclude_mob ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-scroll-depth="' . esc_attr( (string) $scroll_depth ) . '"';
				$data_attrs .= ' data-scroll-direction="' . esc_attr( $scroll_direction ) . '"';
				$data_attrs .= ' data-time-on-page="' . esc_attr( (string) $time_on_page ) . '"';
				$data_attrs .= ' data-gallery-group-id="' . esc_attr( $gallery_group_id ) . '"';
				$data_attrs .= ' data-gallery-index="' . esc_attr( (string) $gallery_index ) . '"';
				$data_attrs .= ' data-show-gallery-navigation="' . ( $show_gallery_nav ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-navigation-style="' . esc_attr( $nav_style ) . '"';
				$data_attrs .= ' data-navigation-position="' . esc_attr( $nav_position ) . '"';

				// Overlay styles. save.js writes background-color ONLY when the
				// author set overlayColor explicitly (hasExplicitString) — left
				// unset, the stylesheet default owns the scrim
				// (--wp--custom--designsetgo--modal--overlay-color → #000) — so
				// mirror that here, and the property order (background-color,
				// opacity, backdrop-filter), or the block fails validation on
				// first edit.
				$overlay_style = '';
				if ( '' !== $overlay_color ) {
					$overlay_style .= 'background-color:' . esc_attr( self::convert_color_value_to_css_var( $overlay_color ) ) . ';';
				}
				$overlay_style .= 'opacity:' . ( $overlay_opacity / 100 );
				if ( $overlay_blur > 0 ) {
					$overlay_style .= ';backdrop-filter:blur(' . $overlay_blur . 'px)';
				}

				// Content styles. In panel mode save.js passes no dimensions to
				// transferStylesToContent(), because the panel is sized by
				// panelSize on the dialog — so no width/max-width is written.
				$content_style = 'border-style:none;border-width:0px';
				if ( ! $is_panel ) {
					$content_style .= ';width:' . esc_attr( $width ) . ';max-width:' . esc_attr( $max_width );
				}

				// Close button HTML.
				$close_button_html = '';
				if ( $show_close_button ) {
					$close_button_style = 'width:' . $close_button_size . 'px;height:' . $close_button_size . 'px';
					$close_button_html  = '<button class="dsgo-modal__close dsgo-modal__close--' . esc_attr( $close_button_position ) . '" style="' . esc_attr( $close_button_style ) . '" type="button" aria-label="Close modal">';
					$close_button_html .= '<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">';
					$close_button_html .= '<path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>';
					$close_button_html .= '</svg></button>';
				}

				$close_button_is_inside = strpos( $close_button_position, 'inside-' ) === 0;

				// Off-canvas panel mode. save() appends these to the root class
				// list and writes --dsgo-panel-size as a style AFTER the class
				// attribute; mirror both exactly or the block fails validation
				// on first edit.
				$outer_class = 'wp-block-designsetgo-modal dsgo-modal';
				$panel_style = '';
				if ( $is_panel ) {
					$outer_class .= ' dsgo-modal--panel dsgo-modal--panel-' . $panel_edge;
					$panel_style  = ' style="' . esc_attr( '--dsgo-panel-size:' . $panel_size ) . '"';
				}

				$inner_html  = '<div class="dsgo-modal__backdrop" style="' . esc_attr( $overlay_style ) . '" aria-hidden="true"></div>';
				$inner_html .= '<div class="dsgo-modal__dialog">';
				if ( ! $close_button_is_inside ) {
					$inner_html .= $close_button_html;
				}
				// save.js: modalLabel?.trim() || __( 'Modal' ).
				$modal_label = isset( $attributes['modalLabel'] ) && '' !== trim( (string) $attributes['modalLabel'] )
					? trim( (string) $attributes['modalLabel'] )
					: __( 'Modal', 'designsetgo' );

				$inner_html .= '<div class="dsgo-modal__content" style="' . esc_attr( $content_style ) . '">';

				$closing_html = '';
				if ( $close_button_is_inside ) {
					$closing_html .= $close_button_html;
				}
				$closing_html .= '</div></div></div>';

				// save.js omits the id attribute while modalId is blank
				// (React would render id=""), so mirror that here too.
				$id_attr = '' !== $modal_id ? ' id="' . esc_attr( $modal_id ) . '"' : '';

				return array(
					'opening' => '<div' . $id_attr . ' role="dialog" aria-modal="true" aria-label="' . esc_attr( $modal_label ) . '" aria-hidden="true"' . $data_attrs . ' class="' . esc_attr( $outer_class ) . '"' . $panel_style . '>' . $inner_html,
					'closing' => $closing_html,
				);

			case 'designsetgo/modal-trigger':
				$target_modal_id = isset( $attributes['targetModalId'] ) ? $attributes['targetModalId'] : '';
				$text            = isset( $attributes['text'] ) ? $attributes['text'] : 'Open Modal';
				$button_style    = isset( $attributes['buttonStyle'] ) ? $attributes['buttonStyle'] : 'fill';
				$icon            = isset( $attributes['icon'] ) ? $attributes['icon'] : '';
				$icon_position   = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'none';
				$icon_gap        = isset( $attributes['iconGap'] ) ? $attributes['iconGap'] : '8px';
				$icon_style      = isset( $attributes['iconStyle'] ) ? $attributes['iconStyle'] : '';
				$stroke_width    = isset( $attributes['strokeWidth'] ) ? $attributes['strokeWidth'] : 1.5;

				// Read the current `justification`/`fullWidth` attributes; fall
				// back to the legacy `align` for callers that still pass it
				// (mirrors the icon-button case above).
				$justification = isset( $attributes['justification'] )
					? $attributes['justification']
					: ( isset( $attributes['align'] ) ? $attributes['align'] : 'left' );
				if ( ! in_array( $justification, array( 'left', 'center', 'right' ), true ) ) {
					$justification = 'left';
				}
				$full_width = ! empty( $attributes['fullWidth'] ) || ( isset( $attributes['align'] ) && 'full' === $attributes['align'] );

				$has_icon = 'none' !== $icon_position && $icon;

				// Button classes — must match save.js's clsx() list exactly, or
				// the block validator flags AI-inserted triggers as invalid.
				$class_parts = array(
					'dsgo-modal-trigger',
					'dsgo-modal-trigger--' . $button_style,
					'wp-block-button',
					'wp-block-button__link',
					'wp-element-button',
				);
				if ( $full_width ) {
					$class_parts[] = 'dsgo-modal-trigger--full-width';
				}
				if ( 'end' === $icon_position ) {
					$class_parts[] = 'dsgo-modal-trigger--icon-end';
				}

				// Colour, typography, border and shadow are skip-serialized on the
				// block root and re-applied to the trigger by save.js, exactly as
				// Icon Button does. None of them were emitted here, so a trigger
				// given any colour stored markup save() would not reproduce.
				$trigger_routed = self::get_routed_visual_attributes( $attributes );
				$class_parts    = array_merge( $class_parts, $trigger_routed['classes'] );

				// save.js writes the gap inline whenever there is an icon and an
				// iconGap (which defaults to 8px); layout lives in style.scss.
				$trigger_styles = $trigger_routed['styles'];
				if ( $has_icon && '' !== $icon_gap ) {
					$trigger_styles[] = 'gap:' . $icon_gap;
				}
				// Padding is skip-serialized on the root and re-applied here.
				// Unlike Icon Button, save.js writes the value through unchanged.
				$trigger_styles = array_merge( $trigger_styles, self::routed_padding_styles( $attributes, false ) );
				$button_style_attr = empty( $trigger_styles )
					? ''
					: ' style="' . esc_attr( implode( ';', $trigger_styles ) ) . '"';

				// Icon span — size is only baked inline when the caller sets an
				// explicit numeric iconSize, so the theme token owns it otherwise.
				$icon_html = '';
				if ( $has_icon ) {
					$icon_attrs = '';
					if ( isset( $attributes['iconSize'] ) && is_numeric( $attributes['iconSize'] ) ) {
						$size        = self::numeric_attribute( $attributes['iconSize'] );
						$icon_attrs .= ' style="' . esc_attr( 'width:' . $size . 'px;height:' . $size . 'px' ) . '"';
					}
					$icon_attrs .= ' data-icon-name="' . esc_attr( $icon ) . '"';
					if ( $icon_style ) {
						$icon_attrs .= ' data-icon-style="' . esc_attr( $icon_style ) . '"';
					}
					if ( 'outlined' === $icon_style ) {
						$icon_attrs .= ' data-icon-stroke-width="' . esc_attr( (string) $stroke_width ) . '"';
					}
					$icon_html = '<span class="dsgo-modal-trigger__icon dsgo-lazy-icon"' . $icon_attrs . '></span>';
				}

				$inner_html  = '<button class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $button_style_attr;
				$inner_html .= ' type="button" data-dsgo-modal-trigger="' . esc_attr( $target_modal_id ) . '">';
				$inner_html .= $icon_html;
				$inner_html .= '<span class="dsgo-modal-trigger__text">' . wp_kses_post( $text ) . '</span>';
				$inner_html .= '</button>';

				// The block root is a block-level justification wrapper — core's
				// constrained layout caps IT at the content column — with the
				// button shrink-wrapped inside it (see save.js). Matches
				// `getJustificationClass()` (src/utils/justification.js).
				$wrapper_class = 'wp-block-designsetgo-modal-trigger dsgo-justify dsgo-justify--' . $justification;

				return array(
					'opening' => '<div class="' . esc_attr( $wrapper_class ) . '">' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/table-of-contents':
				$unique_id     = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : substr( wp_generate_uuid4(), 0, 8 );
				$include_h2    = isset( $attributes['includeH2'] ) ? $attributes['includeH2'] : true;
				$include_h3    = isset( $attributes['includeH3'] ) ? $attributes['includeH3'] : true;
				$include_h4    = isset( $attributes['includeH4'] ) ? $attributes['includeH4'] : false;
				$include_h5    = isset( $attributes['includeH5'] ) ? $attributes['includeH5'] : false;
				$include_h6    = isset( $attributes['includeH6'] ) ? $attributes['includeH6'] : false;
				$display_mode  = isset( $attributes['displayMode'] ) ? $attributes['displayMode'] : 'hierarchical';
				$list_style    = isset( $attributes['listStyle'] ) ? $attributes['listStyle'] : 'unordered';
				$show_title    = isset( $attributes['showTitle'] ) ? $attributes['showTitle'] : true;
				$title_text    = isset( $attributes['titleText'] ) ? $attributes['titleText'] : 'Table of Contents';
				$scroll_smooth = isset( $attributes['scrollSmooth'] ) ? $attributes['scrollSmooth'] : true;
				$scroll_offset = isset( $attributes['scrollOffset'] ) ? self::numeric_attribute( $attributes['scrollOffset'] ) : 0;

				// Build heading levels.
				$heading_levels = array();
				if ( $include_h2 ) {
					$heading_levels[] = 'h2';
				}
				if ( $include_h3 ) {
					$heading_levels[] = 'h3';
				}
				if ( $include_h4 ) {
					$heading_levels[] = 'h4';
				}
				if ( $include_h5 ) {
					$heading_levels[] = 'h5';
				}
				if ( $include_h6 ) {
					$heading_levels[] = 'h6';
				}

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-table-of-contents', 'dsgo-table-of-contents' );
				if ( 'hierarchical' === $display_mode ) {
					$class_parts[] = 'dsgo-table-of-contents--hierarchical';
				} else {
					$class_parts[] = 'dsgo-table-of-contents--flat';
				}
				if ( 'ordered' === $list_style ) {
					$class_parts[] = 'dsgo-table-of-contents--ordered';
				}
				if ( $scroll_smooth ) {
					$class_parts[] = 'dsgo-table-of-contents--smooth';
				}

				// Data attributes.
				$data_attrs  = ' data-unique-id="' . esc_attr( $unique_id ) . '"';
				$data_attrs .= ' data-heading-levels="' . esc_attr( implode( ',', $heading_levels ) ) . '"';
				$data_attrs .= ' data-display-mode="' . esc_attr( $display_mode ) . '"';
				$data_attrs .= ' data-scroll-smooth="' . ( $scroll_smooth ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-scroll-offset="' . esc_attr( (string) $scroll_offset ) . '"';

				// List tag.
				$list_tag = 'ordered' === $list_style ? 'ol' : 'ul';

				// Inner HTML.
				$inner_html = '<div class="dsgo-table-of-contents__content">';
				// titleText is DOM-sourced, so the title element is always rendered
				// (matching save.js) and hidden via the `--hidden` modifier when the
				// toggle is off, rather than being omitted — otherwise a hidden
				// title's text would be silently lost on reload.
				$toc_title_class = 'dsgo-table-of-contents__title' . ( $show_title ? '' : ' dsgo-table-of-contents__title--hidden' );
				$inner_html     .= '<div class="' . esc_attr( $toc_title_class ) . '">' . esc_html( $title_text ) . '</div>';
				$inner_html     .= '<' . $list_tag . ' class="dsgo-table-of-contents__list"></' . $list_tag . '>';
				$inner_html     .= '</div>';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $data_attrs . '>' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/image-accordion':
				$height                   = isset( $attributes['height'] ) ? $attributes['height'] : '500px';
				$gap                      = isset( $attributes['gap'] ) ? $attributes['gap'] : '4px';
				$expanded_ratio           = isset( $attributes['expandedRatio'] ) ? floatval( $attributes['expandedRatio'] ) : 3;
				$transition_duration      = isset( $attributes['transitionDuration'] ) ? $attributes['transitionDuration'] : '0.5s';
				$enable_overlay           = isset( $attributes['enableOverlay'] ) ? $attributes['enableOverlay'] : true;
				$overlay_color            = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '#000000';
				$overlay_opacity          = isset( $attributes['overlayOpacity'] ) ? floatval( $attributes['overlayOpacity'] ) : 40;
				$overlay_opacity_expanded = isset( $attributes['overlayOpacityExpanded'] ) ? floatval( $attributes['overlayOpacityExpanded'] ) : 20;
				$trigger_type             = isset( $attributes['triggerType'] ) ? $attributes['triggerType'] : 'hover';
				$default_expanded         = isset( $attributes['defaultExpanded'] ) ? self::numeric_attribute( $attributes['defaultExpanded'] ) : 0;

				// Build classes.
				$class_parts   = array( 'wp-block-designsetgo-image-accordion', 'dsgo-image-accordion' );
				$class_parts[] = 'dsgo-image-accordion--' . esc_attr( $trigger_type );

				// Build style with CSS custom properties.
				// height and gap have no block.json default either, so save()
				// writes nothing for them and the stylesheet supplies the size.
				$style_parts = array();
				if ( isset( $attributes['height'] ) && '' !== $attributes['height'] ) {
					$style_parts[] = '--dsgo-image-accordion-height:' . esc_attr( $attributes['height'] );
				}
				if ( isset( $attributes['gap'] ) && '' !== $attributes['gap'] ) {
					$style_parts[] = '--dsgo-image-accordion-gap:' . esc_attr( $attributes['gap'] );
				}
				$style_parts[] = '--dsgo-image-accordion-expanded-ratio:' . esc_attr( (string) $expanded_ratio );
				$style_parts[] = '--dsgo-image-accordion-transition:' . esc_attr( $transition_duration );

				// overlayColor and the two opacities have NO block.json default,
				// so save() sees them undefined and writes nothing; the
				// stylesheet supplies the fallback. Inventing #000000 / 0.4 / 0.2
				// here emitted declarations save() never writes, and the block
				// was only "valid" because a deprecation claimed it — every
				// insert silently migrated on open.
				if ( isset( $attributes['overlayColor'] ) && '' !== $attributes['overlayColor'] ) {
					$style_parts[] = '--dsgo-image-accordion-overlay-color:' . esc_attr( $attributes['overlayColor'] );
				}
				if ( isset( $attributes['overlayOpacity'] ) && is_numeric( $attributes['overlayOpacity'] ) ) {
					$style_parts[] = '--dsgo-image-accordion-overlay-opacity:' . esc_attr( self::format_js_number( (float) $attributes['overlayOpacity'] / 100 ) );
				}
				if ( isset( $attributes['overlayOpacityExpanded'] ) && is_numeric( $attributes['overlayOpacityExpanded'] ) ) {
					$style_parts[] = '--dsgo-image-accordion-overlay-opacity-expanded:' . esc_attr( self::format_js_number( (float) $attributes['overlayOpacityExpanded'] / 100 ) );
				}
				$style       = implode( ';', $style_parts );

				// Data attributes.
				$data_attrs  = ' data-trigger-type="' . esc_attr( $trigger_type ) . '"';
				$data_attrs .= ' data-default-expanded="' . esc_attr( (string) $default_expanded ) . '"';
				$data_attrs .= ' data-enable-overlay="' . ( $enable_overlay ? 'true' : 'false' ) . '"';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '"' . $data_attrs . '><div class="dsgo-image-accordion__items">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/image-accordion-item':
				$unique_id            = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : 'image-accordion-item-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 );
				$vertical_alignment   = isset( $attributes['verticalAlignment'] ) ? $attributes['verticalAlignment'] : 'center';
				$horizontal_alignment = isset( $attributes['horizontalAlignment'] ) ? $attributes['horizontalAlignment'] : 'center';

				// The overlay marker is unconditional: enableOverlay is not an
				// attribute of this block (it comes from the parent through
				// usesContext, and save() receives no context), so save.js
				// hardcodes the enabled default for serialized markup.
				$class_parts = array(
					'wp-block-designsetgo-image-accordion-item',
					'dsgo-image-accordion-item',
					'dsgo-image-accordion-item--has-overlay',
				);

				// Build style with CSS custom properties (overlay first, then alignment - must match save.js order).
				$style_parts = array();
				// No overlay custom properties here. The overlay colour and
				// opacities are NOT attributes of this block - they come from the
				// parent accordion through usesContext, and WordPress passes no
				// context to save(), so save() serializes none of them and the
				// values cascade from the parent's own custom properties at
				// render time. Inventing #000000 / 0.4 / 0.2 wrote three
				// declarations save() never emits, and the block was only
				// "valid" because a deprecation claimed the markup - every
				// inserted item silently migrated when the editor opened it.
				$style_parts[] = '--dsgo-vertical-alignment:' . esc_attr( $vertical_alignment );
				$style_parts[] = '--dsgo-horizontal-alignment:' . esc_attr( $horizontal_alignment );
				$style         = implode( ';', $style_parts );

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '" data-unique-id="' . esc_attr( $unique_id ) . '" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/scroll-accordion':
				$align_items = isset( $attributes['alignItems'] ) ? $attributes['alignItems'] : 'flex-start';

				// Must match save.js: the constant layout (`width`/`align-self`
				// on the root, `display`/`flex-direction` on the items wrapper)
				// lives in style.scss and is no longer serialized. Only the
				// author-controlled alignItems is written inline. Emitting the
				// constants here would produce markup save() never generates, so
				// the block would fail validation on first open.
				$inner_style = 'align-items:' . esc_attr( $align_items );

				// Built as a list, not by concatenating a possibly-empty align
				// class between two others: trim() only strips the ends, so an
				// unaligned block was left with a double space in its class
				// attribute on every insert.
				$accordion_classes = array( 'wp-block-designsetgo-scroll-accordion' );
				$accordion_align   = self::align_class( $block_name, $attributes );
				if ( '' !== $accordion_align ) {
					$accordion_classes[] = $accordion_align;
				}
				$accordion_classes[] = 'dsgo-scroll-accordion';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $accordion_classes ) ) . '"><div class="dsgo-scroll-accordion__items" style="' . esc_attr( $inner_style ) . '">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/scroll-accordion-item':
				$overlay_color = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '';

				// Build classes.
				$class_parts        = array( 'wp-block-designsetgo-scroll-accordion-item', 'dsgo-scroll-accordion-item' );
				$accordion_item_align = self::align_class( $block_name, $attributes );
				if ( '' !== $accordion_item_align ) {
					$class_parts[] = $accordion_item_align;
				}
				if ( $overlay_color ) {
					$class_parts[] = 'dsgo-scroll-accordion-item--has-overlay';
				}

				// Build style.
				$style = '';
				if ( $overlay_color ) {
					$style = '--dsgo-overlay-color:' . esc_attr( $overlay_color ) . ';--dsgo-overlay-opacity:0.8';
				}

				$style_attr = $style ? ' style="' . esc_attr( $style ) . '"' : '';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $style_attr . '>',
					'closing' => '</div>',
				);

			case 'designsetgo/slider':
				// The inline isset() fallbacks below are normally unreachable
				// because apply_block_json_defaults() fills these from the
				// registry before we get here. They only fire when the block
				// isn't registered (e.g., build folder missing), so keep them
				// synced with src/blocks/slider/block.json — not with any
				// past convention like height=500px / arrowSize=48px.
				$slides_per_view        = isset( $attributes['slidesPerView'] ) ? self::numeric_attribute( $attributes['slidesPerView'] ) : 1;
				$slides_per_view_tablet = isset( $attributes['slidesPerViewTablet'] ) ? self::numeric_attribute( $attributes['slidesPerViewTablet'] ) : 1;
				$slides_per_view_mobile = isset( $attributes['slidesPerViewMobile'] ) ? self::numeric_attribute( $attributes['slidesPerViewMobile'] ) : 1;
				$height                 = isset( $attributes['height'] ) ? $attributes['height'] : '';
				$aspect_ratio           = isset( $attributes['aspectRatio'] ) ? $attributes['aspectRatio'] : '16/9';
				$use_aspect_ratio       = isset( $attributes['useAspectRatio'] ) ? $attributes['useAspectRatio'] : false;
				$gap                    = isset( $attributes['gap'] ) ? $attributes['gap'] : '20px';
				$show_arrows            = isset( $attributes['showArrows'] ) ? $attributes['showArrows'] : true;
				$show_dots              = isset( $attributes['showDots'] ) ? $attributes['showDots'] : true;
				$arrow_style            = isset( $attributes['arrowStyle'] ) ? $attributes['arrowStyle'] : 'default';
				$arrow_position         = isset( $attributes['arrowPosition'] ) ? $attributes['arrowPosition'] : 'sides';
				$arrow_vertical_pos     = isset( $attributes['arrowVerticalPosition'] ) ? $attributes['arrowVerticalPosition'] : 'center';
				$arrow_color            = isset( $attributes['arrowColor'] ) ? $attributes['arrowColor'] : '';
				$arrow_bg_color         = isset( $attributes['arrowBackgroundColor'] ) ? $attributes['arrowBackgroundColor'] : '';
				$arrow_size             = isset( $attributes['arrowSize'] ) ? $attributes['arrowSize'] : '24px';
				$arrow_padding          = isset( $attributes['arrowPadding'] ) ? $attributes['arrowPadding'] : '';
				$dot_style              = isset( $attributes['dotStyle'] ) ? $attributes['dotStyle'] : 'default';
				$dot_position           = isset( $attributes['dotPosition'] ) ? $attributes['dotPosition'] : 'inside';
				$dot_color              = isset( $attributes['dotColor'] ) ? $attributes['dotColor'] : '';
				$effect                 = isset( $attributes['effect'] ) ? $attributes['effect'] : 'slide';
				$transition_duration    = isset( $attributes['transitionDuration'] ) ? $attributes['transitionDuration'] : '0.5s';
				$transition_easing      = isset( $attributes['transitionEasing'] ) ? $attributes['transitionEasing'] : 'ease-in-out';
				$autoplay               = isset( $attributes['autoplay'] ) ? $attributes['autoplay'] : false;
				$autoplay_interval      = isset( $attributes['autoplayInterval'] ) ? self::numeric_attribute( $attributes['autoplayInterval'] ) : 3000;
				$pause_on_hover         = isset( $attributes['pauseOnHover'] ) ? $attributes['pauseOnHover'] : true;
				$pause_on_interaction   = isset( $attributes['pauseOnInteraction'] ) ? $attributes['pauseOnInteraction'] : true;
				$loop                   = isset( $attributes['loop'] ) ? $attributes['loop'] : true;
				$draggable              = isset( $attributes['draggable'] ) ? $attributes['draggable'] : true;
				$swipeable              = isset( $attributes['swipeable'] ) ? $attributes['swipeable'] : true;
				$free_mode              = isset( $attributes['freeMode'] ) ? $attributes['freeMode'] : false;
				$centered_slides        = isset( $attributes['centeredSlides'] ) ? $attributes['centeredSlides'] : false;
				$mobile_breakpoint      = isset( $attributes['mobileBreakpoint'] ) ? self::numeric_attribute( $attributes['mobileBreakpoint'] ) : 768;
				$tablet_breakpoint      = isset( $attributes['tabletBreakpoint'] ) ? self::numeric_attribute( $attributes['tabletBreakpoint'] ) : 1024;
				$active_slide           = isset( $attributes['activeSlide'] ) ? self::numeric_attribute( $attributes['activeSlide'] ) : 0;
				$style_variation        = isset( $attributes['styleVariation'] ) ? $attributes['styleVariation'] : 'classic';
				$aria_label             = isset( $attributes['ariaLabel'] ) ? $attributes['ariaLabel'] : '';
				$scroll_driven          = isset( $attributes['scrollDriven'] ) ? $attributes['scrollDriven'] : false;
				$scroll_driven_speed    = isset( $attributes['scrollDrivenSpeed'] ) ? floatval( $attributes['scrollDrivenSpeed'] ) : 1;

				// Single slide effects.
				$single_slide_effects    = array( 'fade', 'zoom' );
				$requires_single         = in_array( $effect, $single_slide_effects, true );
				$effective_slides        = $requires_single ? 1 : $slides_per_view;
				$effective_slides_tablet = $requires_single ? 1 : $slides_per_view_tablet;
				$effective_slides_mobile = $requires_single ? 1 : $slides_per_view_mobile;

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-slider', 'dsgo-slider' );
				if ( $style_variation ) {
					$class_parts[] = 'dsgo-slider--' . esc_attr( $style_variation );
				}
				if ( $effect ) {
					$class_parts[] = 'dsgo-slider--effect-' . esc_attr( $effect );
				}
				if ( $show_arrows ) {
					$class_parts[] = 'dsgo-slider--has-arrows';
				}
				if ( $show_dots ) {
					$class_parts[] = 'dsgo-slider--has-dots';
				}
				if ( $centered_slides ) {
					$class_parts[] = 'dsgo-slider--centered';
				}
				if ( $free_mode ) {
					$class_parts[] = 'dsgo-slider--free-mode';
				}
				if ( $scroll_driven ) {
					$class_parts[] = 'dsgo-slider--scroll-driven';
				}

				// Build style. Mirror save.js: height is only included when
				// truthy (block.json default is ""), and arrow size follows the
				// same rule. Emitting them unconditionally here breaks
				// round-tripping against save().
				$style_parts = array();
				if ( $height ) {
					$style_parts[] = '--dsgo-slider-height:' . esc_attr( $height );
				}
				$style_parts[] = '--dsgo-slider-aspect-ratio:' . esc_attr( $aspect_ratio );
				$style_parts[] = '--dsgo-slider-gap:' . esc_attr( $gap );
				$style_parts[] = '--dsgo-slider-transition:' . esc_attr( $transition_duration );
				$style_parts[] = '--dsgo-slider-slides-per-view:' . esc_attr( (string) $effective_slides );
				$style_parts[] = '--dsgo-slider-slides-per-view-tablet:' . esc_attr( (string) $effective_slides_tablet );
				$style_parts[] = '--dsgo-slider-slides-per-view-mobile:' . esc_attr( (string) $effective_slides_mobile );
				if ( $arrow_color ) {
					$style_parts[] = '--dsgo-slider-arrow-color:' . esc_attr( self::convert_color_value_to_css_var( $arrow_color ) );
				}
				if ( $arrow_bg_color ) {
					$style_parts[] = '--dsgo-slider-arrow-bg-color:' . esc_attr( self::convert_color_value_to_css_var( $arrow_bg_color ) );
				}
				if ( $arrow_size ) {
					$style_parts[] = '--dsgo-slider-arrow-size:' . esc_attr( $arrow_size );
				}
				if ( $arrow_padding ) {
					$style_parts[] = '--dsgo-slider-arrow-padding:' . esc_attr( $arrow_padding );
				}
				if ( $dot_color ) {
					$style_parts[] = '--dsgo-slider-dot-color:' . esc_attr( self::convert_color_value_to_css_var( $dot_color ) );
				}
				$style = implode( ';', $style_parts );

				// Build data attributes.
				$data_attrs  = ' data-slides-per-view="' . esc_attr( (string) $effective_slides ) . '"';
				$data_attrs .= ' data-slides-per-view-tablet="' . esc_attr( (string) $effective_slides_tablet ) . '"';
				$data_attrs .= ' data-slides-per-view-mobile="' . esc_attr( (string) $effective_slides_mobile ) . '"';
				$data_attrs .= ' data-use-aspect-ratio="' . ( $use_aspect_ratio ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-show-arrows="' . ( $show_arrows ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-show-dots="' . ( $show_dots ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-arrow-style="' . esc_attr( $arrow_style ) . '"';
				$data_attrs .= ' data-arrow-position="' . esc_attr( $arrow_position ) . '"';
				$data_attrs .= ' data-arrow-vertical-position="' . esc_attr( $arrow_vertical_pos ) . '"';
				$data_attrs .= ' data-dot-style="' . esc_attr( $dot_style ) . '"';
				$data_attrs .= ' data-dot-position="' . esc_attr( $dot_position ) . '"';
				$data_attrs .= ' data-effect="' . esc_attr( $effect ) . '"';
				$data_attrs .= ' data-transition-duration="' . esc_attr( $transition_duration ) . '"';
				$data_attrs .= ' data-transition-easing="' . esc_attr( $transition_easing ) . '"';
				$data_attrs .= ' data-autoplay="' . ( $autoplay ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-autoplay-interval="' . esc_attr( (string) $autoplay_interval ) . '"';
				$data_attrs .= ' data-pause-on-hover="' . ( $pause_on_hover ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-pause-on-interaction="' . ( $pause_on_interaction ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-loop="' . ( $loop ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-draggable="' . ( $draggable ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-swipeable="' . ( $swipeable ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-free-mode="' . ( $free_mode ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-centered-slides="' . ( $centered_slides ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-mobile-breakpoint="' . esc_attr( (string) $mobile_breakpoint ) . '"';
				$data_attrs .= ' data-tablet-breakpoint="' . esc_attr( (string) $tablet_breakpoint ) . '"';
				$data_attrs .= ' data-active-slide="' . esc_attr( (string) $active_slide ) . '"';
				if ( $scroll_driven ) {
					$data_attrs .= ' data-scroll-driven="true"';
					$data_attrs .= ' data-scroll-driven-speed="' . esc_attr( (string) $scroll_driven_speed ) . '"';
				}

				$aria = $aria_label ? $aria_label : 'Image slider';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '"' . $data_attrs . ' role="region" aria-label="' . esc_attr( $aria ) . '" aria-roledescription="slider"><div class="dsgo-slider__viewport"><div class="dsgo-slider__track">',
					'closing' => '</div></div></div>',
				);

			case 'designsetgo/slide':
				$background_image    = isset( $attributes['backgroundImage'] ) ? $attributes['backgroundImage'] : array();
				$background_size     = isset( $attributes['backgroundSize'] ) ? $attributes['backgroundSize'] : 'cover';
				$background_position = isset( $attributes['backgroundPosition'] ) ? $attributes['backgroundPosition'] : 'center center';
				$background_repeat   = isset( $attributes['backgroundRepeat'] ) ? $attributes['backgroundRepeat'] : 'no-repeat';
				$overlay_color       = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '';
				$overlay_opacity     = isset( $attributes['overlayOpacity'] ) ? floatval( $attributes['overlayOpacity'] ) : 80;
				$content_v_align     = isset( $attributes['contentVerticalAlign'] ) ? $attributes['contentVerticalAlign'] : 'center';
				$content_h_align     = isset( $attributes['contentHorizontalAlign'] ) ? $attributes['contentHorizontalAlign'] : 'center';
				$min_height          = isset( $attributes['minHeight'] ) ? $attributes['minHeight'] : '';
				$bg_url              = isset( $background_image['url'] ) ? $background_image['url'] : '';

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-slide', 'dsgo-slide' );
				if ( $bg_url ) {
					$class_parts[] = 'dsgo-slide--has-background';
				}
				if ( $overlay_color ) {
					$class_parts[] = 'dsgo-slide--has-overlay';
				}

				// Build style.
				$style_parts = array();
				if ( $bg_url ) {
					$style_parts[] = 'background-image:url(' . esc_url( $bg_url ) . ')';
					$style_parts[] = 'background-size:' . esc_attr( $background_size );
					$style_parts[] = 'background-position:' . esc_attr( $background_position );
					$style_parts[] = 'background-repeat:' . esc_attr( $background_repeat );
				}
				if ( $overlay_color ) {
					$style_parts[] = '--dsgo-slide-overlay-color:' . esc_attr( $overlay_color );
					$style_parts[] = '--dsgo-slide-overlay-opacity:' . esc_attr( (string) ( $overlay_opacity / 100 ) );
				}
				$style_parts[] = '--dsgo-slide-content-vertical-align:' . esc_attr( $content_v_align );
				$style_parts[] = '--dsgo-slide-content-horizontal-align:' . esc_attr( $content_h_align );
				if ( $min_height ) {
					$style_parts[] = 'min-height:' . esc_attr( $min_height );
				}
				$style = implode( ';', $style_parts );

				// Overlay HTML.
				$overlay_html = '';
				if ( $overlay_color ) {
					$overlay_style = 'background-color:' . esc_attr( $overlay_color ) . ';opacity:' . esc_attr( (string) ( $overlay_opacity / 100 ) );
					$overlay_html  = '<div class="dsgo-slide__overlay" style="' . esc_attr( $overlay_style ) . '"></div>';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '" role="group" aria-roledescription="slide">' . $overlay_html . '<div class="dsgo-slide__content">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/scroll-marquee':
				$rows          = isset( $attributes['rows'] ) ? $attributes['rows'] : array();
				$scroll_speed  = isset( $attributes['scrollSpeed'] ) ? floatval( $attributes['scrollSpeed'] ) : 0.5;
				$image_height  = isset( $attributes['imageHeight'] ) ? $attributes['imageHeight'] : '200px';
				// block.json defaults imageWidth to 'auto', not '300px'.
				$image_width = isset( $attributes['imageWidth'] ) ? $attributes['imageWidth'] : 'auto';
				$gap         = isset( $attributes['gap'] ) ? $attributes['gap'] : '20px';
				$row_gap     = isset( $attributes['rowGap'] ) ? $attributes['rowGap'] : '20px';
				$object_fit  = isset( $attributes['objectFit'] ) ? $attributes['objectFit'] : 'cover';

				// Build style. save.js writes object-fit here; the border-radius
				// custom property this used to emit was removed from save.js, so
				// every marquee serialized a declaration save() never writes.
				$style_parts = array(
					'--dsgo-marquee-gap:' . esc_attr( $gap ),
					'--dsgo-marquee-row-gap:' . esc_attr( $row_gap ),
					'--dsgo-marquee-image-height:' . esc_attr( $image_height ),
					'--dsgo-marquee-image-width:' . esc_attr( $image_width ),
					'--dsgo-marquee-object-fit:' . esc_attr( $object_fit ),
				);
				$style       = implode( ';', $style_parts );

				// Build rows HTML.
				$rows_html = '';
				foreach ( $rows as $row ) {
					$direction = isset( $row['direction'] ) ? $row['direction'] : 'left';
					$images    = isset( $row['images'] ) ? $row['images'] : array();

					$rows_html .= '<div class="dsgo-scroll-marquee__row" data-direction="' . esc_attr( $direction ) . '">';
					$rows_html .= '<div class="dsgo-scroll-marquee__track">';

					// Render images 6 times for seamless infinite scroll.
					for ( $i = 0; $i < 6; $i++ ) {
						$rows_html .= '<div class="dsgo-scroll-marquee__track-segment">';
						foreach ( $images as $image ) {
							$img_url    = isset( $image['url'] ) ? $image['url'] : '';
							$img_alt    = isset( $image['alt'] ) ? $image['alt'] : '';
							$rows_html .= '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $img_alt ) . '" class="dsgo-scroll-marquee__image" loading="lazy"/>';
						}
						$rows_html .= '</div>';
					}

					$rows_html .= '</div></div>';
				}

				return array(
					'opening' => '<div class="wp-block-designsetgo-scroll-marquee dsgo-scroll-marquee" data-scroll-speed="' . esc_attr( (string) $scroll_speed ) . '" style="' . esc_attr( $style ) . '">' . $rows_html,
					'closing' => '</div>',
				);

			case 'designsetgo/tabs':
				$unique_id         = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 );
				$orientation       = isset( $attributes['orientation'] ) ? $attributes['orientation'] : 'horizontal';
				$active_tab        = isset( $attributes['activeTab'] ) ? self::numeric_attribute( $attributes['activeTab'] ) : 0;
				$alignment         = isset( $attributes['alignment'] ) ? $attributes['alignment'] : 'left';
				$mobile_breakpoint = isset( $attributes['mobileBreakpoint'] ) ? self::numeric_attribute( $attributes['mobileBreakpoint'] ) : 768;
				$mobile_mode       = isset( $attributes['mobileMode'] ) ? $attributes['mobileMode'] : 'accordion';
				$enable_deep_link  = isset( $attributes['enableDeepLinking'] ) ? $attributes['enableDeepLinking'] : false;
				$gap               = isset( $attributes['gap'] ) ? $attributes['gap'] : '8px';
				$tab_style         = isset( $attributes['tabStyle'] ) ? $attributes['tabStyle'] : 'default';
				$show_nav_border   = isset( $attributes['showNavBorder'] ) ? $attributes['showNavBorder'] : false;

				// Build classes.
				$class_parts   = array( 'wp-block-designsetgo-tabs', 'dsgo-tabs', 'dsgo-tabs-' . esc_attr( $unique_id ) );
				$class_parts[] = 'dsgo-tabs--' . esc_attr( $orientation );
				$class_parts[] = 'dsgo-tabs--' . esc_attr( $tab_style );
				$class_parts[] = 'dsgo-tabs--align-' . esc_attr( $alignment );
				if ( $show_nav_border ) {
					$class_parts[] = 'dsgo-tabs--show-nav-border';
				}

				// Build style.
				// Mirrors save.js: the gap always, then each colour custom
				// property only when its attribute is set. These eight were
				// missing entirely, so any Tabs block given colours stored
				// markup that did not match save().
				$tab_style_parts = array( '--dsgo-tabs-gap:' . esc_attr( $gap ) );

				$tab_color_vars = array(
					'tabColor'                   => '--dsgo-tab-color',
					'tabBackgroundColor'         => '--dsgo-tab-bg',
					'tabContentBackgroundColor'  => '--dsgo-tab-content-bg',
					'activeTabColor'             => '--dsgo-tab-color-active',
					'activeTabBackgroundColor'   => '--dsgo-tab-bg-active',
					'tabBorderColor'             => '--dsgo-tab-border-color',
					'tabHoverColor'              => '--dsgo-tab-color-hover',
					'tabHoverBackgroundColor'    => '--dsgo-tab-bg-hover',
				);

				foreach ( $tab_color_vars as $attribute_name => $custom_property ) {
					$colour = isset( $attributes[ $attribute_name ] ) ? (string) $attributes[ $attribute_name ] : '';
					if ( '' !== $colour ) {
						$tab_style_parts[] = $custom_property . ':' . esc_attr( self::convert_color_value_to_css_var( $colour ) );
					}
				}

				$style = implode( ';', $tab_style_parts );

				// Data attributes.
				$data_attrs  = ' data-active-tab="' . esc_attr( (string) $active_tab ) . '"';
				$data_attrs .= ' data-mobile-breakpoint="' . esc_attr( (string) $mobile_breakpoint ) . '"';
				$data_attrs .= ' data-mobile-mode="' . esc_attr( $mobile_mode ) . '"';
				$data_attrs .= ' data-deep-linking="' . ( $enable_deep_link ? 'true' : 'false' ) . '"';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '"' . $data_attrs . '><div class="dsgo-tabs__nav" role="tablist"></div><div class="dsgo-tabs__panels">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/tab':
				$unique_id     = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 );
				$title         = isset( $attributes['title'] ) ? $attributes['title'] : 'Tab';
				$anchor        = isset( $attributes['anchor'] ) ? $attributes['anchor'] : '';
				$icon          = isset( $attributes['icon'] ) ? $attributes['icon'] : '';
				$icon_position = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'none';

				// Build panel ID.
				$panel_id = 'panel-' . ( $anchor ? esc_attr( $anchor ) : esc_attr( $unique_id ) );

				// Build aria-label.
				$aria_label = $title ? $title : 'Tab ' . $unique_id;

				// Data attributes for icon.
				$icon_data = '';
				if ( $icon && $icon_position && 'none' !== $icon_position ) {
					$safe_icon     = strtolower( preg_replace( '/[^a-z0-9\-]/i', '', $icon ) );
					$safe_position = in_array( $icon_position, array( 'left', 'right' ), true ) ? $icon_position : 'left';
					$icon_data     = ' data-icon="' . esc_attr( $safe_icon ) . '" data-icon-position="' . esc_attr( $safe_position ) . '"';
				}

				// Same list-building reason as scroll-accordion above.
				$tab_classes = array( 'wp-block-designsetgo-tab' );
				$tab_align   = self::align_class( $block_name, $attributes );
				if ( '' !== $tab_align ) {
					$tab_classes[] = $tab_align;
				}
				$tab_classes[] = 'dsgo-tab';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $tab_classes ) ) . '" role="tabpanel" aria-labelledby="tab-' . esc_attr( $unique_id ) . '" aria-label="' . esc_attr( $aria_label ) . '" id="' . esc_attr( $panel_id ) . '" hidden' . $icon_data . '><div class="dsgo-tab__content">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/form-builder':
				return self::generate_form_builder_html( $block_class, $attributes );

			default:
				return null;
		}
	}

	/**
	 * Generate wrapper HTML for form-builder block.
	 *
	 * @param string               $block_class Base block class.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string> Array with 'opening' and 'closing' keys.
	 */
	private static function generate_form_builder_html( string $block_class, array $attributes ): array {
		// Get attributes with defaults from block.json.
		$form_id                          = $attributes['formId'] ?? '';
		$submit_button_text               = $attributes['submitButtonText'] ?? 'Submit';
		$submit_button_alignment          = $attributes['submitButtonAlignment'] ?? 'left';
		$submit_button_position           = $attributes['submitButtonPosition'] ?? 'below';
		$submit_button_variation          = $attributes['submitButtonVariation'] ?? 'default';
		$ajax_submit                      = $attributes['ajaxSubmit'] ?? true;
		$success_message                  = $attributes['successMessage'] ?? 'Thank you! Your form has been submitted successfully.';
		$error_message                    = $attributes['errorMessage'] ?? 'There was an error submitting the form. Please try again.';
		$field_spacing                    = $attributes['fieldSpacing'] ?? '1.5rem';
		$input_height                     = $attributes['inputHeight'] ?? '44px';
		$input_padding                    = $attributes['inputPadding'] ?? '0.75rem';
		$field_label_color                = $attributes['fieldLabelColor'] ?? '';
		$field_border_color               = $attributes['fieldBorderColor'] ?? '';
		$field_background_color           = $attributes['fieldBackgroundColor'] ?? '';
		$submit_button_color              = $attributes['submitButtonColor'] ?? '';
		$submit_button_background_color   = $attributes['submitButtonBackgroundColor'] ?? '';
		$submit_button_padding_vertical   = $attributes['submitButtonPaddingVertical'] ?? '0.75rem';
		$submit_button_padding_horizontal = $attributes['submitButtonPaddingHorizontal'] ?? '2rem';
		$submit_button_font_size          = $attributes['submitButtonFontSize'] ?? '';
		$submit_button_height             = $attributes['submitButtonHeight'] ?? '44px';
		$enable_honeypot                  = $attributes['enableHoneypot'] ?? true;
		$enable_turnstile                 = $attributes['enableTurnstile'] ?? false;
		$enable_email                     = $attributes['enableEmail'] ?? false;
		$email_to                         = $attributes['emailTo'] ?? '';
		$email_subject                    = $attributes['emailSubject'] ?? 'New Form Submission';
		$email_from_name                  = $attributes['emailFromName'] ?? '';
		$email_from_email                 = $attributes['emailFromEmail'] ?? '';
		$email_reply_to                   = $attributes['emailReplyTo'] ?? '';
		$email_body                       = $attributes['emailBody'] ?? '';

		// Submit-button style variation class - must match save.js. Validated
		// against the block.json enum so an AI-supplied value can't inject markup.
		// The `is-style-` namespace (not `dsgo-form__submit--*`) keeps it clear of
		// the layout/state/animation modifiers that share the BEM namespace.
		$submit_button_variation_class = in_array( $submit_button_variation, array( 'secondary', 'outline' ), true )
			? ' is-style-' . $submit_button_variation
			: '';

		// Build classes - must match save.js.
		$classes = $block_class;
		if ( $submit_button_alignment && 'below' === $submit_button_position ) {
			$classes .= ' dsgo-form-builder--align-' . $submit_button_alignment;
		}
		if ( 'inline' === $submit_button_position ) {
			$classes .= ' dsgo-form-builder--button-inline';
		}

		// Build CSS custom properties - must match save.js order. Each of these
		// three is spread conditionally in save.js (`...(fieldSpacing && {...})`),
		// so an unset value emits no declaration at all. Writing an empty value
		// instead made every form with default sizing invalid.
		$style_parts = array();
		if ( '' !== (string) $field_spacing ) {
			$style_parts[] = '--dsgo-form-field-spacing:' . esc_attr( $field_spacing );
		}
		if ( '' !== (string) $input_height ) {
			$style_parts[] = '--dsgo-form-input-height:' . esc_attr( $input_height );
		}
		if ( '' !== (string) $input_padding ) {
			$style_parts[] = '--dsgo-form-input-padding:' . esc_attr( $input_padding );
		}
		if ( $field_label_color ) {
			$style_parts[] = '--dsgo-form-label-color:' . esc_attr( $field_label_color );
		}
		// Omit when empty — .dsgo-form-builder in style.scss supplies the #d1d5db default.
		if ( $field_border_color ) {
			$style_parts[] = '--dsgo-form-border-color:' . esc_attr( $field_border_color );
		}
		if ( $field_background_color ) {
			$style_parts[] = '--dsgo-form-field-bg:' . esc_attr( $field_background_color );
		}
		$style = implode( ';', $style_parts );

		// Build data attributes.
		$data_attrs = array(
			'data-form-id="' . esc_attr( $form_id ) . '"',
			'data-ajax-submit="' . ( $ajax_submit ? 'true' : 'false' ) . '"',
			'data-success-message="' . esc_attr( $success_message ) . '"',
			'data-error-message="' . esc_attr( $error_message ) . '"',
			// submitButtonText is sourced from the submit button's text, not a
			// wrapper attribute — save.js no longer emits data-submit-text, so
			// emitting it here would fail block validation.
			//
			// The email settings are deliberately absent too. save.js never
			// emits them: they are notification config, they live in the block
			// comment where the server reads them, and putting the recipient,
			// reply-to and body template into public markup would publish the
			// form's mail configuration to every visitor. Emitting them here
			// both leaked that and failed validation on every form.
		);
		if ( $enable_turnstile ) {
			$data_attrs[] = 'data-dsgo-turnstile="true"';
		}
		$data_str = implode( ' ', $data_attrs );

		// Build button style - must match save.js order.
		$button_style_parts = array();
		if ( $submit_button_color ) {
			$button_style_parts[] = 'color:' . esc_attr( $submit_button_color );
		}
		if ( $submit_button_background_color ) {
			$button_style_parts[] = 'background-color:' . esc_attr( $submit_button_background_color );
		}
		// Sizing is spread conditionally in save.js, so an unset value emits no
		// declaration and the button inherits the theme's global button styles.
		if ( '' !== (string) $submit_button_height ) {
			$button_style_parts[] = 'min-height:' . esc_attr( $submit_button_height );
		}
		if ( '' !== (string) $submit_button_padding_vertical ) {
			$button_style_parts[] = 'padding-top:' . esc_attr( $submit_button_padding_vertical );
			$button_style_parts[] = 'padding-bottom:' . esc_attr( $submit_button_padding_vertical );
		}
		if ( '' !== (string) $submit_button_padding_horizontal ) {
			$button_style_parts[] = 'padding-left:' . esc_attr( $submit_button_padding_horizontal );
			$button_style_parts[] = 'padding-right:' . esc_attr( $submit_button_padding_horizontal );
		}
		if ( $submit_button_font_size ) {
			$button_style_parts[] = 'font-size:' . esc_attr( $submit_button_font_size );
		}
		$button_style = implode( ';', $button_style_parts );

		// Opening HTML: outer div + form + fields wrapper.
		$opening  = '<div class="' . esc_attr( $classes ) . '"' .
			( '' !== $style ? ' style="' . $style . '"' : '' ) . ' ' . $data_str . '>';
		$opening .= '<form class="dsgo-form" method="post" novalidate>';
		$opening .= '<div class="dsgo-form__fields">';

		// Closing HTML: depends on button position.
		$closing = '';

		// Inline button goes inside fields wrapper, before closing.
		if ( 'inline' === $submit_button_position ) {
			$closing .= '<button type="submit" class="dsgo-form__submit dsgo-form__submit--inline' . $submit_button_variation_class . ' wp-element-button"' .
				( '' !== $button_style ? ' style="' . $button_style . '"' : '' ) . '>' . esc_html( $submit_button_text ) . '</button>';
		}

		// Close fields wrapper.
		$closing .= '</div>';

		// Honeypot field.
		if ( $enable_honeypot ) {
			$closing .= '<input type="text" name="dsg_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"/>';
		}

		// Hidden form ID.
		$closing .= '<input type="hidden" name="dsg_form_id" value="' . esc_attr( $form_id ) . '"/>';

		// Turnstile widget container.
		if ( $enable_turnstile ) {
			$closing .= '<div class="dsgo-turnstile-widget" data-dsgo-turnstile-container="true"></div>';
		}

		// Footer with button (below position).
		if ( 'below' === $submit_button_position ) {
			$closing .= '<div class="dsgo-form__footer">';
			$closing .= '<button type="submit" class="dsgo-form__submit' . $submit_button_variation_class . ' wp-element-button"' .
				( '' !== $button_style ? ' style="' . $button_style . '"' : '' ) . '>' . esc_html( $submit_button_text ) . '</button>';
			$closing .= '</div>';
		}

		// Message container.
		$closing .= '<div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display:none"></div>';

		// Close form and outer div.
		$closing .= '</form></div>';

		return array(
			'opening' => $opening,
			'closing' => $closing,
		);
	}

	/**
	 * Generate HTML for core WordPress blocks.
	 *
	 * @param string               $block_name Block name.
	 * @param string               $content Content text.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Generated HTML.
	 */
	private static function generate_core_block_html( string $block_name, string $content, array $attributes ): string {
		// These land inside class="...", so an arbitrary value would break out of
		// the attribute: textAlign of `center" onmouseover="alert(1)` yields a
		// live event handler. They are alignment keywords, so rather than merely
		// escape them, constrain them to the set core actually emits — then the
		// value is provably safe rather than safe-looking. Anything else is
		// dropped, which is also the correct rendering for a nonsense alignment.
		$allowed_alignments = array( 'left', 'center', 'right', 'justify' );

		$text_align = ( isset( $attributes['textAlign'] ) && in_array( $attributes['textAlign'], $allowed_alignments, true ) )
			? ' has-text-align-' . $attributes['textAlign']
			: '';
		$align      = ( isset( $attributes['align'] ) && in_array( $attributes['align'], $allowed_alignments, true ) )
			? ' has-text-align-' . $attributes['align']
			: '';

		switch ( $block_name ) {
			case 'core/heading':
				$level = isset( $attributes['level'] ) ? (int) $attributes['level'] : 2;
				$class = 'wp-block-heading' . $text_align;
				return '<h' . $level . ' class="' . trim( $class ) . '">' . wp_kses_post( $content ) . '</h' . $level . '>';

			case 'core/paragraph':
				$class      = trim( $align );
				$class_attr = $class ? ' class="' . $class . '"' : '';
				return '<p' . $class_attr . '>' . wp_kses_post( $content ) . '</p>';

			default:
				return wp_kses_post( $content );
		}
	}

	/**
	 * Coerce attribute types to match block.json schema.
	 *
	 * Ensures numeric attributes are stored as numbers (not strings) so that
	 * WordPress block validation doesn't fail due to type mismatches.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Attributes to coerce.
	 * @return array<string, mixed> Attributes with corrected types.
	 */
	private static function coerce_attribute_types( string $block_name, array $attributes ): array {
		// Driven by the block's registered attribute schema rather than a
		// hand-kept list. A hardcoded list only covers the blocks somebody
		// remembered to add, and an uncovered numeric attribute arrives as a
		// string, which serializes into the block comment quoted and no longer
		// matches what save() would emit.
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		if ( ! $block_type || empty( $block_type->attributes ) ) {
			return $attributes;
		}

		foreach ( $attributes as $attr_name => $value ) {
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}

			$type = $block_type->attributes[ $attr_name ]['type'] ?? null;

			switch ( $type ) {
				case 'number':
					if ( is_numeric( $value ) ) {
						// Unary plus yields int for "3" and float for "3.5",
						// matching how the editor would have stored it.
						$attributes[ $attr_name ] = +$value;
					}
					break;

				case 'integer':
					if ( is_numeric( $value ) ) {
						$attributes[ $attr_name ] = (int) $value;
					}
					break;

				case 'boolean':
					if ( in_array( strtolower( $value ), array( 'true', 'false', '1', '0' ), true ) ) {
						$attributes[ $attr_name ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
					}
					break;
			}
		}

		return $attributes;
	}

	/**
	 * Render a numeric attribute the way the editor would serialize it.
	 *
	 * Truncation via intval() here was silently dropping every fractional
	 * value: a slider
	 * stored slidesPerView 1.2 in its block comment while the generated HTML
	 * said 1, so the block failed validation the moment it was opened. Integers
	 * still render as integers, so nothing that was already correct changes.
	 *
	 * @param mixed     $value   Attribute value.
	 * @param int|float $default Fallback when the value is not numeric.
	 * @return int|float Numeric value.
	 */
	private static function numeric_attribute( $value, $default = 0 ) {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		$number = +$value;

		// A float that lands exactly on an integer renders without a decimal
		// point, which is what JSON.stringify() does in the editor too.
		if ( is_float( $number ) && (float) (int) $number === $number ) {
			return (int) $number;
		}

		return $number;
	}

	/**
	 * Normalize block attributes to include required defaults.
	 *
	 * Ensures attributes like layout have all required fields for block validation.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Attributes to normalize.
	 * @return array<string, mixed> Normalized attributes.
	 */
	private static function normalize_block_attributes( string $block_name, array $attributes ): array {
		switch ( $block_name ) {
			case 'designsetgo/row':
				// Ensure layout has all required fields for block validation.
				$layout               = isset( $attributes['layout'] ) ? $attributes['layout'] : array();
				$attributes['layout'] = array_merge(
					array(
						'type'           => 'flex',
						'orientation'    => 'horizontal',
						'justifyContent' => 'left',
						'flexWrap'       => 'nowrap',
					),
					$layout
				);
				break;

			case 'designsetgo/grid':
				// Ensure column counts are set for block validation.
				if ( ! isset( $attributes['desktopColumns'] ) ) {
					$attributes['desktopColumns'] = 3;
				}
				if ( ! isset( $attributes['tabletColumns'] ) ) {
					$attributes['tabletColumns'] = 2;
				}
				if ( ! isset( $attributes['mobileColumns'] ) ) {
					$attributes['mobileColumns'] = 1;
				}
				break;

			case 'designsetgo/accordion-item':
				// Ensure uniqueId is set for accessibility attributes.
				if ( ! isset( $attributes['uniqueId'] ) ) {
					$attributes['uniqueId'] = 'accordion-item-' . wp_generate_uuid4();
				}
				break;

			case 'designsetgo/modal':
				// Trim overlayColor HERE, where both consumers (the wrapper
				// HTML builder and the serialized comment attrs) still share
				// one array — save.js's hasExplicitString()/
				// convertColorToCSSVar() never trim, so an untrimmed stored
				// value would regenerate different markup on first parse and
				// fail validation. Whitespace-only means "not set": drop the
				// attribute so the scrim inherits the stylesheet default,
				// matching an editor-cleared color.
				if ( isset( $attributes['overlayColor'] ) ) {
					$trimmed_overlay = trim( (string) $attributes['overlayColor'] );
					if ( '' === $trimmed_overlay ) {
						unset( $attributes['overlayColor'] );
					} else {
						$attributes['overlayColor'] = $trimmed_overlay;
					}
				}
				break;

			case 'designsetgo/counter':
				// Ensure uniqueId is set for element ID.
				if ( ! isset( $attributes['uniqueId'] ) ) {
					$attributes['uniqueId'] = 'counter-' . wp_generate_uuid4();
				}
				break;
		}

		return $attributes;
	}

	/**
	 * Fill missing attributes with their block.json defaults.
	 *
	 * The wrapper HTML generators previously carried their own fallback
	 * defaults, which drifted from block.json over time and produced markup
	 * that didn't round-trip through save(). Applying block.json defaults up
	 * front keeps the ability in sync with the single source of truth.
	 *
	 * Scoped to an allowlist rather than applied to every block: other
	 * wrapper generators have not been audited against their save() output,
	 * so forcing block.json defaults on them could expose unrelated latent
	 * mismatches. Add blocks here as their wrappers are verified.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Incoming attributes.
	 * @return array<string, mixed> Attributes merged with block.json defaults.
	 */
	private static function apply_block_json_defaults( string $block_name, array $attributes ): array {
		// Applied to every block. Gutenberg fills defaults in when it parses a
		// block, so save() always sees them; a generator that did not would
		// omit markup the editor then expects. Fifty Fifty is the clearest
		// case: its align defaults to "full" and its focalPoint to the centre,
		// so save() always emits `alignfull` and an object-position, while the
		// generated markup had neither.
		//
		// This used to run for two audited blocks only. The allowlist is gone
		// because tests/unit/ability-generated-markup.test.js now checks the
		// generated markup against the real save(), which is the audit.
		$registry   = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );

		if ( ! $block_type || empty( $block_type->attributes ) ) {
			return $attributes;
		}

		// block.json is consulted as well as the registry, and wins on defaults.
		// WordPress re-registers `style` (and the other support-backed
		// attributes) on the PHP side as a bare `{"type":"object"}`, dropping
		// the default block.json declares — while the JavaScript registration
		// keeps it. Section declares its page padding that way, so reading only
		// the registry meant save() emitted the padding and the serializer did
		// not, and every inserted Section was quietly migrated by a deprecation
		// when the editor opened it.
		$declared = Block_Schema_Loader::get_block_json( $block_name )['attributes'] ?? array();

		foreach ( $block_type->attributes as $attr_name => $attr_def ) {
			if ( array_key_exists( $attr_name, $attributes ) ) {
				continue;
			}

			// `style` is deliberately excluded. Whether its default reaches the
			// markup depends on whether the block's save() serializes block
			// supports at all: Section's padding default does, Modal's border
			// default does not (its save builds the element itself). Applying it
			// blanket-wise fixed one and broke the other, so the blocks whose
			// save() does serialize it seed the default in their own case below.
			if ( 'style' !== $attr_name && array_key_exists( 'default', $declared[ $attr_name ] ?? array() ) ) {
				$attributes[ $attr_name ] = $declared[ $attr_name ]['default'];
				continue;
			}

			if ( ! array_key_exists( 'default', $attr_def ) ) {
				continue;
			}

			$attributes[ $attr_name ] = $attr_def['default'];
		}

		return $attributes;
	}

	/**
	 * Strip attributes that match their block.json defaults.
	 *
	 * WordPress's block serialization omits attributes that equal the
	 * registered default. This method mirrors that behavior so inserted
	 * blocks produce the same comment markup as the editor.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed> Attributes with defaults removed.
	 */
	private static function strip_default_attributes( string $block_name, array $attributes ): array {
		$registry   = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );

		if ( ! $block_type || empty( $block_type->attributes ) ) {
			return $attributes;
		}

		foreach ( $block_type->attributes as $attr_name => $attr_def ) {
			if ( ! array_key_exists( $attr_name, $attributes ) ) {
				continue;
			}

			// An attribute with a `source` is read back out of the markup, so
			// WordPress never writes it into the block comment. Serializing one
			// there produces a comment save() would not, and the block is
			// invalid on open.
			if ( ! empty( $attr_def['source'] ) ) {
				unset( $attributes[ $attr_name ] );
				continue;
			}

			if ( ! array_key_exists( 'default', $attr_def ) ) {
				continue;
			}
			// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- intentional loose comparison for type-coerced defaults.
			if ( $attributes[ $attr_name ] == $attr_def['default'] ) {
				unset( $attributes[ $attr_name ] );
			}
		}

		return $attributes;
	}

	/**
	 * Convert CSS var() syntax to WordPress shorthand for block comment serialization.
	 *
	 * WordPress stores preset values as `var:preset|spacing|60` in block comments,
	 * which gets converted to `var(--wp--preset--spacing--60)` at render time.
	 *
	 * @param string $value CSS value that may contain var(--wp--preset--*) syntax.
	 * @return string Converted value using WordPress shorthand, or original value.
	 */
	private static function css_var_to_wp_shorthand( string $value ): string {
		if ( preg_match( '/^var\(--wp--preset--([a-zA-Z]+)--(.+)\)$/', $value, $matches ) ) {
			return 'var:preset|' . $matches[1] . '|' . $matches[2];
		}
		return $value;
	}

	/**
	 * Render a float the way JavaScript's String() would.
	 *
	 * @param float $value Value to format.
	 * @return string Formatted number.
	 */
	private static function format_js_number( float $value ): string {
		// JSON/JS print 0.8 as "0.8" and 1 as "1"; PHP's default float cast
		// would give "0.8" but also "1" for 1.0, which matches. Trailing zeros
		// are trimmed so 0.50 does not serialize differently from save().
		$formatted = rtrim( rtrim( sprintf( '%.10F', $value ), '0' ), '.' );

		return '' === $formatted ? '0' : $formatted;
	}

	/**
	 * Clamp a value into a range, falling back when it is not a finite number.
	 *
	 * Mirrors the clamp() helpers in the Text Path save path.
	 *
	 * @param mixed     $value    Value to clamp.
	 * @param int|float $minimum  Lower bound.
	 * @param int|float $maximum  Upper bound.
	 * @param int|float $fallback Value used when $value is not numeric.
	 * @return int|float Clamped value.
	 */
	private static function clamp_number( $value, $minimum, $maximum, $fallback ) {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return self::numeric_attribute( max( $minimum, min( $maximum, (float) $value ) ) );
	}

	/**
	 * Filter a Text Path colour through the same allowlist save() applies.
	 *
	 * @param mixed $color Colour value.
	 * @return string The colour, or an empty string when it is not allowed.
	 */
	private static function safe_text_path_color( $color ): string {
		return self::safe_hotspot_color( $color );
	}

	/**
	 * Filter a Text Path URL through the same allowlist save() applies.
	 *
	 * Mirrors getSafeTextPathUrl(): http, https, mailto, tel, and root-relative
	 * or fragment URLs.
	 *
	 * @param mixed $url URL value.
	 * @return string The URL, or an empty string when it is not allowed.
	 */
	private static function safe_text_path_url( $url ): string {
		if ( ! is_string( $url ) ) {
			return '';
		}

		$trimmed = trim( $url );

		return preg_match( '#^(?:https?:|mailto:|tel:|/|\#)#i', $trimmed ) ? $trimmed : '';
	}

	/**
	 * Resolve Text Path shape data.
	 *
	 * Mirrors getTextPathData() in src/utils/svg-paths.js for the built-in
	 * shapes. `custom` is not resolved here - it is refused before serialization.
	 *
	 * @param string $path_type Shape slug.
	 * @param mixed  $arc_size  Arc size, used only by the arc shape.
	 * @return array{viewBox: string, d: string} Shape data.
	 */
	private static function get_text_path_data( string $path_type, $arc_size ): array {
		$shapes = array(
			'wave'   => array(
				'viewBox' => '0 0 1000 200',
				'd'       => 'M 0 100 C 250 0 750 200 1000 100',
			),
			'arc'    => array(
				'viewBox' => '0 0 1000 200',
				'd'       => 'M 0 200 Q 500 0 1000 200',
			),
			'circle' => array(
				'viewBox' => '0 0 1000 1000',
				'd'       => 'M 500 0 A 500 500 0 1 1 499.9 0',
			),
			'line'   => array(
				'viewBox' => '0 0 1000 200',
				'd'       => 'M 0 100 L 1000 100',
			),
			'oval'   => array(
				'viewBox' => '0 0 1000 500',
				'd'       => 'M 500 0 A 500 250 0 1 1 499.9 0',
			),
			'spiral' => array(
				'viewBox' => '0 0 1000 1000',
				'd'       => 'M 500 500 C 500 250 850 250 850 500 C 850 850 150 850 150 500 C 150 50 950 50 950 500',
			),
		);

		if ( 'arc' === $path_type ) {
			// getTextPathArcSize(): blank means 100, otherwise clamp and round.
			$size = ( null === $arc_size || '' === $arc_size || ! is_numeric( $arc_size ) )
				? 100
				: (int) round( max( 0, min( 100, (float) $arc_size ) ) );

			return array(
				'viewBox' => $shapes['arc']['viewBox'],
				'd'       => 'M 0 200 Q 500 ' . ( 200 - $size * 2 ) . ' 1000 200',
			);
		}

		return $shapes[ $path_type ] ?? $shapes['wave'];
	}

	/**
	 * The alignment class useBlockProps.save() would add, if any.
	 *
	 * Mirrors core's addAssignedAlign: the class is emitted only when the value
	 * is one the block actually supports. Driving it off the registered supports
	 * rather than a hardcoded wide/full pair matters for blocks that allow more
	 * (card and accordion accept left/center/right too), where a hardcoded list
	 * silently drops the class and the block fails validation.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Alignment class, or an empty string.
	 */
	private static function align_class( string $block_name, array $attributes ): string {
		$align = isset( $attributes['align'] ) ? (string) $attributes['align'] : '';
		if ( '' === $align ) {
			return '';
		}

		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		$support    = $block_type->supports['align'] ?? false;

		if ( true === $support ) {
			$valid = array( 'left', 'center', 'right', 'wide', 'full' );
		} elseif ( is_array( $support ) ) {
			$valid = $support;
		} else {
			return '';
		}

		return in_array( $align, $valid, true ) ? 'align' . $align : '';
	}

	/**
	 * Remove style groups the block tells WordPress not to serialize.
	 *
	 * A block can opt out of having a support written onto its root with
	 * `__experimentalSkipSerialization`, and then re-apply it to an inner
	 * element in save() — Icon Button and Modal Trigger both do this for
	 * padding, putting it on the button.
	 *
	 * apply_block_supports() honours that for CLASSES, but the Style Engine
	 * does not: it serializes whatever is in the style attribute. Passing the
	 * raw style through therefore put padding on the root that save() puts on
	 * the button, and the block failed validation. Spacing is filtered per
	 * feature, since a block may skip padding while still serializing margin.
	 *
	 * @param \WP_Block_Type|null  $block_type The block type, when registered.
	 * @param array<string, mixed> $style      The block's style attribute.
	 * @return array<string, mixed> Style with skipped groups removed.
	 */
	private static function strip_skipped_style_groups( ?\WP_Block_Type $block_type, array $style ): array {
		if ( null === $block_type || ! function_exists( 'wp_should_skip_block_supports_serialization' ) ) {
			return $style;
		}

		// Style attribute key => the support key WordPress checks it under.
		$groups = array(
			'color'      => 'color',
			'typography' => 'typography',
			'border'     => '__experimentalBorder',
			'shadow'     => 'shadow',
			'dimensions' => 'dimensions',
		);

		foreach ( $groups as $style_key => $support_key ) {
			if ( isset( $style[ $style_key ] ) && wp_should_skip_block_supports_serialization( $block_type, $support_key ) ) {
				unset( $style[ $style_key ] );
			}
		}

		if ( isset( $style['spacing'] ) && is_array( $style['spacing'] ) ) {
			foreach ( array_keys( $style['spacing'] ) as $feature ) {
				if ( wp_should_skip_block_supports_serialization( $block_type, 'spacing', (string) $feature ) ) {
					unset( $style['spacing'][ $feature ] );
				}
			}

			if ( empty( $style['spacing'] ) ) {
				unset( $style['spacing'] );
			}
		}

		return $style;
	}

	/**
	 * Padding declarations for a block that skip-serializes padding and
	 * re-applies it to an inner element.
	 *
	 * Icon Button and Modal Trigger both declare
	 * `spacing.__experimentalSkipSerialization: ["padding"]`, so WordPress puts
	 * no padding on the block root and each save() writes it onto the button
	 * instead. get_routed_visual_attributes() cannot cover this: it works from
	 * the Style Engine, and `spacing` also carries margin, which is NOT
	 * skip-serialized and must stay on the root.
	 *
	 * The two blocks differ in one respect, so the caller says which it wants:
	 * Icon Button runs each side through convertPaddingValue() (turning
	 * `var:preset|spacing|40` into a CSS var), while Modal Trigger writes the
	 * value through untouched.
	 *
	 * @param array<string, mixed> $attributes      Block attributes.
	 * @param bool                 $convert_presets Whether to resolve preset shorthand.
	 * @return array<int, string> CSS declarations, in save()'s order.
	 */
	private static function routed_padding_styles( array $attributes, bool $convert_presets ): array {
		$padding = $attributes['style']['spacing']['padding'] ?? null;

		if ( ! is_array( $padding ) ) {
			return array();
		}

		$declarations = array();

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$value = $padding[ $side ] ?? null;

			// React drops a style property whose value is undefined or an empty
			// string, and convertPaddingValue() returns undefined for a falsy
			// value, so an unset side produces no declaration either way.
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}

			$declarations[] = 'padding-' . $side . ':' .
				( $convert_presets ? self::wp_shorthand_to_css_var( $value ) : $value );
		}

		return $declarations;
	}

	/**
	 * Whether a container block renders an overlay.
	 *
	 * Mirrors the shared JS helper: an explicit overlayColor, or an
	 * `is-style-overlay-*` variation class supplying the colour from its own
	 * stylesheet. Each container adds its own `--has-overlay` marker class when
	 * this is true, and none of them emitted it.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return bool Whether the overlay marker class applies.
	 */
	private static function has_overlay( array $attributes ): bool {
		if ( ! empty( $attributes['overlayColor'] ) ) {
			return true;
		}

		$class_name = isset( $attributes['className'] ) ? (string) $attributes['className'] : '';
		foreach ( self::split_class_list( $class_name ) as $token ) {
			if ( 0 === strpos( $token, 'is-style-overlay-' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Hover and overlay custom properties the container blocks serialize.
	 *
	 * Section, Row and Grid all write the same five custom properties from the
	 * same five attributes, each only when set. None of them were emitted here,
	 * so any container given a hover or overlay colour stored markup save()
	 * would not reproduce.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<int, string> CSS declarations.
	 */
	private static function container_hover_styles( array $attributes ): array {
		$declarations = array();

		$hover_vars = array(
			'hoverBackgroundColor'       => '--dsgo-hover-bg-color',
			'hoverTextColor'             => '--dsgo-hover-text-color',
			'hoverIconBackgroundColor'   => '--dsgo-parent-hover-icon-bg',
			'hoverButtonBackgroundColor' => '--dsgo-parent-hover-button-bg',
		);

		foreach ( $hover_vars as $attribute_name => $custom_property ) {
			$colour = isset( $attributes[ $attribute_name ] ) ? (string) $attributes[ $attribute_name ] : '';
			if ( '' !== $colour ) {
				$declarations[] = $custom_property . ':' . self::convert_color_value_to_css_var( $colour );
			}
		}

		// The overlay writes its opacity alongside the colour, as one unit.
		$overlay = isset( $attributes['overlayColor'] ) ? (string) $attributes['overlayColor'] : '';
		if ( '' !== $overlay ) {
			$declarations[] = '--dsgo-overlay-color:' . self::convert_color_value_to_css_var( $overlay );
			$declarations[] = '--dsgo-overlay-opacity:0.8';
		}

		return $declarations;
	}

	/**
	 * Clamp a hotspot coordinate to 0-100 the way save() does.
	 *
	 * @param mixed $value Coordinate value.
	 * @return int|float Clamped coordinate.
	 */
	private static function clamp_hotspot_coordinate( $value ) {
		$number = is_numeric( $value ) ? (float) $value : 50;

		return self::numeric_attribute( max( 0, min( 100, $number ) ) );
	}

	/**
	 * Filter a hotspot URL through the same allowlist save() applies.
	 *
	 * Mirrors getSafeHotspotUrl(): only http, https, mailto and tel survive, so
	 * a rejected URL turns the marker into a <button> in both paths.
	 *
	 * @param mixed $url URL value.
	 * @return string The URL, or an empty string when it is not allowed.
	 */
	private static function safe_hotspot_url( $url ): string {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return '';
		}

		$trimmed = trim( $url );
		$scheme  = wp_parse_url( $trimmed, PHP_URL_SCHEME );

		if ( null === $scheme || '' === $scheme ) {
			// Relative URLs resolve against the page, matching the JS helper's
			// use of a base URL.
			return $trimmed;
		}

		return in_array( strtolower( $scheme ), array( 'http', 'https', 'mailto', 'tel' ), true ) ? $trimmed : '';
	}

	/**
	 * Filter a hotspot colour through the same allowlist save() applies.
	 *
	 * Mirrors getSafeHotspotColor() in src/blocks/hotspot-item/utils.js: a value
	 * outside the allowlist is dropped by save(), so emitting it here would
	 * produce a custom property save() never writes.
	 *
	 * @param mixed $color Colour value.
	 * @return string The colour, or an empty string when it is not allowed.
	 */
	private static function safe_hotspot_color( $color ): string {
		if ( ! is_string( $color ) ) {
			return '';
		}

		$value = trim( $color );

		$is_preset     = (bool) preg_match( '/^var:preset\|color\|[a-z0-9-]+$/i', $value );
		$is_hex        = (bool) preg_match( '/^#[0-9a-f]{3,8}$/i', $value );
		$is_functional = (bool) preg_match( '#^(?:rgb|hsl)a?\([0-9.%\s,/+-]+\)$#i', $value );

		return ( $is_preset || $is_hex || $is_functional ) ? $value : '';
	}

	/**
	 * Whether a shape size was explicitly authored.
	 *
	 * Mirrors isExplicitShapeSize() in src/utils/shape-size.js: null, zero and
	 * negatives all mean "inherit the theme token", and serializing them would
	 * write a custom property save() never emits.
	 *
	 * @param mixed $value Attribute value.
	 * @return bool Whether the value is an explicit size.
	 */
	private static function is_explicit_shape_size( $value ): bool {
		return is_numeric( $value ) && is_finite( (float) $value ) && (float) $value > 0;
	}

	/**
	 * Convert WordPress preset shorthand to a CSS custom property reference.
	 *
	 * @param string $value Value to convert.
	 * @return string Converted value.
	 */
	private static function wp_shorthand_to_css_var( string $value ): string {
		if ( preg_match( '/^var:preset\|([a-zA-Z]+)\|(.+)$/', $value, $matches ) ) {
			return 'var(--wp--preset--' . $matches[1] . '--' . $matches[2] . ')';
		}
		return $value;
	}

	/**
	 * Convert a color value to CSS var() syntax, mirroring the JS save helper.
	 *
	 * Supports WordPress preset shorthand (`var:preset|color|slug`), already-
	 * valid CSS values, and bare preset slugs such as `accent-3`.
	 *
	 * @param string $value Color value.
	 * @return string Converted CSS value.
	 */
	private static function convert_color_value_to_css_var( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strpos( $value, 'var(--' ) ) {
			return $value;
		}

		if ( 0 === strpos( $value, 'var:preset|' ) ) {
			return self::wp_shorthand_to_css_var( $value );
		}

		if ( preg_match( '/^(#|rgb|hsl|hwb|lab|lch|oklch|oklab|color\(|var\(|url\(|\d)/i', $value ) ) {
			return $value;
		}

		$css_keywords = array(
			'transparent',
			'inherit',
			'initial',
			'unset',
			'revert',
			'revert-layer',
			'currentcolor',
			'none',
			'auto',
			'normal',
		);

		if ( in_array( strtolower( $value ), $css_keywords, true ) ) {
			return $value;
		}

		return 'var(--wp--preset--color--' . $value . ')';
	}

	/**
	 * Recursively convert CSS var() syntax to WordPress shorthand in style arrays.
	 *
	 * @param array<string, mixed> $style_array Style attribute array.
	 * @return array<string, mixed> Converted style array.
	 */
	private static function convert_style_vars( array $style_array ): array {
		foreach ( $style_array as $key => $value ) {
			if ( is_array( $value ) ) {
				$style_array[ $key ] = self::convert_style_vars( $value );
			} elseif ( is_string( $value ) ) {
				$style_array[ $key ] = self::css_var_to_wp_shorthand( $value );
			}
		}
		return $style_array;
	}

	/**
	 * Extract block support classes and inline styles from the style attribute.
	 *
	 * Processes color, spacing, and other block supports into CSS classes and
	 * inline style strings, mirroring what useBlockProps.save() does in JS.
	 *
	 * @param array<string, mixed> $style Style attribute from block.
	 * @return array{classes: string[], styles: string[]} Classes and style declarations.
	 */
	private static function get_block_support_styles( array $style ): array {
		$classes = array();
		$styles  = array();

		// Color support.
		if ( ! empty( $style['color']['background'] ) ) {
			$classes[] = 'has-background';
			$styles[]  = 'background-color:' . esc_attr( $style['color']['background'] );
		}
		if ( ! empty( $style['color']['text'] ) ) {
			$classes[] = 'has-text-color';
			$styles[]  = 'color:' . esc_attr( $style['color']['text'] );
		}
		if ( ! empty( $style['color']['gradient'] ) ) {
			$classes[] = 'has-background';
			$styles[]  = 'background:' . esc_attr( $style['color']['gradient'] );
		}

		// Spacing support - padding.
		if ( ! empty( $style['spacing']['padding'] ) ) {
			$padding = $style['spacing']['padding'];
			if ( ! empty( $padding['top'] ) ) {
				$styles[] = 'padding-top:' . esc_attr( self::wp_shorthand_to_css_var( $padding['top'] ) );
			}
			if ( ! empty( $padding['right'] ) ) {
				$styles[] = 'padding-right:' . esc_attr( self::wp_shorthand_to_css_var( $padding['right'] ) );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$styles[] = 'padding-bottom:' . esc_attr( self::wp_shorthand_to_css_var( $padding['bottom'] ) );
			}
			if ( ! empty( $padding['left'] ) ) {
				$styles[] = 'padding-left:' . esc_attr( self::wp_shorthand_to_css_var( $padding['left'] ) );
			}
		}

		// Spacing support - margin.
		if ( ! empty( $style['spacing']['margin'] ) ) {
			$margin = $style['spacing']['margin'];
			if ( ! empty( $margin['top'] ) ) {
				$styles[] = 'margin-top:' . esc_attr( self::wp_shorthand_to_css_var( $margin['top'] ) );
			}
			if ( ! empty( $margin['bottom'] ) ) {
				$styles[] = 'margin-bottom:' . esc_attr( self::wp_shorthand_to_css_var( $margin['bottom'] ) );
			}
		}

		// Dimensions support.
		if ( ! empty( $style['dimensions']['minHeight'] ) ) {
			$styles[] = 'min-height:' . esc_attr( self::wp_shorthand_to_css_var( $style['dimensions']['minHeight'] ) );
		}

		return array(
			'classes' => $classes,
			'styles'  => $styles,
		);
	}

	/**
	 * Blocks that carry BOTH a save.js (static HTML saved to post content)
	 * AND a render.php (dynamic transform at display time). For insertion
	 * purposes these should be treated as authored-mode blocks — their
	 * save.js output is the canonical wrapper HTML. Without this allowlist,
	 * Block_Inserter would skip wrapper HTML generation because the render
	 * callback is non-null, producing an empty innerHTML.
	 *
	 * V2.6: slider + scroll-slides became hybrid to support item-host
	 * rendering inside designsetgo/query.
	 */
	/**
	 * Core blocks whose save() output generate_core_block_html() reproduces.
	 *
	 * Deliberately short. Every other core block's markup lives only in its
	 * JavaScript save(), so inserting one would store markup that does not
	 * match and the editor would flag it.
	 */
	/**
	 * Blocks whose save() relocates block-support classes and styles from the
	 * block root onto an inner element, keyed by that element's class.
	 *
	 * Modal's save() calls transferStylesToContent(), which moves everything
	 * useBlockProps.save() produced onto the content div. Injecting on the root
	 * for one of these emits classes save() never puts there.
	 */
	private const SUPPORTS_ON_INNER_ELEMENT = array(
		'designsetgo/modal' => 'dsgo-modal__content',
	);

	private const SERIALIZABLE_CORE_BLOCKS = array(
		'core/heading',
		'core/paragraph',
	);

	private const HYBRID_BLOCKS = array(
		'designsetgo/slider',
		'designsetgo/scroll-slides',
		// The child too: its save.js emits the .dsgo-scroll-slide wrapper the
		// frontend queries for. Treated as purely dynamic, its children were
		// stored as bare block comments around their content, so the panel
		// wrapper never existed and slide navigation never initialised.
		'designsetgo/scroll-slide',
		// Dynamic, but their save() emits a wrapper div that must persist so
		// WordPress keeps the per-item template blocks inside it.
		'designsetgo/query',
		'designsetgo/query-results',
		'designsetgo/query-no-results',
	);

	/**
	 * Check if a block is dynamic (has a render callback).
	 *
	 * Dynamic blocks are rendered server-side via PHP and should not have
	 * wrapper HTML generated during insertion. Hybrid blocks (see
	 * HYBRID_BLOCKS) return false here because their save.js output is still
	 * authoritative at insertion time.
	 *
	 * @param string $block_name Block name (e.g., 'designsetgo/section').
	 * @return bool True if block has a render callback, false otherwise.
	 */
	private static function is_dynamic_block( string $block_name ): bool {
		if ( in_array( $block_name, self::HYBRID_BLOCKS, true ) ) {
			return false;
		}

		$registry   = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );

		if ( ! $block_type ) {
			return false;
		}

		// Check if block has a render callback.
		return null !== $block_type->render_callback;
	}

	/**
	 * Sanitize block attributes recursively.
	 *
	 * @param array<string, mixed> $attributes Attributes to sanitize.
	 * @return array<string, mixed> Sanitized attributes.
	 */
	public static function sanitize_attributes( array $attributes ): array {
		$sanitized = array();

		foreach ( $attributes as $key => $value ) {
			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_attributes( $value );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || is_null( $value ) ) {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Validate inner blocks structure.
	 *
	 * @param array<int, array<string, mixed>> $inner_blocks Inner blocks array.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_inner_blocks( array $inner_blocks ) {
		foreach ( $inner_blocks as $index => $block ) {
			if ( ! isset( $block['name'] ) || ! is_string( $block['name'] ) ) {
				return new WP_Error(
					'designsetgo_invalid_inner_block',
					sprintf(
						/* translators: %d: Block index */
						__( 'Inner block at index %d is missing a valid name.', 'designsetgo' ),
						$index
					)
				);
			}

			if ( isset( $block['attributes'] ) && ! is_array( $block['attributes'] ) ) {
				return new WP_Error(
					'designsetgo_invalid_inner_block_attributes',
					sprintf(
						/* translators: %d: Block index */
						__( 'Inner block at index %d has invalid attributes (must be an array).', 'designsetgo' ),
						$index
					)
				);
			}

			if ( isset( $block['innerBlocks'] ) && ! is_array( $block['innerBlocks'] ) ) {
				return new WP_Error(
					'designsetgo_invalid_nested_blocks',
					sprintf(
						/* translators: %d: Block index */
						__( 'Inner block at index %d has invalid innerBlocks (must be an array).', 'designsetgo' ),
						$index
					)
				);
			}

			// Recursively validate nested inner blocks.
			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$nested_validation = self::validate_inner_blocks( $block['innerBlocks'] );
				if ( is_wp_error( $nested_validation ) ) {
					return $nested_validation;
				}
			}
		}

		return true;
	}

	/**
	 * Get default common input schema properties.
	 *
	 * @return array<string, mixed> Common schema properties.
	 */
	public static function get_common_input_schema(): array {
		return array(
			'post_id'  => array(
				'type'        => 'integer',
				'description' => __( 'Target post ID', 'designsetgo' ),
			),
			'position' => array(
				'type'        => 'integer',
				'description' => __( 'Block position (0 = prepend, -1 = append, or specific index)', 'designsetgo' ),
				'default'     => -1,
			),
		);
	}

	/**
	 * Get default output schema.
	 *
	 * @return array<string, mixed> Output schema.
	 */
	public static function get_default_output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'success'  => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the operation was successful', 'designsetgo' ),
				),
				'post_id'  => array(
					'type'        => 'integer',
					'description' => __( 'Post ID where block was inserted', 'designsetgo' ),
				),
				'block_id' => array(
					'type'        => 'string',
					'description' => __( 'Unique block identifier', 'designsetgo' ),
				),
				'position' => array(
					'type'        => 'integer',
					'description' => __( 'Position where block was inserted', 'designsetgo' ),
				),
			),
			'required'   => array( 'success' ),
		);
	}

	/**
	 * Build inner blocks array from simplified definitions.
	 *
	 * Converts a simplified block definition format into the WordPress
	 * block array format suitable for use in innerBlocks.
	 *
	 * @param array<int, array<string, mixed>> $definitions Block definitions with 'name', 'attributes', 'innerBlocks'.
	 * @return array<int, array<string, mixed>> WordPress-formatted blocks.
	 */
	public static function build_inner_blocks( array $definitions ): array {
		$blocks = array();

		foreach ( $definitions as $def ) {
			$block = array(
				'blockName'    => $def['name'] ?? 'core/paragraph',
				'attrs'        => self::sanitize_attributes( $def['attributes'] ?? array() ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);

			if ( ! empty( $def['innerBlocks'] ) ) {
				$block['innerBlocks']  = self::build_inner_blocks( $def['innerBlocks'] );
				$block['innerContent'] = array( null );
			}

			$blocks[] = $block;
		}

		return $blocks;
	}
}

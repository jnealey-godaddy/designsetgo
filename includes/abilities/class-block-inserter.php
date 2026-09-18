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

use DesignSetGo\Abilities\Serializers\Serializer_Registry;
use DesignSetGo\Abilities\Serializers\Serializer_Support;
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
	 * A top-level block name: "namespace/block-name", lowercase alphanumeric and hyphens.
	 */
	public const BLOCK_NAME_PATTERN = '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/';

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

		$new_block = parse_blocks( self::build_block_markup( $block_name, $attributes, $inner_blocks ) )[0];
		$updated   = self::write_blocks( $post, array( $new_block ), $position );
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
	 * Insert several top-level blocks into a post with one content write.
	 *
	 * All-or-nothing: every definition is checked for serializer coverage and
	 * the resulting tree is validated before the post is saved, so a failure in
	 * any block leaves the post untouched. Definitions must already be screened
	 * and sanitized (see prepare_block_definition()).
	 *
	 * @param int                              $post_id     Post ID.
	 * @param array<int, array<string, mixed>> $definitions Blocks as block_name, attributes and inner_blocks, in order.
	 * @param int                              $position    Position of the first block (-1 appends, 0 prepends, or an index).
	 * @return array<string, mixed>|WP_Error Success data or error.
	 */
	public static function insert_blocks( int $post_id, array $definitions, int $position = -1 ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'designsetgo_invalid_post',
				__( 'Post not found.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'designsetgo_permission_denied',
				__( 'You do not have permission to edit this post.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$new_blocks = array();
		foreach ( array_values( $definitions ) as $index => $definition ) {
			$coverage = self::check_serialization_coverage( $definition['block_name'], $definition['inner_blocks'] );
			if ( null !== $coverage ) {
				return new WP_Error(
					'designsetgo_unsupported_block',
					sprintf( 'blocks[%d]: %s', $index, $coverage['message'] ),
					array(
						'status'      => 400,
						'block_index' => $index,
						'problems'    => $coverage['invalid_paths'],
					)
				);
			}
			$new_blocks[] = parse_blocks(
				self::build_block_markup( $definition['block_name'], $definition['attributes'], $definition['inner_blocks'] )
			)[0];
		}

		$updated = self::write_blocks( $post, $new_blocks, $position );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return array(
			'success'  => true,
			'post_id'  => $post->ID,
			'inserted' => count( $new_blocks ),
			'position' => $position,
		);
	}

	/**
	 * Screen and sanitize one top-level block definition before it is written.
	 *
	 * Placement is checked BEFORE sanitizing: sanitization drops keys it does
	 * not recognise, so a misnamed field would be gone by the time anything
	 * looked for it. That is how a nested `block_name` used to remove every
	 * child in silence.
	 *
	 * @param string               $block_name   Validated block name.
	 * @param array<string, mixed> $attributes   Requested attributes.
	 * @param array<int, mixed>    $inner_blocks Requested inner blocks.
	 * @return array<string, mixed> The sanitized definition, or a diagnostic payload with `success` false.
	 */
	public static function prepare_block_definition( string $block_name, array $attributes, array $inner_blocks ): array {
		$placement = self::check_child_placement( $block_name, $inner_blocks, $attributes );
		if ( null !== $placement ) {
			return $placement;
		}

		return array(
			'block_name'   => $block_name,
			'attributes'   => empty( $attributes ) ? $attributes : Block_Configurator::sanitize_attributes( $attributes, $block_name ),
			'inner_blocks' => empty( $inner_blocks ) ? $inner_blocks : self::sanitize_inner_block_definitions( $inner_blocks ),
		);
	}

	/**
	 * Place parsed blocks into a post's top level and save it.
	 *
	 * @param WP_Post                          $post       Target post.
	 * @param array<int, array<string, mixed>> $new_blocks Parsed blocks, in order.
	 * @param int                              $position   -1 appends, 0 prepends, or an index.
	 * @return true|WP_Error
	 */
	private static function write_blocks( WP_Post $post, array $new_blocks, int $position ) {
		$blocks = parse_blocks( $post->post_content );

		if ( -1 === $position ) {
			array_push( $blocks, ...$new_blocks );
		} elseif ( 0 === $position ) {
			array_unshift( $blocks, ...$new_blocks );
		} else {
			array_splice( $blocks, $position, 0, $new_blocks );
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

		return true;
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
				$clean_block['attributes'] = Block_Configurator::sanitize_attributes( $block['attributes'], $name );
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
	 * for the core blocks in SERIALIZABLE_CORE_BLOCKS). Without a mirror the block serializes to a
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
			// Ask the registry rather than running a serializer and checking for
			// null. Same answer, but it is a lookup instead of markup
			// generation, and - the point of the registry - the set of
			// serializable blocks is now something a test can enumerate rather
			// than discover one block at a time.
			if ( Serializer_Registry::has( $block_name ) ) {
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

		// From the applied set three kinds are taken: the `has-*` support
		// classes, the `align*` class, and the tokens of the block's own
		// `className` attribute (custom-classname support). useBlockProps.save()
		// spreads all three onto the root, so stored markup without them fails
		// block validation the first time the editor re-saves it. Taking the
		// className tokens by intersection keeps a block that disables the
		// support faithful. Layout classes (`is-layout-*`, `wp-container-*`)
		// are render-time only and stay out.
		//
		// Alignment used to be excluded here on the grounds that "the wrapper
		// generators already emit it". Twelve of them did not: accordion,
		// countdown-timer, counter-group, form-builder, icon-button, icon-list,
		// image-accordion, modal-trigger, progress-bar, slider,
		// table-of-contents and tabs all declare `supports.align` and never
		// called align_class(), so save() wrote `alignwide` and the mirror
		// wrote nothing. Reading it from apply_block_supports() fixes the whole
		// class at once instead of patching twelve cases and waiting for the
		// thirteenth. WP_HTML_Tag_Processor::add_class() is idempotent, so the
		// blocks that DO emit it themselves are unaffected.
		$applied_classes = Serializer_Support::split_class_list( (string) ( $applied['class'] ?? '' ) );
		$custom_classes  = isset( $attributes['className'] ) && is_string( $attributes['className'] )
			? Serializer_Support::split_class_list( $attributes['className'] )
			: array();

		// Alignment always goes to the ROOT, which is where the first pass
		// below writes it. That is correct even for a block whose supports land
		// on an inner element: save() puts alignment on the outermost node
		// regardless. designsetgo/modal, the only such block today, declares
		// `supports.align: false` and so never has an alignment class at all.
		// Two destinations, because WordPress has two mechanisms.
		//
		// `blocks.getSaveContent.extraProps` props are merged onto the
		// OUTERMOST element by getSaveElement(), whatever the block's save()
		// does internally. Custom class names, the anchor id and every DSGo
		// extension class arrive that way - core registers custom-classname and
		// anchor on that same filter.
		//
		// `useBlockProps.save()` props go wherever the block chooses to spread
		// them. Most blocks put them on their root, but Modal spreads them onto
		// its inner content div, which is what SUPPORTS_ON_INNER_ELEMENT
		// records.
		//
		// Lumping the two together sent Modal's custom class, its extension
		// classes and its alignment to the content div while save() wrote them
		// on the root - eight of its sixteen matrix failures.
		$root_classes = array_values(
			array_filter(
				$applied_classes,
				static function ( $class_name ) use ( $custom_classes ) {
					if ( 0 === strpos( $class_name, 'align' ) ) {
						return true;
					}

					return in_array( $class_name, $custom_classes, true );
				}
			)
		);

		$support_classes = array_values(
			array_filter(
				$applied_classes,
				static function ( $class_name ) {
					return 0 === strpos( $class_name, 'has-' );
				}
			)
		);

		// Anchor support is the same story: save() writes the `anchor` attribute
		// as the root `id`. It cannot be read off apply_block_supports() though:
		// core only grew a PHP anchor block support in WP 7.0
		// (wp-includes/block-supports/anchor.php), so across the 6.7-6.9 range
		// this plugin also supports it reports no `id` at all and the attribute
		// is the only source. Read it directly, gated on the same block support
		// core's own implementation checks so a block that does not support
		// anchors stays faithful to its save() output.
		$anchor_id = '';
		if ( null !== $block_type
			&& isset( $attributes['anchor'] )
			&& is_string( $attributes['anchor'] )
			&& '' !== $attributes['anchor']
			&& block_has_support( $block_type, array( 'anchor' ) )
		) {
			$anchor_id = $attributes['anchor'];
		}

		// Editor extensions (hover effects, text reveal, expanding background)
		// add their own classes/styles/data attributes onto the SAME root
		// element via `blocks.getSaveContent.extraProps` - the identical
		// mechanism useBlockProps.save() uses for the `has-*` support classes
		// above, so they are merged in the same pass.
		$extension_props = self::get_extension_save_props( $block_name, $attributes );

		// Extension classes follow the same rule the block itself applies. Modal
		// transfers every `has-*` class onto its content div and keeps the rest
		// on the wrapper (utils/style-transfer.js), so `has-dsgo-animation` goes
		// to the content while `dsgo-hide-mobile` and a custom class stay on the
		// root. For every other block the two elements are the same node and the
		// split makes no difference.
		if ( isset( self::SUPPORTS_ON_INNER_ELEMENT[ $block_name ] ) ) {
			foreach ( $extension_props['classes'] as $extension_class ) {
				if ( 0 === strpos( $extension_class, 'has-' ) ) {
					$support_classes[] = $extension_class;
					continue;
				}

				$root_classes[] = $extension_class;
			}
		} else {
			// Same element either way, so splitting would only reorder the
			// class list - harmless for validation, which compares classes as
			// a set, but it churns the committed fixtures for no reason.
			$root_classes = array_merge( $root_classes, $extension_props['classes'] );
		}

		$declarations = array();
		if ( ! empty( $attributes['style'] ) && is_array( $attributes['style'] ) && function_exists( 'wp_style_engine_get_styles' ) ) {
			$engine       = wp_style_engine_get_styles( self::strip_skipped_style_groups( $block_type, $attributes['style'] ) );
			$declarations = $engine['declarations'] ?? array();
		}
		$declarations = array_merge( $declarations, $extension_props['styles'] );

		// Pass one: everything getSaveElement() puts on the outermost element.
		// Skipped entirely when there is nothing to add, so the common case
		// costs no extra parse.
		if ( ! empty( $root_classes ) || '' !== $anchor_id || ! empty( $extension_props['data'] ) ) {
			$root_processor = new \WP_HTML_Tag_Processor( $html );

			if ( $root_processor->next_tag() ) {
				foreach ( $root_classes as $class_name ) {
					$root_processor->add_class( $class_name );
				}

				if ( '' !== $anchor_id && null === $root_processor->get_attribute( 'id' ) ) {
					$root_processor->set_attribute( 'id', $anchor_id );
				}

				foreach ( $extension_props['data'] as $data_name => $data_value ) {
					$root_processor->set_attribute( $data_name, $data_value );
				}

				$html = $root_processor->get_updated_html();
			}
		}

		$processor = new \WP_HTML_Tag_Processor( $html );

		// Pass two: the useBlockProps.save() props. Most blocks carry these on
		// the root too. A few move them to an inner element in save() - Modal
		// transfers them onto its content div - so putting them on the root
		// there is markup save() never emits.
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
	 * Editor-extension save-time classes, inline style declarations, and data
	 * attributes for a block.
	 *
	 * Hover effects, text reveal, expanding background, and SVG patterns are
	 * not block supports - each is an independent extension that hooks
	 * `blocks.getSaveContent.extraProps` directly (see their `src/extensions/*`
	 * source). That is the SAME filter useBlockProps.save() reads to merge in
	 * the `has-*` support classes apply_block_support_attributes() already
	 * reproduces, so every extension's additions land on the identical root
	 * element in the identical pass - which is why this is called from, and
	 * merged inside, that method rather than kept separate.
	 *
	 * Parallax and the SVG pattern's actual generated image are intentionally
	 * NOT reproduced here: those inject their output
	 * at render time via a `render_block` filter, keyed off attributes or
	 * (for SVG patterns) the very data attribute this method writes. Nothing
	 * here should duplicate that.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array{classes: array<int, string>, styles: array<string, string>, data: array<string, string>} Additions to merge onto the root element.
	 */
	private static function get_extension_save_props( string $block_name, array $attributes ): array {
		$classes = array();
		$styles  = array();
		$data    = array();

		// Static blocks need the same animation props as the editor save filter.
		// Dynamic blocks use this shared helper in their render path instead.
		if ( function_exists( 'designsetgo_get_animation_parts' ) ) {
			$animation = \designsetgo_get_animation_parts( $attributes );
			$classes   = array_merge( $classes, $animation['classes'] );
			$data      = array_merge( $data, $animation['attrs'] );
		}

		// Mirror the max-width extension - src/extensions/max-width/index.js
		// (applyMaxWidthStyles). This used to be scoped to three text blocks,
		// but the extension's config is `'blocks' => 'all'` minus an exclusion
		// list, so the attribute exists on nearly every block and save() writes
		// the class wherever it is set. Scoping the mirror more narrowly than
		// the extension meant an agent could set dsgoMaxWidth on any other
		// block and get stored markup save() disagreed with.
		if ( ! self::is_block_excluded_from_extensions( $block_name )
			&& ! in_array( $block_name, self::MAX_WIDTH_EXCLUDED_BLOCKS, true )
			&& ! empty( $attributes['dsgoMaxWidth'] ) && is_string( $attributes['dsgoMaxWidth'] )
		) {
			$classes[]           = 'dsgo-has-max-width';
			$styles['max-width'] = $attributes['dsgoMaxWidth'];

			// The JS checks textAlign OR align; reading one with a fallback to
			// the other is not the same test. textAlign 'center' with align
			// 'left' takes the left branch there and the centred branch here.
			$text_align = isset( $attributes['textAlign'] ) ? (string) $attributes['textAlign'] : '';
			$align      = isset( $attributes['align'] ) ? (string) $attributes['align'] : '';

			if ( 'left' === $text_align || 'left' === $align ) {
				$styles['margin-left']  = '0';
				$styles['margin-right'] = 'auto';
			} elseif ( 'right' === $text_align || 'right' === $align ) {
				$styles['margin-left']  = 'auto';
				$styles['margin-right'] = '0';
			} else {
				$styles['margin-left']  = 'auto';
				$styles['margin-right'] = 'auto';
			}
		}

		// Mirror the responsive visibility extension -
		// src/extensions/responsive/index.js (applyResponsiveVisibilityClasses).
		// Order matches the JS push order; WordPress compares class attributes
		// as a set, so this is for readability rather than correctness.
		if ( ! self::is_block_excluded_from_extensions( $block_name ) ) {
			if ( ! empty( $attributes['dsgoHideOnDesktop'] ) ) {
				$classes[] = 'dsgo-hide-desktop';
			}
			if ( ! empty( $attributes['dsgoHideOnTablet'] ) ) {
				$classes[] = 'dsgo-hide-tablet';
			}
			if ( ! empty( $attributes['dsgoHideOnMobile'] ) ) {
				$classes[] = 'dsgo-hide-mobile';
			}
		}

		// Mirror the reveal-control extension -
		// src/extensions/reveal-control/index.js (addRevealClasses). Note it
		// applies no shouldExtendBlock() gate, so neither does this.
		if ( in_array( $block_name, self::REVEAL_CONTAINER_BLOCKS, true ) && ! empty( $attributes['enableRevealOnHover'] ) ) {
			$classes[]                       = 'dsgo-has-reveal';
			$reveal_animation                = isset( $attributes['revealAnimationType'] ) ? (string) $attributes['revealAnimationType'] : '';
			$data['data-reveal-animation']   = '' !== $reveal_animation ? $reveal_animation : 'fade';
		} elseif ( ! empty( $attributes['dsgoRevealOnHover'] ) ) {
			$classes[] = 'dsgo-reveal-item';
		}

		// Mirror the background-video extension -
		// src/extensions/background-video/index.js
		// (addBackgroundVideoSaveProps). Gated by attribute registration alone:
		// the extension config allowlists the container blocks, so the
		// attribute only exists where it applies, and the JS applies no
		// further block check.
		if ( ! empty( $attributes['dsgoVideoUrl'] ) && is_string( $attributes['dsgoVideoUrl'] ) ) {
			$classes[]                           = 'dsgo-has-video-background';
			$data['data-video-url']              = $attributes['dsgoVideoUrl'];
			$data['data-video-poster']           = isset( $attributes['dsgoVideoPoster'] ) ? (string) $attributes['dsgoVideoPoster'] : '';
			$data['data-video-muted']            = empty( $attributes['dsgoVideoMuted'] ) ? 'false' : 'true';
			$data['data-video-loop']             = empty( $attributes['dsgoVideoLoop'] ) ? 'false' : 'true';
			$data['data-video-autoplay']         = empty( $attributes['dsgoVideoAutoplay'] ) ? 'false' : 'true';
			$data['data-video-mobile-hide']      = empty( $attributes['dsgoVideoMobileHide'] ) ? 'false' : 'true';
			$data['data-video-overlay-color']    = Serializer_Support::convert_color_value_to_css_var(
				isset( $attributes['dsgoVideoOverlayColor'] ) ? (string) $attributes['dsgoVideoOverlayColor'] : ''
			);
		}

		// Mirror the clickable-group extension -
		// src/extensions/clickable-group/index.js (addLinkSaveProps). Scoped to
		// the same four container blocks the JS lists, and gated on a non-blank
		// URL the same way.
		if ( in_array( $block_name, self::CLICKABLE_GROUP_BLOCKS, true )
			&& isset( $attributes['dsgoLinkUrl'] )
			&& is_string( $attributes['dsgoLinkUrl'] )
			&& '' !== trim( $attributes['dsgoLinkUrl'] )
		) {
			$classes[]                 = 'dsgo-clickable';
			$data['data-link-url']     = $attributes['dsgoLinkUrl'];

			// The JS writes the literal '_blank' for ANY truthy target, not the
			// attribute's own value.
			if ( ! empty( $attributes['dsgoLinkTarget'] ) ) {
				$data['data-link-target'] = '_blank';
			}
			if ( ! empty( $attributes['dsgoLinkRel'] ) ) {
				$data['data-link-rel'] = (string) $attributes['dsgoLinkRel'];
			}
		}

		// Mirror the custom-CSS extension - src/extensions/custom-css/index.js
		// (applyCustomCSSClass). The class carries a hash of the CSS and the
		// block name. It looks like something PHP could not reproduce, but the
		// JS hash is the ordinary 32-bit string hash, not a random id, so it
		// is fully deterministic - see js_hash_code().
		if ( ! self::is_block_excluded_from_extensions( $block_name )
			&& ! in_array( $block_name, self::CUSTOM_CSS_EXCLUDED_BLOCKS, true )
			&& ! empty( $attributes['dsgoCustomCSS'] ) && is_string( $attributes['dsgoCustomCSS'] )
		) {
			$classes[] = 'dsgo-custom-css-' . Serializer_Support::js_hash_code( $attributes['dsgoCustomCSS'] . $block_name );
		}

		// Text reveal - src/extensions/text-reveal/editor.js
		// (addTextRevealSaveProps). Applies only to core/paragraph and
		// core/heading (includes/extension-configs/text-reveal.php), both of
		// which this ability can insert.
		if ( in_array( $block_name, array( 'core/paragraph', 'core/heading' ), true )
			&& ! empty( $attributes['dsgoTextRevealEnabled'] )
		) {
			$classes[] = 'has-dsgo-text-reveal';

			$data['data-dsgo-text-reveal-enabled']    = 'true';
			$data['data-dsgo-text-reveal-color']      = Serializer_Support::convert_color_value_to_css_var( (string) ( $attributes['dsgoTextRevealColor'] ?? '' ) );
			$data['data-dsgo-text-reveal-split-mode'] = ! empty( $attributes['dsgoTextRevealSplitMode'] ) ? (string) $attributes['dsgoTextRevealSplitMode'] : 'word';
			$data['data-dsgo-text-reveal-transition'] = Serializer_Support::js_truthy_numeric( $attributes['dsgoTextRevealTransition'] ?? null, '150' );

			// Only emitted when it differs from the default 'color': content
			// saved before this attribute existed carries none in its stored
			// HTML, and emitting it unconditionally would fail validation on
			// every existing text-reveal block. The frontend already falls
			// back to 'color' when the attribute is absent.
			$effect = isset( $attributes['dsgoTextRevealEffect'] ) ? (string) $attributes['dsgoTextRevealEffect'] : '';
			if ( '' !== $effect && 'color' !== $effect ) {
				$data['data-dsgo-text-reveal-effect'] = $effect;
			}
		}

		// Expanding background - src/extensions/expanding-background/editor.js
		// (addExpandingBackgroundSaveProps). Applies to designsetgo/section and
		// core/group (includes/extension-configs/expanding-background.php);
		// only the former can be inserted by this ability today (core/group is
		// a static block absent from SERIALIZABLE_CORE_BLOCKS, so
		// get_serialization_gap() refuses to insert it at all).
		if ( in_array( $block_name, array( 'core/group', 'designsetgo/section' ), true )
			&& ! empty( $attributes['dsgoExpandingBgEnabled'] )
		) {
			$classes[] = 'has-dsgo-expanding-background';

			$raw_color   = (string) ( $attributes['dsgoExpandingBgColor'] ?? '' );
			$style_color = Serializer_Support::convert_color_value_to_css_var( $raw_color );

			$styles['--dsgo-expanding-bg-color'] = '' !== $style_color ? $style_color : '#e8e8e8';

			$data['data-dsgo-expanding-bg-enabled']          = 'true';
			$data['data-dsgo-expanding-bg-color']            = $style_color;
			$data['data-dsgo-expanding-bg-initial-size']     = Serializer_Support::js_truthy_numeric( $attributes['dsgoExpandingBgInitialSize'] ?? null, '' );
			$data['data-dsgo-expanding-bg-blur']             = Serializer_Support::js_truthy_numeric( $attributes['dsgoExpandingBgBlur'] ?? null, '' );
			$data['data-dsgo-expanding-bg-speed']            = Serializer_Support::js_truthy_numeric( $attributes['dsgoExpandingBgSpeed'] ?? null, '' );
			$data['data-dsgo-expanding-bg-trigger-offset']   = Serializer_Support::js_truthy_numeric( $attributes['dsgoExpandingBgTriggerOffset'] ?? null, '' );
			$data['data-dsgo-expanding-bg-completion-point'] = Serializer_Support::js_truthy_numeric( $attributes['dsgoExpandingBgCompletionPoint'] ?? null, '' );
		}

		// SVG patterns - src/extensions/svg-patterns/editor.js
		// (addSvgPatternSaveProps). Applies to designsetgo/section and
		// core/group (includes/extension-configs/svg-patterns.php); only the
		// former is reachable today, same as expanding background. The SVG
		// itself is generated at render time by SVG_Pattern_Renderer from
		// these data attributes (render_block filter, gated on
		// `data-dsgo-svg-pattern` already being present) - only the save-time
		// marker class and data attributes belong here.
		if ( in_array( $block_name, array( 'core/group', 'designsetgo/section' ), true )
			&& ! empty( $attributes['dsgoSvgPatternEnabled'] )
		) {
			$pattern_type = isset( $attributes['dsgoSvgPatternType'] ) ? (string) $attributes['dsgoSvgPatternType'] : '';
			$is_inherit   = 'inherit' === $pattern_type;

			$known_patterns = function_exists( 'designsetgo_get_svg_pattern_data' ) ? \designsetgo_get_svg_pattern_data() : array();
			$is_known       = is_array( $known_patterns ) && isset( $known_patterns[ $pattern_type ] );

			if ( '' !== $pattern_type && ( $is_inherit || $is_known ) ) {
				$classes[] = 'has-dsgo-svg-pattern';

				if ( $is_inherit ) {
					$data['data-dsgo-svg-pattern'] = 'inherit';
				} else {
					$safe_opacity = is_numeric( $attributes['dsgoSvgPatternOpacity'] ?? null ) ? (float) $attributes['dsgoSvgPatternOpacity'] : 0.4;
					$safe_scale   = is_numeric( $attributes['dsgoSvgPatternScale'] ?? null ) ? (float) $attributes['dsgoSvgPatternScale'] : 1.0;

					$data['data-dsgo-svg-pattern'] = $pattern_type;

					// Omitted entirely when unset (the server-side renderer
					// falls back to its own default color), mirroring React's
					// omission of a prop whose value is `undefined` - as
					// opposed to the expanding-background data attributes
					// above, which fall back to an emitted empty string.
					$pattern_color = Serializer_Support::convert_color_value_to_css_var( (string) ( $attributes['dsgoSvgPatternColor'] ?? '' ) );
					if ( '' !== $pattern_color ) {
						$data['data-dsgo-svg-pattern-color'] = $pattern_color;
					}

					$data['data-dsgo-svg-pattern-opacity'] = Serializer_Support::format_js_number( $safe_opacity );
					$data['data-dsgo-svg-pattern-scale']   = Serializer_Support::format_js_number( $safe_scale );
				}
			}
		}

		// Hover effects - src/extensions/hover-effects/index.js
		// (addHoverEffectSaveProps). Applies to ten core blocks
		// (src/extensions/hover-effects/constants.js SUPPORTED_BLOCKS), one of
		// which - core/image - IS now in SERIALIZABLE_CORE_BLOCKS, so this
		// branch is no longer unreachable by block name alone.
		//
		// What still keeps it dormant is narrower: this extension has no config
		// file in includes/extension-configs/ at all, so dsgoHoverEffect is
		// never registered server-side and no caller can get a value past the
		// ability's input schema. If that config ever lands, this branch goes
		// live for core/image and its output needs a fixture payload proving it
		// matches addHoverEffectSaveProps.
		//
		// Kept generic and keyed off the real block list so it activates the
		// day inserter coverage for the rest of them lands, without needing to
		// be revisited.
		$hover_effect_blocks = array(
			'core/group',
			'core/cover',
			'core/column',
			'core/columns',
			'core/image',
			'core/button',
			'core/buttons',
			'core/media-text',
			'core/post-template',
			'core/query',
		);
		$hover_effect_values = array( 'lift', 'sink', 'grow', 'shrink', 'tilt', 'glow' );

		if ( in_array( $block_name, $hover_effect_blocks, true ) ) {
			$effect = isset( $attributes['dsgoHoverEffect'] ) ? (string) $attributes['dsgoHoverEffect'] : '';
			if ( in_array( $effect, $hover_effect_values, true ) ) {
				$classes[] = 'dsgo-hover-effect';
				$classes[] = 'dsgo-hover-effect--' . $effect;
			}
		}

		// Grid mobile order - src/extensions/grid-mobile-order/index.js
		// (applyMobileOrderSaveProps). Registered for every block
		// (includes/extension-configs/grid-mobile-order.php, blocks => 'all')
		// minus the user-configured exclusions. save.js clamps the value to
		// 0..10 and writes the custom property only when it differs from the
		// default 1, so a section ordered first on phones (0) must carry it or
		// the editor rejects the stored HTML.
		if ( isset( $attributes['dsgoMobileOrder'] )
			&& is_numeric( $attributes['dsgoMobileOrder'] )
			&& ! self::is_block_excluded_from_extensions( $block_name )
		) {
			$mobile_order = max( 0.0, min( 10.0, (float) $attributes['dsgoMobileOrder'] ) );
			if ( 1.0 !== $mobile_order ) {
				$styles['--dsgo-mobile-order'] = Serializer_Support::format_js_number( $mobile_order );
			}
		}

		// Grid span - src/extensions/grid-span/index.js (applyGridSpanStyles).
		// Registered for every block (includes/extension-configs/grid-span.php);
		// save() writes grid-column / grid-row for a span above 1 whatever the
		// parent, so a spanning grid item stored without them renders one track
		// wide and fails validation on open.
		if ( ! self::is_block_excluded_from_extensions( $block_name ) ) {
			$spans = array(
				'dsgoColumnSpan' => 'grid-column',
				'dsgoRowSpan'    => 'grid-row',
			);
			foreach ( $spans as $span_attribute => $span_property ) {
				if ( isset( $attributes[ $span_attribute ] ) && is_numeric( $attributes[ $span_attribute ] ) && (float) $attributes[ $span_attribute ] > 1 ) {
					$styles[ $span_property ] = 'span ' . Serializer_Support::format_js_number( (float) $attributes[ $span_attribute ] );
				}
			}
		}

		return array(
			'classes' => $classes,
			'styles'  => $styles,
			'data'    => $data,
		);
	}

	/**
	 * Mirror the editor's shouldExtendBlock(): a block type (or its whole
	 * namespace via `namespace/*`) that the user excluded from extensions in
	 * the plugin settings never receives extension save props.
	 *
	 * @param string $block_name Block name.
	 * @return bool Whether extensions are switched off for this block type.
	 */
	private static function is_block_excluded_from_extensions( string $block_name ): bool {
		$settings = get_option( 'designsetgo_settings', array() );
		$excluded = isset( $settings['excluded_blocks'] ) ? (array) $settings['excluded_blocks'] : array();
		if ( empty( $excluded ) ) {
			return false;
		}
		if ( in_array( $block_name, $excluded, true ) ) {
			return true;
		}
		$namespace = strtok( $block_name, '/' );

		return in_array( $namespace . '/*', $excluded, true );
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
	 * Only core/navigation qualifies: its save() is InnerBlocks.Content.
	 * Other core wrapper generation is gated on the
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
		// Navigation saves InnerBlocks.Content without a wrapper; core renders its nav element.
		if ( 'core/navigation' === $block_name ) {
			return true;
		}

		// List, list item and quote wrap their children in markup this inserter reproduces.
		if ( in_array( $block_name, self::CORE_WRAPPER_BLOCKS, true ) ) {
			return true;
		}

		if ( 0 !== strpos( $block_name, 'designsetgo/' ) ) {
			return false;
		}

		if ( self::is_dynamic_block( $block_name ) ) {
			return true;
		}

		return Serializer_Registry::has( $block_name );
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
			'designsetgo/counter'          => array(
				'showIcon' => __( 'a counter icon is not supported by this inserter; save() inlines the icon\'s SVG from a JavaScript library that has no PHP equivalent, and an approximation would be invalid content. Add the counter in the editor, or leave showIcon off.', 'designsetgo' ),
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
				// A free-form CSS value is written straight into an inline style
				// declaration, so anything that could end that declaration (or
				// the attribute) is refused rather than escaped: escaping would
				// store markup save() never produces. Same set as
				// sanitizeColumnTemplate() in src/blocks/grid/grid-columns.js.
				if ( 'designsetgo/grid' === $block_name && 'columnTemplate' === $attribute && preg_match( '/[;{}<>"\']|url\s*\(/i', $value ) ) {
					$problems[] = array(
						'path'   => $block_path,
						'block'  => $block_name,
						'reason' => sprintf(
							/* translators: %s: attribute name */
							__( '%s: must be a plain grid-template-columns value; it cannot contain ; { } < > quotes or url().', 'designsetgo' ),
							$attribute
						),
					);
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
			$attrs['style'] = Serializer_Support::convert_style_vars( $attrs['style'] );
		}
		if ( 'core/image' === $block_name ) {
			$image_html = self::generate_core_image_html( $attrs );
			// Sourced attributes are read back from the markup, so save() keeps them out of the comment.
			// They also leave before block supports run: a render never passes them, and the rich-text
			// caption type is not one rest_validate_value_from_schema() accepts.
			foreach ( self::CORE_IMAGE_SOURCED_ATTRIBUTES as $sourced ) {
				unset( $attrs[ $sourced ] );
			}
			$innerHTML      = self::apply_block_support_attributes( $image_html, $block_name, $attrs );
			$innerContent[] = $innerHTML;
		} elseif ( isset( $attrs['content'] ) && 0 === strpos( $block_name, 'core/' ) && ! in_array( $block_name, self::CORE_WRAPPER_BLOCKS, true ) ) {
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
					// Match InnerBlocks.save(): sibling segments are separated by whitespace.
					if ( 'designsetgo/advanced-heading' === $block_name && ! empty( $parsed_inners ) ) {
						$innerContent[] = "\n\n";
					}
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

		// Core wrapper blocks (list, list item, quote) mirror their save() the same
		// way: opening and closing markup as separate innerContent entries around
		// the child placeholders, with block-support classes merged onto the root.
		if ( in_array( $block_name, self::CORE_WRAPPER_BLOCKS, true ) ) {
			$wrapper_html = self::generate_core_wrapper_html( $block_name, $attrs );
			unset( $attrs['content'], $attrs['citation'] );
			$wrapper_html['opening'] = self::apply_block_support_attributes( $wrapper_html['opening'], $block_name, $attrs );
			array_unshift( $innerContent, $wrapper_html['opening'] );
			$innerContent[] = $wrapper_html['closing'];
			$innerHTML      = $wrapper_html['opening'] . $wrapper_html['closing'];
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
	 * Creates opening and closing HTML mirroring the block's save() output. The
	 * per-block implementations live in includes/abilities/serializers/, one
	 * file per block, and Serializer_Registry maps names to them.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Array with 'opening' and 'closing' keys, or null if not supported.
	 */
	public static function generate_designsetgo_wrapper_html( string $block_name, array $attributes ): ?array {
		return Serializer_Registry::wrapper( $block_name, $attributes );
	}

	/**
	 * Generate the wrapper markup of a core block whose save() surrounds InnerBlocks.Content.
	 *
	 * Mirrors block-library save.js: the list tag carries the default block class,
	 * a list item has no class support and holds its rich text before any nested
	 * list, and a quote closes with its citation after the children.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array{opening: string, closing: string} Opening and closing markup.
	 */
	private static function generate_core_wrapper_html( string $block_name, array $attributes ): array {
		switch ( $block_name ) {
			case 'core/list':
				$tag = ! empty( $attributes['ordered'] ) ? 'ol' : 'ul';
				return array(
					'opening' => '<' . $tag . ' class="wp-block-list">',
					'closing' => '</' . $tag . '>',
				);

			case 'core/list-item':
				$content = isset( $attributes['content'] ) && is_string( $attributes['content'] ) ? $attributes['content'] : '';
				return array(
					'opening' => '<li>' . wp_kses_post( $content ),
					'closing' => '</li>',
				);

			case 'core/quote':
				$citation = isset( $attributes['citation'] ) && is_string( $attributes['citation'] ) ? $attributes['citation'] : '';
				return array(
					'opening' => '<blockquote class="wp-block-quote">',
					'closing' => ( '' !== $citation ? '<cite>' . wp_kses_post( $citation ) . '</cite>' : '' ) . '</blockquote>',
				);

			default:
				return array(
					'opening' => '',
					'closing' => '',
				);
		}
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
	 * Generate the markup core/image save() produces.
	 *
	 * Mirrors block-library image/save.js (WordPress 7.1): the figure carries the
	 * alignment, size, resize and custom-border classes, while border and shadow
	 * styles skip the root and land on the img with aspect ratio, scale, focal
	 * point and dimensions. The caption follows the image or its link. Block
	 * supports that do serialize on the root (margin, className, anchor) are
	 * merged afterwards by apply_block_support_attributes().
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Figure markup.
	 */
	private static function generate_core_image_html( array $attributes ): string {
		$text   = static function ( string $key ) use ( $attributes ): string {
			return isset( $attributes[ $key ] ) && is_scalar( $attributes[ $key ] ) ? (string) $attributes[ $key ] : '';
		};
		$style  = isset( $attributes['style'] ) && is_array( $attributes['style'] ) ? $attributes['style'] : array();
		$border = isset( $style['border'] ) && is_array( $style['border'] ) ? $style['border'] : array();

		$image_styles = array();
		if ( function_exists( 'wp_style_engine_get_styles' ) ) {
			$skipped      = array_filter(
				array(
					'border' => $border,
					'shadow' => $style['shadow'] ?? null,
				)
			);
			$engine       = $skipped ? wp_style_engine_get_styles( $skipped ) : array();
			$image_styles = $engine['declarations'] ?? array();
		}
		// The PHP style engine turns a preset border color into classes, but the
		// editor's getInlineStyles() writes it inline as a CSS variable.
		$sides = array(
			''        => $border,
			'-top'    => $border['top'] ?? null,
			'-right'  => $border['right'] ?? null,
			'-bottom' => $border['bottom'] ?? null,
			'-left'   => $border['left'] ?? null,
		);
		foreach ( $sides as $side => $values ) {
			if ( is_array( $values ) && isset( $values['color'] ) && is_string( $values['color'] ) && '' !== $values['color'] ) {
				$image_styles[ 'border' . $side . '-color' ] = 0 === strpos( $values['color'], 'var:preset|' )
					? Serializer_Support::wp_shorthand_to_css_var( $values['color'] )
					: $values['color'];
			}
		}
		$has_border_styles = (bool) array_filter(
			array_keys( $image_styles ),
			static function ( $property ) {
				return 0 === strpos( $property, 'border' );
			}
		);

		$border_classes = array();
		$border_color   = $text( 'borderColor' );
		if ( '' !== $border_color || ! empty( $border['color'] ) ) {
			$border_classes[] = 'has-border-color';
		}
		if ( '' !== $border_color ) {
			$border_classes[] = 'has-' . sanitize_html_class( _wp_to_kebab_case( $border_color ) ) . '-border-color';
		}

		$scale = $text( 'scale' );
		if ( '' !== $text( 'aspectRatio' ) ) {
			$image_styles['aspect-ratio'] = $text( 'aspectRatio' );
		}
		if ( '' !== $scale ) {
			$image_styles['object-fit'] = $scale;
		}
		$focal = isset( $attributes['focalPoint'] ) && is_array( $attributes['focalPoint'] ) ? $attributes['focalPoint'] : null;
		if ( $focal && '' !== $scale ) {
			$image_styles['object-position'] = round( (float) ( $focal['x'] ?? 0.5 ) * 100 ) . '% ' . round( (float) ( $focal['y'] ?? 0.5 ) * 100 ) . '%';
		}
		$dimension = static function ( $value ): string {
			return is_numeric( $value ) && ! is_string( $value ) ? $value . 'px' : (string) $value;
		};
		$width     = $attributes['width'] ?? null;
		$height    = $attributes['height'] ?? null;
		if ( null !== $width || null !== $height ) {
			if ( null !== $width && '' !== $width ) {
				$image_styles['width'] = $dimension( $width );
			}
			$image_styles['height'] = ( null === $height || 'auto' === $height ) ? 'auto' : $dimension( $height );
		}

		$align          = $text( 'align' );
		$figure_classes = array( 'wp-block-image' );
		if ( 'none' === $align ) {
			$figure_classes[] = 'alignnone';
		} elseif ( in_array( $align, array( 'left', 'center', 'right', 'wide', 'full' ), true ) ) {
			$figure_classes[] = 'align' . $align;
		}
		if ( '' !== $text( 'sizeSlug' ) ) {
			$figure_classes[] = 'size-' . sanitize_html_class( $text( 'sizeSlug' ) );
		}
		if ( ! empty( $width ) || ! empty( $height ) ) {
			$figure_classes[] = 'is-resized';
		}
		if ( $border_classes || $has_border_styles ) {
			$figure_classes[] = 'has-custom-border';
		}

		$image_classes = $border_classes;
		if ( ! empty( $attributes['id'] ) && is_numeric( $attributes['id'] ) ) {
			$image_classes[] = 'wp-image-' . (int) $attributes['id'];
		}
		$style_string = '';
		foreach ( $image_styles as $property => $value ) {
			$style_string .= $property . ':' . $value . ';';
		}

		$image = '<img src="' . esc_url( $text( 'url' ) ) . '" alt="' . esc_attr( $text( 'alt' ) ) . '"'
			. ( $image_classes ? ' class="' . esc_attr( implode( ' ', $image_classes ) ) . '"' : '' )
			. ( '' !== $style_string ? ' style="' . esc_attr( rtrim( $style_string, ';' ) ) . '"' : '' )
			. ( '' !== $text( 'title' ) ? ' title="' . esc_attr( $text( 'title' ) ) . '"' : '' )
			. ( ! empty( $attributes['isDecorative'] ) ? ' role="none"' : '' )
			. '/>';

		if ( '' !== $text( 'href' ) ) {
			$image = '<a'
				. ( '' !== $text( 'linkClass' ) ? ' class="' . esc_attr( $text( 'linkClass' ) ) . '"' : '' )
				. ' href="' . esc_url( $text( 'href' ) ) . '"'
				. ( '' !== $text( 'linkTarget' ) ? ' target="' . esc_attr( $text( 'linkTarget' ) ) . '"' : '' )
				. ( '' !== $text( 'rel' ) ? ' rel="' . esc_attr( $text( 'rel' ) ) . '"' : '' )
				. '>' . $image . '</a>';
		}
		$caption = '' !== trim( $text( 'caption' ) )
			? '<figcaption class="wp-element-caption">' . wp_kses_post( $text( 'caption' ) ) . '</figcaption>'
			: '';

		return '<figure class="' . esc_attr( implode( ' ', $figure_classes ) ) . '">' . $image . $caption . '</figure>';
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
		// Core adds background-image support at render time, not in useBlockProps.save().
		// Keep the attributes in block JSON, but do not bake render-only CSS into saved HTML.
		unset( $style['background'] );

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
	/**
	 * Blocks the max-width extension refuses, mirroring its EXCLUDED_BLOCKS.
	 *
	 * @see src/extensions/max-width/index.js
	 * @var array<int, string>
	 */
	private const MAX_WIDTH_EXCLUDED_BLOCKS = array(
		'core/spacer',
		'core/separator',
		'core/page-list',
		'core/navigation',
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
		'designsetgo/blobs',
	);

	/**
	 * Containers that own a reveal group, mirroring CONTAINER_BLOCKS.
	 *
	 * @see src/extensions/reveal-control/index.js
	 * @var array<int, string>
	 */
	private const REVEAL_CONTAINER_BLOCKS = array(
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
	);

	/**
	 * Blocks the custom-CSS extension refuses, mirroring its EXCLUDED_BLOCKS.
	 *
	 * @see src/extensions/custom-css/index.js
	 * @var array<int, string>
	 */
	private const CUSTOM_CSS_EXCLUDED_BLOCKS = array( 'core/html', 'core/code' );

	/**
	 * Blocks the clickable-group extension applies to, mirroring SUPPORTED_BLOCKS.
	 *
	 * @see src/extensions/clickable-group/index.js
	 * @var array<int, string>
	 */
	private const CLICKABLE_GROUP_BLOCKS = array(
		'core/group',
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
	);

	private const SUPPORTS_ON_INNER_ELEMENT = array(
		'designsetgo/modal' => 'dsgo-modal__content',
	);

	private const SERIALIZABLE_CORE_BLOCKS = array(
		'core/heading',
		'core/paragraph',
		'core/image',
		'core/list',
		'core/list-item',
		'core/quote',
	);

	/**
	 * Core blocks whose save() is a wrapper around InnerBlocks.Content, reproduced by
	 * generate_core_wrapper_html(). A list item also carries its own rich text before
	 * any nested list, so its content is part of the opening markup rather than a
	 * standalone innerHTML string.
	 */
	/**
	 * Attributes whose block.json source is core/image markup (img, figure > a,
	 * figcaption). The serializer omits sourced attributes from the block comment.
	 */
	private const CORE_IMAGE_SOURCED_ATTRIBUTES = array( 'url', 'alt', 'caption', 'title', 'href', 'rel', 'linkClass', 'linkTarget' );

	private const CORE_WRAPPER_BLOCKS = array(
		'core/list',
		'core/list-item',
		'core/quote',
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
	 * Opacity for a container overlay colour.
	 *
	 * Delegates to Serializer_Support. Kept on Block_Inserter because it is
	 * named as the PHP twin of src/utils/overlay-opacity.js from that file, from
	 * tests/fixtures/overlay-opacity-cases.json and from its PHPUnit test - a
	 * cross-runtime contract that should not move just because the
	 * implementation did.
	 *
	 * @param string $color Colour value.
	 * @return string Opacity as a string, or '' when the colour declares none.
	 */
	public static function overlay_opacity_for_color( string $color ): string {
		return Serializer_Support::overlay_opacity_for_color( $color );
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

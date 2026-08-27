<?php
/**
 * Regression tests for nested block insertion through the Abilities API.
 *
 * These cover the defects behind the "every block was broken" carousel report:
 * children appended outside their parent's wrapper, snake_case nested keys
 * silently dropped, unknown arguments rejected with an opaque message,
 * float attributes coerced to int, and index-based parent addressing drifting
 * between calls.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Inserter;
use DesignSetGo\Abilities\Block_Configurator;
use DesignSetGo\Abilities\Inserters\Add_Block;
use DesignSetGo\Abilities\Inserters\Add_Child_Block;
use DesignSetGo\Abilities\Info\Get_Post_Blocks;

/**
 * Nested insert regression tests.
 */
class Abilities_Nested_Insert_Test extends WP_UnitTestCase {

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private int $editor_id;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $this->editor_id );
	}

	/**
	 * Create an empty page.
	 *
	 * @return int Post ID.
	 */
	private function create_page(): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '',
				'post_author'  => $this->editor_id,
			)
		);
	}

	// ---------------------------------------------------------------------
	// Fix 1: a childless wrapper must keep its opening and closing HTML in
	// separate innerContent entries so a later append lands between them.
	// ---------------------------------------------------------------------

	/**
	 * A stored childless wrapper is split back into opening and closing HTML.
	 *
	 * Serializing and reparsing merges the two halves into one innerContent
	 * string, so the split has to be recoverable from stored content - that is
	 * the state every add-child-block call actually starts from.
	 */
	public function test_stored_childless_wrapper_is_split_for_append(): void {
		$markup = Block_Inserter::build_block_markup( 'designsetgo/slide', array() );
		$parsed = parse_blocks( $markup )[0];

		$this->assertCount( 1, $parsed['innerContent'], 'Precondition: reparsing merges the wrapper.' );

		$split = Block_Configurator::split_childless_wrapper( $parsed, $parsed['innerContent'] );

		$this->assertCount( 2, $split, 'Stored wrapper was not split into opening and closing halves.' );
		$this->assertSame( $parsed['innerContent'][0], $split[0] . $split[1], 'Splitting must not alter the markup.' );
		$this->assertStringEndsWith( '>', $split[0] );
		$this->assertStringStartsWith( '</', $split[1] );
	}

	/**
	 * The fallback split targets the innermost container, not the outermost.
	 */
	public function test_wrapper_split_targets_innermost_container(): void {
		$block = array(
			'blockName' => 'core/group',
			'attrs'     => array(),
		);

		$split = Block_Configurator::split_childless_wrapper(
			$block,
			array( '<div class="outer"><div class="inner"></div></div>' )
		);

		$this->assertSame( '<div class="outer"><div class="inner">', $split[0] );
		$this->assertSame( '</div></div>', $split[1] );
	}

	/**
	 * Appending a child to a childless wrapper nests it inside the wrapper
	 * rather than emitting it before the wrapper.
	 */
	public function test_child_is_nested_inside_empty_parent_wrapper(): void {
		$post_id = $this->create_page();

		$add = new Add_Block();
		$add->execute(
			array(
				'post_id'    => $post_id,
				'block_name' => 'designsetgo/slide',
			)
		);

		$child = new Add_Child_Block();
		$child->execute(
			array(
				'post_id'            => $post_id,
				'parent_block_index' => 0,
				'block_name'         => 'core/paragraph',
				'attributes'         => array( 'content' => 'Inside the slide' ),
			)
		);

		$content = get_post( $post_id )->post_content;

		$wrapper_pos = strpos( $content, '<div class="wp-block-designsetgo-slide' );
		$child_pos   = strpos( $content, '<!-- wp:paragraph' );

		$this->assertNotFalse( $wrapper_pos, 'Slide wrapper markup is missing.' );
		$this->assertNotFalse( $child_pos, 'Child paragraph is missing.' );
		$this->assertGreaterThan(
			$wrapper_pos,
			$child_pos,
			'Child block was emitted before its parent wrapper instead of inside it.'
		);
	}

	// ---------------------------------------------------------------------
	// Fix 2: nested children may use either innerBlocks or inner_blocks.
	// ---------------------------------------------------------------------

	/**
	 * Nested snake_case inner_blocks are honoured, not silently dropped.
	 */
	public function test_nested_snake_case_inner_blocks_are_honoured(): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->execute(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'designsetgo/slider',
				'inner_blocks' => array(
					array(
						'name'         => 'designsetgo/slide',
						'attributes'   => array(),
						'inner_blocks' => array(
							array(
								'name'       => 'core/paragraph',
								'attributes' => array( 'content' => 'Deeply nested' ),
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $result );
		$content = get_post( $post_id )->post_content;

		$this->assertStringContainsString( '<!-- wp:designsetgo/slide', $content );
		$this->assertStringContainsString(
			'Deeply nested',
			$content,
			'Nested inner_blocks (snake_case) were dropped instead of being inserted.'
		);
	}

	/**
	 * The same alias works on add-child-block.
	 */
	public function test_add_child_block_accepts_snake_case_nesting(): void {
		$post_id = $this->create_page();

		$add = new Add_Block();
		$add->execute(
			array(
				'post_id'    => $post_id,
				'block_name' => 'designsetgo/slider',
			)
		);

		$child = new Add_Child_Block();
		$child->execute(
			array(
				'post_id'            => $post_id,
				'parent_block_index' => 0,
				'block_name'         => 'designsetgo/slide',
				'inner_blocks'       => array(
					array(
						'name'         => 'designsetgo/section',
						'attributes'   => array(),
						'inner_blocks' => array(
							array(
								'name'       => 'core/heading',
								'attributes' => array(
									'content' => 'Card title',
									'level'   => 4,
								),
							),
						),
					),
				),
			)
		);

		$content = get_post( $post_id )->post_content;
		$this->assertStringContainsString( 'Card title', $content );
	}

	// ---------------------------------------------------------------------
	// Fix 3: unknown arguments must produce a legible, transport-safe
	// diagnostic instead of an opaque schema-validation failure.
	// ---------------------------------------------------------------------

	/**
	 * An unknown top-level argument names itself in the response.
	 */
	public function test_unknown_argument_returns_named_diagnostic(): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->run(
			array(
				'post_id'     => $post_id,
				'block_name'  => 'designsetgo/section',
				'content_map' => array( '0.0' => 'Hello' ),
			)
		);

		$this->assertIsArray( $result, 'Input errors must survive the MCP bridge as data, not WP_Error.' );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'content_map', (string) $result['message'] );
		$this->assertContains( 'content_map', $result['unknown_parameters'] );
		$this->assertContains( 'inner_blocks', $result['supported_parameters'] );
	}

	/**
	 * A rejected call must not modify the post.
	 */
	public function test_unknown_argument_does_not_write(): void {
		$post_id = $this->create_page();

		$add = new Add_Block();
		$add->run(
			array(
				'post_id'     => $post_id,
				'block_name'  => 'designsetgo/section',
				'content_map' => array( '0.0' => 'Hello' ),
			)
		);

		$this->assertSame( '', get_post( $post_id )->post_content );
	}

	// ---------------------------------------------------------------------
	// Fix 4: generated markup is validated before the post is written.
	// ---------------------------------------------------------------------

	/**
	 * A float "number" attribute is not truncated to int in generated HTML.
	 */
	public function test_number_attribute_keeps_fractional_value(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/slider',
			array( 'slidesPerView' => 1.2 )
		);

		$this->assertStringContainsString( '"slidesPerView":1.2', $markup );
		$this->assertStringContainsString(
			'data-slides-per-view="1.2"',
			$markup,
			'Generated HTML disagrees with the stored attribute, which invalidates the block.'
		);
	}

	/**
	 * The tree validator flags a wrapper whose child precedes its opening HTML.
	 */
	public function test_validator_detects_child_outside_wrapper(): void {
		$blocks = array(
			array(
				'blockName'    => 'designsetgo/slide',
				'attrs'        => array(),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Hi</p>',
						'innerContent' => array( '<p>Hi</p>' ),
					),
				),
				'innerHTML'    => '<div class="dsgo-slide"></div>',
				// Child placeholder before the wrapper - the defect shape.
				'innerContent' => array( null, '<div class="dsgo-slide"></div>' ),
			),
		);

		$problems = Block_Inserter::validate_block_tree( $blocks );

		$this->assertNotEmpty( $problems, 'Validator missed a child emitted outside its parent wrapper.' );
		$this->assertStringContainsString( 'designsetgo/slide', $problems[0] );
	}

	/**
	 * A correctly nested tree passes validation.
	 */
	public function test_validator_accepts_well_formed_tree(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/slider',
			array(),
			array(
				array(
					'name'        => 'designsetgo/slide',
					'attributes'  => array(),
					'innerBlocks' => array(
						array(
							'name'       => 'core/paragraph',
							'attributes' => array( 'content' => 'Fine' ),
						),
					),
				),
			)
		);

		$this->assertSame( array(), Block_Inserter::validate_block_tree( parse_blocks( $markup ) ) );
	}

	// ---------------------------------------------------------------------
	// Children are only accepted by blocks that can actually hold them.
	// ---------------------------------------------------------------------

	/**
	 * A core/heading given children is refused, with the right fix named.
	 */
	public function test_children_on_core_heading_are_rejected(): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->run(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'designsetgo/section',
				'inner_blocks' => array(
					array(
						'name'        => 'core/heading',
						'attributes'  => array( 'level' => 4 ),
						'innerBlocks' => array(
							array(
								'name'       => 'core/paragraph',
								'attributes' => array( 'content' => 'Monthly Content Calendars' ),
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'core/heading', (string) $result['message'] );
		$this->assertStringContainsString( 'attributes.content', (string) $result['message'] );
		$this->assertSame( '', get_post( $post_id )->post_content, 'A refused call must not write.' );
	}

	/**
	 * A core container the inserter cannot serialize is refused too, rather
	 * than silently producing a block comment with no wrapper markup.
	 */
	public function test_children_on_unsupported_core_container_are_rejected(): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->run(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'core/group',
				'inner_blocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'In a group' ),
					),
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'core/group', (string) $result['message'] );
		$this->assertStringContainsString( 'designsetgo/section', (string) $result['message'] );
		$this->assertSame( '', get_post( $post_id )->post_content );
	}

	/**
	 * The refusal names where in the tree the problem is.
	 */
	public function test_child_rejection_reports_the_offending_path(): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->run(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'designsetgo/section',
				'inner_blocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'Fine' ),
					),
					array(
						'name'        => 'designsetgo/slider',
						'innerBlocks' => array(
							array(
								'name'        => 'designsetgo/slide',
								'innerBlocks' => array(
									array(
										'name'        => 'core/heading',
										'innerBlocks' => array(
											array( 'name' => 'core/paragraph' ),
										),
									),
								),
							),
						),
					),
				),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertContains( '1.0.0', $result['invalid_paths'] );
	}

	/**
	 * Server-rendered blocks have no save markup by design, so nesting inside
	 * them is fine and must keep working.
	 */
	public function test_dynamic_blocks_may_hold_children(): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->run(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'designsetgo/query',
				'inner_blocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'Inside a dynamic block' ),
					),
				),
			)
		);

		$this->assertTrue( $result['success'], 'Dynamic containers must still accept children.' );
		$this->assertStringContainsString( 'Inside a dynamic block', get_post( $post_id )->post_content );
	}

	/**
	 * The backstop validator flags a static block holding children with no
	 * wrapper markup of its own, whatever route produced it.
	 */
	public function test_validator_flags_static_block_with_no_wrapper(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/heading',
				'attrs'        => array( 'level' => 4 ),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Hi</p>',
						'innerContent' => array( '<p>Hi</p>' ),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( null ),
			),
		);

		$problems = Block_Inserter::validate_block_tree( $blocks );

		$this->assertNotEmpty( $problems );
		$this->assertStringContainsString( 'core/heading', $problems[0] );
	}

	/**
	 * Nested inner-block arrays declare their item schema.
	 */
	public function test_inner_blocks_schema_declares_nested_items(): void {
		$schema = ( new Add_Block() )->get_config()['input_schema'];
		$items  = $schema['properties']['inner_blocks']['items'];

		$this->assertArrayHasKey( 'items', $items['properties']['innerBlocks'] );
		$this->assertArrayHasKey( 'type', $items['properties']['innerBlocks']['items'] );
		$this->assertArrayHasKey( 'inner_blocks', $items['properties'], 'Both nesting spellings must be documented.' );
	}

	/**
	 * No registered ability declares a schema node without a "type".
	 *
	 * Core's rest_validate_value_from_schema() reads $args['type'] three times
	 * without guarding, so one typeless node emits three PHP warnings on every
	 * request that validates it. That is how list-extensions was flooding the
	 * log during unrelated abilities calls.
	 */
	public function test_no_ability_declares_a_typeless_schema_node(): void {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API not available.' );
		}

		$typeless = array();

		$walk = static function ( $node, string $path ) use ( &$walk, &$typeless ): void {
			if ( ! is_array( $node ) ) {
				return;
			}

			if ( isset( $node['properties'] ) && is_array( $node['properties'] ) ) {
				foreach ( $node['properties'] as $key => $child ) {
					if ( is_array( $child ) && ! isset( $child['type'] ) ) {
						$typeless[] = $path . '.properties.' . $key;
					}
					$walk( $child, $path . '.properties.' . $key );
				}
			}

			if ( isset( $node['items'] ) && is_array( $node['items'] ) ) {
				if ( ! isset( $node['items']['type'] ) ) {
					$typeless[] = $path . '.items';
				}
				$walk( $node['items'], $path . '.items' );
			}
		};

		foreach ( wp_get_abilities() as $name => $ability ) {
			if ( 0 !== strpos( (string) $name, 'designsetgo/' ) ) {
				continue;
			}
			$walk( $ability->get_input_schema(), $name . ':input' );
			$walk( $ability->get_output_schema(), $name . ':output' );
		}

		$this->assertSame( array(), $typeless, 'Schema nodes missing "type": ' . implode( ', ', $typeless ) );
	}

	// ---------------------------------------------------------------------
	// Nested definitions must name a block and carry only known keys.
	// ---------------------------------------------------------------------

	/**
	 * A nested definition keyed block_name still works.
	 *
	 * The block_name key is the TOP-LEVEL argument's spelling. Using it for children is
	 * a consistent reading of the schema, and used to drop every child in
	 * silence while the ability reported success.
	 */
	public function test_nested_block_name_alias_is_honoured(): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->run(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'designsetgo/section',
				'inner_blocks' => array(
					array(
						'block_name'   => 'designsetgo/slider',
						'inner_blocks' => array(
							array(
								'block_name'   => 'designsetgo/slide',
								'inner_blocks' => array(
									array(
										'block_name' => 'core/paragraph',
										'attributes' => array( 'content' => 'Reached the slide' ),
									),
								),
							),
						),
					),
				),
			)
		);

		$this->assertTrue( $result['success'] );

		$content = get_post( $post_id )->post_content;
		$this->assertStringContainsString( '<!-- wp:designsetgo/slider', $content );
		$this->assertStringContainsString( 'Reached the slide', $content );
	}

	/**
	 * The same payload survives core's own input validation.
	 *
	 * Calling execute() or run() directly skips WP_Ability::validate_input(),
	 * so a schema that rejects a payload the callback would have accepted looks
	 * fine in every other test here. Declaring nested "name" as `required` did
	 * exactly that: core refused the alias before the callback ran, and the MCP
	 * bridge reported it only as "Ability execution failed."
	 */
	public function test_nested_alias_passes_registered_input_validation(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available.' );
		}

		$ability = wp_get_ability( 'designsetgo/add-block' );
		$this->assertNotNull( $ability, 'designsetgo/add-block is not registered.' );

		$post_id = $this->create_page();

		$result = $ability->execute(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'designsetgo/section',
				'inner_blocks' => array(
					array(
						'block_name'   => 'designsetgo/slider',
						'inner_blocks' => array(
							array( 'block_name' => 'designsetgo/slide' ),
						),
					),
				),
			)
		);

		$this->assertNotWPError( $result, 'Core rejected the payload before the ability could run.' );
		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'wp:designsetgo/slide', get_post( $post_id )->post_content );
	}

	/**
	 * A nested definition with no recognisable name is refused, not dropped.
	 */
	public function test_nameless_nested_definition_is_refused(): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->run(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'designsetgo/section',
				'inner_blocks' => array(
					array( 'attributes' => array( 'content' => 'Orphan' ) ),
				),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Missing "name"', (string) $result['message'] );
		$this->assertSame( '', get_post( $post_id )->post_content, 'A refused call must not write.' );
	}

	/**
	 * An unknown key on a nested definition is reported rather than ignored.
	 */
	public function test_unknown_nested_key_is_reported(): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->run(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'designsetgo/section',
				'inner_blocks' => array(
					array(
						'name'        => 'core/paragraph',
						'attributes'  => array( 'content' => 'Hi' ),
						'content_map' => array( '0' => 'Hi' ),
					),
				),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'content_map', (string) $result['message'] );
		$this->assertSame( '', get_post( $post_id )->post_content );
	}

	// ---------------------------------------------------------------------
	// Serializer coverage.
	// ---------------------------------------------------------------------

	/**
	 * A block with no PHP serializer is refused rather than written as a
	 * self-closing comment its save() would never produce.
	 */
	public function test_block_without_serializer_is_refused(): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->run(
			array(
				'post_id'    => $post_id,
				'block_name' => 'core/columns',
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'core/columns', (string) $result['message'] );
		$this->assertSame( '', get_post( $post_id )->post_content );
	}

	/**
	 * Every registered DesignSetGo block can be serialized.
	 *
	 * Static blocks whose save() has no PHP mirror serialize to a bare
	 * self-closing comment, which the editor rejects. This is the completeness
	 * guard: a new static block must arrive with a serializer, or be caught here
	 * rather than by an agent writing a broken page.
	 */
	public function test_every_designsetgo_block_is_serializable(): void {
		$gaps = array();

		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block_type ) {
			if ( 0 !== strpos( (string) $name, 'designsetgo/' ) ) {
				continue;
			}

			$gap = Block_Inserter::get_serialization_gap( (string) $name );
			if ( null !== $gap ) {
				$gaps[] = (string) $name;
			}
		}

		$this->assertSame( array(), $gaps, 'Blocks with no serializer: ' . implode( ', ', $gaps ) );
	}

	/**
	 * Variants a serializer deliberately does not reproduce are refused.
	 *
	 * These are the three cases where mirroring save() would mean duplicating a
	 * path sanitizer or an SVG shape library. Refusing keeps the stored markup
	 * honest; the message says where to do it instead.
	 *
	 * @dataProvider provide_unsupported_variants
	 *
	 * @param string               $block_name Block to insert.
	 * @param array<string, mixed> $attributes Attributes carrying the variant.
	 * @param string               $expected   Fragment the message must contain.
	 */
	public function test_unsupported_variants_are_refused( string $block_name, array $attributes, string $expected ): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->run(
			array(
				'post_id'    => $post_id,
				'block_name' => $block_name,
				'attributes' => $attributes,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( $expected, (string) $result['message'] );
		$this->assertSame( '', get_post( $post_id )->post_content );
	}

	/**
	 * Variants that are refused rather than mirrored.
	 *
	 * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
	 */
	public function provide_unsupported_variants(): array {
		return array(
			'text path custom shape'    => array(
				'designsetgo/text-path',
				array( 'pathType' => 'custom' ),
				'pathType',
			),
			'animated heading segment'  => array(
				'designsetgo/heading-segment',
				array( 'headlineRole' => 'animated' ),
				'headlineRole',
			),
			'animated advanced heading' => array(
				'designsetgo/advanced-heading',
				array( 'animatedHeadline' => array( 'mode' => 'rotating' ) ),
				'animatedHeadline',
			),
		);
	}

	/**
	 * An attribute value outside the block's own enum is refused.
	 *
	 * WordPress replaces an out-of-enum value with the default when it parses
	 * the block, so the generated markup and save() disagree and the block is
	 * invalid.
	 */
	public function test_out_of_enum_attribute_is_refused(): void {
		$post_id = $this->create_page();

		$add    = new Add_Block();
		$result = $add->run(
			array(
				'post_id'    => $post_id,
				'block_name' => 'designsetgo/timeline',
				'attributes' => array( 'layout' => 'left' ),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'layout', (string) $result['message'] );
		$this->assertStringContainsString( 'alternating', (string) $result['message'] );
		$this->assertSame( '', get_post( $post_id )->post_content );
	}

	/**
	 * Fifty Fifty now has a serializer and produces its real save() structure.
	 */
	public function test_fifty_fifty_serializes_its_wrappers(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/fifty-fifty',
			array( 'mediaPosition' => 'right' ),
			array(
				array(
					'name'       => 'core/paragraph',
					'attributes' => array( 'content' => 'Beside the media' ),
				),
			)
		);

		$this->assertStringNotContainsString( '/-->', $markup, 'Fifty Fifty must not serialize self-closing.' );
		$this->assertStringContainsString( 'dsgo-fifty-fifty--media-right', $markup );
		$this->assertStringContainsString( 'dsgo-fifty-fifty__media', $markup );
		$this->assertStringContainsString( 'dsgo-fifty-fifty__content-inner', $markup );
		$this->assertStringContainsString( 'Beside the media', $markup );
	}

	/**
	 * Preset colour attributes reach the markup, not just the block comment.
	 */
	public function test_preset_colors_serialize_to_classes(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array( 'backgroundColor' => 'base' )
		);

		$this->assertStringContainsString( 'has-base-background-color', $markup );
		$this->assertStringContainsString( 'has-background', $markup );
	}

	/**
	 * Colours routed to an inner element by save() are placed there too.
	 */
	public function test_routed_colors_land_on_the_inner_element(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/icon-button',
			array(
				'text'            => 'Go',
				'url'             => '/go',
				'backgroundColor' => 'primary',
				'textColor'       => 'base',
			)
		);

		// On the <a>, not on the positioning wrapper.
		$this->assertMatchesRegularExpression(
			'/<a class="[^"]*has-primary-background-color[^"]*"/',
			$markup
		);
		$this->assertMatchesRegularExpression(
			'/<a class="[^"]*has-base-color[^"]*"/',
			$markup
		);
	}

	// ---------------------------------------------------------------------
	// Fix 5: path-based addressing that does not drift with document order.
	// ---------------------------------------------------------------------

	/**
	 * The get-post-blocks ability reports a tree path alongside the flat index.
	 */
	public function test_get_post_blocks_reports_block_path(): void {
		$post_id = $this->create_page();

		$add = new Add_Block();
		$add->execute(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'designsetgo/slider',
				'inner_blocks' => array(
					array( 'name' => 'designsetgo/slide' ),
					array( 'name' => 'designsetgo/slide' ),
				),
			)
		);

		$get    = new Get_Post_Blocks();
		$result = $get->execute( array( 'post_id' => $post_id ) );

		$this->assertSame( '0', $result['blocks'][0]['blockPath'] );
		$this->assertSame( '0.0', $result['blocks'][0]['innerBlocks'][0]['blockPath'] );
		$this->assertSame( '0.1', $result['blocks'][0]['innerBlocks'][1]['blockPath'] );
	}

	/**
	 * A path targets the same block even after an earlier sibling subtree grows.
	 */
	public function test_block_path_survives_earlier_inserts(): void {
		$post_id = $this->create_page();

		$add = new Add_Block();
		$add->execute(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'designsetgo/slider',
				'inner_blocks' => array(
					array( 'name' => 'designsetgo/slide' ),
					array( 'name' => 'designsetgo/slide' ),
				),
			)
		);

		$child = new Add_Child_Block();

		// Grow the FIRST slide. Under flat indexing this shifts the second
		// slide's index; under path addressing "0.1" is unchanged.
		$child->execute(
			array(
				'post_id'           => $post_id,
				'parent_block_path' => '0.0',
				'block_name'        => 'core/paragraph',
				'attributes'        => array( 'content' => 'First slide copy' ),
			)
		);

		$result = $child->execute(
			array(
				'post_id'           => $post_id,
				'parent_block_path' => '0.1',
				'block_name'        => 'core/paragraph',
				'attributes'        => array( 'content' => 'Second slide copy' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );

		$get    = new Get_Post_Blocks();
		$blocks = $get->execute( array( 'post_id' => $post_id ) )['blocks'];

		$slide_one = $blocks[0]['innerBlocks'][0];
		$slide_two = $blocks[0]['innerBlocks'][1];

		$this->assertCount( 1, $slide_one['innerBlocks'] );
		$this->assertCount( 1, $slide_two['innerBlocks'], 'Second insert landed in the wrong slide.' );
	}

	/**
	 * Replay of the payload that produced the broken carousel.
	 *
	 * A section is added, then a slider with three slides is appended to it in
	 * one call, every level using snake_case inner_blocks. Each slide must end
	 * up holding its own card, inside its own wrapper, with the card content in
	 * the slide's content container rather than beside it.
	 */
	public function test_carousel_payload_produces_populated_slides(): void {
		$post_id = $this->create_page();

		$slides = array();
		foreach ( array( 'Social Media Strategy', 'Content Planning', 'Campaign Support' ) as $title ) {
			$slides[] = array(
				'name'         => 'designsetgo/slide',
				'attributes'   => array(),
				'inner_blocks' => array(
					array(
						'name'         => 'designsetgo/section',
						'attributes'   => array( 'className' => 'is-style-card' ),
						'inner_blocks' => array(
							array(
								'name'       => 'core/heading',
								'attributes' => array(
									'content' => $title,
									'level'   => 4,
								),
							),
						),
					),
				),
			);
		}

		$add = new Add_Block();
		$add->execute(
			array(
				'post_id'    => $post_id,
				'block_name' => 'designsetgo/section',
			)
		);

		$child  = new Add_Child_Block();
		$result = $child->execute(
			array(
				'post_id'           => $post_id,
				'parent_block_path' => '0',
				'block_name'        => 'designsetgo/slider',
				'attributes'        => array(
					'slidesPerView' => 1.2,
					'autoplay'      => true,
				),
				'inner_blocks'      => $slides,
			)
		);

		$this->assertTrue( $result['success'] );

		$content = get_post( $post_id )->post_content;

		// Every slide kept its card.
		$this->assertSame( 3, substr_count( $content, '<!-- /wp:designsetgo/slide -->' ) );
		foreach ( array( 'Social Media Strategy', 'Content Planning', 'Campaign Support' ) as $title ) {
			$this->assertStringContainsString( $title, $content );
		}

		// No slide is left with an empty content container - the symptom that
		// made every rendered slide blank.
		$this->assertStringNotContainsString( '<div class="dsgo-slide__content"></div>', $content );

		// The float attribute agrees with the generated markup, so the block
		// still validates when the editor opens it.
		$this->assertStringContainsString( '"slidesPerView":1.2', $content );
		$this->assertStringContainsString( 'data-slides-per-view="1.2"', $content );

		// And the whole tree is structurally sound.
		$this->assertSame( array(), Block_Inserter::validate_block_tree( parse_blocks( $content ) ) );
	}

	/**
	 * The add-child-block ability returns the inserted child's path, so callers
	 * can chain without re-reading the document.
	 */
	public function test_add_child_block_returns_child_path(): void {
		$post_id = $this->create_page();

		$add = new Add_Block();
		$add->execute(
			array(
				'post_id'    => $post_id,
				'block_name' => 'designsetgo/slider',
			)
		);

		$child  = new Add_Child_Block();
		$result = $child->execute(
			array(
				'post_id'           => $post_id,
				'parent_block_path' => '0',
				'block_name'        => 'designsetgo/slide',
			)
		);

		$this->assertSame( '0.0', $result['block_path'] );
	}
}

<?php
/**
 * Tree_Validator mirrors src/engine/tree.js's structural contract in PHP, then
 * adds PHP-only checks (size, unknown block, attribute schema, placement) an
 * agent's JSON block tree needs before the browser engine serializes it.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Agent_Build\Tree_Validator;

/**
 * Tree_Validator tests.
 *
 * @group abilities
 * @group agent-build
 */
class Agent_Build_Tree_Validator_Test extends WP_UnitTestCase {

	/**
	 * Pull just the codes out of a problems array, in order.
	 *
	 * @param array $problems Problems array from Tree_Validator::validate().
	 * @return array<int, string> Just the codes, in order.
	 */
	private function codes( array $problems ): array {
		return array_map(
			function ( $problem ) {
				return $problem['code'];
			},
			$problems
		);
	}

	/**
	 * Tree_Validator is a plain helper, never registered as an ability.
	 */
	public function test_helper_class_is_not_an_ability() {
		// Tree_Validator lives in the agent-build directory the registry scans
		// for ability classes, but it is a plain static helper, not an
		// Abstract_Ability - the registry's is_subclass_of() gate must skip it
		// so it never gets registered as an ability with WordPress.
		$this->assertFalse( is_subclass_of( Tree_Validator::class, Abstract_Ability::class ) );
	}

	/**
	 * Reports invalid tree and stops when root is not an object.
	 */
	public function test_reports_invalid_tree_and_stops_when_root_is_not_an_object() {
		foreach ( array( null, 42, 'a string', array( 1, 2, 3 ) ) as $input ) {
			$problems = Tree_Validator::validate( $input );
			$this->assertSame( array( 'designsetgo_invalid_tree' ), $this->codes( $problems ) );
			$this->assertSame( 'blocks', $problems[0]['path'] );
		}
	}

	/**
	 * Reports invalid tree and stops when blocks is not a list.
	 */
	public function test_reports_invalid_tree_and_stops_when_blocks_is_not_a_list() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => 'nope',
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_tree' ), $this->codes( $problems ) );
	}

	/**
	 * Does not check version when root shape is invalid.
	 */
	public function test_does_not_check_version_when_root_shape_is_invalid() {
		// A missing `blocks` array is itself an invalid-root case, so the
		// version mismatch alongside it must never surface.
		$problems = Tree_Validator::validate(
			array(
				'version' => 99,
				'blocks'  => null,
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_tree' ), $this->codes( $problems ) );
	}

	/**
	 * Reports unsupported tree version when version is missing.
	 */
	public function test_reports_unsupported_tree_version_when_version_is_missing() {
		$problems = Tree_Validator::validate( array( 'blocks' => array() ) );
		$this->assertSame( array( 'designsetgo_unsupported_tree_version' ), $this->codes( $problems ) );
		$this->assertSame( 'version', $problems[0]['path'] );
	}

	/**
	 * Reports unsupported tree version when version is not 1.
	 */
	public function test_reports_unsupported_tree_version_when_version_is_not_1() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 2,
				'blocks'  => array(),
			)
		);
		$this->assertSame( array( 'designsetgo_unsupported_tree_version' ), $this->codes( $problems ) );
	}

	/**
	 * Reports invalid block definition for a malformed name.
	 */
	public function test_reports_invalid_block_definition_for_a_malformed_name() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array( array( 'name' => 'Not Valid' ) ),
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_block_definition' ), $this->codes( $problems ) );
		$this->assertSame( 'blocks[0]', $problems[0]['path'] );
	}

	/**
	 * Reports invalid block definition when attributes is not a plain object.
	 */
	public function test_reports_invalid_block_definition_when_attributes_is_not_a_plain_object() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 1, 2, 3 ),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_block_definition' ), $this->codes( $problems ) );
	}

	/**
	 * Every key other than name/attributes/innerBlocks is its own
	 * designsetgo_invalid_block_definition problem - no silent aliases.
	 * Mirrors src/engine/test/tree.test.js ("reports
	 * designsetgo_invalid_block_definition for each unknown node key")
	 * case-for-case: same codes, paths, and order.
	 */
	public function test_reports_invalid_block_definition_for_unknown_node_keys() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'         => 'core/group',
						'inner_blocks' => array( array( 'name' => 'core/paragraph' ) ),
					),
					array(
						'name'        => 'core/group',
						'innerBlocks' => array(
							array(
								'block_name' => 'core/paragraph',
								'name'       => 'core/paragraph',
								'attrs'      => array( 'content' => 'Hi' ),
							),
						),
					),
				),
			)
		);

		$this->assertSame(
			array(
				'designsetgo_invalid_block_definition',
				'designsetgo_invalid_block_definition',
				'designsetgo_invalid_block_definition',
			),
			$this->codes( $problems )
		);
		$this->assertSame(
			array( 'blocks[0]', 'blocks[1].innerBlocks[0]', 'blocks[1].innerBlocks[0]' ),
			array_column( $problems, 'path' )
		);
		$this->assertStringContainsString( 'inner_blocks', $problems[0]['message'] );
		$this->assertStringContainsString( 'block_name', $problems[1]['message'] );
		$this->assertStringContainsString( 'attrs', $problems[2]['message'] );
	}

	/**
	 * Paths use nested inner blocks format.
	 */
	public function test_paths_use_nested_inner_blocks_format() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array( 'name' => 'core/paragraph' ),
					array(
						'name'        => 'core/group',
						'innerBlocks' => array(
							array( 'name' => 'Not Valid' ),
						),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_block_definition' ), $this->codes( $problems ) );
		$this->assertSame( 'blocks[1].innerBlocks[0]', $problems[0]['path'] );
	}

	/**
	 * Collects every shape problem not just the first.
	 */
	public function test_collects_every_shape_problem_not_just_the_first() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 2,
				'blocks'  => array(
					array( 'name' => 'Bad Name' ),
					array(
						'name'       => 'core/group',
						'attributes' => 5,
					),
				),
			)
		);
		$this->assertSame(
			array(
				'designsetgo_unsupported_tree_version',
				'designsetgo_invalid_block_definition',
				'designsetgo_invalid_block_definition',
			),
			$this->codes( $problems )
		);
	}

	/**
	 * Reports tree too large.
	 */
	public function test_reports_tree_too_large() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => str_repeat( 'a', 1100000 ) ),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_tree_too_large' ), $this->codes( $problems ) );
	}

	/**
	 * Stops after tree too large without looking up block types.
	 */
	public function test_stops_after_tree_too_large_without_looking_up_block_types() {
		// Pair the oversized payload with a block name that would otherwise
		// trip the unknown-block check, and confirm only the size problem
		// surfaces - later stages must not run.
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'does-not/exist',
						'attributes' => array( 'content' => str_repeat( 'a', 1100000 ) ),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_tree_too_large' ), $this->codes( $problems ) );
	}

	/**
	 * Reports unknown block.
	 */
	public function test_reports_unknown_block() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array( array( 'name' => 'does-not/exist' ) ),
			)
		);
		$this->assertSame( array( 'designsetgo_unknown_block' ), $this->codes( $problems ) );
		$this->assertSame( 'blocks[0]', $problems[0]['path'] );
	}

	/**
	 * Unknown block stops before attribute and placement checks.
	 */
	public function test_unknown_block_stops_before_attribute_and_placement_checks() {
		// designsetgo/accordion-item outside its declared parent would also
		// trip the placement check, and the sibling unknown block would trip
		// attribute lookups - only the unknown-block problem should surface.
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array( 'name' => 'designsetgo/accordion-item' ),
					array( 'name' => 'does-not/exist' ),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_unknown_block' ), $this->codes( $problems ) );
	}

	/**
	 * Reports invalid attribute for a boolean attribute.
	 */
	public function test_reports_invalid_attribute_for_a_boolean_attribute() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'        => 'designsetgo/accordion',
						'innerBlocks' => array(
							array(
								'name'       => 'designsetgo/accordion-item',
								'attributes' => array( 'isOpen' => 'yes' ),
							),
						),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_attribute' ), $this->codes( $problems ) );
		$this->assertSame( 'blocks[0].innerBlocks[0]', $problems[0]['path'] );
		$this->assertStringContainsString( 'isOpen', $problems[0]['message'] );
	}

	/**
	 * Reports invalid attribute for an enum attribute.
	 */
	public function test_reports_invalid_attribute_for_an_enum_attribute() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'designsetgo/accordion',
						'attributes' => array( 'iconStyle' => 'square' ),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_attribute' ), $this->codes( $problems ) );
	}

	/**
	 * Reports invalid attribute for an object attribute.
	 */
	public function test_reports_invalid_attribute_for_an_object_attribute() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'designsetgo/card',
						'attributes' => array( 'imageFocalPoint' => 'not-an-object' ),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_attribute' ), $this->codes( $problems ) );
	}

	/**
	 * Accepts valid string attribute with source html.
	 */
	public function test_accepts_valid_string_attribute_with_source_html() {
		// `title` carries source/selector binding keys that must be stripped
		// before rest_validate_value_from_schema() runs, or the schema itself
		// (a binding descriptor, not a value schema) rejects a plain string.
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'        => 'designsetgo/accordion',
						'innerBlocks' => array(
							array(
								'name'       => 'designsetgo/accordion-item',
								'attributes' => array( 'title' => 'A valid title' ),
							),
						),
					),
				),
			)
		);
		$this->assertSame( array(), $problems );
	}

	/**
	 * PHP's up-front check is deliberately fail-open (see Tree_Attributes
	 * ::check()'s docblock, rule (c)): an invented name with no known name
	 * close enough to plausibly be a typo passes PHP unchecked, even on a
	 * manifest-covered block, and is left to the browser engine at finish
	 * time. Rejecting here would have no way to distinguish "genuinely
	 * unknown" from "a legitimately novel attribute PHP just doesn't happen
	 * to know about" - only a name that LOOKS like a typo is confident
	 * enough to reject before the browser ever sees the tree.
	 */
	public function test_far_from_anything_invented_attribute_is_not_rejected_in_php() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'zzqqxx' => 'whatever' ),
					),
				),
			)
		);
		$this->assertSame( array(), $problems );
	}

	/**
	 * The committed manifest only covers `designsetgo/*` and `core/*` (see
	 * Attribute_Manifest). A registered block outside that universe - a
	 * WooCommerce block, a third-party plugin's block - must never be
	 * treated as "this generator looked and found nothing": PHP's own
	 * schema check still applies, but no attribute name on that block is
	 * ever rejected as unknown, since Attribute_Manifest::is_covered()
	 * is false for it. Registers a throwaway `acme/test-block` with a
	 * small PHP attribute schema (no `anchor`, no support attributes) to
	 * prove this without depending on any real third-party plugin being
	 * active.
	 */
	public function test_a_non_manifest_registered_block_never_rejects_an_unknown_attribute() {
		register_block_type(
			'acme/test-block',
			array(
				'attributes' => array(
					'title' => array( 'type' => 'string' ),
				),
			)
		);

		try {
			$problems = Tree_Validator::validate(
				array(
					'version' => 1,
					'blocks'  => array(
						array(
							'name'       => 'acme/test-block',
							'attributes' => array(
								'anchor' => 'my-anchor',
								'someInventedThirdPartyAttr' => 'whatever',
							),
						),
					),
				)
			);
		} finally {
			unregister_block_type( 'acme/test-block' );
		}

		$this->assertSame( array(), $problems );
	}

	/**
	 * `backgroundColor` is declared directly in core/paragraph's own PHP
	 * schema (unlike `anchor`), so a misspelling is rejected purely from
	 * PHP's own known names - the manifest isn't even needed for this one,
	 * though core/paragraph is manifest-covered too.
	 */
	public function test_core_paragraph_misspelled_attribute_is_rejected_with_a_suggestion() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'backgroundColour' => '#fff' ),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_unknown_attribute' ), $this->codes( $problems ) );
		$this->assertSame(
			'unknown attribute "backgroundColour" for core/paragraph — did you mean "backgroundColor"?',
			$problems[0]['message']
		);
	}

	/**
	 * A real DesignSetGo extension attribute - known to JS via the
	 * committed manifest, never declared in core/paragraph's own PHP
	 * schema - is still accepted unchecked.
	 */
	public function test_js_only_extension_attribute_is_allowed() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'dsgoAnimationEnabled' => true ),
					),
				),
			)
		);
		$this->assertSame( array(), $problems );
	}

	/**
	 * `anchor` is a block-support attribute WordPress's PHP registry never
	 * adds to `WP_Block_Type->attributes` (only client-side block-support JS
	 * does) - the manifest is what keeps this from being misreported as
	 * unknown.
	 */
	public function test_manifest_covered_support_attribute_is_allowed() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'anchor' => 'my-anchor' ),
					),
				),
			)
		);
		$this->assertSame( array(), $problems );
	}

	/**
	 * A misspelled attribute name close to a real one is rejected with a
	 * "did you mean" suggestion in the message.
	 */
	public function test_misspelled_attribute_is_rejected_with_a_suggestion() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'designsetgo/section',
						'attributes' => array( 'backgroundColour' => '#fff' ),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_unknown_attribute' ), $this->codes( $problems ) );
		$this->assertSame(
			'unknown attribute "backgroundColour" for designsetgo/section — did you mean "backgroundColor"?',
			$problems[0]['message']
		);
	}

	/**
	 * A misspelled DesignSetGo extension attribute - known only via the
	 * manifest, never PHP's own schema - is still rejected with a
	 * suggestion: rule (a) (designsetgo/section is manifest-covered) and
	 * rule (c) (dsgoAnimationEnabled is a close match) both hold.
	 */
	public function test_misspelled_extension_attribute_is_rejected_with_a_suggestion() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'designsetgo/section',
						'attributes' => array( 'dsgoAnimationEnabeld' => true ),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_unknown_attribute' ), $this->codes( $problems ) );
		$this->assertSame(
			'unknown attribute "dsgoAnimationEnabeld" for designsetgo/section — did you mean "dsgoAnimationEnabled"?',
			$problems[0]['message']
		);
	}

	/**
	 * Attribute and placement problems are collected together, not gated.
	 */
	public function test_attribute_and_placement_problems_are_collected_together() {
		// Once shape, size, and unknown-block all pass, attribute-schema and
		// child-placement problems are independent of each other and both
		// run - this tree trips one of each, on two different top-level
		// blocks, and both must come back from a single call rather than
		// one hiding the other (no round trip needed to see both).
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => 'designsetgo/accordion',
						'attributes' => array( 'iconStyle' => 'square' ),
					),
					array(
						'name'       => 'designsetgo/accordion-item',
						'attributes' => array( 'title' => 'Outside its accordion' ),
					),
				),
			)
		);

		$this->assertSame(
			array( 'designsetgo_invalid_attribute', 'designsetgo_invalid_child_placement' ),
			$this->codes( $problems )
		);
		$this->assertSame( 'blocks[0]', $problems[0]['path'] );
		$this->assertStringContainsString( 'iconStyle', $problems[0]['message'] );
		$this->assertSame( 'blocks[1]', $problems[1]['path'] );
	}

	/**
	 * A block whose block.json declares `parent` is rejected anywhere but
	 * directly inside one of those parents.
	 */
	public function test_reports_invalid_child_placement() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'        => 'designsetgo/section',
						'innerBlocks' => array(
							array(
								'name'       => 'designsetgo/accordion-item',
								'attributes' => array( 'title' => 'Question' ),
							),
						),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_child_placement' ), $this->codes( $problems ) );
		$this->assertSame( 'blocks[0].innerBlocks[0]', $problems[0]['path'] );
		$this->assertStringContainsString( 'designsetgo/accordion', $problems[0]['message'] );
	}

	/**
	 * Placement path uses nested inner blocks format.
	 */
	public function test_placement_path_uses_nested_inner_blocks_format() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array( 'name' => 'core/paragraph' ),
					array(
						'name'        => 'designsetgo/section',
						'innerBlocks' => array(
							array( 'name' => 'core/list-item' ),
						),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_child_placement' ), $this->codes( $problems ) );
		$this->assertSame( 'blocks[1].innerBlocks[0]', $problems[0]['path'] );
	}

	/**
	 * A block whose block.json declares `ancestor` is rejected unless one of
	 * those blocks is somewhere above it, and accepted when one is - even
	 * with another block in between.
	 */
	public function test_ancestor_placement_uses_block_metadata() {
		$outside = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array( array( 'name' => 'core/comments-title' ) ),
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_child_placement' ), $this->codes( $outside ) );
		$this->assertSame( 'blocks[0]', $outside[0]['path'] );

		$inside = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'        => 'core/comments',
						'innerBlocks' => array(
							array(
								'name'        => 'core/group',
								'innerBlocks' => array( array( 'name' => 'core/comments-title' ) ),
							),
						),
					),
				),
			)
		);
		$this->assertSame( array(), $inside );
	}

	/**
	 * A parent whose block.json declares `allowedBlocks` only accepts those
	 * children.
	 */
	public function test_allowed_blocks_placement_uses_block_metadata() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'        => 'core/list',
						'innerBlocks' => array(
							array( 'name' => 'core/list-item' ),
							array( 'name' => 'core/paragraph' ),
						),
					),
				),
			)
		);
		$this->assertSame( array( 'designsetgo_invalid_child_placement' ), $this->codes( $problems ) );
		$this->assertSame( 'blocks[0].innerBlocks[1]', $problems[0]['path'] );
	}

	/**
	 * Core containers the engine assembles validly are accepted: none of
	 * them has a PHP wrapper mirror, and none needs one.
	 */
	public function test_core_containers_are_accepted() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'        => 'core/list',
						'innerBlocks' => array(
							array(
								'name'       => 'core/list-item',
								'attributes' => array( 'content' => 'One' ),
							),
						),
					),
					array(
						'name'        => 'core/buttons',
						'innerBlocks' => array(
							array(
								'name'       => 'core/button',
								'attributes' => array( 'text' => 'Go' ),
							),
						),
					),
					array(
						'name'        => 'core/group',
						'innerBlocks' => array(
							array(
								'name'       => 'core/paragraph',
								'attributes' => array( 'content' => 'Inside a group' ),
							),
						),
					),
				),
			)
		);
		$this->assertSame( array(), $problems );
	}

	/**
	 * Valid designsetgo and core tree passes.
	 */
	public function test_valid_designsetgo_and_core_tree_passes() {
		$problems = Tree_Validator::validate(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'        => 'designsetgo/section',
						'innerBlocks' => array(
							array(
								'name'       => 'core/paragraph',
								'attributes' => array( 'content' => 'Hello there' ),
							),
							array(
								'name'        => 'designsetgo/accordion',
								'attributes'  => array( 'iconStyle' => 'chevron' ),
								'innerBlocks' => array(
									array(
										'name'        => 'designsetgo/accordion-item',
										'attributes'  => array(
											'title'  => 'Question',
											'isOpen' => false,
										),
										'innerBlocks' => array(
											array(
												'name' => 'core/paragraph',
												'attributes' => array( 'content' => 'Answer' ),
											),
										),
									),
								),
							),
						),
					),
				),
			)
		);
		$this->assertSame( array(), $problems );
	}
}

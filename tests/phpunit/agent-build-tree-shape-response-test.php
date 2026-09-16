<?php
/**
 * Tree_Shape::to_response_shape() reshapes a stored tree for JSON REST
 * responses so an empty `attributes` object round-trips as `{}`, not `[]` -
 * see Build_REST::get_item(), the only caller.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Agent_Build\Tree_Shape;

/**
 * Tree_Shape::to_response_shape() tests.
 *
 * @group abilities
 * @group agent-build
 */
class Agent_Build_Tree_Shape_Response_Test extends WP_UnitTestCase {

	/**
	 * A node with no explicit attribute overrides - the exact shape an
	 * agent submits for "use this block's defaults" - has an empty
	 * `attributes` array (PHP can't distinguish `{}` from `[]` after
	 * json_decode()). It must come back as `stdClass`, so `wp_json_encode()`
	 * emits `{}`.
	 */
	public function test_empty_node_attributes_become_stdclass(): void {
		$blocks = array(
			array(
				'name'       => 'designsetgo/section',
				'attributes' => array(),
			),
		);

		$result = Tree_Shape::to_response_shape( $blocks );

		$this->assertInstanceOf( \stdClass::class, $result[0]['attributes'] );
		$this->assertSame( '{}', wp_json_encode( $result[0]['attributes'] ) );
	}

	/**
	 * An attribute the block's own schema declares `type: object` for
	 * (e.g. `designsetgo/section`'s `style`) becomes `stdClass` when its
	 * value is an empty array, so it round-trips as `{}` too - not just the
	 * top-level `attributes` object.
	 */
	public function test_empty_object_typed_attribute_value_becomes_stdclass(): void {
		$blocks = array(
			array(
				'name'       => 'designsetgo/section',
				'attributes' => array(
					'style' => array(),
				),
			),
		);

		$result = Tree_Shape::to_response_shape( $blocks );

		$this->assertInstanceOf( \stdClass::class, $result[0]['attributes']['style'] );
		$this->assertSame( '{"style":{}}', wp_json_encode( $result[0]['attributes'] ) );
	}

	/**
	 * A non-empty associative `attributes` array is left exactly as-is -
	 * PHP's json_encode() already round-trips it as a JSON object, no
	 * reshaping needed.
	 */
	public function test_non_empty_attributes_are_unchanged(): void {
		$blocks = array(
			array(
				'name'       => 'core/paragraph',
				'attributes' => array( 'content' => 'Hello' ),
			),
		);

		$result = Tree_Shape::to_response_shape( $blocks );

		$this->assertSame( array( 'content' => 'Hello' ), $result[0]['attributes'] );
	}

	/**
	 * A node whose type isn't registered (or which has no `attributes` key
	 * at all) is left untouched rather than gaining a key it never had -
	 * to_response_shape() must not invent structure.
	 */
	public function test_node_without_attributes_key_is_untouched(): void {
		$blocks = array(
			array( 'name' => 'core/paragraph' ),
		);

		$result = Tree_Shape::to_response_shape( $blocks );

		$this->assertArrayNotHasKey( 'attributes', $result[0] );
	}

	/**
	 * `innerBlocks` are recursed into, so a nested node's empty `attributes`
	 * is reshaped exactly like a top-level one's.
	 */
	public function test_inner_blocks_are_normalized_recursively(): void {
		$blocks = array(
			array(
				'name'        => 'designsetgo/row',
				'attributes'  => array( 'gap' => '20px' ),
				'innerBlocks' => array(
					array(
						'name'       => 'designsetgo/icon-button',
						'attributes' => array(),
					),
				),
			),
		);

		$result = Tree_Shape::to_response_shape( $blocks );

		$this->assertInstanceOf(
			\stdClass::class,
			$result[0]['innerBlocks'][0]['attributes']
		);
	}

	/**
	 * `innerBlocks` itself always stays a plain list - even when empty, it
	 * must still encode as `[]`, not `{}`.
	 */
	public function test_empty_inner_blocks_list_stays_a_list(): void {
		$blocks = array(
			array(
				'name'        => 'designsetgo/section',
				'attributes'  => array(),
				'innerBlocks' => array(),
			),
		);

		$result = Tree_Shape::to_response_shape( $blocks );

		$this->assertSame( '[]', wp_json_encode( $result[0]['innerBlocks'] ) );
	}
}

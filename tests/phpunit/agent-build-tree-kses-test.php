<?php
/**
 * Tree_Kses filters a tree's attribute values with core's own block
 * attribute filter, filter_block_kses_value(), block context included.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Agent_Build\Tree_Kses;

/**
 * Tree_Kses tests.
 *
 * @group abilities
 * @group agent-build
 */
class Agent_Build_Tree_Kses_Test extends WP_UnitTestCase {

	/**
	 * Filter a one-node tree.
	 *
	 * @param string $name       Block name.
	 * @param array  $attributes Node attributes.
	 * @return array Filtered attributes.
	 */
	private function filter_node( string $name, array $attributes ): array {
		$tree = Tree_Kses::filter_tree(
			array(
				'version' => 1,
				'blocks'  => array(
					array(
						'name'       => $name,
						'attributes' => $attributes,
					),
				),
			)
		);

		return $tree['blocks'][0]['attributes'];
	}

	/**
	 * A template part's tagName gets core's block-context filtering: a tag
	 * the post context does not allow is emptied, not kept as plain text.
	 */
	public function test_template_part_tag_name_uses_core_block_context_filtering(): void {
		$attributes = $this->filter_node(
			'core/template-part',
			array(
				'slug'    => 'header',
				'tagName' => 'script',
			)
		);

		$this->assertSame( '', $attributes['tagName'] );
		$this->assertSame( 'header', $attributes['slug'] );
	}

	/**
	 * An allowed template part tagName is kept.
	 */
	public function test_allowed_template_part_tag_name_is_kept(): void {
		$this->assertSame( 'header', $this->filter_node( 'core/template-part', array( 'tagName' => 'header' ) )['tagName'] );
	}

	/**
	 * Nested attribute values are filtered, and non-strings pass through.
	 */
	public function test_nested_values_are_filtered_and_scalars_kept(): void {
		$attributes = $this->filter_node(
			'designsetgo/section',
			array(
				'style' => array( 'custom' => array( 'text' => 'Hi<script>alert(1)</script>' ) ),
				'level' => 2,
				'flag'  => true,
			)
		);

		$this->assertStringStartsWith( 'Hi', $attributes['style']['custom']['text'] );
		$this->assertStringNotContainsString( '<script', $attributes['style']['custom']['text'] );
		$this->assertSame( 2, $attributes['level'] );
		$this->assertTrue( $attributes['flag'] );
	}
}

<?php
/**
 * Lists and quotes inserted through the ability must match core's save() output.
 *
 * Expected markup was taken from wp.blocks.serialize() in the editor of the same
 * WordPress release: the list tag carries wp-block-list plus block-support classes,
 * a list item has no class and holds its text before any nested list, and a quote
 * closes with its citation after the inner blocks.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * @group abilities
 */
class Block_Inserter_Core_List_Quote_Test extends WP_UnitTestCase {

	public function test_unordered_list_with_nested_list_matches_save_output() {
		$markup = Block_Inserter::build_block_markup(
			'core/list',
			array(
				'className' => 'checklist',
				'anchor'    => 'perks',
				'fontSize'  => 'small',
				'style'     => array( 'spacing' => array( 'padding' => array( 'left' => '0' ) ) ),
			),
			array(
				array(
					'name'       => 'core/list-item',
					'attributes' => array( 'content' => 'One <strong>bold</strong>' ),
				),
				array(
					'name'         => 'core/list-item',
					'attributes'   => array( 'content' => 'Two' ),
					'inner_blocks' => array(
						array(
							'name'         => 'core/list',
							'attributes'   => array(),
							'inner_blocks' => array(
								array(
									'name'       => 'core/list-item',
									'attributes' => array( 'content' => 'Nested item' ),
								),
							),
						),
					),
				),
			)
		);
		$blocks = parse_blocks( $markup );
		$list   = $blocks[0];
		$this->assertSame( 'core/list', $list['blockName'] );
		$this->assertArrayNotHasKey( 'ordered', $list['attrs'] );
		$this->assertStringContainsString( 'class="wp-block-list checklist has-small-font-size"', $list['innerContent'][0] );
		$this->assertStringContainsString( 'id="perks"', $list['innerContent'][0] );
		$this->assertStringContainsString( 'style="padding-left:0"', $list['innerContent'][0] );
		$this->assertSame( '</ul>', end( $list['innerContent'] ) );
		$this->assertCount( 2, $list['innerBlocks'] );

		$first = $list['innerBlocks'][0];
		$this->assertSame( '<li>One <strong>bold</strong></li>', $first['innerHTML'] );
		$this->assertArrayNotHasKey( 'content', $first['attrs'] );

		$second = $list['innerBlocks'][1];
		$this->assertSame( '<li>Two', $second['innerContent'][0] );
		$this->assertNull( $second['innerContent'][1] );
		$this->assertSame( '</li>', $second['innerContent'][2] );
		$this->assertSame( '<ul class="wp-block-list">', $second['innerBlocks'][0]['innerContent'][0] );
		$this->assertSame( '<li>Nested item</li>', $second['innerBlocks'][0]['innerBlocks'][0]['innerHTML'] );
	}

	public function test_ordered_list_and_quote_with_citation_match_save_output() {
		$ordered = parse_blocks(
			Block_Inserter::build_block_markup(
				'core/list',
				array( 'ordered' => true ),
				array(
					array(
						'name'       => 'core/list-item',
						'attributes' => array( 'content' => 'Third' ),
					),
				)
			)
		)[0];
		$this->assertTrue( $ordered['attrs']['ordered'] );
		$this->assertSame( '<ol class="wp-block-list">', $ordered['innerContent'][0] );
		$this->assertSame( '</ol>', end( $ordered['innerContent'] ) );

		$quote = parse_blocks(
			Block_Inserter::build_block_markup(
				'core/quote',
				array(
					'className' => 'pull',
					'citation'  => 'A customer',
					'textColor' => 'contrast',
				),
				array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'Great work.' ),
					),
				)
			)
		)[0];
		$this->assertArrayNotHasKey( 'citation', $quote['attrs'] );
		// Block validation compares class lists as sets, so only membership matters here.
		$this->assertStringContainsString( 'wp-block-quote', $quote['innerContent'][0] );
		$this->assertStringContainsString( 'pull', $quote['innerContent'][0] );
		$this->assertStringContainsString( 'has-contrast-color', $quote['innerContent'][0] );
		$this->assertStringContainsString( 'has-text-color', $quote['innerContent'][0] );
		$this->assertNull( $quote['innerContent'][1] );
		$this->assertSame( '<cite>A customer</cite></blockquote>', $quote['innerContent'][2] );
		$this->assertSame( 'core/paragraph', $quote['innerBlocks'][0]['blockName'] );
		$this->assertSame( '<p>Great work.</p>', $quote['innerBlocks'][0]['innerHTML'] );
	}

	public function test_quote_without_citation_closes_cleanly() {
		$quote = parse_blocks(
			Block_Inserter::build_block_markup( 'core/quote', array(), array( array( 'name' => 'core/paragraph', 'attributes' => array( 'content' => 'Plain.' ) ) ) )
		)[0];
		$this->assertSame( '</blockquote>', end( $quote['innerContent'] ) );
		$this->assertStringNotContainsString( '<cite>', $quote['innerHTML'] );
	}
}

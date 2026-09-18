<?php
/**
 * Images inserted through the ability must match core/image save() output.
 *
 * Expected markup was checked with wp.blocks.parse() in the WordPress 7.1 editor:
 * border and shadow styles skip the figure and sit on the img, the caption follows
 * the image or its link, and sourced attributes stay out of the block comment.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * Core image serialization tests.
 *
 * @group abilities
 */
class Block_Inserter_Core_Image_Test extends WP_UnitTestCase {

	/**
	 * A captioned, bordered image keeps its caption and puts border styles on the img.
	 */
	public function test_captioned_image_with_border_and_focal_point_matches_save_output() {
		$blocks = parse_blocks(
			Block_Inserter::build_block_markup(
				'core/image',
				array(
					'className'   => 'ritual-frame',
					'url'         => 'https://example.test/a.jpg',
					'alt'         => 'Shampoo & care',
					'id'          => 42,
					'caption'     => 'Care <em>begins</em>',
					'aspectRatio' => '4 / 5',
					'scale'       => 'cover',
					'focalPoint'  => array(
						'x' => 0.5,
						'y' => 0.44,
					),
					'style'       => array(
						'spacing' => array( 'margin' => array( 'top' => '0' ) ),
						'border'  => array(
							'radius' => '20px',
							'width'  => '8px',
							'style'  => 'solid',
							'color'  => 'var:preset|color|rose',
						),
					),
				)
			)
		);
		$image  = $blocks[0];
		$html   = $image['innerHTML'];

		$this->assertSame( 'core/image', $image['blockName'] );
		foreach ( array( 'url', 'alt', 'caption' ) as $sourced ) {
			$this->assertArrayNotHasKey( $sourced, $image['attrs'] );
		}
		$this->assertStringContainsString( 'class="wp-block-image has-custom-border ritual-frame"', $html );
		$this->assertStringContainsString( 'style="margin-top:0"', $html );
		$this->assertStringContainsString( 'class="has-border-color wp-image-42"', $html );
		$this->assertStringContainsString(
			'style="border-radius:20px;border-style:solid;border-width:8px;border-color:var(--wp--preset--color--rose);aspect-ratio:4 / 5;object-fit:cover;object-position:50% 44%"',
			$html
		);
		$this->assertStringEndsWith( '/><figcaption class="wp-element-caption">Care <em>begins</em></figcaption></figure>', $html );
	}

	/**
	 * A linked, aligned image carries its link and alignment class like save() does.
	 */
	public function test_linked_aligned_image_without_caption_matches_save_output() {
		$blocks = parse_blocks(
			Block_Inserter::build_block_markup(
				'core/image',
				array(
					'url'    => 'https://example.test/b.jpg',
					'alt'    => '',
					'href'   => '/about/',
					'anchor' => 'pic',
					'align'  => 'right',
				)
			)
		);

		$this->assertSame(
			array(
				'anchor' => 'pic',
				'align'  => 'right',
			),
			$blocks[0]['attrs']
		);
		$this->assertStringContainsString( 'id="pic"', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'class="wp-block-image alignright"', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( '<a href="/about/"><img src="https://example.test/b.jpg" alt=""/></a></figure>', $blocks[0]['innerHTML'] );
	}
}

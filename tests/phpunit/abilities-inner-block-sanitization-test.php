<?php
/**
 * Inner blocks must be sanitized by the same path as every other insertion.
 *
 * Block_Inserter::build_inner_blocks() is the only route by which a block
 * reaches storage WITHOUT going through Block_Configurator::sanitize_attributes()
 * — it is used by the add-tab ability to build a tab's children. It called a
 * second, weaker sanitizer on Block_Inserter that ran sanitize_text_field()
 * over every string and took no block name, so it could not consult the
 * rich-text policy at all.
 *
 * The effect was a split personality: inserting a card at the top level kept
 * `Care <em>begins</em>`, and inserting the same card inside a tab silently
 * flattened it to `Care begins`. A second implementation of a rule is exactly
 * how the drift this branch exists to prevent starts, so the weaker one is now
 * a delegation rather than a rule of its own.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * Inner-block sanitization parity test.
 */
class Abilities_Inner_Block_Sanitization_Test extends WP_UnitTestCase {

	/**
	 * Inline markup on a rich-text attribute survives into an inner block.
	 */
	public function test_inner_blocks_keep_inline_markup_on_rich_text_attributes(): void {
		$built = Block_Inserter::build_inner_blocks(
			array(
				array(
					'name'       => 'designsetgo/card',
					'attributes' => array(
						'title' => 'Care <em>begins</em> here',
					),
				),
			)
		);

		$this->assertCount( 1, $built );
		$this->assertStringContainsString(
			'<em>',
			(string) $built[0]['attrs']['title'],
			'card::title is classified inline, so a tab\'s child card must keep its markup too.'
		);
	}

	/**
	 * A plain-text attribute is still stripped inside an inner block.
	 */
	public function test_inner_blocks_still_strip_markup_from_plain_attributes(): void {
		$built = Block_Inserter::build_inner_blocks(
			array(
				array(
					'name'       => 'designsetgo/card',
					'attributes' => array(
						// badgeText is `source: text`, so it is plain by policy.
						'badgeText' => 'Sale <em>now</em>',
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<em>', (string) $built[0]['attrs']['badgeText'] );
	}

	/**
	 * Scripts are removed whatever the policy.
	 */
	public function test_inner_blocks_drop_scripts(): void {
		$built = Block_Inserter::build_inner_blocks(
			array(
				array(
					'name'       => 'designsetgo/card',
					'attributes' => array(
						'title' => 'Hi <script>alert(1)</script>',
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<script', (string) $built[0]['attrs']['title'] );
	}

	/**
	 * Nested inner blocks take the same path.
	 */
	public function test_nested_inner_blocks_are_sanitized_too(): void {
		$built = Block_Inserter::build_inner_blocks(
			array(
				array(
					'name'        => 'designsetgo/row',
					'attributes'  => array(),
					'innerBlocks' => array(
						array(
							'name'       => 'designsetgo/card',
							'attributes' => array( 'title' => 'Deep <em>markup</em>' ),
						),
					),
				),
			)
		);

		$this->assertStringContainsString(
			'<em>',
			(string) $built[0]['innerBlocks'][0]['attrs']['title']
		);
	}

	/**
	 * Non-string values are preserved unchanged.
	 */
	public function test_non_string_values_survive(): void {
		$built = Block_Inserter::build_inner_blocks(
			array(
				array(
					'name'       => 'designsetgo/grid',
					'attributes' => array(
						'desktopColumns' => 3,
						'matchRowHeights' => true,
					),
				),
			)
		);

		$this->assertSame( 3, $built[0]['attrs']['desktopColumns'] );
		$this->assertTrue( $built[0]['attrs']['matchRowHeights'] );
	}
}

<?php
/** Batch layout patches synchronize markup and refuse invalid batches atomically.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;
use DesignSetGo\Abilities\Configurators\Batch_Update;
use DesignSetGo\Layout_Support;

/** Test the separate public batch writer. */
class Layout_Updater_Batch_Test extends WP_UnitTestCase {

	/** A permitted author can patch a page. */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Create two authored Grid blocks for filter and atomic batch checks.
	 *
	 * @return int Page ID.
	 */
	private function page(): int {
		$markup = Block_Inserter::build_block_markup( 'designsetgo/grid', array( 'mobileColumnTemplate' => '1fr' ) );
		$first  = parse_blocks( $markup )[0];
		$second = $first;
		$first['attrs']['clientId']  = 'first';
		$second['attrs']['clientId'] = 'second';
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => wp_slash( serialize_blocks( array( $first, $second ) ) ),
			)
		);
	}

	/** A filter scopes class and inner-style updates to the matching owning root. */
	public function test_filtered_batch_syncs_only_the_selected_grid(): void {
		$id     = $this->page();
		$before = parse_blocks( get_post( $id )->post_content );
		$layout = array( 'mobile' => array( 'gap' => '12px' ) );
		$result = ( new Batch_Update() )->execute(
			array(
				'post_id'    => $id,
				'operations' => array(
					array(
						'block_name' => 'designsetgo/grid',
						'attributes' => array(
							'dsgoLayout' => $layout,
							'mobileColumnTemplate' => '2fr 1fr',
						),
						'filter'     => array( 'clientId' => 'second' ),
					),
				),
			)
		);
		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['total_updated'] );
		$after = parse_blocks( get_post( $id )->post_content );
		$this->assertSame( $before[0], $after[0] );
		$this->assertStringContainsString( Layout_Support::class_name( 'designsetgo/grid', $layout ), $after[1]['innerHTML'] );
		$this->assertStringContainsString( '--dsgo-grid-columns-mobile:2fr 1fr', $after[1]['innerHTML'] );
	}

	/** A later unsafe request refuses the entire batch, even after a valid operation. */
	public function test_later_unsafe_patch_refuses_entire_batch(): void {
		$id      = $this->page();
		$content = get_post( $id )->post_content;
		$result  = ( new Batch_Update() )->execute(
			array(
				'post_id'    => $id,
				'operations' => array(
					array(
						'block_name' => 'designsetgo/grid',
						'attributes' => array( 'mobileColumnTemplate' => '2fr 1fr' ),
					),
					array(
						'block_name' => 'designsetgo/grid',
						'attributes' => array( 'dsgoLayout' => array( 'mobile' => array( 'gap' => '<b>12px</b>' ) ) ),
					),
				),
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( $content, get_post( $id )->post_content );
	}
}

<?php
/** Pure serialization must match insertion without touching posts.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Abilities_Registry;
use DesignSetGo\Abilities\Inserters\Add_Blocks;

/** Regression coverage for abilities serialize blocks test. */
class Abilities_Serialize_Blocks_Test extends WP_UnitTestCase {

	/** Set up. */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/** Ability. */
	private function ability() {
		$ability = Abilities_Registry::get_instance()->get_ability( 'designsetgo/serialize-blocks' );
		$this->assertNotNull( $ability, 'Pure serialization must be discoverable.' );
		return $ability;
	}

	/** Blocks. */
	private function blocks(): array {
		return array(
			array(
				'block_name'   => 'designsetgo/grid',
				'attributes'   => array( 'columnTemplate' => 'minmax(0, 1fr) minmax(0, 2fr)' ),
				'inner_blocks' => array(
					array(
						'name'       => 'designsetgo/icon-button',
						'attributes' => array(
							'text' => 'Our <em>work</em>',
							'url'  => '#work',
						),
					),
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'A "quoted" line &amp; <strong>more</strong>.' ),
					),
				),
			),
			array(
				'block_name' => 'designsetgo/form-email-field',
				'attributes' => array(
					'label'    => 'Email',
					'required' => true,
				),
			),
		);
	}

	/** Test matches batch insertion without writes or dynamic rendering. */
	public function test_matches_batch_insertion_without_writes_or_dynamic_rendering(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '',
			)
		);
		$blocks  = $this->blocks();
		$added   = ( new Add_Blocks() )->run(
			array(
				'post_id' => $post_id,
				'blocks'  => $blocks,
			)
		);
		$this->assertTrue( $added['success'] );
		$writes       = 0;
		$renders      = 0;
		$count_write  = static function () use ( &$writes ): void {
			++$writes;
		};
		$count_render = static function ( $content ) use ( &$renders ) {
			++$renders;
			return $content;
		};
		add_action( 'save_post', $count_write );
		add_filter( 'render_block', $count_render );
		try {
			$result = $this->ability()->run( array( 'blocks' => $blocks ) );
		} finally {
			remove_action( 'save_post', $count_write );
			remove_filter( 'render_block', $count_render );
		}
		$this->assertTrue( $result['success'] );
		$this->assertSame( get_post_field( 'post_content', $post_id ), $result['content'] );
		$this->assertSame( 2, $result['block_count'] );
		$this->assertSame( 0, $writes );
		$this->assertSame( 0, $renders );
		$this->assertStringContainsString( 'Our <em>work</em>', $result['content'] );
		$this->assertStringContainsString( '"required":true', $result['content'] );
	}

	/** Test nested refusals keep the index and return no partial content. */
	public function test_nested_refusals_keep_the_index_and_return_no_partial_content(): void {
		$result = $this->ability()->run(
			array(
				'blocks' => array(
					array(
						'block_name' => 'core/paragraph',
						'attributes' => array( 'content' => 'Valid' ),
					),
					array(
						'block_name'   => 'designsetgo/grid',
						'inner_blocks' => array( array( 'name' => 'missing/block' ) ),
					),
				),
			)
		);
		$this->assertFalse( $result['success'] );
		$this->assertSame( 1, $result['block_index'] );
		$this->assertStringContainsString( 'blocks[1]', $result['message'] );
		$this->assertArrayNotHasKey( 'content', $result );
	}

	/** Test schema errors are diagnostics. */
	public function test_schema_errors_are_diagnostics(): void {
		$ability = $this->ability();
		foreach ( array(
			array(),
			array( 'blocks' => array() ),
			array(
				'blocks' => array(
					array(
						'block_name' => 'core/paragraph',
						'unexpected' => true,
					),
				),
			),
		) as $input ) {
			$result = $ability->run( $input );
			$this->assertFalse( $result['success'] );
			$this->assertArrayNotHasKey( 'content', $result );
		}
	}

	/** Test top level limit is enforced. */
	public function test_top_level_limit_is_enforced(): void {
		$result = $this->ability()->run( array( 'blocks' => array_fill( 0, 51, array( 'block_name' => 'core/paragraph' ) ) ) );
		$this->assertFalse( $result['success'] );
	}

	/** Test permission and annotations. */
	public function test_permission_and_annotations(): void {
		$ability = $this->ability();
		$this->assertTrue( $ability->check_permission_callback() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( $ability->check_permission_callback() );
		wp_set_current_user( 0 );
		$this->assertFalse( $ability->check_permission_callback() );
		$config = $ability->get_config();
		$this->assertTrue( $config['annotations']['readonly'] );
		$this->assertTrue( $config['annotations']['idempotent'] );
		$this->assertFalse( $config['annotations']['destructive'] );
	}
}

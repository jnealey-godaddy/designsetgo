<?php
/**
 * Tests for the batch top-level inserter (designsetgo/add-blocks).
 *
 * A generation run stages a page's sections one call at a time; the batch
 * ability must produce exactly the content those sequential add-block calls
 * produce, write it once, and refuse the whole batch when any entry is bad.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Inserters\Add_Block;
use DesignSetGo\Abilities\Inserters\Add_Blocks;
use DesignSetGo\Abilities\Info\List_Abilities;

/**
 * Add blocks tests.
 */
class Abilities_Add_Blocks_Test extends WP_UnitTestCase {

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
	 * Create a page.
	 *
	 * @param string $content Initial content.
	 * @return int Post ID.
	 */
	private function create_page( string $content = '' ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => $content,
				'post_author'  => $this->editor_id,
			)
		);
	}

	/**
	 * Three sections with nested content, as a generated page stages them.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sections(): array {
		return array(
			array(
				'block_name'   => 'designsetgo/section',
				'attributes'   => array( 'className' => 'hero' ),
				'inner_blocks' => array(
					array(
						'name'       => 'core/heading',
						'attributes' => array(
							'level'   => 1,
							'content' => 'Harbor Point',
						),
					),
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'Boat repair on the "north" dock.' ),
					),
				),
			),
			array(
				'block_name'   => 'designsetgo/section',
				'inner_blocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'Second section' ),
					),
				),
			),
			array(
				'block_name' => 'core/paragraph',
				'attributes' => array( 'content' => 'Closing line' ),
			),
		);
	}

	/**
	 * The batch writes byte-identical content to one add-block call per section.
	 */
	public function test_batch_matches_sequential_add_block_content(): void {
		$sequential = $this->create_page();
		$add        = new Add_Block();
		foreach ( $this->sections() as $section ) {
			$result = $add->run( array_merge( array( 'post_id' => $sequential ), $section ) );
			$this->assertTrue( $result['success'] );
		}

		$batched = $this->create_page();
		$result  = ( new Add_Blocks() )->run(
			array(
				'post_id' => $batched,
				'blocks'  => $this->sections(),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 3, $result['inserted'] );
		$this->assertSame( get_post( $sequential )->post_content, get_post( $batched )->post_content );
	}

	/**
	 * The whole batch is saved once, not once per block.
	 */
	public function test_batch_saves_the_post_once(): void {
		$post_id = $this->create_page();
		$saves   = 0;
		// Revisions fire save_post too; count writes to the page itself.
		$count = static function ( int $saved_id ) use ( &$saves, $post_id ): void {
			if ( $saved_id === $post_id ) {
				++$saves;
			}
		};
		add_action( 'save_post', $count );

		( new Add_Blocks() )->run(
			array(
				'post_id' => $post_id,
				'blocks'  => $this->sections(),
			)
		);

		remove_action( 'save_post', $count );
		$this->assertSame( 1, $saves );
	}

	/**
	 * A bad entry anywhere refuses the batch, names its index and writes nothing.
	 */
	public function test_invalid_entry_refuses_the_whole_batch(): void {
		$post_id = $this->create_page();
		$blocks  = $this->sections();

		$blocks[1]['inner_blocks'] = array(
			array(
				'name'        => 'core/heading',
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'Not a heading child' ),
					),
				),
			),
		);

		$result = ( new Add_Blocks() )->run(
			array(
				'post_id' => $post_id,
				'blocks'  => $blocks,
			)
		);

		$this->assertIsArray( $result, 'Refusals must survive the MCP bridge as data, not WP_Error.' );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 1, $result['block_index'] );
		$this->assertStringStartsWith( 'blocks[1]:', $result['message'] );
		$this->assertSame( '', get_post( $post_id )->post_content, 'A refused batch must not write.' );
	}

	/**
	 * Unknown keys and malformed names are refused with the entry index.
	 */
	public function test_malformed_entries_name_their_index(): void {
		$post_id = $this->create_page();
		$ability = new Add_Blocks();

		$unknown = $ability->run(
			array(
				'post_id' => $post_id,
				'blocks'  => array(
					array( 'block_name' => 'core/paragraph' ),
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'x' ),
					),
				),
			)
		);
		$this->assertFalse( $unknown['success'] );
		$this->assertSame( 1, $unknown['block_index'] );
		$this->assertStringContainsString( 'name', $unknown['message'] );

		$bad_name = $ability->run(
			array(
				'post_id' => $post_id,
				'blocks'  => array( array( 'block_name' => 'Paragraph' ) ),
			)
		);
		$this->assertFalse( $bad_name['success'] );
		$this->assertSame( 0, $bad_name['block_index'] );

		$this->assertSame( '', get_post( $post_id )->post_content );
	}

	/**
	 * An empty batch is refused.
	 */
	public function test_empty_batch_is_refused(): void {
		$result = ( new Add_Blocks() )->run(
			array(
				'post_id' => $this->create_page(),
				'blocks'  => array(),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'designsetgo_invalid_input', $result->get_error_code() );
	}

	/**
	 * Position places the batch as a run, keeping its internal order.
	 */
	public function test_prepend_keeps_batch_order_before_existing_blocks(): void {
		$post_id = $this->create_page( '<!-- wp:paragraph --><p>Existing</p><!-- /wp:paragraph -->' );

		( new Add_Blocks() )->run(
			array(
				'post_id'  => $post_id,
				'position' => 0,
				'blocks'   => array(
					array(
						'block_name' => 'core/paragraph',
						'attributes' => array( 'content' => 'First' ),
					),
					array(
						'block_name' => 'core/paragraph',
						'attributes' => array( 'content' => 'Second' ),
					),
				),
			)
		);

		$content = get_post( $post_id )->post_content;
		$this->assertLessThan( strpos( $content, 'Second' ), strpos( $content, 'First' ) );
		$this->assertLessThan( strpos( $content, 'Existing' ), strpos( $content, 'Second' ) );
	}

	/**
	 * Callers detect the batch inserter by name, so it must be registered.
	 */
	public function test_batch_ability_is_registered(): void {
		$result = ( new List_Abilities() )->execute( array( 'category' => 'all' ) );

		$this->assertContains( 'designsetgo/add-blocks', array_column( $result['abilities'], 'name' ) );
	}
}

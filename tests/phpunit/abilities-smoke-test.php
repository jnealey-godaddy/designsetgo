<?php
/**
 * Smoke tests for DesignSetGo Abilities API.
 *
 * Verifies each ability endpoint responds correctly with valid inputs:
 * list-abilities, get-post-blocks, list-blocks, list-extensions,
 * update-block, configure-shape-divider, add-block, and add-child-block.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Abilities_Registry;
use DesignSetGo\Abilities\Info\List_Abilities;
use DesignSetGo\Abilities\Info\Get_Post_Blocks;
use DesignSetGo\Abilities\Info\List_Blocks;
use DesignSetGo\Abilities\Configurators\Update_Block;
use DesignSetGo\Abilities\Configurators\Configure_Shape_Divider;
use DesignSetGo\Abilities\Inserters\Add_Child_Block;

/**
 * Abilities Smoke Test class.
 */
class Abilities_Smoke_Test extends WP_UnitTestCase {

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
	 * Create a post with block content.
	 *
	 * @param string $content Block content string.
	 * @return int Post ID.
	 */
	private function create_block_post( string $content ): int {
		return self::factory()->post->create(
			array(
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_author'  => $this->editor_id,
			)
		);
	}

	// -------------------------------------------------------------------------
	// List Abilities
	// -------------------------------------------------------------------------

	/**
	 * Test that list-abilities returns a non-empty array of abilities.
	 */
	public function test_list_abilities_returns_abilities(): void {
		$ability = new List_Abilities();
		$result  = $ability->execute( array( 'category' => 'all' ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'abilities', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertGreaterThan( 0, $result['total'] );

		// Each ability should have required fields.
		$first = $result['abilities'][0];
		$this->assertArrayHasKey( 'name', $first );
		$this->assertArrayHasKey( 'label', $first );
		$this->assertArrayHasKey( 'description', $first );
		$this->assertArrayHasKey( 'category', $first );
		$this->assertArrayHasKey( 'input_schema', $first );
	}

	/**
	 * Test that list-abilities filters by category.
	 */
	public function test_list_abilities_filters_by_category(): void {
		$ability = new List_Abilities();

		$all_result  = $ability->execute( array( 'category' => 'all' ) );
		$info_result = $ability->execute( array( 'category' => 'info' ) );

		// Info subset should be smaller than all.
		$this->assertLessThanOrEqual( $all_result['total'], $info_result['total'] );

		// All returned abilities should be in the 'info' category.
		foreach ( $info_result['abilities'] as $ab ) {
			$this->assertSame( 'info', $ab['category'] );
		}
	}

	/**
	 * Test that known abilities are present in the registry.
	 */
	public function test_list_abilities_contains_new_abilities(): void {
		$ability = new List_Abilities();
		$result  = $ability->execute( array( 'category' => 'all' ) );
		$names   = array_column( $result['abilities'], 'name' );

		$expected = array(
			'designsetgo/list-abilities',
			'designsetgo/get-post-blocks',
			'designsetgo/list-blocks',
			'designsetgo/list-extensions',
			'designsetgo/update-block',
			'designsetgo/configure-shape-divider',
			'designsetgo/add-block',
			'designsetgo/add-child-block',
		);

		foreach ( $expected as $name ) {
			$this->assertContains( $name, $names, "Expected ability '$name' to be registered." );
		}
	}

	// -------------------------------------------------------------------------
	// Get Post Blocks
	// -------------------------------------------------------------------------

	/**
	 * Test that get-post-blocks returns blockIndex on each block.
	 */
	public function test_get_post_blocks_returns_block_index(): void {
		$post_id = $this->create_block_post(
			'<!-- wp:core/paragraph --><p>Hello</p><!-- /wp:core/paragraph -->'
		);

		$ability = new Get_Post_Blocks();
		$result  = $ability->execute( array( 'post_id' => $post_id ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertCount( 1, $result['blocks'] );
		$this->assertSame( 0, $result['blocks'][0]['blockIndex'] );
		$this->assertSame( 'core/paragraph', $result['blocks'][0]['blockName'] );
	}

	/**
	 * Test depth-first document-order indexing with nested blocks.
	 */
	public function test_get_post_blocks_depth_first_indexing(): void {
		$content = '<!-- wp:core/group --><div class="wp-block-group">'
			. '<!-- wp:core/paragraph --><p>Inner</p><!-- /wp:core/paragraph -->'
			. '</div><!-- /wp:core/group -->'
			. '<!-- wp:core/paragraph --><p>Outer</p><!-- /wp:core/paragraph -->';

		$post_id = $this->create_block_post( $content );

		$ability = new Get_Post_Blocks();
		$result  = $ability->execute( array( 'post_id' => $post_id ) );

		$this->assertTrue( $result['success'] );

		// Top-level: group at 0, outer paragraph at 2.
		$this->assertSame( 0, $result['blocks'][0]['blockIndex'] );
		$this->assertSame( 'core/group', $result['blocks'][0]['blockName'] );

		// Inner paragraph at 1 (depth-first).
		$inner = $result['blocks'][0]['innerBlocks'][0];
		$this->assertSame( 1, $inner['blockIndex'] );
		$this->assertSame( 'core/paragraph', $inner['blockName'] );

		// Outer paragraph at 2.
		$this->assertSame( 2, $result['blocks'][1]['blockIndex'] );
		$this->assertSame( 'core/paragraph', $result['blocks'][1]['blockName'] );
	}

	/**
	 * Test filtering blocks by name.
	 */
	public function test_get_post_blocks_filters_by_block_name(): void {
		$content = '<!-- wp:core/heading --><h2>Title</h2><!-- /wp:core/heading -->'
			. '<!-- wp:core/paragraph --><p>Text</p><!-- /wp:core/paragraph -->';

		$post_id = $this->create_block_post( $content );

		$ability = new Get_Post_Blocks();
		$result  = $ability->execute(
			array(
				'post_id'    => $post_id,
				'block_name' => 'core/paragraph',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['total'] );
		$this->assertSame( 'core/paragraph', $result['blocks'][0]['blockName'] );
	}

	/**
	 * Test flatten option returns a flat list with depth tracking.
	 */
	public function test_get_post_blocks_flatten(): void {
		$content = '<!-- wp:core/group --><div class="wp-block-group">'
			. '<!-- wp:core/paragraph --><p>Inner</p><!-- /wp:core/paragraph -->'
			. '</div><!-- /wp:core/group -->';

		$post_id = $this->create_block_post( $content );

		$ability = new Get_Post_Blocks();
		$result  = $ability->execute(
			array(
				'post_id' => $post_id,
				'flatten' => true,
			)
		);

		$this->assertTrue( $result['success'] );
		// Flattened: group + paragraph = 2 blocks at top level.
		$this->assertSame( 2, $result['total'] );
		$this->assertSame( 0, $result['blocks'][0]['depth'] );
		$this->assertSame( 1, $result['blocks'][1]['depth'] );
	}

	// -------------------------------------------------------------------------
	// List Blocks
	// -------------------------------------------------------------------------

	/**
	 * Test that list-blocks returns DesignSetGo blocks.
	 */
	public function test_list_blocks_returns_blocks(): void {
		$ability = new List_Blocks();
		$result  = $ability->execute( array( 'category' => 'all' ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'blocks', $result );
		$this->assertArrayHasKey( 'total', $result );

		// Should only contain designsetgo/* blocks.
		foreach ( $result['blocks'] as $block ) {
			$this->assertStringStartsWith( 'designsetgo/', $block['name'] );
			$this->assertArrayHasKey( 'title', $block );
			$this->assertArrayHasKey( 'attributes', $block );
		}
	}

	/**
	 * Test that full detail includes extra schema fields.
	 */
	public function test_list_blocks_full_detail_includes_extra_fields(): void {
		$ability = new List_Blocks();

		$summary = $ability->execute( array( 'detail' => 'summary' ) );
		$full    = $ability->execute( array( 'detail' => 'full' ) );

		// Same number of blocks.
		$this->assertSame( $summary['total'], $full['total'] );

		if ( $full['total'] > 0 ) {
			// Full detail may include fields like minimum, maximum, items, properties.
			$full_block  = $full['blocks'][0];
			$full_json   = wp_json_encode( $full_block['attributes'] );
			$summary_json = wp_json_encode( $summary['blocks'][0]['attributes'] );

			// Full detail should be at least as long (likely longer) than summary.
			$this->assertGreaterThanOrEqual( strlen( $summary_json ), strlen( $full_json ) );
		}
	}

	/**
	 * Test filtering list-blocks by specific block names.
	 */
	public function test_list_blocks_filters_by_block_names(): void {
		$ability = new List_Blocks();

		// Get all blocks first.
		$all = $ability->execute( array() );

		if ( $all['total'] < 2 ) {
			$this->markTestSkipped( 'Need at least 2 DesignSetGo blocks registered.' );
		}

		// Filter to just the first block.
		$first_name = $all['blocks'][0]['name'];
		$filtered   = $ability->execute(
			array(
				'blocks' => array( $first_name ),
			)
		);

		$this->assertSame( 1, $filtered['total'] );
		$this->assertSame( $first_name, $filtered['blocks'][0]['name'] );
	}

	// -------------------------------------------------------------------------
	// Configure Block Attributes
	// -------------------------------------------------------------------------

	/**
	 * Test updating a block's attributes by document-order index.
	 */
	public function test_configure_block_attributes_by_index(): void {
		$content = '<!-- wp:core/paragraph --><p>Hello</p><!-- /wp:core/paragraph -->';
		$post_id = $this->create_block_post( $content );

		$ability = new Update_Block();
		$result  = $ability->execute(
			array(
				'post_id'     => $post_id,
				'block_index' => 0,
				'block_name'  => 'core/paragraph',
				'attributes'  => array(
					'className' => 'test-class',
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['updated_count'] );
		$this->assertSame( 'core/paragraph', $result['block_name'] );
	}

	/**
	 * Test updating a block's attributes by block name.
	 */
	public function test_configure_block_attributes_by_name(): void {
		$content = '<!-- wp:core/paragraph --><p>Hello</p><!-- /wp:core/paragraph -->';
		$post_id = $this->create_block_post( $content );

		$ability = new Update_Block();
		$result  = $ability->execute(
			array(
				'post_id'    => $post_id,
				'block_name' => 'core/paragraph',
				'attributes' => array(
					'className' => 'styled',
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['updated_count'] );
	}

	/**
	 * Test that attribute changes persist in the saved post content.
	 */
	public function test_configure_block_attributes_persists_changes(): void {
		$content = '<!-- wp:core/paragraph --><p>Hello</p><!-- /wp:core/paragraph -->';
		$post_id = $this->create_block_post( $content );

		$ability = new Update_Block();
		$ability->execute(
			array(
				'post_id'     => $post_id,
				'block_index' => 0,
				'attributes'  => array(
					'className' => 'persisted-class',
				),
			)
		);

		// Re-read the post and verify the attribute is present.
		$post   = get_post( $post_id );
		$blocks = parse_blocks( $post->post_content );

		// Filter out freeform blocks.
		$blocks = array_values(
			array_filter(
				$blocks,
				function ( $b ) {
					return ! empty( $b['blockName'] );
				}
			)
		);

		$this->assertSame( 'persisted-class', $blocks[0]['attrs']['className'] );
	}

	/**
	 * Test style attribute (nested object) updates work.
	 */
	public function test_configure_block_attributes_style_object(): void {
		$content = '<!-- wp:core/paragraph --><p>Hello</p><!-- /wp:core/paragraph -->';
		$post_id = $this->create_block_post( $content );

		$ability = new Update_Block();
		$result  = $ability->execute(
			array(
				'post_id'     => $post_id,
				'block_index' => 0,
				'attributes'  => array(
					'style' => array(
						'spacing' => array(
							'padding' => array( 'top' => '20px' ),
						),
					),
				),
			)
		);

		$this->assertTrue( $result['success'] );

		// Verify persistence.
		$post   = get_post( $post_id );
		$blocks = parse_blocks( $post->post_content );
		$blocks = array_values( array_filter( $blocks, fn( $b ) => ! empty( $b['blockName'] ) ) );

		$this->assertSame( '20px', $blocks[0]['attrs']['style']['spacing']['padding']['top'] );
	}

	// -------------------------------------------------------------------------
	// Configure Shape Divider
	// -------------------------------------------------------------------------

	/**
	 * Test applying a shape divider to a section block.
	 */
	public function test_configure_shape_divider_applies_shape(): void {
		$content = '<!-- wp:designsetgo/section -->'
			. '<div class="wp-block-designsetgo-section"></div>'
			. '<!-- /wp:designsetgo/section -->';
		$post_id = $this->create_block_post( $content );

		$ability = new Configure_Shape_Divider();
		$result  = $ability->execute(
			array(
				'post_id'     => $post_id,
				'block_index' => 0,
				'shape'       => 'wave',
				'position'    => 'bottom',
				'color'       => '#ffffff',
				'height'      => 100,
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );

		// Verify the attributes were saved.
		$post   = get_post( $post_id );
		$blocks = parse_blocks( $post->post_content );
		$blocks = array_values( array_filter( $blocks, fn( $b ) => ! empty( $b['blockName'] ) ) );

		$this->assertSame( 'wave', $blocks[0]['attrs']['shapeDividerBottom'] );
		$this->assertSame( '#ffffff', $blocks[0]['attrs']['shapeDividerBottomColor'] );
		$this->assertSame( 100, $blocks[0]['attrs']['shapeDividerBottomHeight'] );
		// Clearance is NOT stored as a snapshot attribute — it is derived live
		// from the divider's rendered height by the stylesheet fallback, so an
		// omitted spacing still clears content matched to the height and a
		// partial follow-up call can't stale it.
		$this->assertArrayNotHasKey( 'shapeDividerBottomSpacing', $blocks[0]['attrs'] );
	}

	/**
	 * A partial follow-up call (no height) must not disturb a previously-set
	 * divider height or introduce a stale clearance snapshot.
	 */
	public function test_configure_shape_divider_partial_update_preserves_height(): void {
		$content = '<!-- wp:designsetgo/section -->'
			. '<div class="wp-block-designsetgo-section"></div>'
			. '<!-- /wp:designsetgo/section -->';
		$post_id = $this->create_block_post( $content );

		$ability = new Configure_Shape_Divider();

		// First call establishes a tall divider.
		$ability->execute(
			array(
				'post_id'     => $post_id,
				'block_index' => 0,
				'shape'       => 'wave',
				'position'    => 'bottom',
				'height'      => 300,
			)
		);

		// Second call tweaks only the flip, without re-passing height.
		$result = $ability->execute(
			array(
				'post_id'     => $post_id,
				'block_index' => 0,
				'shape'       => 'wave',
				'position'    => 'bottom',
				'flipX'       => true,
			)
		);
		$this->assertTrue( $result['success'] );

		$post   = get_post( $post_id );
		$blocks = parse_blocks( $post->post_content );
		$blocks = array_values( array_filter( $blocks, fn( $b ) => ! empty( $b['blockName'] ) ) );

		// Height is preserved (patch semantics) and no stale clearance snapshot
		// was written that would under-reserve against the 300px divider.
		$this->assertSame( 300, $blocks[0]['attrs']['shapeDividerBottomHeight'] );
		$this->assertTrue( $blocks[0]['attrs']['shapeDividerBottomFlipX'] );
		$this->assertArrayNotHasKey( 'shapeDividerBottomSpacing', $blocks[0]['attrs'] );
	}

	/**
	 * Test applying shape divider to both positions.
	 */
	public function test_configure_shape_divider_both_positions(): void {
		$content = '<!-- wp:designsetgo/section -->'
			. '<div class="wp-block-designsetgo-section"></div>'
			. '<!-- /wp:designsetgo/section -->';
		$post_id = $this->create_block_post( $content );

		$ability = new Configure_Shape_Divider();
		$result  = $ability->execute(
			array(
				'post_id'     => $post_id,
				'block_index' => 0,
				'shape'       => 'triangle',
				'position'    => 'both',
				'flipX'       => true,
			)
		);

		$this->assertTrue( $result['success'] );

		$post   = get_post( $post_id );
		$blocks = parse_blocks( $post->post_content );
		$blocks = array_values( array_filter( $blocks, fn( $b ) => ! empty( $b['blockName'] ) ) );

		$this->assertSame( 'triangle', $blocks[0]['attrs']['shapeDividerTop'] );
		$this->assertSame( 'triangle', $blocks[0]['attrs']['shapeDividerBottom'] );
		$this->assertTrue( $blocks[0]['attrs']['shapeDividerTopFlipX'] );
		$this->assertTrue( $blocks[0]['attrs']['shapeDividerBottomFlipX'] );
	}

	// -------------------------------------------------------------------------
	// Insert Block Into
	// -------------------------------------------------------------------------

	/**
	 * Test inserting a child block into a parent by index.
	 */
	public function test_insert_block_into_adds_child(): void {
		$content = '<!-- wp:core/group --><div class="wp-block-group"></div><!-- /wp:core/group -->';
		$post_id = $this->create_block_post( $content );

		$ability = new Add_Child_Block();
		$result  = $ability->execute(
			array(
				'post_id'            => $post_id,
				'parent_block_index' => 0,
				'block_name'         => 'core/paragraph',
				'attributes'         => array( 'content' => 'Inserted' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 0, $result['parent_block_index'] );
		$this->assertSame( 'core/paragraph', $result['block_name'] );
	}

	/**
	 * Test that an inserted block survives serialize_blocks() round-trip.
	 *
	 * This is the critical test: innerContent must be updated alongside
	 * innerBlocks, or serialize_blocks() silently drops the new block.
	 */
	public function test_insert_block_survives_serialize(): void {
		$content = '<!-- wp:core/group --><div class="wp-block-group"></div><!-- /wp:core/group -->';
		$post_id = $this->create_block_post( $content );

		$ability = new Add_Child_Block();
		$ability->execute(
			array(
				'post_id'            => $post_id,
				'parent_block_index' => 0,
				'block_name'         => 'core/paragraph',
			)
		);

		// Re-read the post and parse blocks again.
		$post   = get_post( $post_id );
		$blocks = parse_blocks( $post->post_content );
		$blocks = array_values( array_filter( $blocks, fn( $b ) => ! empty( $b['blockName'] ) ) );

		// The group should now have an inner block.
		$this->assertNotEmpty( $blocks[0]['innerBlocks'], 'Inner block was silently dropped by serialize_blocks().' );

		$inner = array_values( array_filter( $blocks[0]['innerBlocks'], fn( $b ) => ! empty( $b['blockName'] ) ) );
		$this->assertSame( 'core/paragraph', $inner[0]['blockName'] );
	}

	/**
	 * Test inserting a block at a specific position.
	 */
	public function test_insert_block_at_position(): void {
		$content = '<!-- wp:core/group --><div class="wp-block-group">'
			. '<!-- wp:core/paragraph --><p>First</p><!-- /wp:core/paragraph -->'
			. '</div><!-- /wp:core/group -->';
		$post_id = $this->create_block_post( $content );

		$ability = new Add_Child_Block();
		$result  = $ability->execute(
			array(
				'post_id'            => $post_id,
				'parent_block_index' => 0,
				'block_name'         => 'core/heading',
				'position'           => 0, // Prepend before existing paragraph.
			)
		);

		$this->assertTrue( $result['success'] );

		// Verify the heading was inserted before the paragraph.
		$post   = get_post( $post_id );
		$blocks = parse_blocks( $post->post_content );
		$blocks = array_values( array_filter( $blocks, fn( $b ) => ! empty( $b['blockName'] ) ) );

		$inner = array_values( array_filter( $blocks[0]['innerBlocks'], fn( $b ) => ! empty( $b['blockName'] ) ) );
		$this->assertSame( 'core/heading', $inner[0]['blockName'] );
		$this->assertSame( 'core/paragraph', $inner[1]['blockName'] );
	}
}

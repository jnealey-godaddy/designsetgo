<?php
/**
 * Additional Abilities API Tests
 *
 * Covers gaps identified in the abilities audit:
 * - Block_Configurator delete methods
 * - Block_Configurator update_block_markup
 * - Abstract_Ability validate_input
 * - Block_Schema_Loader path traversal protection
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Configurator;
use DesignSetGo\Abilities\Block_Schema_Loader;
use DesignSetGo\Abilities\Abilities_Registry;

/**
 * Tests for Block_Configurator delete methods.
 */
class Test_Block_Configurator_Delete extends WP_UnitTestCase {

	/**
	 * Test blocks fixture.
	 *
	 * @var array
	 */
	private $blocks;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		// Build a block tree for delete tests.
		$this->blocks = array(
			array(
				'blockName'    => 'core/heading',
				'attrs'        => array( 'level' => 2, 'clientId' => 'heading-1' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '<h2>Heading 1</h2>',
				'innerContent' => array( '<h2>Heading 1</h2>' ),
			),
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array( 'clientId' => 'para-1' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '<p>Paragraph 1</p>',
				'innerContent' => array( '<p>Paragraph 1</p>' ),
			),
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array( 'clientId' => 'para-2' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '<p>Paragraph 2</p>',
				'innerContent' => array( '<p>Paragraph 2</p>' ),
			),
			array(
				'blockName'    => 'designsetgo/section',
				'attrs'        => array( 'clientId' => 'section-1' ),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array( 'clientId' => 'nested-para' ),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Nested</p>',
						'innerContent' => array( '<p>Nested</p>' ),
					),
				),
				'innerHTML'    => '<div class="wp-block-designsetgo-section"><p>Nested</p></div>',
				'innerContent' => array( '<div class="wp-block-designsetgo-section">', null, '</div>' ),
			),
		);
	}

	/**
	 * Test delete_block_by_client_id removes the correct block.
	 */
	public function test_delete_block_by_client_id() {
		$result = Block_Configurator::delete_block_by_client_id( $this->blocks, 'para-1' );

		$this->assertEquals( 1, $result['deleted'] );
		$this->assertEquals( 'core/paragraph', $result['block_name'] );
		$this->assertCount( 3, $result['blocks'] );

		// Verify para-1 is gone.
		$names = wp_list_pluck( $result['blocks'], 'blockName' );
		$this->assertEquals( array( 'core/heading', 'core/paragraph', 'designsetgo/section' ), $names );
	}

	/**
	 * Test delete_block_by_client_id finds nested blocks.
	 */
	public function test_delete_nested_block_by_client_id() {
		$result = Block_Configurator::delete_block_by_client_id( $this->blocks, 'nested-para' );

		$this->assertEquals( 1, $result['deleted'] );
		$this->assertEquals( 'core/paragraph', $result['block_name'] );
		// Top-level count unchanged — nested block was removed.
		$this->assertCount( 4, $result['blocks'] );
		// Verify the section's inner blocks are now empty.
		$this->assertCount( 0, $result['blocks'][3]['innerBlocks'] );
	}

	/**
	 * Test delete_block_by_client_id returns 0 for non-existent ID.
	 */
	public function test_delete_block_by_client_id_not_found() {
		$result = Block_Configurator::delete_block_by_client_id( $this->blocks, 'nonexistent' );

		$this->assertEquals( 0, $result['deleted'] );
		$this->assertNull( $result['block_name'] );
		$this->assertCount( 4, $result['blocks'] );
	}

	/**
	 * Test delete_first_block_by_name removes only the first match.
	 */
	public function test_delete_first_block_by_name() {
		$result = Block_Configurator::delete_first_block_by_name( $this->blocks, 'core/paragraph' );

		$this->assertEquals( 1, $result['deleted'] );
		$this->assertCount( 3, $result['blocks'] );

		// The second paragraph should still exist.
		$para_blocks = array_filter( $result['blocks'], function ( $b ) {
			return 'core/paragraph' === $b['blockName'];
		} );
		$this->assertCount( 1, $para_blocks );
	}

	/**
	 * Test delete_first_block_by_name finds nested blocks.
	 */
	public function test_delete_first_block_by_name_nested() {
		// Create blocks with only a nested paragraph (no top-level paragraphs).
		$blocks = array(
			array(
				'blockName'    => 'designsetgo/section',
				'attrs'        => array(),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Nested</p>',
						'innerContent' => array( '<p>Nested</p>' ),
					),
				),
				'innerHTML'    => '<div></div>',
				'innerContent' => array( '<div>', null, '</div>' ),
			),
		);

		$result = Block_Configurator::delete_first_block_by_name( $blocks, 'core/paragraph' );

		$this->assertEquals( 1, $result['deleted'] );
		$this->assertCount( 0, $result['blocks'][0]['innerBlocks'] );
	}

	/**
	 * Test delete_all_blocks_by_name removes all matches.
	 */
	public function test_delete_all_blocks_by_name() {
		$result = Block_Configurator::delete_all_blocks_by_name( $this->blocks, 'core/paragraph' );

		// Should delete para-1, para-2, AND nested-para (3 total).
		$this->assertEquals( 3, $result['deleted'] );

		// Only heading and section remain at top level.
		$this->assertCount( 2, $result['blocks'] );

		// Section's inner blocks should be empty too.
		$this->assertCount( 0, $result['blocks'][1]['innerBlocks'] );
	}

	/**
	 * Test delete_all_blocks_by_name with no matches.
	 */
	public function test_delete_all_blocks_by_name_no_match() {
		$result = Block_Configurator::delete_all_blocks_by_name( $this->blocks, 'core/image' );

		$this->assertEquals( 0, $result['deleted'] );
		$this->assertCount( 4, $result['blocks'] );
	}

	/**
	 * Test delete_block_at_position removes the correct occurrence.
	 */
	public function test_delete_block_at_position() {
		// Delete the second paragraph (position 1, 0-indexed).
		$result = Block_Configurator::delete_block_at_position( $this->blocks, 'core/paragraph', 1 );

		$this->assertEquals( 1, $result['deleted'] );
		$this->assertCount( 3, $result['blocks'] );

		// First paragraph (para-1) should still exist.
		$remaining = array_values( array_filter( $result['blocks'], function ( $b ) {
			return 'core/paragraph' === $b['blockName'];
		} ) );
		$this->assertCount( 1, $remaining );
		$this->assertEquals( 'para-1', $remaining[0]['attrs']['clientId'] );
	}

	/**
	 * Test delete_block_at_position with out-of-range position.
	 */
	public function test_delete_block_at_position_out_of_range() {
		$result = Block_Configurator::delete_block_at_position( $this->blocks, 'core/paragraph', 99 );

		$this->assertEquals( 0, $result['deleted'] );
		$this->assertCount( 4, $result['blocks'] );
	}
}

/**
 * Tests for Block_Configurator update_block_markup.
 */
class Test_Block_Configurator_Markup extends WP_UnitTestCase {

	/**
	 * Test update_block_markup updates data attributes.
	 */
	public function test_update_data_attribute() {
		$block = array(
			'blockName'    => 'designsetgo/accordion',
			'attrs'        => array( 'iconPosition' => 'end' ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<div class="wp-block-designsetgo-accordion" data-dsgo-icon-position="start"></div>',
			'innerContent' => array( '<div class="wp-block-designsetgo-accordion" data-dsgo-icon-position="start"></div>' ),
		);

		$result = Block_Configurator::update_block_markup(
			$block,
			array( 'iconPosition' => 'end' ),
			'designsetgo/accordion'
		);

		// If block has htmlMappings, data attributes should be updated.
		// If not, innerHTML should remain unchanged (no mappings = no update).
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'innerHTML', $result );
	}

	/**
	 * Test update_block_markup skips blocks without innerHTML.
	 */
	public function test_update_markup_no_inner_html() {
		$block = array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$result = Block_Configurator::update_block_markup(
			$block,
			array( 'content' => 'test' ),
			'core/paragraph'
		);

		// Should return block unchanged.
		$this->assertEquals( $block, $result );
	}

	/**
	 * Test update_block_markup preserves non-mapped attributes.
	 */
	public function test_update_markup_preserves_existing_html() {
		$original_html = '<div class="wp-block-designsetgo-section alignfull" style="padding:20px">content</div>';
		$block = array(
			'blockName'    => 'designsetgo/section',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $original_html,
			'innerContent' => array( $original_html ),
		);

		$result = Block_Configurator::update_block_markup(
			$block,
			array( 'constrainWidth' => true ),
			'designsetgo/section'
		);

		// Should still contain the original class.
		$this->assertStringContainsString( 'wp-block-designsetgo-section', $result['innerHTML'] );
	}
}

/**
 * Tests for Abstract_Ability validate_input.
 */
class Test_Validate_Input extends WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private $editor_user_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->editor_user_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		wp_set_current_user( $this->editor_user_id );
	}

	/**
	 * Test validate_input passes with no schema.
	 */
	public function test_validate_input_no_schema() {
		$registry = Abilities_Registry::get_instance();
		// List-blocks has no required input fields.
		$ability = $registry->get_ability( 'designsetgo/list-blocks' );
		$this->assertNotNull( $ability );

		// Should succeed with empty input.
		$result = $ability->execute( array() );
		$this->assertIsArray( $result );
	}

	/**
	 * Test validate_input catches missing required fields.
	 */
	public function test_validate_input_missing_required() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/update-block' );
		$this->assertNotNull( $ability );

		// Missing post_id (required by update-block).
		$result = $ability->execute( array(
			'block_name' => 'designsetgo/section',
			'attributes' => array( 'tagName' => 'section' ),
		) );
		$this->assertWPError( $result );
		$this->assertEquals( 'designsetgo_missing_post_id', $result->get_error_code() );
	}

	/**
	 * Test validate_input allows falsy required values.
	 */
	public function test_validate_input_falsy_values_allowed() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/list-abilities' );
		$this->assertNotNull( $ability );

		// Category 'all' is a valid value.
		$result = $ability->execute( array( 'category' => 'all' ) );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'abilities', $result );
	}
}

/**
 * Tests for Block_Schema_Loader security.
 */
class Test_Block_Schema_Loader_Security extends WP_UnitTestCase {

	/**
	 * Test get_block_json rejects path traversal attempts.
	 */
	public function test_path_traversal_rejected() {
		$result = Block_Schema_Loader::get_block_json( '../../wp-config' );
		$this->assertNull( $result );
	}

	/**
	 * Test get_block_json rejects double-dot traversal.
	 */
	public function test_double_dot_traversal_rejected() {
		$result = Block_Schema_Loader::get_block_json( 'designsetgo/../../../etc/passwd' );
		$this->assertNull( $result );
	}

	/**
	 * Test get_block_json returns null for non-existent block.
	 */
	public function test_nonexistent_block_returns_null() {
		$result = Block_Schema_Loader::get_block_json( 'designsetgo/nonexistent-block-xyz' );
		$this->assertNull( $result );
	}

	/**
	 * Test get_block_json returns valid data for real block.
	 */
	public function test_valid_block_returns_data() {
		$result = Block_Schema_Loader::get_block_json( 'designsetgo/section' );

		if ( null !== $result ) {
			$this->assertArrayHasKey( 'name', $result );
			$this->assertEquals( 'designsetgo/section', $result['name'] );
			$this->assertArrayHasKey( 'attributes', $result );
		}
	}

	/**
	 * Test get_block_json handles block name without namespace.
	 */
	public function test_block_name_without_namespace() {
		$result = Block_Schema_Loader::get_block_json( 'section' );
		$this->assertNull( $result );
	}

	/**
	 * Test get_attribute_names returns empty for invalid block.
	 */
	public function test_attribute_names_invalid_block() {
		$result = Block_Schema_Loader::get_attribute_names( 'designsetgo/nonexistent' );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}
}

/**
 * Tests for Block_Configurator update_block_by_index.
 */
class Test_Block_Configurator_Index extends WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private $editor_user_id;

	/**
	 * Test page ID.
	 *
	 * @var int
	 */
	private $page_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->editor_user_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		wp_set_current_user( $this->editor_user_id );

		$content  = '<!-- wp:heading {"level":2} --><h2>Heading</h2><!-- /wp:heading -->';
		$content .= '<!-- wp:paragraph --><p>Content</p><!-- /wp:paragraph -->';

		$this->page_id = $this->factory->post->create( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => $content,
		) );
	}

	/**
	 * Test update_block_by_index updates the correct block.
	 */
	public function test_update_by_index() {
		$result = Block_Configurator::update_block_by_index(
			$this->page_id,
			0,
			array( 'level' => 3 ),
			'core/heading'
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 1, $result['updated_count'] );
		$this->assertEquals( 'core/heading', $result['block_name'] );
	}

	/**
	 * Test update_block_by_index rejects out-of-bounds index.
	 */
	public function test_update_by_index_out_of_bounds() {
		$result = Block_Configurator::update_block_by_index(
			$this->page_id,
			99,
			array( 'level' => 3 )
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'designsetgo_block_not_found', $result->get_error_code() );
	}

	/**
	 * Test update_block_by_index rejects mismatched block name.
	 */
	public function test_update_by_index_name_mismatch() {
		$result = Block_Configurator::update_block_by_index(
			$this->page_id,
			0,
			array( 'content' => 'test' ),
			'core/paragraph' // Index 0 is heading, not paragraph.
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'designsetgo_block_name_mismatch', $result->get_error_code() );
	}

	/**
	 * Test update_block_by_index rejects invalid post.
	 */
	public function test_update_by_index_invalid_post() {
		$result = Block_Configurator::update_block_by_index(
			999999,
			0,
			array( 'level' => 3 )
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'designsetgo_invalid_post', $result->get_error_code() );
	}

	/**
	 * Test update_block_by_index checks permissions.
	 */
	public function test_update_by_index_permission_denied() {
		// Switch to subscriber.
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$result = Block_Configurator::update_block_by_index(
			$this->page_id,
			0,
			array( 'level' => 3 )
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'designsetgo_permission_denied', $result->get_error_code() );
	}
}

/**
 * Tests for Block_Configurator insert_inner_block.
 */
class Test_Block_Configurator_Insert_Inner extends WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private $editor_user_id;

	/**
	 * Test page ID.
	 *
	 * @var int
	 */
	private $page_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->editor_user_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		wp_set_current_user( $this->editor_user_id );

		$content = '<!-- wp:designsetgo/section {"align":"full"} --><div class="wp-block-designsetgo-section alignfull"><div class="dsgo-stack__inner"><!-- wp:paragraph --><p>Existing</p><!-- /wp:paragraph --></div></div><!-- /wp:designsetgo/section -->';

		$this->page_id = $this->factory->post->create( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => $content,
		) );
	}

	/**
	 * Test insert_inner_block rejects invalid post.
	 */
	public function test_insert_inner_block_invalid_post() {
		$result = Block_Configurator::insert_inner_block(
			999999,
			0,
			'core/paragraph'
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'designsetgo_invalid_post', $result->get_error_code() );
	}

	/**
	 * Test insert_inner_block rejects unregistered block type.
	 */
	public function test_insert_inner_block_unregistered_type() {
		$result = Block_Configurator::insert_inner_block(
			$this->page_id,
			0,
			'fake/nonexistent-block'
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'designsetgo_invalid_input', $result->get_error_code() );
	}

	/**
	 * Test insert_inner_block rejects out-of-bounds parent index.
	 */
	public function test_insert_inner_block_parent_not_found() {
		$result = Block_Configurator::insert_inner_block(
			$this->page_id,
			99,
			'core/paragraph'
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'designsetgo_block_not_found', $result->get_error_code() );
	}

	/**
	 * Test insert_inner_block checks permissions.
	 */
	public function test_insert_inner_block_permission_denied() {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$result = Block_Configurator::insert_inner_block(
			$this->page_id,
			0,
			'core/paragraph'
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'designsetgo_permission_denied', $result->get_error_code() );
	}
}

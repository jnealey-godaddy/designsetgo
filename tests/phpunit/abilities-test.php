<?php
/**
 * Test Abilities API Core Functionality
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Abilities_Registry;
use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Block_Inserter;
use DesignSetGo\Abilities\Block_Configurator;

/**
 * Tests for Abilities API core classes.
 */
class Test_Abilities extends WP_UnitTestCase {

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

		// Create a test user with edit capabilities.
		$this->editor_user_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		wp_set_current_user( $this->editor_user_id );

		// Create a test page with block content.
		$this->page_id = $this->factory->post->create( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Test Page',
			'post_content' => '<!-- wp:paragraph --><p>Test content</p><!-- /wp:paragraph -->',
		) );
	}

	/**
	 * Test that Abilities_Registry class exists.
	 */
	public function test_abilities_registry_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\Abilities\Abilities_Registry' ) );
	}

	/**
	 * Test that Abstract_Ability class exists.
	 */
	public function test_abstract_ability_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\Abilities\Abstract_Ability' ) );
	}

	/**
	 * Test that Block_Inserter class exists.
	 */
	public function test_block_inserter_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\Abilities\Block_Inserter' ) );
	}

	/**
	 * Test that Block_Configurator class exists.
	 */
	public function test_block_configurator_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\Abilities\Block_Configurator' ) );
	}

	/**
	 * Test registry singleton pattern.
	 */
	public function test_registry_singleton() {
		$instance1 = Abilities_Registry::get_instance();
		$instance2 = Abilities_Registry::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test registry returns abilities array.
	 */
	public function test_registry_get_abilities() {
		$registry = Abilities_Registry::get_instance();
		$abilities = $registry->get_abilities();

		$this->assertIsArray( $abilities );
	}

	/**
	 * Test registry has_ability method.
	 */
	public function test_registry_has_ability() {
		$registry = Abilities_Registry::get_instance();

		// Should have list-blocks ability.
		$this->assertTrue( $registry->has_ability( 'designsetgo/list-blocks' ) );

		// Should not have non-existent ability.
		$this->assertFalse( $registry->has_ability( 'designsetgo/non-existent' ) );
	}

	/**
	 * Test registry get_ability method.
	 */
	public function test_registry_get_ability() {
		$registry = Abilities_Registry::get_instance();

		$ability = $registry->get_ability( 'designsetgo/list-blocks' );
		$this->assertInstanceOf( Abstract_Ability::class, $ability );

		$non_existent = $registry->get_ability( 'designsetgo/non-existent' );
		$this->assertNull( $non_existent );
	}

	/**
	 * Test all registered abilities have required methods.
	 */
	public function test_abilities_have_required_methods() {
		$registry = Abilities_Registry::get_instance();
		$abilities = $registry->get_abilities();

		foreach ( $abilities as $name => $ability ) {
			$this->assertInstanceOf( Abstract_Ability::class, $ability, "Ability {$name} should extend Abstract_Ability" );
			$this->assertIsString( $ability->get_name(), "Ability {$name} get_name() should return string" );
			$this->assertIsArray( $ability->get_config(), "Ability {$name} get_config() should return array" );
		}
	}

	/**
	 * Test ability config has required keys.
	 */
	public function test_ability_config_has_required_keys() {
		$registry = Abilities_Registry::get_instance();
		$abilities = $registry->get_abilities();

		$required_keys = array( 'label', 'description', 'input_schema', 'output_schema', 'permission_callback' );

		foreach ( $abilities as $name => $ability ) {
			$config = $ability->get_config();

			foreach ( $required_keys as $key ) {
				$this->assertArrayHasKey( $key, $config, "Ability {$name} config should have '{$key}' key" );
			}
		}
	}
}

/**
 * Tests for Block_Inserter helper class.
 */
class Test_Block_Inserter extends WP_UnitTestCase {

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

		$this->page_id = $this->factory->post->create( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Test Page',
			'post_content' => '<!-- wp:paragraph --><p>Original content</p><!-- /wp:paragraph -->',
		) );
	}

	/**
	 * Test insert_block with valid parameters.
	 */
	public function test_insert_block_success() {
		$result = Block_Inserter::insert_block(
			$this->page_id,
			'core/heading',
			array( 'level' => 2, 'content' => 'Test Heading' ),
			array(),
			-1
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( $this->page_id, $result['post_id'] );
		$this->assertArrayHasKey( 'block_id', $result );
	}

	/**
	 * Test insert_block with invalid post ID.
	 */
	public function test_insert_block_invalid_post() {
		$result = Block_Inserter::insert_block(
			99999,
			'core/heading',
			array(),
			array(),
			-1
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_post', $result->get_error_code() );
	}

	/**
	 * Test insert_block without permission.
	 */
	public function test_insert_block_no_permission() {
		// Create subscriber user with no edit permissions.
		$subscriber_id = $this->factory->user->create( array(
			'role' => 'subscriber',
		) );
		wp_set_current_user( $subscriber_id );

		$result = Block_Inserter::insert_block(
			$this->page_id,
			'core/heading',
			array(),
			array(),
			-1
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'permission_denied', $result->get_error_code() );
	}

	/**
	 * Test insert_block at prepend position.
	 */
	public function test_insert_block_prepend() {
		$result = Block_Inserter::insert_block(
			$this->page_id,
			'core/heading',
			array( 'content' => 'First Heading' ),
			array(),
			0
		);

		$this->assertTrue( $result['success'] );

		// Verify block is at the beginning.
		$post = get_post( $this->page_id );
		$blocks = parse_blocks( $post->post_content );

		$this->assertEquals( 'core/heading', $blocks[0]['blockName'] );
	}

	/**
	 * Test insert_block with inner blocks.
	 */
	public function test_insert_block_with_inner_blocks() {
		$inner_blocks = array(
			array(
				'name'       => 'core/paragraph',
				'attributes' => array( 'content' => 'Inner paragraph' ),
			),
		);

		$result = Block_Inserter::insert_block(
			$this->page_id,
			'core/group',
			array(),
			$inner_blocks,
			-1
		);

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test sanitize_attributes method.
	 */
	public function test_sanitize_attributes() {
		$attributes = array(
			'text'     => '<script>alert("xss")</script>Hello',
			'number'   => 42,
			'boolean'  => true,
			'float'    => 3.14,
			'nested'   => array(
				'inner' => '<b>bold</b>',
			),
		);

		$sanitized = Block_Inserter::sanitize_attributes( $attributes );

		$this->assertEquals( 'Hello', $sanitized['text'] );
		$this->assertEquals( 42, $sanitized['number'] );
		$this->assertTrue( $sanitized['boolean'] );
		$this->assertEquals( 3.14, $sanitized['float'] );
		$this->assertEquals( 'bold', $sanitized['nested']['inner'] );
	}

	/**
	 * Test validate_inner_blocks with valid blocks.
	 */
	public function test_validate_inner_blocks_valid() {
		$inner_blocks = array(
			array(
				'name'       => 'core/paragraph',
				'attributes' => array( 'content' => 'Test' ),
			),
			array(
				'name'        => 'core/group',
				'attributes'  => array(),
				'innerBlocks' => array(
					array(
						'name' => 'core/heading',
					),
				),
			),
		);

		$result = Block_Inserter::validate_inner_blocks( $inner_blocks );

		$this->assertTrue( $result );
	}

	/**
	 * Test validate_inner_blocks with missing name.
	 */
	public function test_validate_inner_blocks_missing_name() {
		$inner_blocks = array(
			array(
				'attributes' => array( 'content' => 'Test' ),
			),
		);

		$result = Block_Inserter::validate_inner_blocks( $inner_blocks );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_inner_block', $result->get_error_code() );
	}

	/**
	 * Test validate_inner_blocks with invalid attributes type.
	 */
	public function test_validate_inner_blocks_invalid_attributes() {
		$inner_blocks = array(
			array(
				'name'       => 'core/paragraph',
				'attributes' => 'not an array',
			),
		);

		$result = Block_Inserter::validate_inner_blocks( $inner_blocks );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_inner_block_attributes', $result->get_error_code() );
	}

	/**
	 * Test get_common_input_schema returns expected structure.
	 */
	public function test_get_common_input_schema() {
		$schema = Block_Inserter::get_common_input_schema();

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'post_id', $schema );
		$this->assertArrayHasKey( 'position', $schema );
		$this->assertEquals( 'integer', $schema['post_id']['type'] );

		// JSON Schema Draft 7+ compliance: requiredness is expressed via a
		// parent-level `required` array, never as an inline boolean on a
		// property definition (Swagger/OpenAPI v2 syntax). Downstream
		// consumers like OpenAI function calling reject the inline form.
		foreach ( $schema as $name => $definition ) {
			$this->assertArrayNotHasKey(
				'required',
				$definition,
				"Property '$name' must not declare an inline 'required' flag."
			);
		}
	}

	/**
	 * Test get_default_output_schema returns expected structure.
	 */
	public function test_get_default_output_schema() {
		$schema = Block_Inserter::get_default_output_schema();

		$this->assertIsArray( $schema );
		$this->assertEquals( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayHasKey( 'success', $schema['properties'] );
		$this->assertArrayHasKey( 'post_id', $schema['properties'] );
		$this->assertArrayHasKey( 'block_id', $schema['properties'] );
	}
}

/**
 * Tests for Block_Configurator helper class.
 */
class Test_Block_Configurator extends WP_UnitTestCase {

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

		// Create page with multiple blocks for testing.
		$content = '<!-- wp:heading {"level":2} --><h2>Heading 1</h2><!-- /wp:heading -->';
		$content .= '<!-- wp:paragraph --><p>Paragraph 1</p><!-- /wp:paragraph -->';
		$content .= '<!-- wp:heading {"level":3} --><h3>Heading 2</h3><!-- /wp:heading -->';

		$this->page_id = $this->factory->post->create( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Test Page',
			'post_content' => $content,
		) );
	}

	/**
	 * Test update_block_attributes with valid parameters.
	 */
	public function test_update_block_attributes_success() {
		$result = Block_Configurator::update_block_attributes(
			$this->page_id,
			'core/heading',
			array( 'textAlign' => 'center' )
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( $this->page_id, $result['post_id'] );
		$this->assertEquals( 1, $result['updated_count'] );
	}

	/**
	 * Test update_block_attributes updates all matching blocks.
	 */
	public function test_update_block_attributes_update_all() {
		$result = Block_Configurator::update_block_attributes(
			$this->page_id,
			'core/heading',
			array( 'textAlign' => 'center' ),
			null,
			true // update_all
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 2, $result['updated_count'] ); // Two headings in content.
	}

	/**
	 * Test update_block_attributes with invalid post.
	 */
	public function test_update_block_attributes_invalid_post() {
		$result = Block_Configurator::update_block_attributes(
			99999,
			'core/heading',
			array()
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_post', $result->get_error_code() );
	}

	/**
	 * Test update_block_attributes with no matching blocks.
	 */
	public function test_update_block_attributes_no_match() {
		$result = Block_Configurator::update_block_attributes(
			$this->page_id,
			'core/image',
			array()
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'block_not_found', $result->get_error_code() );
	}

	/**
	 * Test walk_blocks traverses nested blocks.
	 */
	public function test_walk_blocks() {
		$blocks = array(
			array(
				'blockName'   => 'core/group',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'core/paragraph',
						'attrs'       => array(),
						'innerBlocks' => array(),
					),
				),
			),
		);

		$visited = array();
		Block_Configurator::walk_blocks( $blocks, function( $block ) use ( &$visited ) {
			$visited[] = $block['blockName'];
			return $block;
		} );

		$this->assertContains( 'core/group', $visited );
		$this->assertContains( 'core/paragraph', $visited );
	}

	/**
	 * Test find_blocks_by_name returns correct blocks.
	 */
	public function test_find_blocks_by_name() {
		$post = get_post( $this->page_id );
		$blocks = parse_blocks( $post->post_content );

		$headings = Block_Configurator::find_blocks_by_name( $blocks, 'core/heading' );

		$this->assertCount( 2, $headings );
	}

	/**
	 * Test find_block_by_client_id returns correct block.
	 */
	public function test_find_block_by_client_id() {
		$blocks = array(
			array(
				'blockName'   => 'core/paragraph',
				'attrs'       => array( 'clientId' => 'test-id-123' ),
				'innerBlocks' => array(),
			),
		);

		$found = Block_Configurator::find_block_by_client_id( $blocks, 'test-id-123' );

		$this->assertNotNull( $found );
		$this->assertEquals( 'core/paragraph', $found['blockName'] );
	}

	/**
	 * Test find_block_by_client_id returns null when not found.
	 */
	public function test_find_block_by_client_id_not_found() {
		$blocks = array(
			array(
				'blockName'   => 'core/paragraph',
				'attrs'       => array( 'clientId' => 'different-id' ),
				'innerBlocks' => array(),
			),
		);

		$found = Block_Configurator::find_block_by_client_id( $blocks, 'non-existent-id' );

		$this->assertNull( $found );
	}

	/**
	 * Test sanitize_attributes preserves CSS properties.
	 */
	public function test_sanitize_attributes_css_properties() {
		$attributes = array(
			'color'           => '#ff0000',
			'backgroundColor' => 'rgb(255, 0, 0)',
			'padding'         => '10px 20px',
			'normalText'      => '<script>alert("xss")</script>Normal',
		);

		$sanitized = Block_Configurator::sanitize_attributes( $attributes );

		// CSS properties should preserve values (just strip tags).
		$this->assertEquals( '#ff0000', $sanitized['color'] );
		$this->assertEquals( 'rgb(255, 0, 0)', $sanitized['backgroundColor'] );
		$this->assertEquals( '10px 20px', $sanitized['padding'] );
		// Normal text should be sanitized.
		$this->assertEquals( 'Normal', $sanitized['normalText'] );
	}

	/**
	 * Test get_common_input_schema returns expected structure.
	 */
	public function test_get_common_input_schema() {
		$schema = Block_Configurator::get_common_input_schema();

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'post_id', $schema );
		$this->assertArrayHasKey( 'block_client_id', $schema );
		$this->assertArrayHasKey( 'update_all', $schema );

		// JSON Schema Draft 7+ compliance: requiredness is expressed via a
		// parent-level `required` array, never as an inline boolean on a
		// property definition (Swagger/OpenAPI v2 syntax). Downstream
		// consumers like OpenAI function calling reject the inline form.
		foreach ( $schema as $name => $definition ) {
			$this->assertArrayNotHasKey(
				'required',
				$definition,
				"Property '$name' must not declare an inline 'required' flag."
			);
		}
	}

	/**
	 * Test configure_block is alias for update_block_attributes.
	 */
	public function test_configure_block_alias() {
		$result = Block_Configurator::configure_block(
			$this->page_id,
			'core/heading',
			array( 'textAlign' => 'right' )
		);

		$this->assertTrue( $result['success'] );
	}
}

/**
 * Tests for individual abilities execution.
 */
class Test_Abilities_Execution extends WP_UnitTestCase {

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

		$this->page_id = $this->factory->post->create( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Test Page',
			'post_content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Test content</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
		) );
	}

	/**
	 * Test list-blocks ability execution.
	 */
	public function test_list_blocks_ability() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/list-blocks' );

		$this->assertNotNull( $ability );

		$result = $ability->execute( array( 'category' => 'all' ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'blocks', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertGreaterThan( 0, $result['total'] );
	}

	/**
	 * Test list-blocks ability with category filter.
	 */
	public function test_list_blocks_ability_filtered() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/list-blocks' );

		$result = $ability->execute( array( 'category' => 'layout' ) );

		$this->assertIsArray( $result );

		// All returned blocks should be in layout category.
		foreach ( $result['blocks'] as $block ) {
			$this->assertEquals( 'layout', $block['category'] );
		}
	}

	/**
	 * Test add-child-block ability execution.
	 */
	public function test_add_child_block_ability() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/add-child-block' );

		if ( ! $ability ) {
			$this->markTestSkipped( 'Add child block ability not registered' );
		}

		$result = $ability->execute( array(
			'post_id'            => $this->page_id,
			'parent_block_index' => 0,
			'block_name'         => 'core/paragraph',
			'attributes'         => array(
				'content' => 'Test paragraph',
			),
		) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test insert ability fails without post_id.
	 */
	public function test_insert_ability_missing_post_id() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/add-child-block' );

		if ( ! $ability ) {
			$this->markTestSkipped( 'Add child block ability not registered' );
		}

		$result = $ability->execute( array(
			'block_name' => 'core/paragraph',
		) );

		$this->assertWPError( $result );
		$this->assertEquals( 'missing_post_id', $result->get_error_code() );
	}

	/**
	 * Test add-child-block ability with inner blocks.
	 */
	public function test_add_child_block_with_inner_blocks() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/add-child-block' );

		if ( ! $ability ) {
			$this->markTestSkipped( 'Add child block ability not registered' );
		}

		$result = $ability->execute( array(
			'post_id'            => $this->page_id,
			'parent_block_index' => 0,
			'block_name'         => 'designsetgo/section',
			'attributes'         => array(
				'constrainWidth' => true,
			),
			'inner_blocks'       => array(
				array(
					'name'       => 'core/heading',
					'attributes' => array(
						'level'   => 1,
						'content' => 'Test Hero',
					),
				),
				array(
					'name'       => 'core/paragraph',
					'attributes' => array(
						'content' => 'Test description',
					),
				),
			),
		) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test ability permission check.
	 */
	public function test_ability_permission_denied() {
		// Create subscriber user with no edit permissions.
		$subscriber_id = $this->factory->user->create( array(
			'role' => 'subscriber',
		) );
		wp_set_current_user( $subscriber_id );

		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/add-child-block' );

		if ( ! $ability ) {
			$this->markTestSkipped( 'Add child block ability not registered' );
		}

		// The permission callback should fail.
		$config = $ability->get_config();
		$permission_result = call_user_func( $config['permission_callback'] );

		// Should be false or WP_Error.
		$this->assertTrue( $permission_result === false || is_wp_error( $permission_result ) );
	}
}

/**
 * Tests for Block_Schema_Loader class.
 */
class Test_Block_Schema_Loader extends WP_UnitTestCase {

	/**
	 * Test that Block_Schema_Loader class exists.
	 */
	public function test_block_schema_loader_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\Abilities\Block_Schema_Loader' ) );
	}

	/**
	 * Test get_block_json returns valid data.
	 */
	public function test_get_block_json() {
		$block_data = \DesignSetGo\Abilities\Block_Schema_Loader::get_block_json( 'designsetgo/section' );

		$this->assertIsArray( $block_data );
		$this->assertArrayHasKey( 'name', $block_data );
		$this->assertArrayHasKey( 'attributes', $block_data );
		$this->assertEquals( 'designsetgo/section', $block_data['name'] );
	}

	/**
	 * Test get_block_json returns null for non-existent block.
	 */
	public function test_get_block_json_not_found() {
		$block_data = \DesignSetGo\Abilities\Block_Schema_Loader::get_block_json( 'designsetgo/non-existent-block' );

		$this->assertNull( $block_data );
	}

	/**
	 * Test get_block_attributes_schema returns valid schema.
	 */
	public function test_get_block_attributes_schema() {
		$schema = \DesignSetGo\Abilities\Block_Schema_Loader::get_block_attributes_schema( 'designsetgo/section' );

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayHasKey( 'align', $schema['properties'] );
		$this->assertArrayHasKey( 'tagName', $schema['properties'] );
		$this->assertArrayHasKey( 'constrainWidth', $schema['properties'] );
	}

	/**
	 * Test schema properties have correct structure.
	 */
	public function test_schema_properties_structure() {
		$schema = \DesignSetGo\Abilities\Block_Schema_Loader::get_block_attributes_schema( 'designsetgo/section' );

		// Check that each property has type and description.
		foreach ( $schema['properties'] as $name => $prop ) {
			$this->assertArrayHasKey( 'type', $prop, "Property {$name} should have 'type'" );
			$this->assertArrayHasKey( 'description', $prop, "Property {$name} should have 'description'" );
		}
	}

	/**
	 * Test schema includes enums from block.json.
	 */
	public function test_schema_includes_enums() {
		$schema = \DesignSetGo\Abilities\Block_Schema_Loader::get_block_attributes_schema( 'designsetgo/accordion' );

		// iconStyle should have enum values.
		$this->assertArrayHasKey( 'iconStyle', $schema['properties'] );
		$this->assertArrayHasKey( 'enum', $schema['properties']['iconStyle'] );
		$this->assertContains( 'chevron', $schema['properties']['iconStyle']['enum'] );
		$this->assertContains( 'plus-minus', $schema['properties']['iconStyle']['enum'] );
	}

	/**
	 * Test schema includes defaults from block.json.
	 */
	public function test_schema_includes_defaults() {
		$schema = \DesignSetGo\Abilities\Block_Schema_Loader::get_block_attributes_schema( 'designsetgo/section' );

		// align should have default 'full'.
		$this->assertArrayHasKey( 'default', $schema['properties']['align'] );
		$this->assertEquals( 'full', $schema['properties']['align']['default'] );

		// constrainWidth should have default true.
		$this->assertTrue( $schema['properties']['constrainWidth']['default'] );
	}

	/**
	 * Test get_block_attributes_schema with include filter.
	 */
	public function test_schema_include_filter() {
		$schema = \DesignSetGo\Abilities\Block_Schema_Loader::get_block_attributes_schema(
			'designsetgo/section',
			array( 'align', 'tagName' ) // Only include these.
		);

		$this->assertCount( 2, $schema['properties'] );
		$this->assertArrayHasKey( 'align', $schema['properties'] );
		$this->assertArrayHasKey( 'tagName', $schema['properties'] );
		$this->assertArrayNotHasKey( 'constrainWidth', $schema['properties'] );
	}

	/**
	 * Test get_block_attributes_schema with exclude filter.
	 */
	public function test_schema_exclude_filter() {
		$schema = \DesignSetGo\Abilities\Block_Schema_Loader::get_block_attributes_schema(
			'designsetgo/section',
			array(), // Include all.
			array( 'style' ) // Exclude style.
		);

		$this->assertArrayNotHasKey( 'style', $schema['properties'] );
		$this->assertArrayHasKey( 'align', $schema['properties'] );
	}

	/**
	 * Test get_ability_input_schema includes common properties.
	 */
	public function test_ability_input_schema_includes_common() {
		$schema = \DesignSetGo\Abilities\Block_Schema_Loader::get_ability_input_schema( 'designsetgo/section' );

		$this->assertEquals( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayHasKey( 'post_id', $schema['properties'] );
		$this->assertArrayHasKey( 'block_client_id', $schema['properties'] );
		$this->assertArrayHasKey( 'update_all', $schema['properties'] );
		$this->assertArrayHasKey( 'block_name', $schema['properties'] );
		$this->assertArrayHasKey( 'attributes', $schema['properties'] );
	}

	/**
	 * Test get_available_blocks returns block names.
	 */
	public function test_get_available_blocks() {
		$blocks = \DesignSetGo\Abilities\Block_Schema_Loader::get_available_blocks();

		$this->assertIsArray( $blocks );
		$this->assertContains( 'designsetgo/section', $blocks );
		$this->assertContains( 'designsetgo/row', $blocks );
	}

	/**
	 * Test get_attribute_names returns attribute list.
	 */
	public function test_get_attribute_names() {
		$attrs = \DesignSetGo\Abilities\Block_Schema_Loader::get_attribute_names( 'designsetgo/accordion' );

		$this->assertIsArray( $attrs );
		$this->assertContains( 'allowMultipleOpen', $attrs );
		$this->assertContains( 'iconStyle', $attrs );
		$this->assertContains( 'iconPosition', $attrs );
	}

	/**
	 * Test has_attribute returns correct values.
	 */
	public function test_has_attribute() {
		$this->assertTrue(
			\DesignSetGo\Abilities\Block_Schema_Loader::has_attribute( 'designsetgo/section', 'align' )
		);
		$this->assertFalse(
			\DesignSetGo\Abilities\Block_Schema_Loader::has_attribute( 'designsetgo/section', 'nonExistent' )
		);
	}

	/**
	 * Test get_attribute_default returns correct default.
	 */
	public function test_get_attribute_default() {
		$default = \DesignSetGo\Abilities\Block_Schema_Loader::get_attribute_default( 'designsetgo/accordion', 'iconStyle' );

		$this->assertEquals( 'chevron', $default );
	}

	/**
	 * Test schemaMetadata descriptions are used.
	 */
	public function test_schema_metadata_descriptions() {
		$schema = \DesignSetGo\Abilities\Block_Schema_Loader::get_block_attributes_schema( 'designsetgo/section' );

		// These descriptions come from schemaMetadata in block.json.
		$this->assertStringContainsString(
			'wide or full width',
			$schema['properties']['align']['description']
		);
	}

	/**
	 * Test cache clearing.
	 */
	public function test_clear_cache() {
		// First load.
		$data1 = \DesignSetGo\Abilities\Block_Schema_Loader::get_block_json( 'designsetgo/section' );
		$this->assertNotNull( $data1 );

		// Clear cache.
		\DesignSetGo\Abilities\Block_Schema_Loader::clear_cache();

		// Should still work after cache clear.
		$data2 = \DesignSetGo\Abilities\Block_Schema_Loader::get_block_json( 'designsetgo/section' );
		$this->assertNotNull( $data2 );
		$this->assertEquals( $data1, $data2 );
	}
}

/**
 * Tests for Abstract_Configurator_Ability class.
 */
class Test_Abstract_Configurator_Ability extends WP_UnitTestCase {

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

		// Create page with section block.
		$content = '<!-- wp:designsetgo/section {"align":"full","constrainWidth":true} -->';
		$content .= '<div class="wp-block-designsetgo-section">Test Section</div>';
		$content .= '<!-- /wp:designsetgo/section -->';

		$this->page_id = $this->factory->post->create( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Test Page',
			'post_content' => $content,
		) );
	}

	/**
	 * Test Abstract_Configurator_Ability class exists.
	 */
	public function test_abstract_configurator_ability_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\Abilities\Abstract_Configurator_Ability' ) );
	}

	/**
	 * Test list-extensions ability is registered.
	 */
	public function test_list_extensions_ability_registered() {
		$registry = Abilities_Registry::get_instance();

		$this->assertTrue( $registry->has_ability( 'designsetgo/list-extensions' ) );
	}

	/**
	 * Test list-extensions returns extension data.
	 */
	public function test_list_extensions_returns_data() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/list-extensions' );

		$this->assertNotNull( $ability );

		$result = $ability->execute( array() );

		$this->assertArrayHasKey( 'extensions', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertGreaterThan( 0, $result['total'] );

		// Each extension should have required fields.
		$ext = $result['extensions'][0];
		$this->assertArrayHasKey( 'name', $ext );
		$this->assertArrayHasKey( 'label', $ext );
		$this->assertArrayHasKey( 'attributes', $ext );
		$this->assertArrayHasKey( 'blocks', $ext );
	}

	/**
	 * Test list-extensions can filter by extension name.
	 */
	public function test_list_extensions_filter() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/list-extensions' );

		$result = $ability->execute( array( 'extension' => 'block-animations' ) );

		$this->assertEquals( 1, $result['total'] );
		$this->assertEquals( 'block-animations', $result['extensions'][0]['name'] );
		$this->assertArrayHasKey( 'dsgoAnimationEnabled', $result['extensions'][0]['attributes'] );
	}

	/**
	 * Test update-block ability is registered.
	 */
	public function test_update_block_ability() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/update-block' );

		$this->assertNotNull( $ability );

		$config = $ability->get_config();

		// Should have input_schema with common properties.
		$this->assertArrayHasKey( 'input_schema', $config );
		$this->assertArrayHasKey( 'properties', $config['input_schema'] );
		$this->assertArrayHasKey( 'post_id', $config['input_schema']['properties'] );
		$this->assertArrayHasKey( 'block_name', $config['input_schema']['properties'] );
		$this->assertArrayHasKey( 'attributes', $config['input_schema']['properties'] );
	}

	/**
	 * Test update-block ability execution targeting by block name.
	 */
	public function test_update_block_execution() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/update-block' );

		$result = $ability->execute( array(
			'post_id'    => $this->page_id,
			'block_name' => 'designsetgo/section',
			'attributes' => array(
				'tagName'        => 'section',
				'constrainWidth' => false,
			),
		) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 1, $result['updated_count'] );

		// Verify the block was updated.
		$post = get_post( $this->page_id );
		$blocks = parse_blocks( $post->post_content );
		$section = $blocks[0];

		$this->assertEquals( 'section', $section['attrs']['tagName'] );
		$this->assertFalse( $section['attrs']['constrainWidth'] );
	}

	/**
	 * Test update-block ability fails without post_id.
	 */
	public function test_update_block_missing_post_id() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/update-block' );

		$result = $ability->execute( array(
			'block_name' => 'designsetgo/section',
			'attributes' => array( 'tagName' => 'section' ),
		) );

		$this->assertWPError( $result );
		$this->assertEquals( 'missing_post_id', $result->get_error_code() );
	}

	/**
	 * Test update-block ability fails without attributes.
	 */
	public function test_update_block_missing_attributes() {
		$registry = Abilities_Registry::get_instance();
		$ability = $registry->get_ability( 'designsetgo/update-block' );

		$result = $ability->execute( array(
			'post_id'    => $this->page_id,
			'block_name' => 'designsetgo/section',
		) );

		$this->assertWPError( $result );
		$this->assertEquals( 'missing_settings', $result->get_error_code() );
	}
}

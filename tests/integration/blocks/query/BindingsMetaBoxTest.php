<?php
/**
 * Tests for the designsetgo/metabox Block Bindings source.
 *
 * Uses the MetaBoxBindings::$reader facade so no global rwmb_meta() stub
 * is required — avoids PHP namespace-shadowing issues that occur when
 * defining functions inside namespaced test files.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests\Integration\Blocks\Query;

use DesignSetGo\Blocks\Query\MetaBoxBindings;
use WP_Block;
use WP_UnitTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class BindingsMetaBoxTest extends WP_UnitTestCase {

	/**
	 * Reset the reader facade after each test.
	 */
	public function tearDown(): void {
		MetaBoxBindings::$reader = null;
		parent::tearDown();
	}

	public function test_source_skipped_when_metabox_absent() {
		// Ensure no reader override is present and rwmb_meta is not defined.
		MetaBoxBindings::$reader = null;

		if ( function_exists( 'rwmb_meta' ) ) {
			$this->markTestSkipped( 'rwmb_meta already defined; cannot test absent-case.' );
		}

		// register() should be a no-op because neither rwmb_meta nor $reader is available.
		MetaBoxBindings::register();

		$this->assertNull( get_block_bindings_source( 'designsetgo/metabox' ) );
	}

	public function test_reads_formatted_value_from_rwmb_meta() {
		// Inject a closure stub via the facade — no global function required.
		MetaBoxBindings::$reader = static function ( $field_id, $args = array(), $post_id = null ) {
			return 'FORMATTED-' . $field_id;
		};

		MetaBoxBindings::register();

		$post_id = self::factory()->post->create();
		$block   = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);

		$source = get_block_bindings_source( 'designsetgo/metabox' );
		$this->assertNotNull( $source );

		$value = $source->get_value( array( 'key' => 'my_field' ), $block, 'content' );
		$this->assertSame( 'FORMATTED-my_field', $value );
	}

	public function test_returns_null_for_array_values() {
		MetaBoxBindings::$reader = static function ( $field_id, $args = array(), $post_id = null ) {
			return array( 'foo', 'bar' ); // Complex field type.
		};

		MetaBoxBindings::register();

		$post_id = self::factory()->post->create();
		$block   = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);

		$source = get_block_bindings_source( 'designsetgo/metabox' );
		$this->assertNotNull( $source );

		$value = $source->get_value( array( 'key' => 'gallery_field' ), $block, 'content' );
		$this->assertNull( $value );
	}

	public function test_returns_null_for_empty_values() {
		MetaBoxBindings::$reader = static function ( $field_id, $args = array(), $post_id = null ) {
			return '';
		};

		MetaBoxBindings::register();

		$post_id = self::factory()->post->create();
		$block   = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);

		$source = get_block_bindings_source( 'designsetgo/metabox' );
		$this->assertNotNull( $source );

		$value = $source->get_value( array( 'key' => 'empty_field' ), $block, 'content' );
		$this->assertNull( $value );
	}
}

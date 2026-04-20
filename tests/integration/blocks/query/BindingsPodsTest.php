<?php
/**
 * Tests for the designsetgo/pods Block Bindings source.
 *
 * Uses the PodsBindings::$reader facade so no global pods_field() stub
 * is required — avoids PHP namespace-shadowing issues that occur when
 * defining functions inside namespaced test files.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests\Integration\Blocks\Query;

use DesignSetGo\Blocks\Query\PodsBindings;
use WP_Block;
use WP_UnitTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class BindingsPodsTest extends WP_UnitTestCase {

	/**
	 * Reset the reader facade after each test.
	 */
	public function tearDown(): void {
		PodsBindings::$reader = null;
		parent::tearDown();
	}

	public function test_source_absent_when_pods_inactive() {
		// Ensure no reader override is present and pods_field is not defined.
		PodsBindings::$reader = null;

		if ( function_exists( 'pods_field' ) ) {
			$this->markTestSkipped( 'pods_field already defined; cannot test absent-case.' );
		}

		// register() should be a no-op because neither pods_field nor $reader is available.
		PodsBindings::register();

		$this->assertNull( get_block_bindings_source( 'designsetgo/pods' ) );
	}

	public function test_reads_formatted_value_via_reader() {
		// Inject a closure stub via the facade — no global function required.
		PodsBindings::$reader = static function ( $pod_type, $post_id, $field_name ) {
			return 'FORMATTED-' . $field_name;
		};

		PodsBindings::register();

		$post_id = self::factory()->post->create();
		$block   = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);

		$source = get_block_bindings_source( 'designsetgo/pods' );
		$this->assertNotNull( $source );

		$value = $source->get_value( array( 'key' => 'my_field' ), $block, 'content' );
		$this->assertSame( 'FORMATTED-my_field', $value );
	}

	public function test_returns_null_for_array_values() {
		PodsBindings::$reader = static function ( $pod_type, $post_id, $field_name ) {
			return array( 'foo', 'bar' ); // Complex field type.
		};

		PodsBindings::register();

		$post_id = self::factory()->post->create();
		$block   = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);

		$source = get_block_bindings_source( 'designsetgo/pods' );
		$this->assertNotNull( $source );

		$value = $source->get_value( array( 'key' => 'gallery_field' ), $block, 'content' );
		$this->assertNull( $value );
	}

	public function test_returns_null_for_empty_values() {
		PodsBindings::$reader = static function ( $pod_type, $post_id, $field_name ) {
			return '';
		};

		PodsBindings::register();

		$post_id = self::factory()->post->create();
		$block   = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);

		$source = get_block_bindings_source( 'designsetgo/pods' );
		$this->assertNotNull( $source );

		$value = $source->get_value( array( 'key' => 'empty_field' ), $block, 'content' );
		$this->assertNull( $value );
	}
}

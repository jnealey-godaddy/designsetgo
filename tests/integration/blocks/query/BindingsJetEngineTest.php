<?php
/**
 * Tests for the designsetgo/jetengine Block Bindings source.
 *
 * Uses the JetEngineBindings::$reader facade so no global jet_engine() stub
 * is required — avoids PHP namespace-shadowing issues that occur when
 * defining functions inside namespaced test files.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests\Integration\Blocks\Query;

use DesignSetGo\Blocks\Query\JetEngineBindings;
use WP_Block;
use WP_UnitTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class BindingsJetEngineTest extends WP_UnitTestCase {

	/**
	 * Reset the reader facade after each test.
	 */
	public function tearDown(): void {
		JetEngineBindings::$reader = null;
		parent::tearDown();
	}

	public function test_source_absent_when_jetengine_inactive() {
		// Ensure no reader override is present and JetEngine is not active.
		JetEngineBindings::$reader = null;

		if ( class_exists( 'Jet_Engine' ) && function_exists( 'jet_engine' ) ) {
			$this->markTestSkipped( 'JetEngine already active; cannot test absent-case.' );
		}

		// register() should be a no-op because neither JetEngine nor $reader is available.
		JetEngineBindings::register();

		$this->assertNull( get_block_bindings_source( 'designsetgo/jetengine' ) );
	}

	public function test_reads_formatted_value_via_reader() {
		// Inject a closure stub via the facade — no global function required.
		JetEngineBindings::$reader = static function ( $key, $post_id ) {
			return 'FORMATTED-' . $key;
		};

		JetEngineBindings::register();

		$post_id = self::factory()->post->create();
		$block   = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);

		$source = get_block_bindings_source( 'designsetgo/jetengine' );
		$this->assertNotNull( $source );

		$value = $source->get_value( array( 'key' => 'my_field' ), $block, 'content' );
		$this->assertSame( 'FORMATTED-my_field', $value );
	}

	public function test_returns_null_for_array_values() {
		JetEngineBindings::$reader = static function ( $key, $post_id ) {
			return array( 'foo', 'bar' ); // Complex field type.
		};

		JetEngineBindings::register();

		$post_id = self::factory()->post->create();
		$block   = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);

		$source = get_block_bindings_source( 'designsetgo/jetengine' );
		$this->assertNotNull( $source );

		$value = $source->get_value( array( 'key' => 'gallery_field' ), $block, 'content' );
		$this->assertNull( $value );
	}

	public function test_returns_null_for_empty_values() {
		JetEngineBindings::$reader = static function ( $key, $post_id ) {
			return '';
		};

		JetEngineBindings::register();

		$post_id = self::factory()->post->create();
		$block   = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);

		$source = get_block_bindings_source( 'designsetgo/jetengine' );
		$this->assertNotNull( $source );

		$value = $source->get_value( array( 'key' => 'empty_field' ), $block, 'content' );
		$this->assertNull( $value );
	}
}

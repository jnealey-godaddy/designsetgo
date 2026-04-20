<?php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_Block;
use WP_UnitTestCase;

class BindingsHelperTest extends WP_UnitTestCase {

	public function test_registers_source_with_shared_gates() {
		$called_with = null;
		designsetgo_register_bindings_source( 'designsetgo/test-source', function ( $args, $block ) use ( &$called_with ) {
			$called_with = $args;
			return 'OK-' . $args['key'];
		}, array( 'label' => 'Test source' ) );

		$this->assertNotNull( get_block_bindings_source( 'designsetgo/test-source' ) );

		$post_id = self::factory()->post->create();
		$block   = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);

		$source = get_block_bindings_source( 'designsetgo/test-source' );
		$value  = $source->get_value( array( 'key' => 'x' ), $block, 'content' );
		$this->assertSame( 'OK-x', $value );
	}

	public function test_applies_password_gate() {
		$post_id = self::factory()->post->create( array( 'post_password' => 'secret' ) );
		designsetgo_register_bindings_source( 'designsetgo/test-gated', function ( $args ) {
			return 'LEAK-' . $args['key'];
		} );

		$block = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);
		$source = get_block_bindings_source( 'designsetgo/test-gated' );
		$value  = $source->get_value( array( 'key' => 'x' ), $block, 'content' );
		$this->assertNull( $value );
	}
}

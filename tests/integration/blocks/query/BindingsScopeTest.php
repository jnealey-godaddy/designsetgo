<?php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use DesignSetGo\Blocks\Query\Bindings;
use WP_Block;
use WP_UnitTestCase;

class BindingsScopeTest extends WP_UnitTestCase {

	public function test_parent_scope_reads_from_parent_stack() {
		$parent_id = self::factory()->post->create();
		$child_id  = self::factory()->post->create();
		update_post_meta( $parent_id, 'parent_label', 'HELLO-PARENT' );

		$GLOBALS['designsetgo_parent_stack'] = array(
			array( 'postId' => $parent_id, 'postType' => 'post' ),
		);

		$bindings = new Bindings();
		$block    = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $child_id, 'postType' => 'post' )
		);
		$value = $bindings->get_post_meta_value(
			array( 'key' => 'parent_label', 'scope' => 'parent' ),
			$block,
			'content'
		);

		$this->assertSame( 'HELLO-PARENT', $value );
		unset( $GLOBALS['designsetgo_parent_stack'] );
	}

	public function test_self_scope_is_default() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'label', 'ME' );

		$bindings = new Bindings();
		$block    = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);
		$value = $bindings->get_post_meta_value( array( 'key' => 'label' ), $block, 'content' );
		$this->assertSame( 'ME', $value );
	}

	public function test_root_scope_reads_from_stack_bottom() {
		$root_id  = self::factory()->post->create();
		$mid_id   = self::factory()->post->create();
		$leaf_id  = self::factory()->post->create();
		update_post_meta( $root_id, 'root_label', 'ROOT' );

		$GLOBALS['designsetgo_parent_stack'] = array(
			array( 'postId' => $root_id, 'postType' => 'post' ),
			array( 'postId' => $mid_id,  'postType' => 'post' ),
		);

		$bindings = new Bindings();
		$block    = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $leaf_id, 'postType' => 'post' )
		);
		$value = $bindings->get_post_meta_value(
			array( 'key' => 'root_label', 'scope' => 'root' ),
			$block,
			'content'
		);

		$this->assertSame( 'ROOT', $value );
		unset( $GLOBALS['designsetgo_parent_stack'] );
	}
}

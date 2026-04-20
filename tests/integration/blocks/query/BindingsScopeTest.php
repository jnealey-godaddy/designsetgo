<?php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_Block;
use WP_UnitTestCase;

/**
 * Tests scope resolution ('self'|'parent'|'root') for the designsetgo/post-meta
 * binding source.
 *
 * After the A2 refactor, scope resolution lives entirely in
 * `designsetgo_resolve_bindings_post_id()` (the shared helper). These tests
 * exercise it end-to-end via the registered source's get_value callback so that
 * the full gate + scope pipeline is covered.
 */
class BindingsScopeTest extends WP_UnitTestCase {

	/**
	 * Returns the get_value callable for designsetgo/post-meta.
	 */
	private function get_source_callback() {
		$source = get_block_bindings_source( 'designsetgo/post-meta' );
		$this->assertNotNull( $source, 'designsetgo/post-meta source must be registered.' );
		return $source;
	}

	public function test_parent_scope_reads_ancestor_not_current_item() {
		$parent_id = self::factory()->post->create();
		$child_id  = self::factory()->post->create();
		update_post_meta( $parent_id, 'parent_label', 'HELLO-PARENT' );
		update_post_meta( $child_id, 'parent_label', 'WRONG-CHILD' );

		// designsetgo_query_render_item() pushes the CURRENT item onto the
		// stack before innerBlocks render, so 'parent' must skip the top entry
		// (self) and read the ancestor one level up.
		$GLOBALS['designsetgo_parent_stack'] = array(
			array( 'postId' => $parent_id, 'postType' => 'post' ),
			array( 'postId' => $child_id,  'postType' => 'post' ),
		);

		$source = $this->get_source_callback();
		$block  = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $child_id, 'postType' => 'post' )
		);
		$value = $source->get_value(
			array( 'key' => 'parent_label', 'scope' => 'parent' ),
			$block,
			'content'
		);

		$this->assertSame( 'HELLO-PARENT', $value );
		unset( $GLOBALS['designsetgo_parent_stack'] );
	}

	public function test_parent_scope_returns_null_when_no_outer_loop() {
		$only_id = self::factory()->post->create();
		update_post_meta( $only_id, 'label', 'ONLY' );

		// Single-level query — stack has just the current item, no ancestor.
		$GLOBALS['designsetgo_parent_stack'] = array(
			array( 'postId' => $only_id, 'postType' => 'post' ),
		);

		$source = $this->get_source_callback();
		$block  = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $only_id, 'postType' => 'post' )
		);
		$value = $source->get_value(
			array( 'key' => 'label', 'scope' => 'parent' ),
			$block,
			'content'
		);

		$this->assertNull( $value );
		unset( $GLOBALS['designsetgo_parent_stack'] );
	}

	public function test_self_scope_is_default() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'label', 'ME' );

		$source = $this->get_source_callback();
		$block  = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $post_id, 'postType' => 'post' )
		);
		$value = $source->get_value( array( 'key' => 'label' ), $block, 'content' );
		$this->assertSame( 'ME', $value );
	}

	public function test_root_scope_reads_from_stack_bottom() {
		$root_id  = self::factory()->post->create();
		$mid_id   = self::factory()->post->create();
		$leaf_id  = self::factory()->post->create();
		update_post_meta( $root_id, 'root_label', 'ROOT' );

		// Three-level stack matches the real render pipeline: root → mid → leaf
		// (leaf is the current item, pushed by designsetgo_query_render_item()).
		$GLOBALS['designsetgo_parent_stack'] = array(
			array( 'postId' => $root_id, 'postType' => 'post' ),
			array( 'postId' => $mid_id,  'postType' => 'post' ),
			array( 'postId' => $leaf_id, 'postType' => 'post' ),
		);

		$source = $this->get_source_callback();
		$block  = new WP_Block(
			array( 'blockName' => 'core/paragraph' ),
			array( 'postId' => $leaf_id, 'postType' => 'post' )
		);
		$value = $source->get_value(
			array( 'key' => 'root_label', 'scope' => 'root' ),
			$block,
			'content'
		);

		$this->assertSame( 'ROOT', $value );
		unset( $GLOBALS['designsetgo_parent_stack'] );
	}
}

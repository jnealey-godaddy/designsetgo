<?php
/**
 * `__dsgo_post_id` is an internal channel; stored block args must not use it.
 *
 * @package DesignSetGo
 * @group   bindings
 */

/**
 * Trust boundary of designsetgo_resolve_bindings_post_id().
 *
 * @group bindings
 */
class DesignSetGo_Bindings_Post_Id_Trust_Test extends WP_UnitTestCase {

	/**
	 * Block-less internal callers (REST preview, style bindings) keep working.
	 */
	public function test_blockless_callers_may_preresolve_the_post() {
		$this->assertSame( 42, designsetgo_resolve_bindings_post_id( array( '__dsgo_post_id' => 42 ), null ) );
	}

	/**
	 * When core resolves a real binding, a stored arg is ignored.
	 */
	public function test_stored_arg_is_ignored_for_a_real_block() {
		$page  = self::factory()->post->create();
		$other = self::factory()->post->create();

		$block = new WP_Block(
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array( 'postId' => $page )
		);

		$this->assertSame(
			$page,
			designsetgo_resolve_bindings_post_id( array( '__dsgo_post_id' => $other ), $block )
		);
	}
}

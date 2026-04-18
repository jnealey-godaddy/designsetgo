<?php
/**
 * @group query-block
 */
class DesignSetGo_Query_Bindings_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		( new \DesignSetGo\Blocks\Query\Bindings() )->register();
	}

	public function test_post_meta_source_registered() {
		$sources = get_all_registered_block_bindings_sources();
		$this->assertArrayHasKey( 'designsetgo/post-meta', $sources );
	}

	public function test_post_meta_resolves_value_for_current_post() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'subtitle', 'Hello world' );

		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		// WP_Block_Bindings_Source::$get_value_callback is private (WP 6.5+).
		// Use the public get_value() method instead of accessing the property directly.
		$source = get_all_registered_block_bindings_sources()['designsetgo/post-meta'];
		$value  = $source->get_value( array( 'key' => 'subtitle' ), null, 'content' );

		$this->assertSame( 'Hello world', $value );
	}

	public function test_acf_source_only_registered_when_acf_present() {
		$sources = get_all_registered_block_bindings_sources();
		if ( function_exists( 'get_field' ) ) {
			$this->assertArrayHasKey( 'designsetgo/acf', $sources );
		} else {
			$this->assertArrayNotHasKey( 'designsetgo/acf', $sources );
		}
	}
}

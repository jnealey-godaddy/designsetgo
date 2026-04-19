<?php
/**
 * @group query-block
 * @runInSeparateProcess
 * @preserveGlobalState disabled
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

/**
 * Tests for the ACF binding source using a userland get_field mock.
 *
 * Runs in a separate process so the mock get_field() definition does not
 * leak into DesignSetGo_Query_Bindings_Test (which asserts ACF is NOT present).
 *
 * @group query-block
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class DesignSetGo_Query_Bindings_ACF_Test extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! function_exists( 'get_field' ) ) {
			// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
			function get_field( $selector, $post_id = false, $format_value = true ) {
				$post_id = $post_id ?: get_the_ID();
				if ( ! $post_id ) {
					return false;
				}
				$map = (array) get_post_meta( $post_id, '_dsgo_acf_mock', true );
				return isset( $map[ $selector ] ) ? $map[ $selector ] : false;
			}
			// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		}
	}

	public function set_up() {
		parent::set_up();
		( new \DesignSetGo\Blocks\Query\Bindings() )->register();
	}

	public function test_acf_source_is_registered_when_get_field_exists() {
		$sources = get_all_registered_block_bindings_sources();
		$this->assertArrayHasKey( 'designsetgo/acf', $sources );
	}

	public function test_acf_returns_null_for_missing_field() {
		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$source = get_all_registered_block_bindings_sources()['designsetgo/acf'];
		$value  = $source->get_value( array( 'key' => 'nonexistent' ), null, 'content' );

		$this->assertNull( $value, 'Missing ACF fields must return null, not empty string, so the bindings API can fall back.' );
	}

	public function test_acf_returns_string_for_scalar_field() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_dsgo_acf_mock', array( 'headline' => 'Hello ACF' ) );

		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$source = get_all_registered_block_bindings_sources()['designsetgo/acf'];
		$value  = $source->get_value( array( 'key' => 'headline' ), null, 'content' );

		$this->assertSame( 'Hello ACF', $value );
	}
}

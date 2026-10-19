<?php
/**
 * Regression tests for llms.txt / markdown / settings bug-hunt fixes.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Settings;
use DesignSetGo\LLMS_Txt\Controller;
use DesignSetGo\Markdown\Converter;

/**
 * Bug-hunt regression tests.
 */
class LLMS_Txt_Bug_Hunt_Test extends WP_UnitTestCase {

	/**
	 * Reset settings.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
	}

	/**
	 * Clean up.
	 */
	public function tear_down(): void {
		delete_option( Settings::OPTION_NAME );
		delete_option( Controller::PHYSICAL_FILE_OPTION );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	/**
	 * Enable llms.txt.
	 *
	 * @param array $extra Extra llms_txt settings.
	 */
	private function enable( array $extra = array() ): void {
		update_option( Settings::OPTION_NAME, array( 'llms_txt' => array_merge( array( 'enable' => true ), $extra ) ) );
		Settings::invalidate_cache();
	}

	/**
	 * Saving a post type outside the configured list must not touch the cache.
	 */
	public function test_post_save_ignores_untracked_post_types() {
		$this->enable();
		$controller = new Controller();
		set_transient( Controller::CACHE_KEY, 'cached', HOUR_IN_SECONDS );

		$id = self::factory()->post->create(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'publish',
			)
		);
		set_transient( Controller::CACHE_KEY, 'cached', HOUR_IN_SECONDS );
		$controller->handle_post_save( $id, get_post( $id ) );

		$this->assertSame( 'cached', get_transient( Controller::CACHE_KEY ) );
	}

	/**
	 * Saving a configured post type still invalidates the cache.
	 */
	public function test_post_save_tracks_configured_post_types() {
		$this->enable();
		$controller = new Controller();
		$id         = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		set_transient( Controller::CACHE_KEY, 'cached', HOUR_IN_SECONDS );

		$controller->handle_post_save( $id, get_post( $id ) );

		$this->assertFalse( get_transient( Controller::CACHE_KEY ) );
	}

	/**
	 * A description change rewrites the physical file while enabled.
	 */
	public function test_settings_update_refreshes_physical_file_on_description_change() {
		$controller = new Controller();
		$this->enable( array( 'description' => 'First description' ) );
		$this->assertTrue( $controller->write_physical_file() );
		$path = \DesignSetGo\LLMS_Txt\File_Manager::site_root_path() . 'llms.txt';

		$old = array( 'llms_txt' => array( 'enable' => true, 'description' => 'First description' ) );
		$new = array( 'llms_txt' => array( 'enable' => true, 'description' => 'Second description' ) );
		update_option( Settings::OPTION_NAME, $new );
		Settings::invalidate_cache();
		$controller->handle_settings_update( $old, $new );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test reads a local file.
		$this->assertStringContainsString( 'Second description', (string) file_get_contents( $path ) );

		$controller->delete_physical_file();
	}

	/**
	 * Non-Markdown Accept headers must not be intercepted (no 406).
	 */
	public function test_negotiation_ignores_non_markdown_accepts() {
		foreach ( array( 'application/json', 'image/png', 'application/xhtml+xml' ) as $accept ) {
			$this->assertNotSame( 'markdown', \DesignSetGo\LLMS_Txt\Negotiation_Handler::preferred_type( $accept ) );
		}
		$this->assertSame( 'markdown', \DesignSetGo\LLMS_Txt\Negotiation_Handler::preferred_type( 'text/x-markdown' ) );
		$this->assertFalse( method_exists( \DesignSetGo\LLMS_Txt\Negotiation_Handler::class, 'send_406' ) );
	}

	/**
	 * Rate limit allows exactly 30 requests per fixed window and does not extend it.
	 */
	public function test_markdown_route_rate_limit_is_fixed_window() {
		$this->enable();
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		$key = 'dsgo_llms_rate_' . md5( '203.0.113.9' );
		delete_transient( $key );

		$last = null;
		for ( $i = 0; $i < 31; $i++ ) {
			$request = new WP_REST_Request( 'GET', '/designsetgo/v1/llms-txt/markdown/' . $post_id );
			$request->set_param( 'post_id', $post_id );
			$last = rest_do_request( $request );
			if ( $i < 30 ) {
				$this->assertNotSame( 429, $last->get_status(), "Request $i should be allowed" );
			}
		}
		$this->assertSame( 429, $last->get_status() );

		// Expire the window: the next request starts a fresh one.
		$window          = get_transient( $key );
		$window['start'] = time() - 120;
		set_transient( $key, $window, 60 );
		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/llms-txt/markdown/' . $post_id );
		$request->set_param( 'post_id', $post_id );
		$this->assertNotSame( 429, rest_do_request( $request )->get_status() );

		delete_transient( $key );
	}

	/**
	 * Inline tag conversion must not swallow br, blockquote, img, etc.
	 */
	public function test_html_to_text_tag_boundaries() {
		$converter = new Converter();

		$out = $converter->html_to_text( '<b class="x">bold</b> <i>it</i> <strong>s</strong> <em>e</em>' );
		$this->assertStringContainsString( '**bold**', $out );
		$this->assertStringContainsString( '*it*', $out );

		$out = $converter->html_to_text( 'a<br>b <blockquote>quote</blockquote> <img src="x.png"> end' );
		$this->assertStringNotContainsString( '**', $out );
		$this->assertStringContainsString( 'quote', $out );
		$this->assertStringContainsString( 'end', $out );
	}

	/**
	 * A partial save keeps the other defaults of the group on read.
	 */
	public function test_get_settings_deep_merges_partial_groups() {
		update_option( Settings::OPTION_NAME, array( 'llms_txt' => array( 'enable' => true ) ) );
		Settings::invalidate_cache();

		$settings = Settings::get_settings();
		$this->assertTrue( $settings['llms_txt']['enable'] );
		$this->assertSame( array( 'page', 'post' ), $settings['llms_txt']['post_types'] );
		$this->assertArrayHasKey( 'description', $settings['llms_txt'] );
	}

	/**
	 * Lists are replaced, not merged by index.
	 */
	public function test_get_settings_replaces_lists() {
		update_option( Settings::OPTION_NAME, array( 'llms_txt' => array( 'post_types' => array() ) ) );
		Settings::invalidate_cache();
		$this->assertSame( array(), Settings::get_settings()['llms_txt']['post_types'] );

		update_option( Settings::OPTION_NAME, array( 'llms_txt' => array( 'post_types' => array( 'page' ) ) ) );
		Settings::invalidate_cache();
		$this->assertSame( array( 'page' ), Settings::get_settings()['llms_txt']['post_types'] );
	}
}

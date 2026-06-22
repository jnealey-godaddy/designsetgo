<?php
/**
 * Tests for llms.txt Feature
 *
 * Tests the llms.txt generation, caching, exclusion logic,
 * REST API endpoints, and markdown conversion.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_REST_Request;
use DesignSetGo\LLMS_Txt\Controller;
use DesignSetGo\LLMS_Txt\REST_Controller;
use DesignSetGo\LLMS_Txt\Generator;
use DesignSetGo\LLMS_Txt\File_Manager;
use DesignSetGo\LLMS_Txt\Negotiation_Handler;
use DesignSetGo\Markdown\Converter;
use DesignSetGo\Admin\Settings;

/**
 * Shared helpers for the llms.txt test cases in this file.
 */
trait Enables_LLMS_Feature {
	/**
	 * Helper: enable the llms.txt feature in settings.
	 */
	private function enable_llms_feature(): void {
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable' => true,
				),
			)
		);
		Settings::invalidate_cache();
	}
}

/**
 * llms.txt Feature Test Case
 */
class Test_LLMS_Txt extends WP_UnitTestCase {
	use Enables_LLMS_Feature;

	/**
	 * Controller instance.
	 *
	 * @var Controller
	 */
	private $controller;

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_user;

	/**
	 * Regular user ID.
	 *
	 * @var int
	 */
	private $regular_user;

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();

		// Create Controller instance.
		$this->controller = new Controller();

		// Set up admin user.
		$this->admin_user = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		// Set up regular user.
		$this->regular_user = $this->factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		// Clear cache before each test.
		delete_transient( Controller::CACHE_KEY );
		Settings::invalidate_cache();
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down() {
		// Clear cache after each test.
		delete_transient( Controller::CACHE_KEY );
		delete_transient( Controller::FULL_CACHE_KEY );
		Settings::invalidate_cache();

		// Reset the backfill option so each test runs against a clean state.
		delete_option( Controller::HTACCESS_BACKFILL_OPTION );

		// Remove any .htaccess the tests may have written in the uploads dir.
		$upload_dir = wp_upload_dir();
		$htaccess   = trailingslashit( $upload_dir['basedir'] ) . File_Manager::MARKDOWN_DIR . '/.htaccess';
		if ( file_exists( $htaccess ) ) {
			unlink( $htaccess );
		}

		parent::tear_down();
	}

	/**
	 * Helper: create the markdown root directory without any contents.
	 *
	 * Simulates a legacy install whose llms.txt feature was enabled before
	 * this PR shipped — directory exists but no .htaccess yet.
	 */
	private function make_legacy_markdown_dir(): string {
		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . File_Manager::MARKDOWN_DIR;
		wp_mkdir_p( $dir );
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( file_exists( $htaccess ) ) {
			unlink( $htaccess );
		}
		return $dir;
	}

	/**
	 * Test that the Controller class exists.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\LLMS_Txt\Controller' ) );
	}

	/**
	 * Test that the Converter class exists.
	 */
	public function test_markdown_converter_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\Markdown\Converter' ) );
	}

	/**
	 * Test that query var is added.
	 */
	public function test_query_var_added() {
		$vars   = array();
		$result = $this->controller->add_query_var( $vars );

		$this->assertContains( 'llms_txt', $result );
	}

	/**
	 * Test cache invalidation.
	 */
	public function test_cache_invalidation() {
		// Set a cache value.
		set_transient( Controller::CACHE_KEY, 'test content' );

		// Verify cache is set.
		$this->assertEquals( 'test content', get_transient( Controller::CACHE_KEY ) );

		// Invalidate cache.
		$this->controller->invalidate_cache();

		// Verify cache is cleared.
		$this->assertFalse( get_transient( Controller::CACHE_KEY ) );
	}

	/**
	 * Test post markdown cache invalidation.
	 */
	public function test_markdown_cache_invalidation() {
		$post_id   = 123;
		$cache_key = 'designsetgo_llms_md_' . $post_id;

		// Set a cache value.
		set_transient( $cache_key, array( 'test' => 'data' ) );

		// Verify cache is set.
		$this->assertNotFalse( get_transient( $cache_key ) );

		// Invalidate cache with post ID.
		$this->controller->invalidate_cache( $post_id );

		// Verify individual cache is cleared.
		$this->assertFalse( get_transient( $cache_key ) );
	}

	/**
	 * Test schedule_flush_rewrite_rules sets transient.
	 */
	public function test_schedule_flush_rewrite_rules() {
		// Clear any existing transient.
		delete_transient( 'designsetgo_llms_txt_flush_rules' );

		// Call the static method.
		Controller::schedule_flush_rewrite_rules();

		// Verify transient is set.
		$this->assertTrue( (bool) get_transient( 'designsetgo_llms_txt_flush_rules' ) );

		// Clean up.
		delete_transient( 'designsetgo_llms_txt_flush_rules' );
	}

	/**
	 * Test post types endpoint requires admin permissions.
	 */
	public function test_post_types_endpoint_requires_admin() {
		// Test as regular user - should fail.
		wp_set_current_user( $this->regular_user );

		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/llms-txt/post-types' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test post types endpoint returns data for admin.
	 */
	public function test_post_types_endpoint_returns_data() {
		wp_set_current_user( $this->admin_user );

		$request  = new WP_REST_Request( 'GET', '/designsetgo/v1/llms-txt/post-types' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		// Should include 'post' and 'page'.
		$names = wp_list_pluck( $data, 'name' );
		$this->assertContains( 'post', $names );
		$this->assertContains( 'page', $names );

		// Should NOT include 'attachment'.
		$this->assertNotContains( 'attachment', $names );
	}

	/**
	 * Test flush cache endpoint requires admin permissions.
	 */
	public function test_flush_cache_endpoint_requires_admin() {
		// Test as regular user - should fail.
		wp_set_current_user( $this->regular_user );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/llms-txt/flush-cache' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test flush cache endpoint clears cache.
	 */
	public function test_flush_cache_endpoint_clears_cache() {
		wp_set_current_user( $this->admin_user );

		// Set a cache value.
		set_transient( Controller::CACHE_KEY, 'test content' );

		$request  = new WP_REST_Request( 'POST', '/designsetgo/v1/llms-txt/flush-cache' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( get_transient( Controller::CACHE_KEY ) );
	}

	/**
	 * Test flush_cache endpoint clears pattern-based markdown transients.
	 */
	public function test_flush_cache_endpoint_clears_markdown_transients() {
		wp_set_current_user( $this->admin_user );

		// Set pattern-based transients that should be flushed.
		set_transient( 'designsetgo_llms_md_post_1', 'cached markdown 1' );
		set_transient( 'designsetgo_llms_md_post_2', 'cached markdown 2' );

		$request  = new WP_REST_Request( 'POST', '/designsetgo/v1/llms-txt/flush-cache' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		// The bulk DELETE clears the DB rows; flush object cache to simulate
		// a new request (the in-memory cache still holds stale values).
		wp_cache_flush();

		$this->assertFalse( get_transient( 'designsetgo_llms_md_post_1' ) );
		$this->assertFalse( get_transient( 'designsetgo_llms_md_post_2' ) );
	}

	/**
	 * Test markdown endpoint returns 404 for non-existent post.
	 */
	public function test_markdown_endpoint_returns_404_for_missing_post() {
		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/llms-txt/markdown/999999' );
		$request->set_param( 'post_id', 999999 );

		$response = rest_do_request( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test markdown endpoint returns 404 for draft post.
	 */
	public function test_markdown_endpoint_returns_404_for_draft() {
		$post_id = $this->factory->post->create(
			array(
				'post_status' => 'draft',
				'post_title'  => 'Draft Post',
			)
		);

		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/llms-txt/markdown/' . $post_id );
		$request->set_param( 'post_id', $post_id );

		$response = rest_do_request( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test markdown endpoint returns 403 for excluded post.
	 */
	public function test_markdown_endpoint_returns_403_for_excluded() {
		// Enable the feature first.
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable'     => true,
					'post_types' => array( 'post' ),
				),
			)
		);
		Settings::invalidate_cache();

		$post_id = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Excluded Post',
			)
		);

		// Mark as excluded.
		update_post_meta( $post_id, Controller::EXCLUDE_META_KEY, true );

		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/llms-txt/markdown/' . $post_id );
		$request->set_param( 'post_id', $post_id );

		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test markdown endpoint returns content for valid post.
	 */
	public function test_markdown_endpoint_returns_content() {
		// Enable the feature first.
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable'     => true,
					'post_types' => array( 'post' ),
				),
			)
		);
		Settings::invalidate_cache();

		$post_id = $this->factory->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Test Post',
				'post_content' => '<!-- wp:paragraph --><p>Test content.</p><!-- /wp:paragraph -->',
			)
		);

		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/llms-txt/markdown/' . $post_id );
		$request->set_param( 'post_id', $post_id );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertEquals( $post_id, $data['id'] );
		$this->assertEquals( 'Test Post', $data['title'] );
		$this->assertArrayHasKey( 'markdown', $data );
		$this->assertStringContainsString( '# Test Post', $data['markdown'] );
	}

	/**
	 * Test prevent_trailing_slash cancels redirect for llms_txt requests.
	 */
	public function test_prevent_trailing_slash() {
		// Simulate llms_txt query var being set.
		set_query_var( 'llms_txt', '1' );

		$result = $this->controller->prevent_trailing_slash(
			'https://example.com/llms.txt/',
			'https://example.com/llms.txt'
		);
		$this->assertFalse( $result );

		// Reset query var.
		set_query_var( 'llms_txt', false );

		// Non-llms_txt requests should pass through.
		$url    = 'https://example.com/some-page/';
		$result = $this->controller->prevent_trailing_slash( $url, $url );
		$this->assertEquals( $url, $result );
	}

	/**
	 * Test prevent_trailing_slash rejects query var abuse on non-llms.txt paths.
	 */
	public function test_prevent_trailing_slash_rejects_query_var_abuse() {
		// Simulate someone adding ?llms_txt=1 to a random URL.
		set_query_var( 'llms_txt', '1' );

		$url    = 'https://example.com/some-page/';
		$result = $this->controller->prevent_trailing_slash( $url, 'https://example.com/some-page' );
		$this->assertEquals( $url, $result, 'Should not cancel redirect for non-llms.txt paths even with query var set.' );

		// Reset query var.
		set_query_var( 'llms_txt', false );
	}

	/**
	 * Test rewrite rule matches both /llms.txt and /llms.txt/ paths.
	 */
	public function test_rewrite_rule_matches_with_and_without_trailing_slash() {
		$pattern = Controller::REWRITE_PATTERN;

		$this->assertSame( 1, preg_match( '@' . $pattern . '@', 'llms.txt' ), 'Pattern should match llms.txt without trailing slash.' );
		$this->assertSame( 1, preg_match( '@' . $pattern . '@', 'llms.txt/' ), 'Pattern should match llms.txt with trailing slash.' );
		$this->assertSame( 0, preg_match( '@' . $pattern . '@', 'llms.txt.bak' ), 'Pattern should not match llms.txt.bak.' );
		$this->assertSame( 0, preg_match( '@' . $pattern . '@', 'xllms.txt' ), 'Pattern should not match xllms.txt.' );
	}

	/**
	 * Test posts limit constant.
	 */
	public function test_posts_limit_filter() {
		$this->assertEquals( 500, Generator::DEFAULT_POSTS_LIMIT );
	}

	/**
	 * Test exclusion meta key constant.
	 */
	public function test_exclusion_meta_key() {
		$this->assertEquals( '_designsetgo_exclude_llms', Controller::EXCLUDE_META_KEY );
	}

	/**
	 * Test cache key constant.
	 */
	public function test_cache_key() {
		$this->assertEquals( 'designsetgo_llms_txt_cache', Controller::CACHE_KEY );
	}

	/**
	 * Test full cache key constant.
	 */
	public function test_full_cache_key() {
		$this->assertEquals( 'designsetgo_llms_full_txt_cache', Controller::FULL_CACHE_KEY );
	}

	/**
	 * Test site_root_path() returns a trailing-slashed absolute path.
	 *
	 * Physical llms.txt lives at the served site root (home_url()), not
	 * necessarily ABSPATH. On the test install home == siteurl, so
	 * get_home_path() falls back to ABSPATH.
	 */
	public function test_site_root_path_is_trailing_slashed() {
		$path = File_Manager::site_root_path();

		$this->assertNotEmpty( $path );
		$this->assertSame(
			trailingslashit( $path ),
			$path,
			'site_root_path() must return a trailing-slashed path so callers can append a filename.'
		);
		$this->assertStringEndsWith( '/', $path );
	}

	/**
	 * Test the designsetgo_llms_txt_site_root filter overrides resolution
	 * and the result is still trailing-slashed.
	 */
	public function test_site_root_path_filter_override() {
		$callback = static function () {
			return '/custom/site/root'; // Intentionally no trailing slash.
		};
		add_filter( 'designsetgo_llms_txt_site_root', $callback );

		$this->assertSame( '/custom/site/root/', File_Manager::site_root_path() );

		remove_filter( 'designsetgo_llms_txt_site_root', $callback );
	}

	/**
	 * Test query vars include both llms_txt and llms_full_txt.
	 */
	public function test_query_vars_include_full_txt() {
		$vars   = array();
		$result = $this->controller->add_query_var( $vars );

		$this->assertContains( 'llms_txt', $result );
		$this->assertContains( 'llms_full_txt', $result );
	}

	/**
	 * Test cache invalidation also clears full cache.
	 */
	public function test_cache_invalidation_clears_full_cache() {
		set_transient( Controller::CACHE_KEY, 'test' );
		set_transient( Controller::FULL_CACHE_KEY, 'full test' );

		$this->controller->invalidate_cache();

		$this->assertFalse( get_transient( Controller::CACHE_KEY ) );
		$this->assertFalse( get_transient( Controller::FULL_CACHE_KEY ) );
	}

	/**
	 * Test prevent_trailing_slash handles llms-full.txt path.
	 */
	public function test_prevent_trailing_slash_handles_full_txt() {
		set_query_var( 'llms_full_txt', '1' );

		$result = $this->controller->prevent_trailing_slash(
			'https://example.com/llms-full.txt/',
			'https://example.com/llms-full.txt'
		);
		$this->assertFalse( $result );

		// Should reject non-llms paths.
		$url    = 'https://example.com/some-page/';
		$result = $this->controller->prevent_trailing_slash( $url, 'https://example.com/some-page' );
		$this->assertEquals( $url, $result );

		set_query_var( 'llms_full_txt', false );
	}

	/**
	 * Test full rewrite pattern matches correctly.
	 */
	public function test_full_rewrite_pattern_matches() {
		$pattern = Controller::FULL_REWRITE_PATTERN;

		$this->assertSame( 1, preg_match( '@' . $pattern . '@', 'llms-full.txt' ) );
		$this->assertSame( 1, preg_match( '@' . $pattern . '@', 'llms-full.txt/' ) );
		$this->assertSame( 0, preg_match( '@' . $pattern . '@', 'llms-full.txt.bak' ) );
		$this->assertSame( 0, preg_match( '@' . $pattern . '@', 'llms.txt' ) );
	}

	/**
	 * Test robots.txt integration adds llms.txt reference.
	 */
	public function test_robots_txt_includes_llms_reference() {
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable' => true,
				),
			)
		);
		Settings::invalidate_cache();

		$output = $this->controller->add_to_robots_txt( "User-agent: *\nDisallow:\n", true );

		$this->assertStringContainsString( 'llms.txt', $output );
		$this->assertStringContainsString( '# llms.txt - AI language model content index', $output );
	}

	/**
	 * Test robots.txt includes llms-full.txt when enabled.
	 */
	public function test_robots_txt_includes_full_txt_when_enabled() {
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable'           => true,
					'generate_full_txt' => true,
				),
			)
		);
		Settings::invalidate_cache();

		$output = $this->controller->add_to_robots_txt( '', true );

		$this->assertStringContainsString( 'llms-full.txt', $output );
	}

	/**
	 * Test robots.txt omits reference when feature is disabled.
	 */
	public function test_robots_txt_omits_when_disabled() {
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable' => false,
				),
			)
		);
		Settings::invalidate_cache();

		$output = $this->controller->add_to_robots_txt( '', true );

		$this->assertStringNotContainsString( 'llms.txt', $output );
	}

	/**
	 * Test backfill writes .htaccess into an existing legacy markdown dir.
	 */
	public function test_htaccess_backfill_writes_file_on_legacy_dir() {
		$dir = $this->make_legacy_markdown_dir();
		$this->enable_llms_feature();

		$this->controller->maybe_backfill_htaccess();

		$this->assertFileExists( trailingslashit( $dir ) . '.htaccess' );
		$this->assertEquals( 1, (int) get_option( Controller::HTACCESS_BACKFILL_OPTION ) );
	}

	/**
	 * Test backfill short-circuits when the option is already set.
	 */
	public function test_htaccess_backfill_skips_when_option_already_set() {
		$dir = $this->make_legacy_markdown_dir();
		$this->enable_llms_feature();
		update_option( Controller::HTACCESS_BACKFILL_OPTION, 1, true );

		$this->controller->maybe_backfill_htaccess();

		$this->assertFileDoesNotExist( trailingslashit( $dir ) . '.htaccess' );
	}

	/**
	 * Test backfill is a no-op when the feature is disabled.
	 */
	public function test_htaccess_backfill_noop_when_feature_disabled() {
		$dir = $this->make_legacy_markdown_dir();
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable' => false,
				),
			)
		);
		Settings::invalidate_cache();

		$this->controller->maybe_backfill_htaccess();

		$this->assertFileDoesNotExist( trailingslashit( $dir ) . '.htaccess' );
		$this->assertFalse( get_option( Controller::HTACCESS_BACKFILL_OPTION ) );
	}

	/**
	 * Test backfill marks done (without writing) when the dir doesn't exist.
	 *
	 * In that case the normal post-save path creates both dir and .htaccess,
	 * so we short-circuit to avoid rechecking every init.
	 */
	public function test_htaccess_backfill_marks_done_when_dir_missing() {
		// Ensure the directory is absent.
		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . File_Manager::MARKDOWN_DIR;
		if ( is_dir( $dir ) ) {
			// Remove htaccess first if present; then the dir (must be empty).
			$htaccess = trailingslashit( $dir ) . '.htaccess';
			if ( file_exists( $htaccess ) ) {
				unlink( $htaccess );
			}
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup; if non-empty we skip the test.
			if ( is_dir( $dir ) ) {
				$this->markTestSkipped( 'Could not remove markdown dir to simulate missing-dir scenario.' );
			}
		}

		$this->enable_llms_feature();

		$this->controller->maybe_backfill_htaccess();

		$this->assertDirectoryDoesNotExist( $dir );
		$this->assertEquals( 1, (int) get_option( Controller::HTACCESS_BACKFILL_OPTION ) );
	}

	/**
	 * Test the .htaccess preserves an existing (admin-authored) file.
	 */
	public function test_htaccess_preserves_existing_file() {
		$dir           = $this->make_legacy_markdown_dir();
		$htaccess_path = trailingslashit( $dir ) . '.htaccess';
		$custom        = "# admin override\nAddType text/plain .md\n";
		file_put_contents( $htaccess_path, $custom ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->enable_llms_feature();

		$this->controller->maybe_backfill_htaccess();

		$this->assertStringEqualsFile( $htaccess_path, $custom );
	}

	/**
	 * Test the generated .htaccess declares markdown + UTF-8.
	 */
	public function test_htaccess_contents_declare_markdown_and_utf8() {
		$dir = $this->make_legacy_markdown_dir();
		$this->enable_llms_feature();

		$this->controller->maybe_backfill_htaccess();

		$contents = file_get_contents( trailingslashit( $dir ) . '.htaccess' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Test read.
		$this->assertStringContainsString( 'AddType text/markdown .md', $contents );
		$this->assertStringContainsString( 'AddCharset UTF-8 .md', $contents );
		$this->assertStringContainsString( '<IfModule mod_mime.c>', $contents );
	}

	/**
	 * Test robots.txt omits reference when site is not public.
	 */
	public function test_robots_txt_omits_when_not_public() {
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable' => true,
				),
			)
		);
		Settings::invalidate_cache();

		$output = $this->controller->add_to_robots_txt( '', false );

		$this->assertStringNotContainsString( 'llms.txt', $output );
	}

	/**
	 * Test generate_content includes post excerpt as link description.
	 */
	public function test_generate_content_includes_excerpts() {
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable'     => true,
					'post_types' => array( 'post' ),
				),
			)
		);
		Settings::invalidate_cache();

		$this->factory->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'My Post',
				'post_excerpt' => 'A custom excerpt for the post.',
			)
		);

		$generator = $this->controller->get_generator();
		$content   = $generator->generate_content();

		$this->assertStringContainsString( 'A custom excerpt for the post', $content );
	}

	/**
	 * Test generate_content uses custom description when set.
	 */
	public function test_generate_content_uses_custom_description() {
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable'      => true,
					'post_types'  => array( 'post' ),
					'description' => 'Custom AI description for my site.',
				),
			)
		);
		Settings::invalidate_cache();

		$generator = $this->controller->get_generator();
		$content   = $generator->generate_content();

		$this->assertStringContainsString( '> Custom AI description for my site', $content );
	}

	/**
	 * Test generate_content falls back to tagline when no custom description.
	 */
	public function test_generate_content_falls_back_to_tagline() {
		update_option( 'blogdescription', 'Just another WordPress site' );
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable'      => true,
					'post_types'  => array( 'post' ),
					'description' => '',
				),
			)
		);
		Settings::invalidate_cache();

		$generator = $this->controller->get_generator();
		$content   = $generator->generate_content();

		$this->assertStringContainsString( '> Just another WordPress site', $content );
	}

	/**
	 * Test settings defaults include new llms_txt fields.
	 */
	public function test_settings_defaults_include_new_fields() {
		$defaults = Settings::get_defaults();

		$this->assertArrayHasKey( 'description', $defaults['llms_txt'] );
		$this->assertArrayHasKey( 'generate_full_txt', $defaults['llms_txt'] );
		$this->assertSame( '', $defaults['llms_txt']['description'] );
		$this->assertFalse( $defaults['llms_txt']['generate_full_txt'] );
	}

	/**
	 * Test generate_full_content includes section headings per post type.
	 */
	public function test_generate_full_content_includes_section_headings() {
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable'           => true,
					'post_types'       => array( 'post' ),
					'generate_full_txt' => true,
				),
			)
		);
		Settings::invalidate_cache();

		$this->factory->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Full Content Test',
				'post_content' => '<!-- wp:paragraph --><p>Full content body.</p><!-- /wp:paragraph -->',
			)
		);

		$generator = $this->controller->get_generator();
		$content   = $generator->generate_full_content();

		// Should contain the site name as H1.
		$this->assertStringContainsString( '# ', $content );
		// Should contain the post type section heading.
		$this->assertStringContainsString( '## ', $content );
		// Should contain the post content.
		$this->assertStringContainsString( 'Full content body', $content );
		// Should contain separators between posts.
		$this->assertStringContainsString( '---', $content );
	}

	/**
	 * Test generate_full_content uses custom description.
	 */
	public function test_generate_full_content_uses_custom_description() {
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable'           => true,
					'post_types'       => array( 'post' ),
					'generate_full_txt' => true,
					'description'      => 'Full content custom description.',
				),
			)
		);
		Settings::invalidate_cache();

		$this->factory->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Test',
				'post_content' => '<!-- wp:paragraph --><p>Content.</p><!-- /wp:paragraph -->',
			)
		);

		$generator = $this->controller->get_generator();
		$content   = $generator->generate_full_content();

		$this->assertStringContainsString( '> Full content custom description', $content );
	}

	/**
	 * Test physical full file option constant exists.
	 */
	public function test_physical_full_file_option_constant() {
		$this->assertEquals( 'designsetgo_llms_full_txt_physical', Controller::PHYSICAL_FULL_FILE_OPTION );
	}

	/**
	 * Test excerpt max length constant.
	 */
	public function test_excerpt_max_length_constant() {
		$this->assertEquals( 160, Generator::EXCERPT_MAX_LENGTH );
	}

	/**
	 * Test generate_content auto-generates excerpt from post content when no manual excerpt.
	 */
	public function test_generate_content_auto_generates_excerpt() {
		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable'     => true,
					'post_types' => array( 'post' ),
				),
			)
		);
		Settings::invalidate_cache();

		$this->factory->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Auto Excerpt Post',
				'post_excerpt' => '',
				'post_content' => '<!-- wp:paragraph --><p>This is a long paragraph that should be auto-trimmed into an excerpt for the llms.txt link description.</p><!-- /wp:paragraph -->',
			)
		);

		$generator = $this->controller->get_generator();
		$content   = $generator->generate_content();

		// Should contain the colon separator indicating a description is present.
		$this->assertMatchesRegularExpression( '/\): .+\(/', $content );
	}
}

/**
 * Markdown Converter Test Case
 */
class Test_Markdown_Converter extends WP_UnitTestCase {
	use Enables_LLMS_Feature;

	/**
	 * Converter instance.
	 *
	 * @var Converter
	 */
	private $converter;

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();

		$this->converter = new Converter();
	}

	/**
	 * Test converter creates title as H1.
	 */
	public function test_converts_title_to_h1() {
		$post = $this->factory->post->create_and_get(
			array(
				'post_title'   => 'Test Title',
				'post_content' => '',
				'post_status'  => 'publish',
			)
		);

		$markdown = $this->converter->convert( $post );

		$this->assertStringStartsWith( '# Test Title', $markdown );
	}

	/**
	 * Test converter handles paragraph blocks.
	 */
	public function test_converts_paragraph_block() {
		$post = $this->factory->post->create_and_get(
			array(
				'post_title'   => 'Test',
				'post_content' => '<!-- wp:paragraph --><p>Hello world.</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
			)
		);

		$markdown = $this->converter->convert( $post );

		$this->assertStringContainsString( 'Hello world.', $markdown );
	}

	/**
	 * Test converter handles heading blocks.
	 */
	public function test_converts_heading_block() {
		$post = $this->factory->post->create_and_get(
			array(
				'post_title'   => 'Test',
				'post_content' => '<!-- wp:heading {"level":2} --><h2>Section Title</h2><!-- /wp:heading -->',
				'post_status'  => 'publish',
			)
		);

		$markdown = $this->converter->convert( $post );

		$this->assertStringContainsString( '## Section Title', $markdown );
	}

	/**
	 * Test converter handles list blocks.
	 */
	public function test_converts_list_block() {
		$post = $this->factory->post->create_and_get(
			array(
				'post_title'   => 'Test',
				'post_content' => '<!-- wp:list --><ul><li>Item 1</li><li>Item 2</li></ul><!-- /wp:list -->',
				'post_status'  => 'publish',
			)
		);

		$markdown = $this->converter->convert( $post );

		$this->assertStringContainsString( 'Item 1', $markdown );
		$this->assertStringContainsString( 'Item 2', $markdown );
	}

	/**
	 * Test custom handler registration.
	 */
	public function test_register_custom_handler() {
		$this->converter->register_handler(
			'test/custom-block',
			function ( $block, $converter ) {
				return 'Custom output';
			}
		);

		// Use reflection to verify handler is registered.
		$reflection = new \ReflectionClass( $this->converter );
		$property   = $reflection->getProperty( 'handlers' );
		$property->setAccessible( true );
		$handlers = $property->getValue( $this->converter );

		$this->assertArrayHasKey( 'test/custom-block', $handlers );
	}

	/**
	 * Test escapes markdown special characters in title.
	 */
	public function test_escapes_markdown_in_title() {
		$post = $this->factory->post->create_and_get(
			array(
				'post_title'   => 'Title with *stars* and [brackets]',
				'post_content' => '',
				'post_status'  => 'publish',
			)
		);

		$markdown = $this->converter->convert( $post );

		// Should escape special characters.
		$this->assertStringContainsString( '\\*stars\\*', $markdown );
		$this->assertStringContainsString( '\\[brackets\\]', $markdown );
	}

	/**
	 * `Accept: text/markdown` alone selects markdown.
	 */
	public function test_negotiation_markdown_only_selects_markdown() {
		$this->assertSame( 'markdown', Negotiation_Handler::preferred_type( 'text/markdown' ) );
	}

	/**
	 * A browser-style Accept (html first) resolves to html.
	 */
	public function test_negotiation_browser_accept_selects_html() {
		$browser = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
		$this->assertSame( 'html', Negotiation_Handler::preferred_type( $browser ) );
	}

	/**
	 * When markdown outranks html via q-value, markdown wins.
	 */
	public function test_negotiation_q_values_prefer_markdown() {
		$accept = 'text/html;q=0.5, text/markdown;q=1.0';
		$this->assertSame( 'markdown', Negotiation_Handler::preferred_type( $accept ) );
	}

	/**
	 * When html outranks markdown via q-value, html wins.
	 */
	public function test_negotiation_q_values_prefer_html() {
		$accept = 'text/markdown;q=0.5, text/html;q=0.9';
		$this->assertSame( 'html', Negotiation_Handler::preferred_type( $accept ) );
	}

	/**
	 * Tie between html and markdown goes to html (safe default for browsers).
	 */
	public function test_negotiation_tie_prefers_html() {
		$this->assertSame( 'html', Negotiation_Handler::preferred_type( 'text/html, text/markdown' ) );
	}

	/**
	 * `*\/*` accepts everything — html wins by default tiebreak.
	 */
	public function test_negotiation_wildcard_prefers_html() {
		$this->assertSame( 'html', Negotiation_Handler::preferred_type( '*/*' ) );
	}

	/**
	 * RFC 7231 §5.3.2: an explicit media-range overrides a wildcard's q-value.
	 * `Accept: *\/*;q=0.9, text/html;q=0.5` should resolve to markdown
	 * (markdown from wildcard = 0.9, html explicit = 0.5).
	 */
	public function test_negotiation_explicit_overrides_wildcard() {
		$accept = '*/*;q=0.9, text/html;q=0.5';
		$this->assertSame( 'markdown', Negotiation_Handler::preferred_type( $accept ) );
	}

	/**
	 * An explicit `text/markdown` q-value must not be bumped by a wildcard.
	 * `text/markdown;q=0.3, *\/*;q=0.9` → markdown explicit at 0.3, html
	 * via wildcard at 0.9 → html wins.
	 */
	public function test_negotiation_wildcard_does_not_raise_explicit() {
		$accept = 'text/markdown;q=0.3, */*;q=0.9';
		$this->assertSame( 'html', Negotiation_Handler::preferred_type( $accept ) );
	}

	/**
	 * An Accept that lists only unsupported types returns 'none' so the
	 * handler can emit 406.
	 */
	public function test_negotiation_unsupported_only_is_none() {
		$this->assertSame( 'none', Negotiation_Handler::preferred_type( 'application/pdf' ) );
		$this->assertSame( 'none', Negotiation_Handler::preferred_type( 'image/png, application/json' ) );
	}

	/**
	 * `q=0` is an explicit rejection — a markdown-only Accept with q=0
	 * should not be treated as a markdown preference.
	 */
	public function test_negotiation_q_zero_is_rejection() {
		$this->assertSame( 'none', Negotiation_Handler::preferred_type( 'text/markdown;q=0' ) );
		$this->assertSame( 'html', Negotiation_Handler::preferred_type( 'text/html, text/markdown;q=0' ) );
	}

	/**
	 * Parameter ordering and whitespace shouldn't break parsing.
	 */
	public function test_negotiation_parses_parameters_loosely() {
		$accept = '  text/markdown ;  q=0.9 ,text/html;charset=utf-8;q=0.5';
		$this->assertSame( 'markdown', Negotiation_Handler::preferred_type( $accept ) );
	}

	/**
	 * Empty header resolves to 'none' — caller treats it as a no-op.
	 */
	public function test_negotiation_empty_accept_is_none() {
		$this->assertSame( 'none', Negotiation_Handler::preferred_type( '' ) );
	}

	/**
	 * Malformed q-values (non-numeric, out-of-range) should be treated as
	 * "missing" and default to 1.0 — not as explicit rejections that would
	 * silently flip a positive preference to 'none'.
	 */
	public function test_negotiation_malformed_q_defaults_to_one() {
		// `q=abc` is invalid; markdown should still count at q=1.0.
		$this->assertSame( 'markdown', Negotiation_Handler::preferred_type( 'text/markdown;q=abc, text/html;q=0.9' ) );

		// `q=1.5` is out of range; treat as 1.0 (clamp).
		$this->assertSame( 'markdown', Negotiation_Handler::preferred_type( 'text/markdown;q=1.5, text/html;q=0.9' ) );

		// A solo markdown with malformed q must NOT become a 406.
		$this->assertSame( 'markdown', Negotiation_Handler::preferred_type( 'text/markdown;q=abc' ) );
	}

	// ------------------------------------------------------------------
	// Path traversal guard in generate_full_content()
	// ------------------------------------------------------------------

	/**
	 * Test that a symlink or path traversal outside the markdown directory
	 * is rejected — the file content should NOT be included.
	 */
	public function test_full_content_rejects_path_traversal() {
		$this->enable_llms_feature();

		$post_id = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Traversal Test',
				'post_content' => '<p>Inline content</p>',
			)
		);

		$file_manager = new File_Manager();
		$md_dir       = $file_manager->get_directory();
		wp_mkdir_p( $md_dir );

		// Write a file outside the markdown directory.
		$outside_file = ABSPATH . 'secret-outside.md';
		file_put_contents( $outside_file, '# SECRET FILE SHOULD NOT APPEAR' );

		// Create a symlink inside the markdown directory pointing outside.
		$post     = get_post( $post_id );
		$filename = $file_manager->get_filename( $post ) . '.md';
		$symlink  = $md_dir . '/' . $filename;

		// Only run if we can create symlinks (skip on Windows or restricted envs).
		if ( ! @symlink( $outside_file, $symlink ) ) {
			@unlink( $outside_file );
			$this->markTestSkipped( 'Cannot create symlinks in this environment.' );
		}

		// Ensure file_exists reports true for this post so the generator
		// attempts to read the static file path.
		$this->assertTrue( file_exists( $symlink ), 'Symlink should exist on disk.' );

		$generator = new Generator( $file_manager );

		update_option(
			'designsetgo_settings',
			array(
				'llms_txt' => array(
					'enable'     => true,
					'post_types' => array( 'post' ),
				),
			)
		);
		Settings::invalidate_cache();

		$content = $generator->generate_full_content();

		// The secret content must NOT appear — the path traversal guard rejects it.
		$this->assertStringNotContainsString( 'SECRET FILE SHOULD NOT APPEAR', $content );

		// The inline fallback content should appear instead.
		$this->assertStringContainsString( 'Inline content', $content );

		// Cleanup.
		@unlink( $symlink );
		@unlink( $outside_file );
	}

	/**
	 * Test that a constructed filename with ../ is rejected by realpath resolution.
	 */
	public function test_full_content_rejects_dot_dot_path() {
		$this->enable_llms_feature();

		$file_manager = new File_Manager();
		$md_dir       = $file_manager->get_directory();
		wp_mkdir_p( $md_dir );

		// Place a file one level above the markdown directory.
		$parent_dir  = dirname( $md_dir );
		$secret_file = $parent_dir . '/evil-payload.md';
		file_put_contents( $secret_file, '# EVIL PAYLOAD' );

		// Verify the path resolves outside.
		$traversal_path = $md_dir . '/../evil-payload.md';
		$real_path      = realpath( $traversal_path );
		$real_dir       = realpath( $md_dir );

		// The guard: realpath resolves the ../ so it no longer starts with $md_dir.
		if ( $real_path && $real_dir ) {
			$normalized_path = wp_normalize_path( $real_path );
			$normalized_dir  = wp_normalize_path( trailingslashit( $real_dir ) );
			$this->assertNotSame( 0, strpos( $normalized_path, $normalized_dir ), 'Traversal path should NOT be within the markdown directory.' );
		}

		// Cleanup.
		@unlink( $secret_file );
	}

	// ------------------------------------------------------------------
	// designsetgo_llms_txt_extra_sections filter
	// ------------------------------------------------------------------

	/**
	 * Test that extra sections filter adds content to summary output.
	 */
	public function test_extra_sections_filter_in_summary() {
		$this->enable_llms_feature();

		$callback = function ( $sections, $variant ) {
			if ( 'summary' === $variant ) {
				$sections[] = '## Extra Plugin Section';
			}
			return $sections;
		};
		add_filter( 'designsetgo_llms_txt_extra_sections', $callback, 10, 2 );

		$file_manager = new File_Manager();
		$generator    = new Generator( $file_manager );
		$content      = $generator->generate_content();

		$this->assertStringContainsString( '## Extra Plugin Section', $content );

		remove_filter( 'designsetgo_llms_txt_extra_sections', $callback, 10 );
	}

	/**
	 * Test that extra sections filter adds content to full output.
	 */
	public function test_extra_sections_filter_in_full() {
		$this->enable_llms_feature();

		$callback = function ( $sections, $variant ) {
			if ( 'full' === $variant ) {
				$sections[] = '## Full Extra Section';
			}
			return $sections;
		};
		add_filter( 'designsetgo_llms_txt_extra_sections', $callback, 10, 2 );

		$file_manager = new File_Manager();
		$generator    = new Generator( $file_manager );
		$content      = $generator->generate_full_content();

		$this->assertStringContainsString( '## Full Extra Section', $content );

		remove_filter( 'designsetgo_llms_txt_extra_sections', $callback, 10 );
	}

	/**
	 * Test that non-string entries in extra sections are skipped.
	 */
	public function test_extra_sections_filter_skips_non_strings() {
		$this->enable_llms_feature();

		$callback = function () {
			return array(
				'## Valid Section',
				123,
				null,
				'',
				array( 'not a string' ),
				'## Another Valid',
			);
		};
		add_filter( 'designsetgo_llms_txt_extra_sections', $callback, 10 );

		$file_manager = new File_Manager();
		$generator    = new Generator( $file_manager );
		$content      = $generator->generate_content();

		$this->assertStringContainsString( '## Valid Section', $content );
		$this->assertStringContainsString( '## Another Valid', $content );
		$this->assertStringNotContainsString( '123', $content );

		remove_filter( 'designsetgo_llms_txt_extra_sections', $callback, 10 );
	}

	/**
	 * Test that variant parameter is passed correctly to filter.
	 */
	public function test_extra_sections_filter_receives_variant() {
		$this->enable_llms_feature();

		$received_variants = array();

		$callback = function ( $sections, $variant ) use ( &$received_variants ) {
			$received_variants[] = $variant;
			return $sections;
		};
		add_filter( 'designsetgo_llms_txt_extra_sections', $callback, 10, 2 );

		$file_manager = new File_Manager();
		$generator    = new Generator( $file_manager );

		$generator->generate_content();
		$generator->generate_full_content();

		$this->assertContains( 'summary', $received_variants );
		$this->assertContains( 'full', $received_variants );

		remove_filter( 'designsetgo_llms_txt_extra_sections', $callback, 10 );
	}

	/**
	 * Test that filter returning non-array is handled safely.
	 */
	public function test_extra_sections_filter_non_array_return() {
		$this->enable_llms_feature();

		$callback = function () {
			return 'not an array';
		};
		add_filter( 'designsetgo_llms_txt_extra_sections', $callback, 10 );

		$file_manager = new File_Manager();
		$generator    = new Generator( $file_manager );

		// Should not fatal — the is_array() check prevents iteration.
		$content = $generator->generate_content();
		$this->assertIsString( $content );

		remove_filter( 'designsetgo_llms_txt_extra_sections', $callback, 10 );
	}
}

<?php
/**
 * Existing public export repair and downstream visibility regression tests.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use DesignSetGo\LLMS_Txt\Controller;
use DesignSetGo\LLMS_Txt\File_Manager;
use DesignSetGo\LLMS_Txt\Generator;
use DesignSetGo\LLMS_Txt\REST_Controller;
use DesignSetGo\LLMS_Txt\Conflict_Detector;
use DesignSetGo\LLMS_Txt\Negotiation_Handler;
use DesignSetGo\Admin\Settings;

/** Real filesystem/public output regression coverage. */
class Test_LLMS_Public_Export_Repair extends \WP_UnitTestCase {
	/** @var string Isolated site root for physical export tests. */
	private $root;
	/** @var callable Site root filter callback. */
	private $root_filter;
	/** @var File_Manager File manager. */
	private $files;

	/** Keep physical exports away from the test WordPress root. */
	public function set_up() {
		parent::set_up();
		$this->root = trailingslashit( sys_get_temp_dir() ) . 'dsgo-public-repair-' . wp_generate_uuid4() . '/';
		wp_mkdir_p( $this->root );
		$this->root_filter = function () { return $this->root; };
		add_filter( 'designsetgo_llms_txt_site_root', $this->root_filter );
		$this->files = new File_Manager();
		delete_option( 'designsetgo_llms_public_exports_repaired' );
		Settings::invalidate_cache();
	}

	/** Remove only temporary export fixtures. */
	public function tear_down() {
		$filesystem = File_Manager::filesystem();
		$filesystem->delete( $this->root, true );
		$filesystem->delete( $this->files->get_directory(), true );
		remove_filter( 'designsetgo_llms_txt_site_root', $this->root_filter );
		wp_set_current_user( 0 );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	/** Public stored paragraph with a login-only secret. */
	private function post(): \WP_Post {
		return self::factory()->post->create_and_get(
			array(
				'post_type' => 'page', 'post_status' => 'publish',
				'post_content' => '<!-- wp:paragraph {"dsgoVisibility":{"rules":[{"type":"auth","value":true}]}} --><p>STALE_SECRET</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>PUBLIC_BODY</p><!-- /wp:paragraph -->',
			)
		);
	}

	/** No version bump is required to delete legacy physical/static exports. */
	public function test_upgrade_removes_owned_exports_and_caches_even_when_disabled() {
		$post = $this->post();
		$this->files->ensure_directory( 'nested' );
		File_Manager::fs_put_contents( $this->files->get_directory() . '/nested/legacy.md', 'STALE_SECRET' );
		File_Manager::fs_put_contents( $this->root . 'llms-full.txt', 'STALE_SECRET' );
		File_Manager::fs_put_contents( $this->root . 'llms.txt', 'user-managed' );
		update_option( Controller::PHYSICAL_FULL_FILE_OPTION, true );
		set_transient( Controller::FULL_CACHE_KEY, 'STALE_SECRET', DAY_IN_SECONDS );
		set_transient( 'designsetgo_llms_md_' . $post->ID, array( 'markdown' => 'STALE_SECRET' ), DAY_IN_SECONDS );
		// Saving the fixture can complete the normal first-run repair before seeding legacy files.
		delete_option( 'designsetgo_llms_public_exports_repaired' );
		$controller = new Controller();
		$this->assertTrue( is_callable( array( $controller, 'maybe_repair_exports' ) ) );
		$controller->maybe_repair_exports();
		$this->assertFileDoesNotExist( $this->files->get_directory() . '/nested/legacy.md' );
		$this->assertFileDoesNotExist( $this->root . 'llms-full.txt' );
		$this->assertSame( 'user-managed', file_get_contents( $this->root . 'llms.txt' ) );
		$this->assertFalse( get_transient( 'designsetgo_llms_md_' . $post->ID ) );
		$this->assertFalse( get_transient( Controller::FULL_CACHE_KEY ) );
		$this->assertTrue( (bool) get_option( 'designsetgo_llms_public_exports_repaired' ) );
		// A later safe export must survive subsequent requests.
		$this->files->ensure_directory();
		File_Manager::fs_put_contents( $this->files->get_directory() . '/safe.md', 'PUBLIC_BODY' );
		$controller->maybe_repair_exports();
		$this->assertFileExists( $this->files->get_directory() . '/safe.md' );
	}

	/** All public output paths must use anonymous conversion after the repair. */
	public function test_rest_static_full_and_negotiated_exports_omit_hidden_content() {
		$post = $this->post();
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		update_option( 'designsetgo_settings', array( 'llms_txt' => array( 'enable' => true, 'post_types' => array( 'page' ), 'generate_full_txt' => true ) ) );
		Settings::invalidate_cache();
		$generator = new Generator( $this->files );
		$rest = new REST_Controller( $this->files, $generator, new Conflict_Detector() );
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'post_id', $post->ID );
		$markdown = $rest->get_post_markdown( $request )->get_data()['markdown'];
		$this->assertStringNotContainsString( 'STALE_SECRET', $markdown );
		$this->assertStringContainsString( 'PUBLIC_BODY', $markdown );
		$this->assertTrue( $this->files->generate_file( $post->ID ) );
		$this->assertStringNotContainsString( 'STALE_SECRET', file_get_contents( $this->files->get_directory() . '/' . $this->files->get_filename( $post ) . '.md' ) );
		$this->assertStringNotContainsString( 'STALE_SECRET', $generator->generate_full_content() );
		$handler = new Negotiation_Handler( $this->files, $generator );
		$method = new \ReflectionMethod( $handler, 'read_markdown' );
		$method->setAccessible( true );
		$this->assertStringNotContainsString( 'STALE_SECRET', $method->invoke( $handler, $post ) );
		$this->assertSame( $admin, get_current_user_id() );
	}

	/** Legacy response cache cannot bypass the repaired converter. */
	public function test_rest_does_not_reuse_legacy_markdown_cache() {
		$post = $this->post();
		update_option( 'designsetgo_settings', array( 'llms_txt' => array( 'enable' => true, 'post_types' => array( 'page' ) ) ) );
		Settings::invalidate_cache();
		set_transient( 'designsetgo_llms_md_' . $post->ID, array( 'markdown' => 'STALE_SECRET' ), DAY_IN_SECONDS );
		$rest = new REST_Controller( $this->files, new Generator( $this->files ), new Conflict_Detector() );
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'post_id', $post->ID );
		$this->assertStringNotContainsString( 'STALE_SECRET', $rest->get_post_markdown( $request )->get_data()['markdown'] );
	}
	/** Failed deletion stays retryable; no stale file is read while cleanup is pending. */
	public function test_failed_cleanup_ignores_stale_files_and_retries() {
		$post = $this->post();
		update_option( 'designsetgo_settings', array( 'llms_txt' => array( 'enable' => true, 'post_types' => array( 'page' ) ) ) );
		Settings::invalidate_cache();
		$this->files->ensure_directory();
		$path = $this->files->get_directory() . '/' . $this->files->get_filename( $post ) . '.md';
		File_Manager::fs_put_contents( $path, 'STALE_SECRET' );
		delete_option( 'designsetgo_llms_public_exports_repaired' );
		$filesystem = $GLOBALS['wp_filesystem'];
		$GLOBALS['wp_filesystem'] = new class( false ) extends \WP_Filesystem_Direct {
			public function delete( $file, $recursive = false, $type = false ) {
				return false;
			}
		};
		try {
			$controller = new Controller();
			$controller->maybe_repair_exports();
			$this->assertFalse( get_option( 'designsetgo_llms_public_exports_repaired' ) );
			$generator = new Generator( $this->files );
			$this->assertStringNotContainsString( 'STALE_SECRET', $generator->generate_full_content() );
			$handler = new Negotiation_Handler( $this->files, $generator );
			$method = new \ReflectionMethod( $handler, 'read_markdown' );
			$method->setAccessible( true );
			$this->assertStringNotContainsString( 'STALE_SECRET', $method->invoke( $handler, $post ) );
			$this->assertFileExists( $path );
		} finally {
			$GLOBALS['wp_filesystem'] = $filesystem;
		}
		$controller->maybe_repair_exports();
		$this->assertTrue( (bool) get_option( 'designsetgo_llms_public_exports_repaired' ) );
		$this->assertFileDoesNotExist( $path );
	}

	/** A valid password cookie authorizes HTML reading, never public static export. */
	public function test_valid_password_cookie_cannot_publish_static_markdown() {
		$this->with_unlocked_protected_post( function ( $post ) {
			$this->files->ensure_directory();
			$path = $this->files->get_directory() . '/' . $this->files->get_filename( $post ) . '.md';
			File_Manager::fs_put_contents( $path, 'PASSWORD_SECRET' );
			$result = $this->files->generate_file( $post->ID );
			$this->assertWPError( $result );
			$this->assertSame( 'not_public', $result->get_error_code() );
			$this->assertFileDoesNotExist( $path );
		} );
	}

	/** An unlocked browser session must not put protected content into a public REST cache. */
	public function test_valid_password_cookie_cannot_export_rest_markdown() {
		$this->with_unlocked_protected_post( function ( $post ) {
			$rest = new REST_Controller( $this->files, new Generator( $this->files ), new Conflict_Detector() );
			$request = new \WP_REST_Request( 'GET' );
			$request->set_param( 'post_id', $post->ID );
			$result = $rest->get_post_markdown( $request );
			$this->assertWPError( $result );
			$this->assertSame( 'not_public', $result->get_error_code() );
			$this->assertFalse( get_transient( 'designsetgo_llms_md_public_' . $post->ID ) );
		} );
	}

	/** Use WordPress's real postpass hash format and prove that the cookie unlocks HTML. */
	private function with_unlocked_protected_post( callable $callback ): void {
		$post = self::factory()->post->create_and_get( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_password' => 'export-password', 'post_content' => '<!-- wp:paragraph --><p>PASSWORD_SECRET</p><!-- /wp:paragraph -->' ) );
		update_option( 'designsetgo_settings', array( 'llms_txt' => array( 'enable' => true, 'post_types' => array( 'page' ) ) ) );
		Settings::invalidate_cache();
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher = new \PasswordHash( 8, true );
		$key = 'wp-postpass_' . COOKIEHASH;
		$previous = $_COOKIE[ $key ] ?? null;
		$_COOKIE[ $key ] = $hasher->HashPassword( $post->post_password );
		try {
			$this->assertFalse( post_password_required( $post ), 'The real cookie must unlock normal HTML before checking the export boundary.' );
			$callback( $post );
		} finally {
			if ( null === $previous ) {
				unset( $_COOKIE[ $key ] );
			} else {
				$_COOKIE[ $key ] = $previous;
			}
		}
	}

}

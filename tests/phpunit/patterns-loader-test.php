<?php
/**
 * Tests for the Patterns Loader
 *
 * Tests performance optimizations and security hardening:
 * - Front-end request gating (patterns skip non-editor requests)
 * - Category-level transient caching with full pattern data
 * - Cache validation (version + file hash) and invalidation
 * - Relative path security validation
 * - Pattern registration, category registration, and context filtering
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\Patterns\Loader;
use ReflectionClass;

/**
 * Patterns Loader Test Case
 */
class Test_Patterns_Loader extends WP_UnitTestCase {

	/**
	 * Loader instance.
	 *
	 * @var Loader
	 */
	private $loader;

	/**
	 * Reflection instance for accessing private methods.
	 *
	 * @var ReflectionClass
	 */
	private $reflection;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$this->loader     = new Loader();
		$this->reflection = new ReflectionClass( $this->loader );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		// Clean up legacy transient.
		delete_transient( Loader::CACHE_TRANSIENT );

		// Clean up all category-level transients.
		foreach ( Loader::ALLOWED_CATEGORIES as $category ) {
			delete_transient( Loader::CACHE_TRANSIENT_PREFIX . $category );
		}

		// Remove any test filters.
		remove_all_filters( 'designsetgo_pattern_cache_enabled' );
		remove_all_filters( 'designsetgo_pattern_cache_duration' );
		remove_all_filters( 'designsetgo_pattern_post_types_map' );

		parent::tear_down();
	}

	/**
	 * Helper to call private/static methods for testing.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @return mixed Method result.
	 */
	private function call_private_method( $method, $args = array() ) {
		$method_ref = $this->reflection->getMethod( $method );
		$method_ref->setAccessible( true );
		return $method_ref->invokeArgs( $this->loader, $args );
	}

	/**
	 * Helper to call private static methods for testing.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @return mixed Method result.
	 */
	private function call_private_static_method( $method, $args = array() ) {
		$method_ref = $this->reflection->getMethod( $method );
		$method_ref->setAccessible( true );
		return $method_ref->invokeArgs( null, $args );
	}

	/**
	 * Build a valid compressed transient payload.
	 *
	 * @param array  $patterns Patterns to encode.
	 * @param string $hash     File hash to embed.
	 * @param string $version  Plugin version string.
	 * @return array Transient value ready for set_transient().
	 */
	private function make_compressed_transient( $patterns, $hash, $version = DESIGNSETGO_VERSION ) {
		return array(
			'version'    => $version,
			'hash'       => $hash,
			'compressed' => base64_encode( gzcompress( wp_json_encode( $patterns ) ) ),
		);
	}

	// -------------------------------------------------------------------------
	// Class structure tests
	// -------------------------------------------------------------------------

	/**
	 * Test that the Loader class exists.
	 */
	public function test_loader_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\Patterns\Loader' ) );
	}

	/**
	 * Test that ALLOWED_CATEGORIES constant contains expected categories.
	 */
	public function test_allowed_categories_defined() {
		$expected = array(
			'homepage',
			'header',
			'footer',
			'hero',
			'features',
			'pricing',
			'testimonials',
			'team',
			'cta',
			'content',
			'faq',
			'modal',
			'gallery',
			'contact',
			'services',
			'headings',
		);

		$this->assertSame( $expected, Loader::ALLOWED_CATEGORIES );
	}

	/**
	 * Test that CACHE_TRANSIENT constant is defined (legacy).
	 */
	public function test_cache_transient_constant() {
		$this->assertSame( 'dsgo_pattern_files', Loader::CACHE_TRANSIENT );
	}

	/**
	 * Test that CACHE_TRANSIENT_PREFIX constant is defined.
	 */
	public function test_cache_transient_prefix_constant() {
		$this->assertSame( 'dsgo_pattern_data_', Loader::CACHE_TRANSIENT_PREFIX );
	}

	// -------------------------------------------------------------------------
	// is_editor_request() tests
	// -------------------------------------------------------------------------

	/**
	 * Test is_editor_request returns true in admin context.
	 */
	public function test_is_editor_request_in_admin() {
		// PHPUnit runs via CLI where is_admin() is false.
		// Simulate an admin screen to flip it to true.
		set_current_screen( 'edit-post' );

		$result = $this->call_private_static_method( 'is_editor_request' );
		$this->assertTrue( $result, 'is_editor_request should return true in admin context' );

		// Restore non-admin context.
		set_current_screen( 'front' );
	}

	/**
	 * Test is_editor_request returns false on front-end requests.
	 */
	public function test_is_editor_request_false_on_frontend() {
		// Ensure we are in a non-admin, non-REST, non-CLI context.
		set_current_screen( 'front' );

		// Temporarily unset REQUEST_URI to prevent URL-based REST detection.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- saving raw superglobal for test restore.
		$saved_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		unset( $_SERVER['REQUEST_URI'] );

		$result = $this->call_private_static_method( 'is_editor_request' );
		$this->assertFalse( $result, 'is_editor_request should return false on front-end' );

		// Restore.
		if ( null !== $saved_uri ) {
			$_SERVER['REQUEST_URI'] = $saved_uri;
		}
	}

	// -------------------------------------------------------------------------
	// is_valid_relative_path() tests
	// -------------------------------------------------------------------------

	/**
	 * Test valid relative paths are accepted.
	 */
	public function test_valid_relative_paths() {
		$valid_paths = array(
			'hero/hero-centered.php',
			'homepage/homepage-agency.php',
			'content/content-timeline.php',
			'cta/cta-banner-gradient.php',
		);

		foreach ( $valid_paths as $path ) {
			$this->assertTrue(
				$this->call_private_static_method( 'is_valid_relative_path', array( $path ) ),
				"Path '$path' should be valid"
			);
		}
	}

	/**
	 * Test directory traversal sequences are rejected.
	 */
	public function test_rejects_directory_traversal() {
		$malicious_paths = array(
			'../../../etc/passwd',
			'hero/../../../wp-config.php',
			'..\\..\\wp-config.php',
		);

		foreach ( $malicious_paths as $path ) {
			$this->assertFalse(
				$this->call_private_static_method( 'is_valid_relative_path', array( $path ) ),
				"Path '$path' should be rejected (directory traversal)"
			);
		}
	}

	/**
	 * Test absolute paths are rejected.
	 */
	public function test_rejects_absolute_paths() {
		$absolute_paths = array(
			'/etc/passwd',
			'/tmp/malicious.php',
			'\\windows\\system32\\cmd.php',
		);

		foreach ( $absolute_paths as $path ) {
			$this->assertFalse(
				$this->call_private_static_method( 'is_valid_relative_path', array( $path ) ),
				"Path '$path' should be rejected (absolute path)"
			);
		}
	}

	/**
	 * Test non-PHP extensions are rejected.
	 */
	public function test_rejects_non_php_extensions() {
		$wrong_extensions = array(
			'hero/hero-centered.html',
			'hero/hero-centered.phtml',
			'hero/hero-centered.txt',
			'hero/hero-centered.php.bak',
			'hero/hero-centered',
		);

		foreach ( $wrong_extensions as $path ) {
			$this->assertFalse(
				$this->call_private_static_method( 'is_valid_relative_path', array( $path ) ),
				"Path '$path' should be rejected (wrong extension)"
			);
		}
	}

	/**
	 * Test empty and non-string values are rejected.
	 */
	public function test_rejects_empty_and_non_string() {
		$this->assertFalse(
			$this->call_private_static_method( 'is_valid_relative_path', array( '' ) ),
			'Empty string should be rejected'
		);

		$this->assertFalse(
			$this->call_private_static_method( 'is_valid_relative_path', array( null ) ),
			'Null should be rejected'
		);

		$this->assertFalse(
			$this->call_private_static_method( 'is_valid_relative_path', array( 123 ) ),
			'Integer should be rejected'
		);

		$this->assertFalse(
			$this->call_private_static_method( 'is_valid_relative_path', array( array() ) ),
			'Array should be rejected'
		);
	}

	// -------------------------------------------------------------------------
	// Pattern category registration tests
	// -------------------------------------------------------------------------

	/**
	 * Test that pattern categories are registered.
	 */
	public function test_pattern_categories_registered() {
		$this->loader->register_pattern_categories();

		$registry = \WP_Block_Pattern_Categories_Registry::get_instance();

		$expected_categories = array(
			'dsgo-homepage',
			'dsgo-hero',
			'dsgo-features',
			'dsgo-pricing',
			'dsgo-testimonials',
			'dsgo-team',
			'dsgo-cta',
			'dsgo-content',
			'dsgo-faq',
			'dsgo-modal',
			'dsgo-gallery',
			'dsgo-contact',
		);

		foreach ( $expected_categories as $slug ) {
			$this->assertTrue(
				$registry->is_registered( $slug ),
				"Pattern category '$slug' should be registered"
			);
		}
	}

	/**
	 * Test that duplicate category registration is safe.
	 */
	public function test_duplicate_category_registration_safe() {
		// Register twice — should not error.
		$this->loader->register_pattern_categories();
		$this->loader->register_pattern_categories();

		$registry = \WP_Block_Pattern_Categories_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'dsgo-hero' ) );
	}

	// -------------------------------------------------------------------------
	// Category-level transient cache tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_category_patterns stores results in category transient.
	 */
	public function test_category_patterns_cached_in_transient() {
		// Enable caching for this test (normally disabled when WP_DEBUG is true).
		add_filter( 'designsetgo_pattern_cache_enabled', '__return_true' );

		$cached = get_transient( Loader::CACHE_TRANSIENT_PREFIX . 'hero' );
		// Should be false before caching.
		$this->assertFalse( $cached, 'Transient should not exist before caching' );

		// Call get_category_patterns to populate cache.
		$this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		$cached = get_transient( Loader::CACHE_TRANSIENT_PREFIX . 'hero' );

		$this->assertIsArray( $cached, 'Transient should contain an array' );
		$this->assertArrayHasKey( 'version', $cached, 'Cache should include version key' );
		$this->assertArrayHasKey( 'hash', $cached, 'Cache should include hash key' );
		$this->assertArrayHasKey( 'compressed', $cached, 'Cache should include compressed key' );
		$this->assertSame( DESIGNSETGO_VERSION, $cached['version'], 'Cached version should match plugin version' );
		$this->assertIsString( $cached['compressed'], 'Cached compressed data should be a string' );

		// Verify compressed data can be decoded back to a patterns array.
		$raw = base64_decode( $cached['compressed'] );
		$this->assertNotFalse( $raw, 'Compressed data should be valid base64' );
		$decompressed = gzuncompress( $raw );
		$this->assertNotFalse( $decompressed, 'Compressed data should decompress' );
		$patterns = json_decode( $decompressed, true );
		$this->assertIsArray( $patterns, 'Decompressed data should be an array of patterns' );

		remove_filter( 'designsetgo_pattern_cache_enabled', '__return_true' );
	}

	/**
	 * Test that cached pattern data includes content key.
	 */
	public function test_cached_patterns_include_content() {
		$patterns = $this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		$this->assertNotEmpty( $patterns, 'Hero category should have patterns' );

		foreach ( $patterns as $slug => $pattern ) {
			$this->assertArrayHasKey(
				'content',
				$pattern,
				"Cached pattern '$slug' should have content"
			);
			$this->assertNotEmpty(
				$pattern['content'],
				"Cached pattern '$slug' content should not be empty"
			);
		}
	}

	/**
	 * Test that cached pattern data is returned on subsequent calls.
	 */
	public function test_category_patterns_returns_cached_data() {
		// Caching is disabled when WP_DEBUG is true; force it on so the transient is consulted.
		add_filter( 'designsetgo_pattern_cache_enabled', '__return_true' );

		$fake_patterns = array(
			'designsetgo/hero/hero-test' => array(
				'title'   => 'Test Hero',
				'content' => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->',
			),
		);

		// Compute the real hash for the hero directory so the cache validates.
		$hero_dir = DESIGNSETGO_PATH . 'patterns/hero/';
		$files    = glob( $hero_dir . '*.php' );
		$hash     = $this->call_private_static_method( 'compute_files_hash', array( $files ) );

		set_transient(
			Loader::CACHE_TRANSIENT_PREFIX . 'hero',
			$this->make_compressed_transient( $fake_patterns, $hash ),
			DAY_IN_SECONDS
		);

		$result = $this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		$this->assertSame( $fake_patterns, $result, 'Should return cached patterns without re-scanning' );
	}

	/**
	 * Test that stale cache (wrong version) triggers a fresh scan.
	 */
	public function test_stale_version_cache_rebuilds() {
		set_transient(
			Loader::CACHE_TRANSIENT_PREFIX . 'hero',
			$this->make_compressed_transient(
				array(
					'designsetgo/hero/hero-fake' => array(
						'title'   => 'Fake',
						'content' => 'fake',
					),
				),
				'fake-hash',
				'0.0.0-old'
			),
			DAY_IN_SECONDS
		);

		$result = $this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		// Result should NOT contain our fake data — it should be a fresh scan.
		$this->assertArrayNotHasKey( 'designsetgo/hero/hero-fake', $result, 'Stale cache should be discarded' );
	}

	/**
	 * Test that stale cache (wrong file hash) triggers a fresh scan.
	 */
	public function test_stale_hash_cache_rebuilds() {
		set_transient(
			Loader::CACHE_TRANSIENT_PREFIX . 'hero',
			$this->make_compressed_transient(
				array(
					'designsetgo/hero/hero-fake' => array(
						'title'   => 'Fake',
						'content' => 'fake',
					),
				),
				'deliberately-wrong-hash'
			),
			DAY_IN_SECONDS
		);

		$result = $this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		// Result should NOT contain our fake data — hash mismatch triggers rebuild.
		$this->assertArrayNotHasKey( 'designsetgo/hero/hero-fake', $result, 'Cache with wrong hash should be discarded' );
	}

	/**
	 * Test that malformed cache data triggers a fresh scan.
	 */
	public function test_malformed_cache_rebuilds() {
		// Missing 'patterns' key.
		set_transient(
			Loader::CACHE_TRANSIENT_PREFIX . 'hero',
			array( 'version' => DESIGNSETGO_VERSION ),
			DAY_IN_SECONDS
		);

		$result = $this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result, 'Should return real patterns after malformed cache' );
	}

	/**
	 * Test that a non-string 'compressed' value in cache triggers a fresh scan.
	 */
	public function test_non_string_compressed_cache_rebuilds() {
		set_transient(
			Loader::CACHE_TRANSIENT_PREFIX . 'hero',
			array(
				'version'    => DESIGNSETGO_VERSION,
				'hash'       => 'some-hash',
				'compressed' => array( 'not' => 'a string' ),
			),
			DAY_IN_SECONDS
		);

		$result = $this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		$this->assertIsArray( $result, 'Should return fresh array when compressed is not a string' );
		$this->assertNotEmpty( $result, 'Should return real patterns after bad cache' );
	}

	/**
	 * Test that an invalid base64 string in 'compressed' triggers a fresh scan.
	 */
	public function test_corrupted_base64_cache_rebuilds() {
		set_transient(
			Loader::CACHE_TRANSIENT_PREFIX . 'hero',
			array(
				'version'    => DESIGNSETGO_VERSION,
				'hash'       => 'some-hash',
				'compressed' => '!!! not valid base64 !!!',
			),
			DAY_IN_SECONDS
		);

		$result = $this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result, 'Should return real patterns after invalid base64' );
	}

	/**
	 * Test that base64-encoded bytes with wrong zlib header trigger a fresh scan.
	 *
	 * GzCompress() always produces a 0x78 CMF byte first. Data starting with a
	 * different byte fails the header check before gzuncompress() is called.
	 */
	public function test_invalid_zlib_header_cache_rebuilds() {
		// 0xFF is not a valid zlib CMF byte.
		$bad_raw    = "\xFF\xFE" . str_repeat( 'x', 20 );
		$compressed = base64_encode( $bad_raw );

		set_transient(
			Loader::CACHE_TRANSIENT_PREFIX . 'hero',
			array(
				'version'    => DESIGNSETGO_VERSION,
				'hash'       => 'some-hash',
				'compressed' => $compressed,
			),
			DAY_IN_SECONDS
		);

		$result = $this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result, 'Should return real patterns after invalid zlib header' );
	}

	/**
	 * Test that valid zlib header but corrupted body triggers a fresh scan.
	 */
	public function test_corrupted_zlib_body_cache_rebuilds() {
		// Valid CMF byte (0x78) but the rest is garbage — gzuncompress() will return false.
		$bad_raw    = "\x78\x9C" . str_repeat( "\xAB", 20 );
		$compressed = base64_encode( $bad_raw );

		set_transient(
			Loader::CACHE_TRANSIENT_PREFIX . 'hero',
			array(
				'version'    => DESIGNSETGO_VERSION,
				'hash'       => 'some-hash',
				'compressed' => $compressed,
			),
			DAY_IN_SECONDS
		);

		$result = $this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result, 'Should return real patterns after corrupted zlib body' );
	}

	/**
	 * Test that valid zlib wrapping non-array JSON triggers a fresh scan.
	 */
	public function test_non_array_json_compressed_cache_rebuilds() {
		$compressed = base64_encode( gzcompress( '"just a string"' ) );

		set_transient(
			Loader::CACHE_TRANSIENT_PREFIX . 'hero',
			array(
				'version'    => DESIGNSETGO_VERSION,
				'hash'       => 'some-hash',
				'compressed' => $compressed,
			),
			DAY_IN_SECONDS
		);

		$result = $this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result, 'Should return real patterns when decompressed JSON is not an array' );
	}

	/**
	 * Test that clear_cache removes all category transients.
	 */
	public function test_clear_cache() {
		// Set legacy transient.
		set_transient(
			Loader::CACHE_TRANSIENT,
			array( 'version' => DESIGNSETGO_VERSION, 'map' => array() ),
			DAY_IN_SECONDS
		);

		// Set a few category transients.
		foreach ( array( 'hero', 'homepage', 'content' ) as $category ) {
			set_transient(
				Loader::CACHE_TRANSIENT_PREFIX . $category,
				$this->make_compressed_transient( array(), 'test' ),
				DAY_IN_SECONDS
			);
		}

		Loader::clear_cache();

		$this->assertFalse( get_transient( Loader::CACHE_TRANSIENT ), 'Legacy transient should be deleted' );

		foreach ( Loader::ALLOWED_CATEGORIES as $category ) {
			$this->assertFalse(
				get_transient( Loader::CACHE_TRANSIENT_PREFIX . $category ),
				"Category transient for '$category' should be deleted"
			);
		}
	}

	// -------------------------------------------------------------------------
	// Category pattern content tests
	// -------------------------------------------------------------------------

	/**
	 * Test that patterns are loaded for expected categories.
	 */
	public function test_patterns_loaded_for_expected_categories() {
		$expected_populated = array( 'hero', 'homepage', 'features', 'cta', 'content' );

		foreach ( $expected_populated as $category ) {
			$patterns = $this->call_private_method( 'get_category_patterns', array( $category ) );
			$this->assertNotEmpty(
				$patterns,
				"Category '$category' should have patterns"
			);
		}
	}

	/**
	 * Test that pattern slugs follow the expected format.
	 */
	public function test_pattern_slugs_follow_format() {
		$patterns = $this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		foreach ( $patterns as $slug => $pattern ) {
			$this->assertMatchesRegularExpression(
				'/^designsetgo\/[a-z0-9-]+\/[a-z0-9-]+$/',
				$slug,
				"Pattern slug '$slug' should match format designsetgo/category/name"
			);
		}
	}

	/**
	 * Test that non-existent category returns empty array.
	 */
	public function test_nonexistent_category_returns_empty() {
		// 'nonexistent' is not a valid directory, should return empty.
		$patterns = $this->call_private_method( 'get_category_patterns', array( 'nonexistent' ) );
		$this->assertEmpty( $patterns, 'Non-existent category should return empty array' );
	}

	/**
	 * Test that excluded categories (navigation) are not in loaded patterns.
	 */
	public function test_excluded_categories_not_loaded() {
		$patterns = $this->call_private_method( 'get_category_patterns', array( 'navigation' ) );
		$this->assertEmpty( $patterns, 'Navigation should not produce patterns' );
	}

	// -------------------------------------------------------------------------
	// compute_files_hash() tests
	// -------------------------------------------------------------------------

	/**
	 * Test that compute_files_hash returns a consistent hash.
	 */
	public function test_compute_files_hash_consistent() {
		$hero_dir = DESIGNSETGO_PATH . 'patterns/hero/';
		$files    = glob( $hero_dir . '*.php' );

		$hash1 = $this->call_private_static_method( 'compute_files_hash', array( $files ) );
		$hash2 = $this->call_private_static_method( 'compute_files_hash', array( $files ) );

		$this->assertSame( $hash1, $hash2, 'Hash should be consistent for same files' );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $hash1, 'Hash should be an MD5 string' );
	}

	/**
	 * Test that compute_files_hash changes with different file sets.
	 */
	public function test_compute_files_hash_differs_for_different_files() {
		$hero_files = glob( DESIGNSETGO_PATH . 'patterns/hero/*.php' );
		$cta_files  = glob( DESIGNSETGO_PATH . 'patterns/cta/*.php' );

		$hash1 = $this->call_private_static_method( 'compute_files_hash', array( $hero_files ) );
		$hash2 = $this->call_private_static_method( 'compute_files_hash', array( $cta_files ) );

		$this->assertNotSame( $hash1, $hash2, 'Different file sets should produce different hashes' );
	}

	/**
	 * Test that compute_files_hash handles empty array.
	 */
	public function test_compute_files_hash_empty_array() {
		$hash = $this->call_private_static_method( 'compute_files_hash', array( array() ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $hash, 'Empty array should still produce valid hash' );
	}

	// -------------------------------------------------------------------------
	// Pattern registration tests
	// -------------------------------------------------------------------------

	/**
	 * Test that patterns are registered with correct slugs.
	 */
	public function test_patterns_registered_with_correct_slugs() {
		$this->loader->register_pattern_categories();
		$this->loader->register_patterns();

		$registry = \WP_Block_Patterns_Registry::get_instance();

		// Spot-check a few known patterns.
		$expected_slugs = array(
			'designsetgo/hero/hero-centered',
			'designsetgo/hero/hero-split',
			'designsetgo/cta/cta-banner',
			'designsetgo/content/content-timeline',
		);

		foreach ( $expected_slugs as $slug ) {
			$this->assertTrue(
				$registry->is_registered( $slug ),
				"Pattern '$slug' should be registered"
			);
		}
	}

	/**
	 * Test that registered patterns have required content key.
	 */
	public function test_registered_patterns_have_content() {
		$this->loader->register_pattern_categories();
		$this->loader->register_patterns();

		$registry = \WP_Block_Patterns_Registry::get_instance();
		$patterns = $registry->get_all_registered();

		// Filter to only DesignSetGo patterns.
		$dsgo_patterns = array_filter(
			$patterns,
			function ( $pattern ) {
				return isset( $pattern['name'] ) && 0 === strpos( $pattern['name'], 'designsetgo/' );
			}
		);

		$this->assertNotEmpty( $dsgo_patterns, 'Should have registered DesignSetGo patterns' );

		foreach ( $dsgo_patterns as $pattern ) {
			$this->assertArrayHasKey(
				'content',
				$pattern,
				"Pattern '{$pattern['name']}' should have content"
			);
			$this->assertNotEmpty(
				$pattern['content'],
				"Pattern '{$pattern['name']}' content should not be empty"
			);
		}
	}

	// -------------------------------------------------------------------------
	// Context filtering tests (Phase 2)
	// -------------------------------------------------------------------------

	/**
	 * Test that homepage patterns have postTypes restricted to page.
	 */
	public function test_homepage_patterns_restricted_to_pages() {
		$this->loader->register_pattern_categories();
		$this->loader->register_patterns();

		$registry = \WP_Block_Patterns_Registry::get_instance();
		$patterns = $registry->get_all_registered();

		$homepage_patterns = array_filter(
			$patterns,
			function ( $pattern ) {
				return isset( $pattern['name'] ) && 0 === strpos( $pattern['name'], 'designsetgo/homepage/' );
			}
		);

		$this->assertNotEmpty( $homepage_patterns, 'Should have homepage patterns' );

		foreach ( $homepage_patterns as $pattern ) {
			$this->assertArrayHasKey(
				'postTypes',
				$pattern,
				"Homepage pattern '{$pattern['name']}' should have postTypes"
			);
			$this->assertSame(
				array( 'page' ),
				$pattern['postTypes'],
				"Homepage pattern '{$pattern['name']}' should be restricted to pages"
			);
		}
	}

	/**
	 * Test that non-homepage patterns do NOT have postTypes restriction.
	 */
	public function test_non_homepage_patterns_unrestricted_post_types() {
		$this->loader->register_pattern_categories();
		$this->loader->register_patterns();

		$registry = \WP_Block_Patterns_Registry::get_instance();
		$patterns = $registry->get_all_registered();

		$hero_patterns = array_filter(
			$patterns,
			function ( $pattern ) {
				return isset( $pattern['name'] ) && 0 === strpos( $pattern['name'], 'designsetgo/hero/' );
			}
		);

		$this->assertNotEmpty( $hero_patterns, 'Should have hero patterns' );

		foreach ( $hero_patterns as $pattern ) {
			$this->assertArrayNotHasKey(
				'postTypes',
				$pattern,
				"Hero pattern '{$pattern['name']}' should NOT have postTypes restriction"
			);
		}
	}

	/**
	 * Test that patterns do not have blockTypes restriction by default.
	 *
	 * Patterns should not be restricted to core/post-content because that
	 * prevents them from appearing in the top-level inserter when other
	 * plugins register unrestricted patterns.
	 */
	public function test_patterns_have_no_block_types_restriction() {
		$this->loader->register_pattern_categories();
		$this->loader->register_patterns();

		$registry = \WP_Block_Patterns_Registry::get_instance();
		$patterns = $registry->get_all_registered();

		$dsgo_patterns = array_filter(
			$patterns,
			function ( $pattern ) {
				return isset( $pattern['name'] ) && 0 === strpos( $pattern['name'], 'designsetgo/' );
			}
		);

		$this->assertNotEmpty( $dsgo_patterns, 'Should have DesignSetGo patterns' );

		// Header and footer patterns legitimately use blockTypes to bind to
		// core/template-part/header and core/template-part/footer respectively.
		$template_part_prefixes = array( 'designsetgo/header/', 'designsetgo/footer/' );

		foreach ( $dsgo_patterns as $pattern ) {
			$is_template_part_pattern = false;
			foreach ( $template_part_prefixes as $prefix ) {
				if ( 0 === strpos( $pattern['name'], $prefix ) ) {
					$is_template_part_pattern = true;
					break;
				}
			}

			if ( $is_template_part_pattern ) {
				continue;
			}

			$this->assertArrayNotHasKey(
				'blockTypes',
				$pattern,
				"Pattern '{$pattern['name']}' should not have blockTypes restriction"
			);
		}
	}

	/**
	 * Test that the postTypes filter hook works.
	 */
	public function test_post_types_map_filter() {
		add_filter(
			'designsetgo_pattern_post_types_map',
			function ( $map ) {
				$map['hero'] = array( 'page', 'post' );
				return $map;
			}
		);

		$this->loader->register_pattern_categories();
		$this->loader->register_patterns();

		$registry = \WP_Block_Patterns_Registry::get_instance();
		$patterns = $registry->get_all_registered();

		$hero_patterns = array_filter(
			$patterns,
			function ( $pattern ) {
				return isset( $pattern['name'] ) && 0 === strpos( $pattern['name'], 'designsetgo/hero/' );
			}
		);

		foreach ( $hero_patterns as $pattern ) {
			$this->assertArrayHasKey(
				'postTypes',
				$pattern,
				"Hero pattern should have postTypes after filter"
			);
			$this->assertSame(
				array( 'page', 'post' ),
				$pattern['postTypes'],
				"Hero pattern postTypes should match filter"
			);
		}
	}

	// -------------------------------------------------------------------------
	// Cache duration filter test
	// -------------------------------------------------------------------------

	/**
	 * Test that the designsetgo_pattern_cache_duration filter works.
	 */
	public function test_cache_duration_filter() {
		$custom_duration = HOUR_IN_SECONDS;

		// Enable caching for this test (normally disabled when WP_DEBUG is true).
		add_filter( 'designsetgo_pattern_cache_enabled', '__return_true' );

		add_filter(
			'designsetgo_pattern_cache_duration',
			function () use ( $custom_duration ) {
				return $custom_duration;
			}
		);

		// Trigger cache population.
		$this->call_private_method( 'get_category_patterns', array( 'hero' ) );

		// Verify transient was set (it exists).
		$cached = get_transient( Loader::CACHE_TRANSIENT_PREFIX . 'hero' );
		$this->assertIsArray( $cached, 'Cache should be set even with custom duration' );
	}

	// -------------------------------------------------------------------------
	// Transient size safety test
	// -------------------------------------------------------------------------

	/**
	 * Test that no single category transient exceeds 500 KB when serialized.
	 *
	 * This is a safety check to ensure category-level splitting keeps transients
	 * under safe size limits for MySQL max_allowed_packet and object cache plugins.
	 */
	public function test_category_transients_under_size_limit() {
		$max_bytes = 500 * 1024; // 500 KB.

		foreach ( Loader::ALLOWED_CATEGORIES as $category ) {
			$patterns = $this->call_private_method( 'get_category_patterns', array( $category ) );

			// Measure the compressed transient size (matches how data is stored).
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- measuring size only.
			$compressed = base64_encode( gzcompress( serialize( $patterns ) ) );
			$transient_data = array(
				'version'    => DESIGNSETGO_VERSION,
				'hash'       => 'test',
				'compressed' => $compressed,
			);
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- measuring size only.
			$serialized_size = strlen( serialize( $transient_data ) );

			$this->assertLessThan(
				$max_bytes,
				$serialized_size,
				"Category '$category' compressed transient ($serialized_size bytes) exceeds 500 KB limit"
			);
		}
	}
}

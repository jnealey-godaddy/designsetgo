<?php
/**
 * PHPUnit Bootstrap File for DesignSetGo
 *
 * Sets up the testing environment for WordPress plugin tests.
 * This file is loaded before all PHPUnit tests.
 *
 * @package DesignSetGo
 */

// Require Composer autoloader if available
if ( file_exists( dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php' ) ) {
	require_once dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';
}

// Define test environment constants
define( 'DESIGNSETGO_PLUGIN_DIR', dirname( dirname( __DIR__ ) ) );
define( 'DESIGNSETGO_TESTS_DIR', __DIR__ );

// Load PHPUnit Polyfills for WordPress test compatibility
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( dirname( __DIR__ ) ) . '/vendor/yoast/phpunit-polyfills' );
}

// Check if we're running in wp-env (WordPress test environment)
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

// If WP_TESTS_DIR is not set, check common locations
if ( ! $wp_tests_dir ) {
	// Try wp-env location first
	$wp_env_config = dirname( dirname( __DIR__ ) ) . '/.wp-env.json';
	if ( file_exists( $wp_env_config ) ) {
		// Running in wp-env, use its WordPress installation
		$wp_tests_dir = '/tmp/wordpress-tests-lib';
	} else {
		// Try default WordPress test suite location
		if ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
			$wp_tests_dir = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
		} elseif ( file_exists( '/tmp/wordpress-tests-lib/includes/functions.php' ) ) {
			$wp_tests_dir = '/tmp/wordpress-tests-lib';
		} elseif ( file_exists( '/vagrant/www/wordpress-develop/tests/phpunit/includes/functions.php' ) ) {
			$wp_tests_dir = '/vagrant/www/wordpress-develop/tests/phpunit';
		}
	}
}

// Give installation instructions if WordPress test suite not found
if ( ! $wp_tests_dir || ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test suite.\n\n";
	echo "To run PHPUnit tests, you have two options:\n\n";
	echo "Option 1 (Recommended) - Use wp-env:\n";
	echo "  1. Start wp-env: npm run wp-env:start\n";
	echo "  2. Run tests in wp-env: npm run wp-env run tests-cli --env-cwd=wp-content/plugins/designsetgo vendor/bin/phpunit\n\n";
	echo "Option 2 - Install WordPress test suite manually:\n";
	echo "  bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n\n";
	exit( 1 );
}

// Load WordPress test suite
require_once $wp_tests_dir . '/includes/functions.php';

/**
 * Manually load WooCommerce, when it is present.
 *
 * The WordPress test suite runs against its own freshly-installed database, in
 * which no plugins are active — activation state from the dev site does not carry
 * over. So a WooCommerce that `wp plugin list` reports as "active" is still absent
 * from the test run unless it is required here explicitly.
 *
 * Loaded at priority 5 so WooCommerce is defined before DesignSetGo, whose Woo
 * blocks gate on `class_exists( 'WooCommerce' )`.
 */
function _manually_load_woocommerce() {
	$woocommerce = WP_CONTENT_DIR . '/plugins/woocommerce/woocommerce.php';

	if ( file_exists( $woocommerce ) ) {
		require_once $woocommerce;
	}
}
tests_add_filter( 'muplugins_loaded', '_manually_load_woocommerce', 5 );

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __DIR__ ) ) . '/designsetgo.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WordPress testing environment
require $wp_tests_dir . '/includes/bootstrap.php';

// Create WooCommerce's tables. Product CRUD writes to wc_product_meta_lookup and
// friends, so loading the plugin alone is not enough — without this, every fixture
// fails on a missing table.
if ( class_exists( 'WC_Install' ) ) {
	WC_Install::install();

	// install() defers the roles/caps population to a shutdown hook in some flows;
	// the object cache must be reset so the freshly-created tables are visible.
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}

// Shared test helpers. Loaded after the WordPress bootstrap because they guard on
// ABSPATH, which is only defined once WordPress itself has loaded.
require_once __DIR__ . '/helpers/class-woo-product-factory.php';

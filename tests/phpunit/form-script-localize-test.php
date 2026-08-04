<?php
/**
 * Tests that the form-builder view script is localized wherever the form renders.
 *
 * `Form_Handler::localize_form_script()` used to bail early behind
 * `has_block( 'designsetgo/form-builder' )`, which only inspects the CURRENT
 * POST'S CONTENT. A form living in a template part (a newsletter signup in the
 * footer), a synced pattern, a block widget, or anything rendered through
 * `do_blocks()` never matched that guard. The block still rendered and
 * WordPress still enqueued its `viewScript`, so `view.js` ran with
 * `designsetgoForm` undefined and every submission died on a ReferenceError.
 *
 * The guard was removed rather than taught about template parts: scanning
 * template-part content would still miss synced patterns, widgets and
 * `do_blocks()`. No guard is needed because `wp_localize_script()` only
 * attaches data to a REGISTERED handle — the payload is printed only if that
 * script is actually enqueued, which happens at block render time.
 *
 * These tests pin both halves of that reasoning so the guard cannot be
 * reintroduced as an "optimization" without CI failing.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\Blocks\Form_Handler;

/**
 * Form Script Localization Test Case
 */
class Test_Form_Script_Localize extends WP_UnitTestCase {

	/**
	 * The handle WordPress generates for the block's viewScript.
	 *
	 * Derived from `designsetgo/form-builder` + `viewScript` in block.json,
	 * via WP core's generate_block_asset_handle().
	 */
	const HANDLE = 'designsetgo-form-builder-view-script';

	/**
	 * Form handler instance.
	 *
	 * @var Form_Handler
	 */
	private $handler;

	/**
	 * Whether this test registered a stand-in for the viewScript handle.
	 *
	 * @var bool
	 */
	private $registered_stub = false;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->handler = new Form_Handler();

		// The real handle is registered by register_block_type() from
		// build/blocks/form-builder/block.json on `init`. Stand one in when the
		// build artifacts are absent, so these tests exercise the localization
		// behaviour rather than whether someone ran `npm run build`.
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_register_script( self::HANDLE, 'https://example.org/view.js', array(), '1.0.0', true );
			$this->registered_stub = true;
		}

		$this->reset_localized_data();
		wp_dequeue_script( self::HANDLE );
	}

	/**
	 * Reset scripts state after each test.
	 */
	public function tear_down() {
		$this->reset_localized_data();
		wp_dequeue_script( self::HANDLE );

		if ( $this->registered_stub ) {
			wp_deregister_script( self::HANDLE );
			$this->registered_stub = false;
		}

		parent::tear_down();
	}

	/**
	 * Drop any previously localized payload from the handle.
	 *
	 * Calling wp_localize_script() APPENDS rather than replaces, and
	 * $wp_scripts outlives an individual test, so without this a later
	 * assertion could pass on stale data.
	 */
	private function reset_localized_data() {
		$scripts = wp_scripts();

		if ( isset( $scripts->registered[ self::HANDLE ] ) ) {
			unset( $scripts->registered[ self::HANDLE ]->extra['data'] );
		}
	}

	/**
	 * Make $page_id the queried post so has_block() evaluates its content.
	 *
	 * The has_block() helper falls back to get_post(), which reads
	 * $GLOBALS['post'] — so the global has to be set, or the guard would read
	 * false for the wrong reason and the regression test would pass trivially.
	 *
	 * @param int $page_id Page to query.
	 */
	private function go_to_page( $page_id ) {
		$this->go_to( get_permalink( $page_id ) );

		$GLOBALS['post'] = get_post( $page_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Emulating the main query for has_block().
		setup_postdata( $GLOBALS['post'] );
	}

	/**
	 * Create a page whose content does NOT contain the form block.
	 *
	 * @return int Page ID.
	 */
	private function create_page_without_form() {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:paragraph --><p>No form in this page body.</p><!-- /wp:paragraph -->',
			)
		);
	}

	/**
	 * THE REGRESSION TEST: a form in a template part, not in post content.
	 *
	 * Reproduces the reported failure exactly — a footer newsletter signup on a
	 * page whose own content has no form block. Reinstating the has_block()
	 * guard makes this fail, because the guard reads the post content only.
	 */
	public function test_localizes_when_queried_post_has_no_form_block() {
		$this->go_to_page( $this->create_page_without_form() );

		// Precondition: this is the exact state the old guard bailed on. If
		// this ever returns true the fixture is wrong and the test below
		// would pass for the wrong reason.
		$this->assertFalse(
			has_block( 'designsetgo/form-builder' ),
			'Fixture invalid: the queried post must NOT contain the form block.'
		);

		$this->handler->localize_form_script();

		$data = wp_scripts()->get_data( self::HANDLE, 'data' );

		$this->assertIsString(
			$data,
			'The form script must be localized even when the form lives outside post content (e.g. a footer template part).'
		);
		$this->assertStringContainsString(
			'designsetgoForm',
			$data,
			'view.js reads window.designsetgoForm; without it every submission fails with a ReferenceError.'
		);
		$this->assertStringContainsString(
			'ajaxNonce',
			$data,
			'The admin-ajax fallback nonce must reach the frontend.'
		);
		$this->assertStringContainsString(
			'dsgoIntegrations',
			$data,
			'Turnstile settings ride the same handle and were lost to the same guard.'
		);
	}

	/**
	 * Positive control: the in-content case must keep working.
	 *
	 * Guards against a "fix" that merely inverts the bug.
	 */
	public function test_localizes_when_queried_post_does_contain_the_form_block() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:designsetgo/form-builder {"formId":"abc123"} --><div class="wp-block-designsetgo-form-builder"></div><!-- /wp:designsetgo/form-builder -->',
			)
		);

		$this->go_to_page( $page_id );

		$this->assertTrue(
			has_block( 'designsetgo/form-builder' ),
			'Fixture invalid: the queried post SHOULD contain the form block.'
		);

		$this->handler->localize_form_script();

		$data = wp_scripts()->get_data( self::HANDLE, 'data' );

		$this->assertIsString( $data, 'The form script must still be localized for in-content forms.' );
		$this->assertStringContainsString( 'designsetgoForm', $data );
	}

	/**
	 * Localizing must not enqueue anything by itself.
	 *
	 * This is what makes the unconditional localization free: data attached to
	 * a registered-but-not-enqueued handle is never printed. WordPress enqueues
	 * the viewScript at block render time, so a page with no form emits nothing
	 * — no payload, no nonce. A "fix" that unconditionally enqueued the script
	 * instead would leak both onto every page, and fails here.
	 */
	public function test_localizing_does_not_enqueue_the_view_script() {
		$this->go_to_page( $this->create_page_without_form() );

		$this->handler->localize_form_script();

		$this->assertFalse(
			wp_script_is( self::HANDLE, 'enqueued' ),
			'Localizing must not enqueue the view script; otherwise the nonce payload prints on every page.'
		);
	}
}

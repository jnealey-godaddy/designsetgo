<?php
/**
 * Draft Mode honours its Blocks & Extensions toggle.
 *
 * Switching the draft-mode extension off must turn Draft Mode off, not only
 * hide a checkbox: Draft_Mode::is_enabled() drives the editor panel (via the
 * status endpoint), the create endpoint, and the page-list actions.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Draft_Mode;
use DesignSetGo\Admin\Draft_Mode_REST;
use DesignSetGo\Admin\Settings;

/**
 * Draft Mode extension toggle test class.
 */
class Test_Draft_Mode_Extension_Toggle extends WP_UnitTestCase {

	/**
	 * Draft Mode instance.
	 *
	 * @var Draft_Mode
	 */
	private $draft_mode;

	/**
	 * Published page ID.
	 *
	 * @var int
	 */
	private $page_id;

	/**
	 * Set up routes, an editor and a published page.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();

		$this->draft_mode = new Draft_Mode();
		new Draft_Mode_REST( $this->draft_mode );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		rest_get_server()->override_by_default = true;
		do_action( 'rest_api_init' );
	}

	/**
	 * Reset stored settings.
	 */
	public function tear_down(): void {
		delete_option( Settings::OPTION_NAME );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	/**
	 * Switch the draft-mode extension off.
	 */
	private function disable_extension(): void {
		update_option( Settings::OPTION_NAME, array( 'disabled_extensions' => array( 'draft-mode' ) ) );
		Settings::invalidate_cache();
	}

	/**
	 * Dispatch a REST request with a valid nonce.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route.
	 * @param array  $params Request params.
	 * @return WP_REST_Response
	 */
	private function dispatch( string $method, string $route, array $params = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Enabled by default.
	 */
	public function test_enabled_by_default(): void {
		$this->assertTrue( $this->draft_mode->is_enabled() );
	}

	/**
	 * The extension toggle switches Draft Mode off.
	 */
	public function test_disabled_extension_disables_draft_mode(): void {
		$this->disable_extension();

		$this->assertFalse( $this->draft_mode->is_enabled() );
	}

	/**
	 * Other extensions being off does not affect Draft Mode.
	 */
	public function test_other_disabled_extensions_leave_draft_mode_on(): void {
		update_option( Settings::OPTION_NAME, array( 'disabled_extensions' => array( 'custom-css' ) ) );
		Settings::invalidate_cache();

		$this->assertTrue( $this->draft_mode->is_enabled() );
	}

	/**
	 * The status endpoint the editor panel reads reports it off.
	 */
	public function test_status_endpoint_reports_disabled(): void {
		$this->disable_extension();

		$response = $this->dispatch( 'GET', '/designsetgo/v1/draft-mode/status/' . $this->page_id );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $data['settings']['enabled'] );
		$this->assertFalse( $data['can_create'] );
	}

	/**
	 * A draft copy made before the switch still reports itself as one, so the
	 * editor's publish intercept keeps merging it instead of publishing it as
	 * a second page.
	 */
	public function test_existing_draft_still_reported_when_disabled(): void {
		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->disable_extension();

		$data = $this->dispatch( 'GET', '/designsetgo/v1/draft-mode/status/' . $draft_id )->get_data();

		$this->assertTrue( $data['is_draft'] );
		$this->assertSame( $this->page_id, $data['original_id'] );
	}

	/**
	 * An existing draft copy can still be merged into its original.
	 */
	public function test_existing_draft_still_merges_when_disabled(): void {
		$draft_id = $this->draft_mode->create_draft( $this->page_id, array( 'title' => 'Staged title' ) );
		$this->disable_extension();

		$response = $this->dispatch( 'POST', '/designsetgo/v1/draft-mode/' . $draft_id . '/publish', array( 'id' => $draft_id ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Staged title', get_post( $this->page_id )->post_title );
	}

	/**
	 * Drafts can't be created while it is off.
	 */
	public function test_create_endpoint_refuses_when_disabled(): void {
		$this->disable_extension();

		$response = $this->dispatch( 'POST', '/designsetgo/v1/draft-mode/create', array( 'post_id' => $this->page_id ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'draft_mode_disabled', $response->get_data()['code'] );
	}
}

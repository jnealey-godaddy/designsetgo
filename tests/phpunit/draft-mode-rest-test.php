<?php
/**
 * Test Draft Mode REST API
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Draft_Mode;
use DesignSetGo\Admin\Draft_Mode_REST;

/**
 * Tests for Draft_Mode_REST class.
 */
class Test_Draft_Mode_REST extends WP_UnitTestCase {

	/**
	 * Draft Mode instance.
	 *
	 * @var Draft_Mode
	 */
	private $draft_mode;

	/**
	 * REST API instance.
	 *
	 * @var Draft_Mode_REST
	 */
	private $rest_api;

	/**
	 * Test admin user ID.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Test editor user ID.
	 *
	 * @var int
	 */
	private $editor_id;

	/**
	 * Test subscriber user ID (no page permissions).
	 *
	 * @var int
	 */
	private $subscriber_id;

	/**
	 * Published page ID for testing.
	 *
	 * @var int
	 */
	private $page_id;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'designsetgo/v1';

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->draft_mode = new Draft_Mode();
		$this->rest_api = new Draft_Mode_REST( $this->draft_mode );

		// Create test users with different capabilities.
		$this->admin_id = $this->factory->user->create( array(
			'role' => 'administrator',
		) );

		$this->editor_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );

		$this->subscriber_id = $this->factory->user->create( array(
			'role' => 'subscriber',
		) );

		// Create a published test page.
		$this->page_id = $this->factory->post->create( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Test Page',
			'post_content' => 'Test content',
		) );

		// Register REST routes.
		rest_get_server()->override_by_default = true;
		do_action( 'rest_api_init' );
	}

	/**
	 * Create a REST request pre-populated with a valid nonce for the current user.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route path.
	 * @return WP_REST_Request
	 */
	private function authed_request( string $method, string $route ): WP_REST_Request {
		$req = new WP_REST_Request( $method, $route );
		$req->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		return $req;
	}

	/**
	 * Test REST routes are registered.
	 */
	public function test_routes_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/' . $this->namespace . '/draft-mode/create', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/draft-mode/(?P<id>\d+)/publish', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/draft-mode/(?P<id>\d+)', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/draft-mode/status/(?P<post_id>\d+)', $routes );
	}

	/**
	 * Test create draft endpoint with proper permissions.
	 */
	public function test_create_draft_endpoint_success() {
		wp_set_current_user( $this->editor_id );

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'draft_id', $data );
		$this->assertArrayHasKey( 'edit_url', $data );
		$this->assertGreaterThan( 0, $data['draft_id'] );
	}

	/**
	 * Test create draft endpoint with content overrides.
	 */
	public function test_create_draft_endpoint_with_overrides() {
		wp_set_current_user( $this->editor_id );

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', 'Draft content' );
		$request->set_param( 'title', 'Draft title' );
		$request->set_param( 'excerpt', 'Draft excerpt' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		$this->assertEquals( 'Draft title', $draft->post_title );
		$this->assertEquals( 'Draft content', $draft->post_content );
		$this->assertEquals( 'Draft excerpt', $draft->post_excerpt );
	}

	/**
	 * Test create draft endpoint permission check - subscriber should fail.
	 */
	public function test_create_draft_endpoint_permission_denied() {
		wp_set_current_user( $this->subscriber_id );

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test create draft endpoint requires post_id parameter.
	 */
	public function test_create_draft_endpoint_missing_post_id() {
		wp_set_current_user( $this->editor_id );

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test publish draft endpoint.
	 */
	public function test_publish_draft_endpoint_success() {
		wp_set_current_user( $this->editor_id );

		// Create a draft first.
		$draft_id = $this->draft_mode->create_draft( $this->page_id, array(
			'title'   => 'Updated Title',
			'content' => 'Updated content',
		) );

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/' . $draft_id . '/publish' );
		$request->set_param( 'id', $draft_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( $this->page_id, $data['original_id'] );

		// Verify original was updated.
		$updated_page = get_post( $this->page_id );
		$this->assertEquals( 'Updated Title', $updated_page->post_title );
	}

	/**
	 * Test publish draft endpoint permission check.
	 */
	public function test_publish_draft_endpoint_permission_denied() {
		wp_set_current_user( $this->editor_id );
		$draft_id = $this->draft_mode->create_draft( $this->page_id );

		// Switch to subscriber (no permissions).
		wp_set_current_user( $this->subscriber_id );

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/' . $draft_id . '/publish' );
		$request->set_param( 'id', $draft_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test discard draft endpoint.
	 */
	public function test_discard_draft_endpoint_success() {
		wp_set_current_user( $this->editor_id );

		// Create a draft first.
		$draft_id = $this->draft_mode->create_draft( $this->page_id );

		$request = $this->authed_request( 'DELETE', '/' . $this->namespace . '/draft-mode/' . $draft_id );
		$request->set_param( 'id', $draft_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( $this->page_id, $data['original_id'] );

		// Verify draft was deleted.
		$this->assertNull( get_post( $draft_id ) );
	}

	/**
	 * Test get status endpoint for published page without draft.
	 */
	public function test_get_status_endpoint_no_draft() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/draft-mode/status/' . $this->page_id );
		$request->set_param( 'post_id', $this->page_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['exists'] );
		$this->assertFalse( $data['is_draft'] );
		$this->assertFalse( $data['has_draft'] );
		$this->assertNull( $data['draft_id'] );
	}

	/**
	 * Test get status endpoint for published page with draft.
	 */
	public function test_get_status_endpoint_has_draft() {
		wp_set_current_user( $this->editor_id );

		// Create a draft.
		$draft_id = $this->draft_mode->create_draft( $this->page_id );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/draft-mode/status/' . $this->page_id );
		$request->set_param( 'post_id', $this->page_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['exists'] );
		$this->assertFalse( $data['is_draft'] );
		$this->assertTrue( $data['has_draft'] );
		$this->assertEquals( $draft_id, $data['draft_id'] );
		$this->assertArrayHasKey( 'draft_edit_url', $data );
	}

	/**
	 * Test get status endpoint for a draft post.
	 */
	public function test_get_status_endpoint_is_draft() {
		wp_set_current_user( $this->editor_id );

		// Create a draft.
		$draft_id = $this->draft_mode->create_draft( $this->page_id );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/draft-mode/status/' . $draft_id );
		$request->set_param( 'post_id', $draft_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['exists'] );
		$this->assertTrue( $data['is_draft'] );
		$this->assertFalse( $data['has_draft'] );
		$this->assertEquals( $this->page_id, $data['original_id'] );
		$this->assertArrayHasKey( 'original_edit_url', $data );
		$this->assertArrayHasKey( 'original_view_url', $data );
	}

	/**
	 * Test get status endpoint read permission.
	 */
	public function test_get_status_endpoint_read_permission() {
		wp_set_current_user( $this->subscriber_id );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/draft-mode/status/' . $this->page_id );
		$request->set_param( 'post_id', $this->page_id );

		$response = rest_get_server()->dispatch( $request );

		// Subscriber cannot edit pages, so should be denied.
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test capability check method for publish_pages.
	 */
	public function test_check_permission_publish_pages() {
		// Editor has publish_pages capability.
		wp_set_current_user( $this->editor_id );
		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$this->assertTrue( $this->rest_api->check_permission( $request ) );

		// Subscriber does not have publish_pages capability — fails before nonce check.
		wp_set_current_user( $this->subscriber_id );
		$result = $this->rest_api->check_permission( $request );
		$this->assertWPError( $result );
		$this->assertEquals( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test nonce validation in check_permission.
	 */
	public function test_check_permission_rejects_invalid_nonce() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_header( 'X-WP-Nonce', 'invalid_nonce_value' );

		$result = $this->rest_api->check_permission( $request );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_nonce', $result->get_error_code() );
	}

	/**
	 * Test capability check method for edit_pages.
	 */
	public function test_check_read_permission_edit_pages() {
		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/draft-mode/status/' . $this->page_id );

		// Editor has edit_pages capability.
		wp_set_current_user( $this->editor_id );
		$this->assertTrue( $this->rest_api->check_read_permission( $request ) );

		// Subscriber does not have edit_pages capability.
		wp_set_current_user( $this->subscriber_id );
		$this->assertFalse( $this->rest_api->check_read_permission( $request ) );
	}

	/**
	 * Test that block content with CSS display property is preserved.
	 *
	 * Regression test for wp_kses_post stripping display:flex from block content.
	 *
	 * @ticket DSGO-BLOCK-VALIDATION
	 */
	public function test_create_draft_preserves_display_flex_in_block_content() {
		wp_set_current_user( $this->editor_id );

		// Block content with display:flex that wp_kses_post would strip.
		$block_content = '<!-- wp:designsetgo/row -->
<div class="wp-block-designsetgo-row dsgo-flex"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:wrap;gap:20px"><!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $block_content );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Verify display:flex is preserved.
		$this->assertStringContainsString( 'display:flex', $draft->post_content, 'display:flex should be preserved in block content' );
	}

	/**
	 * Test that block content with CSS display:grid is preserved.
	 *
	 * Regression test for wp_kses_post stripping display:grid from block content.
	 *
	 * @ticket DSGO-BLOCK-VALIDATION
	 */
	public function test_create_draft_preserves_display_grid_in_block_content() {
		wp_set_current_user( $this->editor_id );

		$block_content = '<!-- wp:designsetgo/grid -->
<div class="wp-block-designsetgo-grid dsgo-grid"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(3, 1fr);gap:20px"><!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/grid -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $block_content );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Verify display:grid is preserved.
		$this->assertStringContainsString( 'display:grid', $draft->post_content, 'display:grid should be preserved in block content' );
	}

	/**
	 * Test that block content with SVG elements is preserved.
	 *
	 * Regression test for wp_kses_post stripping SVG from shape dividers.
	 *
	 * @ticket DSGO-BLOCK-VALIDATION
	 */
	public function test_create_draft_preserves_svg_in_block_content() {
		wp_set_current_user( $this->editor_id );

		$block_content = '<!-- wp:designsetgo/section {"shapeDividerBottom":"wave"} -->
<div class="wp-block-designsetgo-section dsgo-stack"><div class="dsgo-shape-divider dsgo-shape-divider--bottom"><svg viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M0,0 C300,120 900,0 1200,80 L1200,120 L0,120 Z"></path></svg></div></div>
<!-- /wp:designsetgo/section -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $block_content );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Verify SVG content is preserved.
		$this->assertStringContainsString( '<svg', $draft->post_content, 'SVG element should be preserved in block content' );
		$this->assertStringContainsString( '<path', $draft->post_content, 'SVG path element should be preserved in block content' );
	}

	/**
	 * Test that block content with inline-flex display is preserved.
	 *
	 * Regression test for wp_kses_post stripping display:inline-flex from buttons.
	 *
	 * @ticket DSGO-BLOCK-VALIDATION
	 */
	public function test_create_draft_preserves_inline_flex_in_block_content() {
		wp_set_current_user( $this->editor_id );

		$block_content = '<!-- wp:designsetgo/icon-button {"text":"Click me"} -->
<a class="wp-block-designsetgo-icon-button dsgo-icon-button" style="display:inline-flex;align-items:center;justify-content:center;gap:8px" href="#">Click me</a>
<!-- /wp:designsetgo/icon-button -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $block_content );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Verify display:inline-flex is preserved.
		$this->assertStringContainsString( 'display:inline-flex', $draft->post_content, 'display:inline-flex should be preserved in block content' );
	}

	/**
	 * Test that block content with tabindex attribute is preserved.
	 *
	 * Regression test for wp_kses_post stripping tabindex from interactive elements.
	 *
	 * @ticket DSGO-BLOCK-VALIDATION
	 */
	public function test_create_draft_preserves_tabindex_in_block_content() {
		wp_set_current_user( $this->editor_id );

		$block_content = '<!-- wp:designsetgo/image-accordion-item -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $block_content );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Verify tabindex is preserved.
		$this->assertStringContainsString( 'tabindex="0"', $draft->post_content, 'tabindex attribute should be preserved in block content' );
	}

	/**
	 * Test that script tags are filtered from block content.
	 *
	 * Security test to verify XSS prevention.
	 *
	 * @ticket DSGO-SECURITY
	 */
	public function test_create_draft_filters_script_tags() {
		wp_set_current_user( $this->editor_id );

		$malicious_content = '<!-- wp:paragraph -->
<p>Hello</p>
<!-- /wp:paragraph -->
<script>alert("XSS")</script>
<!-- wp:paragraph -->
<p>World</p>
<!-- /wp:paragraph -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $malicious_content );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Verify script tag is removed.
		$this->assertStringNotContainsString( '<script', $draft->post_content, 'Script tags should be filtered from content' );
		$this->assertStringNotContainsString( 'alert(', $draft->post_content, 'Script content should be filtered' );
		// But legitimate content should be preserved.
		$this->assertStringContainsString( '<p>Hello</p>', $draft->post_content, 'Legitimate content should be preserved' );
		$this->assertStringContainsString( '<p>World</p>', $draft->post_content, 'Legitimate content should be preserved' );
	}

	/**
	 * Test that onclick event handlers are filtered from block content.
	 *
	 * Security test to verify XSS prevention via event handlers.
	 *
	 * @ticket DSGO-SECURITY
	 */
	public function test_create_draft_filters_onclick_handlers() {
		wp_set_current_user( $this->editor_id );

		$malicious_content = '<!-- wp:paragraph -->
<p onclick="alert(\'XSS\')">Click me</p>
<!-- /wp:paragraph -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $malicious_content );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Verify onclick is removed.
		$this->assertStringNotContainsString( 'onclick', $draft->post_content, 'onclick handler should be filtered from content' );
		// But the paragraph should be preserved.
		$this->assertStringContainsString( '<p', $draft->post_content, 'Paragraph tag should be preserved' );
		$this->assertStringContainsString( 'Click me', $draft->post_content, 'Text content should be preserved' );
	}

	/**
	 * Test that onerror event handlers on images are filtered.
	 *
	 * Security test to verify XSS prevention via img onerror.
	 *
	 * @ticket DSGO-SECURITY
	 */
	public function test_create_draft_filters_onerror_handlers() {
		wp_set_current_user( $this->editor_id );

		$malicious_content = '<!-- wp:image -->
<figure class="wp-block-image"><img src="invalid.jpg" onerror="alert(\'XSS\')" alt="Test" /></figure>
<!-- /wp:image -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $malicious_content );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Verify onerror is removed.
		$this->assertStringNotContainsString( 'onerror', $draft->post_content, 'onerror handler should be filtered from content' );
		// But the image should be preserved.
		$this->assertStringContainsString( '<img', $draft->post_content, 'Image tag should be preserved' );
		$this->assertStringContainsString( 'src="invalid.jpg"', $draft->post_content, 'Image src should be preserved' );
	}

	/**
	 * Test that javascript: URLs in href are filtered.
	 *
	 * Security test to verify XSS prevention via javascript: protocol.
	 *
	 * @ticket DSGO-SECURITY
	 */
	public function test_create_draft_filters_javascript_urls() {
		wp_set_current_user( $this->editor_id );

		$malicious_content = '<!-- wp:paragraph -->
<p><a href="javascript:alert(\'XSS\')">Click me</a></p>
<!-- /wp:paragraph -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $malicious_content );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Verify javascript: URL is removed or sanitized.
		$this->assertStringNotContainsString( 'javascript:', $draft->post_content, 'javascript: URLs should be filtered from content' );
		// But the anchor and text should be preserved.
		$this->assertStringContainsString( 'Click me', $draft->post_content, 'Link text should be preserved' );
	}

	/**
	 * Test that onload handlers on SVG are filtered while preserving SVG.
	 *
	 * Security test to verify XSS prevention while allowing legitimate SVG.
	 *
	 * @ticket DSGO-SECURITY
	 */
	public function test_create_draft_filters_svg_onload_handlers() {
		wp_set_current_user( $this->editor_id );

		$malicious_content = '<!-- wp:designsetgo/section -->
<div class="wp-block-designsetgo-section"><svg onload="alert(\'XSS\')" viewBox="0 0 100 100"><path d="M0,0 L100,100"></path></svg></div>
<!-- /wp:designsetgo/section -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $malicious_content );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Verify onload is removed.
		$this->assertStringNotContainsString( 'onload', $draft->post_content, 'onload handler should be filtered from SVG' );
		// But legitimate SVG content should be preserved.
		$this->assertStringContainsString( '<svg', $draft->post_content, 'SVG element should be preserved' );
		$this->assertStringContainsString( '<path', $draft->post_content, 'SVG path should be preserved' );
		$this->assertStringContainsString( 'viewBox', $draft->post_content, 'SVG viewBox should be preserved' );
	}

	/**
	 * Test that legitimate block content with flex AND sanitization works together.
	 *
	 * Integration test: ensures security sanitization doesn't break legitimate content.
	 *
	 * @ticket DSGO-SECURITY
	 */
	public function test_create_draft_preserves_flex_while_filtering_xss() {
		wp_set_current_user( $this->editor_id );

		// Content with both legitimate flex styling and XSS attempt.
		$mixed_content = '<!-- wp:designsetgo/row -->
<div class="wp-block-designsetgo-row dsgo-flex" onclick="alert(\'XSS\')"><div class="dsgo-flex__inner" style="display:flex;justify-content:center"><!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $mixed_content );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Security: onclick should be removed.
		$this->assertStringNotContainsString( 'onclick', $draft->post_content, 'onclick should be filtered' );
		// Functionality: display:flex should be preserved.
		$this->assertStringContainsString( 'display:flex', $draft->post_content, 'display:flex should be preserved' );
		$this->assertStringContainsString( 'justify-content:center', $draft->post_content, 'Other flex properties should be preserved' );
	}

	/**
	 * Test that excerpt is properly sanitized.
	 *
	 * Security test to verify excerpt uses sanitize_textarea_field.
	 *
	 * @ticket DSGO-SECURITY
	 */
	public function test_create_draft_sanitizes_excerpt() {
		wp_set_current_user( $this->editor_id );

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'excerpt', '<script>alert("XSS")</script>Safe text with line
breaks' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$draft = get_post( $data['draft_id'] );

		// Verify script tag is stripped.
		$this->assertStringNotContainsString( '<script', $draft->post_excerpt, 'Script tags should be stripped from excerpt' );
		// Verify safe text is preserved (sanitize_textarea_field preserves line breaks).
		$this->assertStringContainsString( 'Safe text', $draft->post_excerpt, 'Safe text should be preserved' );
	}

	/**
	 * Test sanitize_block_content method directly.
	 *
	 * Unit test for the static sanitization method.
	 *
	 * @ticket DSGO-SECURITY
	 */
	public function test_sanitize_block_content_method() {
		// Test script removal.
		$result = Draft_Mode_REST::sanitize_block_content( '<p>Hello</p><script>alert("XSS")</script>' );
		$this->assertStringNotContainsString( '<script', $result );
		$this->assertStringContainsString( '<p>Hello</p>', $result );

		// Test event handler removal.
		$result = Draft_Mode_REST::sanitize_block_content( '<div onclick="evil()">Test</div>' );
		$this->assertStringNotContainsString( 'onclick', $result );
		$this->assertStringContainsString( '<div', $result );
		$this->assertStringContainsString( 'Test', $result );

		// Test SVG preservation.
		$result = Draft_Mode_REST::sanitize_block_content( '<svg viewBox="0 0 100 100"><path d="M0,0"></path></svg>' );
		$this->assertStringContainsString( '<svg', $result );
		$this->assertStringContainsString( '<path', $result );
		$this->assertStringContainsString( 'viewBox', $result );

		// Test style with flex preservation.
		$result = Draft_Mode_REST::sanitize_block_content( '<div style="display:flex;gap:10px">Test</div>' );
		$this->assertStringContainsString( 'display:flex', $result );

		// Test tabindex preservation.
		$result = Draft_Mode_REST::sanitize_block_content( '<div tabindex="0" role="button">Test</div>' );
		$this->assertStringContainsString( 'tabindex="0"', $result );
		$this->assertStringContainsString( 'role="button"', $result );

		// Test non-string input.
		$result = Draft_Mode_REST::sanitize_block_content( null );
		$this->assertEquals( '', $result );

		$result = Draft_Mode_REST::sanitize_block_content( 123 );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test sanitize_block_content preserves block comments with nested JSON.
	 *
	 * Block comments often contain deeply nested JSON for style attributes.
	 * The preservation regex must handle nested braces correctly or wp_kses
	 * strips the comments, corrupting block content into a classic block.
	 *
	 * @ticket DSGO-DRAFT-NESTED-JSON
	 */
	public function test_sanitize_block_content_nested_json_block_comments() {
		// Block comment with deeply nested JSON (style > spacing > padding).
		$content = '<!-- wp:designsetgo/section {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","right":"var:preset|spacing|30"}}}} -->'
			. "\n" . '<div class="wp-block-designsetgo-section alignfull dsgo-stack">Test</div>'
			. "\n" . '<!-- /wp:designsetgo/section -->';

		$result = Draft_Mode_REST::sanitize_block_content( $content );

		$this->assertStringContainsString(
			'<!-- wp:designsetgo/section {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","right":"var:preset|spacing|30"}}}} -->',
			$result,
			'Block comment with nested JSON braces must be preserved'
		);
		$this->assertStringContainsString( '<!-- /wp:designsetgo/section -->', $result );

		// Block comment with 4+ levels of nesting.
		$deep_content = '<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|20"}}}} -->'
			. "\n" . '<p>Hello</p>'
			. "\n" . '<!-- /wp:paragraph -->';

		$deep_result = Draft_Mode_REST::sanitize_block_content( $deep_content );

		$this->assertStringContainsString(
			'<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|20"}}}} -->',
			$deep_result,
			'Block comment with 4+ levels of nested JSON must be preserved'
		);
		$this->assertStringContainsString(
			'<!-- /wp:paragraph -->',
			$deep_result,
			'Closing block comment for deeply nested paragraph must be preserved'
		);
		$this->assertStringContainsString(
			'<p>Hello</p>',
			$deep_result,
			'Inner HTML content for deeply nested paragraph must be preserved'
		);
	}
}

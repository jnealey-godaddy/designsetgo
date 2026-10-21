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
		$this->deny_unfiltered_html();

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
		$this->deny_unfiltered_html();

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
		$this->deny_unfiltered_html();

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
		$this->deny_unfiltered_html();

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
		$this->deny_unfiltered_html();

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
		// The wrapper block markup survives (core kses decides about the SVG itself).
		$this->assertStringContainsString( 'wp-block-designsetgo-section', $draft->post_content );
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
		$this->deny_unfiltered_html();

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
		// The block wrapper survives; which style properties do is core's call.
		$this->assertStringContainsString( 'dsgo-flex__inner', $draft->post_content );
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
	 * Make the current user lack unfiltered_html, as on multisite or with
	 * DISALLOW_UNFILTERED_HTML, and re-run kses setup so core's post kses applies.
	 */
	private function deny_unfiltered_html(): void {
		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap ) {
				return 'unfiltered_html' === $cap ? array( 'do_not_allow' ) : $caps;
			},
			10,
			2
		);
		kses_init();
	}

	/**
	 * Markup the blocks' save() emits must survive create-draft untouched
	 * (aria-modal, aria-valuenow, aria-roledescription, aria-atomic, ...).
	 * A private allowlist used to strip these and leave invalid blocks.
	 */
	public function test_create_draft_preserves_block_aria_attributes() {
		wp_set_current_user( $this->admin_id );

		$content = '<!-- wp:designsetgo/modal -->
<div class="wp-block-designsetgo-modal"><div class="dsgo-modal" role="dialog" aria-modal="true" aria-labelledby="m1" tabindex="-1">Hi</div></div>
<!-- /wp:designsetgo/modal -->
<!-- wp:designsetgo/progress-bar -->
<div class="wp-block-designsetgo-progress-bar" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100"><span class="dsgo-sr" aria-atomic="true"></span></div>
<!-- /wp:designsetgo/progress-bar -->
<!-- wp:designsetgo/slider -->
<div class="wp-block-designsetgo-slider" role="region" aria-roledescription="carousel"><input type="text" aria-hidden="true" tabindex="-1" name="hp" /></div>
<!-- /wp:designsetgo/slider -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $content );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$draft = get_post( $response->get_data()['draft_id'] );
		$this->assertSame( $content, $draft->post_content, 'Draft content must be stored exactly as sent for an unfiltered_html user' );
	}

	/**
	 * Without unfiltered_html the draft gets core's post kses, exactly as a normal save.
	 */
	public function test_create_draft_applies_core_kses_without_unfiltered_html() {
		wp_set_current_user( $this->editor_id );
		$this->deny_unfiltered_html();

		$content = '<!-- wp:paragraph --><p onmouseover="x()">Hi<script>evil()</script></p><!-- /wp:paragraph -->';

		$request = $this->authed_request( 'POST', '/' . $this->namespace . '/draft-mode/create' );
		$request->set_param( 'post_id', $this->page_id );
		$request->set_param( 'content', $content );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$draft = get_post( $response->get_data()['draft_id'] );
		$this->assertStringNotContainsString( 'onmouseover', $draft->post_content );
		$this->assertStringNotContainsString( '<script', $draft->post_content );
		$this->assertStringContainsString( 'wp:paragraph', $draft->post_content );
	}
}

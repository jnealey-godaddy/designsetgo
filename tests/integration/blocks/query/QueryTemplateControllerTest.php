<?php
/**
 * Integration tests for the Query Template Controller REST endpoints.
 *
 * Covers: export success, export 404, export 403, import wrong schema version,
 * import wrong blockName, import generates fresh queryId.
 *
 * @package DesignSetGo\Tests\Integration\Blocks\Query
 * @since   2.4.0
 */

namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests for GET + POST /designsetgo/v1/query/template.
 */
class QueryTemplateControllerTest extends WP_UnitTestCase {

	/**
	 * Minimal block markup used across export tests.
	 *
	 * @var string
	 */
	private const QUERY_BLOCK = '<!-- wp:designsetgo/query {"queryId":"abc","perPage":5} --><ul></ul><!-- /wp:designsetgo/query -->';

	// -------------------------------------------------------------------------
	// Export tests
	// -------------------------------------------------------------------------

	/**
	 * A valid export request returns 200 with the full blob.
	 */
	public function test_export_returns_json_for_existing_block() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create(
			array( 'post_content' => self::QUERY_BLOCK )
		);

		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/query/template' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_query_params( array( 'post_id' => $post_id, 'query_id' => 'abc' ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 1, $data['schemaVersion'] );
		$this->assertSame( 'designsetgo/query', $data['blockName'] );
		$this->assertSame( 5, $data['attributes']['perPage'] );
		$this->assertArrayHasKey( 'exportedAt', $data );
		$this->assertArrayHasKey( 'innerBlocks', $data );
	}

	/**
	 * Export returns 404 when no matching queryId exists in the post.
	 */
	public function test_export_returns_404_when_block_missing() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create( array( 'post_content' => '' ) );

		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/query/template' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_query_params( array( 'post_id' => $post_id, 'query_id' => 'missing' ) );
		$response = rest_do_request( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Export returns 403 when the user cannot edit the requested post.
	 */
	public function test_export_denies_without_edit_post_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$post_id = self::factory()->post->create(
			array( 'post_content' => self::QUERY_BLOCK )
		);

		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/query/template' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_query_params( array( 'post_id' => $post_id, 'query_id' => 'abc' ) );
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Export finds a query block nested inside another block.
	 */
	public function test_export_finds_nested_query_block() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$content = '<!-- wp:group --><div class="wp-block-group">'
				. '<!-- wp:designsetgo/query {"queryId":"nested","perPage":3} --><ul></ul><!-- /wp:designsetgo/query -->'
				. '</div><!-- /wp:group -->';

		$post_id = self::factory()->post->create( array( 'post_content' => $content ) );

		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/query/template' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_query_params( array( 'post_id' => $post_id, 'query_id' => 'nested' ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 3, $response->get_data()['attributes']['perPage'] );
	}

	// -------------------------------------------------------------------------
	// Import tests
	// -------------------------------------------------------------------------

	/**
	 * Import returns 400 when schemaVersion is not 1.
	 */
	public function test_import_rejects_wrong_schema_version() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/template' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'schemaVersion' => 2,
				'blockName'     => 'designsetgo/query',
				'attributes'    => array(),
				'innerBlocks'   => '',
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Import returns 400 when blockName is not designsetgo/query.
	 */
	public function test_import_rejects_wrong_block_name() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/template' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'schemaVersion' => 1,
				'blockName'     => 'core/paragraph',
				'attributes'    => array(),
				'innerBlocks'   => '',
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Import generates a fresh queryId and never reuses the exported one.
	 */
	public function test_import_generates_fresh_query_id() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/template' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'schemaVersion' => 1,
				'blockName'     => 'designsetgo/query',
				'attributes'    => array( 'queryId' => 'old-id', 'perPage' => 5 ),
				'innerBlocks'   => '',
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'blockMarkup', $data );
		$this->assertStringContainsString( '<!-- wp:designsetgo/query', $data['blockMarkup'] );
		$this->assertStringNotContainsString( '"queryId":"old-id"', $data['blockMarkup'] );
	}

	/**
	 * Import returns valid block markup that contains the closing comment.
	 */
	public function test_import_returns_valid_block_markup() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/template' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'schemaVersion' => 1,
				'blockName'     => 'designsetgo/query',
				'attributes'    => array( 'perPage' => 10, 'source' => 'posts' ),
				'innerBlocks'   => '<!-- wp:paragraph --><p>Item</p><!-- /wp:paragraph -->',
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$markup = $response->get_data()['blockMarkup'];
		$this->assertStringContainsString( '<!-- wp:designsetgo/query', $markup );
		$this->assertStringContainsString( '<!-- /wp:designsetgo/query -->', $markup );
		$this->assertStringContainsString( 'Item', $markup );
	}

	/**
	 * Attribute values containing "-->" are escaped so they cannot prematurely
	 * close the block comment — round-trip via parse_blocks() must yield the
	 * original value byte-for-byte.
	 */
	public function test_import_escapes_block_comment_terminator_in_attrs() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/template' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'schemaVersion' => 1,
				'blockName'     => 'designsetgo/query',
				'attributes'    => array( 'search' => 'hack --> </p>' ),
				'innerBlocks'   => '',
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$markup = $response->get_data()['blockMarkup'];

		// The raw "-->" must not appear inside the opening comment tag.
		$open_comment_end = strpos( $markup, ' -->' );
		$this->assertNotFalse( $open_comment_end, 'Opening block comment must be present.' );
		$opening_comment = substr( $markup, 0, $open_comment_end );
		$this->assertStringNotContainsString( '-->', $opening_comment );

		// Round-trip: parse_blocks() must recover the original value.
		$parsed = parse_blocks( $markup );
		$this->assertSame( 'hack --> </p>', $parsed[0]['attrs']['search'] );
	}

	/**
	 * Import silently drops attributes not present in the block.json allowlist.
	 */
	public function test_import_drops_unknown_attributes() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/template' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'schemaVersion' => 1,
				'blockName'     => 'designsetgo/query',
				'attributes'    => array(
					'perPage'       => 6,
					'__evil_script' => '<script>alert(1)</script>',
					'unknownField'  => 'should-be-dropped',
				),
				'innerBlocks'   => '',
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$markup = $response->get_data()['blockMarkup'];
		$this->assertStringNotContainsString( '__evil_script', $markup );
		$this->assertStringNotContainsString( 'unknownField', $markup );
	}

	/**
	 * Import preserves freeform custom HTML inside inner blocks instead of KSES-stripping it.
	 */
	public function test_import_preserves_freeform_custom_html_inside_inner_blocks() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/template' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'schemaVersion' => 1,
				'blockName'     => 'designsetgo/query',
				'attributes'    => array( 'perPage' => 6 ),
				'innerBlocks'   => '<x-dsgo-card data-variant="hero">Hello</x-dsgo-card>',
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$markup = $response->get_data()['blockMarkup'];
		$this->assertStringContainsString( '<x-dsgo-card data-variant="hero">Hello</x-dsgo-card>', $markup );
	}
}

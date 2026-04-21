<?php
/**
 * Tests for the pagination block's upstream contracts:
 *  1. State registry is populated correctly after a post query.
 *  2. JSON blobs (data-dsgo-attrs / data-dsgo-inner) are emitted by the
 *     container wrapper when a query ID is present.
 *  3. JSON blobs are omitted when the query ID is empty.
 *
 * @group query-block
 */
class DesignSetGo_Query_Pagination_Render_Test extends WP_UnitTestCase {

	/**
	 * Load the render helpers from the build directory.
	 * Must be called from within a test after the build artefact exists.
	 */
	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $path;
	}

	/**
	 * After a posts render the state registry should record totalPages and
	 * totalItems correctly so the pagination block can read them without
	 * re-executing the query.
	 *
	 * @since 2.1.0
	 */
	public function test_pagination_records_total_pages_after_posts_render() {
		self::factory()->post->create_many( 7, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		designsetgo_query_render(
			array(
				'source'   => 'posts',
				'postType' => 'post',
				'perPage'  => 3,
			),
			array(
				'query_id'   => 'p-state',
				'page'       => 1,
				'inner_html' => '',
			)
		);

		$state = designsetgo_query_get_last_state( 'p-state' );

		$this->assertIsArray( $state, 'State should be an array after a query render.' );
		$this->assertSame( 3, $state['totalPages'], 'Expected 3 pages for 7 items with perPage=3.' );
		$this->assertSame( 7, $state['totalItems'], 'Expected 7 total items.' );
	}

	/**
	 * When a query ID is present the container HTML should include the two
	 * JSON blob script elements that load-more uses to re-send the block
	 * attributes and inner-blocks template to the REST endpoint.
	 *
	 * @since 2.1.0
	 */
	public function test_container_emits_json_attrs_blob_when_query_id_present() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		// v2.6: blobs live on the outer .dsgo-query-region wrapper emitted by
		// designsetgo_query_render_region(), not inside designsetgo_query_wrap.
		$result = designsetgo_query_render_region(
			array(
				'source'   => 'posts',
				'postType' => 'post',
				'perPage'  => 5,
			),
			array(
				'query_id'   => 'blobs',
				'page'       => 1,
				'inner_html' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertStringContainsString(
			'data-dsgo-blobs-for="blobs"',
			$result['html'],
			'Result HTML must include the blob wrapper (data-dsgo-blobs-for) when query_id is set.'
		);
		$this->assertStringContainsString(
			'data-dsgo-attrs',
			$result['html'],
			'Result HTML must include the data-dsgo-attrs JSON blob when query_id is set.'
		);
		$this->assertStringContainsString(
			'data-dsgo-inner',
			$result['html'],
			'Result HTML must include the data-dsgo-inner JSON blob when query_id is set.'
		);
	}

	/**
	 * When no query ID is supplied the container must NOT emit the JSON blobs
	 * so anonymous query containers stay lean.
	 *
	 * @since 2.1.0
	 */
	public function test_container_omits_blobs_when_query_id_empty() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$result = designsetgo_query_render_region(
			array(
				'source'   => 'posts',
				'postType' => 'post',
				'perPage'  => 5,
			),
			array(
				'query_id'   => '',
				'page'       => 1,
				'inner_html' => '',
			)
		);

		$this->assertStringNotContainsString(
			'data-dsgo-attrs',
			$result['html'],
			'Container HTML must NOT include data-dsgo-attrs when query_id is empty.'
		);
		$this->assertStringNotContainsString(
			'data-dsgo-inner',
			$result['html'],
			'Container HTML must NOT include data-dsgo-inner when query_id is empty.'
		);
	}
}

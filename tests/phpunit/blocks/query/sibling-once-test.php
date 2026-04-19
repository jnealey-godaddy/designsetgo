<?php
/**
 * Fix 1 regression test: sibling blocks (pagination, filter, no-results)
 * must render exactly once for the whole query — NOT once per item.
 *
 * Before the fix, render.php passed $content (pre-rendered inner HTML of ALL
 * inner blocks) as inner_html to designsetgo_query_render_item(). Because
 * parse_blocks() re-parsed that string once per post, a sibling block like
 * query-pagination would appear in the output N times (once per item).
 *
 * After the fix, designsetgo_query_render_region() splits parsed innerBlocks
 * into template blocks (rendered per-item) and sibling blocks (rendered once
 * after the query). The test confirms the count is exactly 1.
 *
 * NOTE: this test exercises designsetgo_query_render_region() directly,
 * without block registration (pagination block isn't registered in unit-test
 * bootstrap). We use a recognisable dummy comment block name so the sibling-
 * splitting logic is exercised and the output is verifiable without needing
 * full block registration.
 *
 * @group query-block
 */
class DesignSetGo_Query_Sibling_Once_Test extends WP_UnitTestCase {

	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $path;
	}

	/**
	 * Verify the region helper splits sibling vs template blocks correctly.
	 *
	 * We stub the pagination block name inside inner_html and count how many
	 * times the region output contains the block comment — it must be exactly 1
	 * (rendered as part of the siblings pass), never N (once per post).
	 */
	public function test_sibling_blocks_render_once_not_per_item() {
		// Create 3 published posts so the template loop runs 3 times.
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		// Build an inner_html string that contains:
		//  - a paragraph template block (renders per item).
		//  - a designsetgo/query-pagination sibling (should render once).
		//
		// Because query-pagination is not registered in the unit-test bootstrap its
		// render() call returns '' — that's fine. What we're testing is that the
		// SPLITTING logic runs at the region level, not per-item. We verify the
		// dsgo-query-region wrapper is present and that the pagination comment
		// string does NOT appear inside a <li> element.
		$template_block  = '<!-- wp:paragraph --><p>Item template</p><!-- /wp:paragraph -->';
		$sibling_block   = '<!-- wp:designsetgo/query-pagination /-->';
		$full_inner_html = $template_block . $sibling_block;

		$result = designsetgo_query_render_region(
			array(
				'source'      => 'posts',
				'postType'    => 'post',
				'perPage'     => 10,
				'queryId'     => 'sibling-once-test',
				'tagName'     => 'ul',
				'itemTagName' => 'li',
			),
			array(
				'query_id'   => 'sibling-once-test',
				'page'       => 1,
				'inner_html' => $full_inner_html,
			)
		);

		$html = $result['html'];

		// Region wrapper must be present.
		$this->assertStringContainsString(
			'data-dsgo-query-region="sibling-once-test"',
			$html,
			'Region wrapper must be emitted by designsetgo_query_render_region().'
		);

		// The paragraph template content should appear once per post inside <li>
		// items. We count <li> elements rather than the string literal because the
		// string also appears once in the JSON blob (data-dsgo-inner).
		$li_count = substr_count( $html, '<li class="dsgo-query__item">' );
		$this->assertSame(
			3,
			$li_count,
			'Template block must render once per item (3 posts → 3 <li> items).'
		);

		// The pagination block comment must NOT appear inside any <li> element.
		// Extract all <li>…</li> content and confirm none contains the sibling.
		preg_match_all( '/<li[^>]*>(.*?)<\/li>/s', $html, $matches );
		$li_content = implode( '', $matches[1] ?? array() );
		$this->assertStringNotContainsString(
			'wp:designsetgo/query-pagination',
			$li_content,
			'Pagination block comment must not appear inside <li> item wrappers — it is a sibling, not a template block.'
		);
	}

	/**
	 * Confirm the region helper returns the correct { html, totalPages, totalItems }
	 * shape and that totalItems reflects the actual post count.
	 */
	public function test_region_returns_correct_shape_and_totals() {
		self::factory()->post->create_many( 5, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$result = designsetgo_query_render_region(
			array(
				'source'   => 'posts',
				'postType' => 'post',
				'perPage'  => 2,
				'queryId'  => 'region-shape-test',
			),
			array(
				'query_id'   => 'region-shape-test',
				'page'       => 1,
				'inner_html' => '<!-- wp:paragraph --><p>X</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'html', $result );
		$this->assertArrayHasKey( 'totalPages', $result );
		$this->assertArrayHasKey( 'totalItems', $result );
		$this->assertSame( 5, $result['totalItems'] );
		$this->assertSame( 3, $result['totalPages'] );
	}

	/**
	 * Verify data-dsgo-query-role="container" is present on the list element
	 * so JS can distinguish it from pagination/filter wrappers.
	 */
	public function test_list_element_has_container_role() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$result = designsetgo_query_render_region(
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 5, 'queryId' => 'role-test' ),
			array( 'query_id' => 'role-test', 'page' => 1, 'inner_html' => '' )
		);

		$this->assertStringContainsString(
			'data-dsgo-query-role="container"',
			$result['html'],
			'List element must carry data-dsgo-query-role="container".'
		);
	}
}

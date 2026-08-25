<?php
/**
 * Loop Carousel — pagination and item-container contract.
 *
 * Verifies the two halves of "carousel presentation wins over infinite
 * scroll":
 *
 *  - the item-host registry records which layout block rendered a query, and
 *    designsetgo_query_host_supports_infinite_scroll() answers for it;
 *  - a carousel host tags its own item container with the same role/id pair
 *    the grid host uses, so the shared view.js load-more plumbing can find it.
 *
 * @group query-block
 */
class DesignSetGo_Carousel_Pagination_Test extends WP_UnitTestCase {

	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $path;
	}

	/**
	 * Build a parsed innerBlocks tree: one item host wrapping one paragraph,
	 * plus an infinite-scroll pagination sibling.
	 *
	 * @param string $host_name Item host block name.
	 * @return array Parsed children.
	 */
	private function children_with_host( $host_name ) {
		return array(
			array(
				'blockName'   => $host_name,
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Item</p>',
						'innerContent' => array( '<p>Item</p>' ),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( '' ),
			),
			array(
				'blockName'    => 'designsetgo/query-pagination',
				'attrs'        => array( 'paginationKind' => 'infinite' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array( '' ),
			),
		);
	}

	/**
	 * Render a two-page query through the container helper.
	 *
	 * @param string $host_name Item host block name.
	 * @param string $query_id  Query identifier.
	 * @return string Rendered HTML.
	 */
	private function render_two_page_query( $host_name, $query_id ) {
		// perPage 2 over 5 posts gives totalPages 3, clearing the pagination
		// block's single-page guard.
		self::factory()->post->create_many( 5, array( 'post_status' => 'publish' ) );

		return designsetgo_query_render_container(
			array(
				'source'   => 'posts',
				'postType' => 'post',
				'perPage'  => 2,
				'queryId'  => $query_id,
			),
			$this->children_with_host( $host_name ),
			1,
			$query_id,
			'class="test-wrap"'
		);
	}

	public function test_only_the_grid_host_supports_infinite_scroll() {
		$this->load_helpers();

		$this->assertTrue( designsetgo_query_host_supports_infinite_scroll( 'designsetgo/query-results' ) );
		// A legacy tree with no host block still renders a vertical list.
		$this->assertTrue( designsetgo_query_host_supports_infinite_scroll( '' ) );

		$this->assertFalse( designsetgo_query_host_supports_infinite_scroll( 'designsetgo/slider' ) );
		$this->assertFalse( designsetgo_query_host_supports_infinite_scroll( 'designsetgo/scroll-slides' ) );
	}

	public function test_third_parties_can_opt_a_host_into_infinite_scroll() {
		$this->load_helpers();

		$filter = static function ( $supported, $host_name ) {
			return 'acme/masonry' === $host_name ? true : $supported;
		};
		add_filter( 'designsetgo_query_host_supports_infinite_scroll', $filter, 10, 2 );

		$this->assertTrue( designsetgo_query_host_supports_infinite_scroll( 'acme/masonry' ) );
		$this->assertFalse( designsetgo_query_host_supports_infinite_scroll( 'designsetgo/slider' ) );

		remove_filter( 'designsetgo_query_host_supports_infinite_scroll', $filter, 10 );
	}

	public function test_container_records_the_resolved_item_host() {
		$this->load_helpers();

		$this->render_two_page_query( 'designsetgo/slider', 'host-registry' );

		$this->assertSame(
			'designsetgo/slider',
			designsetgo_query_get_item_host( 'host-registry' )
		);
	}

	public function test_carousel_host_degrades_infinite_scroll_to_load_more() {
		$this->load_helpers();

		$html = $this->render_two_page_query( 'designsetgo/slider', 'carousel-pag' );

		// The sentinel is what infinite scroll hangs on; inside a carousel it
		// either fires on first paint or never, so it must not be emitted.
		$this->assertStringNotContainsString( 'dsgo-query-pagination__sentinel', $html );
		$this->assertStringNotContainsString( 'data-dsgo-pagination="infinite"', $html );

		// The reader keeps a way to the rest of the query.
		$this->assertStringContainsString( 'data-dsgo-pagination="loadmore"', $html );
		$this->assertStringContainsString( 'dsgo-query-pagination__loadmore', $html );
	}

	public function test_grid_host_still_gets_the_infinite_sentinel() {
		$this->load_helpers();

		$html = $this->render_two_page_query( 'designsetgo/query-results', 'grid-pag' );

		$this->assertStringContainsString( 'data-dsgo-pagination="infinite"', $html );
		$this->assertStringContainsString( 'dsgo-query-pagination__sentinel', $html );
	}

	public function test_slider_track_is_tagged_as_the_item_container() {
		$this->load_helpers();

		$html = $this->render_two_page_query( 'designsetgo/slider', 'slider-container' );

		$this->assertMatchesRegularExpression(
			'#<div class="dsgo-slider__track" data-dsgo-query-results-role="container" data-dsgo-query-id="slider-container">#',
			$html
		);
	}

	public function test_scroll_slides_panels_are_tagged_as_the_item_container() {
		$this->load_helpers();

		$html = $this->render_two_page_query( 'designsetgo/scroll-slides', 'panels-container' );

		$this->assertMatchesRegularExpression(
			'#<div class="dsgo-scroll-slides__panels" data-dsgo-query-results-role="container" data-dsgo-query-id="panels-container">#',
			$html
		);
	}
}

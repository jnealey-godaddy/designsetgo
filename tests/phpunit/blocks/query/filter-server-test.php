<?php
/**
 * Tests for URL-param-driven filtering in designsetgo_query_build_posts_args().
 *
 * Covers:
 *  - filter_<taxonomy> param → tax_query clause (IN, slug field)
 *  - sort param (orderby.DIR) → args['orderby'] / args['order']
 *  - q search param → args['s']
 *
 * @package DesignSetGo
 * @since 2.1.0
 * @group query-block
 */
class DesignSetGo_Query_Filter_Server_Test extends WP_UnitTestCase {

	/**
	 * Load the render helpers (served from build/).
	 */
	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists(
			$path,
			'Run `npm run build` before PHPUnit — render helpers are served from build/.'
		);
		require_once $path;
	}

	/**
	 * filter_category=tech should limit results to posts in that category.
	 */
	public function test_filter_category_param_narrows_posts_via_tax_query() {
		$cat = self::factory()->category->create( array( 'slug' => 'tech' ) );

		// 2 posts in "tech" category.
		$in_cat = self::factory()->post->create_many(
			2,
			array( 'post_status' => 'publish' )
		);
		foreach ( $in_cat as $id ) {
			wp_set_post_categories( $id, array( $cat ) );
		}

		// 3 posts NOT in any category (get default "Uncategorized").
		self::factory()->post->create_many(
			3,
			array( 'post_status' => 'publish' )
		);

		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'      => 'posts',
				'postType'    => 'post',
				'perPage'     => 10,
				'tagName'     => 'ul',
				'itemTagName' => 'li',
			),
			array(
				'query_id'   => 'filter-cat-test',
				'page'       => 1,
				'inner_html' => '',
				'params'     => array( 'filter_category' => array( 'tech' ) ),
			)
		);

		$this->assertSame(
			2,
			$result['totalItems'],
			'filter_category=tech should return only the 2 posts in the tech category.'
		);
	}

	/**
	 * sort=title.ASC should order posts alphabetically ascending by title.
	 */
	public function test_sort_param_overrides_orderby_and_order() {
		// Two posts with distinct titles — create in reverse order so default
		// (date DESC) would put Zeta first.
		self::factory()->post->create(
			array( 'post_title' => 'Alpha', 'post_status' => 'publish' )
		);
		self::factory()->post->create(
			array( 'post_title' => 'Zeta', 'post_status' => 'publish' )
		);

		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'      => 'posts',
				'postType'    => 'post',
				'perPage'     => 10,
				'orderBy'     => 'date',
				'order'       => 'DESC',
				'tagName'     => 'ul',
				'itemTagName' => 'li',
			),
			array(
				'query_id'   => 'sort-test',
				'page'       => 1,
				'inner_html' => '<!-- wp:post-title /-->',
				'params'     => array( 'sort' => 'title.ASC' ),
			)
		);

		$alpha_pos = strpos( $result['html'], 'Alpha' );
		$zeta_pos  = strpos( $result['html'], 'Zeta' );

		$this->assertNotFalse( $alpha_pos, 'Alpha should appear in the output.' );
		$this->assertNotFalse( $zeta_pos, 'Zeta should appear in the output.' );
		$this->assertLessThan(
			$zeta_pos,
			$alpha_pos,
			'sort=title.ASC — Alpha should appear before Zeta.'
		);
	}

	/**
	 * q=Needle should limit results to posts whose title/content matches.
	 */
	public function test_q_search_param_narrows_posts() {
		self::factory()->post->create(
			array( 'post_title' => 'Needle post', 'post_status' => 'publish' )
		);
		self::factory()->post->create(
			array( 'post_title' => 'Haystack', 'post_status' => 'publish' )
		);

		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'      => 'posts',
				'postType'    => 'post',
				'perPage'     => 10,
				'tagName'     => 'ul',
				'itemTagName' => 'li',
			),
			array(
				'query_id'   => 'q-search-test',
				'page'       => 1,
				'inner_html' => '',
				'params'     => array( 'q' => 'Needle' ),
			)
		);

		$this->assertSame(
			1,
			$result['totalItems'],
			'q=Needle should return only the 1 matching post.'
		);
	}
}

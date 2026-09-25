<?php
/**
 * Tests for per-Query URL param scoping.
 *
 * Verified bug: designsetgo_query_extract_params_from_request() used to read
 * $_GET globally with no queryId namespacing, so every designsetgo/query on a
 * page consumed the same filter_ / q / sort params — a checkbox filter on one
 * Query ('All posts') also silently filtered an unrelated Query on the same
 * page ('Related posts') that had no control for it at all.
 *
 * Fix (see render-helpers.php, render-posts.php, query-filter/render.php):
 *  - A query-scoped key (`{key}__{queryId}`) always wins over a same-named
 *    bare key, and a key scoped for a DIFFERENT query is never read.
 *  - A bare/legacy key is honored only when the query declares it (its own
 *    filter blocks' paramName, or its bindSearchTo attribute) — see
 *    designsetgo_query_collect_declared_params().
 *  - WooCommerce's own filter-block params bypass this gate entirely — Woo
 *    can't emit a queryId, so they must keep driving every product query on
 *    the page unscoped, exactly as before.
 *
 * @group query
 */
class DesignSetGo_Query_Param_Scoping_Test extends WP_UnitTestCase {

	/**
	 * Loads the query render helpers from build/ (production's actual source).
	 */
	public function set_up() {
		parent::set_up();

		$helpers = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $helpers, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $helpers;

		$posts = DESIGNSETGO_PATH . 'build/blocks/query/render-posts.php';
		$this->assertFileExists( $posts, 'Run `npm run build` before PHPUnit — render-posts.php is served from build/.' );
		require_once $posts;
	}

	/**
	 * Restores $_GET so scoping tests never leak state into later tests.
	 */
	public function tear_down() {
		$_GET = array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		parent::tear_down();
	}


	public function test_extract_params_prefers_scoped_key_over_bare() {
		$_GET = array( // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'filter_category'         => 'bare-value',
			'filter_category__qa1b2c' => 'scoped-value',
		);

		$params = designsetgo_query_extract_params_from_request( 'qa1b2c' );

		$this->assertSame( 'scoped-value', $params['filter_category'] );
	}

	public function test_extract_params_wins_regardless_of_get_iteration_order() {
		// Scoped entry appears BEFORE the bare one this time — resolution must
		// not depend on which one $_GET happens to iterate first.
		$_GET = array( // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'filter_category__qa1b2c' => 'scoped-value',
			'filter_category'         => 'bare-value',
		);

		$params = designsetgo_query_extract_params_from_request( 'qa1b2c' );

		$this->assertSame( 'scoped-value', $params['filter_category'] );
	}

	public function test_extract_params_falls_back_to_bare_key_when_unscoped() {
		$_GET = array( 'q' => 'hello' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$params = designsetgo_query_extract_params_from_request( 'qa1b2c' );

		$this->assertSame( 'hello', $params['q'] );
	}

	public function test_extract_params_never_reads_a_key_scoped_for_a_different_query() {
		$_GET = array( 'filter_category__qzzzzz' => 'other-query' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$params = designsetgo_query_extract_params_from_request( 'qa1b2c' );

		$this->assertArrayNotHasKey( 'filter_category', $params );
	}

	public function test_extract_params_two_queries_read_independent_scoped_values() {
		$_GET = array( // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'filter_category__qa1' => 'news',
			'filter_category__qb1' => 'sports',
		);

		$this->assertSame( 'news', designsetgo_query_extract_params_from_request( 'qa1' )['filter_category'] );
		$this->assertSame( 'sports', designsetgo_query_extract_params_from_request( 'qb1' )['filter_category'] );
	}

	public function test_extract_params_still_honors_woo_params_unscoped() {
		$_GET = array( 'min_price' => '10' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$params = designsetgo_query_extract_params_from_request( 'qa1b2c' );

		$this->assertSame( '10', $params['min_price'] );
	}


	public function test_collect_declared_params_reads_filter_block_param_names() {
		$blocks = parse_blocks(
			'<!-- wp:designsetgo/query-filter {"filterKind":"checkbox","taxonomy":"category","paramName":"filter_category"} /-->' .
			'<!-- wp:designsetgo/query-filter {"filterKind":"search","paramName":"q"} /-->'
		);

		$declared = designsetgo_query_collect_declared_params( $blocks );

		$this->assertContains( 'filter_category', $declared );
		$this->assertContains( 'q', $declared );
	}

	public function test_collect_declared_params_defaults_to_filter_category_when_paramname_omitted() {
		// A checkbox/select filter whose paramName equals the block.json
		// default is omitted from the stored comment entirely.
		$blocks = parse_blocks( '<!-- wp:designsetgo/query-filter {"filterKind":"checkbox"} /-->' );

		$declared = designsetgo_query_collect_declared_params( $blocks );

		$this->assertContains( 'filter_category', $declared );
	}

	public function test_collect_declared_params_includes_bind_search_to() {
		$declared = designsetgo_query_collect_declared_params( array(), 'custom_search_param' );

		$this->assertContains( 'custom_search_param', $declared );
	}

	public function test_collect_declared_params_ignores_active_and_reset_kinds() {
		$blocks = parse_blocks(
			'<!-- wp:designsetgo/query-filter {"filterKind":"active","paramName":""} /-->' .
			'<!-- wp:designsetgo/query-filter {"filterKind":"reset","paramName":""} /-->'
		);

		$this->assertSame( array(), designsetgo_query_collect_declared_params( $blocks ) );
	}

	public function test_collect_declared_params_stops_at_a_nested_query_boundary() {
		$blocks = parse_blocks(
			'<!-- wp:designsetgo/query {"queryId":"nested"} -->' .
			'<!-- wp:designsetgo/query-filter {"filterKind":"checkbox","paramName":"filter_inner"} /-->' .
			'<!-- /wp:designsetgo/query -->'
		);

		$this->assertSame( array(), designsetgo_query_collect_declared_params( $blocks ) );
	}

	public function test_collect_declared_params_walks_through_a_layout_wrapper() {
		// Filters are often wrapped in a Row/Group for visual arrangement —
		// declaration must still be found beneath it.
		$blocks = parse_blocks(
			'<!-- wp:designsetgo/row -->' .
			'<!-- wp:designsetgo/query-filter {"filterKind":"checkbox","paramName":"filter_category"} /-->' .
			'<!-- /wp:designsetgo/row -->'
		);

		$this->assertContains( 'filter_category', designsetgo_query_collect_declared_params( $blocks ) );
	}


	public function test_build_posts_args_ignores_undeclared_bare_filter() {
		$atts = designsetgo_query_defaults( array( 'postType' => 'post' ) );

		$args = designsetgo_query_build_posts_args(
			$atts,
			array(
				'page'            => 1,
				'params'          => array( 'filter_category' => 'news' ),
				'declared_params' => array(), // This query owns no filters at all.
			)
		);

		$this->assertArrayNotHasKey( 'tax_query', $args, 'A bystander query with no filter control must ignore a bare filter_* param.' );
	}

	public function test_build_posts_args_applies_a_declared_bare_filter() {
		$atts = designsetgo_query_defaults( array( 'postType' => 'post' ) );

		$args = designsetgo_query_build_posts_args(
			$atts,
			array(
				'page'            => 1,
				'params'          => array( 'filter_category' => 'news' ),
				'declared_params' => array( 'filter_category' ),
			)
		);

		$this->assertArrayHasKey( 'tax_query', $args );
	}

	public function test_build_posts_args_ignores_undeclared_bare_search() {
		$atts = designsetgo_query_defaults( array( 'postType' => 'post' ) );

		$args = designsetgo_query_build_posts_args(
			$atts,
			array(
				'page'            => 1,
				'params'          => array( 'q' => 'hello' ),
				'declared_params' => array(),
			)
		);

		$this->assertArrayNotHasKey( 's', $args );
	}

	public function test_build_posts_args_ignores_undeclared_bare_sort() {
		$atts = designsetgo_query_defaults(
			array(
				'postType' => 'post',
				'orderBy'  => 'date',
			) 
		);

		$args = designsetgo_query_build_posts_args(
			$atts,
			array(
				'page'            => 1,
				'params'          => array( 'sort' => 'title.ASC' ),
				'declared_params' => array(),
			)
		);

		$this->assertSame( 'date', $args['orderby'], 'An undeclared ?sort= must not override this query\'s own orderBy.' );
	}


	/**
	 * Markup for a Query block with a checkbox category filter, scoped to
	 * $query_id, targeting only published posts.
	 *
	 * @param string $query_id Query ID.
	 * @return string Block markup.
	 */
	private function query_with_checkbox_filter( $query_id ) {
		return '<!-- wp:designsetgo/query {"queryId":"' . $query_id . '","perPage":10,"postType":"post"} -->'
			. '<!-- wp:designsetgo/query-results --><!-- wp:post-title /--><!-- /wp:designsetgo/query-results -->'
			. '<!-- wp:designsetgo/query-filter {"filterKind":"checkbox","taxonomy":"category","paramName":"filter_category"} /-->'
			. '<!-- /wp:designsetgo/query -->';
	}

	/**
	 * Markup for a Query block with NO filter siblings at all — a "bystander"
	 * query that declares nothing.
	 *
	 * @param string $query_id Query ID.
	 * @return string Block markup.
	 */
	private function query_with_no_filters( $query_id ) {
		return '<!-- wp:designsetgo/query {"queryId":"' . $query_id . '","perPage":10,"postType":"post"} -->'
			. '<!-- wp:designsetgo/query-results --><!-- wp:post-title /--><!-- /wp:designsetgo/query-results -->'
			. '<!-- /wp:designsetgo/query -->';
	}

	/**
	 * Creates two categories with two published posts each.
	 *
	 * @return array{news: int[], sports: int[]}
	 */
	private function create_categorized_posts() {
		$news_term   = self::factory()->category->create(
			array(
				'name' => 'News',
				'slug' => 'news',
			) 
		);
		$sports_term = self::factory()->category->create(
			array(
				'name' => 'Sports',
				'slug' => 'sports',
			) 
		);

		$news_ids   = self::factory()->post->create_many(
			2,
			array(
				'post_status'   => 'publish',
				'post_category' => array( $news_term ),
			) 
		);
		$sports_ids = self::factory()->post->create_many(
			2,
			array(
				'post_status'   => 'publish',
				'post_category' => array( $sports_term ),
			) 
		);

		return array(
			'news'   => $news_ids,
			'sports' => $sports_ids,
		);
	}

	public function test_bystander_query_is_unaffected_by_another_querys_bare_filter() {
		$this->create_categorized_posts();

		// A page with two Query blocks: "All posts" (has a category filter)
		// and "Related posts" (no filter control at all) — the exact scenario
		// from the bug report. A bare, unscoped ?filter_category= is present
		// (e.g. a legacy no-JS submission or hand-typed URL).
		$_GET = array( 'filter_category' => 'news' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$html = do_blocks(
			$this->query_with_checkbox_filter( 'allposts' )
			. $this->query_with_no_filters( 'related' )
		);

		$this->assertIsString( $html );

		$all_posts_state = designsetgo_query_get_last_state( 'allposts' );
		$related_state   = designsetgo_query_get_last_state( 'related' );

		$this->assertSame( 2, $all_posts_state['totalItems'], '"All posts" declares filter_category, so the bare param must narrow it to the 2 News posts.' );
		$this->assertSame( 4, $related_state['totalItems'], '"Related posts" declares no filter at all, so the bare param must NOT narrow it — all 4 posts.' );
	}

	public function test_legacy_single_query_bare_param_still_works() {
		$this->create_categorized_posts();

		$_GET = array( 'filter_category' => 'sports' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		do_blocks( $this->query_with_checkbox_filter( 'onlyone' ) );

		$state = designsetgo_query_get_last_state( 'onlyone' );
		$this->assertSame( 2, $state['totalItems'], 'A single Query on the page must keep honoring an existing bookmarked/shared bare-param URL unchanged.' );
	}

	public function test_two_queries_with_independent_filters_do_not_cross_contaminate() {
		$this->create_categorized_posts();

		// Both queries declare their OWN filter_category control — the
		// harder collision case than the bystander above. Once scoped (as
		// the rendered filter forms and view.js now write), each must read
		// only its own selection.
		$_GET = array( // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'filter_category__qa1' => 'news',
			'filter_category__qb1' => 'sports',
		);

		do_blocks(
			$this->query_with_checkbox_filter( 'qa1' )
			. $this->query_with_checkbox_filter( 'qb1' )
		);

		$state_a = designsetgo_query_get_last_state( 'qa1' );
		$state_b = designsetgo_query_get_last_state( 'qb1' );

		$this->assertSame( 2, $state_a['totalItems'], 'Query A must apply only its own scoped filter (News).' );
		$this->assertSame( 2, $state_b['totalItems'], 'Query B must apply only its own scoped filter (Sports), unaffected by Query A.' );
	}
}

<?php
/**
 * Tests for per-Query URL param scoping.
 *
 * Verified bug: designsetgo_query_extract_params_from_request() used to read
 * $_GET globally with no queryId namespacing, so every designsetgo/query on a
 * page consumed the same filter_ / q / sort params — an INTERACTION on one
 * Query's own filter control (e.g. checking a "News" checkbox on "All
 * posts") also silently filtered an unrelated Query on the same page
 * ('Related posts') that had no such control at all.
 *
 * Fix (see render-helpers.php, render-posts.php, query-filter/render.php):
 *  - A query-scoped key (`{key}__{queryId}`) always wins over a same-named
 *    bare key, and a key scoped for a DIFFERENT query is never read.
 *  - A bare/legacy key is honored by EVERY query, exactly as before this
 *    task — that's the pre-existing, intentional contract a menu link or
 *    widget to `?filter_category=news` relies on, and an earlier version of
 *    this fix that gated bare keys by filter-block declaration broke it
 *    (see PR #592's CI failures in DesignSetGo_Query_Filter_Server_Test).
 *    Query-filter blocks now render their own `name` attribute already
 *    scoped, so an INTERACTION never produces a bare key in the first
 *    place — that's what actually stops the cross-query leak.
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


	public function test_build_posts_args_applies_a_bare_filter_to_any_query() {
		// No filter block, no bindSearchTo — just a bare URL param. This is
		// the pre-existing, intentional contract: a menu link or widget to
		// `?filter_category=news` works regardless of whether the landing
		// page's Query block happens to carry a matching filter control.
		$atts = designsetgo_query_defaults( array( 'postType' => 'post' ) );

		$args = designsetgo_query_build_posts_args(
			$atts,
			array(
				'page'   => 1,
				'params' => array( 'filter_category' => 'news' ),
			)
		);

		$this->assertArrayHasKey( 'tax_query', $args );
	}

	public function test_build_posts_args_applies_a_bare_search_to_any_query() {
		$atts = designsetgo_query_defaults( array( 'postType' => 'post' ) );

		$args = designsetgo_query_build_posts_args(
			$atts,
			array(
				'page'   => 1,
				'params' => array( 'q' => 'hello' ),
			)
		);

		$this->assertSame( 'hello', $args['s'] );
	}

	public function test_build_posts_args_applies_a_bare_sort_to_any_query() {
		$atts = designsetgo_query_defaults(
			array(
				'postType' => 'post',
				'orderBy'  => 'date',
			)
		);

		$args = designsetgo_query_build_posts_args(
			$atts,
			array(
				'page'   => 1,
				'params' => array( 'sort' => 'title.ASC' ),
			)
		);

		$this->assertSame( 'title', $args['orderby'] );
		$this->assertSame( 'ASC', $args['order'] );
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

	/**
	 * The actual cross-query leak this task fixes: an INTERACTION on one
	 * Query's own filter control (which now writes a SCOPED key, per
	 * query-filter/render.php) must never reach an unrelated Query — even
	 * one with no filter control of its own.
	 */
	public function test_bystander_query_is_unaffected_by_a_scoped_param_from_another_querys_filter() {
		$this->create_categorized_posts();

		// A page with two Query blocks: "All posts" (has a category filter,
		// and its filter control was just used — a scoped key) and "Related
		// posts" (no filter control at all).
		$_GET = array( 'filter_category__allposts' => 'news' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		do_blocks(
			$this->query_with_checkbox_filter( 'allposts' )
			. $this->query_with_no_filters( 'related' )
		);

		$all_posts_state = designsetgo_query_get_last_state( 'allposts' );
		$related_state   = designsetgo_query_get_last_state( 'related' );

		$this->assertSame( 2, $all_posts_state['totalItems'], '"All posts" owns the scoped key, so it narrows to the 2 News posts.' );
		$this->assertSame( 4, $related_state['totalItems'], '"Related posts" is scoped to a different query (allposts), so it must be unaffected — all 4 posts.' );
	}

	/**
	 * The backward-compat contract this task must NOT break: a bare, legacy
	 * `?filter_category=` (a menu link, a widget, a hand-typed URL — nothing
	 * scoped to any queryId) has always applied to every Query on the page,
	 * whether or not that Query has a matching filter control. Gating this
	 * by filter-block declaration was tried and reverted (see PR #592).
	 */
	public function test_bare_legacy_param_still_applies_to_a_query_with_no_filter_block() {
		$this->create_categorized_posts();

		$_GET = array( 'filter_category' => 'news' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		do_blocks( $this->query_with_no_filters( 'related' ) );

		$state = designsetgo_query_get_last_state( 'related' );
		$this->assertSame( 2, $state['totalItems'], 'A bare/legacy param must keep narrowing every Query, including one with no filter control of its own.' );
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

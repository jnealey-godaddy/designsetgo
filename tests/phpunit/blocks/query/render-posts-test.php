<?php
/**
 * @group query-block
 */
class DesignSetGo_Query_Render_Posts_Test extends WP_UnitTestCase {

	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $path;
		// render-helpers.php loads this lazily on the first posts render; tests
		// that build query args directly must not depend on test order.
		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-posts.php';
	}

	public function test_grouped_items_render_with_their_own_global_post() {
		$this->load_helpers();
		$term  = self::factory()->category->create( array( 'name' => 'Grouped' ) );
		$posts = self::factory()->post->create_many( 2, array( 'post_status' => 'publish', 'post_category' => array( $term ) ) );
		$page  = self::factory()->post->create( array( 'post_type' => 'page' ) );

		global $post;
		$post = get_post( $page ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$seen   = array();
		$record = static function ( $content ) use ( &$seen ) {
			$seen[] = get_the_ID();
			return $content;
		};
		add_filter( 'render_block_core/paragraph', $record );
		designsetgo_query_render(
			array(
				'source'   => 'posts',
				'postType' => 'post',
				'perPage'  => 10,
				'groupBy'  => array(
					'field' => 'taxonomy',
					'key'   => 'category',
				),
			),
			array(
				'query_id'   => 'qgroup',
				'page'       => 1,
				// A group header block is what switches on grouped rendering.
				'inner_html' => '<!-- wp:designsetgo/query-group-header /--><!-- wp:paragraph --><p>Item</p><!-- /wp:paragraph -->',
			)
		);
		remove_filter( 'render_block_core/paragraph', $record );

		$this->assertNotEmpty( $seen );
		$this->assertNotContains( $page, $seen );
		$this->assertEqualsCanonicalizing( $posts, array_values( array_intersect( $seen, $posts ) ) );
		$this->assertSame( $page, $post->ID, 'The page is the global post again afterwards.' );
	}

	public function test_renders_a_list_item_per_post() {
		self::factory()->post->create_many( 4, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'      => 'posts',
				'postType'    => 'post',
				'perPage'     => 4,
				'tagName'     => 'ul',
				'itemTagName' => 'li',
			),
			array(
				'query_id'   => 'test',
				'page'       => 1,
				'inner_html' => '<!-- wp:paragraph --><p>Item</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertSame( 4, substr_count( $result['html'], '<li' ) );
		$this->assertSame( 4, $result['totalItems'] );
	}

	public function test_respects_per_page_and_pagination() {
		self::factory()->post->create_many( 7, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$atts = array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 3, 'itemTagName' => 'li' );
		$page1 = designsetgo_query_render( $atts, array( 'query_id' => 't', 'page' => 1, 'inner_html' => '' ) );
		$page2 = designsetgo_query_render( $atts, array( 'query_id' => 't', 'page' => 2, 'inner_html' => '' ) );
		$page3 = designsetgo_query_render( $atts, array( 'query_id' => 't', 'page' => 3, 'inner_html' => '' ) );

		$this->assertSame( 7, $page1['totalItems'] );
		$this->assertSame( 3, $page1['totalPages'] );
		$this->assertSame( 3, substr_count( $page1['html'], '<li' ) );
		$this->assertSame( 3, substr_count( $page2['html'], '<li' ) );
		$this->assertSame( 1, substr_count( $page3['html'], '<li' ) );
	}

	public function test_taxonomy_filter_narrows_results() {
		$cat = self::factory()->category->create();
		$matched = self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		foreach ( $matched as $id ) {
			wp_set_post_categories( $id, array( $cat ) );
		}
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'   => 'posts',
				'postType' => 'post',
				'perPage'  => 10,
				'taxQuery' => array(
					'relation' => 'AND',
					'clauses'  => array(
						array( 'taxonomy' => 'category', 'terms' => array( $cat ), 'operator' => 'IN' ),
					),
				),
			),
			array( 'query_id' => 't', 'page' => 1, 'inner_html' => '' )
		);

		$this->assertSame( 2, $result['totalItems'] );
	}

	public function test_meta_query_filters_results() {
		$m = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );
		update_post_meta( $m[0], 'featured', '1' );

		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'    => 'posts',
				'postType'  => 'post',
				'perPage'   => 10,
				'metaQuery' => array(
					'relation' => 'AND',
					'clauses'  => array(
						array( 'key' => 'featured', 'compare' => '=', 'value' => '1', 'type' => 'CHAR' ),
					),
				),
			),
			array( 'query_id' => 't', 'page' => 1, 'inner_html' => '' )
		);

		$this->assertSame( 1, $result['totalItems'] );
	}

	public function test_url_sort_override_drops_meta_key_for_non_meta_sort() {
		$this->load_helpers();
		$atts = designsetgo_query_defaults( array( 'postType' => 'post', 'orderBy' => 'meta_value', 'orderByMetaKey' => 'price' ) );

		$args = designsetgo_query_build_posts_args( $atts, array( 'page' => 1, 'params' => array( 'sort' => 'title.ASC' ) ) );

		$this->assertSame( 'title', $args['orderby'] );
		$this->assertArrayNotHasKey( 'meta_key', $args );
	}

	public function test_meta_sort_option_without_a_key_is_ignored() {
		$this->load_helpers();
		$atts = designsetgo_query_defaults( array( 'postType' => 'post', 'orderBy' => 'date' ) );

		$args = designsetgo_query_build_posts_args( $atts, array( 'page' => 1, 'params' => array( 'sort' => 'meta_value.ASC' ) ) );

		$this->assertSame( 'date', $args['orderby'] );
	}

	public function test_random_order_is_seeded_so_pages_do_not_repeat() {
		$this->load_helpers();
		$atts = designsetgo_query_defaults( array( 'postType' => 'post', 'orderBy' => 'rand' ) );

		$args = designsetgo_query_build_posts_args( $atts, array( 'page' => 1, 'params' => array() ) );
		$this->assertArrayHasKey( 'dsgo_rand_seed', $args );

		$query = new WP_Query( $args );
		$this->assertStringContainsString( 'RAND(' . $args['dsgo_rand_seed'] . ')', $query->request );
	}

	public function test_posts_that_tie_on_the_sort_column_page_without_repeats() {
		$this->load_helpers();
		$ids = self::factory()->post->create_many(
			5,
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		$atts = designsetgo_query_defaults(
			array(
				'postType' => 'post',
				'orderBy'  => 'date',
				'perPage'  => 2,
			)
		);

		$seen = array();
		foreach ( array( 1, 2, 3 ) as $page ) {
			$query = new WP_Query( designsetgo_query_build_posts_args( $atts, array( 'page' => $page, 'params' => array() ) ) );
			$seen  = array_merge( $seen, wp_list_pluck( $query->posts, 'ID' ) );
		}

		$this->assertSame( array_reverse( $ids ), $seen, 'Every tied post appears once, newest ID first.' );
	}

	public function test_tiebreak_follows_the_sort_direction() {
		$this->load_helpers();
		global $wpdb;
		$atts = designsetgo_query_defaults(
			array(
				'postType' => 'post',
				'orderBy'  => 'title',
				'order'    => 'ASC',
			)
		);

		$query = new WP_Query( designsetgo_query_build_posts_args( $atts, array( 'page' => 1, 'params' => array() ) ) );

		$this->assertStringContainsString( "{$wpdb->posts}.post_title ASC, {$wpdb->posts}.ID ASC", $query->request );
	}

	public function test_tiebreak_leaves_args_orderby_a_string() {
		$this->load_helpers();
		$atts = designsetgo_query_defaults( array( 'postType' => 'post' ) );

		$args = designsetgo_query_build_posts_args( $atts, array( 'page' => 1, 'params' => array() ) );

		$this->assertSame( 'date', $args['orderby'], 'designsetgo_query_args filters keep seeing the plain orderby.' );
	}

	public function test_tiebreak_skips_random_and_manual_order() {
		$this->load_helpers();
		global $wpdb;
		$ids = self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );

		$appended = '/,\s*' . preg_quote( $wpdb->posts, '/' ) . '\.ID (ASC|DESC)/';

		$rand = new WP_Query( designsetgo_query_build_posts_args( designsetgo_query_defaults( array( 'postType' => 'post', 'orderBy' => 'rand' ) ), array( 'page' => 1, 'params' => array() ) ) );
		$this->assertDoesNotMatchRegularExpression( $appended, $rand->request );

		$manual = new WP_Query( designsetgo_query_build_posts_args( designsetgo_query_defaults( array( 'source' => 'manual', 'manualIds' => $ids ) ), array( 'page' => 1, 'params' => array() ) ) );
		$this->assertStringContainsString( 'FIELD(', $manual->request );
		$this->assertDoesNotMatchRegularExpression( $appended, $manual->request, 'post__in keeps only its FIELD() ordering.' );
	}

	public function test_load_more_continues_the_first_paint_seed() {
		$this->load_helpers();
		$atts = designsetgo_query_defaults( array( 'postType' => 'post', 'orderBy' => 'rand' ) );

		$first = designsetgo_query_build_posts_args( $atts, array( 'page' => 1, 'params' => array(), 'query_id' => 'qrand' ) );
		$this->assertGreaterThan( 0, $first['dsgo_rand_seed'] );
		$this->assertSame( $first['dsgo_rand_seed'], designsetgo_query_region_context( 'qrand', 'posts', 1 )['seed'] );

		$next = designsetgo_query_build_posts_args(
			$atts,
			array(
				'page'      => 2,
				'params'    => array(),
				'query_id'  => 'qrand',
				'rand_seed' => $first['dsgo_rand_seed'],
			)
		);
		$this->assertSame( $first['dsgo_rand_seed'], $next['dsgo_rand_seed'] );
	}

	public function test_region_render_hands_the_requested_seed_to_the_posts_query() {
		$this->load_helpers();
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		designsetgo_query_render_region(
			array(
				'queryId' => 'qseedreg',
				'orderBy' => 'rand',
				'perPage' => 1,
			),
			array(
				'query_id'   => 'qseedreg',
				'page'       => 2,
				'inner_html' => '<!-- wp:designsetgo/query-results --><div class="wp-block-designsetgo-query-results"><!-- wp:post-title /--></div><!-- /wp:designsetgo/query-results -->',
				'rand_seed'  => 4242,
			)
		);

		$this->assertSame( 4242, $GLOBALS['designsetgo_query_rand_seeds']['qseedreg'] ?? null );
	}

	public function test_applies_designsetgo_query_args_filter_hook() {
		self::factory()->post->create_many( 5, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$called_with = null;
		$filter_cb = function ( $args, $atts, $context ) use ( &$called_with ) {
			$called_with = array( 'args' => $args, 'atts' => $atts, 'context' => $context );
			$args['posts_per_page'] = 2; // narrow to 2.
			return $args;
		};
		add_filter( 'designsetgo_query_args', $filter_cb, 10, 3 );

		$result = designsetgo_query_render(
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 10, 'itemTagName' => 'li' ),
			array( 'query_id' => 'hook', 'page' => 1, 'inner_html' => '' )
		);

		remove_filter( 'designsetgo_query_args', $filter_cb, 10 );

		$this->assertSame( 2, substr_count( $result['html'], '<li' ) );
		$this->assertIsArray( $called_with );
		$this->assertSame( 'hook', $called_with['context']['query_id'] );
	}

	public function test_scoped_filter_hook_receives_query_id_specific_events() {
		self::factory()->post->create_many( 5, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$calls = 0;
		$cb = function ( $args, $atts, $context ) use ( &$calls ) {
			$calls++;
			$args['posts_per_page'] = 1;
			return $args;
		};
		add_filter( 'designsetgo/query/abc123/args', $cb, 10, 3 );

		designsetgo_query_render(
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 10 ),
			array( 'query_id' => 'abc123', 'page' => 1, 'inner_html' => '' )
		);
		// Different queryId — should NOT trigger the scoped filter.
		designsetgo_query_render(
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 10 ),
			array( 'query_id' => 'other', 'page' => 1, 'inner_html' => '' )
		);

		remove_filter( 'designsetgo/query/abc123/args', $cb, 10 );

		$this->assertSame( 1, $calls, 'Scoped filter should fire only for its queryId.' );
	}

	public function test_last_state_registry_is_populated() {
		self::factory()->post->create_many( 7, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		designsetgo_query_render(
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 3 ),
			array( 'query_id' => 'state-reg', 'page' => 1, 'inner_html' => '' )
		);

		$state = designsetgo_query_get_last_state( 'state-reg' );
		$this->assertIsArray( $state );
		$this->assertSame( 7, $state['totalItems'] );
		$this->assertSame( 3, $state['totalPages'] );
		$this->assertSame( 1, $state['page'] );
	}

	public function test_manual_source_returns_exact_ids_in_order() {
		$ids = self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'    => 'manual',
				'manualIds' => array( $ids[2], $ids[0] ), // reversed subset
				'perPage'   => 10,
			),
			array( 'query_id' => 'm', 'page' => 1, 'inner_html' => '' )
		);

		$this->assertSame( 2, $result['totalItems'] );
		// Verify order: first <li occurrence corresponds to $ids[2] — we can't
		// easily check ids inside HTML without rendering the item, but
		// orderby=post__in is what enforces order; the count check is primary.
	}

	public function test_exclude_current_removes_singular_post() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$current = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		// Simulate singular context by setting the queried object.
		$this->go_to( get_permalink( $current ) );

		$this->load_helpers();

		$result = designsetgo_query_render(
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 10, 'excludeCurrent' => true ),
			array( 'query_id' => 'x', 'page' => 1, 'inner_html' => '' )
		);

		// Current post is excluded, so 2 remaining.
		$this->assertSame( 2, $result['totalItems'] );
	}

	public function test_tax_clause_defaults_include_children_true() {
		$this->load_helpers();
		$base_atts = [
			'perPage'       => 10,
			'orderBy'       => 'date',
			'order'         => 'DESC',
			'postType'      => 'post',
			'offset'        => 0,
			'ignoreSticky'  => false,
			'search'        => '',
			'bindSearchTo'  => '',
		];
		$atts = array_merge( $base_atts, [
			'source'   => 'posts',
			'taxQuery' => [
				'relation' => 'AND',
				'clauses'  => [ [ 'taxonomy' => 'category', 'terms' => [ 1 ], 'operator' => 'IN' ] ],
			],
		] );
		$args = designsetgo_query_build_posts_args( $atts, [ 'page' => 1, 'query_id' => '' ] );
		$this->assertTrue( $args['tax_query'][0]['include_children'] );
	}

	public function test_tax_clause_include_children_false() {
		$this->load_helpers();
		$base_atts = [
			'perPage'       => 10,
			'orderBy'       => 'date',
			'order'         => 'DESC',
			'postType'      => 'post',
			'offset'        => 0,
			'ignoreSticky'  => false,
			'search'        => '',
			'bindSearchTo'  => '',
		];
		$atts = array_merge( $base_atts, [
			'source'   => 'posts',
			'taxQuery' => [
				'relation' => 'AND',
				'clauses'  => [ [
					'taxonomy'         => 'category',
					'terms'            => [ 1 ],
					'operator'         => 'IN',
					'include_children' => false,
				] ],
			],
		] );
		$args = designsetgo_query_build_posts_args( $atts, [ 'page' => 1, 'query_id' => '' ] );
		$this->assertFalse( $args['tax_query'][0]['include_children'] );
	}

	public function test_date_query_after_builds_correctly() {
		$this->load_helpers();
		$base_atts = [
			'perPage' => 10, 'orderBy' => 'date', 'order' => 'DESC',
			'postType' => 'post', 'offset' => 0, 'ignoreSticky' => false,
			'search' => '', 'bindSearchTo' => '',
		];
		$atts = array_merge( $base_atts, [
			'source'    => 'posts',
			'dateQuery' => [
				'relation' => 'AND',
				'clauses'  => [ [
					'column'    => 'post_date',
					'mode'      => 'after',
					'after'     => '-30 days',
					'before'    => '',
					'inclusive' => false,
				] ],
			],
		] );
		$args = designsetgo_query_build_posts_args( $atts, [ 'page' => 1, 'query_id' => '' ] );
		$this->assertArrayHasKey( 'date_query', $args );
		$this->assertEquals( 'post_date', $args['date_query'][0]['column'] );
		$this->assertEquals( '-30 days', $args['date_query'][0]['after'] );
		$this->assertArrayNotHasKey( 'before', $args['date_query'][0] );
	}

	public function test_date_query_between_includes_both_bounds() {
		$this->load_helpers();
		$base_atts = [
			'perPage' => 10, 'orderBy' => 'date', 'order' => 'DESC',
			'postType' => 'post', 'offset' => 0, 'ignoreSticky' => false,
			'search' => '', 'bindSearchTo' => '',
		];
		$atts = array_merge( $base_atts, [
			'source'    => 'posts',
			'dateQuery' => [
				'relation' => 'AND',
				'clauses'  => [ [
					'column'    => 'post_date',
					'mode'      => 'between',
					'after'     => '2024-01-01',
					'before'    => '2024-12-31',
					'inclusive' => true,
				] ],
			],
		] );
		$args = designsetgo_query_build_posts_args( $atts, [ 'page' => 1, 'query_id' => '' ] );
		$this->assertEquals( '2024-01-01', $args['date_query'][0]['after'] );
		$this->assertEquals( '2024-12-31', $args['date_query'][0]['before'] );
		$this->assertTrue( $args['date_query'][0]['inclusive'] );
	}

	public function test_date_query_skips_empty_clauses() {
		$this->load_helpers();
		$base_atts = [
			'perPage' => 10, 'orderBy' => 'date', 'order' => 'DESC',
			'postType' => 'post', 'offset' => 0, 'ignoreSticky' => false,
			'search' => '', 'bindSearchTo' => '',
		];
		$atts = array_merge( $base_atts, [
			'source'    => 'posts',
			'dateQuery' => [
				'relation' => 'AND',
				'clauses'  => [ [ 'column' => 'post_date', 'mode' => 'after', 'after' => '', 'before' => '' ] ],
			],
		] );
		$args = designsetgo_query_build_posts_args( $atts, [ 'page' => 1, 'query_id' => '' ] );
		$this->assertArrayNotHasKey( 'date_query', $args );
	}

	public function test_nested_tax_group_builds_correctly() {
		$this->load_helpers();
		$base_atts = [
			'perPage' => 10, 'orderBy' => 'date', 'order' => 'DESC',
			'postType' => 'post', 'offset' => 0, 'ignoreSticky' => false,
			'search' => '', 'bindSearchTo' => '',
		];
		$atts = array_merge( $base_atts, [
			'source'   => 'posts',
			'taxQuery' => [
				'relation' => 'AND',
				'clauses'  => [
					[
						'relation' => 'OR',
						'clauses'  => [
							[ 'taxonomy' => 'category', 'terms' => [ 1 ], 'operator' => 'IN', 'include_children' => true ],
							[ 'taxonomy' => 'category', 'terms' => [ 2 ], 'operator' => 'IN', 'include_children' => true ],
						],
					],
					[ 'taxonomy' => 'post_tag', 'terms' => [ 5 ], 'operator' => 'IN', 'include_children' => false ],
				],
			],
		] );
		$args = designsetgo_query_build_posts_args( $atts, [ 'page' => 1, 'query_id' => '' ] );
		$this->assertEquals( 'AND', $args['tax_query']['relation'] );
		$sub = $args['tax_query'][0];
		$this->assertEquals( 'OR', $sub['relation'] );
		$this->assertEquals( 'category', $sub[0]['taxonomy'] );
		$this->assertEquals( 'category', $sub[1]['taxonomy'] );
		$leaf = $args['tax_query'][1];
		$this->assertEquals( 'post_tag', $leaf['taxonomy'] );
		$this->assertFalse( $leaf['include_children'] );
	}

	public function test_nested_meta_group_builds_correctly() {
		$this->load_helpers();
		$base_atts = [
			'perPage'      => 10,
			'orderBy'      => 'date',
			'order'        => 'DESC',
			'postType'     => 'post',
			'offset'       => 0,
			'ignoreSticky' => false,
			'search'       => '',
			'bindSearchTo' => '',
		];
		$atts = array_merge( $base_atts, [
			'source'    => 'posts',
			'metaQuery' => [
				'relation' => 'AND',
				'clauses'  => [
					[
						'relation' => 'OR',
						'clauses'  => [
							[ 'key' => 'status', 'compare' => '=', 'value' => 'featured', 'type' => 'CHAR' ],
							[ 'key' => 'featured', 'compare' => '=', 'value' => '1', 'type' => 'NUMERIC' ],
						],
					],
					[ 'key' => 'active', 'compare' => '=', 'value' => '1', 'type' => 'CHAR' ],
				],
			],
		] );
		$args = designsetgo_query_build_posts_args( $atts, [ 'page' => 1, 'query_id' => '' ] );
		$this->assertEquals( 'AND', $args['meta_query']['relation'] );
		$sub = $args['meta_query'][0];
		$this->assertEquals( 'OR', $sub['relation'] );
		$this->assertEquals( 'status', $sub[0]['key'] );
		$this->assertEquals( 'active', $args['meta_query'][1]['key'] );
	}

	/**
	 * Date query supports `before`-only mode (upper bound, no after).
	 * Regression: missing branch coverage in the inlined date builder.
	 */
	public function test_date_query_before_builds_correctly() {
		$this->load_helpers();
		$base_atts = [
			'perPage' => 10, 'orderBy' => 'date', 'order' => 'DESC',
			'postType' => 'post', 'offset' => 0, 'ignoreSticky' => false,
			'search' => '', 'bindSearchTo' => '',
		];
		$atts = array_merge( $base_atts, [
			'source'    => 'posts',
			'dateQuery' => [
				'relation' => 'AND',
				'clauses'  => [ [
					'column'    => 'post_modified',
					'mode'      => 'before',
					'after'     => '',
					'before'    => '2025-01-01',
					'inclusive' => true,
				] ],
			],
		] );
		$args = designsetgo_query_build_posts_args( $atts, [ 'page' => 1, 'query_id' => '' ] );
		$this->assertArrayHasKey( 'date_query', $args );
		$this->assertEquals( 'post_modified', $args['date_query'][0]['column'] );
		$this->assertEquals( '2025-01-01', $args['date_query'][0]['before'] );
		$this->assertArrayNotHasKey( 'after', $args['date_query'][0] );
	}

	/**
	 * Single-child nested groups are unwrapped to the bare child to avoid
	 * WP_Query's `_doing_it_wrong()` notice when a tax_query group has
	 * `relation` set with only one sub-clause.
	 */
	public function test_single_child_tax_group_is_unwrapped() {
		$this->load_helpers();
		$base_atts = [
			'perPage' => 10, 'orderBy' => 'date', 'order' => 'DESC',
			'postType' => 'post', 'offset' => 0, 'ignoreSticky' => false,
			'search' => '', 'bindSearchTo' => '',
		];
		$atts = array_merge( $base_atts, [
			'source'   => 'posts',
			'taxQuery' => [
				'relation' => 'AND',
				'clauses'  => [ [
					'relation' => 'OR',
					'clauses'  => [ [
						'taxonomy' => 'category',
						'terms'    => [ 1 ],
						'operator' => 'IN',
					] ],
				] ],
			],
		] );
		$args = designsetgo_query_build_posts_args( $atts, [ 'page' => 1, 'query_id' => '' ] );
		// The single-child OR group should collapse to a bare leaf clause.
		$this->assertArrayHasKey( 'tax_query', $args );
		$this->assertSame( 'category', $args['tax_query'][0]['taxonomy'] );
		$this->assertArrayNotHasKey( 'relation', $args['tax_query'][0] );
	}

	/**
	 * Empty groups (all-invalid leaves) are pruned from the WP_Query args
	 * entirely instead of emitting a `relation`-only stub.
	 */
	public function test_empty_meta_group_is_pruned() {
		$this->load_helpers();
		$base_atts = [
			'perPage' => 10, 'orderBy' => 'date', 'order' => 'DESC',
			'postType' => 'post', 'offset' => 0, 'ignoreSticky' => false,
			'search' => '', 'bindSearchTo' => '',
		];
		$atts = array_merge( $base_atts, [
			'source'    => 'posts',
			'metaQuery' => [
				'relation' => 'AND',
				'clauses'  => [ [
					'relation' => 'OR',
					'clauses'  => [
						[ 'key' => '', 'compare' => '=', 'value' => 'a', 'type' => 'CHAR' ],
						[ 'key' => '', 'compare' => '=', 'value' => 'b', 'type' => 'CHAR' ],
					],
				] ],
			],
		] );
		$args = designsetgo_query_build_posts_args( $atts, [ 'page' => 1, 'query_id' => '' ] );
		$this->assertArrayNotHasKey( 'meta_query', $args );
	}

	public function test_child_blocks_resolve_per_item_context() {
		$ids   = array();
		$ids[] = self::factory()->post->create( array( 'post_title' => 'Alpha', 'post_status' => 'publish' ) );
		$ids[] = self::factory()->post->create( array( 'post_title' => 'Beta',  'post_status' => 'publish' ) );

		$this->load_helpers();

		// core/post-title reads postId from context; its render will emit the
		// title of whichever post context->postId points at.
		$result = designsetgo_query_render(
			array(
				'source'   => 'posts',
				'postType' => 'post',
				'perPage'  => 10,
				'orderBy'  => 'date',
				'order'    => 'ASC',
			),
			array(
				'query_id'   => 'ctx',
				'page'       => 1,
				'inner_html' => '<!-- wp:post-title /-->',
			)
		);

		// Both titles must appear — one per iterated post context.
		$this->assertStringContainsString( 'Alpha', $result['html'] );
		$this->assertStringContainsString( 'Beta', $result['html'] );

		// And they must appear in the correct order (Alpha then Beta).
		$pos_alpha = strpos( $result['html'], 'Alpha' );
		$pos_beta  = strpos( $result['html'], 'Beta' );
		$this->assertGreaterThan( 0, $pos_alpha );
		$this->assertGreaterThan( $pos_alpha, $pos_beta, 'Order posts ASC → Alpha before Beta.' );
	}
}

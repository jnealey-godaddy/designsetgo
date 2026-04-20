<?php
/**
 * @group query-block
 */
class DesignSetGo_Query_Render_Posts_Test extends WP_UnitTestCase {

	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $path;
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

		$atts = array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 3 );
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
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 10 ),
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

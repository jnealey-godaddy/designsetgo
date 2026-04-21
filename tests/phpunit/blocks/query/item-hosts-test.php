<?php
/**
 * Query item host registry + items_html stash contract.
 *
 * @group query-block
 */
class DesignSetGo_Query_Item_Hosts_Test extends WP_UnitTestCase {

	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $path;
	}

	public function test_registry_defaults_to_query_results() {
		$this->load_helpers();

		$hosts = designsetgo_query_item_host_block_names();
		$this->assertIsArray( $hosts );
		$this->assertContains( 'designsetgo/query-results', $hosts );
	}

	public function test_filter_adds_block_name_to_registry() {
		$this->load_helpers();

		$cb = static function ( $hosts ) {
			$hosts[] = 'designsetgo/slider';
			return $hosts;
		};
		add_filter( 'designsetgo_query_item_host_block_names', $cb );

		$hosts = designsetgo_query_item_host_block_names();
		$this->assertContains( 'designsetgo/slider', $hosts );
		$this->assertContains( 'designsetgo/query-results', $hosts );

		remove_filter( 'designsetgo_query_item_host_block_names', $cb );
	}

	public function test_render_posts_returns_items_html_without_outer_wrap() {
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'      => 'posts',
				'postType'    => 'post',
				'perPage'     => 3,
				'tagName'     => 'ul',
				'itemTagName' => 'li',
			),
			array(
				'query_id'   => 'items-html-posts',
				'page'       => 1,
				'inner_html' => '<!-- wp:paragraph --><p>Item</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertArrayHasKey( 'items_html', $result );
		$this->assertIsString( $result['items_html'] );
		// Raw items should include the per-item <li> wrappers but NOT the
		// outer grid <ul class="dsgo-query-results">.
		$this->assertSame( 3, substr_count( $result['items_html'], '<li' ) );
		$this->assertStringNotContainsString( 'dsgo-query-results', $result['items_html'] );
	}

	public function test_render_users_returns_items_html_field() {
		self::factory()->user->create_many( 2 );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'      => 'users',
				'perPage'     => 10,
				'tagName'     => 'ul',
				'itemTagName' => 'li',
			),
			array(
				'query_id'   => 'items-html-users',
				'page'       => 1,
				'inner_html' => '<!-- wp:paragraph --><p>User</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertArrayHasKey( 'items_html', $result );
		$this->assertIsString( $result['items_html'] );
		$this->assertStringNotContainsString( 'dsgo-query-results', $result['items_html'] );
	}

	public function test_render_terms_returns_items_html_field() {
		self::factory()->term->create_many( 2, array( 'taxonomy' => 'category' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'      => 'terms',
				'perPage'     => 10,
				'tagName'     => 'ul',
				'itemTagName' => 'li',
				'taxQuery'    => array(
					'relation' => 'AND',
					'clauses'  => array( array( 'taxonomy' => 'category' ) ),
				),
			),
			array(
				'query_id'   => 'items-html-terms',
				'page'       => 1,
				'inner_html' => '<!-- wp:paragraph --><p>Term</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertArrayHasKey( 'items_html', $result );
		$this->assertIsString( $result['items_html'] );
		$this->assertStringNotContainsString( 'dsgo-query-results', $result['items_html'] );
	}

	public function test_empty_source_fallback_returns_items_html_empty_string() {
		$this->load_helpers();

		$result = designsetgo_query_render(
			array( 'source' => 'nonexistent-source' ),
			array(
				'query_id'   => 'empty',
				'page'       => 1,
				'inner_html' => '',
			)
		);

		$this->assertArrayHasKey( 'items_html', $result );
		$this->assertSame( '', $result['items_html'] );
	}
}

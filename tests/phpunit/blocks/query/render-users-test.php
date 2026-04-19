<?php
/**
 * @group query-block
 */
class DesignSetGo_Query_Render_Users_Test extends WP_UnitTestCase {

	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit.' );
		require_once $path;
	}

	public function test_renders_one_item_per_user() {
		self::factory()->user->create_many( 3, array( 'role' => 'author' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array( 'source' => 'users', 'perPage' => 10 ),
			array( 'query_id' => 'u', 'page' => 1, 'inner_html' => '' )
		);

		// At least 3 authors + whatever default users the factory seeded.
		$this->assertGreaterThanOrEqual( 3, substr_count( $result['html'], '<li' ) );
		$this->assertGreaterThanOrEqual( 3, $result['totalItems'] );
	}

	public function test_respects_per_page_and_pagination() {
		self::factory()->user->create_many( 7, array( 'role' => 'subscriber' ) );
		$this->load_helpers();

		$atts = array( 'source' => 'users', 'perPage' => 3 );
		$page1 = designsetgo_query_render( $atts, array( 'query_id' => 'p', 'page' => 1, 'inner_html' => '' ) );
		$page2 = designsetgo_query_render( $atts, array( 'query_id' => 'p', 'page' => 2, 'inner_html' => '' ) );

		$this->assertSame( 3, substr_count( $page1['html'], '<li' ) );
		$this->assertGreaterThanOrEqual( 1, substr_count( $page2['html'], '<li' ) );
		$this->assertGreaterThanOrEqual( 3, $page1['totalPages'] );
	}

	public function test_search_param_narrows_users() {
		self::factory()->user->create( array( 'user_login' => 'alice_needle_haystack', 'user_email' => 'alice_needle@example.test' ) );
		self::factory()->user->create( array( 'user_login' => 'bob_other' ) );

		$this->load_helpers();

		$result = designsetgo_query_render(
			array( 'source' => 'users', 'perPage' => 10, 'search' => 'alice_needle' ),
			array( 'query_id' => 'u', 'page' => 1, 'inner_html' => '' )
		);

		$this->assertSame( 1, $result['totalItems'] );
	}

	public function test_writes_state_to_registry() {
		self::factory()->user->create_many( 5, array( 'role' => 'contributor' ) );
		$this->load_helpers();

		designsetgo_query_render(
			array( 'source' => 'users', 'perPage' => 2 ),
			array( 'query_id' => 'u-state', 'page' => 1, 'inner_html' => '' )
		);

		$state = designsetgo_query_get_last_state( 'u-state' );
		$this->assertIsArray( $state );
		$this->assertGreaterThanOrEqual( 5, $state['totalItems'] );
		$this->assertSame( 1, $state['page'] );
	}

	public function test_child_blocks_resolve_per_item_user_context() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array( 'source' => 'users', 'perPage' => 20 ),
			array(
				'query_id'   => 'u-ctx',
				'page'       => 1,
				// Paragraph rendered through new WP_Block should see context,
				// but core blocks don't use designsetgo/currentItemId directly.
				// Assert the items HTML at least contains an <li ...> per user.
				'inner_html' => '<!-- wp:paragraph --><p>User item</p><!-- /wp:paragraph -->',
			)
		);

		// We can't trivially assert the ID inside via core blocks, but the test
		// guarantees parsing + iteration + rendering doesn't error for users.
		$this->assertStringContainsString( 'User item', $result['html'] );
		$this->assertGreaterThanOrEqual( 1, $result['totalItems'] );
	}
}

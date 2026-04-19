<?php
/**
 * @group query-block
 */
class DesignSetGo_Query_Render_Terms_Test extends WP_UnitTestCase {

	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit.' );
		require_once $path;
	}

	public function test_renders_one_item_per_term() {
		self::factory()->term->create_many( 4, array( 'taxonomy' => 'category' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'   => 'terms',
				'perPage'  => 20,
				'taxQuery' => array( 'relation' => 'AND', 'clauses' => array( array( 'taxonomy' => 'category' ) ) ),
			),
			array( 'query_id' => 't', 'page' => 1, 'inner_html' => '' )
		);

		// 4 new categories + 1 seeded "Uncategorized" = at least 4.
		$this->assertGreaterThanOrEqual( 4, substr_count( $result['html'], '<li' ) );
		$this->assertGreaterThanOrEqual( 4, $result['totalItems'] );
	}

	public function test_defaults_to_category_when_no_taxonomy_specified() {
		self::factory()->term->create_many( 3, array( 'taxonomy' => 'category' ) );
		$this->load_helpers();

		// No taxQuery clause — should still work by defaulting to 'category'.
		$result = designsetgo_query_render(
			array( 'source' => 'terms', 'perPage' => 20 ),
			array( 'query_id' => 'tdefault', 'page' => 1, 'inner_html' => '' )
		);

		$this->assertGreaterThanOrEqual( 3, $result['totalItems'] );
	}

	public function test_respects_per_page_and_pagination() {
		self::factory()->term->create_many( 7, array( 'taxonomy' => 'category' ) );
		$this->load_helpers();

		$atts = array(
			'source'   => 'terms',
			'perPage'  => 3,
			'taxQuery' => array( 'relation' => 'AND', 'clauses' => array( array( 'taxonomy' => 'category' ) ) ),
		);
		$page1 = designsetgo_query_render( $atts, array( 'query_id' => 'tp', 'page' => 1, 'inner_html' => '' ) );
		$page2 = designsetgo_query_render( $atts, array( 'query_id' => 'tp', 'page' => 2, 'inner_html' => '' ) );

		$this->assertSame( 3, substr_count( $page1['html'], '<li' ) );
		$this->assertGreaterThanOrEqual( 1, substr_count( $page2['html'], '<li' ) );
	}

	public function test_writes_state_to_registry() {
		self::factory()->term->create_many( 5, array( 'taxonomy' => 'post_tag' ) );
		$this->load_helpers();

		designsetgo_query_render(
			array(
				'source'   => 'terms',
				'perPage'  => 2,
				'taxQuery' => array( 'relation' => 'AND', 'clauses' => array( array( 'taxonomy' => 'post_tag' ) ) ),
			),
			array( 'query_id' => 't-state', 'page' => 1, 'inner_html' => '' )
		);

		$state = designsetgo_query_get_last_state( 't-state' );
		$this->assertIsArray( $state );
		$this->assertGreaterThanOrEqual( 5, $state['totalItems'] );
		$this->assertSame( 1, $state['page'] );
	}

	public function test_custom_taxonomy_is_honored() {
		register_taxonomy( 'dsgo_test_tax', 'post', array( 'public' => true ) );
		self::factory()->term->create_many( 2, array( 'taxonomy' => 'dsgo_test_tax' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'   => 'terms',
				'perPage'  => 20,
				'taxQuery' => array( 'relation' => 'AND', 'clauses' => array( array( 'taxonomy' => 'dsgo_test_tax' ) ) ),
			),
			array( 'query_id' => 'tx', 'page' => 1, 'inner_html' => '' )
		);

		$this->assertSame( 2, $result['totalItems'] );

		unregister_taxonomy( 'dsgo_test_tax' );
	}
}

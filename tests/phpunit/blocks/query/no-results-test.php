<?php
/**
 * @group query-block
 */
class DesignSetGo_Query_No_Results_Test extends WP_UnitTestCase {

	private function load_helpers() {
		$p = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $p );
		require_once $p;
	}

	public function test_state_registry_reports_zero_when_no_posts_match() {
		// No posts with the 'nonexistent' tag.
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$tag = self::factory()->tag->create( array( 'slug' => 'nonexistent' ) );
		// Don't assign anything to this tag.

		designsetgo_query_render(
			array(
				'source'   => 'posts',
				'postType' => 'post',
				'perPage'  => 10,
				'taxQuery' => array(
					'relation' => 'AND',
					'clauses'  => array(
						array( 'taxonomy' => 'post_tag', 'terms' => array( $tag ), 'operator' => 'IN' ),
					),
				),
			),
			array( 'query_id' => 'nr', 'page' => 1, 'inner_html' => '' )
		);

		$state = designsetgo_query_get_last_state( 'nr' );
		$this->assertIsArray( $state );
		$this->assertSame( 0, $state['totalItems'] );
	}

	public function test_state_registry_reports_positive_when_posts_match() {
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		designsetgo_query_render(
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 10 ),
			array( 'query_id' => 'nr2', 'page' => 1, 'inner_html' => '' )
		);

		$state = designsetgo_query_get_last_state( 'nr2' );
		$this->assertGreaterThan( 0, $state['totalItems'] );
	}
}

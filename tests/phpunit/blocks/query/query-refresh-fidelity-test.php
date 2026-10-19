<?php
/**
 * A refreshed Query must match its first paint.
 *
 * Covers: page facts (exclude current, Current source) replayed on REST
 * refresh, offset-aware totals, comma slug lists, hierarchical filter counts,
 * Woo params reaching a refresh, visitor nonces and refresh-source writes.
 *
 * Loads from build/ (as production does): run `npm run build` first.
 *
 * @package DesignSetGo
 * @subpackage Tests
 * @group query-block
 */

use DesignSetGo\Blocks\Query\FilterIndex;
use DesignSetGo\Blocks\Query\FilterRegistry;
use DesignSetGo\Blocks\Query\RefreshSource;

/**
 * Refresh-fidelity tests.
 */
class DesignSetGo_Query_Refresh_Fidelity_Test extends WP_UnitTestCase {

	/**
	 * Load the render helpers from build/.
	 */
	public function set_up(): void {
		parent::set_up();
		$file = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $file, 'Run npm run build first.' );
		require_once $file;
		do_action( 'rest_api_init' );
	}

	/**
	 * Clean up request state.
	 */
	public function tear_down(): void {
		global $wpdb;
		$_GET = array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $GLOBALS['designsetgo_query_request_context'] );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'dsgo_query_filter_index' );
		delete_option( FilterIndex::OPTION_SCHEMA );
		delete_option( FilterRegistry::OPTION );
		FilterIndex::reset_table_cache();
		parent::tear_down();
	}

	/**
	 * Query markup listing post titles.
	 *
	 * @param string $query_id Query ID.
	 * @param array  $attrs    Extra block attributes.
	 * @return string Block markup.
	 */
	private function query_markup( $query_id, array $attrs = array() ) {
		$attrs = array_merge( array( 'queryId' => $query_id, 'perPage' => 20 ), $attrs );

		return '<!-- wp:designsetgo/query ' . wp_json_encode( $attrs ) . ' -->'
			. '<!-- wp:designsetgo/query-results --><!-- wp:post-title /--><!-- /wp:designsetgo/query-results -->'
			. '<!-- /wp:designsetgo/query -->';
	}

	/**
	 * Render a post's content as its singular page does.
	 *
	 * @param WP_Post $post Post.
	 * @return string HTML.
	 */
	private function render_as_page( $post ) {
		$this->go_to( get_permalink( $post ) );
		$GLOBALS['post'] = $post;
		setup_postdata( $post );
		$html = apply_filters( 'the_content', $post->post_content );
		wp_reset_postdata();

		return $html;
	}

	/**
	 * The signed refresh source a rendered region carries.
	 *
	 * @param string $html     Rendered HTML.
	 * @param string $query_id Query ID.
	 * @return array{source: string, signature: string}
	 */
	private function refresh_source( $html, $query_id ) {
		$pattern = '#data-dsgo-blobs-for="' . preg_quote( $query_id, '#' ) . '" data-dsgo-refresh-source="([A-Za-z0-9+/=]+)" data-dsgo-signature="([a-f0-9]{64})"#';
		$this->assertSame( 1, preg_match( $pattern, $html, $match ) );

		return array(
			'source'    => $match[1],
			'signature' => $match[2],
		);
	}

	/**
	 * Call the public refresh route.
	 *
	 * @param string $query_id Query ID.
	 * @param array  $source   Signed source.
	 * @param array  $params   URL params.
	 * @return array Response data.
	 */
	private function refresh( $query_id, array $source, array $params = array() ) {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
		$request->set_param( 'queryId', $query_id );
		$request->set_param( 'source', $source['source'] );
		$request->set_param( 'signature', $source['signature'] );
		$request->set_param( 'page', 1 );
		$request->set_param( 'params', $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		return $response->get_data();
	}

	public function test_exclude_current_survives_a_refresh() {
		self::factory()->post->create( array( 'post_title' => 'Sibling Alpha' ) );
		$page = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Host Page',
				'post_content' => $this->query_markup( 'qexcl', array( 'postType' => 'page', 'excludeCurrent' => true ) ),
			)
		);
		self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Other Page' ) );

		$html = $this->render_as_page( $page );
		$this->assertStringNotContainsString( 'Host Page</a>', $html );
		$this->assertStringContainsString( 'Other Page', $html );

		// The refresh runs on a request that is not that page.
		$this->go_to( home_url( '/' ) );
		$data = $this->refresh( 'qexcl', $this->refresh_source( $html, 'qexcl' ) );

		$this->assertStringNotContainsString( 'Host Page</a>', $data['html'] );
		$this->assertStringContainsString( 'Other Page', $data['html'] );
	}

	public function test_current_source_keeps_its_archive_on_refresh() {
		$cat = self::factory()->category->create( array( 'name' => 'Archive Cat', 'slug' => 'archive-cat' ) );
		self::factory()->post->create( array( 'post_title' => 'In The Archive', 'post_category' => array( $cat ) ) );
		self::factory()->post->create( array( 'post_title' => 'Outside The Archive' ) );

		$this->go_to( get_category_link( $cat ) );
		$html = do_blocks( $this->query_markup( 'qcurr', array( 'source' => 'current' ) ) );
		$this->assertStringContainsString( 'In The Archive', $html );
		$this->assertStringNotContainsString( 'Outside The Archive', $html );

		$this->go_to( home_url( '/' ) );
		$data = $this->refresh( 'qcurr', $this->refresh_source( $html, 'qcurr' ) );

		$this->assertStringContainsString( 'In The Archive', $data['html'] );
		$this->assertStringNotContainsString( 'Outside The Archive', $data['html'] );
	}

	public function test_page_facts_are_not_stored_for_queries_that_ignore_them() {
		$page = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_content' => $this->query_markup( 'qplain', array( 'postType' => 'page' ) ),
			)
		);
		$html = $this->render_as_page( $page );
		$ref  = json_decode( base64_decode( $this->refresh_source( $html, 'qplain' )['source'] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		$definition = get_transient( 'dsgo_query_source_' . $ref['ref'] );
		$this->assertIsArray( $definition );
		$this->assertArrayNotHasKey( 'pageContext', $definition );
	}

	public function test_offset_is_subtracted_from_totals() {
		self::factory()->post->create_many( 5, array( 'post_status' => 'publish' ) );
		$atts = array(
			'source'      => 'posts',
			'postType'    => 'post',
			'perPage'     => 2,
			'offset'      => 1,
			'itemTagName' => 'li',
		);

		$result = designsetgo_query_render( $atts, array( 'query_id' => 'off', 'page' => 1, 'inner_html' => '' ) );
		$this->assertSame( 4, $result['totalItems'] );
		$this->assertSame( 2, $result['totalPages'] );

		$atts['offset'] = 5;
		$result         = designsetgo_query_render( $atts, array( 'query_id' => 'off', 'page' => 1, 'inner_html' => '' ) );
		$this->assertSame( 0, $result['totalItems'] );
		$this->assertSame( 0, $result['totalPages'] );

		$atts['offset'] = 9;
		$result         = designsetgo_query_render( $atts, array( 'query_id' => 'off', 'page' => 1, 'inner_html' => '' ) );
		$this->assertSame( 0, $result['totalItems'] );
	}

	public function test_paginate_totals_helper() {
		$this->assertSame( array( 'totalItems' => 7, 'totalPages' => 3 ), designsetgo_query_paginate_totals( 10, 3, 3 ) );
		$this->assertSame( array( 'totalItems' => 0, 'totalPages' => 0 ), designsetgo_query_paginate_totals( 2, 5, 3 ) );
		$this->assertSame( array( 'totalItems' => 4, 'totalPages' => 1 ), designsetgo_query_paginate_totals( 4, 0, -1 ) );
	}

	public function test_comma_separated_filter_value_matches_each_slug() {
		$news   = self::factory()->category->create( array( 'name' => 'News', 'slug' => 'news' ) );
		$events = self::factory()->category->create( array( 'name' => 'Events', 'slug' => 'events' ) );
		self::factory()->post->create( array( 'post_title' => 'A News Item', 'post_category' => array( $news ) ) );
		self::factory()->post->create( array( 'post_title' => 'An Events Item', 'post_category' => array( $events ) ) );
		self::factory()->post->create( array( 'post_title' => 'A Neither Item' ) );

		$result = designsetgo_query_render(
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 10, 'itemTagName' => 'li' ),
			array(
				'query_id'   => 'comma',
				'page'       => 1,
				'inner_html' => '<!-- wp:post-title /-->',
				'params'     => array( 'filter_category' => 'news,events' ),
			)
		);

		$this->assertSame( 2, $result['totalItems'] );
		$this->assertStringContainsString( 'A News Item', $result['html'] );
		$this->assertStringContainsString( 'An Events Item', $result['html'] );
		$this->assertStringNotContainsString( 'A Neither Item', $result['html'] );
	}

	public function test_parent_term_count_includes_posts_in_children() {
		FilterIndex::install();
		FilterRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		$parent  = self::factory()->category->create( array( 'name' => 'Parent', 'slug' => 'parent' ) );
		$child_a = self::factory()->category->create( array( 'name' => 'Child A', 'slug' => 'child-a', 'parent' => $parent ) );
		$child_b = self::factory()->category->create( array( 'name' => 'Child B', 'slug' => 'child-b', 'parent' => $parent ) );

		$only_child = self::factory()->post->create( array( 'post_category' => array( $child_a ) ) );
		$both       = self::factory()->post->create( array( 'post_category' => array( $child_a, $child_b ) ) );
		$direct     = self::factory()->post->create( array( 'post_category' => array( $parent ) ) );
		foreach ( array( $only_child, $both, $direct ) as $pid ) {
			FilterIndex::reindex_object( 'post', $pid );
		}

		$counts = FilterIndex::count_for_options( 'category', array( $parent, $child_a, $child_b ), array() );

		// What the query returns for filter_category=parent (include_children).
		$query = new WP_Query(
			array(
				'post_type'   => 'post',
				'fields'      => 'ids',
				'tax_query'   => array( array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => array( 'parent' ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'nopaging'    => true,
			)
		);
		$this->assertSame( 3, count( $query->posts ) );
		$this->assertSame( 3, $counts[ (string) $parent ] );
		$this->assertSame( 2, $counts[ (string) $child_a ] );
		$this->assertSame( 1, $counts[ (string) $child_b ] );
	}

	public function test_active_parent_filter_includes_child_posts_in_other_counts() {
		FilterIndex::install();
		FilterRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		FilterRegistry::register( 'post_tag', array( 'type' => 'taxonomy', 'source' => 'post_tag' ) );
		$parent = self::factory()->category->create( array( 'name' => 'Parent', 'slug' => 'parent' ) );
		$child  = self::factory()->category->create( array( 'name' => 'Child', 'slug' => 'child', 'parent' => $parent ) );
		$tag    = self::factory()->tag->create( array( 'name' => 'Hot', 'slug' => 'hot' ) );

		$pid = self::factory()->post->create( array( 'post_category' => array( $child ) ) );
		wp_set_post_tags( $pid, array( 'hot' ) );
		FilterIndex::reindex_object( 'post', $pid );

		$counts = FilterIndex::count_for_options( 'post_tag', array( $tag ), array( 'category' => array( (string) $parent ) ) );

		$this->assertSame( 1, $counts[ (string) $tag ] );
	}

	public function test_rest_nonce_is_empty_for_visitors() {
		wp_set_current_user( 0 );
		$this->assertSame( '', designsetgo_query_rest_nonce() );

		$markup = '<!-- wp:designsetgo/query {"queryId":"qnonce","perPage":2} -->'
			. '<!-- wp:designsetgo/query-filter {"filterType":"search"} /-->'
			. '<!-- wp:designsetgo/query-results --><!-- wp:post-title /--><!-- /wp:designsetgo/query-results -->'
			. '<!-- wp:designsetgo/query-pagination {"paginationKind":"loadmore"} /-->'
			. '<!-- /wp:designsetgo/query -->';

		$html = do_blocks( $markup );
		$this->assertGreaterThan( 0, preg_match_all( '/"nonce":"([^"]*)"/', $html, $m ), 'Expected IAPI contexts carrying a nonce key.' );
		foreach ( $m[1] as $nonce ) {
			$this->assertSame( '', $nonce, 'A visitor must not be handed a REST nonce.' );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertNotSame( '', designsetgo_query_rest_nonce() );
	}

	public function test_refresh_source_is_not_rewritten_on_every_view() {
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'Transients are not stored in options with an object cache.' );
		}
		$source = RefreshSource::sign( 'qwrite', array( 'queryId' => 'qwrite' ), '', 0 );
		$ref    = json_decode( base64_decode( $source['source'] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$key    = '_transient_timeout_dsgo_query_source_' . $ref['ref'];

		// Plenty of lifetime left: a new view leaves the record alone.
		$fresh = time() + (int) ( RefreshSource::LIFETIME * 0.9 );
		update_option( $key, $fresh );
		RefreshSource::sign( 'qwrite', array( 'queryId' => 'qwrite' ), '', 0 );
		$this->assertSame( $fresh, (int) get_option( $key ) );

		// Less than half left: it is renewed.
		update_option( $key, time() + (int) ( RefreshSource::LIFETIME * 0.25 ) );
		RefreshSource::sign( 'qwrite', array( 'queryId' => 'qwrite' ), '', 0 );
		$this->assertGreaterThan( time() + (int) ( RefreshSource::LIFETIME * 0.9 ), (int) get_option( $key ) );

		// Gone: it is written again and still verifies.
		delete_transient( 'dsgo_query_source_' . $ref['ref'] );
		$again = RefreshSource::sign( 'qwrite', array( 'queryId' => 'qwrite' ), '', 0 );
		$this->assertNotNull( RefreshSource::verify( $again['source'], $again['signature'], 'qwrite' ) );
	}

	public function test_woo_filter_params_reach_a_refresh() {
		self::factory()->post->create( array( 'post_title' => 'Anything' ) );
		$seen = array();
		add_filter(
			'designsetgo_query_args',
			static function ( $args ) use ( &$seen ) {
				$seen = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return $args;
			}
		);

		$html = do_blocks( $this->query_markup( 'qwoo' ) );
		$this->refresh(
			'qwoo',
			$this->refresh_source( $html, 'qwoo' ),
			array(
				'min_price'        => '10',
				'max_price'        => '50',
				'rating_filter'    => '4',
				'query_type_color' => 'and',
				'filter_color'     => 'red',
				'unrelated'        => 'x',
			)
		);

		$this->assertSame( '10', $seen['min_price'] ?? null );
		$this->assertSame( '50', $seen['max_price'] ?? null );
		$this->assertSame( '4', $seen['rating_filter'] ?? null );
		$this->assertSame( 'and', $seen['query_type_color'] ?? null );
		$this->assertArrayNotHasKey( 'unrelated', $seen );
	}
}

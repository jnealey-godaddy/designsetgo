<?php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_UnitTestCase;

class GroupByTest extends WP_UnitTestCase {

	public function test_partitions_by_taxonomy() {
		$news_term   = self::factory()->term->create( array( 'taxonomy' => 'category', 'slug' => 'news' ) );
		$events_term = self::factory()->term->create( array( 'taxonomy' => 'category', 'slug' => 'events' ) );

		$news_post_ids = self::factory()->post->create_many( 2 );
		foreach ( $news_post_ids as $pid ) wp_set_post_terms( $pid, array( $news_term ), 'category' );

		$events_post_ids = self::factory()->post->create_many( 1 );
		wp_set_post_terms( $events_post_ids[0], array( $events_term ), 'category' );

		require_once DESIGNSETGO_PATH . 'src/blocks/query/render-helpers.php';

		$header_html = '<!-- wp:designsetgo/query-group-header -->'
					  . '<div class="wp-block-designsetgo-query-group-header"><h3>Group</h3></div>'
					  . '<!-- /wp:designsetgo/query-group-header -->';
		$inner       = $header_html . '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->';

		$html = designsetgo_query_render(
			array(
				'source'      => 'posts',
				'postType'    => 'post',
				'perPage'     => 10,
				'tagName'     => 'ul',
				'itemTagName' => 'li',
				'groupBy'     => array( 'field' => 'taxonomy', 'key' => 'category' ),
			),
			array( 'query_id' => 'g1', 'page' => 1, 'inner_html' => $inner, 'params' => array() )
		)['html'];

		// Two groups → two headers in output.
		$this->assertSame( 2, substr_count( $html, 'dsgo-query-group-header' ) );
	}
}

<?php
// tests/integration/blocks/query/RelationshipRenderTest.php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_UnitTestCase;

class RelationshipRenderTest extends WP_UnitTestCase {

	public function test_resolves_parent_meta_to_post_ids() {
		$parent  = self::factory()->post->create( array( 'post_title' => 'Parent' ) );
		$child_a = self::factory()->post->create( array( 'post_title' => 'Child A' ) );
		$child_b = self::factory()->post->create( array( 'post_title' => 'Child B' ) );
		update_post_meta( $parent, 'related_posts', array( $child_a, $child_b ) );

		require_once DESIGNSETGO_PATH . 'src/blocks/query/render-helpers.php';

		$result = designsetgo_query_render(
			array(
				'source'            => 'relationship',
				'relationshipField' => 'related_posts',
				'postType'          => 'post',
				'perPage'           => 10,
				'tagName'           => 'ul',
				'itemTagName'       => 'li',
			),
			array(
				'query_id'     => 'rel-1',
				'page'         => 1,
				'inner_html'   => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->',
				'params'       => array(),
				'parent_stack' => array(
					array( 'postId' => $parent, 'postType' => 'post' ),
				),
			)
		);

		$this->assertSame( 2, $result['totalItems'] );
		$this->assertStringContainsString( 'dsgo-query__item', $result['html'] );
	}

	public function test_returns_empty_when_parent_stack_missing() {
		$result = designsetgo_query_render(
			array( 'source' => 'relationship', 'relationshipField' => 'x' ),
			array( 'query_id' => 'rel-2', 'page' => 1, 'inner_html' => '', 'params' => array() )
		);
		$this->assertSame( 0, $result['totalItems'] );
		$this->assertSame( '', trim( wp_strip_all_tags( $result['html'] ) ) );
	}
}

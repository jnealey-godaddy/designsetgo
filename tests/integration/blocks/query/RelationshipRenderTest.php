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

	public function test_relationship_honors_per_page_and_total_pages() {
		$parent   = self::factory()->post->create( array( 'post_title' => 'Parent' ) );
		$children = self::factory()->post->create_many( 3 );
		update_post_meta( $parent, 'related_posts', $children );

		require_once DESIGNSETGO_PATH . 'src/blocks/query/render-helpers.php';

		$page_one = designsetgo_query_render(
			array(
				'source'            => 'relationship',
				'relationshipField' => 'related_posts',
				'postType'          => 'post',
				'perPage'           => 2,
				'tagName'           => 'ul',
				'itemTagName'       => 'li',
			),
			array(
				'query_id'     => 'rel-paginated',
				'page'         => 1,
				'inner_html'   => '<!-- wp:paragraph --><p>child</p><!-- /wp:paragraph -->',
				'params'       => array(),
				'parent_stack' => array(
					array( 'postId' => $parent, 'postType' => 'post' ),
				),
			)
		);

		$page_two = designsetgo_query_render(
			array(
				'source'            => 'relationship',
				'relationshipField' => 'related_posts',
				'postType'          => 'post',
				'perPage'           => 2,
				'tagName'           => 'ul',
				'itemTagName'       => 'li',
			),
			array(
				'query_id'     => 'rel-paginated',
				'page'         => 2,
				'inner_html'   => '<!-- wp:paragraph --><p>child</p><!-- /wp:paragraph -->',
				'params'       => array(),
				'parent_stack' => array(
					array( 'postId' => $parent, 'postType' => 'post' ),
				),
			)
		);

		$this->assertSame( 3, $page_one['totalItems'] );
		$this->assertSame( 2, $page_one['totalPages'] );
		$this->assertSame( 2, substr_count( $page_one['html'], 'dsgo-query__item' ) );
		$this->assertSame( 1, substr_count( $page_two['html'], 'dsgo-query__item' ) );
	}

	/**
	 * Production path: no manual context injection — parent stack comes from
	 * $GLOBALS['designsetgo_parent_stack'] pushed by designsetgo_query_render_item().
	 */
	public function test_relationship_uses_globals_parent_stack_fallback() {
		$parent  = self::factory()->post->create( array( 'post_title' => 'Parent Post' ) );
		$child_a = self::factory()->post->create( array( 'post_title' => 'Child One' ) );
		$child_b = self::factory()->post->create( array( 'post_title' => 'Child Two' ) );
		update_post_meta( $parent, 'related_posts', array( $child_a, $child_b ) );

		require_once DESIGNSETGO_PATH . 'src/blocks/query/render-helpers.php';

		// Simulate the production path: push the parent item onto $GLOBALS directly,
		// as designsetgo_query_render_item() does, then call the relationship renderer
		// WITHOUT injecting parent_stack into $context (which is what production does).
		$GLOBALS['designsetgo_parent_stack'] = array(
			array( 'postId' => $parent, 'postType' => 'post', 'index' => 0 ),
		);

		try {
			// Use designsetgo_query_render() so defaults are applied (avoids "Undefined array key" notices).
			$result = designsetgo_query_render(
				array(
					'source'               => 'relationship',
					'relationshipField'    => 'related_posts',
					'relationshipFallback' => 'empty',
					'postType'             => 'post',
					'perPage'              => 10,
					'tagName'              => 'ul',
					'itemTagName'          => 'li',
				),
				array(
					'query_id'   => 'rel-globals',
					'page'       => 1,
					'inner_html' => '<!-- wp:paragraph --><p>child</p><!-- /wp:paragraph -->',
					'params'     => array(),
					// Intentionally NO parent_stack key — exercises the GLOBALS fallback.
				)
			);
		} finally {
			unset( $GLOBALS['designsetgo_parent_stack'] );
		}

		$this->assertSame( 2, $result['totalItems'], 'Relationship renderer must resolve IDs via $GLOBALS parent stack when context key is absent.' );
		$this->assertStringContainsString( 'dsgo-query__item', $result['html'] );
	}
}

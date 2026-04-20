<?php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_UnitTestCase;

class ParentStackTest extends WP_UnitTestCase {

	public function test_stack_is_pushed_and_popped_per_item() {
		$posts = self::factory()->post->create_many( 2 );

		$GLOBALS['dsgo_stack_capture'] = array();
		add_action( 'render_block', function ( $html, $block ) {
			if ( ! empty( $block['blockName'] ) && 'core/paragraph' === $block['blockName'] ) {
				$GLOBALS['dsgo_stack_capture'][] = $GLOBALS['designsetgo_parent_stack'] ?? array();
			}
			return $html;
		}, 10, 2 );

		require_once DESIGNSETGO_PATH . 'src/blocks/query/render-helpers.php';
		designsetgo_query_render(
			array( 'source' => 'posts', 'postType' => 'post', 'perPage' => 2, 'tagName' => 'ul', 'itemTagName' => 'li' ),
			array( 'query_id' => 'c1', 'page' => 1, 'inner_html' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', 'params' => array() )
		);

		$this->assertCount( 2, $GLOBALS['dsgo_stack_capture'] );
		// WP_Query defaults to date DESC (newest first), so the second-created post
		// (higher ID) renders first. Sort the captured IDs to assert both are present
		// without depending on query ordering.
		$captured_ids = array(
			$GLOBALS['dsgo_stack_capture'][0][0]['postId'],
			$GLOBALS['dsgo_stack_capture'][1][0]['postId'],
		);
		sort( $captured_ids );
		$expected_ids = $posts;
		sort( $expected_ids );
		$this->assertSame( $expected_ids, $captured_ids );
		$this->assertArrayNotHasKey( 'designsetgo_parent_stack', $GLOBALS ); // popped after render
	}
}

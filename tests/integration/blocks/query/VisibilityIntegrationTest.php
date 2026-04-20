<?php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_UnitTestCase;

class VisibilityIntegrationTest extends WP_UnitTestCase {

	public function test_hides_block_when_rule_does_not_match() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'featured', '0' );

		require_once DESIGNSETGO_PATH . 'src/blocks/query/render-helpers.php';

		$inner_html = '<!-- wp:paragraph {"dsgoVisibility":{"operator":"AND","rules":[{"type":"meta","key":"featured","op":"equals","value":"1"}]}} -->'
					. '<p>Featured badge</p>'
					. '<!-- /wp:paragraph -->'
					. '<!-- wp:paragraph --><p>Always shown</p><!-- /wp:paragraph -->';

		$html = designsetgo_query_render_item(
			$inner_html,
			array( 'postId' => $post_id, 'postType' => 'post', 'index' => 0 ),
			'li'
		);

		$this->assertStringNotContainsString( 'Featured badge', $html );
		$this->assertStringContainsString( 'Always shown', $html );
	}

	public function test_index_rule_is_applied_during_full_query_render() {
		$first_post  = self::factory()->post->create( array( 'post_title' => 'First Item' ) );
		$second_post = self::factory()->post->create( array( 'post_title' => 'Second Item' ) );

		require_once DESIGNSETGO_PATH . 'src/blocks/query/render-helpers.php';

		$inner_html = '<!-- wp:paragraph {"dsgoVisibility":{"operator":"AND","rules":[{"type":"index","op":"equals","value":0}]}} -->'
					. '<p>First only</p>'
					. '<!-- /wp:paragraph -->';

		$result = designsetgo_query_render(
			array(
				'source'      => 'manual',
				'manualIds'   => array( $first_post, $second_post ),
				'perPage'     => 2,
				'tagName'     => 'ul',
				'itemTagName' => 'li',
			),
			array(
				'query_id'   => 'visibility-index',
				'page'       => 1,
				'inner_html' => $inner_html,
				'params'     => array(),
			)
		);

		// 'First only' appears once in the rendered list items.
		// It also appears in the JSON blob (the raw template stored for IAPI refreshes) as
		// a JSON-encoded, HTML-entity-escaped string inside <script type="application/json">.
		// Strip the blob scripts before counting, so we only assert on rendered output.
		$list_html = preg_replace( '/<script type="application\/json"[^>]*>.*?<\/script>/s', '', $result['html'] );
		$this->assertSame( 1, substr_count( $list_html, 'First only' ) );
	}
}

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
}

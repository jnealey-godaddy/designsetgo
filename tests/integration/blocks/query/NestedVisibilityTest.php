<?php
namespace DesignSetGo\Tests\Integration\Blocks\Query;

use WP_UnitTestCase;

class NestedVisibilityTest extends WP_UnitTestCase {

	public function test_visibility_applies_to_nested_blocks() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'featured', '0' );

		require_once DESIGNSETGO_PATH . 'src/blocks/query/render-helpers.php';

		// Register the render_block filter so nested block visibility is gated.
		\DesignSetGo\BlockVisibility::register();

		// Paragraph with dsgoVisibility nested inside a core/group.
		$inner_html = '<!-- wp:group -->'
			. '<div class="wp-block-group">'
			. '<!-- wp:paragraph {"dsgoVisibility":{"operator":"AND","rules":[{"type":"meta","key":"featured","op":"equals","value":"1"}]}} -->'
			. '<p>Nested featured badge</p>'
			. '<!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>Always shown nested</p><!-- /wp:paragraph -->'
			. '</div>'
			. '<!-- /wp:group -->';

		$html = designsetgo_query_render_item(
			$inner_html,
			array( 'postId' => $post_id, 'postType' => 'post', 'index' => 0 ),
			'li'
		);

		$this->assertStringNotContainsString( 'Nested featured badge', $html );
		$this->assertStringContainsString( 'Always shown nested', $html );
	}
}

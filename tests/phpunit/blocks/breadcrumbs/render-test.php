<?php
/**
 * Tests for the breadcrumbs block server render.
 *
 * Regression guard for the PrefixAllGlobals refactor that wrapped render.php in
 * designsetgo_render_breadcrumbs(). The trail is built from
 * $block->context['postId'], so we hand the render a lightweight context object
 * pointing at a real page and assert the <nav> markup is emitted.
 *
 * @group breadcrumbs
 */

/**
 * @group breadcrumbs
 */
class DesignSetGo_Breadcrumbs_Render_Test extends WP_UnitTestCase {

	/**
	 * Include the built render template with the given attributes/context and
	 * capture its echoed output.
	 *
	 * @param array       $attributes Block attributes.
	 * @param object|null $block      Block instance (carries context).
	 * @return string Rendered HTML ('' when the template bails).
	 */
	private function render( array $attributes, $block = null ) {
		$path = DESIGNSETGO_PATH . 'build/blocks/breadcrumbs/render.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render templates are served from build/.' );

		$content = '';

		$previous_block                     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'designsetgo/breadcrumbs',
			'attrs'     => array(),
		);

		ob_start();
		$returned = include $path;
		$html     = ob_get_clean();

		WP_Block_Supports::$block_to_render = $previous_block;

		return is_string( $returned ) ? $returned : $html;
	}

	/**
	 * Build a minimal block-context stand-in (duck-typed: render reads
	 * $block->context['postId']).
	 *
	 * @param array $context Context map.
	 * @return object
	 */
	private function block_with_context( array $context ) {
		$block          = new stdClass();
		$block->context = $context;
		return $block;
	}

	public function test_renders_nav_with_home_and_current_for_a_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'About Our Team',
				'post_status' => 'publish',
			)
		);

		$html = $this->render(
			array(
				'showHome'    => true,
				'showCurrent' => true,
			),
			$this->block_with_context( array( 'postId' => $page_id ) )
		);

		$this->assertStringContainsString( '<nav', $html );
		$this->assertStringContainsString( 'dsgo-breadcrumbs', $html );
		$this->assertStringContainsString( 'About Our Team', $html );
		$this->assertStringContainsString( 'Home', $html );
	}

	public function test_renders_nothing_without_post_context() {
		// No postId in context -> empty trail -> the template returns early.
		$html = $this->render(
			array(
				'showHome'    => true,
				'showCurrent' => true,
			),
			$this->block_with_context( array() )
		);

		$this->assertSame( '', $html );
	}
}

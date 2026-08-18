<?php
/**
 * Collecting schema text must not execute dynamic blocks.
 *
 * @package DesignSetGo
 */

/**
 * Dynamic-block side-effect tests.
 *
 * @group schema
 */
class Schema_No_Render_Test extends WP_UnitTestCase {

	/**
	 * Times the probe block's render_callback ran.
	 *
	 * @var int
	 */
	public static $renders = 0;

	/**
	 * Register a dynamic probe block.
	 */
	public function set_up() {
		parent::set_up();
		self::$renders = 0;

		register_block_type(
			'dsgo-test/probe',
			array(
				'render_callback' => static function () {
					++Schema_No_Render_Test::$renders;
					return '<p>rendered</p>';
				},
			)
		);
	}

	/**
	 * Clean up the probe block.
	 */
	public function tear_down() {
		unregister_block_type( 'dsgo-test/probe' );
		parent::tear_down();
	}

	/**
	 * wp_head must not execute a dynamic block nested in an answer.
	 */
	public function test_dynamic_block_in_an_answer_is_not_executed() {
		$content = '<!-- wp:designsetgo/accordion {"dsgoSchema":"faq"} -->'
			. '<div class="dsgo-accordion">'
			. '<!-- wp:designsetgo/accordion-item -->'
			. '<div class="dsgo-accordion-item"><div class="dsgo-accordion-item__header">'
			. '<span class="dsgo-accordion-item__title">Question?</span></div>'
			. '<div class="dsgo-accordion-item__content">'
			. '<!-- wp:paragraph --><p>Real answer.</p><!-- /wp:paragraph -->'
			. '<!-- wp:dsgo-test/probe /-->'
			. '</div></div>'
			. '<!-- /wp:designsetgo/accordion-item -->'
			. '</div><!-- /wp:designsetgo/accordion -->';

		$post_id = self::factory()->post->create( array( 'post_content' => $content ) );
		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );
		$head = ob_get_clean();

		$this->assertStringContainsString( 'FAQPage', $head, 'The schema should still be emitted.' );
		$this->assertSame( 0, self::$renders, 'A dynamic block must not be executed while collecting schema text.' );
	}
}

<?php
/**
 * Schema head output tests.
 *
 * Exercises the whole path: a real post, parsed on wp_head, producing exactly
 * one JSON-LD script for the page.
 *
 * @package DesignSetGo
 */

/**
 * Head output tests.
 *
 * @group schema
 */
class Schema_Output_Test extends WP_UnitTestCase {

	/**
	 * Render wp_head for a post with the given content.
	 *
	 * @param string $content Post content.
	 * @return string Captured head output.
	 */
	private function head_for( $content ) {
		$post_id = self::factory()->post->create(
			array( 'post_content' => $content )
		);

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );

		return ob_get_clean();
	}

	/**
	 * Serialize an accordion the way save.js does.
	 *
	 * The title is HTML-sourced, so it lives in the markup, not in attrs.
	 *
	 * @param string $schema   dsgoSchema value.
	 * @param string $question Question text.
	 * @param string $answer   Answer text.
	 * @return string Block markup.
	 */
	private function accordion_markup( $schema = 'faq', $question = 'What is it?', $answer = 'A plugin.' ) {
		return '<!-- wp:designsetgo/accordion {"dsgoSchema":"' . $schema . '"} -->'
			. '<div class="wp-block-designsetgo-accordion dsgo-accordion">'
			. '<!-- wp:designsetgo/accordion-item -->'
			. '<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item">'
			. '<div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger">'
			. '<span class="dsgo-accordion-item__title">' . $question . '</span>'
			. '</button></div>'
			. '<div class="dsgo-accordion-item__panel"><div class="dsgo-accordion-item__content">'
			. '<!-- wp:paragraph --><p>' . $answer . '</p><!-- /wp:paragraph -->'
			. '</div></div></div>'
			. '<!-- /wp:designsetgo/accordion-item -->'
			. '</div>'
			. '<!-- /wp:designsetgo/accordion -->';
	}

	/**
	 * Opting in emits a JSON-LD script.
	 */
	public function test_emits_a_json_ld_script_when_opted_in() {
		$head = $this->head_for( $this->accordion_markup() );

		$this->assertStringContainsString( 'application/ld+json', $head );
		$this->assertStringContainsString( 'FAQPage', $head );
	}

	/**
	 * The default emits nothing.
	 */
	public function test_emits_nothing_when_not_opted_in() {
		$head = $this->head_for( $this->accordion_markup( 'none' ) );

		$this->assertStringNotContainsString( 'application/ld+json', $head );
	}

	/**
	 * A post with no schema blocks emits nothing.
	 */
	public function test_emits_nothing_for_a_post_with_no_schema_blocks() {
		$head = $this->head_for( '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' );

		$this->assertStringNotContainsString( 'application/ld+json', $head );
	}

	/**
	 * Two opted-in blocks still produce one script.
	 */
	public function test_emits_exactly_one_script_for_two_opted_in_blocks() {
		$head = $this->head_for( $this->accordion_markup() . $this->accordion_markup( 'faq', 'Second?', 'Yes.' ) );

		$this->assertSame( 1, substr_count( $head, 'application/ld+json' ) );
	}

	/**
	 * Both blocks land in the graph.
	 */
	public function test_merges_both_blocks_into_the_graph() {
		$head = $this->head_for( $this->accordion_markup() . $this->accordion_markup( 'faq', 'Second?', 'Yes.' ) );

		$this->assertStringContainsString( '@graph', $head );
		$this->assertSame( 2, substr_count( $head, 'FAQPage' ) );
	}

	/**
	 * Nesting inside a container is still found.
	 */
	public function test_finds_a_schema_block_nested_inside_a_section() {
		$content = '<!-- wp:designsetgo/section --><div class="wp-block-designsetgo-section">'
			. $this->accordion_markup()
			. '</div><!-- /wp:designsetgo/section -->';

		$this->assertStringContainsString( 'FAQPage', $this->head_for( $content ) );
	}

	/**
	 * A HowTo block emits HowTo, not FAQ.
	 */
	public function test_howto_opt_in_emits_howto() {
		$head = $this->head_for( $this->accordion_markup( 'howto', 'Install', 'Upload the zip.' ) );

		$this->assertStringContainsString( 'HowTo', $head );
		$this->assertStringNotContainsString( 'FAQPage', $head );
	}

	/**
	 * HowTo.name is required by Google, and comes from the post title.
	 */
	public function test_howto_carries_the_post_title_as_its_name() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'How to install the plugin',
				'post_content' => $this->accordion_markup( 'howto', 'Install', 'Upload the zip.' ),
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );
		$head = ob_get_clean();

		preg_match(
			'#<script type="application/ld\+json">(.*?)</script>#s',
			$head,
			$matches
		);

		$decoded = json_decode( $matches[1], true );

		$this->assertSame( 'How to install the plugin', $decoded['@graph'][0]['name'] );
		$this->assertSame( 1, $decoded['@graph'][0]['step'][0]['position'] );
	}

	/**
	 * The output parses as JSON.
	 */
	public function test_output_is_valid_json() {
		$head = $this->head_for( $this->accordion_markup() );

		preg_match(
			'#<script type="application/ld\+json">(.*?)</script>#s',
			$head,
			$matches
		);

		$this->assertNotEmpty( $matches );

		$decoded = json_decode( $matches[1], true );

		$this->assertNotNull( $decoded );
		$this->assertSame( 'https://schema.org', $decoded['@context'] );
		$this->assertSame( 'FAQPage', $decoded['@graph'][0]['@type'] );
	}

	/**
	 * A closing script tag in content cannot break out of the element.
	 */
	public function test_closing_script_tag_in_content_cannot_break_out() {
		$head = $this->head_for(
			$this->accordion_markup( 'faq', '</script><script>alert(1)</script>', 'Answer.' )
		);

		$this->assertStringNotContainsString( '</script><script>alert(1)', $head );

		// And what IS emitted must still be parseable.
		preg_match(
			'#<script type="application/ld\+json">(.*?)</script>#s',
			$head,
			$matches
		);
		$this->assertNotEmpty( $matches );
		$this->assertNotNull( json_decode( $matches[1], true ) );
	}

	/**
	 * Archives are not singular, so nothing is emitted.
	 */
	public function test_emits_nothing_on_an_archive() {
		self::factory()->post->create(
			array( 'post_content' => $this->accordion_markup() )
		);

		$this->go_to( home_url( '/' ) );

		ob_start();
		do_action( 'wp_head' );
		$head = ob_get_clean();

		$this->assertStringNotContainsString( 'application/ld+json', $head );
	}

	/**
	 * An opted-in but empty accordion emits nothing.
	 */
	public function test_emits_nothing_when_the_block_has_no_usable_content() {
		$content = '<!-- wp:designsetgo/accordion {"dsgoSchema":"faq"} -->'
			. '<div class="wp-block-designsetgo-accordion dsgo-accordion"></div>'
			. '<!-- /wp:designsetgo/accordion -->';

		$this->assertStringNotContainsString( 'application/ld+json', $this->head_for( $content ) );
	}

	/**
	 * The filter can replace the graph.
	 */
	public function test_nodes_filter_can_modify_the_graph() {
		$callback = static function ( $nodes ) {
			return array( array( '@type' => 'Thing' ) );
		};

		add_filter( 'designsetgo_schema_nodes', $callback );
		$head = $this->head_for( $this->accordion_markup() );
		remove_filter( 'designsetgo_schema_nodes', $callback );

		$this->assertStringContainsString( 'Thing', $head );
		$this->assertStringNotContainsString( 'FAQPage', $head );
	}

	/**
	 * The filter can suppress output entirely.
	 */
	public function test_nodes_filter_can_suppress_output() {
		$callback = '__return_empty_array';

		add_filter( 'designsetgo_schema_nodes', $callback );
		$head = $this->head_for( $this->accordion_markup() );
		remove_filter( 'designsetgo_schema_nodes', $callback );

		$this->assertStringNotContainsString( 'application/ld+json', $head );
	}
}

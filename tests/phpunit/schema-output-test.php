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
	 * An ENTITY-ENCODED closing tag cannot break out either.
	 *
	 * Text is stripped before entities are decoded, so `&lt;/script&gt;` in the
	 * saved markup survives strip_tags() and only becomes a literal
	 * `</script>` afterwards. That ordering is deliberate — decoding first
	 * would let strip_tags() delete text the page actually displays, e.g. an
	 * answer that literally reads "<b>bold</b>" — so the breakout is stopped at
	 * output instead, where every source of the sequence is covered at once.
	 * This test is what makes that safe to rely on.
	 */
	public function test_entity_encoded_closing_script_tag_cannot_break_out() {
		$head = $this->head_for(
			$this->accordion_markup(
				'faq',
				'Ends with &lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;',
				'Answer.'
			)
		);

		// No real element boundary was created.
		$this->assertStringNotContainsString( '</script><script>alert(1)', $head );

		preg_match(
			'#<script type="application/ld\+json">(.*?)</script>#s',
			$head,
			$matches
		);

		$this->assertNotEmpty( $matches );

		$decoded = json_decode( $matches[1], true );
		$this->assertNotNull( $decoded );

		// The author's text is preserved verbatim inside the JSON string —
		// escaped for the script context, not mangled.
		$this->assertSame(
			'Ends with </script><script>alert(1)</script>',
			$decoded['@graph'][0]['mainEntity'][0]['name']
		);
	}

	/**
	 * Escaped markup an author typed as literal text survives as text.
	 */
	public function test_literal_markup_in_an_answer_is_preserved_not_stripped() {
		$head = $this->head_for(
			$this->accordion_markup( 'faq', 'Formatting?', 'Use &lt;b&gt;bold&lt;/b&gt; for emphasis.' )
		);

		preg_match(
			'#<script type="application/ld\+json">(.*?)</script>#s',
			$head,
			$matches
		);

		$decoded = json_decode( $matches[1], true );

		$this->assertSame(
			'Use <b>bold</b> for emphasis.',
			$decoded['@graph'][0]['mainEntity'][0]['acceptedAnswer']['text']
		);
	}

	/**
	 * A password-protected post must not disclose its content.
	 *
	 * is_singular() is still true and get_post() still returns the full object
	 * for a protected post — the front end substitutes the password form at
	 * the_content, not before it. wp_head runs earlier still, so without an
	 * explicit gate the questions, answers and title would be readable in view
	 * source by anyone, password or not.
	 */
	public function test_emits_nothing_for_a_password_protected_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Secret guide',
				'post_password' => 'hunter2',
				'post_content'  => $this->accordion_markup( 'faq', 'Secret question?', 'Secret answer.' ),
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );
		$head = ob_get_clean();

		$this->assertStringNotContainsString( 'application/ld+json', $head );
		$this->assertStringNotContainsString( 'Secret question?', $head );
		$this->assertStringNotContainsString( 'Secret answer.', $head );
	}

	/**
	 * The same protection applies to a HowTo, whose name is the post title.
	 */
	public function test_emits_nothing_for_a_password_protected_howto() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Secret HowTo title',
				'post_password' => 'hunter2',
				'post_content'  => $this->accordion_markup( 'howto', 'Step one', 'Do the thing.' ),
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );
		$head = ob_get_clean();

		// The title legitimately appears in <title>; what must not appear is a
		// JSON-LD payload carrying it, or any of the protected step content.
		$this->assertStringNotContainsString( 'application/ld+json', $head );
		$this->assertStringNotContainsString( '@graph', $head );
		$this->assertStringNotContainsString( 'Step one', $head );
		$this->assertStringNotContainsString( 'Do the thing.', $head );
	}

	/**
	 * Once the password is supplied, the schema is emitted as normal.
	 */
	public function test_emits_schema_once_the_password_has_been_entered() {
		$post_id = self::factory()->post->create(
			array(
				'post_password' => 'hunter2',
				'post_content'  => $this->accordion_markup( 'faq', 'Secret question?', 'Secret answer.' ),
			)
		);

		// This is how wp-login.php stores a correct password. It must be a
		// phpass hash specifically: post_password_required() rejects anything
		// not starting with $P$B before it ever checks the value, so a bcrypt
		// hash from wp_hash_password() would read as "still locked".
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher                                 = new PasswordHash( 8, true );
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = $hasher->HashPassword( 'hunter2' );

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );
		$head = ob_get_clean();

		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

		$this->assertStringContainsString( 'FAQPage', $head );
		$this->assertStringContainsString( 'Secret question?', $head );
	}

	/**
	 * The attribute alone must not summon a builder on any block.
	 *
	 * The allowlist lives in the editor UI and in the server-side attribute
	 * registration, but neither constrains what parse_blocks() hands the
	 * collector at runtime. A hand-written block comment — the editor's Code
	 * view is enough, no unfiltered_html needed — could put dsgoSchema on any
	 * block and have the FAQ builder run against its children.
	 *
	 * No privilege escalation and no XSS, but it contradicts the stated design
	 * that only blocks with a builder behind them generate schema.
	 */
	public function test_the_attribute_on_a_non_allowlisted_block_is_ignored() {
		$content = '<!-- wp:group {"dsgoSchema":"faq"} --><div class="wp-block-group">'
			// Real accordion items, so the builder would find usable pairs.
			. '<!-- wp:designsetgo/accordion-item -->'
			. '<div class="dsgo-accordion-item"><div class="dsgo-accordion-item__header">'
			. '<span class="dsgo-accordion-item__title">Smuggled question?</span></div>'
			. '<div class="dsgo-accordion-item__content">'
			. '<!-- wp:paragraph --><p>Smuggled answer.</p><!-- /wp:paragraph -->'
			. '</div></div>'
			. '<!-- /wp:designsetgo/accordion-item -->'
			. '</div><!-- /wp:group -->';

		$head = $this->head_for( $content );

		$this->assertStringNotContainsString( 'application/ld+json', $head );
		$this->assertStringNotContainsString( 'Smuggled question?', $head );
	}

	/**
	 * The allowlisted block still works, so the check is not simply blocking.
	 */
	public function test_the_allowlisted_block_still_emits() {
		$this->assertStringContainsString( 'FAQPage', $this->head_for( $this->accordion_markup() ) );
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

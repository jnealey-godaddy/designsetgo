<?php
/**
 * Schema must be found inside synced patterns (core/block references).
 *
 * A synced pattern stores its markup in a wp_block post; the page only holds
 * <!-- wp:block {"ref":N} /-->. Defining an FAQ once in a shared pattern and
 * reusing it is a realistic authoring flow, and silently emitting nothing
 * there would be invisible to the author.
 *
 * @package DesignSetGo
 */

/**
 * Synced pattern tests.
 *
 * @group schema
 */
class Schema_Synced_Pattern_Test extends WP_UnitTestCase {

	/**
	 * Accordion markup with an opted-in schema type.
	 *
	 * @return string Block markup.
	 */
	private function accordion() {
		return '<!-- wp:designsetgo/accordion {"dsgoSchema":"faq"} -->'
			. '<div class="dsgo-accordion">'
			. '<!-- wp:designsetgo/accordion-item -->'
			. '<div class="dsgo-accordion-item"><div class="dsgo-accordion-item__header">'
			. '<span class="dsgo-accordion-item__title">Synced question?</span></div>'
			. '<div class="dsgo-accordion-item__content">'
			. '<!-- wp:paragraph --><p>Synced answer.</p><!-- /wp:paragraph -->'
			. '</div></div>'
			. '<!-- /wp:designsetgo/accordion-item -->'
			. '</div><!-- /wp:designsetgo/accordion -->';
	}

	/**
	 * Render wp_head for a post.
	 *
	 * @param string $content Post content.
	 * @return string Head output.
	 */
	private function head_for( $content ) {
		$post_id = self::factory()->post->create( array( 'post_content' => $content ) );
		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		do_action( 'wp_head' );

		return ob_get_clean();
	}

	/**
	 * An accordion inside a synced pattern still emits schema.
	 */
	public function test_schema_inside_a_synced_pattern_is_found() {
		$ref = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => $this->accordion(),
			)
		);

		$head = $this->head_for( '<!-- wp:block {"ref":' . $ref . '} /-->' );

		$this->assertStringContainsString( 'FAQPage', $head );
		$this->assertStringContainsString( 'Synced question?', $head );
	}

	/**
	 * A pattern nested inside another pattern is still reached.
	 */
	public function test_schema_inside_a_nested_synced_pattern_is_found() {
		$inner = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => $this->accordion(),
			)
		);

		$outer = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:block {"ref":' . $inner . '} /-->',
			)
		);

		$this->assertStringContainsString(
			'FAQPage',
			$this->head_for( '<!-- wp:block {"ref":' . $outer . '} /-->' )
		);
	}

	/**
	 * A pattern that references itself must not recurse forever.
	 */
	public function test_a_self_referencing_pattern_terminates() {
		$ref = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => 'placeholder',
			)
		);

		// Point the pattern at itself, and include a real accordion so there is
		// something to find on the first pass.
		wp_update_post(
			array(
				'ID'           => $ref,
				'post_content' => $this->accordion() . '<!-- wp:block {"ref":' . $ref . '} /-->',
			)
		);

		$head = $this->head_for( '<!-- wp:block {"ref":' . $ref . '} /-->' );

		// The assertion that matters is that we get here at all.
		$this->assertStringContainsString( 'FAQPage', $head );
		$this->assertSame( 1, substr_count( $head, 'FAQPage' ) );
	}

	/**
	 * Two patterns referencing each other must not recurse forever.
	 */
	public function test_mutually_referencing_patterns_terminate() {
		$a = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => 'placeholder',
			)
		);
		$b = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:block {"ref":' . $a . '} /-->',
			)
		);

		wp_update_post(
			array(
				'ID'           => $a,
				'post_content' => $this->accordion() . '<!-- wp:block {"ref":' . $b . '} /-->',
			)
		);

		$this->assertStringContainsString(
			'FAQPage',
			$this->head_for( '<!-- wp:block {"ref":' . $a . '} /-->' )
		);
	}

	/**
	 * A draft pattern contributes nothing.
	 */
	public function test_an_unpublished_pattern_is_ignored() {
		$ref = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'draft',
				'post_content' => $this->accordion(),
			)
		);

		$this->assertStringNotContainsString(
			'application/ld+json',
			$this->head_for( '<!-- wp:block {"ref":' . $ref . '} /-->' )
		);
	}

	/**
	 * A password-protected pattern contributes nothing.
	 *
	 * Core's render_block_core_block() refuses to render one, so emitting its
	 * questions and answers into the head would disclose content the page
	 * itself never shows.
	 */
	public function test_a_password_protected_pattern_is_ignored() {
		$ref = self::factory()->post->create(
			array(
				'post_type'     => 'wp_block',
				'post_status'   => 'publish',
				'post_password' => 'hunter2',
				'post_content'  => $this->accordion(),
			)
		);

		$head = $this->head_for( '<!-- wp:block {"ref":' . $ref . '} /-->' );

		$this->assertStringNotContainsString( 'application/ld+json', $head );
		$this->assertStringNotContainsString( 'Synced question?', $head );
	}

	/**
	 * A reference to a post that is not a pattern is ignored.
	 */
	public function test_a_reference_to_a_non_pattern_post_is_ignored() {
		$ref = self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => $this->accordion(),
			)
		);

		$this->assertStringNotContainsString(
			'application/ld+json',
			$this->head_for( '<!-- wp:block {"ref":' . $ref . '} /-->' )
		);
	}

	/**
	 * A dangling reference is ignored rather than fatal.
	 */
	public function test_a_missing_reference_is_ignored() {
		$this->assertStringNotContainsString(
			'application/ld+json',
			$this->head_for( '<!-- wp:block {"ref":999999} /-->' )
		);
	}

	/**
	 * A reference with no ref attribute is ignored.
	 */
	public function test_a_reference_with_no_id_is_ignored() {
		$this->assertStringNotContainsString(
			'application/ld+json',
			$this->head_for( '<!-- wp:block /-->' )
		);
	}
}

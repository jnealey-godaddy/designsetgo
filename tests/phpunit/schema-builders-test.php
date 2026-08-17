<?php
/**
 * Schema builder tests.
 *
 * The accordion's `title` is an HTML-SOURCED attribute
 * (source: "html", selector: ".dsgo-accordion-item__title"), so parse_blocks()
 * never puts it in $block['attrs']. A builder that reads $attrs['title'] finds
 * an empty string for every real post and silently emits nothing — while
 * passing any test that hand-builds the array with attrs.title set.
 *
 * These tests therefore drive the builders from markup shaped like what
 * accordion-item/save.js actually writes, never from invented attributes.
 *
 * @package DesignSetGo
 */

/**
 * Builder tests.
 *
 * @group schema
 */
class Schema_Builders_Test extends WP_UnitTestCase {

	/**
	 * Load the builders under test.
	 *
	 * Required here rather than at file scope: a require_once directly after
	 * the file docblock makes PHPCS stop recognising it as the file comment.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once DESIGNSETGO_PATH . 'includes/features/schema-builders.php';
	}

	/**
	 * Serialize one accordion item the way save.js does.
	 *
	 * @param string $question Title text (may contain inline markup).
	 * @param string $answer   Answer paragraph text.
	 * @return string Block markup.
	 */
	private function item_markup( $question, $answer ) {
		$markup = '<!-- wp:designsetgo/accordion-item -->';

		$markup .= '<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed">';
		$markup .= '<div class="dsgo-accordion-item__header">';
		$markup .= '<button type="button" class="dsgo-accordion-item__trigger">';
		$markup .= '<span class="dsgo-accordion-item__title">' . $question . '</span>';
		$markup .= '<span class="dsgo-accordion-item__icon" aria-hidden="true"><svg></svg></span>';
		$markup .= '</button></div>';
		$markup .= '<div class="dsgo-accordion-item__panel">';
		$markup .= '<div class="dsgo-accordion-item__content">';

		if ( '' !== $answer ) {
			$markup .= '<!-- wp:paragraph --><p>' . $answer . '</p><!-- /wp:paragraph -->';
		}

		$markup .= '</div></div></div>';
		$markup .= '<!-- /wp:designsetgo/accordion-item -->';

		return $markup;
	}

	/**
	 * Parse a full accordion from markup.
	 *
	 * @param array  $items  List of array{q: string, a: string}.
	 * @param string $schema dsgoSchema value.
	 * @return array Parsed accordion block.
	 */
	private function accordion( array $items, $schema = 'faq' ) {
		$inner = '';

		foreach ( $items as $item ) {
			$inner .= $this->item_markup( $item['q'], $item['a'] );
		}

		$markup = '<!-- wp:designsetgo/accordion {"dsgoSchema":"' . $schema . '"} -->'
			. '<div class="wp-block-designsetgo-accordion dsgo-accordion">' . $inner . '</div>'
			. '<!-- /wp:designsetgo/accordion -->';

		$blocks = parse_blocks( $markup );

		return $blocks[0];
	}

	/**
	 * The core case: a real accordion yields one question per item.
	 */
	public function test_faq_builds_one_question_per_item() {
		$schema = designsetgo_schema_build_faq(
			$this->accordion(
				array(
					array(
						'q' => 'What is it?',
						'a' => 'A plugin.',
					),
					array(
						'q' => 'How much?',
						'a' => 'Free.',
					),
				)
			)
		);

		$this->assertSame( 'FAQPage', $schema['@type'] );
		$this->assertCount( 2, $schema['mainEntity'] );
		$this->assertSame( 'What is it?', $schema['mainEntity'][0]['name'] );
		$this->assertSame( 'A plugin.', $schema['mainEntity'][0]['acceptedAnswer']['text'] );
		$this->assertSame( 'How much?', $schema['mainEntity'][1]['name'] );
	}

	/**
	 * The regression guard for the HTML-sourced attribute.
	 *
	 * The attrs.title key is absent from real content. If a builder reads it,
	 * this returns null instead of a populated graph.
	 */
	public function test_faq_reads_the_title_from_markup_not_from_attrs() {
		$block = $this->accordion(
			array(
				array(
					'q' => 'Sourced from HTML',
					'a' => 'Answer.',
				),
			)
		);

		$this->assertArrayNotHasKey(
			'title',
			$block['innerBlocks'][0]['attrs'],
			'parse_blocks() must not surface an html-sourced attribute; if it does, this test is no longer meaningful.'
		);

		$schema = designsetgo_schema_build_faq( $block );

		$this->assertNotNull( $schema );
		$this->assertSame( 'Sourced from HTML', $schema['mainEntity'][0]['name'] );
	}

	/**
	 * Inline markup inside the title is reduced to text.
	 */
	public function test_faq_strips_inline_markup_from_the_question() {
		$schema = designsetgo_schema_build_faq(
			$this->accordion(
				array(
					array(
						'q' => 'What is <strong>it</strong>?',
						'a' => 'A plugin.',
					),
				)
			)
		);

		$this->assertSame( 'What is it?', $schema['mainEntity'][0]['name'] );
	}

	/**
	 * Entities decode to their characters.
	 */
	public function test_faq_decodes_entities_in_the_question() {
		$schema = designsetgo_schema_build_faq(
			$this->accordion(
				array(
					array(
						'q' => 'Tea &amp; coffee?',
						'a' => 'Both.',
					),
				)
			)
		);

		$this->assertSame( 'Tea & coffee?', $schema['mainEntity'][0]['name'] );
	}

	/**
	 * The icon span must not contribute to the question text.
	 */
	public function test_faq_ignores_the_icon_span() {
		$schema = designsetgo_schema_build_faq(
			$this->accordion(
				array(
					array(
						'q' => 'Clean title',
						'a' => 'Answer.',
					),
				)
			)
		);

		$this->assertSame( 'Clean title', $schema['mainEntity'][0]['name'] );
	}

	/**
	 * A pair missing its answer is not valid structured data.
	 */
	public function test_faq_skips_an_item_with_no_answer() {
		$block = $this->accordion(
			array(
				array(
					'q' => 'Question?',
					'a' => '',
				),
			)
		);

		$this->assertNull( designsetgo_schema_build_faq( $block ) );
	}

	/**
	 * A pair missing its question is not valid structured data.
	 */
	public function test_faq_skips_an_item_with_no_question() {
		$block = $this->accordion(
			array(
				array(
					'q' => '',
					'a' => 'Orphan answer.',
				),
			)
		);

		$this->assertNull( designsetgo_schema_build_faq( $block ) );
	}

	/**
	 * An empty accordion produces nothing.
	 */
	public function test_faq_of_an_empty_accordion_is_null() {
		$this->assertNull( designsetgo_schema_build_faq( $this->accordion( array() ) ) );
	}

	/**
	 * Valid pairs survive alongside an invalid one.
	 */
	public function test_faq_keeps_valid_pairs_when_one_is_incomplete() {
		$schema = designsetgo_schema_build_faq(
			$this->accordion(
				array(
					array(
						'q' => 'Good',
						'a' => 'Answer.',
					),
					array(
						'q' => 'Orphan',
						'a' => '',
					),
				)
			)
		);

		$this->assertCount( 1, $schema['mainEntity'] );
		$this->assertSame( 'Good', $schema['mainEntity'][0]['name'] );
	}

	/**
	 * HowTo numbers its steps from one.
	 */
	public function test_howto_numbers_its_steps() {
		$schema = designsetgo_schema_build_howto(
			$this->accordion(
				array(
					array(
						'q' => 'Install',
						'a' => 'Upload the zip.',
					),
					array(
						'q' => 'Activate',
						'a' => 'Click activate.',
					),
				),
				'howto'
			)
		);

		$this->assertSame( 'HowTo', $schema['@type'] );
		$this->assertSame( 1, $schema['step'][0]['position'] );
		$this->assertSame( 2, $schema['step'][1]['position'] );
		$this->assertSame( 'Install', $schema['step'][0]['name'] );
		$this->assertSame( 'Upload the zip.', $schema['step'][0]['text'] );
	}

	/**
	 * The title is a parameter, so HowTo.name needs no request state.
	 *
	 * This test deliberately performs no go_to() and touches no global $post —
	 * if the builder reached for get_the_title() again, name would be absent.
	 */
	public function test_howto_takes_its_name_from_the_title_argument() {
		$schema = designsetgo_schema_build_howto(
			$this->accordion(
				array(
					array(
						'q' => 'Install',
						'a' => 'Upload the zip.',
					),
				),
				'howto'
			),
			'How to install the plugin'
		);

		$this->assertSame( 'How to install the plugin', $schema['name'] );
	}

	/**
	 * With no title, name is omitted rather than emitted empty.
	 */
	public function test_howto_omits_name_when_no_title_is_given() {
		$schema = designsetgo_schema_build_howto(
			$this->accordion(
				array(
					array(
						'q' => 'Install',
						'a' => 'Upload the zip.',
					),
				),
				'howto'
			)
		);

		$this->assertArrayNotHasKey( 'name', $schema );
	}

	/**
	 * A dynamic block in an answer contributes no text and is never executed.
	 */
	public function test_text_extraction_ignores_a_dynamic_block() {
		$text = designsetgo_schema_text_from_blocks(
			parse_blocks(
				'<!-- wp:paragraph --><p>Real answer.</p><!-- /wp:paragraph -->'
				. '<!-- wp:some-plugin/dynamic /-->'
			)
		);

		$this->assertSame( 'Real answer.', $text );
	}

	/**
	 * An empty accordion produces no HowTo.
	 */
	public function test_howto_of_an_empty_accordion_is_null() {
		$this->assertNull(
			designsetgo_schema_build_howto( $this->accordion( array(), 'howto' ) )
		);
	}

	/**
	 * Multi-block answers are concatenated.
	 */
	public function test_answer_joins_multiple_blocks() {
		$text = designsetgo_schema_text_from_blocks(
			parse_blocks(
				'<!-- wp:paragraph --><p>One.</p><!-- /wp:paragraph -->'
				. '<!-- wp:paragraph --><p>Two.</p><!-- /wp:paragraph -->'
			)
		);

		$this->assertSame( 'One. Two.', $text );
	}

	/**
	 * Markup is stripped from answers.
	 */
	public function test_text_extraction_strips_markup() {
		$text = designsetgo_schema_text_from_blocks(
			parse_blocks( '<!-- wp:paragraph --><p>Hello <strong>world</strong></p><!-- /wp:paragraph -->' )
		);

		$this->assertSame( 'Hello world', $text );
	}

	/**
	 * Whitespace collapses to single spaces.
	 */
	public function test_text_extraction_collapses_whitespace() {
		$text = designsetgo_schema_text_from_blocks(
			parse_blocks( "<!-- wp:paragraph --><p>One\n\n   two</p><!-- /wp:paragraph -->" )
		);

		$this->assertSame( 'One two', $text );
	}

	/**
	 * Text in a SIBLING after the title must not be absorbed.
	 *
	 * A sibling sits at the same breadcrumb depth as the matched element, so a
	 * depth comparison of `< $found` never terminates on it — collection only
	 * stopped once the walk climbed out past the parent. Today the icon that
	 * follows the title is an <svg> with no text nodes, so nothing leaked; the
	 * moment anything text-bearing was added after the title (a screen-reader
	 * label, a badge, a different icon style) it would have been concatenated
	 * onto the question with no test failing.
	 */
	public function test_title_extractor_stops_at_the_end_of_the_matched_element() {
		$html = '<div class="dsgo-accordion-item__header"><button type="button">'
			. '<span class="dsgo-accordion-item__title">Real question?</span>'
			. '<span class="dsgo-accordion-item__icon">LEAKED</span>'
			. '</button></div>';

		$this->assertSame(
			'Real question?',
			designsetgo_schema_text_from_html( $html, 'dsgo-accordion-item__title' )
		);
	}

	/**
	 * Nested markup inside the title is still collected in full.
	 */
	public function test_title_extractor_collects_nested_text() {
		$html = '<span class="dsgo-accordion-item__title">A <em>nested <b>deep</b></em> title</span>'
			. '<span class="other">LEAKED</span>';

		$this->assertSame(
			'A nested deep title',
			designsetgo_schema_text_from_html( $html, 'dsgo-accordion-item__title' )
		);
	}

	/**
	 * An empty title element yields '' rather than the following sibling.
	 */
	public function test_title_extractor_handles_an_empty_element() {
		$html = '<span class="dsgo-accordion-item__title"></span><span>LEAKED</span>';

		$this->assertSame(
			'',
			designsetgo_schema_text_from_html( $html, 'dsgo-accordion-item__title' )
		);
	}

	/**
	 * The title extractor returns '' when the element is absent.
	 */
	public function test_title_extractor_returns_empty_for_missing_element() {
		$this->assertSame(
			'',
			designsetgo_schema_text_from_html( '<div>no title here</div>', 'dsgo-accordion-item__title' )
		);
	}

	/**
	 * The title extractor tolerates malformed HTML rather than throwing.
	 */
	public function test_title_extractor_survives_malformed_html() {
		$this->assertSame(
			'',
			designsetgo_schema_text_from_html( '<div><span class="unclosed', 'dsgo-accordion-item__title' )
		);
	}

	/**
	 * A class that merely contains the target as a substring must not match.
	 */
	public function test_title_extractor_matches_whole_class_names_only() {
		$html = '<span class="dsgo-accordion-item__title-suffix">Wrong</span>';

		$this->assertSame(
			'',
			designsetgo_schema_text_from_html( $html, 'dsgo-accordion-item__title' )
		);
	}
}

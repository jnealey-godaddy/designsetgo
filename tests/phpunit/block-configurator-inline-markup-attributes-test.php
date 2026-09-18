<?php
/**
 * Inline rich-text markup keeps the attributes page CSS and assistive tech rely on.
 *
 * Generated pages style inline links by class (e.g. `<a class="sd-text-link">`)
 * and mark them up with ids, targets, aria-* and data-* attributes. The
 * sanitizer used to allow only href/title/rel on `<a>` and class on `<span>`,
 * so those attributes were gone before Block_Inserter built any markup.
 *
 * Button labels are RichText too (`source: html` on icon-button and
 * modal-trigger `text`), but the key `text` is plain text on other blocks, so
 * it is treated as inline markup only for the blocks whose save() renders it
 * with RichText.Content.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Configurator;
use DesignSetGo\Abilities\Block_Inserter;

/**
 * @group abilities
 */
class Block_Configurator_Inline_Markup_Attributes_Test extends WP_UnitTestCase {

	/**
	 * Sanitize a single `content` value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_content( string $value ): string {
		$sanitized = Block_Configurator::sanitize_attributes( array( 'content' => $value ) );
		return $sanitized['content'];
	}

	public function test_link_keeps_class_id_target_aria_and_data_attributes() {
		$value = 'See the <a class="sd-text-link" id="menu-link" href="/menu/" title="Menu" target="_blank" rel="noopener" aria-label="Full menu" aria-current="page" aria-describedby="menu-note" data-track="menu">full menu</a> today.';

		$this->assertSame( $value, $this->sanitize_content( $value ) );
	}

	public function test_plain_link_with_class_is_unchanged() {
		$value = 'Browse the <a class="sd-text-link" href="/menu/">menu</a>.';

		$this->assertSame( $value, $this->sanitize_content( $value ) );
	}

	public function test_span_keeps_class_id_aria_hidden_and_data_attributes() {
		$value = 'Price <span class="sd-accent" id="price" aria-hidden="true" data-role="price">$12</span>';

		$this->assertSame( $value, $this->sanitize_content( $value ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function formatting_tag_provider(): array {
		return array(
			'em'     => array( 'em' ),
			'strong' => array( 'strong' ),
			'mark'   => array( 'mark' ),
			'code'   => array( 'code' ),
		);
	}

	/**
	 * @dataProvider formatting_tag_provider
	 */
	public function test_formatting_tags_keep_class( string $tag ) {
		$value = sprintf( 'A <%1$s class="sd-emphasis">word</%1$s> here', $tag );

		$this->assertSame( $value, $this->sanitize_content( $value ) );
	}

	public function test_javascript_href_is_neutralised() {
		$clean = $this->sanitize_content( '<a class="sd-text-link" href="javascript:alert(1)">x</a>' );

		$this->assertStringNotContainsStringIgnoringCase( 'javascript:', $clean );
		$this->assertStringContainsString( 'class="sd-text-link"', $clean );
	}

	public function test_entity_encoded_javascript_href_is_neutralised() {
		$clean = $this->sanitize_content( '<a href="&#106;avascript:alert(1)">x</a>' );

		$this->assertStringNotContainsStringIgnoringCase( 'javascript:', html_entity_decode( $clean, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	public function test_event_handlers_and_style_are_stripped() {
		$clean = $this->sanitize_content(
			'<a href="/x" onclick="alert(1)" onmouseover="alert(2)" style="color:red">x</a><span class="a" onclick="alert(3)" style="position:fixed">y</span>'
		);

		$this->assertStringNotContainsStringIgnoringCase( 'onclick', $clean );
		$this->assertStringNotContainsStringIgnoringCase( 'onmouseover', $clean );
		$this->assertStringNotContainsStringIgnoringCase( 'style=', $clean );
		$this->assertSame( '<a href="/x">x</a><span class="a">y</span>', $clean );
	}

	public function test_unlisted_aria_attribute_is_stripped() {
		$clean = $this->sanitize_content( '<a href="/x" aria-hidden="true" role="button">x</a>' );

		$this->assertSame( '<a href="/x">x</a>', $clean );
	}

	public function test_blank_target_without_rel_gets_noopener() {
		$clean = $this->sanitize_content( '<a href="https://example.com" target="_blank">x</a>' );

		$processor = new WP_HTML_Tag_Processor( $clean );
		$this->assertTrue( $processor->next_tag( 'a' ) );
		$this->assertSame( '_blank', $processor->get_attribute( 'target' ) );
		$this->assertSame( 'noopener', $processor->get_attribute( 'rel' ) );
		$this->assertSame( 'https://example.com', $processor->get_attribute( 'href' ) );
	}

	public function test_blank_target_with_other_rel_appends_noopener() {
		$clean = $this->sanitize_content( '<a href="https://example.com" target="_blank" rel="nofollow">x</a>' );

		$this->assertSame( '<a href="https://example.com" target="_blank" rel="nofollow noopener">x</a>', $clean );
	}

	public function test_self_target_is_left_alone() {
		$value = '<a href="/x" target="_self">x</a>';

		$this->assertSame( $value, $this->sanitize_content( $value ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function rich_text_button_provider(): array {
		return array(
			'icon-button'   => array( 'designsetgo/icon-button', '<span class="dsgo-icon-button__text">Book <strong>now</strong> <em>today</em></span>' ),
			'modal-trigger' => array( 'designsetgo/modal-trigger', '<span class="dsgo-modal-trigger__text">Book <strong>now</strong> <em>today</em></span>' ),
		);
	}

	/**
	 * @dataProvider rich_text_button_provider
	 */
	public function test_button_label_keeps_inline_markup( string $block_name ) {
		$sanitized = Block_Configurator::sanitize_attributes(
			array( 'text' => 'Book <strong>now</strong> <em>today</em>' ),
			$block_name
		);

		$this->assertSame( 'Book <strong>now</strong> <em>today</em>', $sanitized['text'] );
	}

	/**
	 * @dataProvider rich_text_button_provider
	 */
	public function test_button_label_markup_survives_prepare_then_build( string $block_name, string $expected ) {
		$definition = Block_Inserter::prepare_block_definition(
			$block_name,
			array( 'text' => 'Book <strong>now</strong> <em>today</em>' ),
			array()
		);

		$markup = Block_Inserter::build_block_markup( $block_name, $definition['attributes'] );

		$this->assertStringContainsString( $expected, $markup );
	}

	/**
	 * @dataProvider rich_text_button_provider
	 */
	public function test_nested_button_label_markup_survives_inner_block_sanitization( string $block_name ) {
		$inner = Block_Inserter::sanitize_inner_block_definitions(
			array(
				array(
					'name'       => $block_name,
					'attributes' => array( 'text' => 'Book <strong>now</strong>' ),
				),
			)
		);

		$this->assertSame( 'Book <strong>now</strong>', $inner[0]['attributes']['text'] );
	}

	/**
	 * @dataProvider rich_text_button_provider
	 */
	public function test_button_label_still_drops_script( string $block_name ) {
		$sanitized = Block_Configurator::sanitize_attributes(
			array( 'text' => 'Go <script>alert(1)</script><strong onclick="alert(1)">now</strong>' ),
			$block_name
		);

		$this->assertStringNotContainsString( '<script', $sanitized['text'] );
		$this->assertStringNotContainsString( 'onclick', $sanitized['text'] );
		$this->assertStringContainsString( '<strong>now</strong>', $sanitized['text'] );
	}

	/**
	 * @dataProvider rich_text_button_provider
	 */
	public function test_button_label_strips_interactive_and_non_editor_formats( string $block_name ) {
		$sanitized = Block_Configurator::sanitize_attributes(
			array( 'text' => 'Book <a href="/y" class="sd-link">now</a> <span class="x">for</span> <mark>two</mark> <code>c</code> <b>b</b> <i>i</i><br>next' ),
			$block_name
		);

		$this->assertSame( 'Book now for two c <b>b</b> <i>i</i><br>next', $sanitized['text'] );
	}

	/**
	 * @dataProvider rich_text_button_provider
	 */
	public function test_button_label_formats_drop_attributes_and_trim( string $block_name ) {
		$sanitized = Block_Configurator::sanitize_attributes(
			array( 'text' => '  <strong class="x" data-a="1">Book</strong> <em id="y">now</em>  ' ),
			$block_name
		);

		$this->assertSame( '<strong>Book</strong> <em>now</em>', $sanitized['text'] );
	}

	/**
	 * @dataProvider rich_text_button_provider
	 */
	public function test_linked_icon_button_label_with_nested_link_stays_a_single_anchor( string $block_name ) {
		$definition = Block_Inserter::prepare_block_definition(
			$block_name,
			array(
				'text' => 'Book <a href="/y">now</a>',
				'url'  => '/book/',
			),
			array()
		);

		$markup = Block_Inserter::build_block_markup( $block_name, $definition['attributes'] );

		$this->assertStringNotContainsString( 'href="/y"', $markup );
		$this->assertStringContainsString( '__text">Book now</span>', $markup );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function interactivity_directive_provider(): array {
		return array(
			'bind href on link'  => array( '<a href="/x" data-wp-interactive="a" data-wp-context=\'{"u":"javascript:alert(1)"}\' data-wp-bind--href="context.u">x</a>' ),
			'on click on link'   => array( '<a href="/x" data-wp-on--click="actions.go">x</a>' ),
			'bind style on span' => array( '<span data-wp-bind--style="state.s" DATA-WP-ON--CLICK="actions.go">x</span>' ),
		);
	}

	/**
	 * @dataProvider interactivity_directive_provider
	 */
	public function test_interactivity_directives_are_stripped( string $value ) {
		$clean = $this->sanitize_content( $value );

		$this->assertStringNotContainsStringIgnoringCase( 'data-wp-', $clean );
	}

	public function test_other_data_attributes_survive_directive_stripping() {
		$clean = $this->sanitize_content( '<a href="/x" data-track="menu" data-wp="button" data-wp-on--click="actions.go" data-wpx="1">x</a>' );

		// The tag processor leaves the removed attribute's separating space behind,
		// so compare attributes rather than bytes.
		$processor = new WP_HTML_Tag_Processor( $clean );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'a' ) ) );
		$this->assertSame( array( 'data-track', 'data-wp', 'data-wpx' ), $processor->get_attribute_names_with_prefix( 'data-' ) );
		$this->assertSame( 'menu', $processor->get_attribute( 'data-track' ) );
		$this->assertSame( 'button', $processor->get_attribute( 'data-wp' ) );
		$this->assertSame( '/x', $processor->get_attribute( 'href' ) );
	}

	public function test_text_stays_plain_without_a_rich_text_block_context() {
		$without_block = Block_Configurator::sanitize_attributes( array( 'text' => 'Curved <em>words</em>' ) );
		$plain_block   = Block_Configurator::sanitize_attributes( array( 'text' => 'Curved <em>words</em>' ), 'designsetgo/text-path' );

		$this->assertSame( 'Curved words', $without_block['text'] );
		$this->assertSame( 'Curved words', $plain_block['text'] );
	}
}

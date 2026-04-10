<?php
/**
 * Test HTML-to-Block Converter: DesignSetGo Handlers
 *
 * Validates that DesignSetGo-specific HTML patterns (section, row, grid, card,
 * accordion, button, form, tabs, timeline, modal, slider, flip-card,
 * progress-bar, counter, divider, icon, pill, fifty-fifty, reveal, etc.) are
 * converted to the correct designsetgo/* block arrays with innerHTML matching
 * each block's save.js output.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\HTML_Converter\Converter;

/**
 * Tests for HTML-to-Block Converter DesignSetGo element handlers.
 */
class HTML_Converter_Dsgo_Handlers_Test extends WP_UnitTestCase {

	/**
	 * Converter instance with DesignSetGo blocks preferred.
	 *
	 * @var Converter
	 */
	private $converter;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->converter = new Converter( array( 'prefer_dsgo' => true ) );
	}

	// ------------------------------------------------------------------
	// Block name mapping tests
	// ------------------------------------------------------------------

	/**
	 * Test section tag maps to designsetgo/section.
	 */
	public function test_section_maps_to_dsgo_section() {
		$blocks = $this->converter->convert_to_blocks( '<section><p>Content</p></section>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/section', $blocks[0]['blockName'] );
		$this->assertNotEmpty( $blocks[0]['innerBlocks'] );
	}

	/**
	 * Test div.columns maps to designsetgo/row.
	 */
	public function test_div_columns_maps_to_dsgo_row() {
		$blocks = $this->converter->convert_to_blocks( '<div class="columns"><div>Col 1</div><div>Col 2</div></div>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/row', $blocks[0]['blockName'] );
	}

	/**
	 * Test div.grid maps to designsetgo/grid.
	 */
	public function test_div_grid_maps_to_dsgo_grid() {
		$blocks = $this->converter->convert_to_blocks( '<div class="grid"><div>Item 1</div></div>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/grid', $blocks[0]['blockName'] );
	}

	/**
	 * Test article maps to designsetgo/card.
	 */
	public function test_article_maps_to_dsgo_card() {
		$blocks = $this->converter->convert_to_blocks( '<article><h3>Title</h3><p>Body</p></article>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/card', $blocks[0]['blockName'] );
		$this->assertNotEmpty( $blocks[0]['innerBlocks'] );
	}

	/**
	 * Test details maps to designsetgo/accordion.
	 */
	public function test_details_maps_to_dsgo_accordion() {
		$html   = '<details><summary>FAQ Question</summary><p>Answer here.</p></details>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/accordion', $blocks[0]['blockName'] );
		$this->assertNotEmpty( $blocks[0]['innerBlocks'] );

		// The inner block should be an accordion-item.
		$item = $blocks[0]['innerBlocks'][0];
		$this->assertSame( 'designsetgo/accordion-item', $item['blockName'] );
		$this->assertSame( 'FAQ Question', $item['attrs']['title'] );
	}

	/**
	 * Test button maps to designsetgo/icon-button.
	 */
	public function test_button_maps_to_dsgo_icon_button() {
		$blocks = $this->converter->convert_to_blocks( '<button>Click Me</button>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/icon-button', $blocks[0]['blockName'] );
		$this->assertSame( 'Click Me', $blocks[0]['attrs']['text'] );
	}

	/**
	 * Test form maps to designsetgo/form-builder.
	 */
	public function test_form_maps_to_dsgo_form_builder() {
		$html   = '<form><input type="text" name="name" /><input type="email" name="email" /></form>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/form-builder', $blocks[0]['blockName'] );
		$this->assertNotEmpty( $blocks[0]['innerBlocks'] );
	}

	/**
	 * Test input type mapping to form field blocks.
	 */
	public function test_form_field_type_mapping() {
		$type_to_block = array(
			'text'     => 'designsetgo/form-text-field',
			'email'    => 'designsetgo/form-email-field',
			'tel'      => 'designsetgo/form-phone-field',
			'number'   => 'designsetgo/form-number-field',
			'url'      => 'designsetgo/form-url-field',
			'date'     => 'designsetgo/form-date-field',
			'time'     => 'designsetgo/form-time-field',
			'checkbox' => 'designsetgo/form-checkbox-field',
			'hidden'   => 'designsetgo/form-hidden-field',
		);

		foreach ( $type_to_block as $type => $expected_block ) {
			$html   = '<form><input type="' . $type . '" name="field" /></form>';
			$blocks = $this->converter->convert_to_blocks( $html );

			$form_block  = $blocks[0];
			$field_block = $form_block['innerBlocks'][0];

			$this->assertSame( $expected_block, $field_block['blockName'], "Failed for input type={$type}" );
		}
	}

	/**
	 * Test textarea maps to form-textarea-field.
	 */
	public function test_textarea_maps_to_form_textarea_field() {
		$html   = '<form><textarea name="message"></textarea></form>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$field = $blocks[0]['innerBlocks'][0];
		$this->assertSame( 'designsetgo/form-textarea-field', $field['blockName'] );
	}

	/**
	 * Test select maps to form-select-field.
	 */
	public function test_select_maps_to_form_select_field() {
		$html   = '<form><select name="choice"><option>A</option></select></form>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$field = $blocks[0]['innerBlocks'][0];
		$this->assertSame( 'designsetgo/form-select-field', $field['blockName'] );
	}

	/**
	 * Test nav with breadcrumb class maps to designsetgo/breadcrumbs.
	 */
	public function test_nav_breadcrumb_maps_to_dsgo_breadcrumbs() {
		$blocks = $this->converter->convert_to_blocks( '<nav class="breadcrumb"><a href="/">Home</a></nav>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/breadcrumbs', $blocks[0]['blockName'] );
	}

	/**
	 * Test div.slider maps to designsetgo/slider with slides.
	 */
	public function test_div_slider_maps_to_dsgo_slider() {
		$html   = '<div class="slider"><div>Slide 1</div><div>Slide 2</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/slider', $blocks[0]['blockName'] );
		$this->assertCount( 2, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'designsetgo/slide', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	/**
	 * Test div.flip-card maps to designsetgo/flip-card with front and back.
	 */
	public function test_div_flip_card_maps_to_dsgo_flip_card() {
		$html   = '<div class="flip-card"><div>Front</div><div>Back</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/flip-card', $blocks[0]['blockName'] );
		$this->assertCount( 2, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'designsetgo/flip-card-front', $blocks[0]['innerBlocks'][0]['blockName'] );
		$this->assertSame( 'designsetgo/flip-card-back', $blocks[0]['innerBlocks'][1]['blockName'] );
	}

	/**
	 * Test div.pill maps to designsetgo/pill.
	 */
	public function test_div_pill_maps_to_dsgo_pill() {
		$blocks = $this->converter->convert_to_blocks( '<div class="pill">Beta</div>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/pill', $blocks[0]['blockName'] );
		$this->assertSame( 'Beta', $blocks[0]['attrs']['text'] );
	}

	/**
	 * Test div.divider maps to designsetgo/divider.
	 */
	public function test_div_divider_maps_to_dsgo_divider() {
		$blocks = $this->converter->convert_to_blocks( '<div class="divider"></div>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'designsetgo/divider', $blocks[0]['blockName'] );
	}

	// ------------------------------------------------------------------
	// save.js innerHTML validation tests
	// ------------------------------------------------------------------

	/**
	 * Test section innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-stack"><div class="dsgo-stack__inner">...</div></div>
	 */
	public function test_section_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<section><p>Content</p></section>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-stack', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-stack__inner', $block['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-designsetgo-section', $block['innerHTML'] );

		// innerContent should have opening + null for each inner block + closing.
		$this->assertSame( 3, count( $block['innerContent'] ), 'Section should have 3 innerContent entries (open + null + close)' );
		$this->assertNull( $block['innerContent'][1] );
		$this->assertStringContainsString( 'dsgo-stack__inner', $block['innerContent'][0] );
	}

	/**
	 * Test row innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-flex"><div class="dsgo-flex__inner">...</div></div>
	 */
	public function test_row_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<div class="row"><div>Col</div></div>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-flex', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-flex__inner', $block['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-designsetgo-row', $block['innerHTML'] );
	}

	/**
	 * Test grid innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-grid dsgo-grid-cols-3 ..."><div class="dsgo-grid__inner">...</div></div>
	 */
	public function test_grid_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<div class="grid"><div>Item</div></div>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-grid', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-grid__inner', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-grid-cols-', $block['innerHTML'] );
	}

	/**
	 * Test card innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-card dsgo-card--default dsgo-card--style-minimal">
	 *   <div class="dsgo-card__inner"><div class="dsgo-card__content">...</div></div>
	 * </div>
	 */
	public function test_card_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<article><p>Card content</p></article>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-card', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-card__inner', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-card__content', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-card--default', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-card--style-minimal', $block['innerHTML'] );
	}

	/**
	 * Test accordion innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-accordion"><div class="dsgo-accordion__items">...</div></div>
	 */
	public function test_accordion_innerhtml_matches_save_js() {
		$html   = '<details><summary>Question</summary><p>Answer</p></details>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-accordion', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-accordion__items', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-allow-multiple', $block['innerHTML'] );

		// Accordion item should have proper markup.
		$item = $block['innerBlocks'][0];
		$this->assertStringContainsString( 'dsgo-accordion-item', $item['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-accordion-item__trigger', $item['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-accordion-item__panel', $item['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-accordion-item__content', $item['innerHTML'] );
		$this->assertStringContainsString( 'Question', $item['innerHTML'] );
	}

	/**
	 * Test icon-button innerHTML matches save.js output.
	 *
	 * save.js: <button class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button" ...>
	 *   <span class="dsgo-icon-button__text">text</span>
	 * </button>
	 */
	public function test_icon_button_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<button>Click Me</button>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-icon-button', $block['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-button', $block['innerHTML'] );
		$this->assertStringContainsString( 'wp-element-button', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-icon-button__text', $block['innerHTML'] );
		$this->assertStringContainsString( 'Click Me', $block['innerHTML'] );
		$this->assertStringContainsString( '<button', $block['innerHTML'] );
	}

	/**
	 * Test pill innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-pill"><span class="dsgo-pill__content">text</span></div>
	 */
	public function test_pill_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<div class="pill">Beta</div>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-pill', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-pill__content', $block['innerHTML'] );
		$this->assertStringContainsString( 'Beta', $block['innerHTML'] );
	}

	/**
	 * Test divider innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-divider dsgo-divider--line">
	 *   <div class="dsgo-divider__container"><div class="dsgo-divider__line"></div></div>
	 * </div>
	 */
	public function test_divider_innerhtml_matches_save_js() {
		$blocks = $this->converter->convert_to_blocks( '<div class="divider"></div>' );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-divider', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-divider--line', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-divider__container', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-divider__line', $block['innerHTML'] );
	}

	/**
	 * Test slider innerHTML matches save.js output.
	 *
	 * save.js: <div class="dsgo-slider" ...><div class="dsgo-slider__viewport"><div class="dsgo-slider__track">...</div></div></div>
	 */
	public function test_slider_innerhtml_matches_save_js() {
		$html   = '<div class="slider"><div>Slide 1</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-slider', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-slider__viewport', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-slider__track', $block['innerHTML'] );

		// Slide inner block.
		$slide = $block['innerBlocks'][0];
		$this->assertStringContainsString( 'dsgo-slide', $slide['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-slide__content', $slide['innerHTML'] );
	}

	/**
	 * Test flip-card innerHTML matches save.js output.
	 */
	public function test_flip_card_innerhtml_matches_save_js() {
		$html   = '<div class="flip-card"><div>Front</div><div>Back</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-flip-card', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-flip-card__container', $block['innerHTML'] );

		$front = $block['innerBlocks'][0];
		$this->assertStringContainsString( 'dsgo-flip-card__face', $front['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-flip-card__front', $front['innerHTML'] );

		$back = $block['innerBlocks'][1];
		$this->assertStringContainsString( 'dsgo-flip-card__back', $back['innerHTML'] );
	}

	/**
	 * Test fifty-fifty innerHTML matches save.js output.
	 */
	public function test_fifty_fifty_innerhtml_matches_save_js() {
		$html   = '<div class="fifty-fifty"><p>Content</p></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-fifty-fifty', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-fifty-fifty__media', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-fifty-fifty__content', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-fifty-fifty__content-inner', $block['innerHTML'] );
	}

	/**
	 * Test timeline innerHTML matches save.js output.
	 */
	public function test_timeline_innerhtml_matches_save_js() {
		$html   = '<div class="timeline"><div>Event 1</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-timeline', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-timeline__line', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-timeline__items', $block['innerHTML'] );
	}

	/**
	 * Test tabs innerHTML matches save.js output.
	 */
	public function test_tabs_innerhtml_matches_save_js() {
		$html   = '<div class="tabs"><div>Tab 1 content</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertStringContainsString( 'dsgo-tabs', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-tabs__nav', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-tabs__panels', $block['innerHTML'] );
		$this->assertStringContainsString( 'role="tablist"', $block['innerHTML'] );
	}

	// ------------------------------------------------------------------
	// Deterministic ID tests (block validation parity with save.js)
	// ------------------------------------------------------------------

	/**
	 * Test accordion-item has uniqueId attribute matching ARIA IDs in markup.
	 *
	 * save.js derives header/panel IDs from the uniqueId attribute:
	 *   headerId = `${uniqueId}-header`
	 *   panelId  = `${uniqueId}-panel`
	 */
	public function test_accordion_item_has_unique_id_matching_aria() {
		$html   = '<details><summary>Q</summary><p>A</p></details>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$item   = $blocks[0]['innerBlocks'][0];

		// uniqueId must be set as a block attribute.
		$this->assertArrayHasKey( 'uniqueId', $item['attrs'] );
		$unique_id = $item['attrs']['uniqueId'];
		$this->assertNotEmpty( $unique_id );

		// IDs in markup must match save.js derivation: uniqueId-header / uniqueId-panel.
		$this->assertStringContainsString( $unique_id . '-header', $item['innerHTML'] );
		$this->assertStringContainsString( $unique_id . '-panel', $item['innerHTML'] );

		// aria-controls must reference the panel ID.
		$this->assertStringContainsString( 'aria-controls="' . $unique_id . '-panel"', $item['innerHTML'] );
		// aria-labelledby must reference the header ID.
		$this->assertStringContainsString( 'aria-labelledby="' . $unique_id . '-header"', $item['innerHTML'] );
	}

	/**
	 * Test form-builder has formId attribute matching data-form-id in markup.
	 *
	 * save.js reads formId from attributes and uses it for data-form-id and
	 * the hidden input value.
	 */
	public function test_form_has_form_id_attribute_matching_markup() {
		$html   = '<form><input type="text" name="name" /></form>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		// formId must be set as a block attribute.
		$this->assertArrayHasKey( 'formId', $block['attrs'] );
		$form_id = $block['attrs']['formId'];
		$this->assertNotEmpty( $form_id );

		// data-form-id and hidden input must match.
		$this->assertStringContainsString( 'data-form-id="' . $form_id . '"', $block['innerHTML'] );
		$this->assertStringContainsString( 'value="' . $form_id . '"', $block['innerHTML'] );
	}

	/**
	 * Test slider innerHTML includes data-* attributes matching save.js output.
	 *
	 * save.js renders data-slides-per-view, data-effect, data-autoplay, etc.
	 * on the outer div. The converter must emit these defaults so stored markup
	 * matches what save.js would produce.
	 */
	public function test_slider_has_data_attributes_matching_save_js() {
		$html   = '<div class="slider"><div>Slide 1</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		// Verify default data-* attributes are present.
		$this->assertStringContainsString( 'data-slides-per-view="1"', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-effect="slide"', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-show-arrows="true"', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-show-dots="true"', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-autoplay="false"', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-loop="true"', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-draggable="true"', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-transition-duration="0.5s"', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-transition-easing="ease-in-out"', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-mobile-breakpoint="768"', $block['innerHTML'] );
		$this->assertStringContainsString( 'data-tablet-breakpoint="1024"', $block['innerHTML'] );

		// Verify CSS custom properties are present.
		$this->assertStringContainsString( '--dsgo-slider-aspect-ratio:16/9', $block['innerHTML'] );
		$this->assertStringContainsString( '--dsgo-slider-gap:20px', $block['innerHTML'] );
		$this->assertStringContainsString( '--dsgo-slider-transition:0.5s', $block['innerHTML'] );

		// Verify modifier classes.
		$this->assertStringContainsString( 'dsgo-slider--classic', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-slider--effect-slide', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-slider--has-arrows', $block['innerHTML'] );
		$this->assertStringContainsString( 'dsgo-slider--has-dots', $block['innerHTML'] );

		// scroll-driven attrs should NOT be present by default.
		$this->assertStringNotContainsString( 'data-scroll-driven', $block['innerHTML'] );
	}

	/**
	 * Test tabs has uniqueId attribute matching class in markup.
	 */
	public function test_tabs_has_unique_id_attribute() {
		$html   = '<div class="tabs"><div>Content</div></div>';
		$blocks = $this->converter->convert_to_blocks( $html );
		$block  = $blocks[0];

		$this->assertArrayHasKey( 'uniqueId', $block['attrs'] );
		$unique_id = $block['attrs']['uniqueId'];
		$this->assertNotEmpty( $unique_id );
		$this->assertStringContainsString( 'dsgo-tabs-' . $unique_id, $block['innerHTML'] );
	}
}

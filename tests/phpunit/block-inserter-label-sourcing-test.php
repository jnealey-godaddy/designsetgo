<?php
/**
 * AI-inserted blocks must mirror the label-sourcing markup from each block's
 * save.js, or the stored HTML won't match save() and the block will fail
 * validation ("Attempt recovery") — or, worse for the DOM-sourced fields,
 * silently drop the author's text.
 *
 * Covers the four blocks whose labels became HTML-sourced:
 * - form-builder / countdown-timer: the redundant `data-*` copy of the label
 *   must NOT be emitted (the label lives only in the button / message div).
 * - card / table-of-contents: a hidden field's element must stay in the markup
 *   with a `--hidden` modifier (not be omitted), so its sourced text survives.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * @group abilities
 */
class Block_Inserter_Label_Sourcing_Test extends WP_UnitTestCase {

	/**
	 * form-builder no longer serializes data-submit-text; the label is the
	 * button text (source: 'text').
	 */
	public function test_form_builder_omits_data_submit_text() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/form-builder',
			array(
				'hasFields'        => true,
				'submitButtonText' => 'Sign Up',
			)
		);
		$this->assertStringNotContainsString( 'data-submit-text', $markup );
		$this->assertStringContainsString( '>Sign Up</button>', $markup );
	}

	/**
	 * countdown-timer no longer serializes data-completion-message; the label is
	 * the message div's text (source: 'text').
	 */
	public function test_countdown_omits_data_completion_message() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/countdown-timer',
			array( 'completionMessage' => 'Offer has ended!' )
		);
		$this->assertStringNotContainsString( 'data-completion-message', $markup );
		$this->assertStringContainsString(
			'<div class="dsgo-countdown-timer__completion-message">Offer has ended!</div>',
			$markup
		);
	}

	/**
	 * A shown card field renders its element with no `--hidden` modifier
	 * (byte-identical to the pre-change markup).
	 */
	public function test_card_shown_title_has_no_hidden_modifier() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/card',
			array(
				'title'     => 'Our Story',
				'showTitle' => true,
			)
		);
		$this->assertStringContainsString( '<h3 class="dsgo-card__title">Our Story</h3>', $markup );
	}

	/**
	 * A hidden card field keeps its element (with the `--hidden` modifier) and
	 * its text, so the DOM-sourced value isn't lost.
	 */
	public function test_card_hidden_title_keeps_element_and_text() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/card',
			array(
				'title'     => 'Our Story',
				'showTitle' => false,
			)
		);
		$this->assertStringContainsString(
			'<h3 class="dsgo-card__title dsgo-card__title--hidden">Our Story</h3>',
			$markup
		);
	}

	/**
	 * A hidden table-of-contents title keeps its element (with the `--hidden`
	 * modifier) and its text.
	 */
	public function test_toc_hidden_title_keeps_element_and_text() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/table-of-contents',
			array(
				'titleText' => 'On This Page',
				'showTitle' => false,
			)
		);
		$this->assertStringContainsString(
			'<div class="dsgo-table-of-contents__title dsgo-table-of-contents__title--hidden">On This Page</div>',
			$markup
		);
	}

	/**
	 * A shown table-of-contents title renders with no `--hidden` modifier.
	 */
	public function test_toc_shown_title_has_no_hidden_modifier() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/table-of-contents',
			array(
				'titleText' => 'On This Page',
				'showTitle' => true,
			)
		);
		$this->assertStringContainsString(
			'<div class="dsgo-table-of-contents__title">On This Page</div>',
			$markup
		);
	}
}

<?php
/**
 * Modal wrapper HTML must mirror save.js's explicit-or-omit overlay contract.
 *
 * The block's save.js writes the backdrop background-color ONLY when the author set
 * overlayColor explicitly; left unset, the stylesheet default owns the scrim
 * (--wp--custom--designsetgo--modal--overlay-color → #000). The Abilities
 * block-insertion path builds the same markup in PHP, so if it bakes a color
 * save() would omit (or vice versa), every AI-inserted modal fails block
 * validation the first time it is opened in the editor ("Attempt Recovery").
 * Same contract for backdrop-filter and for omitting a blank id attribute.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * Modal overlay mirroring tests for the Abilities block-insertion path.
 *
 * @group abilities
 */
class Block_Inserter_Modal_Overlay_Test extends WP_UnitTestCase {

	/**
	 * Generate the modal's opening wrapper HTML.
	 *
	 * @param array $attrs Modal attributes.
	 * @return string Opening wrapper HTML.
	 */
	private function opening_html( array $attrs ) {
		$method = new ReflectionMethod( Block_Inserter::class, 'generate_designsetgo_wrapper_html' );
		$method->setAccessible( true );

		$html = $method->invoke( null, 'designsetgo/modal', $attrs );

		return $html['opening'];
	}

	/**
	 * An unset overlayColor must not bake a background-color (save.js omits it).
	 */
	public function test_backdrop_omits_background_color_when_overlay_color_unset() {
		$html = $this->opening_html( array( 'modalId' => 'm1' ) );

		$this->assertStringContainsString(
			'<div class="dsgo-modal__backdrop" style="opacity:0.8" aria-hidden="true">',
			$html
		);
		$this->assertStringNotContainsString( 'background-color', $html );
	}

	/**
	 * An explicit overlayColor is baked inline, matching save.js.
	 */
	public function test_backdrop_bakes_explicit_overlay_color() {
		$html = $this->opening_html(
			array(
				'modalId'        => 'm1',
				'overlayColor'   => '#ff0000',
				'overlayOpacity' => 90,
			)
		);

		$this->assertStringContainsString(
			'<div class="dsgo-modal__backdrop" style="background-color:#ff0000;opacity:0.9" aria-hidden="true">',
			$html
		);
	}

	/**
	 * Preset shorthand converts to a CSS var, mirroring convertColorToCSSVar.
	 */
	public function test_backdrop_converts_preset_shorthand_like_save_js() {
		$html = $this->opening_html(
			array(
				'modalId'      => 'm1',
				'overlayColor' => 'var:preset|color|accent',
			)
		);

		$this->assertStringContainsString(
			'background-color:var(--wp--preset--color--accent);opacity:0.8',
			$html
		);
	}

	/**
	 * An overlayBlur > 0 emits backdrop-filter after opacity, matching save.js.
	 */
	public function test_backdrop_mirrors_backdrop_filter_for_overlay_blur() {
		$html = $this->opening_html(
			array(
				'modalId'     => 'm1',
				'overlayBlur' => 4,
			)
		);

		$this->assertStringContainsString(
			'<div class="dsgo-modal__backdrop" style="opacity:0.8;backdrop-filter:blur(4px)" aria-hidden="true">',
			$html
		);
	}

	/**
	 * A padded overlayColor is trimmed in BOTH the comment attrs and the HTML.
	 *
	 * The trim must happen in normalize_block_attributes(), before the attrs
	 * array forks into the wrapper-HTML builder and the serialized comment —
	 * a function-local trim in the HTML builder alone would store the padded
	 * value in the comment, and save.js (which never trims) would regenerate
	 * var(--wp--preset--color-- #ff0000) on first parse and fail validation.
	 */
	public function test_padded_overlay_color_is_trimmed_in_comment_attrs_and_html() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/modal',
			array(
				'modalId'      => 'm1',
				'overlayColor' => ' #ff0000',
			)
		);

		$this->assertStringContainsString( '"overlayColor":"#ff0000"', $markup );
		$this->assertStringNotContainsString( '" #ff0000"', $markup );
		$this->assertStringContainsString( 'background-color:#ff0000;opacity:0.8', $markup );
	}

	/**
	 * A whitespace-only overlayColor is dropped from both consumers entirely.
	 */
	public function test_whitespace_only_overlay_color_is_dropped_entirely() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/modal',
			array(
				'modalId'      => 'm1',
				'overlayColor' => '   ',
			)
		);

		$this->assertStringNotContainsString( 'overlayColor', $markup );
		$this->assertStringNotContainsString( 'background-color', $markup );
		$this->assertStringContainsString( 'style="opacity:0.8"', $markup );
	}

	/**
	 * The id attribute mirrors save.js: present when modalId is set, omitted when blank.
	 */
	public function test_id_attribute_present_for_explicit_modal_id_and_omitted_when_blank() {
		$html = $this->opening_html( array( 'modalId' => 'my-modal' ) );
		$this->assertStringContainsString( ' id="my-modal"', $html );

		$blank = $this->opening_html( array( 'modalId' => '' ) );
		$this->assertStringNotContainsString( ' id="', $blank );
		// data-modal-id is still written blank, matching save.js.
		$this->assertStringContainsString( ' data-modal-id=""', $blank );
	}
}

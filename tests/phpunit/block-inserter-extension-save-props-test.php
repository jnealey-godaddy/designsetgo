<?php
/**
 * Editor-extension save-time output must mirror each extension's
 * `blocks.getSaveContent.extraProps` filter exactly.
 *
 * Four extensions add classes/styles/data attributes onto a block's saved
 * root element without being block supports: hover effects, text reveal,
 * expanding background, and SVG patterns (see src/extensions/*). Before this,
 * none were mirrored by the Abilities block-insertion path, so an
 * AI-inserted section/heading/paragraph carrying one of these attributes
 * stored markup the editor considered invalid the moment it was opened.
 *
 * Text reveal, expanding background, and SVG patterns are exercised here
 * through the real build_block_markup() pipeline, because the blocks they
 * target (core/paragraph, core/heading, designsetgo/section) are blocks this
 * ability can actually insert. Hover effects cannot be: its ten target
 * blocks (src/extensions/hover-effects/constants.js SUPPORTED_BLOCKS) are
 * all core blocks that are neither designsetgo/* nor in
 * Block_Inserter::SERIALIZABLE_CORE_BLOCKS (core/heading, core/paragraph
 * only), so get_serialization_gap() refuses to insert every one of them —
 * this ability has no code path that could ever reach one. That half is
 * tested directly against the private mirror via Reflection instead, the
 * same way tests/phpunit/block-inserter-modal-overlay-test.php reaches
 * generate_designsetgo_wrapper_html().
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * Extension save-props mirroring tests for the Abilities block-insertion path.
 *
 * @group abilities
 */
class Block_Inserter_Extension_Save_Props_Test extends WP_UnitTestCase {

	/**
	 * Call the private Block_Inserter::get_extension_save_props() directly.
	 *
	 * @param string $block_name Block name.
	 * @param array  $attributes Block attributes.
	 * @return array{classes: array, styles: array, data: array}
	 */
	private function extension_save_props( string $block_name, array $attributes ): array {
		$method = new ReflectionMethod( Block_Inserter::class, 'get_extension_save_props' );
		$method->setAccessible( true );

		return $method->invoke( null, $block_name, $attributes );
	}

	// -------------------------------------------------------------
	// Text reveal (core/paragraph, core/heading)
	// -------------------------------------------------------------

	/**
	 * A heading with text reveal enabled carries the class and every data
	 * attribute, including the non-default effect.
	 */
	public function test_heading_text_reveal_carries_class_and_data_attributes() {
		$markup = Block_Inserter::build_block_markup(
			'core/heading',
			array(
				'level'                    => 2,
				'content'                  => 'Scroll to reveal',
				'dsgoTextRevealEnabled'    => true,
				'dsgoTextRevealColor'      => 'var:preset|color|accent',
				'dsgoTextRevealSplitMode'  => 'character',
				'dsgoTextRevealTransition' => 90,
				'dsgoTextRevealEffect'     => 'rise',
			)
		);

		$this->assertStringContainsString( 'has-dsgo-text-reveal', $markup );
		$this->assertStringContainsString( 'data-dsgo-text-reveal-enabled="true"', $markup );
		$this->assertStringContainsString( 'data-dsgo-text-reveal-color="var(--wp--preset--color--accent)"', $markup );
		$this->assertStringContainsString( 'data-dsgo-text-reveal-split-mode="character"', $markup );
		$this->assertStringContainsString( 'data-dsgo-text-reveal-transition="90"', $markup );
		$this->assertStringContainsString( 'data-dsgo-text-reveal-effect="rise"', $markup );
	}

	/**
	 * A paragraph with text reveal enabled and every other attribute left at
	 * its default omits data-dsgo-text-reveal-effect entirely — content saved
	 * before that attribute existed carries none, so emitting it
	 * unconditionally would invalidate every such block.
	 */
	public function test_paragraph_text_reveal_defaults_omit_effect_attribute() {
		$markup = Block_Inserter::build_block_markup(
			'core/paragraph',
			array(
				'content'               => 'Fades in as you scroll.',
				'dsgoTextRevealEnabled' => true,
			)
		);

		$this->assertStringContainsString( 'has-dsgo-text-reveal', $markup );
		$this->assertStringContainsString( 'data-dsgo-text-reveal-color="#2563eb"', $markup );
		$this->assertStringContainsString( 'data-dsgo-text-reveal-split-mode="word"', $markup );
		$this->assertStringContainsString( 'data-dsgo-text-reveal-transition="150"', $markup );
		$this->assertStringNotContainsString( 'data-dsgo-text-reveal-effect', $markup );
	}

	/**
	 * Text reveal disabled (the default) emits nothing — no stray class or
	 * data attribute on an ordinary paragraph.
	 */
	public function test_text_reveal_disabled_emits_nothing() {
		$markup = Block_Inserter::build_block_markup( 'core/paragraph', array( 'content' => 'Plain.' ) );

		$this->assertStringNotContainsString( 'text-reveal', $markup );
	}

	// -------------------------------------------------------------
	// Expanding background (designsetgo/section)
	// -------------------------------------------------------------

	/**
	 * A section with expanding background enabled carries the class, the
	 * inline CSS custom property, and every data attribute.
	 */
	public function test_section_expanding_background_carries_class_style_and_data() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'style'                          => array(),
				'dsgoExpandingBgEnabled'         => true,
				'dsgoExpandingBgColor'           => 'var:preset|color|primary',
				'dsgoExpandingBgInitialSize'     => 80,
				'dsgoExpandingBgBlur'            => 20,
				'dsgoExpandingBgSpeed'           => 1.5,
				'dsgoExpandingBgTriggerOffset'   => 10,
				'dsgoExpandingBgCompletionPoint' => 90,
			)
		);

		$this->assertStringContainsString( 'has-dsgo-expanding-background', $markup );
		$this->assertStringContainsString( '--dsgo-expanding-bg-color:var(--wp--preset--color--primary)', $markup );
		$this->assertStringContainsString( 'data-dsgo-expanding-bg-enabled="true"', $markup );
		$this->assertStringContainsString( 'data-dsgo-expanding-bg-initial-size="80"', $markup );
		$this->assertStringContainsString( 'data-dsgo-expanding-bg-blur="20"', $markup );
		$this->assertStringContainsString( 'data-dsgo-expanding-bg-speed="1.5"', $markup );
		$this->assertStringContainsString( 'data-dsgo-expanding-bg-trigger-offset="10"', $markup );
		$this->assertStringContainsString( 'data-dsgo-expanding-bg-completion-point="90"', $markup );
	}

	/**
	 * Enabled with no color override falls back to the plugin default
	 * (#e8e8e8) for both the inline style and the data attribute.
	 */
	public function test_section_expanding_background_default_color_fallback() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'style'                  => array(),
				'dsgoExpandingBgEnabled' => true,
			)
		);

		$this->assertStringContainsString( '--dsgo-expanding-bg-color:#e8e8e8', $markup );
	}

	// -------------------------------------------------------------
	// SVG patterns (designsetgo/section)
	// -------------------------------------------------------------

	/**
	 * A section with a concrete SVG pattern carries the class and every
	 * data attribute the JS save props write.
	 */
	public function test_section_svg_pattern_carries_class_and_data_attributes() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'style'                 => array(),
				'dsgoSvgPatternEnabled' => true,
				'dsgoSvgPatternType'    => 'dot-grid',
				'dsgoSvgPatternColor'   => '#9c92ac',
				'dsgoSvgPatternOpacity' => 0.5,
				'dsgoSvgPatternScale'   => 1.5,
			)
		);

		$this->assertStringContainsString( 'has-dsgo-svg-pattern', $markup );
		$this->assertStringContainsString( 'data-dsgo-svg-pattern="dot-grid"', $markup );
		$this->assertStringContainsString( 'data-dsgo-svg-pattern-color="#9c92ac"', $markup );
		$this->assertStringContainsString( 'data-dsgo-svg-pattern-opacity="0.5"', $markup );
		$this->assertStringContainsString( 'data-dsgo-svg-pattern-scale="1.5"', $markup );
	}

	/**
	 * The "inherit" sentinel only ever saves the class and
	 * data-dsgo-svg-pattern="inherit" — never color/opacity/scale, which
	 * come from the theme at render time via SVG_Pattern_Renderer.
	 */
	public function test_section_svg_pattern_inherit_omits_color_opacity_scale() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'style'                 => array(),
				'dsgoSvgPatternEnabled' => true,
				'dsgoSvgPatternType'    => 'inherit',
			)
		);

		$this->assertStringContainsString( 'data-dsgo-svg-pattern="inherit"', $markup );
		$this->assertStringNotContainsString( 'data-dsgo-svg-pattern-color', $markup );
		$this->assertStringNotContainsString( 'data-dsgo-svg-pattern-opacity', $markup );
		$this->assertStringNotContainsString( 'data-dsgo-svg-pattern-scale', $markup );
	}

	/**
	 * An unrecognised pattern type (not a known pattern id and not
	 * "inherit") saves nothing at all, matching the JS PATTERN_IDS guard.
	 */
	public function test_section_svg_pattern_unknown_type_saves_nothing() {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'style'                 => array(),
				'dsgoSvgPatternEnabled' => true,
				'dsgoSvgPatternType'    => 'not-a-real-pattern',
			)
		);

		$this->assertStringNotContainsString( 'svg-pattern', $markup );
	}

	/**
	 * An unset pattern color omits the data attribute entirely rather than
	 * emitting it empty — mirrors React omitting a prop whose value is
	 * `undefined` (as opposed to expanding background's data attributes,
	 * which fall back to an emitted empty string).
	 */
	public function test_section_svg_pattern_omits_color_attribute_when_unset() {
		$props = $this->extension_save_props(
			'designsetgo/section',
			array(
				'dsgoSvgPatternEnabled' => true,
				'dsgoSvgPatternType'    => 'dot-grid',
			)
		);

		$this->assertArrayNotHasKey( 'data-dsgo-svg-pattern-color', $props['data'] );
		$this->assertSame( '0.4', $props['data']['data-dsgo-svg-pattern-opacity'] );
	}

	// -------------------------------------------------------------
	// Hover effects — unreachable through this ability today
	// -------------------------------------------------------------

	/**
	 * The mirror is correct for a hover-effects target block even though no
	 * such block can currently reach it through insert_block(): all ten
	 * blocks in SUPPORTED_BLOCKS (core/group, core/cover, core/column,
	 * core/columns, core/image, core/button, core/buttons, core/media-text,
	 * core/post-template, core/query) are refused outright by
	 * get_serialization_gap() — they are neither designsetgo/* nor one of
	 * the two SERIALIZABLE_CORE_BLOCKS.
	 */
	public function test_hover_effect_classes_for_a_supported_block() {
		$props = $this->extension_save_props( 'core/image', array( 'dsgoHoverEffect' => 'lift' ) );

		$this->assertSame( array( 'dsgo-hover-effect', 'dsgo-hover-effect--lift' ), $props['classes'] );
	}

	/**
	 * An unrecognised effect value is dropped, matching VALID_EFFECTS in
	 * src/extensions/hover-effects/index.js.
	 */
	public function test_hover_effect_rejects_unknown_value() {
		$props = $this->extension_save_props( 'core/image', array( 'dsgoHoverEffect' => 'not-a-real-effect' ) );

		$this->assertSame( array(), $props['classes'] );
	}

	/**
	 * Designsetgo/icon-button and designsetgo/card are NOT in the
	 * hover-effects SUPPORTED_BLOCKS list at all — they have their own,
	 * unrelated hoverAnimation/hoverBackgroundColor/hoverTextColor
	 * attributes — so a dsgoHoverEffect value there is always inert.
	 */
	public function test_hover_effect_does_not_apply_to_icon_button_or_card() {
		$icon_button = $this->extension_save_props( 'designsetgo/icon-button', array( 'dsgoHoverEffect' => 'lift' ) );
		$card        = $this->extension_save_props( 'designsetgo/card', array( 'dsgoHoverEffect' => 'lift' ) );

		$this->assertSame( array(), $icon_button['classes'] );
		$this->assertSame( array(), $card['classes'] );
	}
}

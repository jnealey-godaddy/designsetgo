<?php
/**
 * Confirms the four post-insert "restore" repairs airo-wp's designAnchors.ts
 * applies to generated pages are unnecessary against this inserter.
 *
 * Site-designer-api (a sibling repo) runs `restoreGenerationBlockMarkup()` over
 * every page the Block_Inserter produces, patching four things it assumed the
 * inserter could get wrong: custom classes missing from the root, a section
 * anchor missing its `id`, the block-animation save props, and a background
 * Section's saved class order/style declarations. Each is pinned here against
 * the ACTUAL inserter output so the workaround can be deleted with evidence
 * instead of assumption.
 *
 * WordPress compares `class` and `style` attribute VALUES as sets, not
 * strings (see @wordpress/blocks isEqualAttributesOfName in
 * api/validation/index.js, and this project's own
 * tests/unit/ability-generated-markup.test.js, which says so explicitly) - so
 * token/declaration ORDER is never a validity requirement. The literal strings
 * below pin today's deterministic output as a regression guard; the
 * assertions that matter for correctness are the class-set and
 * style-declaration-set checks alongside them.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * Generation restore-parity tests.
 *
 * @group abilities
 */
class Block_Inserter_Generation_Restore_Parity_Test extends WP_UnitTestCase {

	/**
	 * Root opening tag of a serialized block, i.e. everything up to and
	 * including the first `>`.
	 *
	 * @param string $markup Serialized block markup.
	 * @return string
	 */
	private function root_opening_tag( string $markup ): string {
		$parsed = parse_blocks( $markup )[0];
		preg_match( '/^<[^>]*>/', $parsed['innerHTML'], $match );
		return $match[0] ?? '';
	}

	/**
	 * Class tokens of the first tag, order-independent (how WordPress itself
	 * compares a `class` attribute during block validation).
	 *
	 * @param string $markup Serialized block markup.
	 * @return string[]
	 */
	private function root_class_set( string $markup ): array {
		$processor = new WP_HTML_Tag_Processor( $markup );
		$processor->next_tag();
		$tokens = preg_split( '/\s+/', trim( (string) $processor->get_attribute( 'class' ) ) );
		$tokens = is_array( $tokens ) ? array_values( array_filter( $tokens ) ) : array();
		sort( $tokens );
		return $tokens;
	}

	/**
	 * Style declarations of the first tag as a property => value map,
	 * order-independent (how WordPress itself compares a `style` attribute
	 * during block validation).
	 *
	 * @param string $markup Serialized block markup.
	 * @return array<string, string>
	 */
	private function root_style_map( string $markup ): array {
		$processor = new WP_HTML_Tag_Processor( $markup );
		$processor->next_tag();
		$style = (string) $processor->get_attribute( 'style' );
		$map   = array();
		foreach ( explode( ';', $style ) as $declaration ) {
			$parts = explode( ':', $declaration, 2 );
			if ( 2 === count( $parts ) ) {
				$map[ trim( $parts[0] ) ] = trim( $parts[1] );
			}
		}
		return $map;
	}

	/**
	 * 1. Custom className tokens reach the root exactly where
	 * useBlockProps.save() spreads them - restoreBlockClasses() in
	 * designAnchors.ts is redundant.
	 */
	public function test_custom_class_name_and_anchor_reach_the_root_exactly(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'className'       => 'sd-site-band ck-visit',
				'anchor'          => 'visit',
				'backgroundColor' => 'primary',
				'style'           => array(),
			)
		);

		$opening = $this->root_opening_tag( $markup );
		$this->assertSame(
			'<div id="visit" class="wp-block-designsetgo-section alignfull dsgo-stack sd-site-band ck-visit has-background has-primary-background-color">',
			$opening
		);
		$this->assertSame(
			array( 'alignfull', 'ck-visit', 'dsgo-stack', 'has-background', 'has-primary-background-color', 'sd-site-band', 'wp-block-designsetgo-section' ),
			$this->root_class_set( $markup )
		);
	}

	/**
	 * 2. The section anchor writes an `id` on the root even on this branch's
	 * oldest supported WordPress (6.7-6.9), where core has no PHP anchor block
	 * support to read it from - restoreSectionAnchor() in designAnchors.ts is
	 * redundant. (See block-inserter-anchor-version-test.php for the
	 * dedicated 6.7-6.9 emulation; this asserts the same contract at the
	 * exact-tag level on the current WordPress.)
	 */
	public function test_anchor_id_is_the_only_addition_when_nothing_else_is_set(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'anchor' => 'pricing',
				'style'  => array(),
			)
		);

		$this->assertSame(
			'<div id="pricing" class="wp-block-designsetgo-section alignfull dsgo-stack">',
			$this->root_opening_tag( $markup )
		);
	}

	/**
	 * 3. Static blocks carry the identical animation classes/data attributes
	 * addAnimationSaveProps() (src/extensions/block-animations/editor.js) adds
	 * at save time - restoreBlockAnimation() in designAnchors.ts is redundant.
	 * Covers entrance+exit+trigger+duration+delay+easing+offset+once+stagger
	 * together, the scroll-linked ("scrubbing") exclusivity rule, and SVG-draw
	 * both alone and alongside an entrance animation.
	 */
	public function test_full_animation_combination_matches_the_editor_save_filter(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'style'                 => array(),
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => 'fadeInUp',
				'dsgoExitAnimation'     => 'fadeOutDown',
				'dsgoAnimationTrigger'  => 'load',
				'dsgoAnimationDuration' => 900,
				'dsgoAnimationDelay'    => 200,
				'dsgoAnimationEasing'   => 'ease-in',
				'dsgoAnimationOffset'   => 50,
				'dsgoAnimationOnce'     => false,
				'dsgoStaggerEnabled'    => true,
				'dsgoStaggerStep'       => 120,
			)
		);

		$this->assertSame(
			array( 'alignfull', 'dsgo-animation-exit-fadeOutDown', 'dsgo-animation-fadeInUp', 'dsgo-stack', 'has-dsgo-animation', 'wp-block-designsetgo-section' ),
			$this->root_class_set( $markup )
		);

		$processor = new WP_HTML_Tag_Processor( $markup );
		$processor->next_tag();
		$this->assertSame( 'true', $processor->get_attribute( 'data-dsgo-animation-enabled' ) );
		$this->assertSame( 'fadeInUp', $processor->get_attribute( 'data-dsgo-entrance-animation' ) );
		$this->assertSame( 'fadeOutDown', $processor->get_attribute( 'data-dsgo-exit-animation' ) );
		$this->assertSame( 'load', $processor->get_attribute( 'data-dsgo-animation-trigger' ) );
		$this->assertSame( '900', $processor->get_attribute( 'data-dsgo-animation-duration' ) );
		$this->assertSame( '200', $processor->get_attribute( 'data-dsgo-animation-delay' ) );
		$this->assertSame( 'ease-in', $processor->get_attribute( 'data-dsgo-animation-easing' ) );
		$this->assertSame( '50', $processor->get_attribute( 'data-dsgo-animation-offset' ) );
		$this->assertSame( 'false', $processor->get_attribute( 'data-dsgo-animation-once' ) );
		$this->assertSame( 'true', $processor->get_attribute( 'data-dsgo-stagger' ) );
		$this->assertSame( '120', $processor->get_attribute( 'data-dsgo-stagger-step' ) );
		// Not scroll-linked, so exempt from the scrubbing exclusivity rule below.
		$this->assertNull( $processor->get_attribute( 'data-dsgo-scroll-linked' ) );
	}

	/** Scroll-linked ("scrubbing") entrance drops the exit animation and stagger, exactly as the editor's save filter does. */
	public function test_scroll_linked_animation_drops_exit_and_stagger(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'style'                 => array(),
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => 'fadeInUp',
				'dsgoAnimationTrigger'  => 'scroll',
				'dsgoScrollLinked'      => true,
				'dsgoExitAnimation'     => 'fadeOutDown',
				'dsgoStaggerEnabled'    => true,
			)
		);

		$processor = new WP_HTML_Tag_Processor( $markup );
		$processor->next_tag();
		$this->assertSame( 'true', $processor->get_attribute( 'data-dsgo-scroll-linked' ) );
		$this->assertNull( $processor->get_attribute( 'data-dsgo-exit-animation' ) );
		$this->assertNull( $processor->get_attribute( 'data-dsgo-stagger' ) );
		$this->assertStringNotContainsString( 'dsgo-animation-exit-', (string) $processor->get_attribute( 'class' ) );
	}

	/** SVG-draw is independent of the entrance/exit system and survives alongside it. */
	public function test_svg_draw_reaches_the_root_alone_and_alongside_animation(): void {
		$alone = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'dsgoSvgDraw' => true,
				'style'       => array(),
			)
		);
		$this->assertSame(
			'<div data-dsgo-svg-draw="true" class="wp-block-designsetgo-section alignfull dsgo-stack">',
			$this->root_opening_tag( $alone )
		);

		$combined           = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'dsgoSvgDraw'           => true,
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => 'fadeIn',
				'style'                 => array(),
			)
		);
		$combined_processor = new WP_HTML_Tag_Processor( $combined );
		$combined_processor->next_tag();
		$this->assertSame( 'true', $combined_processor->get_attribute( 'data-dsgo-svg-draw' ) );
		$this->assertSame( 'true', $combined_processor->get_attribute( 'data-dsgo-animation-enabled' ) );
	}

	/**
	 * 4. A background-image Section's saved root carries the same class set
	 * and style declarations save() emits: no `background-image`/`-size`/
	 * `-position`/`-repeat` (Core renders those at display time via
	 * wp-includes/block-supports/background.php, never in save()), and every
	 * other support/DesignSetGo declaration intact -
	 * restoreSectionBackground() in designAnchors.ts is redundant.
	 */
	public function test_background_image_section_omits_render_only_declarations(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'overlayColor' => 'rgba(0,0,0,0.475)',
				'style'        => array(
					'background' => array(
						'backgroundImage'    => array(
							'url'    => 'https://example.com/hero.jpg',
							'source' => 'file',
						),
						'backgroundSize'     => 'cover',
						'backgroundPosition' => 'center center',
						'backgroundRepeat'   => 'no-repeat',
					),
					'color'      => array( 'text' => '#ffffff' ),
					'spacing'    => array( 'padding' => array( 'top' => '40px' ) ),
				),
				'className'    => 'ck-hero',
			)
		);

		$this->assertSame(
			array( 'alignfull', 'ck-hero', 'dsgo-stack', 'dsgo-stack--has-overlay', 'has-text-color', 'wp-block-designsetgo-section' ),
			$this->root_class_set( $markup )
		);
		$this->assertSame(
			array(
				'--dsgo-overlay-color'   => 'rgba(0,0,0,0.475)',
				'--dsgo-overlay-opacity' => '1',
				'color'                  => '#ffffff',
				'padding-top'            => '40px',
			),
			$this->root_style_map( $markup )
		);

		$style = (string) ( new WP_HTML_Tag_Processor( $markup ) )->get_attribute( 'style' );
		foreach ( array( 'background-image', 'background-size', 'background-position', 'background-repeat' ) as $render_only ) {
			$this->assertStringNotContainsString( $render_only . ':', $style );
		}

		// Confirms the render (not save) path is where the image actually shows up.
		$block = parse_blocks( $markup )[0];
		$this->assertStringContainsString( 'background-image:', render_block( $block ) );
	}
}

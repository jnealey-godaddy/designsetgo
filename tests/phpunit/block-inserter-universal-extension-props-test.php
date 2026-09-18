<?php
/**
 * The inserter must reproduce the save props of the universal extensions.
 *
 * Four extensions declare `'blocks' => 'all'` in includes/extension-configs,
 * so their attributes are registered on nearly every block and their
 * `blocks.getSaveContent.extraProps` filters write a class or style into
 * save() wherever they are set. Block_Inserter::get_extension_save_props()
 * reproduced none of them - it covered animation, mobile order and grid span
 * only, and max width for three hardcoded text blocks.
 *
 * The consequence was not subtle: an agent that set "hide on mobile" on any
 * block wrote stored markup missing the class save() emits, and the author
 * opened the page to "This block contains unexpected or invalid content".
 * The attribute coverage matrix found 287 payloads in that state across 46
 * blocks.
 *
 * The matrix is the guard that keeps this closed. These tests exist because a
 * matrix failure reports "designsetgo/card::dsgoHideOnMobile::0 is invalid"
 * and leaves the reader to work out why; the assertions below say which rule
 * broke.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * Universal extension save-prop mirror tests.
 */
class Block_Inserter_Universal_Extension_Props_Test extends WP_UnitTestCase {

	/**
	 * Build markup for a block with one set of attributes.
	 *
	 * @param string              $block_name Block name.
	 * @param array<string,mixed> $attributes Attributes.
	 * @return string Serialized markup.
	 */
	private function markup( string $block_name, array $attributes ): string {
		return Block_Inserter::build_block_markup( $block_name, $attributes, array() );
	}

	/**
	 * Responsive visibility classes reach the root element.
	 */
	public function test_responsive_visibility_classes_are_emitted(): void {
		$markup = $this->markup(
			'designsetgo/card',
			array(
				'dsgoHideOnDesktop' => true,
				'dsgoHideOnTablet'  => true,
				'dsgoHideOnMobile'  => true,
			)
		);

		$this->assertStringContainsString( 'dsgo-hide-desktop', $markup );
		$this->assertStringContainsString( 'dsgo-hide-tablet', $markup );
		$this->assertStringContainsString( 'dsgo-hide-mobile', $markup );
	}

	/**
	 * A visibility flag left false emits nothing.
	 */
	public function test_responsive_visibility_is_absent_when_unset(): void {
		$markup = $this->markup( 'designsetgo/card', array( 'dsgoHideOnMobile' => false ) );

		$this->assertStringNotContainsString( 'dsgo-hide-', $markup );
	}

	/**
	 * Max width applies beyond the three text blocks it used to be scoped to.
	 */
	public function test_max_width_applies_to_any_non_excluded_block(): void {
		$markup = $this->markup( 'designsetgo/card', array( 'dsgoMaxWidth' => '42rem' ) );

		$this->assertStringContainsString( 'dsgo-has-max-width', $markup );
		$this->assertStringContainsString( 'max-width:42rem', $markup );
	}

	/**
	 * The extension's own exclusion list is honoured.
	 */
	public function test_max_width_skips_excluded_blocks(): void {
		foreach ( array( 'designsetgo/section', 'designsetgo/row', 'designsetgo/grid' ) as $block_name ) {
			$this->assertStringNotContainsString(
				'dsgo-has-max-width',
				$this->markup( $block_name, array( 'dsgoMaxWidth' => '42rem' ) ),
				$block_name . ' is on the extension\'s EXCLUDED_BLOCKS list.'
			);
		}
	}

	/**
	 * Margins follow textAlign OR align, the way the JavaScript does.
	 *
	 * The previous mirror read `textAlign ?? align`, which is a different test:
	 * a block with textAlign 'center' and align 'left' takes the left branch in
	 * JavaScript and the centred branch under `??`.
	 *
	 * @dataProvider provide_max_width_alignments
	 *
	 * @param array<string,mixed> $attributes Attributes to set.
	 * @param string              $expected   Expected margin declaration.
	 */
	public function test_max_width_margins_follow_either_alignment( array $attributes, string $expected ): void {
		$markup = $this->markup( 'designsetgo/card', array_merge( array( 'dsgoMaxWidth' => '42rem' ), $attributes ) );

		$this->assertStringContainsString( $expected, $markup );
	}

	/**
	 * Alignment cases.
	 *
	 * @return array<string, array{0: array<string,mixed>, 1: string}>
	 */
	public function provide_max_width_alignments(): array {
		return array(
			'textAlign left'              => array( array( 'textAlign' => 'left' ), 'margin-left:0' ),
			'align left'                  => array( array( 'align' => 'left' ), 'margin-left:0' ),
			'textAlign right'             => array( array( 'textAlign' => 'right' ), 'margin-right:0' ),
			'align right'                 => array( array( 'align' => 'right' ), 'margin-right:0' ),
			'centred textAlign wins none' => array( array( 'textAlign' => 'center' ), 'margin-left:auto' ),
		);
	}

	/**
	 * Reveal containers and reveal children take different classes.
	 */
	public function test_reveal_control_classes(): void {
		$container = $this->markup(
			'designsetgo/row',
			array(
				'enableRevealOnHover'  => true,
				'revealAnimationType'  => 'collapse',
			)
		);

		$this->assertStringContainsString( 'dsgo-has-reveal', $container );
		$this->assertStringContainsString( 'data-reveal-animation="collapse"', $container );

		$child = $this->markup( 'designsetgo/card', array( 'dsgoRevealOnHover' => true ) );

		$this->assertStringContainsString( 'dsgo-reveal-item', $child );
		$this->assertStringNotContainsString( 'dsgo-has-reveal', $child );
	}

	/**
	 * An enabled container with no animation type falls back to `fade`.
	 */
	public function test_reveal_animation_defaults_to_fade(): void {
		$markup = $this->markup( 'designsetgo/row', array( 'enableRevealOnHover' => true ) );

		$this->assertStringContainsString( 'data-reveal-animation="fade"', $markup );
	}

	/**
	 * Background video writes its marker class and every data attribute.
	 */
	public function test_background_video_props(): void {
		$markup = $this->markup(
			'designsetgo/section',
			array(
				'dsgoVideoUrl'  => 'https://example.com/probe.mp4',
				// Loop, muted, autoplay and mobile-hide all DEFAULT to true in
				// includes/extension-configs/background-video.php, so the false
				// case has to be asked for.
				'dsgoVideoLoop' => false,
			)
		);

		$this->assertStringContainsString( 'dsgo-has-video-background', $markup );
		$this->assertStringContainsString( 'data-video-url="https://example.com/probe.mp4"', $markup );
		// Booleans are written out unconditionally once a URL is present - the
		// JS spreads them all - so an explicit false appears as "false" rather
		// than being omitted, and a defaulted true appears as "true".
		$this->assertStringContainsString( 'data-video-loop="false"', $markup );
		$this->assertStringContainsString( 'data-video-muted="true"', $markup );
	}

	/**
	 * The custom-CSS class carries the same hash the editor computes.
	 *
	 * The expected values come from running hashCode() in
	 * src/extensions/custom-css/index.js directly. They are hardcoded rather
	 * than recomputed so this test fails if the PHP mirror drifts, instead of
	 * two implementations of the same bug agreeing with each other.
	 *
	 * @dataProvider provide_custom_css_hashes
	 *
	 * @param string $block_name Block name.
	 * @param string $css        Custom CSS.
	 * @param string $expected   Expected class.
	 */
	public function test_custom_css_hash_matches_javascript( string $block_name, string $css, string $expected ): void {
		$this->assertStringContainsString(
			$expected,
			$this->markup( $block_name, array( 'dsgoCustomCSS' => $css ) )
		);
	}

	/**
	 * Hash cases, computed with the JavaScript implementation.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public function provide_custom_css_hashes(): array {
		return array(
			'ascii'    => array( 'designsetgo/section', 'color: red;', 'dsgo-custom-css-645zrl' ),
			// charCodeAt() returns UTF-16 code units, so a non-ASCII byte-wise
			// hash would diverge here and nowhere else.
			'unicode'  => array( 'designsetgo/grid', 'héllo ünïcode {}', 'dsgo-custom-css-mvsawd' ),
		);
	}
}

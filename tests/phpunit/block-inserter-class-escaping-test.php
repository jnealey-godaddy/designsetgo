<?php
/**
 * Core-block HTML generation must not let an attribute break out of class="".
 *
 * Block_Inserter::generate_core_block_html() concatenated `textAlign` / `align`
 * straight into a class attribute. A value like `center" onmouseover="alert(1)`
 * therefore closed the attribute and added an event handler. Reaching it needs
 * the `designsetgo/add-block` ability (edit_posts), and `unfiltered_html` for the
 * payload to survive wp_update_post()'s KSES pass — so it is not a privilege
 * escalation past an existing trust boundary, but it is a real escaping hole.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * @group security
 * @group abilities
 */
class Block_Inserter_Class_Escaping_Test extends WP_UnitTestCase {

	/**
	 * @param string $block  Block name.
	 * @param array  $attrs  Attributes.
	 * @return string Generated HTML.
	 */
	private function generate( $block, $attrs ) {
		$method = new ReflectionMethod( Block_Inserter::class, 'generate_core_block_html' );
		$method->setAccessible( true );

		return $method->invoke( null, $block, 'Hello', $attrs );
	}

	/**
	 * @dataProvider hostile_alignment_provider
	 *
	 * @param string $payload Hostile alignment value.
	 */
	public function test_hostile_alignment_cannot_break_out_of_the_class_attribute( $payload ) {
		foreach ( array( 'core/heading', 'core/paragraph' ) as $block ) {
			$html = $this->generate( $block, array( 'textAlign' => $payload, 'align' => $payload ) );

			$this->assertStringNotContainsString( 'onmouseover', $html, $block );
			$this->assertStringNotContainsString( 'onerror', $html, $block );
			$this->assertStringNotContainsString( '<img', $html, $block );

			// The allowlist drops a non-alignment value outright, so no alignment
			// class is emitted at all. (Asserting the payload itself is absent
			// would be wrong for a bare `"`, which the surrounding class="…"
			// legitimately contains.)
			$this->assertStringNotContainsString( 'has-text-align-', $html, $block );
		}
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function hostile_alignment_provider() {
		return array(
			'attribute breakout' => array( 'center" onmouseover="alert(1)' ),
			'tag breakout'       => array( 'center"><img src=x onerror=alert(1)>' ),
			'quote only'         => array( '"' ),
		);
	}

	public function test_legitimate_alignments_still_render() {
		$html = $this->generate( 'core/heading', array( 'textAlign' => 'center' ) );
		$this->assertStringContainsString( 'has-text-align-center', $html );

		$html = $this->generate( 'core/paragraph', array( 'align' => 'right' ) );
		$this->assertStringContainsString( 'has-text-align-right', $html );
	}

	public function test_absent_alignment_emits_no_stray_class() {
		$html = $this->generate( 'core/paragraph', array() );
		$this->assertStringNotContainsString( 'class=', $html );
	}
}

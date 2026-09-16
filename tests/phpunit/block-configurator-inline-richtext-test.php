<?php
/**
 * Rich-text attributes must keep their inline markup through sanitization.
 *
 * Block_Configurator::sanitize_attributes() runs on every real ability entry
 * point before Block_Inserter builds markup, so an attribute that the inserter
 * later emits with wp_kses_post() must be exempted from the strip-tags branch
 * here. Otherwise the kses call downstream is dead code and the formatting the
 * caller asked for is gone before any markup is generated.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Configurator;
use DesignSetGo\Abilities\Block_Inserter;

/**
 * @group abilities
 */
class Block_Configurator_Inline_Richtext_Test extends WP_UnitTestCase {

	/**
	 * Attributes the inserter serializes as rich text, with the markup each must keep.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function inline_attribute_provider(): array {
		return array(
			'content'  => array( 'content', 'Care <em>begins</em> here' ),
			'caption'  => array( 'caption', 'Care <em>begins</em> here' ),
			'citation' => array( 'citation', 'Jane <strong>Doe</strong>' ),
		);
	}

	/**
	 * @dataProvider inline_attribute_provider
	 */
	public function test_inline_markup_survives_sanitization( string $key, string $value ) {
		$sanitized = Block_Configurator::sanitize_attributes( array( $key => $value ) );

		$this->assertSame(
			$value,
			$sanitized[ $key ],
			sprintf( 'The %s attribute lost its inline markup before markup generation.', $key )
		);
	}

	/**
	 * @dataProvider inline_attribute_provider
	 */
	public function test_script_is_still_removed( string $key ) {
		$sanitized = Block_Configurator::sanitize_attributes(
			array( $key => 'Safe <script>alert(1)</script><em>kept</em>' )
		);

		// wp_kses() drops the element and keeps its inner text, which is inert.
		// The property under test is that no executable script element survives.
		$this->assertStringNotContainsString( '<script', $sanitized[ $key ] );
		$this->assertStringContainsString( '<em>kept</em>', $sanitized[ $key ] );
	}

	public function test_image_caption_keeps_markup_through_the_sanitize_then_build_path() {
		$attributes = Block_Configurator::sanitize_attributes(
			array(
				'url'     => 'https://example.com/photo.jpg',
				'alt'     => 'A photo',
				'caption' => 'Care <em>begins</em> here',
			)
		);

		$markup = Block_Inserter::build_block_markup( 'core/image', $attributes );

		$this->assertStringContainsString( 'Care <em>begins</em> here', $markup );
	}

	public function test_quote_citation_keeps_markup_through_the_sanitize_then_build_path() {
		$attributes = Block_Configurator::sanitize_attributes(
			array( 'citation' => 'Jane <strong>Doe</strong>' )
		);

		$markup = Block_Inserter::build_block_markup(
			'core/quote',
			$attributes,
			array(
				array(
					'name'       => 'core/paragraph',
					'attributes' => array( 'content' => 'Quoted text' ),
				),
			)
		);

		$this->assertStringContainsString( '<cite>Jane <strong>Doe</strong></cite>', $markup );
	}
}

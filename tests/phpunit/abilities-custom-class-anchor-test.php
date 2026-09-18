<?php
/**
 * Custom class names and anchors reach the root element of generated markup.
 *
 * The editor's useBlockProps.save() spreads the `className` attribute and the `anchor`
 * attribute (as `id`) onto the same root element as the `has-*` support
 * classes. The serializer used to keep both in the block comment only, so a
 * generated block carrying either failed validation the first time the editor
 * re-saved it, and no stylesheet scoped to the custom class could match.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * Tests for custom class and anchor serialization.
 */
class Abilities_Custom_Class_Anchor_Test extends WP_UnitTestCase {

	/**
	 * Class attribute of the first tag in a markup string.
	 *
	 * @param string $markup Serialized block markup.
	 * @return string[] Class tokens.
	 */
	private function root_classes( string $markup ): array {
		$processor = new WP_HTML_Tag_Processor( $markup );
		$processor->next_tag();
		$tokens = preg_split( '/\s+/', trim( (string) $processor->get_attribute( 'class' ) ) );
		return is_array( $tokens ) ? array_values( array_filter( $tokens ) ) : array();
	}

	/**
	 * Id attribute of the first tag in a markup string.
	 *
	 * @param string $markup Serialized block markup.
	 * @return string|null
	 */
	private function root_id( string $markup ): ?string {
		$processor = new WP_HTML_Tag_Processor( $markup );
		$processor->next_tag();
		$id = $processor->get_attribute( 'id' );
		return is_string( $id ) ? $id : null;
	}

	/**
	 * A container keeps its custom classes and anchor on the root element.
	 */
	public function test_container_root_carries_custom_classes_and_anchor(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array(
				'className'       => 'sd-site-band ck-visit',
				'anchor'          => 'visit',
				'backgroundColor' => 'primary',
			)
		);

		$classes = $this->root_classes( $markup );
		$this->assertContains( 'sd-site-band', $classes );
		$this->assertContains( 'ck-visit', $classes );
		$this->assertContains( 'has-primary-background-color', $classes, 'Support classes still merge alongside the custom ones.' );
		$this->assertSame( 'visit', $this->root_id( $markup ) );
		$this->assertSame( 1, count( array_keys( $classes, 'ck-visit', true ) ), 'Each custom class appears once on the root.' );
	}

	/**
	 * Core text blocks get the same treatment.
	 */
	public function test_core_heading_root_carries_custom_class_and_anchor(): void {
		$markup = Block_Inserter::build_block_markup(
			'core/heading',
			array(
				'level'     => 2,
				'content'   => 'Make it your next stop',
				'className' => 'sd-site-display',
				'anchor'    => 'next-stop',
			)
		);

		$this->assertContains( 'sd-site-display', $this->root_classes( $markup ) );
		$this->assertContains( 'wp-block-heading', $this->root_classes( $markup ) );
		$this->assertSame( 'next-stop', $this->root_id( $markup ) );
	}

	/**
	 * Render-time layout classes never reach stored markup.
	 */
	public function test_layout_classes_stay_out_of_generated_markup(): void {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/grid',
			array(
				'className'      => 'ck-mosaic',
				'desktopColumns' => 6,
			)
		);

		$classes = $this->root_classes( $markup );
		$this->assertContains( 'ck-mosaic', $classes );
		foreach ( $classes as $class_name ) {
			$this->assertStringStartsNotWith( 'is-layout-', $class_name );
			$this->assertStringStartsNotWith( 'wp-container-', $class_name );
		}
	}

	/**
	 * A block without either attribute serializes exactly as before.
	 */
	public function test_blocks_without_custom_class_or_anchor_are_unchanged(): void {
		$markup = Block_Inserter::build_block_markup( 'designsetgo/section', array( 'backgroundColor' => 'primary' ) );

		$this->assertNull( $this->root_id( $markup ) );
		$this->assertSame(
			array( 'wp-block-designsetgo-section', 'alignfull', 'dsgo-stack', 'has-background', 'has-primary-background-color' ),
			$this->root_classes( $markup )
		);
	}
}

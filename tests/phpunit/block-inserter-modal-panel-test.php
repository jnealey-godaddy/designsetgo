<?php
/**
 * Modal wrapper HTML must mirror save.js's off-canvas panel contract.
 *
 * The Abilities block-insertion path builds modal markup in PHP by hand. If it
 * drifts from save.js, every AI-inserted modal fails block validation the first
 * time it is opened in the editor ("Attempt Recovery").
 *
 * Panel mode adds three things to save()'s output, each mirrored here:
 *   1. dsgo-modal--panel and dsgo-modal--panel-{edge} on the block ROOT;
 *   2. a --dsgo-panel-size custom property written as a style attribute AFTER
 *      the class attribute;
 *   3. NO inline width/max-width on .dsgo-modal__content — the panel is sized
 *      by panelSize on the dialog, and an inline width would outrank the
 *      stylesheet rule that makes the content fill the panel.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * Off-canvas panel mirroring tests for the Abilities block-insertion path.
 *
 * @group abilities
 */
class Block_Inserter_Modal_Panel_Test extends WP_UnitTestCase {

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
	 * A default modal must be untouched by the panel feature.
	 */
	public function test_dialog_mode_emits_no_panel_markup() {
		$html = $this->opening_html( array( 'modalId' => 'm1' ) );

		$this->assertStringNotContainsString( 'dsgo-modal--panel', $html );
		$this->assertStringNotContainsString( '--dsgo-panel-size', $html );
	}

	/**
	 * A default modal keeps its inline content dimensions.
	 */
	public function test_dialog_mode_keeps_inline_content_dimensions() {
		$html = $this->opening_html( array( 'modalId' => 'm1' ) );

		$this->assertStringContainsString( 'width:600px', $html );
		$this->assertStringContainsString( 'max-width:90vw', $html );
	}

	/**
	 * Panel mode adds both mode classes to the root.
	 *
	 * @dataProvider edge_provider
	 *
	 * @param string $edge Panel edge.
	 */
	public function test_panel_mode_adds_mode_classes( $edge ) {
		$html = $this->opening_html(
			array(
				'modalId'     => 'm1',
				'displayMode' => 'panel',
				'panelEdge'   => $edge,
			)
		);

		$this->assertStringContainsString(
			'class="wp-block-designsetgo-modal dsgo-modal dsgo-modal--panel dsgo-modal--panel-' . $edge . '"',
			$html
		);
	}

	/**
	 * Every supported edge.
	 *
	 * @return array[] Edge names.
	 */
	public function edge_provider() {
		return array(
			'left'   => array( 'left' ),
			'right'  => array( 'right' ),
			'top'    => array( 'top' ),
			'bottom' => array( 'bottom' ),
		);
	}

	/**
	 * The size property is written after the class attribute, as save() does.
	 */
	public function test_panel_size_property_follows_the_class_attribute() {
		$html = $this->opening_html(
			array(
				'modalId'     => 'm1',
				'displayMode' => 'panel',
				'panelSize'   => '30rem',
			)
		);

		$this->assertStringContainsString(
			'dsgo-modal--panel-right" style="--dsgo-panel-size:30rem"',
			$html
		);
	}

	/**
	 * An omitted panelSize falls back to the same default as block.json.
	 */
	public function test_panel_size_defaults_to_24rem() {
		$html = $this->opening_html(
			array(
				'modalId'     => 'm1',
				'displayMode' => 'panel',
			)
		);

		$this->assertStringContainsString( '--dsgo-panel-size:24rem', $html );
	}

	/**
	 * An unrecognised edge is clamped, mirroring save.js.
	 *
	 * @dataProvider bad_edge_provider
	 *
	 * @param mixed $edge Unrecognised panelEdge value.
	 */
	public function test_unrecognised_panel_edge_clamps_to_default( $edge ) {
		$html = $this->opening_html(
			array(
				'modalId'     => 'm1',
				'displayMode' => 'panel',
				'panelEdge'   => $edge,
			)
		);

		$this->assertStringContainsString( 'dsgo-modal--panel-right"', $html );
	}

	/**
	 * Values the inspector cannot produce but callers can.
	 *
	 * @return array[] Bad edge values.
	 */
	public function bad_edge_provider() {
		return array(
			'unknown word' => array( 'diagonal' ),
			'empty string' => array( '' ),
			'wrong case'   => array( 'RIGHT' ),
			'injection'    => array( 'left" onload="x' ),
		);
	}

	/**
	 * Panel mode must not write dialog dimensions onto the content.
	 */
	public function test_panel_mode_omits_inline_content_dimensions() {
		$html = $this->opening_html(
			array(
				'modalId'     => 'm1',
				'displayMode' => 'panel',
				'width'       => '600px',
				'maxWidth'    => '90vw',
			)
		);

		$this->assertStringContainsString(
			'<div class="dsgo-modal__content" style="border-style:none;border-width:0px">',
			$html
		);
		$this->assertStringNotContainsString( 'width:600px', $html );
		$this->assertStringNotContainsString( 'max-width:90vw', $html );
	}
}

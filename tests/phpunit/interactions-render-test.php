<?php
/**
 * Interaction layers render-time injection tests.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * Tests that dynamic blocks receive the interactions data attribute.
 */
class Test_Interactions_Render extends WP_UnitTestCase {

	/**
	 * Interactions instance.
	 *
	 * @var \DesignSetGo\Interactions
	 */
	private $interactions;

	/**
	 * A representative interaction list.
	 *
	 * @var array
	 */
	private $sample;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$this->interactions = new \DesignSetGo\Interactions();
		$this->sample       = array(
			array(
				'id'             => 'abc',
				'trigger'        => 'click',
				'targetMode'     => 'selector',
				'targetSelector' => '.panel',
				'action'         => 'toggleClass',
				'value'          => 'is-open',
			),
		);
	}

	/**
	 * Build a parsed-block array carrying interactions.
	 *
	 * @param array $interactions Interaction list.
	 * @return array Parsed block.
	 */
	private function block( $interactions ) {
		return array(
			'blockName' => 'designsetgo/breadcrumbs',
			'attrs'     => array( 'dsgoInteractions' => $interactions ),
		);
	}

	/**
	 * A dynamic block's rendered markup gains the attribute.
	 *
	 * Dynamic blocks return null from save(), so the JS extraProps filter never
	 * runs for them and this is the only path that can add the attribute.
	 */
	public function test_injects_into_dynamic_block_markup() {
		$html = $this->interactions->inject_interactions(
			'<nav class="wp-block-designsetgo-breadcrumbs">crumbs</nav>',
			$this->block( $this->sample )
		);

		$this->assertStringContainsString( 'data-dsgo-interactions=', $html );
		$this->assertStringContainsString( 'toggleClass', $html );
	}

	/**
	 * The attribute lands on the outermost tag, not an inner one.
	 */
	public function test_targets_the_outermost_tag() {
		$html = $this->interactions->inject_interactions(
			'<div class="outer"><span class="inner">x</span></div>',
			$this->block( $this->sample )
		);

		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag();

		$this->assertSame( 'DIV', $processor->get_tag() );
		$this->assertNotNull( $processor->get_attribute( 'data-dsgo-interactions' ) );
	}

	/**
	 * The emitted JSON round-trips to the original interaction.
	 */
	public function test_emits_valid_json() {
		$html = $this->interactions->inject_interactions(
			'<div>x</div>',
			$this->block( $this->sample )
		);

		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag();
		$decoded = json_decode( $processor->get_attribute( 'data-dsgo-interactions' ), true );

		$this->assertIsArray( $decoded );
		$this->assertSame( 'abc', $decoded[0]['id'] );
		$this->assertSame( 'toggleClass', $decoded[0]['action'] );
		$this->assertSame( '.panel', $decoded[0]['targetSelector'] );
	}

	/**
	 * A static block already carrying the attribute is left untouched.
	 */
	public function test_leaves_static_block_markup_alone() {
		$original = '<div data-dsgo-interactions="[{&quot;id&quot;:&quot;kept&quot;}]">x</div>';

		$this->assertSame(
			$original,
			$this->interactions->inject_interactions( $original, $this->block( $this->sample ) )
		);
	}

	/**
	 * Blocks without interactions are returned byte-identically.
	 */
	public function test_ignores_blocks_without_interactions() {
		$original = '<div class="plain">x</div>';

		$this->assertSame(
			$original,
			$this->interactions->inject_interactions( $original, array( 'attrs' => array() ) )
		);
		$this->assertSame(
			$original,
			$this->interactions->inject_interactions( $original, $this->block( array() ) )
		);
	}

	/**
	 * Unknown keys are dropped rather than echoed back into the attribute.
	 */
	public function test_strips_unknown_keys() {
		$html = $this->interactions->inject_interactions(
			'<div>x</div>',
			$this->block(
				array(
					array(
						'id'      => 'a',
						'action'  => 'hide',
						'surpise' => '<script>alert(1)</script>',
					),
				)
			)
		);

		$this->assertStringNotContainsString( 'surpise', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * A non-scalar field does not raise "Array to string conversion".
	 *
	 * Block attributes are parsed from post content and are not validated
	 * against the block schema, so a direct REST write can put any JSON type
	 * here. A PHP notice would leak into the response when display_errors
	 * is on.
	 */
	public function test_tolerates_non_scalar_field_values() {
		$html = $this->interactions->inject_interactions(
			'<div>x</div>',
			$this->block(
				array(
					array(
						'id'             => array( 'not', 'a', 'string' ),
						'action'         => 'hide',
						'targetSelector' => array( 'nested' => true ),
						'offset'         => 'not-a-number',
					),
				)
			)
		);

		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag();
		$decoded = json_decode( $processor->get_attribute( 'data-dsgo-interactions' ), true );

		$this->assertSame( '', $decoded[0]['id'] );
		$this->assertSame( '', $decoded[0]['targetSelector'] );
		// json_encode drops a zero fraction, so this round-trips as int.
		$this->assertSame( 0, $decoded[0]['offset'] );
		$this->assertSame( 'hide', $decoded[0]['action'] );
	}

	/**
	 * A numeric offset survives normalisation.
	 */
	public function test_keeps_a_numeric_offset() {
		$html = $this->interactions->inject_interactions(
			'<div>x</div>',
			$this->block(
				array(
					array(
						'action' => 'scrollTo',
						'offset' => 80,
					),
				)
			)
		);

		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag();
		$decoded = json_decode( $processor->get_attribute( 'data-dsgo-interactions' ), true );

		$this->assertSame( 80, $decoded[0]['offset'] );
	}

	/**
	 * Empty or non-string content is passed straight through.
	 */
	public function test_handles_empty_content() {
		$this->assertSame(
			'',
			$this->interactions->inject_interactions( '', $this->block( $this->sample ) )
		);
	}

	/**
	 * Content with no tag at all cannot be annotated and is left alone.
	 */
	public function test_handles_content_without_a_tag() {
		$this->assertSame(
			'just text',
			$this->interactions->inject_interactions( 'just text', $this->block( $this->sample ) )
		);
	}

	/**
	 * The filter is registered ahead of the asset-enqueue check.
	 *
	 * Assets::maybe_enqueue_frontend_on_render() looks for the attribute in
	 * rendered content at priority 10 to decide whether to load the runtime.
	 * Injecting after that point would render interactions inert.
	 */
	public function test_runs_before_the_enqueue_check() {
		$this->assertLessThan(
			10,
			has_filter( 'render_block', array( $this->interactions, 'inject_interactions' ) )
		);
	}
}

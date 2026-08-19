<?php
/**
 * Server-rendered blocks must carry their author-set animation markup.
 *
 * A static block bakes its animation classes/data-attributes into saved HTML
 * via the `blocks.getSaveContent.extraProps` JS filter. That filter never runs
 * for a dynamic block, so the same settings have to be applied at render time
 * or they reach the frontend as nothing at all.
 *
 * @package DesignSetGo
 * @group   animation
 */

/**
 * Render-time application of explicit animation settings.
 *
 * @group animation
 */
class DesignSetGo_Animation_Dynamic_Block_Test extends WP_UnitTestCase {

	/**
	 * Injector under test.
	 *
	 * @var \DesignSetGo\Animation_Defaults_Injector
	 */
	private $injector;

	/**
	 * Register a dynamic and a static block type to inject against.
	 */
	public function set_up() {
		parent::set_up();

		$this->injector = new \DesignSetGo\Animation_Defaults_Injector();

		register_block_type(
			'dsgotest/dynamic',
			array(
				'render_callback' => static function () {
					return '<div class="wp-block-dsgotest-dynamic">rendered</div>';
				},
			)
		);

		register_block_type( 'dsgotest/static', array() );
	}

	/**
	 * Drop the test block types.
	 */
	public function tear_down() {
		unregister_block_type( 'dsgotest/dynamic' );
		unregister_block_type( 'dsgotest/static' );
		parent::tear_down();
	}

	/**
	 * Build a parsed-block array for the injector.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Block attributes.
	 * @return array Parsed block.
	 */
	private function block( $name, $attrs ) {
		return array(
			'blockName' => $name,
			'attrs'     => $attrs,
		);
	}

	// -----------------------------------------------------------------
	// The parts helper — must mirror addAnimationSaveProps() in editor.js.
	// -----------------------------------------------------------------

	/**
	 * SVG drawing is independent of the entrance/exit system.
	 */
	public function test_svg_draw_is_emitted_even_when_animations_are_disabled() {
		$parts = designsetgo_get_animation_parts( array( 'dsgoSvgDraw' => true ) );

		$this->assertSame( 'true', $parts['attrs']['data-dsgo-svg-draw'] );
		$this->assertNotContains( 'has-dsgo-animation', $parts['classes'] );
	}

	/**
	 * Scrubbing needs an entrance animation to drive.
	 */
	public function test_scroll_linked_is_emitted_with_an_entrance_animation() {
		$parts = designsetgo_get_animation_parts(
			array(
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => 'fadeInUp',
				'dsgoScrollLinked'      => true,
			)
		);

		$this->assertSame( 'true', $parts['attrs']['data-dsgo-scroll-linked'] );
	}

	/**
	 * Scrubbing with nothing to scrub emits nothing.
	 */
	public function test_scroll_linked_without_an_entrance_animation_is_dropped() {
		$parts = designsetgo_get_animation_parts(
			array(
				'dsgoAnimationEnabled' => true,
				'dsgoScrollLinked'     => true,
			)
		);

		$this->assertArrayNotHasKey( 'data-dsgo-scroll-linked', $parts['attrs'] );
	}

	/**
	 * The exit trigger never fires for a scrubbed block.
	 */
	public function test_scrubbing_drops_the_exit_animation() {
		$parts = designsetgo_get_animation_parts(
			array(
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => 'fadeInUp',
				'dsgoExitAnimation'     => 'fadeOut',
				'dsgoScrollLinked'      => true,
			)
		);

		$this->assertArrayNotHasKey( 'data-dsgo-exit-animation', $parts['attrs'] );
		$this->assertNotContains( 'dsgo-animation-exit-fadeOut', $parts['classes'] );
	}

	/**
	 * A non-default step travels with the stagger flag.
	 */
	public function test_stagger_is_emitted_with_a_non_default_step() {
		$parts = designsetgo_get_animation_parts(
			array(
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => 'fadeInUp',
				'dsgoStaggerEnabled'    => true,
				'dsgoStaggerStep'       => 120,
			)
		);

		$this->assertSame( 'true', $parts['attrs']['data-dsgo-stagger'] );
		$this->assertSame( '120', $parts['attrs']['data-dsgo-stagger-step'] );
	}

	/**
	 * Default values stay out of the markup, as on the save path.
	 */
	public function test_default_stagger_step_is_left_out_of_the_markup() {
		$parts = designsetgo_get_animation_parts(
			array(
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => 'fadeInUp',
				'dsgoStaggerEnabled'    => true,
				'dsgoStaggerStep'       => 80,
			)
		);

		$this->assertSame( 'true', $parts['attrs']['data-dsgo-stagger'] );
		$this->assertArrayNotHasKey( 'data-dsgo-stagger-step', $parts['attrs'] );
	}

	/**
	 * The two want the keyframes on different elements.
	 */
	public function test_stagger_and_scrubbing_are_mutually_exclusive() {
		$parts = designsetgo_get_animation_parts(
			array(
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => 'fadeInUp',
				'dsgoStaggerEnabled'    => true,
				'dsgoScrollLinked'      => true,
			)
		);

		$this->assertSame( 'true', $parts['attrs']['data-dsgo-scroll-linked'] );
		$this->assertArrayNotHasKey( 'data-dsgo-stagger', $parts['attrs'] );
	}

	/**
	 * Stagger needs an animation to move onto the children.
	 */
	public function test_stagger_without_any_animation_chosen_is_dropped() {
		$parts = designsetgo_get_animation_parts(
			array(
				'dsgoAnimationEnabled' => true,
				'dsgoStaggerEnabled'   => true,
			)
		);

		$this->assertArrayNotHasKey( 'data-dsgo-stagger', $parts['attrs'] );
	}

	// -----------------------------------------------------------------
	// The injector — explicit settings on a server-rendered block.
	// -----------------------------------------------------------------

	/**
	 * The regression this fix exists for.
	 */
	public function test_dynamic_block_gets_its_explicit_animation_injected() {
		$html = $this->injector->inject(
			'<div class="wp-block-dsgotest-dynamic">rendered</div>',
			$this->block(
				'dsgotest/dynamic',
				array(
					'dsgoAnimationEnabled'  => true,
					'dsgoEntranceAnimation' => 'fadeInUp',
				)
			)
		);

		$this->assertStringContainsString( 'has-dsgo-animation', $html );
		$this->assertStringContainsString( 'dsgo-animation-fadeInUp', $html );
		$this->assertStringContainsString( 'data-dsgo-animation-enabled="true"', $html );
		$this->assertStringContainsString( 'data-dsgo-entrance-animation="fadeInUp"', $html );
	}

	/**
	 * Stagger reaches server-rendered blocks too.
	 */
	public function test_dynamic_block_gets_stagger_and_step_injected() {
		$html = $this->injector->inject(
			'<div class="wp-block-dsgotest-dynamic">rendered</div>',
			$this->block(
				'dsgotest/dynamic',
				array(
					'dsgoAnimationEnabled'  => true,
					'dsgoEntranceAnimation' => 'fadeInUp',
					'dsgoStaggerEnabled'    => true,
					'dsgoStaggerStep'       => 120,
				)
			)
		);

		$this->assertStringContainsString( 'data-dsgo-stagger="true"', $html );
		$this->assertStringContainsString( 'data-dsgo-stagger-step="120"', $html );
	}

	/**
	 * SVG drawing works with the animation system switched off.
	 */
	public function test_dynamic_block_gets_svg_draw_without_any_animation() {
		$html = $this->injector->inject(
			'<div class="wp-block-dsgotest-dynamic">rendered</div>',
			$this->block( 'dsgotest/dynamic', array( 'dsgoSvgDraw' => true ) )
		);

		$this->assertStringContainsString( 'data-dsgo-svg-draw="true"', $html );
		$this->assertStringNotContainsString( 'has-dsgo-animation', $html );
	}

	/**
	 * The save filter already baked these in. Injecting again would duplicate
	 * the class list and fight the markup the editor validates against.
	 */
	public function test_static_block_is_left_alone() {
		$saved = '<div class="wp-block-dsgotest-static has-dsgo-animation dsgo-animation-fadeInUp" data-dsgo-animation-enabled="true">saved</div>';

		$html = $this->injector->inject(
			$saved,
			$this->block(
				'dsgotest/static',
				array(
					'dsgoAnimationEnabled'  => true,
					'dsgoEntranceAnimation' => 'fadeInUp',
				)
			)
		);

		$this->assertSame( $saved, $html );
	}

	/**
	 * Running twice must be a no-op.
	 */
	public function test_already_injected_markup_is_not_double_applied() {
		$content = '<div class="wp-block-dsgotest-dynamic has-dsgo-animation dsgo-animation-fadeInUp">rendered</div>';

		$html = $this->injector->inject(
			$content,
			$this->block(
				'dsgotest/dynamic',
				array(
					'dsgoAnimationEnabled'  => true,
					'dsgoEntranceAnimation' => 'fadeInUp',
				)
			)
		);

		$this->assertSame( $content, $html );
	}

	/**
	 * Hybrid types (core/button declares both a render_callback and a save())
	 * read as dynamic, but their stored markup is authored by the save filter.
	 */
	public function test_saved_svg_draw_markup_is_not_reapplied() {
		$content = '<div class="wp-block-dsgotest-dynamic" data-dsgo-svg-draw="true">rendered</div>';

		$html = $this->injector->inject(
			$content,
			$this->block( 'dsgotest/dynamic', array( 'dsgoSvgDraw' => true ) )
		);

		$this->assertSame( $content, $html );
	}

	/**
	 * An explicit opt-out beats any configured theme default.
	 */
	public function test_opt_out_still_suppresses_everything() {
		$content = '<div class="wp-block-dsgotest-dynamic">rendered</div>';

		$html = $this->injector->inject(
			$content,
			$this->block( 'dsgotest/dynamic', array( 'dsgoAnimationOptOut' => true ) )
		);

		$this->assertSame( $content, $html );
	}
}

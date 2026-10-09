<?php
/** Layout CSS is printed outside block content, including late renders.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Layout_Renderer;
use DesignSetGo\Layout_Support;

/** Stylesheet lifecycle and empty-query-template coverage. */
class Layout_Renderer_Test extends WP_UnitTestCase {
	/** Feature must load with the plugin. */
	public function set_up(): void {
		parent::set_up();
		$this->assertTrue( class_exists( Layout_Renderer::class ), 'Layout renderer must be loaded.' );
	}

	/** First render adds a root class without adding grid children. */
	public function test_render_syncs_root_class_without_style_siblings(): void {
		$renderer = new Layout_Renderer();
		$layout   = array( 'mobile' => array( 'width' => '90%' ) );
		$block    = array(
			'blockName' => 'core/group',
			'attrs' => array( 'dsgoLayout' => $layout ),
		);
		$html     = $renderer->collect( '<div class="wp-block-group dsgo-layout-stale"><p>Text</p></div>', $block );
		$this->assertStringContainsString( Layout_Support::class_name( 'core/group', $layout ), $html );
		$this->assertStringNotContainsString( 'dsgo-layout-stale', $html );
		$this->assertStringNotContainsString( '<style', $html );
	}

	/** Rules discovered after head print once in the footer. */
	public function test_late_rules_are_printed_once(): void {
		$renderer = new Layout_Renderer();
		ob_start();
		$renderer->print_styles();
		$this->assertSame( '', ob_get_clean() );
		$block = array(
			'blockName' => 'core/group',
			'attrs' => array( 'dsgoLayout' => array( 'desktop' => array( 'height' => '12rem' ) ) ),
		);
		$renderer->collect( '<div></div>', $block );
		$renderer->collect( '<div></div>', $block );
		ob_start();
		$renderer->print_styles();
		$css = ob_get_clean();
		$this->assertSame( 1, substr_count( $css, '<style' ) );
		$this->assertSame( 1, substr_count( $css, 'height:12rem!important' ) );
		$renderer->collect( '<div></div>', $block );
		ob_start();
		$renderer->print_styles();
		$this->assertSame( '', ob_get_clean() );
	}

	/** Different layouts must never share known colliding 32-bit selectors. */
	public function test_distinct_variables_have_independent_rules(): void {
		$renderer = new Layout_Renderer();
		$classes  = array();
		foreach ( array( 'var(--Aa)', 'var(--BB)' ) as $width ) {
			$layout    = array( 'desktop' => array( 'width' => $width ) );
			$classes[] = Layout_Support::class_name( 'core/group', $layout );
			$renderer->collect( '<div></div>', array( 'blockName' => 'core/group', 'attrs' => array( 'dsgoLayout' => $layout ) ) );
		}
		ob_start();
		$renderer->print_styles();
		$css = ob_get_clean();
		$this->assertNotSame( $classes[0], $classes[1] );
		$this->assertStringContainsString( 'width:var(--Aa)!important', $css );
		$this->assertStringContainsString( 'width:var(--BB)!important', $css );
	}

	/** Collect static templates even when a query initially renders zero items. */
	public function test_unrendered_query_templates_are_precollected(): void {
		$renderer = new Layout_Renderer();
		$block    = array(
			'blockName'   => 'designsetgo/query',
			'attrs'       => array(),
			'innerBlocks' => array(
				array(
					'blockName' => 'core/group',
					'attrs' => array( 'dsgoLayout' => array( 'desktop' => array( 'width' => '20rem' ) ) ),
				),
			),
		);
		$this->assertSame( $block, $renderer->collect_tree( $block ) );
		ob_start();
		$renderer->print_styles();
		$this->assertStringContainsString( 'width:20rem!important', ob_get_clean() );
	}

	/**
	 * Footer render callbacks must finish before the final stylesheet flush.
	 *
	 * @expectedDeprecated the_block_template_skip_link
	 */
	public function test_footer_render_at_later_priority_prints_css(): void {
		$renderer = new Layout_Renderer();
		$callback = static function () use ( $renderer ) {
			$renderer->collect(
				'<div></div>',
				array(
					'blockName' => 'core/group',
					'attrs' => array( 'dsgoLayout' => array( 'desktop' => array( 'height' => '123px' ) ) ),
				)
			);
		};
		add_action( 'wp_footer', $callback, 40 );
		ob_start();
		do_action( 'wp_footer' );
		$css = ob_get_clean();
		remove_action( 'wp_footer', $callback, 40 );
		$this->assertStringContainsString( 'height:123px!important', $css );
	}
}

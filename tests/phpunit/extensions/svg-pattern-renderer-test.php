<?php
/**
 * Tests for the SVG_Pattern_Renderer server-side inherit resolution.
 *
 * Covers the theme-inheritance path added alongside the JS resolver: a block
 * whose `data-dsgo-svg-pattern` is "inherit" pulls its preset (type/color/
 * opacity/scale) from settings.custom.designsetgo.svgPattern, falling back to
 * the in-plugin defaults (dot-grid / #9c92ac / 0.4 / 1) per field. Explicit
 * patterns must keep rendering byte-for-byte as before.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\SVG_Pattern_Renderer;

/**
 * SVG Pattern Renderer Test Case.
 */
class SvgPatternRendererTest extends WP_UnitTestCase {

	/**
	 * Renderer instance.
	 *
	 * @var SVG_Pattern_Renderer
	 */
	private SVG_Pattern_Renderer $renderer;

	/**
	 * Set up test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->renderer = new SVG_Pattern_Renderer();
	}

	/**
	 * Remove any injected theme.json data between tests.
	 */
	public function tear_down(): void {
		remove_all_filters( 'wp_theme_json_data_theme' );
		wp_clean_theme_json_cache();
		parent::tear_down();
	}

	/**
	 * Build a minimal block array for the render_block filter.
	 *
	 * @param string $html  Saved block HTML.
	 * @param array  $attrs Block attributes.
	 * @return array
	 */
	private function make_block( string $html, array $attrs = array() ): array {
		return array(
			'blockName'   => 'designsetgo/section',
			'attrs'       => $attrs,
			'innerBlocks' => array(),
			'innerHTML'   => $html,
		);
	}

	/**
	 * Inject the given theme preset under settings.custom.designsetgo.svgPattern.
	 *
	 * @param array $preset Preset array (type/color/opacity/scale).
	 */
	private function set_theme_preset( array $preset ): void {
		add_filter(
			'wp_theme_json_data_theme',
			static function ( $theme_json ) use ( $preset ) {
				return $theme_json->update_with(
					array(
						'version'  => 2,
						'settings' => array(
							'custom' => array(
								'designsetgo' => array(
									'svgPattern' => $preset,
								),
							),
						),
					)
				);
			}
		);
		wp_clean_theme_json_cache();
	}

	/**
	 * Inherit with no theme preset falls back to the dot-grid default (24x24).
	 */
	public function test_inherit_falls_back_to_dot_grid() {
		$html   = '<div class="has-dsgo-svg-pattern" data-dsgo-svg-pattern="inherit"></div>';
		$result = $this->renderer->inject_svg_pattern( $html, $this->make_block( $html ) );

		$this->assertStringContainsString( '--dsgo-svg-pattern-image:url(&quot;data:image/svg+xml,', $result );
		$this->assertStringContainsString( '--dsgo-svg-pattern-size:24px 24px', $result );
	}

	/**
	 * Inherit adopts the themed pattern type + scale (waves 120x40 @ scale 2 => 240x80).
	 */
	public function test_inherit_adopts_theme_preset() {
		$this->set_theme_preset(
			array(
				'type'    => 'waves',
				'color'   => '#123456',
				'opacity' => 0.15,
				'scale'   => 2,
			)
		);

		$html   = '<div class="has-dsgo-svg-pattern" data-dsgo-svg-pattern="inherit"></div>';
		$result = $this->renderer->inject_svg_pattern( $html, $this->make_block( $html ) );

		// waves is 120x40; scale 2 => 240x80. Proves the themed type + scale won.
		$this->assertStringContainsString( '--dsgo-svg-pattern-size:240px 80px', $result );
		$this->assertStringContainsString( '--dsgo-svg-pattern-image:url(&quot;data:image/svg+xml,', $result );
	}

	/**
	 * A themed color in the block-attribute shorthand (var:preset|color|slug)
	 * resolves to its palette hex, matching the editor resolver which accepts
	 * both that form and var(--wp--preset--color--slug).
	 */
	public function test_inherit_resolves_preset_slug_color_shorthand() {
		add_filter(
			'wp_theme_json_data_theme',
			static function ( $theme_json ) {
				return $theme_json->update_with(
					array(
						'version'  => 2,
						'settings' => array(
							'color'  => array(
								'palette' => array(
									array(
										'slug'  => 'dsgotest',
										'color' => '#abcdef',
										'name'  => 'DSGo Test',
									),
								),
							),
							'custom' => array(
								'designsetgo' => array(
									'svgPattern' => array(
										'type'  => 'dot-grid',
										'color' => 'var:preset|color|dsgotest',
									),
								),
							),
						),
					)
				);
			}
		);
		wp_clean_theme_json_cache();

		$html   = '<div class="has-dsgo-svg-pattern" data-dsgo-svg-pattern="inherit"></div>';
		$result = $this->renderer->inject_svg_pattern( $html, $this->make_block( $html ) );

		// rawurlencode('#abcdef') === '%23abcdef' inside the SVG data URI.
		$this->assertStringContainsString( '%23abcdef', $result );
		// The in-plugin gray fallback must NOT win once the slug resolves.
		$this->assertStringNotContainsString( '%239c92ac', $result );
	}

	/**
	 * An unknown themed pattern slug falls back to dot-grid, not a broken render.
	 */
	public function test_inherit_rejects_unknown_theme_type() {
		$this->set_theme_preset( array( 'type' => 'not-a-real-pattern' ) );

		$html   = '<div class="has-dsgo-svg-pattern" data-dsgo-svg-pattern="inherit"></div>';
		$result = $this->renderer->inject_svg_pattern( $html, $this->make_block( $html ) );

		$this->assertStringContainsString( '--dsgo-svg-pattern-size:24px 24px', $result );
	}

	/**
	 * Explicit patterns still render from their own data attributes, unchanged.
	 */
	public function test_explicit_pattern_unchanged() {
		$html   = '<div class="has-dsgo-svg-pattern" data-dsgo-svg-pattern="waves" data-dsgo-svg-pattern-color="#ff0000" data-dsgo-svg-pattern-opacity="0.3" data-dsgo-svg-pattern-scale="1"></div>';
		$result = $this->renderer->inject_svg_pattern( $html, $this->make_block( $html ) );

		// waves 120x40 at scale 1.
		$this->assertStringContainsString( '--dsgo-svg-pattern-size:120px 40px', $result );
		$this->assertStringContainsString( '--dsgo-svg-pattern-image:url(&quot;data:image/svg+xml,', $result );
	}

	/**
	 * An unknown explicit pattern slug bails and leaves content untouched.
	 */
	public function test_unknown_explicit_pattern_bails() {
		$html   = '<div class="has-dsgo-svg-pattern" data-dsgo-svg-pattern="not-a-real-pattern"></div>';
		$result = $this->renderer->inject_svg_pattern( $html, $this->make_block( $html ) );

		$this->assertSame( $html, $result );
	}

	/**
	 * The per-block Fixed toggle still applies while inheriting.
	 */
	public function test_fixed_toggle_applies_while_inheriting() {
		$html   = '<div class="has-dsgo-svg-pattern" data-dsgo-svg-pattern="inherit"></div>';
		$block  = $this->make_block( $html, array( 'dsgoSvgPatternFixed' => true ) );
		$result = $this->renderer->inject_svg_pattern( $html, $block );

		$this->assertStringContainsString( '--dsgo-svg-pattern-attachment:fixed', $result );
	}
}

<?php
/**
 * Tests for the Pill block server render.
 *
 * The visible pill is the inner `.dsgo-pill__content` span, so colour, background
 * and border inline styles are moved off the wrapper onto that span (padding /
 * typography stay on the wrapper). render.php computes both style strings from the
 * structured `style` attribute via the Style Engine, so this guards the split —
 * in particular that unlinked PER-CORNER border-radius and per-side border styles
 * (the case that a per-property allowlist or a `;`-split would silently miss)
 * reach the span, not the wrapper.
 *
 * @group pill
 */

/**
 * @group pill
 */
class DesignSetGo_Pill_Render_Test extends WP_UnitTestCase {

	/**
	 * Include the built render template with the given attributes and capture its
	 * echoed output.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	private function render( array $attributes ) {
		$path = DESIGNSETGO_PATH . 'build/blocks/pill/render.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render templates are served from build/.' );

		$content = '';
		$block   = null;

		// get_block_wrapper_attributes() reads the current block's attrs from this
		// global to build the wrapper class list.
		$previous_block                     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'designsetgo/pill',
			'attrs'     => $attributes,
		);

		ob_start();
		$returned = include $path;
		$html     = ob_get_clean();

		WP_Block_Supports::$block_to_render = $previous_block;

		return is_string( $returned ) ? $returned : $html;
	}

	/**
	 * Read the inline style of the first matching tag in a fragment.
	 *
	 * @param string $html     HTML fragment.
	 * @param string $tag_name Tag to read (e.g. 'div', 'span').
	 * @return string Style attribute value ('' when absent).
	 */
	private function style_of( $html, $tag_name ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag( $tag_name ) ) {
			return '';
		}
		return (string) $processor->get_attribute( 'style' );
	}

	public function test_per_corner_radius_and_border_color_land_on_inner_span() {
		$html = $this->render(
			array(
				'content' => 'Test',
				'style'   => array(
					'border'  => array(
						// Unlinked per-corner radius — the exact case a per-property
						// allowlist (border-radius only) or a `;`-split would drop.
						'radius' => array(
							'topLeft'     => '10px',
							'topRight'    => '2px',
							'bottomLeft'  => '4px',
							'bottomRight' => '8px',
						),
						'color'  => '#ff0000',
						'width'  => '2px',
						'style'  => 'solid',
					),
					'color'   => array(
						'text' => '#0000ff',
					),
					'spacing' => array(
						'padding' => array(
							'top'    => '4px',
							'right'  => '8px',
							'bottom' => '4px',
							'left'   => '8px',
						),
					),
				),
			)
		);

		$wrapper_style = $this->style_of( $html, 'div' );
		$span_style    = $this->style_of( $html, 'span' );

		// Colour + every border declaration (incl. all four per-corner radii) moved
		// to the visible span.
		$this->assertStringContainsString( 'color:#0000ff', $span_style );
		$this->assertStringContainsString( 'border-color:#ff0000', $span_style );
		$this->assertStringContainsString( 'border-width:2px', $span_style );
		$this->assertStringContainsString( 'border-top-left-radius:10px', $span_style );
		$this->assertStringContainsString( 'border-top-right-radius:2px', $span_style );
		$this->assertStringContainsString( 'border-bottom-left-radius:4px', $span_style );
		$this->assertStringContainsString( 'border-bottom-right-radius:8px', $span_style );

		// The wrapper keeps padding but carries no colour/border declarations — if
		// any stayed here they would never reach the visible pill (the span).
		$this->assertStringContainsString( 'padding', $wrapper_style );
		$this->assertStringNotContainsString( 'border', $wrapper_style );
		$this->assertStringNotContainsString( 'color', $wrapper_style );
	}

	public function test_plain_pill_has_no_inline_styles() {
		$html = $this->render( array( 'content' => 'Test' ) );

		$this->assertStringContainsString( 'class="dsgo-pill__content"', $html );
		$this->assertSame( '', $this->style_of( $html, 'div' ) );
		$this->assertSame( '', $this->style_of( $html, 'span' ) );
	}
}

<?php
/**
 * Tests for the Pill block server render.
 *
 * The `.dsgo-pill` wrapper is a plain block-level positioning box that core's
 * constrained layout caps at the content column; the visible pill is the inner
 * `.dsgo-pill__content` span. So colour, background, border AND padding inline
 * styles are moved off the wrapper onto that span via the shared
 * `designsetgo_route_visual_supports()` helper — otherwise padding/background/
 * border would apply to the full-column-width wrapper instead of the pill.
 * Margin and typography stay on the wrapper. render.php computes both style
 * strings from the structured `style` attribute via the Style Engine, so this
 * guards the split — in particular that unlinked PER-CORNER border-radius and
 * per-side border styles (the case that a per-property allowlist or a `;`-split
 * would silently miss) reach the span, not the wrapper.
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

		// Colour + padding + every border declaration (incl. all four per-corner
		// radii) moved to the visible span.
		$this->assertStringContainsString( 'color:#0000ff', $span_style );
		$this->assertStringContainsString( 'border-color:#ff0000', $span_style );
		$this->assertStringContainsString( 'border-width:2px', $span_style );
		$this->assertStringContainsString( 'border-top-left-radius:10px', $span_style );
		$this->assertStringContainsString( 'border-top-right-radius:2px', $span_style );
		$this->assertStringContainsString( 'border-bottom-left-radius:4px', $span_style );
		$this->assertStringContainsString( 'border-bottom-right-radius:8px', $span_style );
		$this->assertStringContainsString( 'padding-top:4px', $span_style );

		// The wrapper (a full-column-width positioning box) carries no colour,
		// border or padding declarations — if any stayed here they would paint
		// across the whole content column instead of hugging the visible pill.
		$this->assertStringNotContainsString( 'padding', $wrapper_style );
		$this->assertStringNotContainsString( 'border', $wrapper_style );
		$this->assertStringNotContainsString( 'color', $wrapper_style );
	}

	public function test_plain_pill_has_no_inline_styles() {
		$html = $this->render( array( 'content' => 'Test' ) );

		$this->assertStringContainsString( 'class="dsgo-pill__content"', $html );
		$this->assertSame( '', $this->style_of( $html, 'div' ) );
		$this->assertSame( '', $this->style_of( $html, 'span' ) );
	}

	public function test_wrapper_carries_the_justification_class() {
		// designsetgo_render_pill() is called via $this->render() (not directly)
		// because get_block_wrapper_attributes() reads WP_Block_Supports::$block_to_render,
		// which only the render() helper populates — see its docblock above.
		$html = $this->render(
			array(
				'content'       => 'Hi',
				'justification' => 'left',
			)
		);

		$this->assertStringContainsString( 'dsgo-justify', $html );
		$this->assertStringContainsString( 'dsgo-justify--left', $html );
	}

	public function test_padding_lands_on_the_pill_not_the_full_width_wrapper() {
		$html = $this->render(
			array(
				'content' => 'Hi',
				'style'   => array(
					'spacing' => array(
						'padding' => array( 'top' => '12px' ),
						'margin'  => array( 'top' => '30px' ),
					),
				),
			)
		);

		// Padding must be on the visible pill; margin stays on the wrapper. Uses
		// style_of() plus a plain substring check rather than a single attribute-
		// order-dependent regex, because WP_HTML_Tag_Processor::set_attribute()
		// inserts a new `style` attribute before pre-existing attributes such as
		// `class`, so a regex anchored to `class="…" … style="…"` order is not
		// reliable.
		$this->assertStringContainsString( 'class="dsgo-pill__content"', $html );
		$this->assertStringContainsString( 'padding-top:12px', $this->style_of( $html, 'span' ) );
		$this->assertStringContainsString( 'margin-top:30px', $this->style_of( $html, 'div' ) );
		$this->assertStringNotContainsString( 'padding-top', $this->style_of( $html, 'div' ) );
	}
}

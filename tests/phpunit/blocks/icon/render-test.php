<?php
/**
 * Tests for the Icon block server render.
 *
 * The `.dsgo-icon` wrapper is a plain block-level positioning box that core's
 * constrained layout caps at the content column (the same mechanism a
 * paragraph relies on); the visible icon is the inner `.dsgo-icon__wrapper`
 * element. Colour, background, border AND padding inline styles are moved off
 * the wrapper onto that element via the shared
 * `designsetgo_route_visual_supports()` helper — otherwise a background or
 * border would paint across the full-column-width wrapper instead of hugging
 * the icon. Margin stays on the wrapper. This guards that split, plus the
 * `justification` attribute driving the `dsgo-justify--*` class instead of the
 * old `align: left|center|right` (which core's constrained layout excludes
 * from the content-size cap, letting an aligned Icon escape the column).
 *
 * @group icon
 */

/**
 * @group icon
 */
class DesignSetGo_Icon_Render_Test extends WP_UnitTestCase {

	/**
	 * Include the built render template with the given attributes and capture its
	 * echoed output.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	private function render( array $attributes ) {
		$path = DESIGNSETGO_PATH . 'build/blocks/icon/render.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render templates are served from build/.' );

		$content = '';
		$block   = null;

		// get_block_wrapper_attributes() reads the current block's attrs from this
		// global to build the wrapper class list.
		$previous_block                     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'designsetgo/icon',
			'attrs'     => $attributes,
		);

		ob_start();
		$returned = include $path;
		$html     = ob_get_clean();

		WP_Block_Supports::$block_to_render = $previous_block;

		return is_string( $returned ) ? $returned : $html;
	}

	/**
	 * Read the inline style of the first tag carrying the given class.
	 *
	 * @param string $html  HTML fragment.
	 * @param string $class Class to search for.
	 * @return string Style attribute value ('' when absent or tag not found).
	 */
	private function style_of_class( $html, $class ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag() ) {
			if ( $processor->has_class( $class ) ) {
				return (string) $processor->get_attribute( 'style' );
			}
		}
		return '';
	}

	/**
	 * Read the class attribute of the first tag carrying the given class.
	 *
	 * @param string $html  HTML fragment.
	 * @param string $class Class to search for.
	 * @return string Class attribute value ('' when absent or tag not found).
	 */
	private function class_of_class( $html, $class ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag() ) {
			if ( $processor->has_class( $class ) ) {
				return (string) $processor->get_attribute( 'class' );
			}
		}
		return '';
	}

	public function test_wrapper_carries_the_justification_class() {
		$html = $this->render(
			array(
				'icon'          => 'star',
				'justification' => 'left',
			)
		);

		$this->assertStringContainsString( 'dsgo-justify', $html );
		$this->assertStringContainsString( 'dsgo-justify--left', $html );
	}

	public function test_default_justification_is_center() {
		$html = $this->render( array( 'icon' => 'star' ) );

		$this->assertStringContainsString( 'dsgo-justify--center', $html );
	}

	public function test_background_and_border_radius_paint_the_icon_not_the_column() {
		$html = $this->render(
			array(
				'icon'  => 'star',
				'style' => array(
					'color'  => array( 'background' => '#f00' ),
					'border' => array( 'radius' => '999px' ),
				),
			)
		);

		$wrapper_style = $this->style_of_class( $html, 'dsgo-icon' );
		$inner_style   = $this->style_of_class( $html, 'dsgo-icon__wrapper' );

		// Background + radius belong to the icon box, never to the
		// full-column-width positioning wrapper.
		$this->assertStringContainsString( 'background-color:#f00', $inner_style );
		$this->assertStringContainsString( 'border-radius:999px', $inner_style );
		$this->assertStringNotContainsString( 'background', $wrapper_style );
		$this->assertStringNotContainsString( 'border-radius', $wrapper_style );
	}

	public function test_padding_lands_on_icon_wrapper_not_the_column() {
		$html = $this->render(
			array(
				'icon'  => 'star',
				'style' => array(
					'spacing' => array(
						'padding' => array( 'top' => '12px' ),
						'margin'  => array( 'top' => '30px' ),
					),
				),
			)
		);

		$wrapper_style = $this->style_of_class( $html, 'dsgo-icon' );
		$inner_style   = $this->style_of_class( $html, 'dsgo-icon__wrapper' );

		// Padding is on the visible icon box; margin stays on the wrapper.
		$this->assertStringContainsString( 'padding-top:12px', $inner_style );
		$this->assertStringNotContainsString( 'padding-top', $wrapper_style );
		$this->assertStringContainsString( 'margin-top:30px', $wrapper_style );
	}

	public function test_preset_background_and_border_color_classes_land_on_wrapper_not_column() {
		$html = $this->render(
			array(
				'icon'            => 'star',
				'backgroundColor' => 'accent-3',
				'borderColor'     => 'contrast',
			)
		);

		$outer_class = $this->class_of_class( $html, 'dsgo-icon' );
		$inner_class = $this->class_of_class( $html, 'dsgo-icon__wrapper' );

		$this->assertStringNotContainsString( 'has-accent-3-background-color', $outer_class );
		$this->assertStringNotContainsString( 'has-background', $outer_class );
		$this->assertStringNotContainsString( 'has-contrast-border-color', $outer_class );

		$this->assertStringContainsString( 'has-accent-3-background-color', $inner_class );
		$this->assertStringContainsString( 'has-background', $inner_class );
		$this->assertStringContainsString( 'has-contrast-border-color', $inner_class );
	}

	public function test_plain_icon_has_no_visual_inline_styles_on_outer_div() {
		$html = $this->render( array( 'icon' => 'star' ) );

		$wrapper_style = $this->style_of_class( $html, 'dsgo-icon' );

		$this->assertSame( '', $wrapper_style );
	}
}

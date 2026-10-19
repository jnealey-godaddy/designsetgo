<?php
/**
 * Icon List settings reach stored Icon List Items at render time.
 *
 * @package DesignSetGo
 */

/**
 * @group icon-list
 */
class DesignSetGo_Icon_List_Context_Styles_Test extends WP_UnitTestCase {

	/**
	 * An item exactly as save() stores it (no context: defaults baked in).
	 *
	 * @param string $icon Icon name.
	 * @return string
	 */
	private function item( $icon = 'clock' ) {
		return '<!-- wp:designsetgo/icon-list-item {"icon":"' . $icon . '","contentGap":8} -->'
			. '<div class="wp-block-designsetgo-icon-list-item dsgo-icon-list-item dsgo-icon-list-item--icon-left" style="display:flex;flex-direction:row;align-items:flex-start;padding-top:4px">'
			. '<div class="dsgo-icon-list-item__icon dsgo-lazy-icon dsgo-icon-list-item__icon--inherit-size" data-icon-name="' . $icon . '"></div>'
			. '<div class="dsgo-icon-list-item__content" style="text-align:left;display:flex;flex-direction:column;gap:8px"><!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph --></div></div>'
			. '<!-- /wp:designsetgo/icon-list-item -->';
	}

	/**
	 * Render a list with the given attributes around stored items.
	 *
	 * @param array  $attrs Icon List attributes.
	 * @param string $items Item markup.
	 * @return string
	 */
	private function render_list( array $attrs, $items = null ) {
		$json = $attrs ? ' ' . wp_json_encode( $attrs ) : '';
		return do_blocks(
			'<!-- wp:designsetgo/icon-list' . $json . ' -->'
			. '<div class="wp-block-designsetgo-icon-list dsgo-icon-list dsgo-icon-list--vertical" style="width:100%"><div class="dsgo-icon-list__items" style="display:flex;flex-direction:column;align-items:flex-start;width:100%">'
			. ( null === $items ? $this->item() : $items )
			. '</div></div><!-- /wp:designsetgo/icon-list -->'
		);
	}

	public function test_default_list_is_left_byte_identical() {
		$items = $this->item();
		$this->assertStringContainsString(
			'class="wp-block-designsetgo-icon-list-item dsgo-icon-list-item dsgo-icon-list-item--icon-left" style="display:flex;flex-direction:row;align-items:flex-start;padding-top:4px"',
			$this->render_list( array() )
		);
	}

	public function test_preset_colour_reaches_the_icon() {
		$html = $this->render_list( array( 'iconColor' => 'vivid-cyan-blue' ) );
		$this->assertStringContainsString( 'color:var(--wp--preset--color--vivid-cyan-blue);--dsgo-icon-color:var(--wp--preset--color--vivid-cyan-blue);', $html );
	}

	public function test_explicit_size_and_background_replace_inherited_size() {
		$html = $this->render_list(
			array(
				'iconSize'            => 24,
				'iconBackgroundColor' => '#ff0000',
			)
		);
		$this->assertStringNotContainsString( 'dsgo-icon-list-item__icon--inherit-size', $html );
		$this->assertStringContainsString( 'width:40px;height:40px;min-width:40px;background-color:#ff0000;padding:8px;border-radius:4px;box-sizing:border-box;', $html );
	}

	public function test_right_position_reverses_row_and_keeps_support_styles() {
		$html = $this->render_list(
			array(
				'iconPosition'          => 'right',
				'iconVerticalAlignment' => 'center',
			)
		);
		$this->assertStringContainsString( 'dsgo-icon-list-item--icon-right', $html );
		$this->assertStringNotContainsString( 'dsgo-icon-list-item--icon-left', $html );
		$this->assertStringContainsString( 'style="display:flex;flex-direction:row-reverse;align-items:center;padding-top:4px;"', $html );
		$this->assertStringContainsString( 'text-align:right;display:flex;flex-direction:column;gap:8px;', $html );
	}

	public function test_top_position_stacks_and_centres() {
		$html = $this->render_list( array( 'iconPosition' => 'top' ) );
		$this->assertStringContainsString( 'flex-direction:column;align-items:center;', $html );
		$this->assertStringContainsString( 'text-align:center;', $html );
	}

	public function test_nested_list_items_use_their_own_list() {
		$inner = '<!-- wp:designsetgo/icon-list -->'
			. '<div class="wp-block-designsetgo-icon-list dsgo-icon-list dsgo-icon-list--vertical" style="width:100%"><div class="dsgo-icon-list__items" style="display:flex">'
			. $this->item( 'star' )
			. '</div></div><!-- /wp:designsetgo/icon-list -->';
		$outer_item = '<!-- wp:designsetgo/icon-list-item {"icon":"clock"} -->'
			. '<div class="wp-block-designsetgo-icon-list-item dsgo-icon-list-item dsgo-icon-list-item--icon-left" style="display:flex;flex-direction:row;align-items:flex-start">'
			. '<div class="dsgo-icon-list-item__icon dsgo-lazy-icon dsgo-icon-list-item__icon--inherit-size" data-icon-name="clock"></div>'
			. '<div class="dsgo-icon-list-item__content" style="text-align:left;display:flex;flex-direction:column">' . $inner . '</div></div>'
			. '<!-- /wp:designsetgo/icon-list-item -->';

		$html = $this->render_list( array( 'iconColor' => '#00ff00' ), $outer_item );

		$this->assertSame( 1, substr_count( $html, '--dsgo-icon-color:#00ff00' ), 'Only the outer list item takes the outer colour.' );
		$this->assertMatchesRegularExpression( '/data-icon-name="star"(?![^>]*#00ff00)/', $html );
	}

	public function test_colour_values_cannot_break_out_of_the_style_attribute() {
		$html = $this->render_list( array( 'iconColor' => 'red;background:url(x)' ) );
		$this->assertStringNotContainsString( 'background:url', $html );
	}
}

<?php
/**
 * Timeline marker settings applied to items at render time.
 *
 * The parent Timeline's marker style and colours reach an item only as block
 * context, which a static save() never receives. The render_block pass redraws
 * the stored marker from that context; these tests pin the shapes and colours.
 *
 * @package DesignSetGo
 * @group timeline
 */

/**
 * Timeline_Context_Styles tests.
 */
class Timeline_Context_Styles_Test extends WP_UnitTestCase {

	/**
	 * Marker as save() stores it: a default circle in the default colour.
	 *
	 * @var string
	 */
	const STORED = '<div class="wp-block-designsetgo-timeline-item dsgo-timeline-item"><div class="dsgo-timeline-item__marker" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="var(--wp--preset--color--primary, #2563eb)" stroke="var(--wp--preset--color--primary, #2563eb)" stroke-width="2"></circle></svg></div><div class="dsgo-timeline-item__wrapper"></div></div>';

	/**
	 * Run the pass for an item with the given parent context.
	 *
	 * @param array  $context Context keyed without the provider prefix.
	 * @param array  $attrs   Item attributes.
	 * @param string $html    Stored item markup.
	 * @return string
	 */
	private function render( array $context, array $attrs = array(), $html = self::STORED ) {
		$prefixed = array();
		foreach ( $context as $key => $value ) {
			$prefixed[ 'designsetgo/timeline/' . $key ] = $value;
		}
		$parsed   = array(
			'blockName'    => 'designsetgo/timeline-item',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
		$instance = new WP_Block( $parsed, $prefixed );

		return ( new DesignSetGo\Timeline_Context_Styles() )->apply( $html, $parsed, $instance );
	}

	/**
	 * Defaults are exactly what save() stored: markup is untouched.
	 */
	public function test_defaults_leave_markup_alone() {
		$this->assertSame( self::STORED, $this->render( array( 'markerStyle' => 'circle' ) ) );
	}

	/**
	 * Square and diamond redraw the shape the way edit.js does.
	 */
	public function test_marker_style_changes_the_shape() {
		$square = $this->render( array( 'markerStyle' => 'square' ) );
		$this->assertStringContainsString( '<rect x="2" y="2" width="20" height="20" rx="2"', $square );
		$this->assertStringNotContainsString( '<circle', $square );

		$diamond = $this->render( array( 'markerStyle' => 'diamond' ) );
		$this->assertStringContainsString( 'transform="rotate(45 12 2)"', $diamond );
	}

	/**
	 * Marker and border colours reach fill and stroke; border falls back to fill.
	 */
	public function test_colours_reach_fill_and_stroke() {
		$html = $this->render(
			array(
				'markerStyle'       => 'circle',
				'markerColor'       => '#ff0000',
				'markerBorderColor' => 'var:preset|color|contrast',
			)
		);
		$this->assertStringContainsString( 'fill="#ff0000"', $html );
		$this->assertStringContainsString( 'stroke="var(--wp--preset--color--contrast)"', $html );

		$no_border = $this->render( array( 'markerColor' => '#00ff00' ) );
		$this->assertStringContainsString( 'fill="#00ff00" stroke="#00ff00"', $no_border );
	}

	/**
	 * An item's own colour wins over the parent's.
	 */
	public function test_item_colour_overrides_parent() {
		$html = $this->render(
			array( 'markerColor' => '#ff0000' ),
			array( 'customMarkerColor' => '#0000ff' )
		);
		$this->assertStringContainsString( 'fill="#0000ff"', $html );
	}

	/**
	 * A marker image keeps its markup; only the corner radius follows the style.
	 */
	public function test_image_marker_radius_follows_style() {
		$html = '<div class="dsgo-timeline-item"><div class="dsgo-timeline-item__marker"><img src="a.jpg" alt="" class="dsgo-timeline-item__marker-image" style="border-radius:50%;object-fit:cover"/></div></div>';
		$out  = $this->render( array( 'markerStyle' => 'square' ), array(), $html );
		$this->assertStringContainsString( 'border-radius:4px', $out );
	}

	/**
	 * A round image in the item's content keeps its radius under a square or
	 * diamond style; only the marker image's corners change.
	 */
	public function test_content_images_keep_their_radius() {
		$html = '<div class="wp-block-designsetgo-timeline-item dsgo-timeline-item"><div class="dsgo-timeline-item__marker" aria-hidden="true"><img src="m.jpg" alt="" class="dsgo-timeline-item__marker-image" style="border-radius:50%;object-fit:cover"/></div><div class="dsgo-timeline-item__wrapper"><figure class="wp-block-image"><img src="a.jpg" alt="" style="border-radius:50%"/></figure></div></div>';

		$out = $this->render( array( 'markerStyle' => 'diamond' ), array(), $html );

		$this->assertStringContainsString( 'class="dsgo-timeline-item__marker-image" style="border-radius:4px', $out );
		$this->assertStringContainsString( '<img src="a.jpg" alt="" style="border-radius:50%"/>', $out );
	}

	/**
	 * An item with a marker image has no marker SVG, so an SVG in its content
	 * must not be taken for one.
	 */
	public function test_content_svgs_are_never_redrawn() {
		$icon = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"></circle></svg>';
		$html = '<div class="wp-block-designsetgo-timeline-item dsgo-timeline-item"><div class="dsgo-timeline-item__marker" aria-hidden="true"><img src="m.jpg" alt="" class="dsgo-timeline-item__marker-image" style="border-radius:50%;object-fit:cover"/></div><div class="dsgo-timeline-item__wrapper"><div class="wp-block-designsetgo-icon">' . $icon . '</div></div></div>';

		$out = $this->render(
			array(
				'markerStyle' => 'circle',
				'markerColor' => '#ff0000',
			),
			array(),
			$html
		);

		$this->assertSame( $html, $out );
	}
}

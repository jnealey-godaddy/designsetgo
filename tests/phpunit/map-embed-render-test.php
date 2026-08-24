<?php
/**
 * Tests for the map block's keyless Google Maps embed provider.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * Covers designsetgo_map_embed_url() and the iframe branch of render.php.
 *
 * @group map
 */
class Map_Embed_Render_Test extends WP_UnitTestCase {

	/**
	 * Render a map block from an attribute array.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered markup.
	 */
	private function render( array $attributes ) {
		return do_blocks(
			'<!-- wp:designsetgo/map ' . wp_json_encode( $attributes ) . ' /-->'
		);
	}

	/**
	 * The block is registered, so the suite exercises real markup.
	 */
	public function test_the_block_is_registered() {
		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( 'designsetgo/map' ),
			'designsetgo/map is not registered — run `npm run build` first.'
		);
	}

	/**
	 * The new provider is a valid enum value in the built block.json.
	 */
	public function test_embed_provider_is_an_allowed_enum_value() {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( 'designsetgo/map' );

		$this->assertContains(
			'googlemaps-embed',
			$block->attributes['dsgoProvider']['enum'],
			'googlemaps-embed missing from the dsgoProvider enum.'
		);
	}

	/**
	 * The URL builder prefers an address over coordinates.
	 */
	public function test_url_builder_prefers_address_over_coordinates() {
		$url = designsetgo_map_embed_url( '123 Main St, Springfield', 40.7128, -74.006, 13 );

		$this->assertStringContainsString( 'q=123+Main+St%2C+Springfield', $url );
		$this->assertStringNotContainsString( '40.7128', $url );
	}

	/**
	 * With no address, the builder falls back to lat,lng.
	 */
	public function test_url_builder_falls_back_to_coordinates() {
		$url = designsetgo_map_embed_url( '', 40.7128, -74.006, 13 );

		$this->assertStringContainsString( 'q=40.7128%2C-74.006', $url );
	}

	/**
	 * Zoom is clamped into Google's supported range.
	 */
	public function test_url_builder_clamps_zoom() {
		$this->assertStringContainsString( 'z=20', designsetgo_map_embed_url( '', 0, 0, 99 ) );
		$this->assertStringContainsString( 'z=1', designsetgo_map_embed_url( '', 0, 0, -5 ) );
	}

	/**
	 * Multi-line addresses are flattened, matching the geocoding normalizer.
	 */
	public function test_url_builder_flattens_multiline_addresses() {
		$url = designsetgo_map_embed_url( "123 Main St\nSpringfield, IL", 0, 0, 13 );

		$this->assertStringContainsString( 'q=123+Main+St%2C+Springfield%2C+IL', $url );
		$this->assertStringNotContainsString( '%0A', $url );
	}

	/**
	 * The embed always asks Google for the bare embed output.
	 */
	public function test_url_builder_requests_embed_output() {
		$url = designsetgo_map_embed_url( 'Paris', 0, 0, 13 );

		$this->assertStringStartsWith( 'https://maps.google.com/maps?', $url );
		$this->assertStringContainsString( 'output=embed', $url );
	}

	/**
	 * Normal mode emits a ready-to-load iframe needing no JavaScript.
	 */
	public function test_embed_provider_renders_a_live_iframe() {
		$html = $this->render(
			array(
				'dsgoProvider' => 'googlemaps-embed',
				'dsgoAddress'  => 'Paris, France',
			)
		);

		$this->assertStringContainsString( 'dsgo-map__iframe', $html );
		$this->assertStringContainsString( 'src="https://maps.google.com/maps?', $html );
		$this->assertStringContainsString( 'loading="lazy"', $html );
		$this->assertStringContainsString( 'referrerpolicy="no-referrer-when-downgrade"', $html );
		$this->assertStringNotContainsString( 'data-dsgo-src', $html );
	}

	/**
	 * The iframe carries an accessible name derived from the address.
	 */
	public function test_embed_iframe_has_an_accessible_title() {
		$html = $this->render(
			array(
				'dsgoProvider' => 'googlemaps-embed',
				'dsgoAddress'  => 'Paris, France',
			)
		);

		$this->assertStringContainsString( 'title="Map showing Paris, France"', $html );
	}

	/**
	 * Privacy mode parks the URL until the visitor asks for it.
	 */
	public function test_privacy_mode_withholds_the_iframe_src() {
		$html = $this->render(
			array(
				'dsgoProvider'    => 'googlemaps-embed',
				'dsgoAddress'     => 'Paris, France',
				'dsgoPrivacyMode' => true,
			)
		);

		$this->assertStringContainsString( 'data-dsgo-src="https://maps.google.com/maps?', $html );
		$this->assertStringNotContainsString( ' src="https://maps.google.com', $html );
		$this->assertStringContainsString( 'dsgo-map__load-button', $html );
	}

	/**
	 * Attribute values cannot break out of the src attribute.
	 */
	public function test_address_is_escaped_in_the_iframe_src() {
		$html = $this->render(
			array(
				'dsgoProvider' => 'googlemaps-embed',
				'dsgoAddress'  => '"><script>alert(1)</script>',
			)
		);

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
	}

	/**
	 * The keyed JavaScript providers keep their container-based markup.
	 */
	public function test_other_providers_still_render_the_js_container() {
		foreach ( array( 'openstreetmap', 'googlemaps' ) as $provider ) {
			$html = $this->render( array( 'dsgoProvider' => $provider ) );

			$this->assertStringContainsString( 'dsgo-map__container', $html, $provider );
			$this->assertStringNotContainsString( 'dsgo-map__iframe', $html, $provider );
		}
	}
}

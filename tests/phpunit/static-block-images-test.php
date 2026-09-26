<?php
/**
 * Tests for render-time image attributes on static blocks.
 *
 * @group images
 */

/**
 * @group images
 */
class DesignSetGo_Static_Block_Images_Test extends WP_UnitTestCase {

	/**
	 * @var \DesignSetGo\Static_Block_Images
	 */
	private $images;

	/**
	 * @var int
	 */
	private $attachment_id;

	public function set_up() {
		parent::set_up();
		$this->images        = new \DesignSetGo\Static_Block_Images();
		$this->attachment_id = self::create_image_attachment();
	}

	/**
	 * Create an image attachment with generated sizes, without real files:
	 * srcset and dimensions are computed from metadata alone.
	 *
	 * @return int Attachment ID.
	 */
	public static function create_image_attachment() {
		$id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'file'           => '2026/09/photo.jpg',
			)
		);
		update_post_meta( $id, '_wp_attached_file', '2026/09/photo.jpg' );
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => 2400,
				'height' => 1600,
				'file'   => '2026/09/photo.jpg',
				'sizes'  => array(
					'medium'       => array( 'file' => 'photo-300x200.jpg', 'width' => 300, 'height' => 200, 'mime-type' => 'image/jpeg' ),
					'medium_large' => array( 'file' => 'photo-768x512.jpg', 'width' => 768, 'height' => 512, 'mime-type' => 'image/jpeg' ),
					'large'        => array( 'file' => 'photo-1024x683.jpg', 'width' => 1024, 'height' => 683, 'mime-type' => 'image/jpeg' ),
				),
			)
		);

		return $id;
	}

	private function url( $file = 'photo.jpg' ) {
		return wp_get_upload_dir()['baseurl'] . '/2026/09/' . $file;
	}

	public function test_hotspot_image_gets_dimensions_and_srcset() {
		$html = '<div class="wp-block-designsetgo-hotspot"><div class="dsgo-hotspot__image-wrap"><img class="dsgo-hotspot__image" src="' . $this->url() . '" alt="Map"/><div class="dsgo-hotspot__items"></div></div></div>';

		$out = $this->images->render_hotspot( $html, array( 'attrs' => array( 'imageId' => $this->attachment_id ) ) );

		$this->assertStringContainsString( 'width="2400"', $out );
		$this->assertStringContainsString( 'height="1600"', $out );
		$this->assertStringContainsString( 'photo-768x512.jpg 768w', $out );
		$this->assertStringContainsString( 'sizes="', $out );
	}

	public function test_hotspot_without_matching_attachment_is_unchanged() {
		$html = '<div><img class="dsgo-hotspot__image" src="https://example.com/elsewhere.jpg" alt=""/></div>';

		$this->assertSame( $html, $this->images->render_hotspot( $html, array( 'attrs' => array( 'imageId' => $this->attachment_id ) ) ), 'A stale imageId must not describe a different image.' );
		$this->assertSame( $html, $this->images->render_hotspot( $html, array( 'attrs' => array() ) ) );
	}

	public function test_card_background_becomes_lazy_img_for_external_url() {
		$html = '<div class="wp-block-designsetgo-card"><div class="dsgo-card__background" style="background-image:url(https://example.com/bg.jpg)"><div class="dsgo-card__overlay" style="opacity:0.5"></div></div></div>';

		$out = $this->images->render_card( $html, array( 'attrs' => array() ) );

		$this->assertStringNotContainsString( 'background-image:url', $out );
		$this->assertStringContainsString( '<div class="dsgo-card__background"><img loading="lazy" decoding="async" class="dsgo-card__background-image" src="https://example.com/bg.jpg" alt="" /><div class="dsgo-card__overlay"', $out );
	}

	public function test_card_background_attachment_gets_dimensions_and_srcset() {
		$html = '<div class="dsgo-card__background" style="background-image:url(' . $this->url( 'photo-1024x683.jpg' ) . ')"><div class="dsgo-card__overlay"></div></div>';

		$out = $this->images->render_card( $html, array( 'attrs' => array( 'imageId' => $this->attachment_id ) ) );

		$this->assertStringContainsString( 'class="dsgo-card__background-image"', $out );
		$this->assertStringContainsString( 'width="1024"', $out );
		$this->assertStringContainsString( 'srcset="', $out );
		// Core's content filter picks loading/fetchpriority once dimensions exist.
		$this->assertStringNotContainsString( 'loading=', $out );
	}

	public function test_card_background_rejects_unsafe_url() {
		$html = '<div class="dsgo-card__background" style="background-image:url(javascript:alert(1))"></div>';

		$this->assertSame( $html, $this->images->render_card( $html, array( 'attrs' => array() ) ) );
	}

	public function test_card_image_layout_gets_dimensions() {
		$html = '<div class="dsgo-card__image-wrapper"><img src="' . $this->url( 'photo-768x512.jpg' ) . '" alt="" class="dsgo-card__image" loading="lazy"/></div>';

		$out = $this->images->render_card( $html, array( 'attrs' => array( 'imageId' => $this->attachment_id ) ) );

		$this->assertStringContainsString( 'width="768"', $out );
		$this->assertStringContainsString( 'height="512"', $out );
	}
}

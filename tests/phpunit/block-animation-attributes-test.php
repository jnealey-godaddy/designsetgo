<?php
/**
 * Tests for the block animation/clickable attribute helpers.
 *
 * Regression guard for the rename dsgo_* -> designsetgo_* in
 * includes/data/block-animation-attributes.php. Confirms the renamed global
 * functions exist and behave as before.
 *
 * @group animation
 */

/**
 * @group animation
 */
class DesignSetGo_Block_Animation_Attributes_Test extends WP_UnitTestCase {

	public function test_animation_attributes_empty_when_disabled() {
		$result = designsetgo_get_animation_attributes( array() );

		$this->assertSame( '', $result['classes'] );
		$this->assertSame( '', $result['attrs'] );
	}

	public function test_animation_attributes_when_enabled() {
		$result = designsetgo_get_animation_attributes(
			array(
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => 'fade-in',
			)
		);

		$this->assertStringContainsString( 'has-dsgo-animation', $result['classes'] );
		$this->assertStringContainsString( 'dsgo-animation-fade-in', $result['classes'] );
		$this->assertStringContainsString( 'data-dsgo-animation-enabled', $result['attrs'] );
	}

	public function test_clickable_attributes_empty_without_link_url() {
		$result = designsetgo_get_clickable_attributes( array() );

		$this->assertSame( '', $result['classes'] );
		$this->assertSame( '', $result['attrs'] );
	}

	public function test_clickable_attributes_with_link_url() {
		$result = designsetgo_get_clickable_attributes(
			array(
				'dsgLinkUrl'    => 'https://example.com/',
				'dsgLinkTarget' => true,
			)
		);

		$this->assertStringContainsString( 'dsgo-clickable', $result['classes'] );
		$this->assertStringContainsString( 'data-link-url="https://example.com/"', $result['attrs'] );
		$this->assertStringContainsString( 'data-link-target="_blank"', $result['attrs'] );
	}

	public function test_add_animation_to_wrapper_merges_classes() {
		$wrapper = 'class="wp-block-designsetgo-stack"';

		$result = designsetgo_add_animation_to_wrapper(
			$wrapper,
			array( 'dsgoAnimationEnabled' => true )
		);

		$this->assertStringContainsString( 'wp-block-designsetgo-stack', $result );
		$this->assertStringContainsString( 'has-dsgo-animation', $result );
	}
}

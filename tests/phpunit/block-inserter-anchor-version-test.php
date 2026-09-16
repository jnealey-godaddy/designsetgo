<?php
/**
 * The anchor id must reach the root element on every supported WordPress version.
 *
 * Core only grew a PHP anchor block support in WP 7.0
 * (wp-includes/block-supports/anchor.php). This plugin supports WP 6.7+, and on
 * 6.7-6.9 apply_block_supports() reports no `id` at all, so reading the id from
 * that array left the anchor in the block comment and out of the saved markup -
 * the exact validation failure the anchor handling exists to prevent. CI only
 * runs "WordPress latest", so nothing else catches the regression.
 *
 * These tests emulate the older range by unregistering the support.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * @group abilities
 */
class Block_Inserter_Anchor_Version_Test extends WP_UnitTestCase {

	/**
	 * Registered block supports, captured so each test can restore them.
	 *
	 * @var array<string, mixed>|null
	 */
	private $original_supports = null;

	public function tear_down() {
		if ( null !== $this->original_supports ) {
			$this->support_property()->setValue( WP_Block_Supports::get_instance(), $this->original_supports );
			$this->original_supports = null;
		}
		parent::tear_down();
	}

	/**
	 * Accessor for WP_Block_Supports' private registry.
	 */
	private function support_property(): ReflectionProperty {
		$property = ( new ReflectionClass( WP_Block_Supports::get_instance() ) )->getProperty( 'block_supports' );
		$property->setAccessible( true );

		return $property;
	}

	/**
	 * Drop the anchor block support, reproducing WP 6.7-6.9.
	 */
	private function emulate_wp_without_anchor_support() {
		$property                = $this->support_property();
		$instance                = WP_Block_Supports::get_instance();
		$this->original_supports = $property->getValue( $instance );

		if ( ! isset( $this->original_supports['anchor'] ) ) {
			$this->markTestSkipped( 'This WordPress has no anchor block support to remove.' );
		}

		$without = $this->original_supports;
		unset( $without['anchor'] );
		$property->setValue( $instance, $without );
	}

	public function test_anchor_reaches_the_root_without_core_anchor_support() {
		$this->emulate_wp_without_anchor_support();

		$markup = Block_Inserter::build_block_markup(
			'core/heading',
			array(
				'content' => 'Perks',
				'anchor'  => 'perks',
				'level'   => 2,
			)
		);

		$this->assertStringContainsString( 'id="perks"', $markup );
	}

	public function test_block_without_anchor_support_gets_no_id() {
		$markup = Block_Inserter::build_block_markup(
			'core/spacer',
			array( 'anchor' => 'nope' )
		);

		$this->assertStringNotContainsString( 'id="nope"', $markup );
	}

	public function test_non_string_anchor_never_reaches_the_markup() {
		$markup = Block_Inserter::build_block_markup(
			'core/heading',
			array(
				'content' => 'Perks',
				'anchor'  => array( 'evil' => 'x' ),
				'level'   => 2,
			)
		);

		$this->assertStringNotContainsString( 'id="Array"', $markup );
		$this->assertStringNotContainsString( ' id=', $markup );
	}
}

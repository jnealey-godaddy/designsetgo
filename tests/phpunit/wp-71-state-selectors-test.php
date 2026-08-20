<?php
/**
 * WordPress 7.1 compatibility: responsive/state style targeting and ability exposure.
 *
 * WP 7.1 lets authors set per-viewport styles on any block instance
 * (`style['@mobile']`, `style['@tablet']`). Core renders those as a separate
 * stylesheet keyed to a generated `wp-states-*` class that it puts on the
 * block's OUTERMOST tag, and it resolves the rest of each rule's selector from
 * the block type's `selectors` in block.json.
 *
 * Blocks that skip serialization for a support and paint it onto an inner
 * element therefore have to declare that inner element in `selectors`, or the
 * responsive override lands on the wrapper while the base value sits on the
 * inner element. The reverse is just as wrong: a support that IS serialized on
 * the wrapper (margin, here) must not be pointed at the inner element.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Abstract_Ability;

/**
 * @group wp-71-compat
 */
class WP_71_State_Selectors_Test extends WP_UnitTestCase {

	/**
	 * Blocks that relocate visual supports, and where each support must land.
	 *
	 * 'wrapper' means the block's own root element; anything else is the class
	 * of the inner element that actually paints the support.
	 *
	 * @return array<string, array{0: string, 1: array<string, string>}>
	 */
	public function relocating_blocks() {
		return array(
			'pill'           => array(
				'pill',
				array(
					'color'          => '.dsgo-pill__content',
					'border'         => '.dsgo-pill__content',
					'typography'     => '.dsgo-pill__content',
					'spacing.root'   => 'wrapper',
					'spacing.padding' => '.dsgo-pill__content',
				),
			),
			'icon'           => array(
				'icon',
				array(
					'root'          => '.dsgo-icon__wrapper',
					'spacing.root'  => '.dsgo-icon__wrapper',
					'spacing.margin' => 'wrapper',
				),
			),
			'icon-button'    => array(
				'icon-button',
				array(
					'root'           => '.dsgo-icon-button',
					'spacing.root'   => '.dsgo-icon-button',
					'spacing.margin' => 'wrapper',
				),
			),
			'modal-trigger'  => array(
				'modal-trigger',
				array(
					'root'           => '.dsgo-modal-trigger',
					'spacing.root'   => '.dsgo-modal-trigger',
					'spacing.margin' => 'wrapper',
				),
			),
			'scroll-marquee' => array(
				'scroll-marquee',
				array(
					'root'   => 'wrapper',
					'border' => '.dsgo-scroll-marquee__image',
				),
			),
		);
	}

	/**
	 * Each relocating block points every relocated support at the element that
	 * actually paints it, so WP 7.1 responsive styles match the base value.
	 *
	 * @dataProvider relocating_blocks
	 *
	 * @param string                $slug     Block directory slug.
	 * @param array<string, string> $expected Selector path => expected target.
	 */
	public function test_selectors_target_the_element_that_paints_the_support( $slug, $expected ) {
		$metadata = $this->block_metadata( $slug );
		$wrapper  = '.wp-block-designsetgo-' . $slug;

		$this->assertArrayHasKey(
			'selectors',
			$metadata,
			"designsetgo/$slug relocates block supports, so it must declare `selectors`; without them WP 7.1 puts responsive styles on the wrapper."
		);

		foreach ( $expected as $path => $target ) {
			$actual = $this->selector_at( $metadata['selectors'], $path );

			$this->assertNotNull( $actual, "designsetgo/$slug is missing the `$path` selector." );

			$expected_selector = 'wrapper' === $target ? $wrapper : $wrapper . ' ' . $target;
			$this->assertSame( $expected_selector, $actual, "designsetgo/$slug `$path` selector points at the wrong element." );
		}
	}

	/**
	 * Every relocated selector must start with the block's own root class.
	 *
	 * Core builds the state selector by swapping that leading token for the
	 * generated `wp-states-*` class and keeping the remainder, so a selector
	 * that does not lead with the block class produces a rule scoped to the
	 * wrong thing — or to the whole page.
	 *
	 * @dataProvider relocating_blocks
	 *
	 * @param string                $slug      Block directory slug.
	 * @param array<string, string> $_expected Unused here.
	 */
	public function test_selectors_are_scoped_to_the_block( $slug, $_expected ) {
		$metadata = $this->block_metadata( $slug );
		$wrapper  = '.wp-block-designsetgo-' . $slug;

		array_walk_recursive(
			$metadata['selectors'],
			function ( $selector ) use ( $wrapper, $slug ) {
				$this->assertStringStartsWith(
					$wrapper,
					$selector,
					"designsetgo/$slug has an unscoped selector: $selector"
				);
			}
		);
	}

	/**
	 * WP 7.1 reads meta.public to decide whether an ability reaches external
	 * clients (MCP adapters, AI agents) and defaults it to false, so abilities
	 * we publish over REST have to opt in explicitly.
	 */
	public function test_abilities_default_to_public_when_shown_in_rest() {
		$config = Abstract_Ability::normalize_config( array( 'show_in_rest' => true ) );

		$this->assertTrue( $config['meta']['public'] );
		$this->assertTrue( $config['meta']['show_in_rest'] );
	}

	/**
	 * An ability kept out of REST is not handed to other clients either.
	 */
	public function test_abilities_hidden_from_rest_are_not_public() {
		$config = Abstract_Ability::normalize_config( array( 'show_in_rest' => false ) );

		$this->assertFalse( $config['meta']['public'] );
	}

	/**
	 * An explicit `public` wins over the show_in_rest-derived default.
	 */
	public function test_explicit_public_flag_is_respected() {
		$config = Abstract_Ability::normalize_config(
			array(
				'show_in_rest' => true,
				'public'       => false,
			)
		);

		$this->assertFalse( $config['meta']['public'] );
		$this->assertTrue( $config['meta']['show_in_rest'] );
		$this->assertArrayNotHasKey( 'public', $config, 'public belongs under meta, not at the top level.' );
	}

	/**
	 * WP 7.1 throws on a non-boolean flag where 6.9 accepted anything truthy.
	 */
	public function test_exposure_flags_are_cast_to_booleans() {
		$config = Abstract_Ability::normalize_config(
			array(
				'show_in_rest' => 1,
				'public'       => 'yes',
			)
		);

		$this->assertIsBool( $config['meta']['show_in_rest'] );
		$this->assertIsBool( $config['meta']['public'] );
	}

	/**
	 * Read a block's block.json.
	 *
	 * @param string $slug Block directory slug.
	 * @return array<string, mixed>
	 */
	private function block_metadata( $slug ) {
		$path = DESIGNSETGO_PLUGIN_DIR . '/src/blocks/' . $slug . '/block.json';

		$this->assertFileExists( $path );

		$metadata = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a bundled file in tests.

		$this->assertIsArray( $metadata, "block.json for $slug is not valid JSON." );

		return $metadata;
	}

	/**
	 * Resolve a dot path within the `selectors` tree.
	 *
	 * @param array<string, mixed> $selectors Selectors tree.
	 * @param string               $path      Dot path, e.g. 'spacing.margin'.
	 * @return string|null
	 */
	private function selector_at( $selectors, $path ) {
		$value = $selectors;

		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! isset( $value[ $segment ] ) ) {
				return null;
			}
			$value = $value[ $segment ];
		}

		return is_string( $value ) ? $value : null;
	}
}

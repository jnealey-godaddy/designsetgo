<?php
/**
 * Container overlay opacity must match save() in the Abilities inserter.
 *
 * Section, Row, Grid and Scroll Accordion Item write `--dsgo-overlay-opacity`
 * beside `--dsgo-overlay-color`: 0.65 for presets and opaque colours, 1 when
 * the colour carries its own alpha (so alpha and opacity do not multiply).
 * Block_Inserter::overlay_opacity_for_color() is the PHP twin of
 * getOverlayOpacity() in src/utils/overlay-opacity.js; both read
 * tests/fixtures/overlay-opacity-cases.json.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;
use DesignSetGo\Abilities\Serializers\Serializer_Support;

/**
 * Overlay opacity tests for the Abilities block-insertion path.
 *
 * @group abilities
 * @group blocks
 */
class Block_Inserter_Overlay_Opacity_Test extends WP_UnitTestCase {

	/**
	 * Shared PHP/JS fixture.
	 *
	 * @return array<string, mixed>
	 */
	private static function fixture(): array {
		return json_decode(
			file_get_contents( dirname( __DIR__ ) . '/fixtures/overlay-opacity-cases.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			true
		);
	}

	/**
	 * Colour cases.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function opacity_cases(): array {
		$cases = array();
		foreach ( self::fixture()['cases'] as $case ) {
			$cases[ 'color "' . $case['color'] . '"' ] = array( $case['color'], $case['opacity'] );
		}
		return $cases;
	}

	/**
	 * The PHP helper matches the shared expectation for every colour.
	 *
	 * @dataProvider opacity_cases
	 *
	 * @param string $color    Overlay colour.
	 * @param string $expected Expected opacity.
	 */
	public function test_opacity_matches_shared_expectation( string $color, string $expected ): void {
		$this->assertSame( $expected, Block_Inserter::overlay_opacity_for_color( $color ) );
	}

	/**
	 * Root element class set and style declarations of the first tag.
	 *
	 * @param string $html HTML.
	 * @return array{classes: string[], styles: array<string, string>}
	 */
	private function root_shape( string $html ): array {
		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag();

		$classes = preg_split( '/\s+/', trim( (string) $processor->get_attribute( 'class' ) ) );
		sort( $classes );

		$styles = array();
		foreach ( explode( ';', (string) $processor->get_attribute( 'style' ) ) as $declaration ) {
			$parts = explode( ':', $declaration, 2 );
			if ( 2 === count( $parts ) ) {
				$styles[ trim( $parts[0] ) ] = trim( $parts[1] );
			}
		}
		ksort( $styles );

		return array(
			'classes' => $classes,
			'styles'  => $styles,
		);
	}

	/**
	 * Section save() markup cases.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function section_markup_cases(): array {
		$cases = array();
		foreach ( self::fixture()['sectionSaveMarkup'] as $color => $html ) {
			$cases[ 'color "' . $color . '"' ] = array( $color, $html );
		}
		return $cases;
	}

	/**
	 * The inserter's Section markup matches save() for a preset and an alpha hex.
	 *
	 * @dataProvider section_markup_cases
	 *
	 * @param string $color     Overlay colour.
	 * @param string $save_html save() output captured from JS.
	 */
	public function test_section_markup_matches_save( string $color, string $save_html ): void {
		$markup = Block_Inserter::build_block_markup( 'designsetgo/section', array( 'overlayColor' => $color ) );

		$this->assertSame( $this->root_shape( $save_html ), $this->root_shape( $markup ) );

		$inner = '<div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"></div></div>';
		$this->assertStringContainsString( $inner, $markup );
	}

	/**
	 * Every container writes the colour-aware opacity.
	 */
	public function test_row_grid_and_scroll_accordion_item_follow_the_colour(): void {
		foreach ( array( 'designsetgo/row', 'designsetgo/grid', 'designsetgo/scroll-accordion-item' ) as $block ) {
			$preset = $this->root_shape( Block_Inserter::build_block_markup( $block, array( 'overlayColor' => 'var:preset|color|contrast' ) ) );
			$this->assertSame( 'var(--wp--preset--color--contrast)', $preset['styles']['--dsgo-overlay-color'], $block );
			$this->assertSame( '0.65', $preset['styles']['--dsgo-overlay-opacity'], $block );

			$alpha = $this->root_shape( Block_Inserter::build_block_markup( $block, array( 'overlayColor' => '#1212127D' ) ) );
			$this->assertSame( '#1212127D', $alpha['styles']['--dsgo-overlay-color'], $block );
			$this->assertSame( '1', $alpha['styles']['--dsgo-overlay-opacity'], $block );
		}
	}

	/**
	 * Explicit-percentage cases from the shared PHP/JS fixture.
	 *
	 * @return array<string, array{0: string, 1: mixed, 2: string}>
	 */
	public function percent_cases(): array {
		$cases = array();
		foreach ( self::fixture()['percentCases'] as $case ) {
			$label           = $case['color'] . ' @ ' . wp_json_encode( $case['percent'] );
			$cases[ $label ] = array( $case['color'], $case['percent'], $case['opacity'] );
		}
		return $cases;
	}

	/**
	 * An explicit overlayOpacity wins over the colour, exactly as in JS.
	 *
	 * @dataProvider percent_cases
	 * @param string $color    Overlay colour.
	 * @param mixed  $percent  overlayOpacity value, or null when absent.
	 * @param string $expected Expected opacity.
	 */
	public function test_explicit_percent_matches_shared_expectation( string $color, $percent, string $expected ): void {
		$attributes = array( 'overlayColor' => $color );
		if ( null !== $percent ) {
			$attributes['overlayOpacity'] = $percent;
		}
		$this->assertSame( $expected, Serializer_Support::overlay_opacity( $attributes ) );
	}

	/**
	 * Every container writes an explicit overlayOpacity instead of the default.
	 */
	public function test_every_container_writes_an_explicit_opacity(): void {
		foreach ( array( 'designsetgo/section', 'designsetgo/row', 'designsetgo/grid', 'designsetgo/scroll-accordion-item' ) as $block ) {
			$shape = $this->root_shape(
				Block_Inserter::build_block_markup(
					$block,
					array(
						'overlayColor'   => 'var:preset|color|contrast',
						'overlayOpacity' => 80,
					)
				)
			);
			$this->assertSame( '0.8', $shape['styles']['--dsgo-overlay-opacity'], $block );
		}
	}
}

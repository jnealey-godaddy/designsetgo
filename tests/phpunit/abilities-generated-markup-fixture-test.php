<?php
/**
 * Keeps the generated-markup fixture in step with the PHP serializer.
 *
 * The fixture is the handoff to tests/unit/ability-generated-markup.test.js,
 * which parses it with the REAL block registrations and save() implementations
 * and asserts the editor would find every block valid. PHP cannot run save(),
 * and JavaScript cannot run the PHP serializer, so the fixture is the only
 * place the two meet.
 *
 * This test fails whenever the PHP output drifts from the committed fixture.
 * Regenerate with:
 *
 *   DSGO_UPDATE_FIXTURES=1 vendor/bin/phpunit --filter Abilities_Generated_Markup_Fixture
 *
 * then run the JS suite to confirm the new markup is still valid.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Inserter;

/**
 * Generated-markup fixture test.
 */
class Abilities_Generated_Markup_Fixture_Test extends WP_UnitTestCase {

	/**
	 * Fixture path.
	 *
	 * @return string
	 */
	private function fixture_path(): string {
		return dirname( __DIR__ ) . '/unit/__fixtures__/ability-generated-markup.json';
	}

	/**
	 * The payloads to serialize.
	 *
	 * Keep the first entry as the reported failure: a Section carrying preset
	 * colors, holding a Pill (dynamic), a Paragraph with a text color, a Fifty
	 * Fifty (static, previously self-closing) and an Icon Button.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function payloads(): array {
		return array_merge( $this->default_payloads(), $this->authored_payloads() );
	}

	/**
	 * Hand-written payloads covering interesting attribute combinations.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function authored_payloads(): array {
		return array(
			'section-pill-paragraph-fifty-fifty-icon-button' => array(
				'name'        => 'designsetgo/section',
				'attributes'  => array(
					'backgroundColor' => 'base',
					'style'           => array(
						'spacing' => array(
							'padding' => array(
								'top'    => 'var:preset|spacing|50',
								'bottom' => 'var:preset|spacing|50',
							),
						),
					),
				),
				'innerBlocks' => array(
					array(
						'name'       => 'designsetgo/pill',
						'attributes' => array( 'text' => 'New' ),
					),
					array(
						'name'       => 'core/paragraph',
						'attributes' => array(
							'content'   => 'Intro copy for the section.',
							'textColor' => 'contrast',
						),
					),
					array(
						'name'        => 'designsetgo/fifty-fifty',
						'attributes'  => array(
							'align'             => 'full',
							'mediaPosition'     => 'right',
							'mediaUrl'          => 'https://example.com/photo.jpg',
							'mediaAlt'          => 'A photo',
							'verticalAlignment' => 'top',
							'minHeight'         => '480px',
							'contentPadding'    => 'var:preset|spacing|40',
						),
						'innerBlocks' => array(
							array(
								'name'       => 'core/heading',
								'attributes' => array(
									'level'   => 3,
									'content' => 'Split section',
								),
							),
						),
					),
					array(
						'name'       => 'designsetgo/icon-button',
						'attributes' => array(
							'text'            => 'Get started',
							'url'             => '/start',
							'icon'            => 'lightning',
							'iconPosition'    => 'end',
							'justification'   => 'center',
							'backgroundColor' => 'primary',
							'textColor'       => 'base',
						),
					),
				),
			),
			'hotspot-defaults'                               => array(
				'name'        => 'designsetgo/hotspot',
				'attributes'  => array(),
				'innerBlocks' => array(),
			),
			'hotspot-configured'                             => array(
				'name'        => 'designsetgo/hotspot',
				'attributes'  => array(
					'imageUrl'               => 'https://example.com/map.jpg',
					'imageAlt'               => 'A map',
					'trigger'                => 'hover',
					'tooltipPosition'        => 'right',
					'tooltipWidth'           => 320,
					'animation'              => 'scale',
					'sequenceDuration'       => 1200,
					'markerColor'            => '#ffffff',
					'markerBackgroundColor'  => 'var:preset|color|primary',
					'tooltipBackgroundColor' => 'rgb(10, 10, 10)',
					'tooltipTextColor'       => 'not-a-colour',
				),
				'innerBlocks' => array(),
			),
			'text-path-defaults'                             => array(
				'name'        => 'designsetgo/text-path',
				'attributes'  => array( 'uniqueId' => 'tp1' ),
				'innerBlocks' => array(),
			),
			'text-path-circle-linked'                        => array(
				'name'        => 'designsetgo/text-path',
				'attributes'  => array(
					'uniqueId'              => 'tp2',
					'text'                  => 'Around we go',
					'pathType'              => 'circle',
					'circleBackgroundColor' => 'var:preset|color|primary',
					'guideColor'            => '#333333',
					'showPath'              => true,
					'startOffset'           => 25,
					'pathFontSize'          => 72,
					'wordSpacing'           => 6,
					'pathPadding'           => 12,
					'rotation'              => 45,
					'guideOpacity'          => 0.5,
					'guideStrokeWidth'      => 4,
					'pathWidth'             => 80,
					'pathAlignment'         => 'center',
					'direction'             => 'rtl',
					'url'                   => 'https://example.com/loop',
					'target'                => true,
					'motion'                => true,
					'motionDuration'        => 30,
					'motionDirection'       => 'reverse',
				),
				'innerBlocks' => array(),
			),
			'text-path-arc'                                  => array(
				'name'        => 'designsetgo/text-path',
				'attributes'  => array(
					'uniqueId' => 'tp3',
					'text'     => 'Over the top',
					'pathType' => 'arc',
					'arcSize'  => 60,
				),
				'innerBlocks' => array(),
			),
			'comparison-table-defaults'                      => array(
				'name'        => 'designsetgo/comparison-table',
				'attributes'  => array(),
				'innerBlocks' => array(),
			),
			'comparison-table-configured'                    => array(
				'name'        => 'designsetgo/comparison-table',
				'attributes'  => array(
					'alternatingRows'       => false,
					'responsiveMode'        => 'stack',
					'featuredColumnColor'   => 'var:preset|color|primary',
					'headerBackgroundColor' => '#101010',
					'headerTextColor'       => '#ffffff',
					'ctaStyle'              => 'outlined',
					'columns'               => array(
						array(
							'name'     => 'Free',
							'link'     => '',
							'linkText' => 'Start',
							'featured' => false,
						),
						array(
							'name'     => 'Team',
							'link'     => 'https://example.com/team',
							'linkText' => 'Upgrade',
							'featured' => true,
						),
					),
					'rows'                  => array(
						array(
							'label'   => 'Seats',
							'tooltip' => 'How many people can sign in',
							'cells'   => array(
								array(
									'type'  => 'text',
									'value' => '1',
								),
								array(
									'type'  => 'text',
									'value' => '25',
								),
							),
						),
						array(
							'label'   => 'SSO',
							'tooltip' => '',
							'cells'   => array(
								array(
									'type'  => 'cross',
									'value' => '',
								),
								array(
									'type'  => 'check',
									'value' => '',
								),
							),
						),
					),
				),
				'innerBlocks' => array(),
			),
			'timeline-item-defaults'                         => array(
				'name'        => 'designsetgo/timeline-item',
				'attributes'  => array(
					'date'  => '2024',
					'title' => 'Launch',
				),
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'What happened.' ),
					),
				),
			),
			'timeline-item-linked'                           => array(
				'name'        => 'designsetgo/timeline-item',
				'attributes'  => array(
					'date'              => '2025',
					'title'             => 'Next',
					'isActive'          => true,
					'linkUrl'           => 'https://example.com/next',
					'linkTarget'        => '_blank',
					'customMarkerColor' => 'var:preset|color|accent',
				),
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'Coming up.' ),
					),
				),
			),
			'timeline-item-image-marker'                     => array(
				'name'        => 'designsetgo/timeline-item',
				'attributes'  => array(
					'title'    => 'With a photo',
					'imageUrl' => 'https://example.com/face.jpg',
				),
				'innerBlocks' => array(),
			),
			'hotspot-item-defaults'                          => array(
				'name'        => 'designsetgo/hotspot-item',
				'attributes'  => array( 'uniqueId' => 'abc123' ),
				'innerBlocks' => array(),
			),
			'hotspot-item-linked'                            => array(
				'name'        => 'designsetgo/hotspot-item',
				'attributes'  => array(
					'uniqueId'        => 'def456',
					'x'               => 130,
					'y'               => 25.5,
					'originX'         => 'left',
					'originY'         => 'bottom',
					'label'           => 'A',
					'url'             => 'https://example.com/detail',
					'tooltip'         => 'More detail here',
					'tooltipPosition' => 'bottom',
					'tooltipWidth'    => 280,
					'trigger'         => 'hover',
					'animation'       => 'fade',
					'sequenceOrder'   => 3,
				),
				'innerBlocks' => array(),
			),
			'hotspot-item-click-trigger'                     => array(
				'name'        => 'designsetgo/hotspot-item',
				'attributes'  => array(
					'uniqueId' => 'ghi789',
					'label'    => 'Detail',
					'trigger'  => 'click',
				),
				'innerBlocks' => array(),
			),
			'advanced-heading'                               => array(
				'name'        => 'designsetgo/advanced-heading',
				'attributes'  => array(
					'level'     => 3,
					'textAlign' => 'center',
					'style'     => array( 'spacing' => array( 'blockGap' => 'var:preset|spacing|20' ) ),
				),
				'innerBlocks' => array(
					array(
						'name'       => 'designsetgo/heading-segment',
						'attributes' => array( 'content' => 'Bold idea' ),
					),
				),
			),
			'blobs-defaults'                                 => array(
				'name'        => 'designsetgo/blobs',
				'attributes'  => array(),
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'Inside a blob.' ),
					),
				),
			),
			'blobs-configured'                               => array(
				'name'        => 'designsetgo/blobs',
				'attributes'  => array(
					'blobShape'         => 'shape-4',
					'blobAnimation'     => 'float',
					'animationDuration' => '12s',
					'animationEasing'   => 'ease-out',
					'size'              => '420px',
					'height'            => '380px',
					'maxWidth'          => '640px',
					'enableOverlay'     => true,
					'overlayColor'      => 'primary',
					'overlayOpacity'    => 35,
				),
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'Inside a blob.' ),
					),
				),
			),
			'heading-segment'                                => array(
				'name'        => 'designsetgo/heading-segment',
				'attributes'  => array( 'content' => 'emphasis' ),
				'innerBlocks' => array(),
			),
			'timeline-defaults'                              => array(
				'name'        => 'designsetgo/timeline',
				'attributes'  => array(),
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'An event.' ),
					),
				),
			),
			'timeline-configured'                            => array(
				'name'        => 'designsetgo/timeline',
				'attributes'  => array(
					'orientation'     => 'horizontal',
					'layout'          => 'right',
					'markerStyle'     => 'square',
					'markerSize'      => 24,
					'lineColor'       => '#ff0000',
					'markerColor'     => '#00ff00',
					'itemSpacing'     => '3rem',
					'animateOnScroll' => false,
					'align'           => 'wide',
				),
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'An event.' ),
					),
				),
			),
			// Nested through real scroll-slide children, not a bare paragraph:
			// the child is the block that was misclassified as purely dynamic,
			// and a paragraph directly inside the parent cannot catch that.
			'scroll-slides-defaults'                         => array(
				'name'        => 'designsetgo/scroll-slides',
				'attributes'  => array(),
				'innerBlocks' => array(
					array(
						'name'        => 'designsetgo/scroll-slide',
						'attributes'  => array( 'navHeading' => 'First' ),
						'innerBlocks' => array(
							array(
								'name'       => 'core/paragraph',
								'attributes' => array( 'content' => 'Panel one.' ),
							),
						),
					),
					array(
						'name'        => 'designsetgo/scroll-slide',
						'attributes'  => array( 'navHeading' => 'Second' ),
						'innerBlocks' => array(
							array(
								'name'       => 'core/heading',
								'attributes' => array(
									'level'   => 3,
									'content' => 'Panel two',
								),
							),
						),
					),
					array(
						'name'        => 'designsetgo/scroll-slide',
						'attributes'  => array(),
						'innerBlocks' => array(
							array(
								'name'       => 'core/paragraph',
								'attributes' => array( 'content' => 'Panel three.' ),
							),
						),
					),
				),
			),
			// Pins the align class on a hybrid block: save() emits it from the
			// block's declared align support, and the serializer omitted it.
			// Every Tabs colour custom property: the serializer emitted only the
			// gap, so any Tabs block given colours stored markup save() would
			// not reproduce.
			// Hover and overlay custom properties on the container blocks: all
			// three write the same five, and none were emitted.
			'section-hover-and-overlay'                      => array(
				'name'        => 'designsetgo/section',
				'attributes'  => array(
					'hoverBackgroundColor'       => '#111111',
					'hoverTextColor'             => '#ffffff',
					'hoverIconBackgroundColor'   => '#222222',
					'hoverButtonBackgroundColor' => '#333333',
					'overlayColor'               => 'var:preset|color|base',
				),
				'innerBlocks' => array(),
			),
			'grid-tagname-and-hover'                         => array(
				'name'        => 'designsetgo/grid',
				'attributes'  => array(
					'tagName'              => 'section',
					'hoverBackgroundColor' => '#111111',
				),
				'innerBlocks' => array(),
			),
			'row-hover'                                      => array(
				'name'        => 'designsetgo/row',
				'attributes'  => array( 'hoverTextColor' => '#ffffff' ),
				'innerBlocks' => array(),
			),
			// counter-group reads columns/columnsTablet/columnsMobile - it was
			// reading the Grid block's attribute names.
			'counter-group-columns'                          => array(
				'name'        => 'designsetgo/counter-group',
				'attributes'  => array(
					'columns'       => 4,
					'columnsTablet' => 3,
					'columnsMobile' => 2,
				),
				'innerBlocks' => array(),
			),
			'modal-labelled-and-coloured'                    => array(
				'name'        => 'designsetgo/modal',
				'attributes'  => array(
					'modalLabel'      => 'Newsletter',
					'backgroundColor' => 'base',
				),
				'innerBlocks' => array(),
			),
			'modal-trigger-coloured'                         => array(
				'name'        => 'designsetgo/modal-trigger',
				'attributes'  => array(
					'text'            => 'Open',
					'backgroundColor' => 'base',
					'textColor'       => 'contrast',
				),
				'innerBlocks' => array(),
			),
			'tabs-colored'                                   => array(
				'name'        => 'designsetgo/tabs',
				'attributes'  => array(
					'uniqueId'                  => 'tabs1',
					'tabColor'                  => '#111111',
					'tabBackgroundColor'        => '#eeeeee',
					'tabContentBackgroundColor' => 'var:preset|color|base',
					'activeTabColor'            => '#000000',
					'activeTabBackgroundColor'  => '#ffffff',
					'tabBorderColor'            => '#cccccc',
					'tabHoverColor'             => '#222222',
					'tabHoverBackgroundColor'   => '#dddddd',
				),
				'innerBlocks' => array(
					array(
						'name'       => 'designsetgo/tab',
						'attributes' => array(),
					),
				),
			),
			'query-alignwide'                                => array(
				'name'        => 'designsetgo/query',
				'attributes'  => array( 'align' => 'wide' ),
				'innerBlocks' => array(
					array(
						'name'        => 'designsetgo/query-results',
						'attributes'  => array(),
						'innerBlocks' => array(),
					),
				),
			),
			'query-with-results-and-no-results'              => array(
				'name'        => 'designsetgo/query',
				'attributes'  => array(),
				'innerBlocks' => array(
					array(
						'name'        => 'designsetgo/query-results',
						'attributes'  => array(),
						'innerBlocks' => array(
							array(
								'name'       => 'core/heading',
								'attributes' => array(
									'level'   => 3,
									'content' => 'A result',
								),
							),
						),
					),
					array(
						'name'        => 'designsetgo/query-no-results',
						'attributes'  => array(),
						'innerBlocks' => array(
							array(
								'name'       => 'core/paragraph',
								'attributes' => array( 'content' => 'Nothing found.' ),
							),
						),
					),
				),
			),
			'scroll-slides-configured'                       => array(
				'name'        => 'designsetgo/scroll-slides',
				'attributes'  => array(
					'minHeight'       => '80vh',
					'maxHeight'       => '1200px',
					'constrainWidth'  => false,
					'overlayColor'    => 'primary',
					'overlayOpacity'  => 45,
					'navColor'        => '#111111',
					'navActiveColor'  => 'accent',
				),
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'A panel.' ),
					),
				),
			),
			'sticky-sections'                                => array(
				'name'        => 'designsetgo/sticky-sections',
				'attributes'  => array( 'stickyOffset' => '80px' ),
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'Sticky one.' ),
					),
				),
			),
			'section-divider-defaults'                       => array(
				'name'        => 'designsetgo/section-divider',
				'attributes'  => array(),
				'innerBlocks' => array(),
			),
			'section-divider-configured'                     => array(
				'name'        => 'designsetgo/section-divider',
				'attributes'  => array(
					'shape'           => 'waves',
					'height'          => 120,
					'width'           => 140,
					'flipX'           => true,
					'fillColor'       => 'primary',
					'backgroundColor' => '#0a0a0a',
					'align'           => 'full',
				),
				'innerBlocks' => array(),
			),
			'fifty-fifty-defaults'                           => array(
				'name'        => 'designsetgo/fifty-fifty',
				'attributes'  => array(),
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'Content side.' ),
					),
				),
			),
			'section-gradient-and-text-color'                => array(
				'name'        => 'designsetgo/section',
				'attributes'  => array(
					'textColor' => 'contrast',
					'style'     => array(
						'color' => array( 'background' => '#112233' ),
					),
				),
				'innerBlocks' => array(
					array(
						'name'       => 'core/paragraph',
						'attributes' => array( 'content' => 'On a custom background.' ),
					),
				),
			),
		);
	}

	/**
	 * A defaults probe for every block the inserter can serialize.
	 *
	 * The hand-written payloads above cover interesting attribute combinations,
	 * but the cheapest and most damaging failure is a block that is invalid with
	 * NO attributes set — every insert of it is broken. Four blocks were in that
	 * state (form-builder, progress-bar, scroll-marquee, heading-segment), each
	 * emitting a declaration or attribute save() never writes. Generating these
	 * from the registry means a new block is covered the day it lands.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function default_payloads(): array {
		$payloads = array();

		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block_type ) {
			if ( 0 !== strpos( (string) $name, 'designsetgo/' ) ) {
				continue;
			}

			if ( null !== Block_Inserter::get_serialization_gap( (string) $name ) ) {
				continue;
			}

			// WooCommerce-gated blocks are skipped: Plugin::gate_woocommerce_blocks()
			// only registers them when WooCommerce is active, so including them
			// would make the fixture depend on which plugins the environment has
			// and drift between a local run and CI. Both are server-rendered, so
			// they serialize to a bare comment and add nothing here anyway.
			if ( in_array( (string) $name, array( 'designsetgo/product-showcase-hero', 'designsetgo/product-categories-grid' ), true ) ) {
				continue;
			}

			$payloads[ 'defaults::' . $name ] = array(
				'name'        => (string) $name,
				'attributes'  => array(),
				'innerBlocks' => array(),
			);
		}

		ksort( $payloads );

		return $payloads;
	}

	/**
	 * Generate markup for every payload.
	 *
	 * @return array<string, string>
	 */
	private function generate(): array {
		$generated = array();

		foreach ( $this->payloads() as $label => $payload ) {
			$generated[ $label ] = self::stabilise_ids(
				Block_Inserter::build_block_markup(
					$payload['name'],
					$payload['attributes'],
					$payload['innerBlocks']
				)
			);
		}

		return $generated;
	}

	/**
	 * Replace generated UUIDs with stable placeholders.
	 *
	 * Several blocks seed a `uniqueId` on insert, so their markup differs on
	 * every run and the fixture could never match. Each distinct UUID maps to a
	 * distinct placeholder, so ids that must agree across the markup (a
	 * trigger's aria-controls and its panel's id, say) still agree — which is
	 * what the JS side validates.
	 *
	 * @param string $markup Generated markup.
	 * @return string Markup with UUIDs replaced.
	 */
	private static function stabilise_ids( string $markup ): string {
		preg_match_all( '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $markup, $matches );

		foreach ( array_values( array_unique( $matches[0] ) ) as $index => $uuid ) {
			$markup = str_replace(
				$uuid,
				sprintf( '00000000-0000-4000-8000-%012d', $index ),
				$markup
			);
		}

		return $markup;
	}

	/**
	 * The committed fixture matches what the serializer produces today.
	 */
	public function test_fixture_matches_generated_markup(): void {
		$generated = $this->generate();
		$path      = $this->fixture_path();

		if ( getenv( 'DSGO_UPDATE_FIXTURES' ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir, WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture regeneration, opt-in via env var.
			if ( ! is_dir( dirname( $path ) ) ) {
				mkdir( dirname( $path ), 0777, true ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir, WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture regeneration.
			}
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Test fixture regeneration.
				$path,
				wp_json_encode( $generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
			);
			$this->addToAssertionCount( 1 );
			return;
		}

		$this->assertFileExists( $path, 'Run with DSGO_UPDATE_FIXTURES=1 to create it.' );

		$fixture = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Test fixture.

		$this->assertSame(
			$generated,
			$fixture,
			'Generated markup drifted from the fixture. Regenerate with DSGO_UPDATE_FIXTURES=1, then run the JS suite to confirm the new markup still validates against save().'
		);
	}

	/**
	 * Nothing in the fixture payloads hits a serializer gap.
	 */
	public function test_fixture_payloads_are_all_serializable(): void {
		foreach ( $this->payloads() as $label => $payload ) {
			$this->assertNull(
				Block_Inserter::check_serialization_coverage( $payload['name'], $payload['innerBlocks'] ),
				"Payload {$label} contains a block with no serializer."
			);
		}
	}

	/**
	 * Every block whose save() emits markup has a PHP serializer.
	 *
	 * The inserter skips blocks it considers dynamic, on the grounds that a
	 * server-rendered block has no save() output to reproduce. A hybrid block
	 * breaks that assumption: it has a render.php AND a save.js, and its stored
	 * markup still has to carry the wrapper save() emits, or the editor reports
	 * the block as invalid and any frontend script looking for that wrapper
	 * finds nothing. designsetgo/scroll-slide shipped that way.
	 *
	 * PHP cannot run save() to tell the two apart, so the list comes from
	 * tests/unit/blocks-with-save-output.test.js, which asks the real block
	 * registrations and writes the fixture this reads.
	 */
	public function test_every_block_with_save_output_has_a_serializer(): void {
		$path = dirname( __DIR__ ) . '/unit/__fixtures__/blocks-with-save-output.json';

		$this->assertFileExists(
			$path,
			'Regenerate with: DSGO_UPDATE_FIXTURES=1 npx wp-scripts test-unit-js tests/unit/blocks-with-save-output.test.js'
		);

		$names   = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Test fixture.
		$missing = array();

		foreach ( (array) $names as $name ) {
			if ( null === Block_Inserter::generate_designsetgo_wrapper_html( (string) $name, array() ) ) {
				$missing[] = (string) $name;
			}
		}

		$this->assertSame(
			array(),
			$missing,
			'These blocks emit markup from save() but have no PHP serializer: ' . implode( ', ', $missing )
		);
	}

	/**
	 * Every fixture payload uses attribute values the blocks actually accept.
	 *
	 * An out-of-enum value is replaced by WordPress when it parses the block, so
	 * the payload would fail in the JS suite as an opaque markup diff. Failing
	 * here instead names the attribute and lists the allowed values.
	 */
	public function test_fixture_payloads_use_valid_attribute_values(): void {
		foreach ( $this->payloads() as $label => $payload ) {
			$problems = Block_Inserter::find_invalid_attribute_values(
				array(
					array(
						'name'        => $payload['name'],
						'attributes'  => $payload['attributes'],
						'innerBlocks' => $payload['innerBlocks'],
					),
				)
			);

			$this->assertSame(
				array(),
				array_column( $problems, 'reason' ),
				"Payload {$label} uses an attribute value the block does not accept."
			);
		}
	}
}

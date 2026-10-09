<?php
/** Installed native-generation capabilities.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Info\List_Blocks;
use DesignSetGo\Abilities\Info\Get_Design_Context;

/** Regression coverage for generation contract test. */
class Generation_Contract_Test extends WP_UnitTestCase {

	/** Generators discover applicable layout fields and their routing. */
	public function test_discovery_exposes_typed_layout_overrides(): void {
		$result = ( new List_Blocks() )->execute( array( 'blocks' => array( 'designsetgo/grid', 'designsetgo/row' ), 'detail' => 'full' ) );
		$blocks = array_column( $result['blocks'], null, 'name' );
		$grid   = $blocks['designsetgo/grid']['generation']['layoutOverrides'];
		$this->assertSame( 'dsgoLayout', $grid['attribute'] );
		$this->assertSame( 1, $grid['version'] );
		$this->assertSame( 767, $grid['devices']['mobile'] );
		$this->assertArrayHasKey( 'gridTemplateAreas', $grid['properties'] );
		$this->assertArrayNotHasKey( 'direction', $grid['properties'] );
		$this->assertArrayHasKey( 'direction', $blocks['designsetgo/row']['generation']['layoutOverrides']['properties'] );
		$this->assertSame( 'inner', $grid['properties']['gridTemplateAreas']['target'] );
	}

	/** Runtime discovery fields must be advertised as output, not inputs. */
	public function test_schema_advertises_generation_contract_as_output(): void {
		$config = ( new List_Blocks() )->get_config();
		$this->assertArrayHasKey( 'generationContract', $config['output_schema']['properties'] );
		$this->assertArrayNotHasKey( 'generationContract', $config['input_schema']['properties'] );
	}

	/** Test discovery returns installed versions and supported targets. */
	public function test_discovery_returns_installed_versions_and_supported_targets(): void {
		$result = ( new List_Blocks() )->execute(
			array(
				'blocks' => array( 'designsetgo/grid', 'designsetgo/icon-button' ),
				'detail' => 'full',
			)
		);
		$this->assertArrayHasKey( 'generationContract', $result );
		$this->assertSame( 1, $result['generationContract']['version'] );
		$this->assertSame( DESIGNSETGO_VERSION, $result['generationContract']['pluginVersion'] );
		$this->assertSame( get_bloginfo( 'version' ), $result['generationContract']['wordpressVersion'] );
		$this->assertSame( 767, $result['generationContract']['breakpoints']['mobileMax'] );
		$this->assertSame( 1024, $result['generationContract']['breakpoints']['tabletMax'] );
		$blocks = array_column( $result['blocks'], null, 'name' );
		$this->assertTrue( $blocks['designsetgo/grid']['generation']['serialization']['supported'] );
		$this->assertSame( 'static', $blocks['designsetgo/grid']['generation']['serialization']['mode'] );
		$this->assertSame( '.wp-block-designsetgo-grid > .dsgo-grid__inner', $blocks['designsetgo/grid']['generation']['targets']['layout'] );
		$this->assertSame( 'style.spacing.blockGap', $blocks['designsetgo/grid']['generation']['styleOwnership']['gap']['attribute'] );
		$this->assertSame( array( 'rowGap', 'columnGap' ), $blocks['designsetgo/grid']['generation']['styleOwnership']['gap']['fallbackAttributes'] );
		$this->assertSame( '.wp-block-designsetgo-icon-button .dsgo-icon-button', $blocks['designsetgo/icon-button']['selectors']['root'] );
	}

	/** Context and block discovery expose the same installed contract. */
	public function test_context_exposes_the_same_contract_and_registered_styles(): void {
		$list    = ( new List_Blocks() )->execute( array() );
		$context = ( new Get_Design_Context() )->execute( array() );
		$this->assertArrayHasKey( 'generationContract', $context );
		$this->assertSame( $list['generationContract'], $context['generationContract'] );
	}

	/** Dynamic blocks report their comment-only storage mode. */
	public function test_dynamic_blocks_are_reported_as_dynamic(): void {
		$result = ( new List_Blocks() )->execute( array( 'blocks' => array( 'designsetgo/form-email-field' ) ) );
		$this->assertArrayHasKey( 'generation', $result['blocks'][0] );
		$this->assertSame( 'dynamic', $result['blocks'][0]['generation']['serialization']['mode'] );
	}

	/** Hybrid render callbacks still require stored static save markup. */
	public function test_hybrid_blocks_report_static_serialization(): void {
		$result = ( new List_Blocks() )->execute( array( 'blocks' => array( 'designsetgo/slider' ) ) );
		$this->assertSame( 'static', $result['blocks'][0]['generation']['serialization']['mode'] );
	}
}

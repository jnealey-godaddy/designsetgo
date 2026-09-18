<?php
/**
 * The inserter must produce the same stored HTML as the editor's save() for
 * form colours and the grid mobile order extension, or the editor rejects
 * ability-inserted blocks with a validation error.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Inserter;

class Block_Inserter_Extension_Save_Props_Test extends WP_UnitTestCase {

	public function test_static_section_serializes_native_animation_props_and_omits_defaults() {
		$html = Block_Inserter::build_block_markup(
			'designsetgo/section',
			array( 'dsgoAnimationEnabled' => true, 'dsgoEntranceAnimation' => 'fadeInUp' ),
			array()
		);
		$this->assertStringContainsString( 'has-dsgo-animation dsgo-animation-fadeInUp', $html );
		$this->assertStringContainsString( 'data-dsgo-animation-enabled="true"', $html );
		$this->assertStringContainsString( 'data-dsgo-entrance-animation="fadeInUp"', $html );
		$this->assertStringNotContainsString( 'data-dsgo-animation-duration', $html );
		$this->assertStringNotContainsString( 'data-dsgo-animation-delay', $html );
	}

	public function test_static_section_without_animation_remains_unmarked() {
		$html = Block_Inserter::build_block_markup( 'designsetgo/section', array(), array() );
		$this->assertStringNotContainsString( 'has-dsgo-animation', $html );
		$this->assertStringNotContainsString( 'data-dsgo-animation-enabled', $html );
	}

	public function test_form_builder_colours_are_written_as_css_variables() {
		$html = Block_Inserter::build_block_markup(
			'designsetgo/form-builder',
			array(
				'formId'                      => 'inquiry',
				'fieldLabelColor'             => 'var:preset|color|contrast',
				'fieldBorderColor'            => 'var:preset|color|base-3',
				'fieldBackgroundColor'        => 'base',
				'submitButtonColor'           => 'primary-foreground',
				'submitButtonBackgroundColor' => '#112233',
			),
			array()
		);

		$this->assertStringContainsString( '--dsgo-form-label-color:var(--wp--preset--color--contrast)', $html );
		$this->assertStringContainsString( '--dsgo-form-border-color:var(--wp--preset--color--base-3)', $html );
		$this->assertStringContainsString( '--dsgo-form-field-bg:var(--wp--preset--color--base)', $html );
		$this->assertStringContainsString( 'color:var(--wp--preset--color--primary-foreground)', $html );
		$this->assertStringContainsString( 'background-color:#112233', $html );
		// Preset shorthand belongs in block JSON; only rendered HTML needs CSS vars.
		$this->assertStringNotContainsString( 'var:preset|', parse_blocks( $html )[0]['innerHTML'] );
	}

	public function test_mobile_order_is_written_only_when_it_differs_from_the_default() {
		$first = Block_Inserter::build_block_markup( 'designsetgo/section', array( 'dsgoMobileOrder' => 0 ), array() );
		$this->assertStringContainsString( '--dsgo-mobile-order:0', $first );

		$last = Block_Inserter::build_block_markup( 'designsetgo/section', array( 'dsgoMobileOrder' => 12 ), array() );
		$this->assertStringContainsString( '--dsgo-mobile-order:10', $last );

		$default = Block_Inserter::build_block_markup( 'designsetgo/section', array( 'dsgoMobileOrder' => 1 ), array() );
		$this->assertStringNotContainsString( '--dsgo-mobile-order', $default );

		$absent = Block_Inserter::build_block_markup( 'designsetgo/section', array(), array() );
		$this->assertStringNotContainsString( '--dsgo-mobile-order', $absent );
	}

	/**
	 * Grid spans above one reach the markup, as blocks.getSaveContent.extraProps writes them.
	 */
	public function test_grid_spans_are_written_like_the_save_filter() {
		$spans   = array(
			'dsgoColumnSpan' => 2,
			'dsgoRowSpan'    => 3,
		);
		$section = Block_Inserter::build_block_markup( 'designsetgo/section', $spans, array() );
		$this->assertStringContainsString( 'grid-column:span 2', $section );
		$this->assertStringContainsString( 'grid-row:span 3', $section );

		$image = Block_Inserter::build_block_markup(
			'core/image',
			array(
				'url'         => 'https://example.test/a.jpg',
				'dsgoRowSpan' => 2,
			)
		);
		$this->assertStringContainsString( '<figure style="grid-row:span 2" class="wp-block-image">', $image );

		$single = Block_Inserter::build_block_markup( 'designsetgo/section', array( 'dsgoColumnSpan' => 1 ), array() );
		$this->assertStringNotContainsString( 'grid-column', $single );
	}

	public function test_mobile_order_respects_the_excluded_block_setting() {
		update_option( 'designsetgo_settings', array( 'excluded_blocks' => array( 'designsetgo/*' ) ) );
		try {
			$html = Block_Inserter::build_block_markup( 'designsetgo/section', array( 'dsgoMobileOrder' => 0 ), array() );
			$this->assertStringNotContainsString( '--dsgo-mobile-order', $html );
		} finally {
			delete_option( 'designsetgo_settings' );
		}
	}
}

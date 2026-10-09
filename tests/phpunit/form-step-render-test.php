<?php
/**
 * Form step block rendering.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;

/**
 * Form step render test case.
 */
class Test_Form_Step_Render extends WP_UnitTestCase {

	/**
	 * A form with two steps (second untitled).
	 *
	 * @param string $form_id Form ID.
	 * @return string Block markup.
	 */
	private function form( $form_id ) {
		return '<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '","enableEmail":false} --><div class="wp-block-designsetgo-form-builder dsgo-form-builder">'
			. '<!-- wp:designsetgo/form-step {"title":"Your <b>details</b> &amp; more"} -->'
			. '<!-- wp:designsetgo/form-text-field {"fieldName":"name"} /-->'
			. '<!-- /wp:designsetgo/form-step -->'
			. '<!-- wp:designsetgo/form-step -->'
			. '<!-- wp:designsetgo/form-email-field {"fieldName":"email"} /-->'
			. '<!-- /wp:designsetgo/form-step -->'
			. '</div><!-- /wp:designsetgo/form-builder -->';
	}

	public function test_step_markup_heading_and_fields() {
		$html = do_blocks( $this->form( 'ms1' ) );

		$this->assertSame( 2, preg_match_all( '/<section[^>]*\bdata-dsgo-step="(\d+)"/', $html, $m ) );
		$this->assertSame( array( '1', '2' ), $m[1] );
		$this->assertMatchesRegularExpression( '/<section[^>]*class="[^"]*dsgo-form-step[^"]*"/', $html );

		// Title: tags stripped, escaped; heading id paired with aria-labelledby.
		$this->assertMatchesRegularExpression( '/<h3 class="dsgo-form-step__title" id="([^"]+)" tabindex="-1">Your details &amp; more<\/h3>/', $html );
		preg_match_all( '/aria-labelledby="([^"]+)"/', $html, $labelled );
		preg_match_all( '/<h3 class="dsgo-form-step__title" id="([^"]+)"/', $html, $ids );
		$this->assertSame( $ids[1], $labelled[1] );
		$this->assertCount( 2, array_unique( $ids[1] ) );

		// Untitled step falls back to "Step 2".
		$this->assertStringContainsString( '>Step 2</h3>', $html );

		// Fields render inside the step's field container.
		$this->assertMatchesRegularExpression( '/<div class="dsgo-form-step__fields">.*data-dsgo-field="name"/s', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_numbering_restarts_for_each_form() {
		$html = do_blocks( $this->form( 'ms2' ) . $this->form( 'ms3' ) );
		preg_match_all( '/data-dsgo-step="(\d+)"/', $html, $m );
		$this->assertSame( array( '1', '2', '1', '2' ), $m[1] );
	}

	public function test_block_registered_with_form_parent_and_fields_allow_step_parent() {
		$registry = \WP_Block_Type_Registry::get_instance();
		$step     = $registry->get_registered( 'designsetgo/form-step' );
		$this->assertNotNull( $step );
		$this->assertSame( array( 'designsetgo/form-builder' ), $step->parent );
		$this->assertSame( '', $step->attributes['title']['default'] );

		foreach ( array( 'text', 'email', 'textarea', 'number', 'phone', 'url', 'date', 'time', 'select', 'checkbox', 'hidden' ) as $type ) {
			$field = $registry->get_registered( 'designsetgo/form-' . $type . '-field' );
			$this->assertSame( array( 'designsetgo/form-builder', 'designsetgo/form-step' ), $field->parent, $type );
		}
	}

	public function test_catalog_lists_the_step_block() {
		$json  = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin/blocks-registry.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$names = array();
		array_walk_recursive(
			$json,
			function ( $value, $key ) use ( &$names ) {
				if ( 'name' === $key ) {
					$names[] = $value;
				}
			}
		);
		$this->assertContains( 'designsetgo/form-step', $names );
	}
}

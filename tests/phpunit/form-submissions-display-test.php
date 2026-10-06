<?php
/**
 * Submission detail rendering.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\Blocks\Form_Submissions;

/**
 * Submission display test case.
 */
class Test_Form_Submissions_Display extends WP_UnitTestCase {

	public function test_format_field_value() {
		$this->assertSame( 'a, b', Form_Submissions::format_field_value( array( 'a', 'b' ) ) );
		$this->assertSame( '3', Form_Submissions::format_field_value( 3 ) );
		$this->assertSame( '', Form_Submissions::format_field_value( null ) );
		$this->assertSame( 'x', Form_Submissions::format_field_value( array( 'x', array( 'nested' ), null ) ) );
	}

	public function test_details_render_labels_and_arrays() {
		$id = self::factory()->post->create( array( 'post_type' => 'dsgo_form_submission', 'post_status' => 'private' ) );
		update_post_meta(
			$id,
			'_dsg_form_fields',
			array(
				'topics'    => array( 'value' => array( 'News', 'Offers' ), 'type' => 'checkbox', 'label' => 'Topics <b>' ),
				'legacy_nm' => array( 'value' => 'Old', 'type' => 'text' ),
			)
		);

		ob_start();
		( new Form_Submissions() )->render_submission_details( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'News, Offers', $html );
		$this->assertStringContainsString( 'Topics &lt;b&gt;', $html, 'Labels are escaped.' );
		$this->assertStringContainsString( '<code>topics</code>', $html );
		$this->assertStringContainsString( '<strong>legacy_nm</strong>', $html, 'Pre-label submissions show the name.' );
	}
}

<?php
/**
 * Form / date filters and the Export button on the submissions list.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_Query;
use WP_UnitTestCase;
use DesignSetGo\Blocks\Form_Submissions_Export;
use DesignSetGo\Blocks\Form_Submissions_List_Filters;

/**
 * Submissions list filters test case.
 */
class Test_Form_Submissions_List_Filters extends WP_UnitTestCase {

	/**
	 * Filters under test.
	 *
	 * @var Form_Submissions_List_Filters
	 */
	private $filters;

	/**
	 * The main query before the test.
	 *
	 * @var WP_Query
	 */
	private $the_query;

	public function set_up() {
		parent::set_up();
		$this->filters   = new Form_Submissions_List_Filters();
		$this->the_query = $GLOBALS['wp_the_query'];
	}

	public function tear_down() {
		$GLOBALS['wp_the_query'] = $this->the_query;
		unset( $_GET['dsgo_form'], $_GET['dsgo_from'], $_GET['dsgo_to'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function make_submission( $form_id ) {
		$id = self::factory()->post->create( array( 'post_type' => 'dsgo_form_submission', 'post_status' => 'private' ) );
		update_post_meta( $id, '_dsg_form_id', $form_id );
		return $id;
	}

	private function render( $post_type = Form_Submissions_Export::POST_TYPE ) {
		ob_start();
		$this->filters->render_filters( $post_type );
		return ob_get_clean();
	}

	public function test_nothing_on_other_post_types() {
		$this->assertSame( '', $this->render( 'post' ) );
	}

	public function test_form_options_with_counts_and_selection() {
		$this->make_submission( 'contact' );
		$this->make_submission( 'contact' );
		$this->make_submission( 'newsletter' );
		$_GET['dsgo_form'] = 'newsletter';

		$html = $this->render();

		$this->assertStringContainsString( '<option value="contact">contact (2)</option>', $html );
		$this->assertStringContainsString( "<option value=\"newsletter\" selected='selected'>newsletter (1)</option>", $html );
		$this->assertStringContainsString( '<label for="dsgo-from">From date</label>', $html );
		$this->assertStringContainsString( 'name="' . Form_Submissions_Export::NONCE_NAME . '"', $html );
		$this->assertStringContainsString( '<button type="submit" name="dsgo_export" value="1" class="button">', $html );
	}

	public function test_form_ids_are_escaped() {
		$this->make_submission( '"><script>alert(1)</script>' );

		$html = $this->render();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&quot;&gt;&lt;script&gt;', $html );
	}

	public function test_cached_form_list_refreshes_when_a_submission_arrives() {
		$this->make_submission( 'contact' );
		$this->assertStringContainsString( 'contact (1)', $this->render() );

		$this->make_submission( 'contact' );
		$this->assertStringContainsString( 'contact (2)', $this->render() );
	}

	public function test_filters_apply_to_the_admin_main_submissions_query() {
		set_current_screen( 'edit-dsgo_form_submission' );
		$_GET['dsgo_form'] = 'contact';
		$_GET['dsgo_from'] = '2026-10-01';

		$query                  = new WP_Query();
		$GLOBALS['wp_the_query'] = $query;
		$query->set( 'post_type', Form_Submissions_Export::POST_TYPE );
		$this->filters->filter_list( $query );

		$this->assertSame( 'contact', $query->get( 'meta_query' )[0]['value'] );
		$this->assertSame( 2026, $query->get( 'date_query' )[0]['after']['year'] );
	}

	/**
	 * @dataProvider untouched_queries
	 *
	 * @param bool   $admin     Whether in wp-admin.
	 * @param bool   $main      Whether the main query.
	 * @param string $post_type Queried post type.
	 */
	public function test_other_queries_are_untouched( $admin, $main, $post_type ) {
		set_current_screen( $admin ? 'edit-dsgo_form_submission' : 'front' );
		$_GET['dsgo_form'] = 'contact';

		$query = new WP_Query();
		if ( $main ) {
			$GLOBALS['wp_the_query'] = $query;
		}
		$query->set( 'post_type', $post_type );
		$this->filters->filter_list( $query );

		$this->assertSame( '', $query->get( 'meta_query' ) );
	}

	public function untouched_queries() {
		return array(
			'front end'       => array( false, true, Form_Submissions_Export::POST_TYPE ),
			'secondary query' => array( true, false, Form_Submissions_Export::POST_TYPE ),
			'other post type' => array( true, true, 'post' ),
		);
	}
}

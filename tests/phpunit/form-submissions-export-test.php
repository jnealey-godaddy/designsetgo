<?php
/**
 * CSV export of form submissions.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WPDieException;
use DesignSetGo\Blocks\Form_Submissions_Export;

/**
 * Submissions export test case.
 */
class Test_Form_Submissions_Export extends WP_UnitTestCase {

	/**
	 * Exporter under test.
	 *
	 * @var Form_Submissions_Export
	 */
	private $export;

	public function set_up() {
		parent::set_up();
		$this->export = new Form_Submissions_Export();
	}

	public function tear_down() {
		unset( $_GET['dsgo_export'], $_GET['post_type'], $_REQUEST[ Form_Submissions_Export::NONCE_NAME ] );
		delete_option( 'timezone_string' );
		parent::tear_down();
	}

	private function make_submission( $form_id, $date, array $fields, array $meta = array() ) {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'private',
				'post_date'   => $date,
			)
		);
		update_post_meta( $id, '_dsg_form_id', $form_id );
		update_post_meta( $id, '_dsg_form_fields', wp_slash( $fields ) );
		update_post_meta( $id, '_dsg_submission_referer', 'https://example.com/contact/' );
		update_post_meta( $id, '_dsg_submission_ip', '198.51.100.7' );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	/**
	 * Run write_csv into memory and parse it back.
	 *
	 * @param array $args Export args.
	 * @return array Rows (header first), BOM stripped.
	 */
	private function export_rows( array $args ) {
		$handle = fopen( 'php://memory', 'w+' );
		$this->export->write_csv( $handle, array_merge( array( 'form_id' => '', 'from' => '', 'to' => '' ), $args ) );
		rewind( $handle );
		$bom = fread( $handle, 3 );
		$this->assertSame( "\xEF\xBB\xBF", $bom );
		$rows = array();
		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			$rows[] = $row;
		}
		fclose( $handle );
		return $rows;
	}

	public function test_headers_use_labels_with_name_fallback_and_disambiguation() {
		$this->make_submission(
			'f1',
			'2026-10-01 10:00:00',
			array(
				'first'  => array( 'value' => 'Pat', 'type' => 'text', 'label' => 'Name' ),
				'last'   => array( 'value' => 'Lee', 'type' => 'text', 'label' => 'Name' ),
				'legacy' => array( 'value' => 'x', 'type' => 'text' ),
			)
		);

		$rows = $this->export_rows( array( 'form_id' => 'f1' ) );
		$this->assertSame(
			array( 'Submission ID', 'Date', 'Form ID', 'Source URL', 'Name (first)', 'Name (last)', 'legacy', 'Email status', 'Webhook status' ),
			$rows[0]
		);
		$this->assertSame( array( 'Pat', 'Lee', 'x' ), array_slice( $rows[1], 4, 3 ) );
		$this->assertSame( '2026-10-01 10:00:00', $rows[1][1] );
		$this->assertNotContains( '198.51.100.7', $rows[1], 'IP is excluded by default.' );
	}

	public function test_union_of_fields_across_changed_form_latest_label_wins() {
		$this->make_submission( 'f2', '2026-10-01 10:00:00', array( 'a' => array( 'value' => '1', 'type' => 'text', 'label' => 'Old A' ) ) );
		$this->make_submission( 'f2', '2026-10-02 10:00:00', array( 'a' => array( 'value' => '2', 'type' => 'text', 'label' => 'New A' ), 'b' => array( 'value' => 'B', 'type' => 'text', 'label' => 'B' ) ) );

		$rows = $this->export_rows( array( 'form_id' => 'f2' ) );
		$this->assertSame( array( 'New A', 'B' ), array_slice( $rows[0], 4, 2 ) );
		$this->assertSame( array( '1', '' ), array_slice( $rows[1], 4, 2 ), 'Missing field is an empty cell.' );
		$this->assertSame( array( '2', 'B' ), array_slice( $rows[2], 4, 2 ) );
	}

	public function test_form_and_inclusive_date_filters() {
		$this->make_submission( 'f3', '2026-09-30 23:59:00', array( 'a' => array( 'value' => 'before', 'type' => 'text' ) ) );
		$this->make_submission( 'f3', '2026-10-01 00:00:00', array( 'a' => array( 'value' => 'start', 'type' => 'text' ) ) );
		$this->make_submission( 'f3', '2026-10-02 23:30:00', array( 'a' => array( 'value' => 'late-on-to-day', 'type' => 'text' ) ) );
		$this->make_submission( 'f3', '2026-10-03 00:00:01', array( 'a' => array( 'value' => 'after', 'type' => 'text' ) ) );
		$this->make_submission( 'other', '2026-10-01 12:00:00', array( 'a' => array( 'value' => 'other-form', 'type' => 'text' ) ) );

		$rows   = $this->export_rows( array( 'form_id' => 'f3', 'from' => '2026-10-01', 'to' => '2026-10-02' ) );
		$values = wp_list_pluck( array_slice( $rows, 1 ), 4 );
		$this->assertSame( array( 'start', 'late-on-to-day' ), $values );
	}

	public function test_values_round_trip_and_arrays_join() {
		$tricky = "He said \"hi\", then C:\\path\nnew line — ✓";
		$this->make_submission(
			'f4',
			'2026-10-01 10:00:00',
			array(
				'note'   => array( 'value' => $tricky, 'type' => 'textarea', 'label' => 'Note' ),
				'topics' => array( 'value' => array( 'News', 'Offers' ), 'type' => 'checkbox', 'label' => 'Topics' ),
			)
		);

		$rows = $this->export_rows( array( 'form_id' => 'f4' ) );
		$this->assertSame( $tricky, $rows[1][4] );
		$this->assertSame( 'News, Offers', $rows[1][5] );
	}

	/**
	 * @dataProvider formula_values
	 *
	 * @param string $value Dangerous leading character value.
	 */
	public function test_formula_injection_is_neutralised( $value ) {
		$this->assertSame( "'" . $value, Form_Submissions_Export::escape_cell( $value ) );
	}

	public function formula_values() {
		return array(
			array( '=HYPERLINK("http://x")' ),
			array( '+1+1' ),
			array( '-2+3' ),
			array( '@SUM(A1)' ),
			array( "\tcmd" ),
			array( "\rcmd" ),
			array( '+15551234567' ),
			array( '-' ),
			array( '-1e3' ),
			array( "-5\n=1+1" ),
			array( "-5\n" ),
		);
	}

	/**
	 * @dataProvider negative_numbers
	 *
	 * @param string $value Plain negative number.
	 */
	public function test_plain_negative_numbers_are_not_prefixed( $value ) {
		$this->assertSame( $value, Form_Submissions_Export::escape_cell( $value ) );
	}

	public function negative_numbers() {
		return array(
			array( '-5' ),
			array( '-12.50' ),
		);
	}

	public function test_submission_arriving_mid_export_is_left_out() {
		$this->make_submission( 'f10', '2026-10-01 10:00:00', array( 'a' => array( 'value' => 'old', 'type' => 'text' ) ) );
		$late    = 0;
		$arrives = function ( $columns ) use ( &$late ) {
			// Runs between the header pass and the row pass.
			$late = self::factory()->post->create( array( 'post_type' => 'dsgo_form_submission', 'post_status' => 'private' ) );
			update_post_meta( $late, '_dsg_form_id', 'f10' );
			update_post_meta( $late, '_dsg_form_fields', array( 'b' => array( 'value' => 'new', 'type' => 'text' ) ) );
			return $columns;
		};
		add_filter( 'designsetgo_form_export_columns', $arrives );

		$rows = $this->export_rows( array( 'form_id' => 'f10' ) );

		remove_filter( 'designsetgo_form_export_columns', $arrives );
		$this->assertNotSame( 0, $late );
		$this->assertCount( 2, $rows, 'Only the submission that existed when the export started.' );
		$this->assertSame( 'old', $rows[1][4] );
	}

	public function test_date_filters_use_local_days_in_a_non_utc_timezone() {
		update_option( 'timezone_string', 'America/Chicago' );
		$in    = $this->make_submission( 'f11', '2026-10-01 23:30:00', array( 'a' => array( 'value' => 'in', 'type' => 'text' ) ) );
		$this->make_submission( 'f11', '2026-10-02 00:30:00', array( 'a' => array( 'value' => 'out', 'type' => 'text' ) ) );
		$this->make_submission( 'f11', '2026-09-30 23:59:59', array( 'a' => array( 'value' => 'out', 'type' => 'text' ) ) );

		$rows = $this->export_rows( array( 'form_id' => 'f11', 'from' => '2026-10-01', 'to' => '2026-10-01' ) );
		$this->assertCount( 2, $rows );
		$this->assertSame( (string) $in, $rows[1][0] );
	}

	public function test_one_sided_and_inverted_date_ranges() {
		$this->make_submission( 'f12', '2026-09-01 10:00:00', array( 'a' => array( 'value' => 'sep', 'type' => 'text' ) ) );
		$this->make_submission( 'f12', '2026-10-01 10:00:00', array( 'a' => array( 'value' => 'oct', 'type' => 'text' ) ) );

		$this->assertSame( 'oct', $this->export_rows( array( 'form_id' => 'f12', 'from' => '2026-09-15' ) )[1][4] );
		$this->assertSame( 'sep', $this->export_rows( array( 'form_id' => 'f12', 'to' => '2026-09-15' ) )[1][4] );
		$this->assertCount( 1, $this->export_rows( array( 'form_id' => 'f12', 'from' => '2026-10-15', 'to' => '2026-09-01' ) ), 'Header only.' );
	}

	public function test_trashed_submissions_are_not_exported() {
		$this->make_submission( 'f13', '2026-10-01 10:00:00', array( 'a' => array( 'value' => 'kept', 'type' => 'text' ) ) );
		wp_trash_post( $this->make_submission( 'f13', '2026-10-01 11:00:00', array( 'a' => array( 'value' => 'trashed', 'type' => 'text' ) ) ) );

		$rows = $this->export_rows( array( 'form_id' => 'f13' ) );
		$this->assertCount( 2, $rows );
		$this->assertSame( 'kept', $rows[1][4] );
	}

	public function test_formula_injection_applies_to_headers_and_cells() {
		$this->make_submission( 'f5', '2026-10-01 10:00:00', array( 'x' => array( 'value' => '=1+1', 'type' => 'text', 'label' => '=Label' ) ) );
		$rows = $this->export_rows( array( 'form_id' => 'f5' ) );
		$this->assertSame( "'=Label", $rows[0][4] );
		$this->assertSame( "'=1+1", $rows[1][4] );
		$this->assertSame( 'safe', Form_Submissions_Export::escape_cell( 'safe' ) );
	}

	public function test_columns_filter_can_add_ip() {
		$add_ip = function ( $columns ) {
			$columns['ip'] = 'IP address';
			return $columns;
		};
		add_filter( 'designsetgo_form_export_columns', $add_ip );
		$this->make_submission( 'f6', '2026-10-01 10:00:00', array( 'a' => array( 'value' => '1', 'type' => 'text' ) ) );

		$rows = $this->export_rows( array( 'form_id' => 'f6' ) );
		remove_filter( 'designsetgo_form_export_columns', $add_ip );

		$this->assertSame( 'IP address', end( $rows[0] ) );
		$this->assertSame( '198.51.100.7', end( $rows[1] ) );
	}

	public function test_status_columns_for_old_and_new_submissions() {
		$this->make_submission( 'f7', '2026-10-01 10:00:00', array( 'a' => array( 'value' => '1', 'type' => 'text' ) ) );
		$this->make_submission( 'f7', '2026-10-02 10:00:00', array( 'a' => array( 'value' => '2', 'type' => 'text' ) ), array( '_dsg_email_sent' => 'yes', '_dsg_webhook_status' => 'delivered' ) );

		$rows = $this->export_rows( array( 'form_id' => 'f7' ) );
		$this->assertSame( array( '', '' ), array_slice( $rows[1], -2 ) );
		$this->assertSame( array( 'Sent', 'Delivered' ), array_slice( $rows[2], -2 ) );
	}

	public function test_request_args_reject_bad_dates() {
		$args = Form_Submissions_Export::request_args( array( 'dsgo_form' => 'f1', 'dsgo_from' => '2026-13-40', 'dsgo_to' => 'yesterday' ) );
		$this->assertSame( array( 'form_id' => 'f1', 'from' => '', 'to' => '' ), $args );
	}

	public function test_export_requires_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET['dsgo_export'] = '1';
		$_GET['post_type']   = 'dsgo_form_submission';

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		$this->export->maybe_export();
	}

	public function test_export_requires_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_GET['dsgo_export']                              = '1';
		$_GET['post_type']                                = 'dsgo_form_submission';
		$_REQUEST[ Form_Submissions_Export::NONCE_NAME ] = wp_create_nonce( Form_Submissions_Export::NONCE_ACTION );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'not allowed to export submissions' );
		$this->export->maybe_export();
	}

	public function test_maybe_export_ignores_other_screens() {
		$_GET['post_type'] = 'post';
		$this->expectOutputString( '' );
		$this->export->maybe_export();
	}
	/**
	 * Export spanning several batches lists every row once, in order.
	 *
	 * @dataProvider batch_sizes
	 *
	 * @param int $count Number of submissions.
	 */
	public function test_export_pages_through_batches( $count ) {
		for ( $i = 1; $i <= $count; $i++ ) {
			$this->make_submission(
				'batch' . $count,
				gmdate( 'Y-m-d H:i:s', 1790000000 + $i * 60 ),
				array( 'n' => array( 'value' => (string) $i, 'type' => 'text' ) )
			);
		}

		$rows = $this->export_rows( array( 'form_id' => 'batch' . $count ) );
		$this->assertCount( $count + 1, $rows );
		$this->assertSame( array_map( 'strval', range( 1, $count ) ), wp_list_pluck( array_slice( $rows, 1 ), 4 ) );
		$this->assertCount( $count, array_unique( wp_list_pluck( array_slice( $rows, 1 ), 0 ) ) );
	}

	/**
	 * Batch boundary sizes.
	 *
	 * @return array
	 */
	public function batch_sizes() {
		return array(
			'exactly one batch' => array( Form_Submissions_Export::BATCH ),
			'one over'          => array( Form_Submissions_Export::BATCH + 1 ),
		);
	}

	public function test_headers_colliding_with_builtin_or_each_other_are_disambiguated() {
		$this->make_submission(
			'f9',
			'2026-10-01 10:00:00',
			array(
				'when'  => array( 'value' => 'soon', 'type' => 'text', 'label' => 'Date' ),
				'Name'  => array( 'value' => 'A', 'type' => 'text' ),
				'first' => array( 'value' => 'B', 'type' => 'text', 'label' => 'Name' ),
			)
		);

		$rows = $this->export_rows( array( 'form_id' => 'f9' ) );
		$this->assertSame(
			array( 'Submission ID', 'Date', 'Form ID', 'Source URL', 'Date (when)', 'Name (Name)', 'Name (first)', 'Email status', 'Webhook status' ),
			$rows[0]
		);
	}
}

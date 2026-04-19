<?php
/**
 * PHPUnit tests for the Dynamic Query facet index schema.
 *
 * @package DesignSetGo
 * @group query-block
 */
class DesignSetGo_Query_Facet_Index_Test extends WP_UnitTestCase {

	public function test_install_creates_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'dsgo_query_facet_index';

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		\DesignSetGo\Blocks\Query\FacetIndex::install();

		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
		);
	}

	public function test_install_creates_indexes() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();

		global $wpdb;
		$table   = $wpdb->prefix . 'dsgo_query_facet_index';
		$indexes = $wpdb->get_col( "SHOW INDEX FROM {$table}", 2 ); // Column 2 = Key_name.

		$this->assertContains( 'PRIMARY', $indexes );
		$this->assertContains( 'facet_key_value', $indexes );
		$this->assertContains( 'object_lookup', $indexes );
	}

	public function test_install_persists_schema_version() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		$this->assertSame( '1', get_option( 'dsgo_query_facet_index_schema' ) );
	}
}

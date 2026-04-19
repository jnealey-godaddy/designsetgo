<?php
/**
 * PHPUnit tests for FilterRegistry.
 *
 * @package DesignSetGo
 * @group query-block
 */

use DesignSetGo\Blocks\Query\FilterRegistry;

/**
 * Filter registry tests.
 */
class DesignSetGo_Query_Filter_Registry_Test extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( FilterRegistry::OPTION );
		remove_all_filters( 'designsetgo_query_registered_filters' );
		parent::tear_down();
	}

	public function test_register_persists_to_option() {
		FilterRegistry::register( 'category', array(
			'type'   => 'taxonomy',
			'source' => 'category',
			'label'  => 'Category',
		) );

		$stored = get_option( FilterRegistry::OPTION, array() );
		$this->assertArrayHasKey( 'category', $stored );
		$this->assertSame( 'taxonomy', $stored['category']['type'] );
		$this->assertSame( 'category', $stored['category']['source'] );
		$this->assertSame( 'Category', $stored['category']['label'] );
	}

	public function test_register_defaults_label_to_key() {
		FilterRegistry::register( 'post_tag', array(
			'type'   => 'taxonomy',
			'source' => 'post_tag',
		) );

		$entry = FilterRegistry::get( 'post_tag' );
		$this->assertSame( 'post_tag', $entry['label'] );
	}

	public function test_register_sanitizes_key_and_values() {
		FilterRegistry::register( 'Bad Key!', array(
			'type'   => 'MeTa',
			'source' => "<script>alert('xss')</script>_price",
		) );

		// sanitize_key() strips spaces/special chars → 'badkey'; type is lowercased.
		$stored = get_option( FilterRegistry::OPTION, array() );
		$this->assertArrayHasKey( 'badkey', $stored );
		$this->assertSame( 'meta', $stored['badkey']['type'] );
		$this->assertStringNotContainsString( '<script>', $stored['badkey']['source'] );
		$this->assertSame( 'Bad Key!', $stored['badkey']['label'], 'Label fallback preserves raw key for human readability.' );
	}

	public function test_all_applies_filter() {
		add_filter( 'designsetgo_query_registered_filters', function ( $filters ) {
			$filters['price'] = array( 'type' => 'meta', 'source' => '_price', 'label' => 'Price' );
			return $filters;
		} );

		$all = FilterRegistry::all();
		$this->assertArrayHasKey( 'price', $all );
		$this->assertSame( '_price', $all['price']['source'] );
	}

	public function test_unregister_removes_key() {
		FilterRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		$this->assertArrayHasKey( 'category', get_option( FilterRegistry::OPTION ) );

		FilterRegistry::unregister( 'category' );

		$stored = get_option( FilterRegistry::OPTION, array() );
		$this->assertArrayNotHasKey( 'category', $stored );
	}

	public function test_get_returns_null_for_missing_key() {
		$this->assertNull( FilterRegistry::get( 'nonexistent' ) );
	}

	public function test_get_returns_entry_for_registered_key() {
		FilterRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		$entry = FilterRegistry::get( 'category' );
		$this->assertIsArray( $entry );
		$this->assertSame( 'taxonomy', $entry['type'] );
	}

	public function test_unregister_missing_key_does_not_write() {
		// Baseline: option does not exist yet.
		$this->assertFalse( get_option( FilterRegistry::OPTION, false ) );

		FilterRegistry::unregister( 'never_registered' );

		// Still should not exist — unregister must not create an empty option.
		$this->assertFalse( get_option( FilterRegistry::OPTION, false ) );
	}
}

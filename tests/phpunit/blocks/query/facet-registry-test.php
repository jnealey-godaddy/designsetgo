<?php
/**
 * PHPUnit tests for FacetRegistry.
 *
 * @package DesignSetGo
 * @group query-block
 */

use DesignSetGo\Blocks\Query\FacetRegistry;

/**
 * Facet registry tests.
 */
class DesignSetGo_Query_Facet_Registry_Test extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( FacetRegistry::OPTION );
		remove_all_filters( 'designsetgo_query_registered_facets' );
		parent::tear_down();
	}

	public function test_register_persists_to_option() {
		FacetRegistry::register( 'category', array(
			'type'   => 'taxonomy',
			'source' => 'category',
			'label'  => 'Category',
		) );

		$stored = get_option( FacetRegistry::OPTION, array() );
		$this->assertArrayHasKey( 'category', $stored );
		$this->assertSame( 'taxonomy', $stored['category']['type'] );
		$this->assertSame( 'category', $stored['category']['source'] );
		$this->assertSame( 'Category', $stored['category']['label'] );
	}

	public function test_register_defaults_label_to_key() {
		FacetRegistry::register( 'post_tag', array(
			'type'   => 'taxonomy',
			'source' => 'post_tag',
		) );

		$entry = FacetRegistry::get( 'post_tag' );
		$this->assertSame( 'post_tag', $entry['label'] );
	}

	public function test_register_sanitizes_key_and_values() {
		FacetRegistry::register( 'Bad Key!', array(
			'type'   => 'MeTa',
			'source' => "<script>alert('xss')</script>_price",
		) );

		// sanitize_key() strips spaces/special chars → 'badkey'; type is lowercased.
		$stored = get_option( FacetRegistry::OPTION, array() );
		$this->assertArrayHasKey( 'badkey', $stored );
		$this->assertSame( 'meta', $stored['badkey']['type'] );
		$this->assertStringNotContainsString( '<script>', $stored['badkey']['source'] );
	}

	public function test_all_applies_filter() {
		add_filter( 'designsetgo_query_registered_facets', function ( $facets ) {
			$facets['price'] = array( 'type' => 'meta', 'source' => '_price', 'label' => 'Price' );
			return $facets;
		} );

		$all = FacetRegistry::all();
		$this->assertArrayHasKey( 'price', $all );
		$this->assertSame( '_price', $all['price']['source'] );
	}

	public function test_unregister_removes_key() {
		FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		$this->assertArrayHasKey( 'category', get_option( FacetRegistry::OPTION ) );

		FacetRegistry::unregister( 'category' );

		$stored = get_option( FacetRegistry::OPTION, array() );
		$this->assertArrayNotHasKey( 'category', $stored );
	}

	public function test_get_returns_null_for_missing_key() {
		$this->assertNull( FacetRegistry::get( 'nonexistent' ) );
	}

	public function test_get_returns_entry_for_registered_key() {
		FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		$entry = FacetRegistry::get( 'category' );
		$this->assertIsArray( $entry );
		$this->assertSame( 'taxonomy', $entry['type'] );
	}
}

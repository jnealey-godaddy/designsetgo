<?php
/**
 * A query-filter block must register its taxonomy from saved post content.
 *
 * The bug this pins: FilterIndexHooks read $attrs['taxonomy'] straight off
 * parse_blocks(), but WordPress never serializes an attribute whose value
 * equals its block.json default — and 'category' IS the default. A filter
 * left alone therefore never registered, so the index stayed empty
 * ("Indexed N objects (0 rows)") and counts silently never rendered.
 *
 * @package DesignSetGo
 * @group query-block
 */

use DesignSetGo\Blocks\Query\FilterRegistry;

/**
 * Auto-registration of filters from post content.
 */
class DesignSetGo_Query_Filter_Auto_Registration_Test extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( FilterRegistry::OPTION );
		remove_all_filters( 'designsetgo_query_registered_filters' );
		parent::tear_down();
	}

	/**
	 * Publish a page carrying the given block markup.
	 *
	 * @param string $content Post content.
	 * @return int Post ID.
	 */
	private function publish_with_content( $content ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
	}

	public function test_registers_taxonomy_left_at_its_default() {
		// Exactly what the editor writes for a category filter: no `taxonomy`
		// and no `filterKind`, because both equal their defaults.
		$this->publish_with_content(
			'<!-- wp:designsetgo/query {"queryId":"qtest1"} -->' .
			'<div class="wp-block-designsetgo-query">' .
			'<!-- wp:designsetgo/query-filter {"label":"Filter by category"} /-->' .
			'</div>' .
			'<!-- /wp:designsetgo/query -->'
		);

		$this->assertArrayHasKey( 'category', FilterRegistry::all() );
	}

	public function test_registers_an_explicit_non_default_taxonomy() {
		register_taxonomy( 'genre', 'post', array( 'public' => true ) );

		$this->publish_with_content(
			'<!-- wp:designsetgo/query-filter {"taxonomy":"genre","filterKind":"select"} /-->'
		);

		$this->assertArrayHasKey( 'genre', FilterRegistry::all() );

		unregister_taxonomy( 'genre' );
	}

	/**
	 * Reading defaults makes `taxonomy` present on every filter block, so the
	 * kinds that never consult it must not register one.
	 *
	 * @dataProvider non_taxonomy_kinds
	 * @param string $kind The filterKind value.
	 */
	public function test_non_taxonomy_kinds_register_nothing( $kind ) {
		$this->publish_with_content(
			'<!-- wp:designsetgo/query-filter {"filterKind":"' . $kind . '"} /-->'
		);

		$this->assertSame( array(), FilterRegistry::all() );
	}

	public function non_taxonomy_kinds() {
		return array(
			'search' => array( 'search' ),
			'sort'   => array( 'sort' ),
			'active' => array( 'active' ),
			'reset'  => array( 'reset' ),
		);
	}

	public function test_finds_filters_nested_deep_in_the_tree() {
		$this->publish_with_content(
			'<!-- wp:designsetgo/section -->' .
			'<div class="wp-block-designsetgo-section">' .
			'<!-- wp:designsetgo/query {"queryId":"qtest2"} -->' .
			'<div class="wp-block-designsetgo-query">' .
			'<!-- wp:designsetgo/query-filter {"filterKind":"checkbox"} /-->' .
			'</div>' .
			'<!-- /wp:designsetgo/query -->' .
			'</div>' .
			'<!-- /wp:designsetgo/section -->'
		);

		$this->assertArrayHasKey( 'category', FilterRegistry::all() );
	}

	public function test_draft_content_registers_nothing() {
		self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_content' => '<!-- wp:designsetgo/query-filter {"filterKind":"checkbox"} /-->',
			)
		);

		$this->assertSame( array(), FilterRegistry::all() );
	}
}

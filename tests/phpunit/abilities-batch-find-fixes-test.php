<?php
/**
 * Batch Update and Find Blocks fixes.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Configurators\Batch_Update;
use DesignSetGo\Abilities\Info\Find_Blocks;

/**
 * Regression coverage for batch-update matching and find-blocks paging.
 */
class Abilities_Batch_Find_Fixes_Test extends WP_UnitTestCase {

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Create a page with raw block content.
	 *
	 * @param string $content Content.
	 * @return int
	 */
	private function page( string $content ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
	}

	/**
	 * A nested match under a parent that fails the filter is still updated.
	 */
	public function test_nested_match_under_non_matching_parent_is_updated() {
		$id = $this->page(
			'<!-- wp:group {"className":"outer"} --><div class="wp-block-group outer">'
			. '<!-- wp:group {"className":"inner"} --><div class="wp-block-group inner"></div><!-- /wp:group -->'
			. '</div><!-- /wp:group -->'
		);

		$result = ( new Batch_Update() )->run(
			array(
				'post_id'    => $id,
				'operations' => array(
					array(
						'block_name' => 'core/group',
						'attributes' => array( 'anchor' => 'found' ),
						'filter'     => array( 'className' => 'inner' ),
					),
				),
			)
		);

		$this->assertTrue( $result['success'] ?? false, wp_json_encode( $result ) );
		$this->assertSame( 1, $result['total_updated'] );
	}

	/**
	 * A filter on a default-valued attribute matches blocks that store nothing, and numeric strings compare loosely.
	 */
	public function test_filter_matches_default_and_numeric_string_values() {
		$id = $this->page( '<!-- wp:heading --><h2 class="wp-block-heading">A</h2><!-- /wp:heading -->' );

		$result = ( new Batch_Update() )->run(
			array(
				'post_id'    => $id,
				'operations' => array(
					array(
						'block_name' => 'core/heading',
						'attributes' => array( 'anchor' => 'x' ),
						'filter'     => array( 'level' => '2' ),
					),
				),
			)
		);

		$this->assertSame( 1, $result['total_updated'], wp_json_encode( $result ) );
	}

	/**
	 * With zero matches the post is not rewritten.
	 */
	public function test_zero_matches_does_not_touch_the_post() {
		$id = $this->page( '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' );
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => '2020-01-01 00:00:00' ), array( 'ID' => $id ) );
		clean_post_cache( $id );

		$result = ( new Batch_Update() )->run(
			array(
				'post_id'    => $id,
				'operations' => array(
					array(
						'block_name' => 'core/heading',
						'attributes' => array( 'anchor' => 'x' ),
					),
				),
			)
		);

		$this->assertSame( 0, $result['total_updated'] );
		$this->assertSame( '2020-01-01 00:00:00', get_post( $id )->post_modified_gmt );
	}

	/**
	 * Find Blocks reports paging so a truncated scan is visible.
	 */
	public function test_find_blocks_pages_and_flags_has_more() {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->page( '<!-- wp:heading --><h2 class="wp-block-heading">H</h2><!-- /wp:heading -->' );
		}

		$first = ( new Find_Blocks() )->run(
			array(
				'block_name' => 'core/heading',
				'limit'      => 2,
			)
		);
		$this->assertTrue( $first['has_more'] );
		$this->assertSame( 2, $first['posts_scanned'] );
		$this->assertGreaterThanOrEqual( 3, $first['total_posts'] );

		$second = ( new Find_Blocks() )->run(
			array(
				'block_name' => 'core/heading',
				'limit'      => 2,
				'page'       => 2,
			)
		);
		$this->assertSame( 2, $second['page'] );
	}
}

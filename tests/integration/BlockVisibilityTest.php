<?php
// tests/integration/BlockVisibilityTest.php
namespace DesignSetGo\Tests\Integration;

use DesignSetGo\BlockVisibility;
use WP_UnitTestCase;

class BlockVisibilityTest extends WP_UnitTestCase {

	public function test_null_rules_always_visible() {
		$this->assertTrue( BlockVisibility::matches( null, array( 'postId' => 1 ) ) );
		$this->assertTrue( BlockVisibility::matches( array(), array() ) );
	}

	public function test_meta_equals_rule() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'featured', '1' );

		$rules = array(
			'operator' => 'AND',
			'rules'    => array(
				array( 'type' => 'meta', 'key' => 'featured', 'op' => 'equals', 'value' => '1' ),
			),
		);
		$this->assertTrue( BlockVisibility::matches( $rules, array( 'postId' => $post_id ) ) );

		update_post_meta( $post_id, 'featured', '0' );
		$this->assertFalse( BlockVisibility::matches( $rules, array( 'postId' => $post_id ) ) );
	}

	public function test_taxonomy_has_rule() {
		$post_id = self::factory()->post->create();
		$term_id = self::factory()->term->create( array( 'taxonomy' => 'category', 'slug' => 'news' ) );
		wp_set_post_terms( $post_id, array( $term_id ), 'category' );

		$rules = array(
			'operator' => 'AND',
			'rules'    => array(
				array( 'type' => 'taxonomy', 'taxonomy' => 'category', 'op' => 'has', 'value' => 'news' ),
			),
		);
		$this->assertTrue( BlockVisibility::matches( $rules, array( 'postId' => $post_id ) ) );
	}

	public function test_index_rule() {
		$rules = array(
			'operator' => 'AND',
			'rules'    => array(
				array( 'type' => 'index', 'op' => 'equals', 'value' => 0 ),
			),
		);
		$this->assertTrue( BlockVisibility::matches( $rules, array( 'postId' => 1, 'index' => 0 ) ) );
		$this->assertFalse( BlockVisibility::matches( $rules, array( 'postId' => 1, 'index' => 3 ) ) );
	}

	public function test_or_relation() {
		$rules = array(
			'operator' => 'OR',
			'rules'    => array(
				array( 'type' => 'index', 'op' => 'equals', 'value' => 0 ),
				array( 'type' => 'index', 'op' => 'equals', 'value' => 2 ),
			),
		);
		$this->assertTrue( BlockVisibility::matches( $rules, array( 'index' => 0 ) ) );
		$this->assertTrue( BlockVisibility::matches( $rules, array( 'index' => 2 ) ) );
		$this->assertFalse( BlockVisibility::matches( $rules, array( 'index' => 1 ) ) );
	}
}

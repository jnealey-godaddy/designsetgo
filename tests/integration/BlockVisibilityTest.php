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

	public function test_protected_meta_never_matches() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_secret_token', 'abc123' );

		foreach ( array( 'equals' => 'abc123', 'contains' => 'a', 'not_empty' => '', 'not_equals' => 'zzz' ) as $op => $value ) {
			$rules = array(
				'rules' => array(
					array( 'type' => 'meta', 'key' => '_secret_token', 'op' => $op, 'value' => $value ),
				),
			);
			$this->assertFalse(
				BlockVisibility::matches( $rules, array( 'postId' => $post_id ) ),
				"A protected meta rule with op {$op} must not reveal the stored value."
			);
		}
	}

	public function test_auth_rule_applies_outside_a_query_loop() {
		$markup = '<!-- wp:paragraph {"dsgoVisibility":{"operator":"AND","rules":[{"type":"auth","value":true}]}} --><p>Members only</p><!-- /wp:paragraph -->';

		wp_set_current_user( 0 );
		$this->assertSame( '', trim( do_blocks( $markup ) ), 'A logged-in-only block must not render for a visitor.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertStringContainsString( 'Members only', do_blocks( $markup ) );
	}

	public function test_nested_block_rules_apply_outside_a_query_loop() {
		$markup = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph {"dsgoVisibility":{"rules":[{"type":"auth","value":false}]}} --><p>Guests only</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertStringNotContainsString( 'Guests only', do_blocks( $markup ) );
	}

	public function test_meta_rule_outside_a_loop_reads_the_current_post() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'featured', '1' );
		$markup = '<!-- wp:paragraph {"dsgoVisibility":{"rules":[{"type":"meta","key":"featured","op":"equals","value":"1"}]}} --><p>Featured</p><!-- /wp:paragraph -->';

		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );
		$this->assertStringContainsString( 'Featured', do_blocks( $markup ) );

		update_post_meta( $post_id, 'featured', '0' );
		$this->assertStringNotContainsString( 'Featured', do_blocks( $markup ) );
		wp_reset_postdata();
	}
}

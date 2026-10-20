<?php
/**
 * Test Draft Mode Core Functionality
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Admin\Draft_Mode;

/**
 * Tests for Draft_Mode class.
 */
class Test_Draft_Mode extends WP_UnitTestCase {

	/**
	 * Draft Mode instance.
	 *
	 * @var Draft_Mode
	 */
	private $draft_mode;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Published page ID for testing.
	 *
	 * @var int
	 */
	private $page_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->draft_mode = new Draft_Mode();

		// Create a test user with page publish capabilities.
		$this->user_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		wp_set_current_user( $this->user_id );

		// Create a published test page.
		$this->page_id = $this->factory->post->create( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Test Page',
			'post_content' => 'Test content',
		) );
	}

	/**
	 * Test that Draft_Mode class exists.
	 */
	public function test_draft_mode_class_exists() {
		$this->assertTrue( class_exists( 'DesignSetGo\Admin\Draft_Mode' ) );
	}

	/**
	 * Test Draft_Mode constants are defined.
	 */
	public function test_constants_defined() {
		$this->assertEquals( '_dsgo_draft_of', Draft_Mode::META_DRAFT_OF );
		$this->assertEquals( '_dsgo_has_draft', Draft_Mode::META_HAS_DRAFT );
		$this->assertEquals( '_dsgo_draft_created', Draft_Mode::META_DRAFT_CREATED );
	}

	/**
	 * Test get_settings returns array with expected keys.
	 */
	public function test_get_settings() {
		$settings = $this->draft_mode->get_settings();

		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'enable', $settings );
		$this->assertArrayHasKey( 'show_page_list_actions', $settings );
		$this->assertArrayHasKey( 'show_page_list_column', $settings );
	}

	/**
	 * Test is_enabled returns boolean.
	 */
	public function test_is_enabled() {
		$enabled = $this->draft_mode->is_enabled();
		$this->assertIsBool( $enabled );
	}

	/**
	 * Test creating a draft from a published page.
	 */
	public function test_create_draft_success() {
		$draft_id = $this->draft_mode->create_draft( $this->page_id );

		$this->assertIsInt( $draft_id );
		$this->assertGreaterThan( 0, $draft_id );

		// Verify draft was created with correct properties.
		$draft = get_post( $draft_id );
		$this->assertEquals( 'draft', $draft->post_status );
		$this->assertEquals( 'page', $draft->post_type );

		// Verify metadata was set correctly.
		$this->assertEquals( $this->page_id, get_post_meta( $draft_id, Draft_Mode::META_DRAFT_OF, true ) );
		$this->assertEquals( $draft_id, get_post_meta( $this->page_id, Draft_Mode::META_HAS_DRAFT, true ) );
		$this->assertNotEmpty( get_post_meta( $draft_id, Draft_Mode::META_DRAFT_CREATED, true ) );
	}

	/**
	 * Test creating draft with content overrides.
	 */
	public function test_create_draft_with_overrides() {
		$overrides = array(
			'title'   => 'Draft Title',
			'content' => 'Draft content',
			'excerpt' => 'Draft excerpt',
		);

		$draft_id = $this->draft_mode->create_draft( $this->page_id, $overrides );
		$draft = get_post( $draft_id );

		$this->assertEquals( 'Draft Title', $draft->post_title );
		$this->assertEquals( 'Draft content', $draft->post_content );
		$this->assertEquals( 'Draft excerpt', $draft->post_excerpt );
	}

	/**
	 * Test that object-injection payloads are not copied to the draft.
	 *
	 * A serialized PHP object nested inside an array must be rejected by the
	 * recursive guard, while plain scalar/array meta still copies.
	 */
	public function test_create_draft_skips_meta_containing_nested_objects() {
		// Object nested inside an array (the case a flat is_object() check misses).
		update_post_meta( $this->page_id, 'nested_object_meta', array( 'payload' => new \stdClass() ) );
		// Top-level object.
		update_post_meta( $this->page_id, 'object_meta', new \stdClass() );
		// Safe scalar/array meta that must survive.
		update_post_meta( $this->page_id, 'safe_meta', 'keep-me' );
		update_post_meta( $this->page_id, 'safe_array_meta', array( 'a', 'b' ) );

		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->assertIsInt( $draft_id );

		// Object-bearing meta is not copied.
		$this->assertSame( '', get_post_meta( $draft_id, 'nested_object_meta', true ) );
		$this->assertSame( '', get_post_meta( $draft_id, 'object_meta', true ) );

		// Safe meta is copied.
		$this->assertSame( 'keep-me', get_post_meta( $draft_id, 'safe_meta', true ) );
		$this->assertSame( array( 'a', 'b' ), get_post_meta( $draft_id, 'safe_array_meta', true ) );
	}

	/**
	 * Test that meta nested deeper than the recursion cap is rejected (fail closed).
	 *
	 * The object guard stops recursing at MAX_OBJECT_SCAN_DEPTH and treats
	 * anything deeper as suspect, so a pathologically deep payload is skipped
	 * rather than copied — and cannot overflow the stack. Normally-shallow
	 * arrays (covered above) still copy.
	 */
	public function test_create_draft_skips_meta_nested_beyond_depth_cap() {
		// Wrap a scalar in arrays well past the 20-level cap (no object present).
		$deep = 'leaf';
		for ( $i = 0; $i < 25; $i++ ) {
			$deep = array( $deep );
		}
		update_post_meta( $this->page_id, 'too_deep_meta', $deep );
		update_post_meta( $this->page_id, 'safe_meta', 'keep-me' );

		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->assertIsInt( $draft_id );

		// Over-deep meta is not copied.
		$this->assertSame( '', get_post_meta( $draft_id, 'too_deep_meta', true ) );
		// Safe meta still copies.
		$this->assertSame( 'keep-me', get_post_meta( $draft_id, 'safe_meta', true ) );
	}

	/**
	 * Test that publishing does not delete meta the scanner refused to copy.
	 *
	 * Because an over-deep value never reaches the draft, publishing would
	 * otherwise read its absence from the draft as "the author deleted it" and
	 * sync that deletion to the original — destroying data the author never
	 * touched. The depth cap says only "too deep to vet", not "hostile", so
	 * such a key must be left untouched rather than deleted.
	 */
	public function test_publish_draft_preserves_meta_too_deep_to_copy() {
		$deep = 'leaf';
		for ( $i = 0; $i < 25; $i++ ) {
			$deep = array( $deep );
		}
		update_post_meta( $this->page_id, 'too_deep_meta', $deep );
		update_post_meta( $this->page_id, 'safe_meta', 'keep-me' );

		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->assertIsInt( $draft_id );
		// Precondition: the deep key really was withheld from the draft.
		$this->assertSame( '', get_post_meta( $draft_id, 'too_deep_meta', true ) );

		$this->draft_mode->publish_draft( $draft_id );

		// The original keeps the deep meta it always had.
		$this->assertSame( $deep, get_post_meta( $this->page_id, 'too_deep_meta', true ) );
		// And ordinary meta still round-trips.
		$this->assertSame( 'keep-me', get_post_meta( $this->page_id, 'safe_meta', true ) );
	}

	/**
	 * Test that a skipped meta key is observable rather than silently dropped.
	 *
	 * A silent skip surfaces to the author only as "some meta didn't carry
	 * over", which is undiagnosable. Fire an action naming the key and reason.
	 */
	public function test_skipped_meta_fires_action_with_reason() {
		$skipped = array();
		add_action(
			'designsetgo_draft_meta_skipped',
			function ( $key, $reason ) use ( &$skipped ) {
				$skipped[ $key ] = $reason;
			},
			10,
			2
		);

		$deep = 'leaf';
		for ( $i = 0; $i < 25; $i++ ) {
			$deep = array( $deep );
		}
		update_post_meta( $this->page_id, 'too_deep_meta', $deep );
		update_post_meta( $this->page_id, 'object_meta', new \stdClass() );
		update_post_meta( $this->page_id, 'safe_meta', 'keep-me' );

		$this->draft_mode->create_draft( $this->page_id );

		$this->assertSame( \DesignSetGo\Admin\Draft_Mode::REJECT_DEPTH, $skipped['too_deep_meta'] ?? null );
		$this->assertSame( \DesignSetGo\Admin\Draft_Mode::REJECT_OBJECT, $skipped['object_meta'] ?? null );
		// Copyable meta must not be reported as skipped.
		$this->assertArrayNotHasKey( 'safe_meta', $skipped );
	}

	/**
	 * Test that publishing does not delete meta an integrator excluded from copying.
	 *
	 * designsetgo_draft_excluded_meta_keys keeps a key off the draft on purpose.
	 * That key's absence from the draft is therefore not the author deleting it,
	 * and publishing must not sync a deletion the author never made. The value is
	 * perfectly copyable, so the object/depth guard alone does not catch this.
	 */
	public function test_publish_draft_preserves_excluded_meta_keys() {
		add_filter(
			'designsetgo_draft_excluded_meta_keys',
			function ( $keys ) {
				$keys[] = 'thirdparty_bookkeeping';
				return $keys;
			}
		);

		update_post_meta( $this->page_id, 'thirdparty_bookkeeping', 'do-not-lose-me' );
		update_post_meta( $this->page_id, 'safe_meta', 'keep-me' );

		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->assertIsInt( $draft_id );
		// Precondition: the exclusion really did keep the key off the draft.
		$this->assertSame( '', get_post_meta( $draft_id, 'thirdparty_bookkeeping', true ) );

		$this->draft_mode->publish_draft( $draft_id );

		// The original keeps the key the integrator deliberately withheld.
		$this->assertSame( 'do-not-lose-me', get_post_meta( $this->page_id, 'thirdparty_bookkeeping', true ) );
		$this->assertSame( 'keep-me', get_post_meta( $this->page_id, 'safe_meta', true ) );
	}

	/**
	 * Test that the depth cap can be raised for legitimately deep data.
	 *
	 * Without an escape hatch, a site whose data genuinely nests past the default
	 * would have that meta withheld from every draft with no recourse.
	 */
	public function test_scan_depth_filter_allows_deeper_meta() {
		add_filter(
			'designsetgo_draft_max_object_scan_depth',
			function () {
				return 40;
			}
		);

		$deep = 'leaf';
		for ( $i = 0; $i < 25; $i++ ) {
			$deep = array( $deep );
		}
		// 25 levels: over the default cap of 20, under the filtered cap of 40.
		update_post_meta( $this->page_id, 'deep_but_allowed', $deep );

		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->assertIsInt( $draft_id );

		$this->assertSame( $deep, get_post_meta( $draft_id, 'deep_but_allowed', true ) );
	}

	/**
	 * Test that the depth filter cannot remove the recursion bound.
	 *
	 * The cap exists to stop a hostile payload overflowing the stack, so a filter
	 * may relax it but must not be able to hand that guarantee away. A value past
	 * the absolute ceiling stays rejected however high the filter reaches.
	 */
	public function test_scan_depth_filter_is_clamped_to_absolute_max() {
		add_filter(
			'designsetgo_draft_max_object_scan_depth',
			function () {
				return PHP_INT_MAX;
			}
		);

		$absolute = \DesignSetGo\Admin\Draft_Mode::ABSOLUTE_MAX_OBJECT_SCAN_DEPTH;

		$deep = 'leaf';
		for ( $i = 0; $i < $absolute + 10; $i++ ) {
			$deep = array( $deep );
		}
		update_post_meta( $this->page_id, 'absurdly_deep', $deep );
		update_post_meta( $this->page_id, 'safe_meta', 'keep-me' );

		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->assertIsInt( $draft_id );

		// Still rejected: the clamp holds even against PHP_INT_MAX.
		$this->assertSame( '', get_post_meta( $draft_id, 'absurdly_deep', true ) );
		$this->assertSame( 'keep-me', get_post_meta( $draft_id, 'safe_meta', true ) );
	}

	/**
	 * Test creating draft fails for invalid post ID.
	 */
	public function test_create_draft_invalid_post() {
		$result = $this->draft_mode->create_draft( 99999 );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_post', $result->get_error_code() );
	}

	/**
	 * Test creating draft fails for non-page post type.
	 */
	public function test_create_draft_invalid_post_type() {
		$post_id = $this->factory->post->create( array(
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );

		$result = $this->draft_mode->create_draft( $post_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_post_type', $result->get_error_code() );
	}

	/**
	 * Test creating draft fails for non-published page.
	 */
	public function test_create_draft_invalid_status() {
		$draft_page_id = $this->factory->post->create( array(
			'post_type'   => 'page',
			'post_status' => 'draft',
		) );

		$result = $this->draft_mode->create_draft( $draft_page_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_status', $result->get_error_code() );
	}

	/**
	 * Test creating draft fails when draft already exists.
	 */
	public function test_create_draft_already_exists() {
		// Create first draft.
		$first_draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->assertIsInt( $first_draft_id );

		// Try to create another draft.
		$result = $this->draft_mode->create_draft( $this->page_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'draft_exists', $result->get_error_code() );
		$this->assertEquals( $first_draft_id, $result->get_error_data()['draft_id'] );
	}

	/**
	 * Test publishing a draft merges content to original.
	 */
	public function test_publish_draft_success() {
		// Create draft with different content.
		$overrides = array(
			'title'   => 'Updated Title',
			'content' => 'Updated content',
			'excerpt' => 'Updated excerpt',
		);

		$draft_id = $this->draft_mode->create_draft( $this->page_id, $overrides );

		// Publish the draft.
		$result = $this->draft_mode->publish_draft( $draft_id );

		$this->assertEquals( $this->page_id, $result );

		// Verify original page was updated.
		$updated_page = get_post( $this->page_id );
		$this->assertEquals( 'Updated Title', $updated_page->post_title );
		$this->assertEquals( 'Updated content', $updated_page->post_content );
		$this->assertEquals( 'Updated excerpt', $updated_page->post_excerpt );

		// Verify draft was deleted.
		$this->assertNull( get_post( $draft_id ) );

		// Verify metadata was cleaned up.
		$this->assertEmpty( get_post_meta( $this->page_id, Draft_Mode::META_HAS_DRAFT, true ) );
	}

	/**
	 * Test publishing draft fails for invalid draft ID.
	 */
	public function test_publish_draft_invalid_id() {
		$result = $this->draft_mode->publish_draft( 99999 );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_draft', $result->get_error_code() );
	}

	/**
	 * Test publishing draft fails for non-draft post.
	 */
	public function test_publish_draft_not_a_draft() {
		$result = $this->draft_mode->publish_draft( $this->page_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'not_a_draft', $result->get_error_code() );
	}

	/**
	 * Test discarding a draft.
	 */
	public function test_discard_draft_success() {
		$draft_id = $this->draft_mode->create_draft( $this->page_id );

		$result = $this->draft_mode->discard_draft( $draft_id );

		$this->assertEquals( $this->page_id, $result );

		// Verify draft was deleted.
		$this->assertNull( get_post( $draft_id ) );

		// Verify metadata was cleaned up.
		$this->assertEmpty( get_post_meta( $this->page_id, Draft_Mode::META_HAS_DRAFT, true ) );
	}

	/**
	 * Test has_draft returns correct boolean.
	 */
	public function test_has_draft() {
		$this->assertFalse( $this->draft_mode->has_draft( $this->page_id ) );

		$draft_id = $this->draft_mode->create_draft( $this->page_id );

		$this->assertTrue( $this->draft_mode->has_draft( $this->page_id ) );
	}

	/**
	 * Test get_draft returns draft post.
	 */
	public function test_get_draft() {
		$this->assertNull( $this->draft_mode->get_draft( $this->page_id ) );

		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$draft = $this->draft_mode->get_draft( $this->page_id );

		$this->assertInstanceOf( 'WP_Post', $draft );
		$this->assertEquals( $draft_id, $draft->ID );
	}

	/**
	 * Test cleanup_draft_meta when original page is deleted.
	 */
	public function test_cleanup_meta_original_deleted() {
		$draft_id = $this->draft_mode->create_draft( $this->page_id );

		// Delete original page.
		wp_delete_post( $this->page_id, true );

		// Verify draft meta was cleaned up.
		$this->assertEmpty( get_post_meta( $draft_id, Draft_Mode::META_DRAFT_OF, true ) );
	}

	/**
	 * Test cleanup_draft_meta when draft is deleted.
	 */
	public function test_cleanup_meta_draft_deleted() {
		$draft_id = $this->draft_mode->create_draft( $this->page_id );

		// Delete draft.
		wp_delete_post( $draft_id, true );

		// Verify original page meta was cleaned up.
		$this->assertEmpty( get_post_meta( $this->page_id, Draft_Mode::META_HAS_DRAFT, true ) );
	}

	/**
	 * Test action hooks are fired.
	 */
	public function test_action_hooks() {
		$created_fired = false;
		$published_fired = false;
		$discarded_fired = false;

		add_action( 'designsetgo_draft_created', function() use ( &$created_fired ) {
			$created_fired = true;
		} );

		add_action( 'designsetgo_draft_published', function() use ( &$published_fired ) {
			$published_fired = true;
		} );

		add_action( 'designsetgo_draft_discarded', function() use ( &$discarded_fired ) {
			$discarded_fired = true;
		} );

		// Test create action.
		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->assertTrue( $created_fired );

		// Test publish action.
		$this->draft_mode->publish_draft( $draft_id );
		$this->assertTrue( $published_fired );

		// Test discard action.
		$draft_id_2 = $this->draft_mode->create_draft( $this->page_id );
		$this->draft_mode->discard_draft( $draft_id_2 );
		$this->assertTrue( $discarded_fired );
	}

	/**
	 * Quick Edit, bulk edit, REST and scheduling all end in wp_insert_post():
	 * a draft copy must stay a draft.
	 */
	public function test_draft_copy_cannot_be_published_directly() {
		$draft_id = $this->draft_mode->create_draft( $this->page_id );

		wp_update_post( array( 'ID' => $draft_id, 'post_status' => 'publish' ) );
		$this->assertSame( 'draft', get_post_status( $draft_id ) );

		wp_update_post(
			array(
				'ID'          => $draft_id,
				'post_status' => 'publish',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);
		$this->assertSame( 'draft', get_post_status( $draft_id ), 'Scheduling must not work either' );

		// A normal page is unaffected.
		$other = $this->factory->post->create( array( 'post_type' => 'page', 'post_status' => 'draft' ) );
		wp_update_post( array( 'ID' => $other, 'post_status' => 'publish' ) );
		$this->assertSame( 'publish', get_post_status( $other ) );
	}

	/**
	 * Publishing still works through the merge, and the guard does not get in the way.
	 */
	public function test_merge_publish_still_works_with_guard() {
		$draft_id = $this->draft_mode->create_draft( $this->page_id, array( 'title' => 'Merged' ) );
		$this->assertEquals( $this->page_id, $this->draft_mode->publish_draft( $draft_id ) );
		$this->assertSame( 'Merged', get_post( $this->page_id )->post_title );
		$this->assertSame( 'publish', get_post_status( $this->page_id ) );
	}

	public function test_publish_detects_live_page_conflict_and_force_overrides() {
		$draft_id = $this->draft_mode->create_draft( $this->page_id, array( 'title' => 'Draft title' ) );

		// The live page is edited after the draft exists.
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => '2031-01-01 00:00:00' ), array( 'ID' => $this->page_id ) );
		clean_post_cache( $this->page_id );

		$result = $this->draft_mode->publish_draft( $draft_id );
		$this->assertWPError( $result );
		$this->assertSame( 'draft_conflict', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertNotSame( 'Draft title', get_post( $this->page_id )->post_title );

		$this->assertEquals( $this->page_id, $this->draft_mode->publish_draft( $draft_id, true ) );
		$this->assertSame( 'Draft title', get_post( $this->page_id )->post_title );
	}

	public function test_publish_syncs_template_menu_order_slug_and_parent() {
		add_filter(
			'theme_page_templates',
			static function ( $templates ) {
				return $templates + array(
					'old.php' => 'Old',
					'new.php' => 'New',
				);
			}
		);
		update_post_meta( $this->page_id, '_wp_page_template', 'old.php' );
		$parent_id = $this->factory->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		$draft_id  = $this->draft_mode->create_draft( $this->page_id );

		update_post_meta( $draft_id, '_wp_page_template', 'new.php' );
		wp_update_post( array( 'ID' => $draft_id, 'menu_order' => 7, 'post_name' => 'brand-new-slug', 'post_parent' => $parent_id ) );

		$this->draft_mode->publish_draft( $draft_id );

		$page = get_post( $this->page_id );
		$this->assertSame( 'new.php', get_post_meta( $this->page_id, '_wp_page_template', true ) );
		$this->assertSame( 7, (int) $page->menu_order );
		$this->assertSame( 'brand-new-slug', $page->post_name );
		$this->assertSame( $parent_id, (int) $page->post_parent );
	}

	public function test_publish_keeps_slug_and_parent_when_draft_did_not_change_them() {
		$page = get_post( $this->page_id );
		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->draft_mode->publish_draft( $draft_id );

		$after = get_post( $this->page_id );
		$this->assertSame( $page->post_name, $after->post_name );
		$this->assertSame( (int) $page->post_parent, (int) $after->post_parent );
	}

	public function test_publish_syncs_terms() {
		register_taxonomy_for_object_type( 'category', 'page' );
		$keep = $this->factory->category->create();
		$add  = $this->factory->category->create();
		wp_set_object_terms( $this->page_id, array( $keep ), 'category' );

		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->assertSame( array( $keep ), array_map( 'intval', wp_get_object_terms( $draft_id, 'category', array( 'fields' => 'ids' ) ) ) );

		wp_set_object_terms( $draft_id, array( $add ), 'category' );
		$this->draft_mode->publish_draft( $draft_id );

		$this->assertSame( array( $add ), array_map( 'intval', wp_get_object_terms( $this->page_id, 'category', array( 'fields' => 'ids' ) ) ) );
	}

	public function test_trashing_original_trashes_draft_and_restoring_restores_it() {
		$draft_id = $this->draft_mode->create_draft( $this->page_id );

		wp_trash_post( $this->page_id );
		$this->assertSame( 'trash', get_post_status( $draft_id ) );

		wp_untrash_post( $this->page_id );
		$this->assertSame( 'draft', get_post_status( $draft_id ) );
		$this->assertSame( $draft_id, (int) get_post_meta( $this->page_id, Draft_Mode::META_HAS_DRAFT, true ) );
	}

	/**
	 * Backdate the page and the draft's baseline so a trash/restore in the
	 * same second still moves the page's modified time.
	 *
	 * @param int    $draft_id Draft post ID.
	 * @param string $base     Baseline stored on the draft.
	 */
	private function backdate_page_and_base( $draft_id, $base ) {
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => '2020-01-01 00:00:00' ), array( 'ID' => $this->page_id ) );
		clean_post_cache( $this->page_id );
		update_post_meta( $draft_id, Draft_Mode::META_BASE_MODIFIED, $base );
	}

	public function test_restoring_a_trashed_page_is_not_a_publish_conflict() {
		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		$this->backdate_page_and_base( $draft_id, '2020-01-01 00:00:00' );

		wp_trash_post( $this->page_id );
		wp_untrash_post( $this->page_id );

		$this->assertNotSame( '2020-01-01 00:00:00', get_post_field( 'post_modified_gmt', $this->page_id ) );
		$this->assertSame( $this->page_id, (int) $this->draft_mode->publish_draft( $draft_id ) );
	}

	public function test_an_edit_before_trashing_still_conflicts_after_restore() {
		$draft_id = $this->draft_mode->create_draft( $this->page_id );
		// The draft's baseline is older than the page: the page was edited.
		$this->backdate_page_and_base( $draft_id, '2019-06-01 00:00:00' );

		wp_trash_post( $this->page_id );
		wp_untrash_post( $this->page_id );

		$result = $this->draft_mode->publish_draft( $draft_id );
		$this->assertWPError( $result );
		$this->assertSame( 'draft_conflict', $result->get_error_code() );
	}
}

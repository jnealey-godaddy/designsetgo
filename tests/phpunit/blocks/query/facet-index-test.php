<?php
/**
 * PHPUnit tests for the Dynamic Query facet index schema.
 *
 * @package DesignSetGo
 * @group query-block
 */
class DesignSetGo_Query_Facet_Index_Test extends WP_UnitTestCase {

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'dsgo_query_facet_index' );
		delete_option( \DesignSetGo\Blocks\Query\FacetIndex::OPTION_SCHEMA );
		delete_option( \DesignSetGo\Blocks\Query\FacetRegistry::OPTION );
		parent::tear_down();
	}

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

	public function test_reindex_post_writes_taxonomy_rows() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array(
			'type'   => 'taxonomy',
			'source' => 'category',
		) );

		$cat_id  = $this->factory->category->create( array( 'name' => 'News' ) );
		$post_id = $this->factory->post->create( array(
			'post_title'    => 'Hello',
			'post_category' => array( $cat_id ),
		) );

		\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $post_id );

		global $wpdb;
		$table  = $wpdb->prefix . 'dsgo_query_facet_index';
		$values = $wpdb->get_col( $wpdb->prepare(
			"SELECT facet_value FROM {$table} WHERE object_id = %d AND object_type = 'post' AND facet_key = 'category'",
			$post_id
		) );

		$this->assertContains( (string) $cat_id, $values );
	}

	public function test_reindex_post_writes_meta_rows() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'price', array(
			'type'   => 'meta',
			'source' => '_price',
		) );

		$post_id = $this->factory->post->create();
		add_post_meta( $post_id, '_price', '19.99' );
		add_post_meta( $post_id, '_price', '29.99' );  // Multiple values, distinct rows.

		\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $post_id );

		global $wpdb;
		$table  = $wpdb->prefix . 'dsgo_query_facet_index';
		$values = $wpdb->get_col( $wpdb->prepare(
			"SELECT facet_value FROM {$table} WHERE object_id = %d AND facet_key = 'price' ORDER BY facet_value",
			$post_id
		) );

		$this->assertSame( array( '19.99', '29.99' ), $values );
	}

	public function test_reindex_is_idempotent() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array(
			'type'   => 'taxonomy',
			'source' => 'category',
		) );

		$cat_id  = $this->factory->category->create();
		$post_id = $this->factory->post->create( array( 'post_category' => array( $cat_id ) ) );

		\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $post_id );
		\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $post_id );
		\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $post_id );

		global $wpdb;
		$table = $wpdb->prefix . 'dsgo_query_facet_index';
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND facet_key = 'category'",
			$post_id
		) );

		$this->assertSame( 1, $count );
	}

	public function test_reindex_user_type_is_noop_in_a2() {
		global $wpdb;
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'role', array(
			'type'   => 'meta',
			'source' => 'wp_capabilities',
		) );

		$user_id = $this->factory->user->create();
		$table   = $wpdb->prefix . 'dsgo_query_facet_index';

		// Seed a post row that happens to share the same numeric id.
		$wpdb->insert( $table, array(
			'object_id'   => $user_id,
			'object_type' => 'post',
			'facet_key'   => 'sentinel',
			'facet_value' => 'survives',
		), array( '%d', '%s', '%s', '%s' ) );

		\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'user', $user_id );

		// No user rows written.
		$user_rows = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND object_type = 'user'",
			$user_id
		) );
		$this->assertSame( 0, $user_rows );

		// Post row with the same id must survive the 'user' reindex call.
		$surviving = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND object_type = 'post' AND facet_key = 'sentinel'",
			$user_id
		) );
		$this->assertSame( 1, $surviving, 'DELETE must be scoped by object_type; post row survives user-type reindex.' );
	}

	public function test_reindex_skips_meta_values_exceeding_column_width() {
		global $wpdb;
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'url', array(
			'type'   => 'meta',
			'source' => '_canonical',
		) );

		$post_id = $this->factory->post->create();
		$short   = 'https://example.com/short';
		$long    = 'https://example.com/' . str_repeat( 'x', 250 );
		add_post_meta( $post_id, '_canonical', $short );
		add_post_meta( $post_id, '_canonical', $long );

		\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $post_id );

		$table  = $wpdb->prefix . 'dsgo_query_facet_index';
		$values = $wpdb->get_col( $wpdb->prepare(
			"SELECT facet_value FROM {$table} WHERE object_id = %d AND facet_key = 'url'",
			$post_id
		) );

		$this->assertSame( array( $short ), $values, 'Meta value exceeding 190 chars must be skipped, not truncated.' );
	}

	public function test_save_post_triggers_reindex() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array(
			'type'   => 'taxonomy',
			'source' => 'category',
		) );
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		$cat_id  = $this->factory->category->create();
		$post_id = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_category' => array( $cat_id ),
		) );

		global $wpdb;
		$table = $wpdb->prefix . 'dsgo_query_facet_index';
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND facet_key = 'category'",
			$post_id
		) );

		$this->assertGreaterThan( 0, $count );
	}

	public function test_unpublished_post_is_removed_from_index() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array(
			'type'   => 'taxonomy',
			'source' => 'category',
		) );
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		$cat_id  = $this->factory->category->create();
		$post_id = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_category' => array( $cat_id ),
		) );

		// Verify seeded.
		global $wpdb;
		$table = $wpdb->prefix . 'dsgo_query_facet_index';
		$this->assertGreaterThan( 0, (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d",
			$post_id
		) ) );

		// Unpublish.
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );

		$count_after = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d",
			$post_id
		) );
		$this->assertSame( 0, $count_after );
	}

	public function test_deleted_post_removes_index_rows() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array(
			'type'   => 'taxonomy',
			'source' => 'category',
		) );
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		$cat_id  = $this->factory->category->create();
		$post_id = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_category' => array( $cat_id ),
		) );

		wp_delete_post( $post_id, true );

		global $wpdb;
		$table = $wpdb->prefix . 'dsgo_query_facet_index';
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d",
			$post_id
		) ) );
	}

	public function test_taxonomy_change_on_registered_facet_triggers_reindex() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array(
			'type'   => 'taxonomy',
			'source' => 'category',
		) );
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		$old_cat = $this->factory->category->create();
		$new_cat = $this->factory->category->create();
		$post_id = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_category' => array( $old_cat ),
		) );

		wp_set_post_categories( $post_id, array( $new_cat ) );

		global $wpdb;
		$table  = $wpdb->prefix . 'dsgo_query_facet_index';
		$values = $wpdb->get_col( $wpdb->prepare(
			"SELECT facet_value FROM {$table} WHERE object_id = %d AND facet_key = 'category'",
			$post_id
		) );

		$this->assertContains( (string) $new_cat, $values );
		$this->assertNotContains( (string) $old_cat, $values );
	}

	public function test_unregistered_taxonomy_change_does_not_reindex() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array(
			'type'   => 'taxonomy',
			'source' => 'category',
		) );
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		// Register an unrelated taxonomy we're NOT tracking as a facet.
		register_taxonomy( 'unrelated_tax', 'post' );
		$term_id = $this->factory->term->create( array( 'taxonomy' => 'unrelated_tax' ) );

		// Seed the index directly with a sentinel row so we can detect an errant DELETE.
		global $wpdb;
		$table = $wpdb->prefix . 'dsgo_query_facet_index';
		$wpdb->insert( $table, array(
			'object_id'   => $post_id,
			'object_type' => 'post',
			'facet_key'   => 'category',
			'facet_value' => 'sentinel',
		), array( '%d', '%s', '%s', '%s' ) );

		wp_set_object_terms( $post_id, array( $term_id ), 'unrelated_tax' );

		// The sentinel row must still be present — no reindex happened.
		$this->assertSame( '1', $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND facet_value = 'sentinel'",
			$post_id
		) ) );
	}

	public function test_meta_change_on_registered_facet_triggers_reindex() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'price', array(
			'type'   => 'meta',
			'source' => '_price',
		) );
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $post_id, '_price', '19.99' );

		global $wpdb;
		$table  = $wpdb->prefix . 'dsgo_query_facet_index';
		$values = $wpdb->get_col( $wpdb->prepare(
			"SELECT facet_value FROM {$table} WHERE object_id = %d AND facet_key = 'price'",
			$post_id
		) );

		$this->assertSame( array( '19.99' ), $values );
	}

	public function test_unregistered_meta_change_does_not_reindex() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'price', array(
			'type'   => 'meta',
			'source' => '_price',
		) );
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		// Seed a sentinel.
		global $wpdb;
		$table = $wpdb->prefix . 'dsgo_query_facet_index';
		$wpdb->insert( $table, array(
			'object_id'   => $post_id,
			'object_type' => 'post',
			'facet_key'   => 'price',
			'facet_value' => 'sentinel',
		), array( '%d', '%s', '%s', '%s' ) );

		// Update a DIFFERENT meta key — not a registered facet.
		update_post_meta( $post_id, '_irrelevant', 'something' );

		$this->assertSame( '1', $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND facet_value = 'sentinel'",
			$post_id
		) ) );
	}

	public function test_revisions_and_autosaves_skipped() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array(
			'type'   => 'taxonomy',
			'source' => 'category',
		) );
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		$cat_id  = $this->factory->category->create();
		$post_id = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_category' => array( $cat_id ),
		) );

		global $wpdb;
		$table = $wpdb->prefix . 'dsgo_query_facet_index';
		$baseline = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d",
			$post_id
		) );

		// Create a revision — this fires save_post with the revision's ID, not the post's.
		_wp_put_post_revision( get_post( $post_id ) );

		// Index state for the actual post is unchanged.
		$this->assertSame( $baseline, (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d",
			$post_id
		) ) );
	}

	public function test_set_object_terms_ignores_non_post_object_id() {
		global $wpdb;
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array(
			'type'   => 'taxonomy',
			'source' => 'category',
		) );
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		// Pick an ID that does NOT correspond to a post.
		$fake_id = 99999999;
		$this->assertNull( get_post( $fake_id ) );

		// Seed a sentinel row under that same ID so we can detect an errant reindex.
		$table = $wpdb->prefix . 'dsgo_query_facet_index';
		$wpdb->insert( $table, array(
			'object_id'   => $fake_id,
			'object_type' => 'post',
			'facet_key'   => 'category',
			'facet_value' => 'sentinel',
		), array( '%d', '%s', '%s', '%s' ) );

		// Trigger set_object_terms against the non-post ID.
		$cat_id = $this->factory->category->create();
		do_action( 'set_object_terms', $fake_id, array( $cat_id ), array( $cat_id ), 'category', false, array() );

		// Sentinel must still be present — no reindex happened.
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND facet_value = 'sentinel'",
			$fake_id
		) );
		$this->assertSame( 1, $count );
	}

	// -------------------------------------------------------------------------
	// Codex HIGH #2 — post-status gate in reindex_object
	// -------------------------------------------------------------------------

	public function test_reindex_object_removes_non_published_post() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$cat = $this->factory->category->create();
		$pid = $this->factory->post->create( array( 'post_status' => 'draft', 'post_category' => array( $cat ) ) );

		\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $pid );

		global $wpdb;
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . \DesignSetGo\Blocks\Query\FacetIndex::table_name() . ' WHERE object_id = %d',
			$pid
		) ), 'A draft post must not be indexed.' );
	}

	public function test_taxonomy_hook_on_draft_does_not_reindex() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		$cat = $this->factory->category->create();
		$pid = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_category' => array( $cat ),
		) );

		// Unpublish: this removes the post from the index via on_save_post.
		wp_update_post( array( 'ID' => $pid, 'post_status' => 'draft' ) );

		// Now a taxonomy change on the draft should NOT re-add it.
		$cat2 = $this->factory->category->create();
		wp_set_post_categories( $pid, array( $cat2 ) );

		global $wpdb;
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . \DesignSetGo\Blocks\Query\FacetIndex::table_name() . ' WHERE object_id = %d',
			$pid
		) ), 'Taxonomy hook on a draft must not re-add the post to the index.' );
	}

	// -------------------------------------------------------------------------
	// Codex MEDIUM #3 — server-side facet registration on save_post
	// -------------------------------------------------------------------------

	public function test_save_post_registers_facets_from_query_filter_blocks() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		$content = '<!-- wp:designsetgo/query --><!-- wp:designsetgo/query-filter {"taxonomy":"category","filterKind":"checkbox"} /--><!-- /wp:designsetgo/query -->';
		$this->factory->post->create( array(
			'post_status'  => 'publish',
			'post_content' => $content,
		) );

		$facets = get_option( \DesignSetGo\Blocks\Query\FacetRegistry::OPTION, array() );
		$this->assertArrayHasKey( 'category', $facets, 'category facet must be registered after publishing a post with a query-filter block.' );
		$this->assertSame( 'taxonomy', $facets['category']['type'] );
	}

	public function test_save_post_does_not_register_facets_for_draft() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetIndexHooks::register_hooks();

		// Clear the registry.
		delete_option( \DesignSetGo\Blocks\Query\FacetRegistry::OPTION );

		$content = '<!-- wp:designsetgo/query-filter {"taxonomy":"post_tag","filterKind":"checkbox"} /-->';
		$this->factory->post->create( array(
			'post_status'  => 'draft',
			'post_content' => $content,
		) );

		$facets = get_option( \DesignSetGo\Blocks\Query\FacetRegistry::OPTION, array() );
		$this->assertArrayNotHasKey( 'post_tag', $facets, 'Draft posts must not register facets.' );
	}

	// -------------------------------------------------------------------------
	// count_for_options + is_available (Task A5)
	// -------------------------------------------------------------------------

	public function test_count_for_options_returns_counts_per_value() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$news   = $this->factory->category->create( array( 'slug' => 'news' ) );
		$events = $this->factory->category->create( array( 'slug' => 'events' ) );

		$news_ids = $this->factory->post->create_many( 3, array(
			'post_status'   => 'publish',
			'post_category' => array( $news ),
		) );
		$event_ids = $this->factory->post->create_many( 2, array(
			'post_status'   => 'publish',
			'post_category' => array( $events ),
		) );
		foreach ( array_merge( $news_ids, $event_ids ) as $pid ) {
			\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $pid );
		}

		$counts = \DesignSetGo\Blocks\Query\FacetIndex::count_for_options(
			'category',
			array( $news, $events ),
			array()
		);

		$this->assertSame( 3, $counts[ (string) $news ] );
		$this->assertSame( 2, $counts[ (string) $events ] );
	}

	public function test_count_for_options_zero_fills_missing_values() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$news   = $this->factory->category->create();
		$orphan = $this->factory->category->create(); // no posts

		$pid = $this->factory->post->create( array( 'post_status' => 'publish', 'post_category' => array( $news ) ) );
		\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $pid );

		$counts = \DesignSetGo\Blocks\Query\FacetIndex::count_for_options( 'category', array( $news, $orphan ), array() );

		$this->assertSame( 1, $counts[ (string) $news ] );
		$this->assertSame( 0, $counts[ (string) $orphan ] );
	}

	public function test_count_for_options_respects_cross_facet_intersection() {
		// 3 posts in news; 2 of them tagged hot; count for news under active tag=hot should be 2.
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'tag',      array( 'type' => 'taxonomy', 'source' => 'post_tag' ) );

		$news = $this->factory->category->create();
		$hot  = $this->factory->term->create( array( 'taxonomy' => 'post_tag' ) );

		$hot_in_news  = $this->factory->post->create_many( 2, array( 'post_status' => 'publish', 'post_category' => array( $news ), 'tags_input' => array( get_term( $hot )->slug ) ) );
		$cold_in_news = $this->factory->post->create( array( 'post_status' => 'publish', 'post_category' => array( $news ) ) );

		foreach ( array_merge( $hot_in_news, array( $cold_in_news ) ) as $pid ) {
			\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $pid );
		}

		$counts = \DesignSetGo\Blocks\Query\FacetIndex::count_for_options(
			'category',
			array( $news ),
			array( 'tag' => array( (string) $hot ) )
		);

		$this->assertSame( 2, $counts[ (string) $news ] );
	}

	public function test_count_for_options_excludes_self_facet_from_intersection() {
		// Counting options for 'category' with 'category' ALSO in active_filters
		// must ignore the self-entry — OR semantics within a group.
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$news   = $this->factory->category->create();
		$events = $this->factory->category->create();

		$news_ids  = $this->factory->post->create_many( 3, array( 'post_status' => 'publish', 'post_category' => array( $news ) ) );
		$event_ids = $this->factory->post->create_many( 2, array( 'post_status' => 'publish', 'post_category' => array( $events ) ) );
		foreach ( array_merge( $news_ids, $event_ids ) as $pid ) {
			\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $pid );
		}

		// Even though user has 'news' selected, counting for 'category' must still
		// show events=2 (so user can see what switching to events would give them).
		$counts = \DesignSetGo\Blocks\Query\FacetIndex::count_for_options(
			'category',
			array( $news, $events ),
			array( 'category' => array( (string) $news ) )
		);

		$this->assertSame( 3, $counts[ (string) $news ] );
		$this->assertSame( 2, $counts[ (string) $events ] );
	}

	public function test_count_for_options_empty_option_values_returns_empty_array() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		$this->assertSame( array(), \DesignSetGo\Blocks\Query\FacetIndex::count_for_options( 'anything', array(), array() ) );
	}

	public function test_count_for_options_deduplicates_option_values() {
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$news = $this->factory->category->create();
		$pid  = $this->factory->post->create( array( 'post_status' => 'publish', 'post_category' => array( $news ) ) );
		\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $pid );

		// Pass the same value twice — result should have one entry.
		$counts = \DesignSetGo\Blocks\Query\FacetIndex::count_for_options( 'category', array( $news, $news, (string) $news ), array() );
		$this->assertCount( 1, $counts );
		$this->assertSame( 1, $counts[ (string) $news ] );
	}

	public function test_is_available_true_for_registered_facet() {
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );
		$this->assertTrue( \DesignSetGo\Blocks\Query\FacetIndex::is_available( 'category' ) );
	}

	public function test_is_available_false_for_unregistered_facet() {
		$this->assertFalse( \DesignSetGo\Blocks\Query\FacetIndex::is_available( 'nonexistent' ) );
	}

	public function test_count_for_options_self_exclusion_uses_sanitized_key() {
		// Regression: $active_filters lookup must use the sanitized key form, not raw $facet_key.
		\DesignSetGo\Blocks\Query\FacetIndex::install();
		\DesignSetGo\Blocks\Query\FacetRegistry::register( 'category', array( 'type' => 'taxonomy', 'source' => 'category' ) );

		$news   = $this->factory->category->create();
		$events = $this->factory->category->create();

		$news_ids  = $this->factory->post->create_many( 3, array( 'post_status' => 'publish', 'post_category' => array( $news ) ) );
		$event_ids = $this->factory->post->create_many( 2, array( 'post_status' => 'publish', 'post_category' => array( $events ) ) );
		foreach ( array_merge( $news_ids, $event_ids ) as $pid ) {
			\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $pid );
		}

		// Pass a key that sanitizes differently from its raw form. active_filters stores the
		// sanitized form (as all real callers do). The self-exclusion must match that form.
		$counts = \DesignSetGo\Blocks\Query\FacetIndex::count_for_options(
			'Category',                                         // sanitizes to 'category'
			array( $news, $events ),
			array( 'category' => array( (string) $news ) )       // stored with sanitized key
		);

		// Without the fix: events count would be 0 (self-filter silently applied).
		// With the fix: events=2 regardless of 'category' being in active_filters.
		$this->assertSame( 3, $counts[ (string) $news ] );
		$this->assertSame( 2, $counts[ (string) $events ] );
	}
}

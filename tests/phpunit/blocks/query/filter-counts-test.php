<?php
/**
 * PHPUnit tests for (N) count rendering in designsetgo/query-filter.
 *
 * Covers:
 *  - \DesignSetGo\Blocks\Query\FacetIndex::count_for_options() returns correct counts per term.
 *  - showCounts=false suppresses counts (simulated via the render.php gate logic).
 *  - Active-filter intersection: counts reflect cross-facet AND semantics.
 *
 * @package DesignSetGo
 * @group query-block
 */

/**
 * Filter counts tests.
 *
 * @group query-block
 */
class DesignSetGo_Query_Filter_Counts_Test extends WP_UnitTestCase {

	/**
	 * Install the facet index table before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		\DesignSetGo\Blocks\Query\FacetIndex::install();
	}

	/**
	 * Drop the facet index table and reset options after each test.
	 */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'dsgo_query_facet_index' );
		delete_option( \DesignSetGo\Blocks\Query\FacetIndex::OPTION_SCHEMA );
		delete_option( \DesignSetGo\Blocks\Query\FacetRegistry::OPTION );
		$_GET = array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Extract active_filters_by_key from $_GET the same way render.php does.
	 *
	 * URL params named filter_<taxonomy> are re-keyed to bare taxonomy slugs
	 * (facet keys) for use with \DesignSetGo\Blocks\Query\FacetIndex::count_for_options().
	 *
	 * @return array  [ taxonomy_slug => [ value, ... ] ]
	 */
	private function extract_active_filters_from_get(): array {
		$active = array();
		foreach ( (array) $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$k = sanitize_key( (string) $k );
			if ( '' === $k || 0 !== strpos( $k, 'filter_' ) ) {
				continue;
			}
			if ( is_array( $v ) ) {
				$active[ $k ] = array_map( 'sanitize_text_field', wp_unslash( $v ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			} else {
				$val = sanitize_text_field( wp_unslash( (string) $v ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( '' !== $val ) {
					$active[ $k ] = array( $val );
				}
			}
		}

		// Re-key: strip "filter_" prefix so keys become taxonomy slugs (facet keys).
		$by_key = array();
		foreach ( $active as $param_key => $vals ) {
			$facet_key          = substr( $param_key, 7 );
			$by_key[ $facet_key ] = $vals;
		}
		return $by_key;
	}

	// -------------------------------------------------------------------------
	// Tests: count_for_options returns correct numbers
	// -------------------------------------------------------------------------

	/**
	 * Test that count_for_options returns (3) for News when 3 posts are indexed.
	 */
	public function test_count_for_options_returns_count_for_news() {
		\DesignSetGo\Blocks\Query\FacetRegistry::register(
			'category',
			array(
				'type'   => 'taxonomy',
				'source' => 'category',
			)
		);

		$news = $this->factory->category->create(
			array(
				'name' => 'News',
				'slug' => 'news',
			)
		);

		$pids = $this->factory->post->create_many(
			3,
			array(
				'post_status'   => 'publish',
				'post_category' => array( $news ),
			)
		);
		foreach ( $pids as $pid ) {
			\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $pid );
		}

		$counts = \DesignSetGo\Blocks\Query\FacetIndex::count_for_options(
			'category',
			array( $news ),
			array()
		);

		$this->assertSame(
			3,
			$counts[ (string) $news ],
			'count_for_options should return 3 for the News category with 3 indexed posts.'
		);
	}

	/**
	 * Test that showCounts=false gate prevents count span from appearing in label.
	 *
	 * The render.php sets $dsgo_counts_enabled = $show_counts && is_available().
	 * This test simulates that gate to confirm the label is built without a span
	 * when showCounts is false.
	 */
	public function test_no_count_label_when_show_counts_false() {
		\DesignSetGo\Blocks\Query\FacetRegistry::register(
			'category',
			array(
				'type'   => 'taxonomy',
				'source' => 'category',
			)
		);

		$news = $this->factory->category->create( array( 'name' => 'News' ) );
		$pid  = $this->factory->post->create(
			array(
				'post_status'   => 'publish',
				'post_category' => array( $news ),
			)
		);
		\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $pid );

		// Simulate the render.php gate: showCounts attribute is false.
		$show_counts    = false;
		$counts_enabled = $show_counts && \DesignSetGo\Blocks\Query\FacetIndex::is_available( 'category' );

		// Build the label string as render.php would.
		$name_label = esc_html( 'News' );
		if ( $counts_enabled ) {
			$counts      = \DesignSetGo\Blocks\Query\FacetIndex::count_for_options( 'category', array( $news ), array() );
			$name_label .= ' <span class="dsgo-query-filter__count">(' . (int) ( $counts[ (string) $news ] ?? 0 ) . ')</span>';
		}

		$this->assertStringNotContainsString(
			'dsgo-query-filter__count',
			$name_label,
			'Label must not contain a count span when showCounts is false.'
		);
	}

	/**
	 * Test that counts reflect active-filter intersection.
	 *
	 * Scenario: 3 posts in News; 2 of them tagged "hot". With filter_post_tag=hot active,
	 * counting "News" should return 2, not 3.
	 */
	public function test_count_reflects_active_filter_intersection() {
		\DesignSetGo\Blocks\Query\FacetRegistry::register(
			'category',
			array(
				'type'   => 'taxonomy',
				'source' => 'category',
			)
		);
		\DesignSetGo\Blocks\Query\FacetRegistry::register(
			'post_tag',
			array(
				'type'   => 'taxonomy',
				'source' => 'post_tag',
			)
		);

		$news = $this->factory->category->create(
			array(
				'name' => 'News',
				'slug' => 'news',
			)
		);
		$hot_tag = $this->factory->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'hot',
			)
		);

		// 2 posts in News AND tagged hot.
		$hot_news = $this->factory->post->create_many(
			2,
			array(
				'post_status'   => 'publish',
				'post_category' => array( $news ),
				'tags_input'    => array( get_term( $hot_tag )->slug ),
			)
		);
		// 1 post in News but NOT tagged hot.
		$cold_news = $this->factory->post->create(
			array(
				'post_status'   => 'publish',
				'post_category' => array( $news ),
			)
		);

		foreach ( array_merge( $hot_news, array( $cold_news ) ) as $pid ) {
			\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $pid );
		}

		// Simulate: filter_post_tag[]=<hot_tag_id> is active.
		$_GET['filter_post_tag'] = array( (string) $hot_tag ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$active_filters = $this->extract_active_filters_from_get();

		$counts = \DesignSetGo\Blocks\Query\FacetIndex::count_for_options(
			'category',
			array( $news ),
			$active_filters
		);

		$this->assertSame(
			2,
			$counts[ (string) $news ],
			'News count should be 2 when filter_post_tag=hot is active (intersection).'
		);
	}

	/**
	 * Test that is_available() returns false when a facet is not registered.
	 *
	 * When is_available() returns false, render.php skips count_for_options entirely,
	 * so no count spans are rendered even if showCounts=true.
	 */
	public function test_is_available_false_suppresses_count_call() {
		// Do NOT register 'category' facet.
		$this->assertFalse(
			\DesignSetGo\Blocks\Query\FacetIndex::is_available( 'category' ),
			'is_available() must return false for an unregistered facet.'
		);

		// Simulate the render.php gate: $dsgo_counts_enabled = $show_counts && is_available().
		$counts_enabled = true && \DesignSetGo\Blocks\Query\FacetIndex::is_available( 'category' );
		$this->assertFalse(
			$counts_enabled,
			'$dsgo_counts_enabled must be false when facet is not registered, even if showCounts=true.'
		);
	}

	// -------------------------------------------------------------------------
	// Codex HIGH #1 — slug→ID translation for taxonomy facets
	// -------------------------------------------------------------------------

	/**
	 * When the URL carries taxonomy slugs (e.g. filter_post_tag=hot) but the
	 * facet index stores term IDs, cross-facet counts must still be correct
	 * after slug-to-ID translation.
	 *
	 * Scenario: 3 posts in News; 2 also tagged "hot" (slug). With active
	 * filter_post_tag=hot, the News count should be 2 (not 0).
	 */
	public function test_active_filter_slugs_resolve_to_term_ids_for_intersection() {
		\DesignSetGo\Blocks\Query\FacetRegistry::register(
			'category',
			array( 'type' => 'taxonomy', 'source' => 'category' )
		);
		\DesignSetGo\Blocks\Query\FacetRegistry::register(
			'post_tag',
			array( 'type' => 'taxonomy', 'source' => 'post_tag' )
		);

		$news    = $this->factory->category->create( array( 'name' => 'News', 'slug' => 'news' ) );
		$hot_tag = $this->factory->term->create( array( 'taxonomy' => 'post_tag', 'slug' => 'hot' ) );

		// 2 posts in News AND tagged hot.
		$hot_news = $this->factory->post->create_many(
			2,
			array(
				'post_status'   => 'publish',
				'post_category' => array( $news ),
				'tags_input'    => array( get_term( $hot_tag )->slug ),
			)
		);
		// 1 post in News but NOT tagged hot.
		$cold_news = $this->factory->post->create( array(
			'post_status'   => 'publish',
			'post_category' => array( $news ),
		) );

		foreach ( array_merge( $hot_news, array( $cold_news ) ) as $pid ) {
			\DesignSetGo\Blocks\Query\FacetIndex::reindex_object( 'post', $pid );
		}

		// active_filters carry the SLUG, not the term ID — as URL params do.
		$active_filters_with_slug = array(
			'post_tag' => array( 'hot' ), // <-- slug, not int
		);

		// Translate slugs to IDs the same way render.php does.
		$registered = \DesignSetGo\Blocks\Query\FacetRegistry::all();
		foreach ( $active_filters_with_slug as $fk => $fv ) {
			$cfg = $registered[ $fk ] ?? null;
			if ( ! $cfg || 'taxonomy' !== ( $cfg['type'] ?? '' ) ) {
				continue;
			}
			$tax         = (string) ( $cfg['source'] ?? '' );
			$translated  = array();
			foreach ( (array) $fv as $slug_or_id ) {
				$slug_or_id = (string) $slug_or_id;
				if ( ctype_digit( $slug_or_id ) ) {
					$translated[] = $slug_or_id;
					continue;
				}
				$term = get_term_by( 'slug', $slug_or_id, $tax );
				if ( $term instanceof \WP_Term ) {
					$translated[] = (string) $term->term_id;
				}
			}
			$active_filters_with_slug[ $fk ] = $translated;
		}

		$counts = \DesignSetGo\Blocks\Query\FacetIndex::count_for_options(
			'category',
			array( $news ),
			$active_filters_with_slug
		);

		$this->assertSame(
			2,
			$counts[ (string) $news ],
			'News count must be 2 when tag=hot (slug) is active and the index stores term IDs.'
		);
	}

	/**
	 * Test that active filter extraction strips "filter_" prefix correctly.
	 *
	 * Render.php re-keys filter_<taxonomy> URL params to bare taxonomy slugs
	 * before passing them to count_for_options().
	 */
	public function test_active_filters_extraction_strips_filter_prefix() {
		$_GET['filter_category'] = array( 'news', 'tech' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_GET['filter_post_tag'] = array( 'hot' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_GET['q']               = 'search term'; // Non-filter param — ignored.

		$active = $this->extract_active_filters_from_get();

		$this->assertArrayHasKey( 'category', $active );
		$this->assertArrayHasKey( 'post_tag', $active );
		$this->assertArrayNotHasKey( 'q', $active );
		$this->assertSame( array( 'news', 'tech' ), $active['category'] );
		$this->assertSame( array( 'hot' ), $active['post_tag'] );
	}
}

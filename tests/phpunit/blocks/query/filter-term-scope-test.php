<?php
/**
 * A filter must not offer terms its own query has excluded.
 *
 * The bug this pins: the filter listed every term in the taxonomy regardless
 * of the enclosing query's taxQuery, each with a count drawn from the global
 * index. A query scoped to four categories still advertised every other
 * category on the site, with a non-zero count — and selecting one returned
 * nothing, because the count never knew about the query.
 *
 * @package DesignSetGo
 * @group query-block
 */

/**
 * Term scoping for the query-filter block.
 */
class DesignSetGo_Query_Filter_Term_Scope_Test extends WP_UnitTestCase {

	/**
	 * Load the filter render file (served from build/, like production).
	 */
	private function load_render() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query-filter/render.php';
		$this->assertFileExists(
			$path,
			'Run `npm run build` before PHPUnit — the filter render is served from build/.'
		);
		require_once $path;
	}

	/**
	 * Run the collector and return both accumulators.
	 *
	 * @param array  $tax_query Query taxQuery attribute.
	 * @param string $taxonomy  Taxonomy the filter renders.
	 * @return array{include: array, exclude: array}
	 */
	private function scope( array $tax_query, $taxonomy = 'category' ) {
		$this->load_render();
		$include = array();
		$exclude = array();
		designsetgo_query_filter_collect_term_scope( $tax_query, $taxonomy, $include, $exclude );
		return array(
			'include' => $include,
			'exclude' => $exclude,
		);
	}

	public function test_an_in_clause_narrows_the_offer_list() {
		$scope = $this->scope(
			array(
				'relation' => 'AND',
				'clauses'  => array(
					array(
						'taxonomy' => 'category',
						'terms'    => array( 11, 12 ),
						'operator' => 'IN',
					),
				),
			)
		);

		$this->assertSame( array( 11, 12 ), $scope['include'] );
		$this->assertSame( array(), $scope['exclude'] );
	}

	public function test_a_not_in_clause_removes_from_the_offer_list() {
		$scope = $this->scope(
			array(
				'relation' => 'AND',
				'clauses'  => array(
					array(
						'taxonomy' => 'category',
						'terms'    => array( 9 ),
						'operator' => 'NOT IN',
					),
				),
			)
		);

		$this->assertSame( array(), $scope['include'] );
		$this->assertSame( array( 9 ), $scope['exclude'] );
	}

	public function test_clauses_for_other_taxonomies_are_ignored() {
		$scope = $this->scope(
			array(
				'relation' => 'AND',
				'clauses'  => array(
					array(
						'taxonomy' => 'post_tag',
						'terms'    => array( 40, 41 ),
						'operator' => 'IN',
					),
				),
			)
		);

		$this->assertSame( array(), $scope['include'] );
		$this->assertSame( array(), $scope['exclude'] );
	}

	/**
	 * v2.5 allows a clause list to hold nested groups, so the collector has to
	 * recurse the same way designsetgo_build_tax_query_entry() does.
	 */
	public function test_nested_groups_are_walked() {
		$scope = $this->scope(
			array(
				'relation' => 'AND',
				'clauses'  => array(
					array(
						'relation' => 'OR',
						'clauses'  => array(
							array(
								'taxonomy' => 'category',
								'terms'    => array( 3 ),
								'operator' => 'IN',
							),
							array(
								'taxonomy' => 'category',
								'terms'    => array( 4 ),
								'operator' => 'IN',
							),
						),
					),
				),
			)
		);

		$this->assertSame( array( 3, 4 ), $scope['include'] );
	}

	public function test_an_empty_tax_query_scopes_nothing() {
		$scope = $this->scope(
			array(
				'relation' => 'AND',
				'clauses'  => array(),
			)
		);

		$this->assertSame( array(), $scope['include'] );
		$this->assertSame( array(), $scope['exclude'] );
	}

	public function test_a_malformed_clause_is_skipped_rather_than_fatal() {
		$scope = $this->scope(
			array(
				'clauses' => array(
					array( 'taxonomy' => 'category' ),   // no terms
					array( 'terms' => array( 5 ) ),      // no taxonomy
					'not-an-array',
				),
			)
		);

		$this->assertSame( array(), $scope['include'] );
	}

	/**
	 * End to end: the rendered checkbox list offers only the scoped terms.
	 */
	public function test_rendered_filter_offers_only_the_scoped_terms() {
		$keep_a = self::factory()->category->create(
			array(
				'slug' => 'keep-a',
				'name' => 'Keep A',
			)
		);
		$keep_b = self::factory()->category->create(
			array(
				'slug' => 'keep-b',
				'name' => 'Keep B',
			)
		);
		$drop   = self::factory()->category->create(
			array(
				'slug' => 'drop-me',
				'name' => 'Drop Me',
			)
		);

		$block = new WP_Block(
			array(
				'blockName'    => 'designsetgo/query-filter',
				'attrs'        => array(
					'filterKind' => 'checkbox',
					'showCounts' => false,
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'designsetgo/queryId'       => 'qscope',
				'designsetgo/querySource'   => 'posts',
				'designsetgo/queryPostType' => 'post',
				'designsetgo/queryTaxQuery' => array(
					'relation' => 'AND',
					'clauses'  => array(
						array(
							'taxonomy' => 'category',
							'terms'    => array( $keep_a, $keep_b ),
							'operator' => 'IN',
						),
					),
				),
			)
		);

		$html = $block->render();

		$this->assertStringContainsString( 'keep-a', $html );
		$this->assertStringContainsString( 'keep-b', $html );
		$this->assertStringNotContainsString( 'drop-me', $html );
		// The site's default category is out of scope too.
		$this->assertStringNotContainsString( 'value="uncategorized"', $html );
	}

	/**
	 * Without a taxQuery the filter behaves exactly as before — every term.
	 */
	public function test_no_tax_query_still_offers_every_term() {
		self::factory()->category->create(
			array(
				'slug' => 'alpha',
				'name' => 'Alpha',
			)
		);
		self::factory()->category->create(
			array(
				'slug' => 'beta',
				'name' => 'Beta',
			)
		);

		$block = new WP_Block(
			array(
				'blockName'    => 'designsetgo/query-filter',
				'attrs'        => array(
					'filterKind' => 'checkbox',
					'showCounts' => false,
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'designsetgo/queryId'     => 'qnoscope',
				'designsetgo/querySource' => 'posts',
			)
		);

		$html = $block->render();

		$this->assertStringContainsString( 'alpha', $html );
		$this->assertStringContainsString( 'beta', $html );
	}

	/**
	 * End to end through the container, which is where this actually broke.
	 *
	 * The isolated WP_Block test above passes context in by hand, so it stayed
	 * green while the real path was broken: designsetgo_query_render_container()
	 * assembles the child context itself rather than letting WordPress derive
	 * it from providesContext, and adding the key to block.json alone did
	 * nothing. Browser QA caught it; this pins it.
	 */
	public function test_container_passes_the_tax_query_down_to_the_filter() {
		$keep = self::factory()->category->create(
			array(
				'slug' => 'container-keep',
				'name' => 'Container Keep',
			)
		);
		self::factory()->category->create(
			array(
				'slug' => 'container-drop',
				'name' => 'Container Drop',
			)
		);
		self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_category' => array( $keep ),
			)
		);

		$tax_query = wp_json_encode(
			array(
				'relation' => 'AND',
				'clauses'  => array(
					array(
						'taxonomy' => 'category',
						'terms'    => array( $keep ),
						'operator' => 'IN',
					),
				),
			)
		);

		$content = '<!-- wp:designsetgo/query {"queryId":"qcontainer","taxQuery":' . $tax_query . '} -->'
			. '<div class="wp-block-designsetgo-query">'
			. '<!-- wp:designsetgo/query-filter {"filterKind":"checkbox","showCounts":false} /-->'
			// Without an item host the container treats every child as the
			// per-item template, and the filter never renders as a sibling.
			. '<!-- wp:designsetgo/query-results -->'
			. '<div class="wp-block-designsetgo-query-results"><!-- wp:post-title /--></div>'
			. '<!-- /wp:designsetgo/query-results -->'
			. '</div>'
			. '<!-- /wp:designsetgo/query -->';

		$html = do_blocks( $content );

		$this->assertStringContainsString( 'container-keep', $html );
		$this->assertStringNotContainsString( 'container-drop', $html );
	}
}

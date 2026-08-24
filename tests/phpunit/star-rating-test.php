<?php
/**
 * Star Rating — value math and JSON-LD builders.
 *
 * The math tests exist because three consumers have to agree on these numbers
 * (editor preview, render.php, schema builder), and only one of them is
 * visible when you look at a page.
 *
 * The builder tests drive the builders from parsed block markup rather than
 * hand-built attribute arrays, for the reason schema-builders-test.php spells
 * out: `parse_blocks()` is what actually feeds them in production, and it
 * omits every attribute equal to its block.json default.
 *
 * @package DesignSetGo
 */

/**
 * Star Rating tests.
 *
 * @group schema
 * @group star-rating
 */
class Star_Rating_Test extends WP_UnitTestCase {

	/**
	 * Load the builders under test.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once DESIGNSETGO_PATH . 'includes/features/star-rating-functions.php';
		require_once DESIGNSETGO_PATH . 'includes/features/schema-builders-rating.php';
	}

	/**
	 * Parse a star-rating block from a serialized block comment.
	 *
	 * @param array $attrs Attributes to serialize. Anything left out is absent
	 *                     from the comment, exactly as WordPress writes it for
	 *                     a value equal to the block.json default.
	 * @return array Parsed block.
	 */
	private function parse_block( array $attrs ) {
		$markup = '<!-- wp:designsetgo/star-rating ' . wp_json_encode( $attrs ) . ' /-->';
		$blocks = parse_blocks( $markup );

		return $blocks[0];
	}

	/**
	 * The icon count is clamped to something a page can render.
	 */
	public function test_clamp_max_bounds_the_icon_count() {
		$this->assertSame( 5, designsetgo_star_rating_clamp_max( 5 ) );
		$this->assertSame( 10, designsetgo_star_rating_clamp_max( 10000 ) );
		$this->assertSame( 1, designsetgo_star_rating_clamp_max( 0 ) );
		$this->assertSame( 5, designsetgo_star_rating_clamp_max( 'nonsense' ) );
	}

	/**
	 * Bound sources hand back numeric strings; those must still read.
	 */
	public function test_clamp_reads_numeric_strings_and_rejects_junk() {
		$this->assertSame( 4.0, designsetgo_star_rating_clamp( '4.00', 5 ) );
		$this->assertSame( 5.0, designsetgo_star_rating_clamp( 9, 5 ) );
		$this->assertSame( 0.0, designsetgo_star_rating_clamp( -2, 5 ) );
		$this->assertSame( 0.0, designsetgo_star_rating_clamp( '', 5 ) );
	}

	/**
	 * Precision snaps the drawn icons only.
	 */
	public function test_fill_percent_follows_precision() {
		$this->assertSame( 90.0, designsetgo_star_rating_fill_percent( 4.5, 5, 'half' ) );
		$this->assertSame( 86.0, designsetgo_star_rating_fill_percent( 4.3, 5, 'exact' ) );
		$this->assertSame( 80.0, designsetgo_star_rating_fill_percent( 4.3, 5, 'full' ) );
		$this->assertSame( 90.0, designsetgo_star_rating_fill_percent( 4.3, 5, 'half' ) );
	}

	/**
	 * The count template is author input, so it may hold stray percent tokens.
	 */
	public function test_count_template_never_runs_through_sprintf() {
		$this->assertSame( '(128)', designsetgo_star_rating_format_count( '(%s)', 128 ) );
		$this->assertSame( '40 of 100%', designsetgo_star_rating_format_count( '%s of 100%', 40 ) );
		$this->assertSame( '12', designsetgo_star_rating_format_count( 'reviews', 12 ) );
	}

	/**
	 * An aggregate node carries the count, the scale and the item.
	 */
	public function test_aggregate_rating_node() {
		$block = $this->parse_block(
			array(
				'dsgoSchema'  => 'aggregate-rating',
				'rating'      => 4.4,
				'ratingCount' => 128,
			)
		);

		$node = designsetgo_schema_build_aggregate_rating( $block, 'Widget Pro' );

		$this->assertSame( 'AggregateRating', $node['@type'] );
		$this->assertSame( 4.4, $node['ratingValue'] );
		$this->assertSame( 5, $node['bestRating'] );
		$this->assertSame( 0, $node['worstRating'] );
		$this->assertSame( 128, $node['ratingCount'] );
		$this->assertSame( 'Widget Pro', $node['itemReviewed']['name'] );
		$this->assertSame( 'Thing', $node['itemReviewed']['@type'] );
	}

	/**
	 * The displayed value may be snapped for the icons; the node is not.
	 */
	public function test_aggregate_rating_reports_the_exact_value() {
		$block = $this->parse_block(
			array(
				'dsgoSchema'  => 'aggregate-rating',
				'rating'      => 4.3,
				'precision'   => 'half',
				'ratingCount' => 9,
			)
		);

		$node = designsetgo_schema_build_aggregate_rating( $block, 'Widget Pro' );

		$this->assertSame( 4.3, $node['ratingValue'] );
	}

	/**
	 * Without a count the markup is ineligible, so nothing is emitted.
	 */
	public function test_aggregate_rating_requires_a_count() {
		$block = $this->parse_block(
			array(
				'dsgoSchema' => 'aggregate-rating',
				'rating'     => 4.5,
			)
		);

		$this->assertSame( array(), designsetgo_schema_build_aggregate_rating( $block, 'Widget Pro' ) );
	}

	/**
	 * A review node needs a named author.
	 */
	public function test_review_node_requires_an_author() {
		$attrs = array(
			'dsgoSchema' => 'review',
			'rating'     => 5,
		);

		$this->assertSame( array(), designsetgo_schema_build_review( $this->parse_block( $attrs ), 'Widget Pro' ) );

		$attrs['schemaAuthor'] = 'Amanda Peters';
		$node                  = designsetgo_schema_build_review( $this->parse_block( $attrs ), 'Widget Pro' );

		$this->assertSame( 'Review', $node['@type'] );
		$this->assertSame( 'Amanda Peters', $node['author']['name'] );
		$this->assertSame( 'Person', $node['author']['@type'] );
		$this->assertSame( 'Rating', $node['reviewRating']['@type'] );
		$this->assertSame( 5, $node['reviewRating']['ratingValue'] );
		$this->assertSame( 'Widget Pro', $node['itemReviewed']['name'] );
	}

	/**
	 * An explicit item name wins over the page title.
	 */
	public function test_item_name_overrides_the_page_title() {
		$block = $this->parse_block(
			array(
				'dsgoSchema'     => 'review',
				'rating'         => 4,
				'schemaAuthor'   => 'Amanda Peters',
				'schemaItemName' => 'The Widget Itself',
			)
		);

		$node = designsetgo_schema_build_review( $block, 'A Blog Post About Widgets' );

		$this->assertSame( 'The Widget Itself', $node['itemReviewed']['name'] );
	}

	/**
	 * A bound rating is not knowable from stored content, so it emits nothing.
	 *
	 * The stored number is the placeholder the author last typed. Publishing it
	 * as a search-engine claim would assert a rating nobody meant — and on a
	 * WooCommerce product page it would duplicate the aggregateRating Woo
	 * already emits inside its own Product node.
	 */
	public function test_bound_rating_emits_nothing() {
		$block = $this->parse_block(
			array(
				'dsgoSchema'  => 'aggregate-rating',
				'rating'      => 4.5,
				'ratingCount' => 128,
				'metadata'    => array(
					'bindings' => array(
						'rating' => array(
							'source' => 'designsetgo/woo-average-rating',
						),
					),
				),
			)
		);

		$this->assertSame( array(), designsetgo_schema_build_aggregate_rating( $block, 'Widget Pro' ) );
	}

	/**
	 * With no item name and no page title there is nothing to attach a rating
	 * to, and a floating rating node describes nothing.
	 */
	public function test_no_item_means_no_node() {
		$block = $this->parse_block(
			array(
				'dsgoSchema'  => 'aggregate-rating',
				'rating'      => 4.5,
				'ratingCount' => 12,
			)
		);

		$this->assertSame( array(), designsetgo_schema_build_aggregate_rating( $block, '' ) );
	}

	/**
	 * The block is collected through the same map as every other schema block.
	 */
	public function test_star_rating_is_in_the_builder_map() {
		$this->assertContains( 'designsetgo/star-rating', \DesignSetGo\SchemaOutput::supported_blocks() );
	}
}

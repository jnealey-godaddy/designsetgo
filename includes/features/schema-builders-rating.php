<?php
/**
 * Schema builders — Star Rating.
 *
 * Pure functions, same contract as schema-builders.php: a parsed block array
 * in, a schema.org array out. Kept in their own file because that one is
 * already at the 300-line budget.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO
 *
 * A bound rating OR a bound rating count is skipped. `SchemaOutput` reads the
 * STORED post content, and `parse_blocks()` does not resolve Block Bindings —
 * so a value driven by post meta, ACF or `designsetgo/woo-average-rating` is
 * not knowable here. What IS in the block comment is the placeholder the author
 * last typed in the editor, and emitting that as a search-engine claim would
 * publish a number nobody meant. Both bindable attributes count: an
 * AggregateRating asserts a value AND a count, so a stale count beside a real
 * rating is just as false as a stale rating. The two cases this leaves are
 * both correct outcomes:
 *
 * - A testimonial or review card with a rating typed into the block — the
 *   common case — emits its node.
 * - A product page whose rating is bound to WooCommerce emits nothing here,
 *   which is right: Woo already outputs a Product node carrying its own
 *   aggregateRating, and a second one would be duplicate structured data.
 *
 * @package DesignSetGo
 * @since   2.8.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_schema_rating_values' ) ) {
	/**
	 * Extract the rating figures a node needs from a parsed block.
	 *
	 * Defaults mirror src/blocks/star-rating/block.json. They have to be
	 * repeated rather than read: WordPress omits an attribute from the block
	 * comment whenever it equals its default, so `attrs` is a sparse array.
	 *
	 * @param array $block Parsed block.
	 * @return array|null { value, max, count, item, author } or null when the
	 *                    block cannot produce an honest node.
	 */
	function designsetgo_schema_rating_values( array $block ) {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		// A bound value is not readable from stored content — see file header.
		// Both bindable attributes disqualify the block: an AggregateRating is
		// a pair of claims, and publishing a stale count beside a real rating
		// is the same false statement as publishing a stale rating.
		if ( isset( $attrs['metadata']['bindings']['rating'] )
			|| isset( $attrs['metadata']['bindings']['ratingCount'] ) ) {
			return null;
		}

		$max   = designsetgo_star_rating_clamp_max( isset( $attrs['maxRating'] ) ? $attrs['maxRating'] : 5 );
		$value = designsetgo_star_rating_clamp( isset( $attrs['rating'] ) ? $attrs['rating'] : 4.5, $max );

		$count = 0;
		if ( isset( $attrs['ratingCount'] ) && is_numeric( $attrs['ratingCount'] ) ) {
			$count = max( 0, (int) $attrs['ratingCount'] );
		}

		return array(
			'value'  => $value,
			'max'    => $max,
			'count'  => $count,
			'item'   => isset( $attrs['schemaItemName'] ) ? trim( (string) $attrs['schemaItemName'] ) : '',
			'author' => isset( $attrs['schemaAuthor'] ) ? trim( (string) $attrs['schemaAuthor'] ) : '',
		);
	}
}

if ( ! function_exists( 'designsetgo_schema_rating_item_reviewed' ) ) {
	/**
	 * The thing the rating is about.
	 *
	 * `Thing` rather than `Product`: the block has no way to know what the page
	 * is about, and mislabelling a blog post as a Product to chase a rich
	 * result is exactly the spam this extension's opt-in default exists to
	 * avoid. A site that genuinely sells something should let WooCommerce (or
	 * its SEO plugin) emit the Product node, and can reshape this one through
	 * the `designsetgo_schema_nodes` filter.
	 *
	 * @param string $name  Author-supplied item name.
	 * @param string $title Page title, used when no name is given.
	 * @return array|null Schema node, or null when neither is available.
	 */
	function designsetgo_schema_rating_item_reviewed( $name, $title ) {
		$name = '' !== $name ? $name : trim( (string) $title );

		if ( '' === $name ) {
			return null;
		}

		return array(
			'@type' => 'Thing',
			'name'  => $name,
		);
	}
}

if ( ! function_exists( 'designsetgo_schema_build_rating_value' ) ) {
	/**
	 * The Rating sub-node shared by both types.
	 *
	 * `worstRating` is 0, not schema.org's default of 1: this block's scale
	 * genuinely starts at zero stars, and letting the default stand would
	 * describe a 1-based scale the author never chose.
	 *
	 * @param float $value Rating.
	 * @param int   $max   Maximum.
	 * @return array Rating node body.
	 */
	function designsetgo_schema_build_rating_value( $value, $max ) {
		$value = round( (float) $value, 2 );

		return array(
			// A whole rating is emitted as an integer so the JSON reads
			// `"ratingValue": 5` rather than `5.0`. Both are valid; the first
			// is what every published example looks like.
			'ratingValue' => ( (float) (int) $value === $value ) ? (int) $value : $value,
			'bestRating'  => (int) $max,
			'worstRating' => 0,
		);
	}
}

if ( ! function_exists( 'designsetgo_schema_build_aggregate_rating' ) ) {
	/**
	 * Build an AggregateRating node.
	 *
	 * Emits nothing without a rating count. That is not caution for its own
	 * sake — `ratingCount` (or `reviewCount`) is required for the markup to be
	 * eligible at all, so a node without one is dead weight in the graph that
	 * still asserts a rating.
	 *
	 * @param array  $block Parsed block.
	 * @param string $title Page title.
	 * @return array Schema node, or an empty array to emit nothing.
	 */
	function designsetgo_schema_build_aggregate_rating( $block, $title = '' ) {
		$values = designsetgo_schema_rating_values( (array) $block );

		if ( null === $values || $values['count'] <= 0 ) {
			return array();
		}

		$item = designsetgo_schema_rating_item_reviewed( $values['item'], $title );

		if ( null === $item ) {
			return array();
		}

		return array_merge(
			array( '@type' => 'AggregateRating' ),
			designsetgo_schema_build_rating_value( $values['value'], $values['max'] ),
			array(
				'ratingCount'  => $values['count'],
				'itemReviewed' => $item,
			)
		);
	}
}

if ( ! function_exists( 'designsetgo_schema_build_review' ) ) {
	/**
	 * Build a Review node.
	 *
	 * Emits nothing without a named author, for the same reason the aggregate
	 * builder insists on a count: a Review with no author is ignored, and an
	 * anonymous rating claim is worth less than no claim.
	 *
	 * @param array  $block Parsed block.
	 * @param string $title Page title.
	 * @return array Schema node, or an empty array to emit nothing.
	 */
	function designsetgo_schema_build_review( $block, $title = '' ) {
		$values = designsetgo_schema_rating_values( (array) $block );

		if ( null === $values || '' === $values['author'] ) {
			return array();
		}

		$item = designsetgo_schema_rating_item_reviewed( $values['item'], $title );

		if ( null === $item ) {
			return array();
		}

		return array(
			'@type'        => 'Review',
			'reviewRating' => array_merge(
				array( '@type' => 'Rating' ),
				designsetgo_schema_build_rating_value( $values['value'], $values['max'] )
			),
			'author'       => array(
				'@type' => 'Person',
				'name'  => $values['author'],
			),
			'itemReviewed' => $item,
		);
	}
}

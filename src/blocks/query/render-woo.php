<?php
/**
 * Query block — WooCommerce-aware query arguments.
 *
 * Two jobs, both following the "use WooCommerce's own blocks first" principle
 * (docs/plans/2026-08-17-woocommerce-surface.md):
 *
 * 1. Catalog-correct product queries. A plain WP_Query with post_type=product is
 *    NOT a Woo catalog query — it ignores the `product_visibility` taxonomy, so
 *    hidden and exclude-from-catalog products leak into the loop, and the
 *    `woocommerce_hide_out_of_stock_items` option does not apply.
 *
 * 2. Consuming the URL parameters WooCommerce's own filter blocks already emit,
 *    so `woocommerce/product-filters` and its children can drive a
 *    `designsetgo/query` loop. Those blocks are built for Woo's Product
 *    Collection and provide their children `query` / `filterParams` context, so
 *    they cannot target our loop directly — but they navigate by URL, and that
 *    we can read. Authors get Woo's filter UI without DSGo rebuilding it.
 *
 * Woo's parameter names, read from its source rather than assumed:
 *   - min_price / max_price       ProductFilterPrice::MIN_PRICE_QUERY_VAR
 *   - filter_stock_status         ProductFilterStatus::STOCK_STATUS_QUERY_VAR
 *   - rating_filter               RatingFilter::RATING_QUERY_VAR
 *   - filter_<attr> / query_type_<attr>   attribute taxonomies, `pa_` stripped
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_woo_active' ) ) {
	/**
	 * Whether WooCommerce is loaded far enough to build catalog queries.
	 *
	 * @return bool
	 */
	function designsetgo_query_woo_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}
}

if ( ! function_exists( 'designsetgo_query_targets_products' ) ) {
	/**
	 * Whether the query's post type includes WooCommerce products.
	 *
	 * @param array $args WP_Query args.
	 * @return bool
	 */
	function designsetgo_query_targets_products( array $args ) {
		$post_type = $args['post_type'] ?? '';

		return in_array( 'product', (array) $post_type, true );
	}
}

if ( ! function_exists( 'designsetgo_query_append_tax_clause' ) ) {
	/**
	 * Appends a tax_query clause, preserving any existing OR group.
	 *
	 * Mirrors the wrapping the URL-param taxonomy filter already performs in
	 * render-posts.php: an author-configured `relation: OR` group must stay an OR
	 * group and be ANDed against the new clause, not flattened into it.
	 *
	 * @param array $args   WP_Query args, by reference.
	 * @param array $clause Tax query clause to append.
	 */
	function designsetgo_query_append_tax_clause( array &$args, array $clause ) {
		if ( isset( $args['tax_query'] ) && 'OR' === ( $args['tax_query']['relation'] ?? 'AND' ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Re-wrapping an author-configured tax_query; same query, AND-grouped.
			$args['tax_query'] = array(
				'relation' => 'AND',
				$args['tax_query'],
			);
		} elseif ( ! isset( $args['tax_query'] ) ) {
			$args['tax_query'] = array( 'relation' => 'AND' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$args['tax_query'][] = $clause;
	}
}

if ( ! function_exists( 'designsetgo_query_append_meta_clause' ) ) {
	/**
	 * Appends a meta_query clause without disturbing an existing OR group.
	 *
	 * @param array $args   WP_Query args, by reference.
	 * @param array $clause Meta query clause to append.
	 */
	function designsetgo_query_append_meta_clause( array &$args, array $clause ) {
		if ( isset( $args['meta_query'] ) && 'OR' === ( $args['meta_query']['relation'] ?? 'AND' ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Re-wrapping an author-configured meta_query; same query, AND-grouped.
			$args['meta_query'] = array(
				'relation' => 'AND',
				$args['meta_query'],
			);
		} elseif ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = array( 'relation' => 'AND' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$args['meta_query'][] = $clause;
	}
}

if ( ! function_exists( 'designsetgo_query_apply_woo_args' ) ) {
	/**
	 * Applies WooCommerce catalog rules and Woo filter URL params.
	 *
	 * No-ops entirely unless WooCommerce is active and the query targets
	 * products, so non-Woo sites and non-product loops are untouched.
	 *
	 * @param array $args   WP_Query args.
	 * @param array $atts   Block attributes.
	 * @param array $params Sanitised URL params.
	 * @return array Modified WP_Query args.
	 */
	function designsetgo_query_apply_woo_args( array $args, array $atts, array $params ) {
		if ( ! designsetgo_query_woo_active() || ! designsetgo_query_targets_products( $args ) ) {
			return $args;
		}

		$args = designsetgo_query_apply_woo_visibility( $args, $atts );
		$args = designsetgo_query_apply_woo_atts( $args, $atts );

		return designsetgo_query_apply_woo_params( $args, $params );
	}
}

if ( ! function_exists( 'designsetgo_query_apply_woo_visibility' ) ) {
	/**
	 * Applies catalog visibility, unless the author explicitly opted out.
	 *
	 * Explicit rather than implicit by design (decision D5): silently rewriting a
	 * query based on a post-type string is undebuggable two years later, so the
	 * author must be able to see and toggle this.
	 *
	 * @param array $args WP_Query args.
	 * @param array $atts Block attributes.
	 * @return array
	 */
	function designsetgo_query_apply_woo_visibility( array $args, array $atts ) {
		// Default true: an author who has never seen the control still gets a
		// catalog-correct query.
		if ( isset( $atts['wooCatalogVisibility'] ) && ! $atts['wooCatalogVisibility'] ) {
			return $args;
		}

		$excluded = array( 'exclude-from-catalog' );

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$excluded[] = 'outofstock';
		}

		designsetgo_query_append_tax_clause(
			$args,
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => $excluded,
				'operator' => 'NOT IN',
			)
		);

		return $args;
	}
}

if ( ! function_exists( 'designsetgo_query_apply_woo_atts' ) ) {
	/**
	 * Applies the block's own Woo attributes (featured, on sale, stock status).
	 *
	 * @param array $args WP_Query args.
	 * @param array $atts Block attributes.
	 * @return array
	 */
	function designsetgo_query_apply_woo_atts( array $args, array $atts ) {
		if ( ! empty( $atts['wooFeatured'] ) ) {
			designsetgo_query_append_tax_clause(
				$args,
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => array( 'featured' ),
					'operator' => 'IN',
				)
			);
		}

		if ( ! empty( $atts['wooOnSale'] ) && function_exists( 'wc_get_product_ids_on_sale' ) ) {
			$on_sale = wc_get_product_ids_on_sale();

			// Intersect rather than overwrite: the manual source sets post__in in
			// designsetgo_query_build_posts_args(), and the designsetgo_query_args
			// filter can too.
			//
			// The relationship source does reach this file — render-relationship.php
			// delegates to the posts renderer with source=manual — but that same
			// override forces post_type=any, so designsetgo_query_targets_products()
			// returns false and nothing here runs for it. A relationship loop over
			// products therefore gets no catalog-visibility filtering; see the test
			// pinning that boundary.
			$args['post__in'] = isset( $args['post__in'] ) && ! empty( $args['post__in'] )
				? array_values( array_intersect( (array) $args['post__in'], $on_sale ) )
				: ( empty( $on_sale ) ? array( 0 ) : $on_sale );
		}

		$stock = isset( $atts['wooStockStatus'] ) ? array_filter( (array) $atts['wooStockStatus'] ) : array();

		// NOTE: this clause and the `filter_stock_status` URL param clause in
		// designsetgo_query_apply_woo_params() are ANDed together, so they
		// intersect rather than override. That is intentional — the attribute is
		// the author's standing constraint and the URL param is the visitor's
		// filter, and a visitor should not be able to widen past what the author
		// allowed. The consequence is that a disjoint combination (author allows
		// only `instock`, visitor filters `outofstock`) legitimately returns zero
		// products. Pair the block with `designsetgo/query-no-results` so that
		// reads as an empty state rather than a broken page.
		if ( ! empty( $stock ) ) {
			designsetgo_query_append_meta_clause(
				$args,
				array(
					'key'     => '_stock_status',
					'value'   => array_map( 'sanitize_key', $stock ),
					'compare' => 'IN',
				)
			);
		}

		return $args;
	}
}

if ( ! function_exists( 'designsetgo_query_apply_woo_params' ) ) {
	/**
	 * Applies the URL params WooCommerce's own filter blocks emit.
	 *
	 * Price uses a `_price` meta_query, matching WooCommerce's own
	 * ProductCollection\QueryBuilder::get_filter_by_price_query() exactly. A
	 * variable product stores one `_price` row per variation, so this matches
	 * "any variation falls in range" — the same semantics Woo's price slider
	 * presents. No DesignSetGo schema is involved.
	 *
	 * @param array $args   WP_Query args.
	 * @param array $params Sanitised URL params.
	 * @return array
	 */
	function designsetgo_query_apply_woo_params( array $args, array $params ) {
		$min = isset( $params['min_price'] ) ? (float) $params['min_price'] : null;
		$max = isset( $params['max_price'] ) ? (float) $params['max_price'] : null;

		if ( null !== $min ) {
			designsetgo_query_append_meta_clause(
				$args,
				array(
					'key'     => '_price',
					'value'   => $min,
					'compare' => '>=',
					'type'    => 'DECIMAL(10,2)',
				)
			);
		}

		if ( null !== $max ) {
			designsetgo_query_append_meta_clause(
				$args,
				array(
					'key'     => '_price',
					'value'   => $max,
					'compare' => '<=',
					'type'    => 'DECIMAL(10,2)',
				)
			);
		}

		if ( ! empty( $params['filter_stock_status'] ) ) {
			// sanitize_key() to match the wooStockStatus attribute path; Woo's
			// statuses are all lowercase slugs, so nothing valid is lost.
			$statuses = array_map( 'sanitize_key', designsetgo_query_woo_split( $params['filter_stock_status'] ) );
			$statuses = array_values( array_filter( $statuses ) );

			// ANDs with any wooStockStatus attribute clause rather than replacing
			// it — see the note in designsetgo_query_apply_woo_atts().
			if ( ! empty( $statuses ) ) {
				designsetgo_query_append_meta_clause(
					$args,
					array(
						'key'     => '_stock_status',
						'value'   => $statuses,
						'compare' => 'IN',
					)
				);
			}
		}

		if ( ! empty( $params['rating_filter'] ) ) {
			$ratings = array_filter(
				array_map( 'absint', designsetgo_query_woo_split( $params['rating_filter'] ) ),
				static function ( $rating ) {
					return $rating >= 1 && $rating <= 5;
				}
			);

			if ( ! empty( $ratings ) ) {
				designsetgo_query_append_tax_clause(
					$args,
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'name',
						'terms'    => array_map(
							static function ( $rating ) {
								return 'rated-' . $rating;
							},
							$ratings
						),
						'operator' => 'IN',
					)
				);
			}
		}

		return designsetgo_query_apply_woo_attribute_params( $args, $params );
	}
}

if ( ! function_exists( 'designsetgo_query_apply_woo_attribute_params' ) ) {
	/**
	 * Maps Woo's `filter_<attr>` params onto `pa_<attr>` attribute taxonomies.
	 *
	 * WooCommerce strips the `pa_` prefix in the URL, so `pa_color` travels as
	 * `filter_color`. The generic `filter_<taxonomy>` handling in render-posts.php
	 * cannot see these: it checks `taxonomy_exists( 'color' )`, which is false, so
	 * it skips them silently. That is why this mapping is needed rather than
	 * relying on the existing loop.
	 *
	 * `query_type_<attr>=and` switches the clause from IN to AND, matching Woo.
	 *
	 * @param array $args   WP_Query args.
	 * @param array $params Sanitised URL params.
	 * @return array
	 */
	function designsetgo_query_apply_woo_attribute_params( array $args, array $params ) {
		foreach ( $params as $key => $value ) {
			$key = (string) $key;

			if ( 0 !== strpos( $key, 'filter_' ) || 'filter_stock_status' === $key ) {
				continue;
			}

			$slug     = substr( $key, strlen( 'filter_' ) );
			$taxonomy = 'pa_' . $slug;

			// Skip anything the generic taxonomy handler already covers, and any
			// attribute taxonomy that does not exist on this site.
			if ( '' === $slug || taxonomy_exists( $slug ) || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = array_filter( array_map( 'sanitize_title', designsetgo_query_woo_split( $value ) ) );

			if ( empty( $terms ) ) {
				continue;
			}

			$query_type = isset( $params[ 'query_type_' . $slug ] ) ? strtolower( (string) $params[ 'query_type_' . $slug ] ) : 'or';

			designsetgo_query_append_tax_clause(
				$args,
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array_values( $terms ),
					'operator' => 'and' === $query_type ? 'AND' : 'IN',
				)
			);
		}

		return $args;
	}
}

if ( ! function_exists( 'designsetgo_query_woo_split' ) ) {
	/**
	 * Normalises a URL param that may arrive as an array or a CSV string.
	 *
	 * @param mixed $value Raw param value.
	 * @return string[]
	 */
	function designsetgo_query_woo_split( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'trim', array_map( 'strval', $value ) ) ) );
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) );
	}
}

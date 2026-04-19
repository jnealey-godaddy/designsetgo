<?php
/**
 * Dynamic Query — Posts source renderer.
 *
 * Handles source values 'posts', 'manual', and 'current' (archive inheritance).
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_render_posts' ) ) :

	/**
	 * Render a Dynamic Query block for the Posts (or Manual/Current) source.
	 *
	 * Builds WP_Query args, applies filter hooks, iterates posts, and delegates
	 * item rendering to designsetgo_query_render_item() with per-item context
	 * using core `postId` / `postType` keys so core blocks and Block Bindings
	 * resolve against the iterated post, not the outer page's post.
	 *
	 * @param array $atts    Block attributes (already defaulted).
	 * @param array $context Render context (query_id, page, inner_html, params).
	 * @return array { html: string, totalPages: int, totalItems: int }
	 */
	function designsetgo_query_render_posts( array $atts, array $context ) {
		global $post;
		$saved_post = $post;

		$args = designsetgo_query_build_posts_args( $atts, $context );

		/**
		 * Filter the WP_Query args for a DesignSetGo Dynamic Query (all queries).
		 *
		 * @param array $args    WP_Query args.
		 * @param array $atts    Block attributes (already defaulted).
		 * @param array $context Render context.
		 */
		$args = apply_filters( 'designsetgo_query_args', $args, $atts, $context );

		$query_id = isset( $context['query_id'] ) ? (string) $context['query_id'] : '';
		if ( '' !== $query_id ) {
			/**
			 * Filter WP_Query args for a specific Query ID.
			 *
			 * Fired only when queryId matches — useful for "related posts"
			 * recipes without polluting unrelated queries.
			 *
			 * @param array $args    WP_Query args.
			 * @param array $atts    Block attributes.
			 * @param array $context Render context.
			 */
			$args = apply_filters( 'designsetgo/query/' . $query_id . '/args', $args, $atts, $context );
		}

		$query = new WP_Query( $args );

		$items_html = '';
		try {
			while ( $query->have_posts() ) {
				$query->the_post();
				$items_html .= designsetgo_query_render_item(
					(string) $context['inner_html'],
					array(
						'postId'   => get_the_ID(),
						'postType' => get_post_type(),
					),
					$atts['itemTagName']
				);
			}
		} finally {
			wp_reset_postdata();
			$post = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$state = array(
			'totalItems' => (int) $query->found_posts,
			'totalPages' => (int) $query->max_num_pages,
			'page'       => max( 1, (int) $context['page'] ),
		);
		designsetgo_query_set_last_state( $query_id, $state );

		return array(
			'html'       => designsetgo_query_wrap( $items_html, $atts, $context, $context['wrapper_attrs'] ?? null ),
			'totalPages' => $state['totalPages'],
			'totalItems' => $state['totalItems'],
		);
	}

	/**
	 * Build WP_Query args from block attributes and render context.
	 *
	 * Handles: pagination, ordering, search, author, excludeCurrent,
	 * tax_query, meta_query, manual IDs (post__in), and current-archive
	 * var inheritance.
	 *
	 * @param array $atts    Block attributes (already defaulted).
	 * @param array $context Render context.
	 * @return array WP_Query args.
	 */
	function designsetgo_query_build_posts_args( array $atts, array $context ) {
		$per_page = max( 1, (int) $atts['perPage'] );
		$page     = max( 1, (int) $context['page'] );

		$post_type = 'manual' === $atts['source'] ? 'any' : sanitize_key( (string) $atts['postType'] );

		$args = array(
			'post_type'           => $post_type,
			'posts_per_page'      => $per_page,
			'offset'              => max( 0, (int) $atts['offset'] ) + ( ( $page - 1 ) * $per_page ),
			'orderby'             => sanitize_key( (string) $atts['orderBy'] ),
			'order'               => 'ASC' === strtoupper( (string) $atts['order'] ) ? 'ASC' : 'DESC',
			'ignore_sticky_posts' => (bool) $atts['ignoreSticky'],
			'post_status'         => 'publish',
		);

		if ( in_array( $atts['orderBy'], array( 'meta_value', 'meta_value_num' ), true ) && ! empty( $atts['orderByMetaKey'] ) ) {
			$args['meta_key'] = sanitize_text_field( (string) $atts['orderByMetaKey'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}

		// Search — attribute-bound OR URL-bound via bindSearchTo.
		$search = (string) $atts['search'];
		if ( ! empty( $atts['bindSearchTo'] ) && isset( $context['params'][ $atts['bindSearchTo'] ] ) ) {
			$search = (string) $context['params'][ $atts['bindSearchTo'] ];
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( ! empty( $atts['author'] ) && is_array( $atts['author'] ) ) {
			$args['author__in'] = array_map( 'absint', $atts['author'] );
		}

		if ( ! empty( $atts['excludeCurrent'] ) && is_singular() ) {
			$current_id = get_queried_object_id();
			if ( $current_id ) {
				$args['post__not_in'] = array( $current_id );
			}
		}

		// Taxonomy query.
		$tax_clauses = isset( $atts['taxQuery']['clauses'] ) ? (array) $atts['taxQuery']['clauses'] : array();
		if ( ! empty( $tax_clauses ) ) {
			$tax_query = array(
				'relation' => ( 'OR' === ( $atts['taxQuery']['relation'] ?? 'AND' ) ) ? 'OR' : 'AND',
			);
			foreach ( $tax_clauses as $clause ) {
				if ( empty( $clause['taxonomy'] ) || empty( $clause['terms'] ) ) {
					continue;
				}
				$tax_query[] = array(
					'taxonomy' => sanitize_key( (string) $clause['taxonomy'] ),
					'terms'    => array_map( 'absint', (array) $clause['terms'] ),
					'operator' => in_array( ( $clause['operator'] ?? 'IN' ), array( 'IN', 'NOT IN', 'AND' ), true ) ? $clause['operator'] : 'IN',
				);
			}
			if ( count( $tax_query ) > 1 ) {
				$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			}
		}

		// Meta query.
		$meta_clauses = isset( $atts['metaQuery']['clauses'] ) ? (array) $atts['metaQuery']['clauses'] : array();
		if ( ! empty( $meta_clauses ) ) {
			$meta_query = array(
				'relation' => ( 'OR' === ( $atts['metaQuery']['relation'] ?? 'AND' ) ) ? 'OR' : 'AND',
			);
			$valid_compare = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS' );
			$valid_type    = array( 'CHAR', 'NUMERIC', 'DATE' );
			foreach ( $meta_clauses as $clause ) {
				if ( empty( $clause['key'] ) ) {
					continue;
				}
				$meta_query[] = array(
					'key'     => sanitize_text_field( (string) $clause['key'] ),
					'value'   => sanitize_text_field( (string) ( $clause['value'] ?? '' ) ),
					'compare' => in_array( ( $clause['compare'] ?? '=' ), $valid_compare, true ) ? $clause['compare'] : '=',
					'type'    => in_array( ( $clause['type'] ?? 'CHAR' ), $valid_type, true ) ? $clause['type'] : 'CHAR',
				);
			}
			if ( count( $meta_query ) > 1 ) {
				$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			}
		}

		// Manual source: override with specific post IDs in user-defined order.
		if ( 'manual' === $atts['source'] && ! empty( $atts['manualIds'] ) && is_array( $atts['manualIds'] ) ) {
			$ids = array_values( array_filter( array_map( 'absint', $atts['manualIds'] ) ) );
			if ( ! empty( $ids ) ) {
				$args['post__in']       = $ids;
				$args['orderby']        = 'post__in';
				$args['post_type']      = 'any';
				$args['posts_per_page'] = count( $ids );
			}
		}

		// Current source: inherit a narrow subset of query vars from the outer archive.
		if ( 'current' === $atts['source'] && isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query']->query_vars ) {
			$inherited = array_intersect_key(
				$GLOBALS['wp_query']->query_vars,
				array_flip( array( 'post_type', 'category_name', 'tag', 'author_name', 'year', 'monthnum', 's' ) )
			);
			$args = array_merge( $args, $inherited );
		}

		return $args;
	}

endif;

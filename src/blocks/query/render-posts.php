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

if ( ! function_exists( 'designsetgo_build_tax_query_entry' ) ) :
	/**
	 * Recursively builds a WP_Query tax_query clause or nested group.
	 *
	 * @param array $entry Clause or group from block attributes.
	 * @return array|null WP_Query tax_query entry, or null if invalid.
	 */
	function designsetgo_build_tax_query_entry( array $entry ) {
		if ( isset( $entry['clauses'] ) ) {
			// Nested group.
			$relation = ( 'OR' === ( $entry['relation'] ?? 'AND' ) ) ? 'OR' : 'AND';
			$children = array();
			foreach ( (array) $entry['clauses'] as $child ) {
				$built = designsetgo_build_tax_query_entry( $child );
				if ( null !== $built ) {
					$children[] = $built;
				}
			}
			if ( empty( $children ) ) {
				return null;
			}
			// WP_Query triggers _doing_it_wrong when a tax_query group carries
			// `relation` with a single child — unwrap to the bare child.
			if ( 1 === count( $children ) ) {
				return $children[0];
			}
			return array_merge( array( 'relation' => $relation ), $children );
		}
		// Leaf clause.
		if ( empty( $entry['taxonomy'] ) || empty( $entry['terms'] ) ) {
			return null;
		}
		$operator = $entry['operator'] ?? 'IN';
		if ( ! in_array( $operator, array( 'IN', 'NOT IN', 'AND' ), true ) ) {
			$operator = 'IN';
		}
		return array(
			'taxonomy'         => sanitize_key( (string) $entry['taxonomy'] ),
			'terms'            => array_map( 'absint', (array) $entry['terms'] ),
			'operator'         => $operator,
			'include_children' => isset( $entry['include_children'] ) ? (bool) $entry['include_children'] : true,
		);
	}
endif;

if ( ! function_exists( 'designsetgo_build_meta_query_entry' ) ) :
	/**
	 * Recursively builds a WP_Query meta_query clause or nested group.
	 *
	 * @param array $entry Clause or group from block attributes.
	 * @return array|null WP_Query meta_query entry, or null if invalid.
	 */
	function designsetgo_build_meta_query_entry( array $entry ) {
		if ( isset( $entry['clauses'] ) ) {
			// Nested group.
			$relation = ( 'OR' === ( $entry['relation'] ?? 'AND' ) ) ? 'OR' : 'AND';
			$children = array();
			foreach ( (array) $entry['clauses'] as $child ) {
				$built = designsetgo_build_meta_query_entry( $child );
				if ( null !== $built ) {
					$children[] = $built;
				}
			}
			if ( empty( $children ) ) {
				return null;
			}
			// Unwrap single-child groups to avoid WP_Meta_Query's
			// `relation`-with-one-child _doing_it_wrong notice.
			if ( 1 === count( $children ) ) {
				return $children[0];
			}
			return array_merge( array( 'relation' => $relation ), $children );
		}
		// Leaf clause.
		if ( empty( $entry['key'] ) ) {
			return null;
		}
		$valid_compare = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS' );
		$valid_type    = array( 'CHAR', 'NUMERIC', 'DATE' );
		$compare       = $entry['compare'] ?? '=';
		if ( ! in_array( $compare, $valid_compare, true ) ) {
			$compare = '=';
		}
		$type = $entry['type'] ?? 'CHAR';
		if ( ! in_array( $type, $valid_type, true ) ) {
			$type = 'CHAR';
		}
		$result = array(
			'key'     => sanitize_text_field( (string) $entry['key'] ),
			'compare' => $compare,
			'type'    => $type,
		);
		if ( ! in_array( $compare, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
			$result['value'] = sanitize_text_field( (string) ( $entry['value'] ?? '' ) );
		}
		return $result;
	}
endif;

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

		// Capture render telemetry only when Query Monitor is loaded — otherwise
		// the action exposes raw SQL to any third-party callback on every public
		// page render, which is data we don't want to broadcast.
		if ( defined( 'QM_VERSION' ) ) {
			$t_start     = microtime( true );
			$query       = new WP_Query( $args );
			$duration_ms = ( microtime( true ) - $t_start ) * 1000;

			do_action(
				'designsetgo_query_did_render',
				array(
					'query_id'    => $atts['queryId'] ?? '',
					'source'      => $atts['source'] ?? 'posts',
					'wp_args'     => $args,
					'found_posts' => $query->found_posts,
					// $query->request is the actual posts SELECT WP_Query just
					// ran; $wpdb->last_query at this point would be the
					// trailing FOUND_ROWS()/COUNT instead.
					'sql'         => (string) ( $query->request ?? '' ),
					'filters'     => array(),
					'duration_ms' => round( $duration_ms, 2 ),
				)
			);
		} else {
			$query = new WP_Query( $args );
		}

		// Determine whether grouped rendering is requested.
		// Parse inner_html to split group-header blocks from the item template.
		$group_header_blocks = array();
		$item_template_html  = (string) $context['inner_html'];
		if ( ! empty( $atts['groupBy'] ) && is_array( $atts['groupBy'] ) ) {
			$parsed_inner   = parse_blocks( $item_template_html );
			$template_parts = array();
			foreach ( $parsed_inner as $pb ) {
				if ( empty( $pb['blockName'] ) ) {
					continue;
				}
				if ( 'designsetgo/query-group-header' === $pb['blockName'] ) {
					$group_header_blocks[] = $pb;
				} else {
					$template_parts[] = serialize_block( $pb );
				}
			}
			if ( ! empty( $group_header_blocks ) ) {
				$item_template_html = implode( '', $template_parts );
			}
		}

		$use_groups = ! empty( $atts['groupBy'] ) && ! empty( $group_header_blocks );

		$items_html        = '';
		$post_urls         = array();
		$collected_ids     = array();
		$collected_indexes = array();
		$flat_counter      = 0;

		try {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id     = get_the_ID();
				$post_urls[] = get_permalink();
				if ( $use_groups ) {
					$collected_ids[] = $post_id;
					if ( ! isset( $collected_indexes[ $post_id ] ) ) {
						$collected_indexes[ $post_id ] = $flat_counter;
					}
				} else {
					$items_html .= designsetgo_query_render_item(
						$item_template_html,
						array(
							'postId'                => $post_id,
							'postType'              => get_post_type(),
							'index'                 => $flat_counter,
							'designsetgo/itemIndex' => $flat_counter,
						),
						$atts['itemTagName']
					);
				}
				++$flat_counter;
			}
		} finally {
			wp_reset_postdata();
			$post = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		// If grouped, partition collected IDs and render per group.
		if ( $use_groups && ! empty( $collected_ids ) ) {
			require_once __DIR__ . '/render-helpers.php';
			$groups = designsetgo_query_partition_items( $collected_ids, $atts['groupBy'] );
			try {
				foreach ( $groups as $group ) {
					// Render group header block(s) with group context.
					$header_html_out = '';
					foreach ( $group_header_blocks as $hb ) {
						$header_block     = new WP_Block(
							$hb,
							array(
								'designsetgo/queryId'    => sanitize_key( (string) ( $context['query_id'] ?? '' ) ),
								'designsetgo/groupLabel' => (string) $group['label'],
								'designsetgo/groupValue' => (string) $group['value'],
							)
						);
						$header_html_out .= $header_block->render();
					}
					// Render items in this group.
					// Pass both the flat cross-group index (designsetgo/itemIndex) and a
					// per-group zero-based index (designsetgo/groupItemIndex) so authors
					// can target either in custom bindings or visibility rules.
					$group_items_html = '';
					$group_item_index = 0;
					foreach ( $group['ids'] as $gid ) {
						$post_obj = get_post( $gid );
						if ( ! $post_obj ) {
							continue;
						}
						// Core blocks in the item template read the global post, so it
						// must be this item, not the page the loop sits on.
						// setup_postdata() fills the other globals but not $post.
						$post = $post_obj; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						setup_postdata( $post );
						$item_index        = isset( $collected_indexes[ $gid ] ) ? (int) $collected_indexes[ $gid ] : 0;
						$group_items_html .= designsetgo_query_render_item(
							$item_template_html,
							array(
								'postId'                => (int) $gid,
								'postType'              => $post_obj->post_type,
								'index'                 => $item_index,
								'designsetgo/itemIndex' => $item_index,
								'designsetgo/groupItemIndex' => $group_item_index,
							),
							$atts['itemTagName']
						);
						++$group_item_index;
					}
					$items_html .= sprintf(
						'<section class="dsgo-query-group" data-dsgo-group-value="%s">%s%s</section>',
						esc_attr( (string) $group['value'] ),
						$header_html_out,     // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block render() output.
						$group_items_html     // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from designsetgo_query_render_item().
					);
				}
			} finally {
				wp_reset_postdata();
				$post = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}

		$totals = designsetgo_query_paginate_totals(
			$query->found_posts,
			(int) $atts['offset'],
			(int) ( $args['posts_per_page'] ?? 0 )
		);
		$state  = array(
			'totalItems' => $totals['totalItems'],
			'totalPages' => $totals['totalPages'],
			'page'       => max( 1, (int) $context['page'] ),
		);
		designsetgo_query_set_last_state( $query_id, $state );

		// JSON-LD ItemList schema for Posts-source queries.
		$emit_schema = ! isset( $atts['emitSchema'] ) || (bool) $atts['emitSchema'];
		$schema_html = '';
		if ( $emit_schema && 'posts' === $atts['source'] && ! empty( $post_urls ) ) {
			$schema = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'ItemList',
				'itemListElement' => array_map(
					function ( $i, $url ) {
						return array(
							'@type'    => 'ListItem',
							'position' => $i + 1,
							'url'      => $url,
						);
					},
					array_keys( $post_urls ),
					$post_urls
				),
			);
			// JSON_HEX_TAG defends against a stray `</script>` inside a URL breaking
			// out of the script context; JSON_HEX_AMP keeps the payload entity-safe.
			$schema_html = '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';
		}

		return array(
			'html'       => designsetgo_query_wrap( $items_html, $atts, $context, $context['wrapper_attrs'] ?? null ) . $schema_html,
			'items_html' => $items_html,
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
			$bound  = $context['params'][ $atts['bindSearchTo'] ];
			$search = is_scalar( $bound ) ? (string) $bound : '';
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( ! empty( $atts['author'] ) && is_array( $atts['author'] ) ) {
			$args['author__in'] = array_map( 'absint', $atts['author'] );
		}

		// The page's facts come from the request on first paint and from the
		// signed definition on a REST refresh — see designsetgo_query_request_context().
		$page_context = designsetgo_query_request_context();

		if ( ! empty( $atts['excludeCurrent'] ) && $page_context['currentPostId'] ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Bounded to the single current post when "exclude current" is enabled on a singular view.
			$args['post__not_in'] = array( $page_context['currentPostId'] );
		}

		// Tax query (supports nested AND/OR groups).
		$tax_clauses = isset( $atts['taxQuery']['clauses'] ) ? (array) $atts['taxQuery']['clauses'] : array();
		if ( ! empty( $tax_clauses ) ) {
			$tax_query = array(
				'relation' => ( 'OR' === ( $atts['taxQuery']['relation'] ?? 'AND' ) ) ? 'OR' : 'AND',
			);
			foreach ( $tax_clauses as $entry ) {
				$built = designsetgo_build_tax_query_entry( $entry );
				if ( null !== $built ) {
					$tax_query[] = $built;
				}
			}
			if ( count( $tax_query ) > 1 ) {
				$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			}
		}

		// Meta query (supports nested AND/OR groups).
		$meta_clauses = isset( $atts['metaQuery']['clauses'] ) ? (array) $atts['metaQuery']['clauses'] : array();
		if ( ! empty( $meta_clauses ) ) {
			$meta_query = array(
				'relation' => ( 'OR' === ( $atts['metaQuery']['relation'] ?? 'AND' ) ) ? 'OR' : 'AND',
			);
			foreach ( $meta_clauses as $entry ) {
				$built = designsetgo_build_meta_query_entry( $entry );
				if ( null !== $built ) {
					$meta_query[] = $built;
				}
			}
			if ( count( $meta_query ) > 1 ) {
				$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			}
		}

		// Date query.
		$date_clauses = isset( $atts['dateQuery']['clauses'] ) ? (array) $atts['dateQuery']['clauses'] : array();
		if ( ! empty( $date_clauses ) ) {
			$valid_columns = array( 'post_date', 'post_modified', 'post_date_gmt', 'post_modified_gmt' );
			$date_query    = array(
				'relation' => ( 'OR' === ( $atts['dateQuery']['relation'] ?? 'AND' ) ) ? 'OR' : 'AND',
			);
			foreach ( $date_clauses as $clause ) {
				$mode   = $clause['mode'] ?? 'after';
				$after  = sanitize_text_field( (string) ( $clause['after'] ?? '' ) );
				$before = sanitize_text_field( (string) ( $clause['before'] ?? '' ) );

				// Skip clauses with no date value.
				if ( 'between' === $mode && ( '' === $after || '' === $before ) ) {
					continue;
				}
				if ( 'after' === $mode && '' === $after ) {
					continue;
				}
				if ( 'before' === $mode && '' === $before ) {
					continue;
				}

				$column = $clause['column'] ?? 'post_date';
				if ( ! in_array( $column, $valid_columns, true ) ) {
					$column = 'post_date';
				}
				$entry = array(
					'column'    => $column,
					'inclusive' => ! empty( $clause['inclusive'] ),
				);
				if ( 'after' === $mode || 'between' === $mode ) {
					$entry['after'] = $after;
				}
				if ( 'before' === $mode || 'between' === $mode ) {
					$entry['before'] = $before;
				}
				$date_query[] = $entry;
			}
			if ( count( $date_query ) > 1 ) {
				$args['date_query'] = $date_query;
			}
		}

		// Manual source: override with specific post IDs in user-defined order.
		if ( 'manual' === $atts['source'] && ! empty( $atts['manualIds'] ) && is_array( $atts['manualIds'] ) ) {
			$ids = array_values( array_filter( array_map( 'absint', $atts['manualIds'] ) ) );
			if ( ! empty( $ids ) ) {
				$args['post__in']  = $ids;
				$args['orderby']   = 'post__in';
				$args['post_type'] = 'any';
				if ( empty( $atts['manualPaginated'] ) ) {
					$args['posts_per_page'] = count( $ids );
				}
			}
		}

		// Current source: inherit a narrow subset of query vars from the outer archive.
		if ( 'current' === $atts['source'] && ! empty( $page_context['inheritedVars'] ) ) {
			$args = array_merge( $args, $page_context['inheritedVars'] );
		}

		// URL-param-driven filters (Task 14).
		// filter_<taxonomy>=slug[,slug] or filter_<taxonomy>[]=slug style.
		// Also handles ?q= directly (overrides static search attr when present).
		//
		// Query param scoping (v2.6): $context['params'] already resolved a
		// query-scoped key (`{key}__{queryId}`) in preference to a same-named
		// bare one, and never surfaces a key scoped for a DIFFERENT query —
		// see designsetgo_query_extract_params_from_request(). A bare/legacy
		// key below is intentionally NOT gated by whether this query has a
		// matching filter block: that was tried and reverted (PR #592) — a
		// site owner routinely links to `?filter_category=news` from a menu
		// or widget with no filter control anywhere on the landing page, and
		// every Query on that page has always honored it. Scoping (not
		// declaration) is what stops one query's filter INTERACTION from
		// leaking into another's; a bare URL still reaches everyone, exactly
		// as before this task.
		$params = isset( $context['params'] ) ? (array) $context['params'] : array();

		// Direct ?q= support: override search when bindSearchTo is not set or empty.
		if ( isset( $params['q'] ) && '' === (string) $atts['bindSearchTo'] ) {
			$q_raw = designsetgo_query_scalar_param( $params['q'] );
			$q_val = is_array( $q_raw ) ? implode( ' ', $q_raw ) : $q_raw;
			$q_val = sanitize_text_field( $q_val );
			if ( '' !== $q_val ) {
				$args['s'] = $q_val;
			}
		}

		foreach ( $params as $key => $value ) {
			$key = (string) $key;
			if ( 0 !== strpos( $key, 'filter_' ) ) {
				continue;
			}
			$taxonomy = substr( $key, strlen( 'filter_' ) );
			if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			// `filter_x=a,b` is the documented slug[,slug] form (the filter
			// control ticks both); split it like the control does, or the pair
			// collapses into one slug "ab".
			$terms = array();
			foreach ( is_array( $value ) ? $value : array( $value ) as $raw_term ) {
				if ( is_scalar( $raw_term ) ) {
					$terms = array_merge( $terms, explode( ',', (string) $raw_term ) );
				}
			}
			$terms = array_unique( array_filter( array_map( 'sanitize_title', $terms ) ) );
			if ( empty( $terms ) ) {
				continue;
			}
			// If an attribute-level tax_query exists with relation=OR, wrap it so
			// the URL-param clause ANDs against the entire OR group.
			if ( isset( $args['tax_query'] ) && 'OR' === ( $args['tax_query']['relation'] ?? 'AND' ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Re-wrapping an existing tax_query the user explicitly configured; same query, just AND-grouped with the URL-param clause.
				$args['tax_query'] = array(
					'relation' => 'AND',
					$args['tax_query'],
				);
			} elseif ( ! isset( $args['tax_query'] ) ) {
				$args['tax_query'] = array( 'relation' => 'AND' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			}
			$args['tax_query'][] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => array_values( $terms ),
				'operator' => 'IN',
			);
		}

		// WooCommerce catalog rules + the URL params Woo's own filter blocks emit.
		// Applied after the generic taxonomy filters so it can see, and safely
		// AND against, whatever they built.
		if ( ! function_exists( 'designsetgo_query_apply_woo_args' ) ) {
			$woo_helpers = __DIR__ . '/render-woo.php';
			if ( file_exists( $woo_helpers ) ) {
				require_once $woo_helpers; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- sibling build file; path resolved from __DIR__
			}
		}
		if ( function_exists( 'designsetgo_query_apply_woo_args' ) ) {
			$args = designsetgo_query_apply_woo_args( $args, $atts, $params );
		}

		// URL-param sort override (?sort=orderby.DIR).
		if ( isset( $params['sort'] ) && is_string( $params['sort'] ) && '' !== $params['sort'] ) {
			$parts           = explode( '.', $params['sort'], 2 );
			$sort_by         = sanitize_key( $parts[0] );
			$sort_dir        = isset( $parts[1] ) && 'ASC' === strtoupper( $parts[1] ) ? 'ASC' : 'DESC';
			$allowed_orderby = array( 'date', 'title', 'menu_order', 'rand', 'comment_count', 'meta_value', 'meta_value_num' );
			$is_meta_sort    = in_array( $sort_by, array( 'meta_value', 'meta_value_num' ), true );
			// Sort options carry no meta key, so a meta sort needs one from the
			// block; without it the option can't be honoured and is ignored
			// rather than silently falling back to date.
			$meta_key = isset( $args['meta_key'] ) ? $args['meta_key'] : sanitize_text_field( (string) $atts['orderByMetaKey'] );
			if ( in_array( $sort_by, $allowed_orderby, true ) && ( ! $is_meta_sort || '' !== $meta_key ) ) {
				$args['orderby'] = $sort_by;
				$args['order']   = $sort_dir;
				if ( $is_meta_sort ) {
					$args['meta_key'] = $meta_key; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				} else {
					// A leftover meta_key would drop every post that lacks it.
					unset( $args['meta_key'] );
				}
			}
		}

		// Random order re-rolls on every request, so offset pagination would
		// repeat and skip posts. Each first paint picks a fresh seed, which the
		// region hands back with Load more / infinite scroll so later pages
		// continue the same shuffle.
		if ( 'rand' === $args['orderby'] ) {
			$query_key = (string) ( $context['query_id'] ?? '' );
			$requested = isset( $GLOBALS['designsetgo_query_requested_rand_seeds'] ) ? (array) $GLOBALS['designsetgo_query_requested_rand_seeds'] : array();
			$seed      = isset( $context['rand_seed'] ) ? absint( $context['rand_seed'] ) : absint( $requested[ $query_key ] ?? 0 );
			if ( $seed < 1 ) {
				$seed = wp_rand( 1, 2147483647 );
			}
			$seed = (int) apply_filters( 'designsetgo_query_rand_seed', $seed, $atts, $context );

			$GLOBALS['designsetgo_query_rand_seeds'][ $query_key ] = $seed;
			$args['dsgo_rand_seed']                                = $seed;
			if ( ! has_filter( 'posts_orderby', 'designsetgo_query_seeded_rand_orderby' ) ) {
				add_filter( 'posts_orderby', 'designsetgo_query_seeded_rand_orderby', 10, 2 );
			}
		}

		// Posts that tie on the sort column (same publish time, same title)
		// come back in whatever order MySQL picks per request, so offset
		// pages repeat some and skip others. Break ties on ID. Done in SQL so
		// `orderby` stays the string the designsetgo_query_args filter sees.
		$args['dsgo_id_tiebreak'] = true;
		if ( ! has_filter( 'posts_orderby', 'designsetgo_query_id_tiebreak_orderby' ) ) {
			add_filter( 'posts_orderby', 'designsetgo_query_id_tiebreak_orderby', 20, 2 );
		}

		return $args;
	}

endif;

if ( ! function_exists( 'designsetgo_query_id_tiebreak_orderby' ) ) :
	/**
	 * Append `posts.ID` to the ORDER BY of Query loops so ties sort stably.
	 *
	 * Skipped when the clause already orders by ID (post__in, an ID sort) or
	 * is random, where a tiebreak changes nothing.
	 *
	 * @param string    $orderby ORDER BY clause.
	 * @param \WP_Query $query   Query.
	 * @return string
	 */
	function designsetgo_query_id_tiebreak_orderby( $orderby, $query ) {
		global $wpdb;

		$orderby = (string) $orderby;
		if ( ! $query instanceof \WP_Query || ! $query->get( 'dsgo_id_tiebreak' ) || '' === trim( $orderby ) ) {
			return $orderby;
		}
		if ( false !== stripos( $orderby, 'RAND(' ) || false !== strpos( $orderby, "{$wpdb->posts}.ID" ) ) {
			return $orderby;
		}

		$order = 'ASC' === strtoupper( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';
		return $orderby . ", {$wpdb->posts}.ID {$order}";
	}
endif;

if ( ! function_exists( 'designsetgo_query_seeded_rand_orderby' ) ) :
	/**
	 * Swap RAND() for RAND(seed) on Query loops that asked for a seeded shuffle.
	 *
	 * @param string    $orderby ORDER BY clause.
	 * @param \WP_Query $query   Query.
	 * @return string
	 */
	function designsetgo_query_seeded_rand_orderby( $orderby, $query ) {
		$seed = $query instanceof \WP_Query ? $query->get( 'dsgo_rand_seed' ) : '';
		if ( '' === $seed || null === $seed || false === strpos( (string) $orderby, 'RAND()' ) ) {
			return $orderby;
		}
		return str_replace( 'RAND()', 'RAND(' . (int) $seed . ')', $orderby );
	}
endif;

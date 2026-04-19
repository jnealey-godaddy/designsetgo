<?php
/**
 * Dynamic Query — Terms source renderer.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_render_terms' ) ) :

	function designsetgo_query_render_terms( array $atts, array $context ) {
		$per_page = max( 1, (int) $atts['perPage'] );
		$page     = max( 1, (int) $context['page'] );

		// Collect taxonomies from clauses; default to 'category' if none supplied.
		$taxonomies = array();
		foreach ( (array) ( $atts['taxQuery']['clauses'] ?? array() ) as $clause ) {
			if ( ! empty( $clause['taxonomy'] ) ) {
				$tax = sanitize_key( (string) $clause['taxonomy'] );
				if ( '' !== $tax && taxonomy_exists( $tax ) ) {
					$taxonomies[] = $tax;
				}
			}
		}
		if ( empty( $taxonomies ) ) {
			$taxonomies = array( 'category' );
		}

		$args = array(
			'taxonomy'   => $taxonomies,
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => max( 0, (int) $atts['offset'] ) + ( ( $page - 1 ) * $per_page ),
			'orderby'    => designsetgo_query_sanitize_term_orderby( (string) $atts['orderBy'] ),
			'order'      => 'ASC' === strtoupper( (string) $atts['order'] ) ? 'ASC' : 'DESC',
		);

		// Search (term name LIKE).
		$search = (string) $atts['search'];
		if ( ! empty( $atts['bindSearchTo'] ) && isset( $context['params'][ $atts['bindSearchTo'] ] ) ) {
			$search = (string) $context['params'][ $atts['bindSearchTo'] ];
		}
		if ( '' !== $search ) {
			$args['name__like'] = $search;
		}

		/** This filter is documented in src/blocks/query/render-posts.php */
		$args = apply_filters( 'designsetgo_query_args', $args, $atts, $context );

		$query_id = isset( $context['query_id'] ) ? (string) $context['query_id'] : '';
		if ( '' !== $query_id ) {
			/** This filter is documented in src/blocks/query/render-posts.php */
			$args = apply_filters( 'designsetgo/query/' . $query_id . '/args', $args, $atts, $context );
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		// Separate call without pagination-affecting args to get total count.
		$count_args = $args;
		unset( $count_args['number'], $count_args['offset'], $count_args['orderby'], $count_args['order'] );
		$count_args['fields'] = 'count';
		$total_terms          = (int) get_terms( $count_args );

		$items_html = '';
		foreach ( (array) $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$items_html .= designsetgo_query_render_item(
				(string) $context['inner_html'],
				array(
					'designsetgo/currentItemId'   => (int) $term->term_id,
					'designsetgo/currentItemType' => 'term:' . $term->taxonomy,
				),
				$atts['itemTagName']
			);
		}

		$state = array(
			'totalItems' => $total_terms,
			'totalPages' => $per_page > 0 ? (int) ceil( $total_terms / $per_page ) : 0,
			'page'       => $page,
		);
		designsetgo_query_set_last_state( $query_id, $state );

		return array(
			'html'       => designsetgo_query_wrap( $items_html, $atts, $context, $context['wrapper_attrs'] ?? null ),
			'totalPages' => $state['totalPages'],
			'totalItems' => $state['totalItems'],
		);
	}

	/**
	 * Whitelist orderby values acceptable to get_terms().
	 */
	function designsetgo_query_sanitize_term_orderby( $orderby ) {
		$allowed = array( 'name', 'slug', 'term_group', 'term_id', 'id', 'description', 'parent', 'count', 'include', 'slug__in', 'meta_value', 'meta_value_num', 'none' );
		if ( 'date' === $orderby ) {
			return 'name'; // Terms don't have a date; default to alphabetical.
		}
		if ( 'title' === $orderby ) {
			return 'name';
		}
		return in_array( $orderby, $allowed, true ) ? $orderby : 'name';
	}

endif;

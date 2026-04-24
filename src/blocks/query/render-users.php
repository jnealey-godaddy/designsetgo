<?php
/**
 * Dynamic Query — Users source renderer.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_render_users' ) ) :

	function designsetgo_query_render_users( array $atts, array $context ) {
		$per_page = max( 1, (int) $atts['perPage'] );
		$page     = max( 1, (int) $context['page'] );

		$args = array(
			'number'  => $per_page,
			'offset'  => max( 0, (int) $atts['offset'] ) + ( ( $page - 1 ) * $per_page ),
			'orderby' => designsetgo_query_sanitize_user_orderby( (string) $atts['orderBy'] ),
			'order'   => 'ASC' === strtoupper( (string) $atts['order'] ) ? 'ASC' : 'DESC',
		);

		// Search — attribute-bound OR URL-bound via bindSearchTo.
		$search = (string) $atts['search'];
		if ( ! empty( $atts['bindSearchTo'] ) && isset( $context['params'][ $atts['bindSearchTo'] ] ) ) {
			$search = (string) $context['params'][ $atts['bindSearchTo'] ];
		}
		if ( '' !== $search ) {
			// Wildcard search like WP_User_Query default.
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'user_nicename', 'display_name' );
		}

		/** This filter is documented in src/blocks/query/render-posts.php */
		$args = apply_filters( 'designsetgo_query_args', $args, $atts, $context );

		$query_id = isset( $context['query_id'] ) ? (string) $context['query_id'] : '';
		if ( '' !== $query_id ) {
			/** This filter is documented in src/blocks/query/render-posts.php */
			$args = apply_filters( 'designsetgo/query/' . $query_id . '/args', $args, $atts, $context );
		}

		$query       = new WP_User_Query( $args );
		$users       = (array) $query->get_results();
		$total_users = (int) $query->get_total();

		$items_html = '';
		$item_index = 0;
		foreach ( $users as $user ) {
			$items_html .= designsetgo_query_render_item(
				(string) $context['inner_html'],
				array(
					'designsetgo/currentItemId'   => (int) $user->ID,
					'designsetgo/currentItemType' => 'user',
					'index'                       => $item_index,
					'designsetgo/itemIndex'       => $item_index,
				),
				$atts['itemTagName']
			);
			++$item_index;
		}

		$state = array(
			'totalItems' => $total_users,
			'totalPages' => $per_page > 0 ? (int) ceil( $total_users / $per_page ) : 0,
			'page'       => $page,
		);
		designsetgo_query_set_last_state( $query_id, $state );

		return array(
			'html'       => designsetgo_query_wrap( $items_html, $atts, $context, $context['wrapper_attrs'] ?? null ),
			'items_html' => $items_html,
			'totalPages' => $state['totalPages'],
			'totalItems' => $state['totalItems'],
		);
	}

	/**
	 * Whitelist orderby values acceptable to WP_User_Query.
	 *
	 * The block's `orderBy` attribute defaults to `date` (a post orderby), so
	 * map that to a sensible user equivalent.
	 */
	function designsetgo_query_sanitize_user_orderby( $orderby ) {
		$allowed = array( 'ID', 'id', 'user_registered', 'registered', 'display_name', 'name', 'login', 'user_login', 'nicename', 'user_nicename', 'email', 'user_email' );
		if ( 'date' === $orderby ) {
			return 'user_registered';
		}
		if ( 'title' === $orderby ) {
			return 'display_name';
		}
		return in_array( $orderby, $allowed, true ) ? $orderby : 'user_registered';
	}

endif;

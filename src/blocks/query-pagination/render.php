<?php
/**
 * Dynamic Query — Pagination sibling block render.
 *
 * Reads the parent's last-render state from the per-request registry
 * populated by designsetgo_query_render_posts/users/terms so we don't
 * re-execute the query.
 *
 * @package DesignSetGo
 * @since 2.1.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Unused (server-side rendered block).
 * @param WP_Block $block      Block instance (carries context).
 */

defined( 'ABSPATH' ) || exit;

$query_id = isset( $block->context['designsetgo/queryId'] )
	? sanitize_key( (string) $block->context['designsetgo/queryId'] )
	: '';

if ( '' === $query_id ) {
	return;
}

// Helpers must be loaded; they live in build/blocks/query/render-helpers.php.
$helpers = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
if ( ! file_exists( $helpers ) ) {
	return;
}
require_once $helpers;

$state = designsetgo_query_get_last_state( $query_id );
if ( ! $state || (int) $state['totalPages'] < 2 ) {
	return; // Single page — no pagination needed.
}

$pagination_mode = isset( $attributes['mode'] ) && 'loadmore' === $attributes['mode'] ? 'loadmore' : 'numbered'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$show_prev_next  = ! isset( $attributes['showPrevNext'] ) || (bool) $attributes['showPrevNext'];
$label_load_more = ! empty( $attributes['labelLoadMore'] )
	? (string) $attributes['labelLoadMore']
	: __( 'Load more', 'designsetgo' );

if ( 'loadmore' === $pagination_mode ) {
	$wrapper = get_block_wrapper_attributes(
		array(
			'class'                => 'dsgo-query-pagination dsgo-query-pagination--loadmore',
			'data-wp-interactive'  => 'designsetgo/query',
			'data-dsgo-query-id'   => $query_id,
			'data-dsgo-pagination' => 'loadmore',
		)
	);
	printf(
		'<div %1$s><button type="button" class="dsgo-query-pagination__loadmore" data-wp-on--click="actions.loadMore">%2$s</button></div>',
		$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output.
		esc_html( $label_load_more )
	);
	return;
}

// Numbered pagination.
$current = max( 1, (int) get_query_var( 'paged' ) );
if ( 1 === $current ) {
	// Singular-post paginator uses 'page' not 'paged'.
	$current = max( 1, (int) get_query_var( 'page' ) );
}

$links = paginate_links(
	array(
		'total'     => (int) $state['totalPages'],
		'current'   => $current,
		'type'      => 'array',
		'prev_next' => $show_prev_next,
	)
);

if ( empty( $links ) || ! is_array( $links ) ) {
	return;
}

$wrapper = get_block_wrapper_attributes(
	array(
		'class'              => 'dsgo-query-pagination dsgo-query-pagination--numbered',
		'role'               => 'navigation',
		'aria-label'         => __( 'Query pagination', 'designsetgo' ),
		'data-dsgo-query-id' => $query_id,
	)
);

echo '<nav ' . $wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output.
echo '<ul class="dsgo-query-pagination__list">';
foreach ( $links as $page_link ) {
	// paginate_links() output is already escaped by WordPress core.
	echo '<li class="dsgo-query-pagination__item">' . $page_link . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() escapes its output.
}
echo '</ul></nav>';

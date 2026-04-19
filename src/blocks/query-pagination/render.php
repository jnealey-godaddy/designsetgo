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

// Determine the effective pagination kind.
// paginationKind takes precedence (supports the 'infinite' variation);
// falls back to legacy 'mode' attribute for backwards compatibility.
$pagination_kind = isset( $attributes['paginationKind'] ) && 'numbered' !== $attributes['paginationKind']
	? $attributes['paginationKind']
	: ( isset( $attributes['mode'] ) ? $attributes['mode'] : 'numbered' );

// Infinite scroll: always render the sentinel even on a single-page result
// (the observer will simply never fire, which is the correct no-op behaviour).
if ( 'infinite' === $pagination_kind ) {
	$auto_pause    = isset( $attributes['autoPauseAfter'] ) ? (int) $attributes['autoPauseAfter'] : 3;
	$sentinel_offset = isset( $attributes['sentinelOffsetPx'] ) ? (int) $attributes['sentinelOffsetPx'] : 200;
	$btn_label     = ! empty( $attributes['buttonLabelWhenPaused'] )
		? (string) $attributes['buttonLabelWhenPaused']
		: __( 'Load more', 'designsetgo' );

	$inf_context = wp_json_encode(
		array(
			'queryId'       => $query_id,
			'autoLoadCount' => 0,
			'page'          => 1,
			'busy'          => false,
			'restUrl'       => esc_url_raw( rest_url( 'designsetgo/v1/query/render' ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
		),
		JSON_HEX_APOS
	);

	$wrapper = get_block_wrapper_attributes(
		array(
			'class'                    => 'dsgo-query-pagination dsgo-query-pagination--infinite',
			'data-wp-interactive'      => 'designsetgo/query',
			'data-dsgo-query-id'       => $query_id,
			'data-dsgo-pagination'     => 'infinite',
			'data-dsgo-auto-pause-after' => (string) $auto_pause,
			'data-dsgo-sentinel-offset'  => (string) $sentinel_offset,
		)
	);

	printf(
		'<div %1$s data-wp-context=\'%2$s\'>' .
		'<button type="button" class="dsgo-query-pagination__loadmore wp-element-button"' .
		' data-wp-on--click="actions.loadMore"' .
		' data-dsgo-label-idle="%3$s"' .
		' data-dsgo-label-loading="%4$s"' .
		' hidden>%5$s</button>' .
		'<div class="dsgo-query-pagination__sentinel" aria-hidden="true" data-wp-init="callbacks.initInfiniteObserver"></div>' .
		'</div>',
		$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output.
		$inf_context, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output inside single-quoted attr.
		esc_attr( $btn_label ),
		esc_attr__( 'Loading\u2026', 'designsetgo' ),
		esc_html( $btn_label )
	);
	return;
}

if ( ! $state || (int) $state['totalPages'] < 2 ) {
	return; // Single page — no pagination needed.
}

$pagination_mode = $pagination_kind; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$show_prev_next  = ! isset( $attributes['showPrevNext'] ) || (bool) $attributes['showPrevNext'];
$label_load_more = ! empty( $attributes['labelLoadMore'] )
	? (string) $attributes['labelLoadMore']
	: __( 'Load more', 'designsetgo' );
$label_loading   = ! empty( $attributes['labelLoading'] )
	? (string) $attributes['labelLoading']
	: __( 'Loading…', 'designsetgo' );

if ( 'loadmore' === $pagination_mode ) {
	// Seed the IAPI context on the pagination wrapper so `getContext()` inside
	// actions.loadMore resolves ctx.queryId / ctx.page / ctx.busy. Without this
	// the click handler would see an empty context and silently no-op.
	// JSON_HEX_APOS defends the single-quoted attr boundary against a stray
	// apostrophe in any future context value.
	$lm_context = wp_json_encode(
		array(
			'queryId' => $query_id,
			'page'    => 1,
			'busy'    => false,
			'restUrl' => esc_url_raw( rest_url( 'designsetgo/v1/query/render' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		),
		JSON_HEX_APOS
	);
	$wrapper = get_block_wrapper_attributes(
		array(
			'class'                => 'dsgo-query-pagination dsgo-query-pagination--loadmore',
			'data-wp-interactive'  => 'designsetgo/query',
			'data-dsgo-query-id'   => $query_id,
			'data-dsgo-pagination' => 'loadmore',
		)
	);
	printf(
		'<div %1$s data-wp-context=\'%5$s\'><button type="button" class="dsgo-query-pagination__loadmore wp-element-button" data-wp-on--click="actions.loadMore" data-dsgo-label-idle="%3$s" data-dsgo-label-loading="%4$s">%2$s</button></div>',
		$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output.
		esc_html( $label_load_more ),
		esc_attr( $label_load_more ),
		esc_attr( $label_loading ),
		$lm_context // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output inside single-quoted attr.
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

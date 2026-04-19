<?php
/**
 * Dynamic Query — first-paint render.
 *
 * Computes the outer wrapper attrs via get_block_wrapper_attributes() (which
 * auto-injects native-supports classes + user inline styles + anchor id +
 * custom className) and threads them through designsetgo_query_render so
 * the frontend <ul>/<ol>/<div> carries every inspector customization.
 *
 * The REST endpoint (designsetgo/v1/query/render) calls the same render
 * helper without wrapper attrs — it only produces items HTML appended
 * inside an already-rendered first-paint container.
 *
 * @package DesignSetGo
 * @since 2.1.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Serialized innerBlocks.
 * @param WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/render-helpers.php';

// Pagination — `paged` for archives, `page` for singular post paginator.
$dsgo_query_page = absint( get_query_var( 'paged' ) );
if ( ! $dsgo_query_page ) {
	$dsgo_query_page = absint( get_query_var( 'page' ) );
}
$dsgo_query_page = max( 1, $dsgo_query_page );

$dsgo_query_wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class' => 'dsgo-query dsgo-query--source-' . sanitize_key( (string) ( $attributes['source'] ?? 'posts' ) ),
	)
);

$dsgo_query_context = array(
	'query_id'      => isset( $attributes['queryId'] ) ? sanitize_key( (string) $attributes['queryId'] ) : '',
	'page'          => $dsgo_query_page,
	'inner_html'    => (string) $content,
	'params'        => designsetgo_query_extract_params_from_request(),
	'wrapper_attrs' => $dsgo_query_wrapper_attrs,
);

$dsgo_query_result = designsetgo_query_render( (array) $attributes, $dsgo_query_context );

echo $dsgo_query_result['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled in designsetgo_query_wrap() from esc_attr()-escaped parts + get_block_wrapper_attributes() output.

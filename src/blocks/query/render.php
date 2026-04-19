<?php
/**
 * Dynamic Query — first-paint render.
 *
 * Delegates to designsetgo_query_render_region() which:
 *  1. Splits the block's parsed innerBlocks into per-item template blocks
 *     and once-only sibling blocks (pagination / filter / no-results).
 *  2. Runs the query (populates the state registry).
 *  3. Renders sibling blocks ONCE, AFTER the query, so they can read
 *     totalPages / totalItems from the state registry.
 *  4. Wraps everything in <div class="dsgo-query-region"> — the JS
 *     refresh target so a filter/sort action can swap the whole region
 *     (list + pagination + no-results) in a single innerHTML assignment.
 *
 * @package DesignSetGo
 * @since 2.1.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Serialized innerBlocks (NOT used — we read
 *                             $block->parsed_block['innerBlocks'] instead
 *                             to avoid sibling-blocks being rendered per-item).
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

// Pre-compute wrapper attrs (first-paint path: carries native-supports classes,
// inline styles, anchor id, and user className from the block editor).
$dsgo_query_wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class' => 'dsgo-query dsgo-query--source-' . sanitize_key( (string) ( $attributes['source'] ?? 'posts' ) ),
	)
);

// Serialize ALL parsed innerBlocks into a WP comment string. The region helper
// will parse this string and split it into template vs. sibling blocks itself.
// Using parsed_block['innerBlocks'] (not $content) is the fix — $content is the
// pre-rendered HTML of ALL inner blocks (WordPress already ran render() on each),
// so using it would cause sibling blocks to appear inside each item iteration.
$dsgo_parsed_inner = isset( $block->parsed_block['innerBlocks'] )
	? (array) $block->parsed_block['innerBlocks']
	: array();

$dsgo_full_inner_html = '';
foreach ( $dsgo_parsed_inner as $dsgo_parsed_block ) {
	if ( ! empty( $dsgo_parsed_block['blockName'] ) ) {
		$dsgo_full_inner_html .= serialize_block( $dsgo_parsed_block );
	}
}

$dsgo_query_context = array(
	'query_id'      => isset( $attributes['queryId'] ) ? sanitize_key( (string) $attributes['queryId'] ) : '',
	'page'          => $dsgo_query_page,
	'inner_html'    => $dsgo_full_inner_html,
	'params'        => designsetgo_query_extract_params_from_request(),
	'wrapper_attrs' => $dsgo_query_wrapper_attrs,
);

$dsgo_region_result = designsetgo_query_render_region( (array) $attributes, $dsgo_query_context );

echo $dsgo_region_result['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled in designsetgo_query_render_region() from esc_attr()-escaped parts + block render() output.

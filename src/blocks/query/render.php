<?php
/**
 * Dynamic Query — first-paint render (v2.6 restructure).
 *
 * The block is now a pure container. Flow:
 *   1. Find the designsetgo/query-results child in parsed innerBlocks.
 *   2. Run the WP_Query once via designsetgo_query_render() using the
 *      parent's query attrs + the results child's presentation attrs
 *      (columns, tagName, groupBy…). Populate the state registry so
 *      pagination / no-results siblings can read totalPages / totalItems.
 *   3. Stash the rendered items HTML in $GLOBALS keyed by queryId so the
 *      query-results block's render.php picks it up when WordPress walks
 *      the tree and renders children.
 *   4. Render each child block in tree order via WP_Block so filters,
 *      pagination, and no-results appear exactly where the author placed
 *      them — no more "always at the bottom" behaviour.
 *
 * @package DesignSetGo
 * @since 2.6.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Ignored — we re-render children manually so
 *                             the results block can splice in its pre-
 *                             rendered items HTML via a global.
 * @param WP_Block $block      Block instance (carries parsed_block).
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/render-helpers.php';

if ( function_exists( 'wp_enqueue_script_module' ) ) {
	wp_enqueue_script_module( 'designsetgo-query-view-script-module' );
}

$dsgo_page = max( 1, absint( get_query_var( 'paged' ) ) );
if ( 1 === $dsgo_page ) {
	$dsgo_page = max( 1, absint( get_query_var( 'page' ) ) );
}

$dsgo_query_id = isset( $attributes['queryId'] ) ? sanitize_key( (string) $attributes['queryId'] ) : '';

$dsgo_parsed_children = isset( $block->parsed_block['innerBlocks'] )
	? (array) $block->parsed_block['innerBlocks']
	: array();

// Block wrapper attrs carry native-supports classes / styles / anchor id +
// author-supplied className. We append IAPI attrs inline so the region
// lives on a single element, giving view.js a clean swap target.
$dsgo_wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class' => 'dsgo-query dsgo-query-region dsgo-query--source-' . sanitize_key( (string) ( $attributes['source'] ?? 'posts' ) ),
	)
);

echo designsetgo_query_render_container( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from WP_Block->render() output + esc_attr()-escaped parts.
	(array) $attributes,
	$dsgo_parsed_children,
	$dsgo_page,
	$dsgo_query_id,
	$dsgo_wrapper_attrs,
	(array) ( $block->context ?? array() )
);

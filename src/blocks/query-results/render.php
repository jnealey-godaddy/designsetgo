<?php
/**
 * Dynamic Query Results — renders the item grid.
 *
 * This block lives inside designsetgo/query. The parent's render.php invokes
 * us during its custom rendering loop and supplies the pre-computed results
 * HTML via $GLOBALS['designsetgo_query_results_html'][ queryId ]. If we run
 * standalone (no parent context), we emit nothing — the block is not meant
 * to be used on its own.
 *
 * @package DesignSetGo
 * @since 2.6.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Unused — server-side rendered.
 * @param WP_Block $block      Block instance (carries context).
 */

defined( 'ABSPATH' ) || exit;

$query_id = isset( $block->context['designsetgo/queryId'] )
	? sanitize_key( (string) $block->context['designsetgo/queryId'] )
	: '';

if ( '' === $query_id ) {
	return;
}

// The parent Query block's render.php pre-renders the items grid and stashes
// the HTML here keyed by queryId. That lets the parent run a single WP_Query
// and populate the state registry (for sibling pagination / no-results)
// before any child block renders.
if ( isset( $GLOBALS['designsetgo_query_results_html'][ $query_id ] ) ) {
	echo $GLOBALS['designsetgo_query_results_html'][ $query_id ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by parent query block's render.php.
}

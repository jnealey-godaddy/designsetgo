<?php
/**
 * Dynamic Query — first-paint render.
 *
 * Delegates to the shared render helper (src/blocks/query/render-helpers.php)
 * so the REST endpoint (designsetgo/v1/query/render) and first-paint produce
 * byte-identical HTML.
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

$dsgo_query_context = array(
	'query_id'   => isset( $attributes['queryId'] ) ? sanitize_key( (string) $attributes['queryId'] ) : '',
	'page'       => max( 1, (int) get_query_var( 'paged' ) ),
	'inner_html' => (string) $content,
	'params'     => designsetgo_query_extract_params_from_request(),
);

$dsgo_query_result = designsetgo_query_render( (array) $attributes, $dsgo_query_context );

echo $dsgo_query_result['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- internally wrapped via get_block_wrapper_attributes().

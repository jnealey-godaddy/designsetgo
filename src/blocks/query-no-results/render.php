<?php
/**
 * Dynamic Query — No-results sibling block.
 *
 * Reads the parent's last-render state registry. When totalItems > 0, returns
 * empty (the items rendered, no fallback needed). When totalItems === 0,
 * returns the saved content unchanged so authors see their message.
 *
 * @package DesignSetGo
 * @since 2.1.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Serialized innerBlocks HTML (authored content).
 * @param WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$query_id = isset( $block->context['designsetgo/queryId'] )
	? sanitize_key( (string) $block->context['designsetgo/queryId'] )
	: '';

if ( '' === $query_id ) {
	return;
}

$helpers = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
if ( ! file_exists( $helpers ) ) {
	return;
}
require_once $helpers;

$state = designsetgo_query_get_last_state( $query_id );

// Only render when the parent query had zero items.
if ( ! $state || (int) $state['totalItems'] > 0 ) {
	return;
}

// $content already contains the authored innerBlocks HTML from save().
// Wrap with block-wrapper attrs so native supports apply.
$wrapper = get_block_wrapper_attributes(
	array( 'class' => 'dsgo-query-no-results' )
);
printf(
	'<div %1$s>%2$s</div>',
	$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- innerBlocks HTML is WP-sanitized on save.
);

<?php
/**
 * Query Group Header — server-side render.
 *
 * Wraps parsed inner content in the configured tag element.
 * The parent Query's group-by logic (Task D2) controls whether this block
 * is actually emitted for each group; this file only handles the HTML wrapper.
 *
 * @package DesignSetGo
 * @since   2.3.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Parsed inner content (WP has already rendered innerBlocks).
 * @param WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$allowed_tags = array( 'header', 'div', 'section' );
$tag          = isset( $attributes['tagName'] ) && in_array( $attributes['tagName'], $allowed_tags, true )
	? $attributes['tagName']
	: 'header';

$wrapper = get_block_wrapper_attributes( array( 'class' => 'dsgo-query-group-header' ) );

printf(
	'<%1$s %2$s>%3$s</%1$s>',
	$tag, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated against allowlist above.
	$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$content  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- innerBlocks HTML is WP-sanitized on save.
);

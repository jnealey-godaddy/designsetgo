<?php
/**
 * Dynamic Query — first render dispatcher (placeholder).
 *
 * Task 5 replaces this with the real renderer. Emits a harmless comment so
 * editors see the block inserted but nothing visible is broken on the
 * frontend.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

defined( 'ABSPATH' ) || exit;

$wrapper = get_block_wrapper_attributes( array( 'class' => 'dsgo-query' ) );
echo '<div ' . $wrapper . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	. '<!-- designsetgo/query: render pending (Task 5) -->'
	. '</div>';

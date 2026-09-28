<?php
/**
 * Countdown Timer Timezone
 *
 * The Countdown Timer's `timezone` attribute can be left empty, meaning
 * "use the WordPress site timezone" — that's what the inspector's
 * "WordPress Default" option and its help text promise. `targetDateTime`
 * itself is stored as a timezoneless wall-clock string (no UTC offset), so
 * something has to supply that site timezone before a visitor's browser can
 * turn it into an absolute instant. PHP is the only place `timezone_string`
 * / `gmt_offset` live — the frontend has no equivalent — so this stamps the
 * resolved value onto the rendered markup as a `data-site-timezone`
 * attribute, which `view.js` falls back to when the block's own `timezone`
 * attribute is empty.
 *
 * This only changes render_block output, never what's stored in the block
 * (save() and the block comment are untouched), so no deprecation is
 * needed.
 *
 * @package DesignSetGo
 * @since 2.9.0
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Countdown Timer Timezone handler.
 */
class Countdown_Timer_Timezone {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'render_block_designsetgo/countdown-timer', array( $this, 'inject_site_timezone' ) );
	}

	/**
	 * Stamp the resolved WordPress site timezone onto the rendered block.
	 *
	 * @param string $block_content The block content.
	 * @return string The block content, with `data-site-timezone` set on
	 *                 the timer wrapper.
	 */
	public function inject_site_timezone( $block_content ) {
		if ( ! is_string( $block_content ) || '' === trim( $block_content ) ) {
			return $block_content;
		}

		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );

		if ( ! $processor->next_tag( array( 'class_name' => 'dsgo-countdown-timer' ) ) ) {
			return $block_content;
		}

		$processor->set_attribute( 'data-site-timezone', wp_timezone()->getName() );

		return $processor->get_updated_html();
	}
}

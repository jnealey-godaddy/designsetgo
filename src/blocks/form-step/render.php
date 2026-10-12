<?php
/**
 * Form Step — server-side render.
 *
 * Wraps the step's fields in a labelled group (not a region landmark per
 * step). Without JavaScript every step shows stacked under its heading; the
 * view script turns them into one-at-a-time steps.
 *
 * @package DesignSetGo
 * @since 2.10.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Inner blocks HTML.
 * @param WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_render_form_step' ) ) {
	/**
	 * Render the Form Step block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner blocks HTML.
	 * @param WP_Block $block      Block instance.
	 * @return void
	 */
	function designsetgo_render_form_step( $attributes, $content, $block ) {
		$number = class_exists( '\DesignSetGo\Blocks\Form_Step_Counter' ) ? \DesignSetGo\Blocks\Form_Step_Counter::next() : 1;

		// The editor's RichText stores HTML. Tags are stripped here; the
		// entities it stores (`Q&amp;A`) survive esc_html() below unchanged,
		// as it does not double-encode.
		$title = isset( $attributes['title'] ) ? trim( wp_strip_all_tags( (string) $attributes['title'] ) ) : '';
		if ( '' === $title ) {
			/* translators: %d: step number */
			$title = sprintf( __( 'Step %d', 'designsetgo' ), $number );
		}
		// wp_unique_id() restarts every request, so a stepped form fetched
		// later over REST (Query load more / refresh) would repeat ids already
		// on the page. A per-request salt keeps them apart.
		static $salt = null;
		$salt       = $salt ?? substr( wp_hash( uniqid( '', true ) ), 0, 6 );
		$heading_id = wp_unique_id( 'dsgo-form-step-' . $salt . '-' );

		$wrapper = get_block_wrapper_attributes(
			array(
				'class'           => 'dsgo-form-step',
				'data-dsgo-step'  => (string) $number,
				'role'            => 'group',
				'aria-labelledby' => $heading_id,
			)
		);

		printf(
			'<div %1$s><h3 class="dsgo-form-step__title" id="%2$s" tabindex="-1">%3$s</h3><div class="dsgo-form-step__fields">%4$s</div></div>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes.
			esc_attr( $heading_id ),
			esc_html( $title ),
			$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner field blocks render their own escaped markup.
		);
	}
}

designsetgo_render_form_step( $attributes, $content, $block );

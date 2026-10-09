<?php
/**
 * Form Step — server-side render.
 *
 * Wraps the step's fields in a labelled section. Without JavaScript every
 * step shows stacked, so the headings double as section headings; the view
 * script turns them into one-at-a-time steps.
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
		$title  = isset( $attributes['title'] ) ? trim( wp_strip_all_tags( (string) $attributes['title'] ) ) : '';
		if ( '' === $title ) {
			/* translators: %d: step number */
			$title = sprintf( __( 'Step %d', 'designsetgo' ), $number );
		}
		$heading_id = wp_unique_id( 'dsgo-form-step-' );

		$wrapper = get_block_wrapper_attributes(
			array(
				'class'           => 'dsgo-form-step',
				'data-dsgo-step'  => (string) $number,
				'aria-labelledby' => $heading_id,
			)
		);

		printf(
			'<section %1$s><h3 class="dsgo-form-step__title" id="%2$s" tabindex="-1">%3$s</h3><div class="dsgo-form-step__fields">%4$s</div></section>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes.
			esc_attr( $heading_id ),
			esc_html( $title ),
			$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner field blocks render their own escaped markup.
		);
	}
}

designsetgo_render_form_step( $attributes, $content, $block );

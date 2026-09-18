<?php
/**
 * Serializer for designsetgo/pill.
 *
 * Mirrors the block's save() so markup written by the Abilities API is markup
 * the editor accepts. Extracted verbatim from Block_Inserter's switch; the
 * attribute matrix pins the output byte-for-byte.
 *
 * Handles:
 *   - designsetgo/pill
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pill_Serializer.
 */
class Pill_Serializer {

	/**
	 * Build the block's opening and closing markup.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Opening and closing markup, or null.
	 */
	public static function wrapper( string $block_name, array $attributes ): ?array {
		// Dead branch: Pill is a dynamic block (render.php), so
		// is_dynamic_block() keeps this switch from ever being
		// reached for it (see convert_to_block_array() above) — the
		// block always serializes to a bare comment and is rendered
		// server-side. Kept in sync with the current markup anyway
		// (rather than deleted) in case that gate is ever revisited;
		// `align` was removed when `justification` replaced it, and
		// the pre-dynamic `wp-block-designsetgo-pill align{value}
		// dsgo-pill has-small-font-size` shape below is stale.
		$content       = isset( $attributes['content'] ) ? $attributes['content'] : '';
		$justification = isset( $attributes['justification'] )
			? $attributes['justification']
			: ( isset( $attributes['align'] ) ? $attributes['align'] : 'center' );
		if ( ! in_array( $justification, array( 'left', 'center', 'right' ), true ) ) {
			$justification = 'center';
		}

		$class_parts = array( 'wp-block-designsetgo-pill', 'dsgo-pill', 'dsgo-justify', 'dsgo-justify--' . $justification );

		$inner_html = '<span class="dsgo-pill__content">' . wp_kses_post( $content ) . '</span>';

		return array(
			'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '">' . $inner_html,
			'closing' => '</div>',
		);
	}
}

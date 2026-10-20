<?php
/**
 * Applies a Timeline's marker settings to its items at render time.
 *
 * Marker style, marker colour and marker border colour are set on the parent
 * Timeline and reach each Timeline Item through block context. The editor
 * renders the marker from that context, but WordPress passes no context to a
 * static save(), so every stored marker is a primary-blue circle and the
 * settings never showed on the front end. Context *is* available to
 * render_block through the block instance, so this redraws the stored marker
 * shape the way timeline-item/edit.js does. Stored markup is untouched: no
 * deprecation, and existing content is fixed without a re-save.
 *
 * Keep in step with src/blocks/timeline-item/save.js (and edit.js).
 *
 * @package DesignSetGo
 * @since 2.11.1
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * Render-time Timeline Item marker styles.
 */
class Timeline_Context_Styles {

	/**
	 * Context key prefix provided by designsetgo/timeline.
	 */
	const CONTEXT = 'designsetgo/timeline/';

	/**
	 * Fallback marker colour, as in save.js / edit.js.
	 */
	const DEFAULT_COLOR = 'var(--wp--preset--color--primary, #2563eb)';

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'render_block_designsetgo/timeline-item', array( $this, 'apply' ), 10, 3 );
	}

	/**
	 * Apply the parent timeline's marker settings to one rendered item.
	 *
	 * @param string         $block_content Rendered item HTML.
	 * @param array          $block         Parsed block.
	 * @param \WP_Block|null $instance      Block instance carrying context.
	 * @return string Possibly-modified HTML.
	 */
	public function apply( $block_content, $block, $instance = null ) {
		if ( ! $instance instanceof \WP_Block || '' === trim( (string) $block_content ) ) {
			return $block_content;
		}

		$context = (array) $instance->context;
		$style   = $this->context_string( $context, 'markerStyle' );
		$style   = in_array( $style, array( 'square', 'diamond' ), true ) ? $style : 'circle';
		$parent  = Icon_List_Context_Styles::color_to_css( $this->context_string( $context, 'markerColor' ) );
		$border  = Icon_List_Context_Styles::color_to_css( $this->context_string( $context, 'markerBorderColor' ) );
		$attrs   = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$custom  = isset( $attrs['customMarkerColor'] ) && is_string( $attrs['customMarkerColor'] )
			? Icon_List_Context_Styles::color_to_css( trim( $attrs['customMarkerColor'] ) )
			: '';

		// The defaults are exactly what save() stored: nothing to do.
		if ( 'circle' === $style && '' === $parent && '' === $border && '' === $custom ) {
			return $block_content;
		}

		$fill   = '' !== $custom ? $custom : ( '' !== $parent ? $parent : self::DEFAULT_COLOR );
		$stroke = '' !== $border ? $border : $fill;

		// Marker image: the corner radius follows the style.
		if ( 'circle' !== $style && false !== strpos( $block_content, 'dsgo-timeline-item__marker-image' ) ) {
			return str_replace( 'border-radius:50%', 'border-radius:4px', $block_content );
		}

		$shape = $this->shape( $style, $fill, $stroke );

		$pattern = '#(<svg\b[^>]*>)\s*<(circle|rect)\b[^>]*?(?:/>|>\s*</\2>)\s*(</svg>)#';
		$updated = preg_replace_callback(
			$pattern,
			static function ( $m ) use ( $shape ) {
				return $m[1] . $shape . $m[3];
			},
			$block_content,
			1
		);

		return is_string( $updated ) ? $updated : $block_content;
	}

	/**
	 * Marker shape markup, mirroring MarkerShapes in timeline-item/edit.js.
	 *
	 * @param string $style  circle|square|diamond.
	 * @param string $fill   CSS fill colour.
	 * @param string $stroke CSS stroke colour.
	 * @return string SVG child element.
	 */
	private function shape( $style, $fill, $stroke ) {
		$paint = ' fill="' . esc_attr( $fill ) . '" stroke="' . esc_attr( $stroke ) . '" stroke-width="2"';
		if ( 'square' === $style ) {
			return '<rect x="2" y="2" width="20" height="20" rx="2"' . $paint . '/>';
		}
		if ( 'diamond' === $style ) {
			return '<rect x="12" y="2" width="14" height="14" rx="2" transform="rotate(45 12 2)"' . $paint . '/>';
		}
		return '<circle cx="12" cy="12" r="10"' . $paint . '/>';
	}

	/**
	 * A string context value, or ''.
	 *
	 * @param array  $context Block context.
	 * @param string $key     Key without the provider prefix.
	 * @return string
	 */
	private function context_string( array $context, $key ) {
		$value = isset( $context[ self::CONTEXT . $key ] ) ? $context[ self::CONTEXT . $key ] : '';
		return is_string( $value ) ? trim( $value ) : '';
	}
}

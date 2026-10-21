<?php
/**
 * Applies an Icon List's item settings to its items at render time.
 *
 * Icon size, colour, background, position and vertical alignment are set on
 * the parent Icon List and reach each Icon List Item through block context.
 * The editor renders items from that context, but WordPress passes no context
 * to a static save(), so every stored item carries the defaults (icon left, no
 * colour, inherited size). On the front end the settings therefore never
 * showed. Context *is* available to render_block through the block instance,
 * so this mirrors the context-driven part of icon-list-item/save.js there.
 * Stored markup is untouched: no deprecation, and existing content is fixed
 * without a re-save.
 *
 * Keep in step with src/blocks/icon-list-item/save.js (and edit.js).
 *
 * @package DesignSetGo
 * @since 2.11.1
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * Render-time Icon List Item context styles.
 */
class Icon_List_Context_Styles {

	/**
	 * Context key prefix provided by designsetgo/icon-list.
	 */
	const CONTEXT = 'designsetgo/iconList/';

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'render_block_designsetgo/icon-list-item', array( $this, 'apply' ), 10, 3 );
	}

	/**
	 * Apply the parent list's settings to one rendered item.
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

		$context  = (array) $instance->context;
		$position = $this->context_string( $context, 'iconPosition' );
		$position = in_array( $position, array( 'left', 'right', 'top' ), true ) ? $position : 'left';
		$valign   = 'center' === $this->context_string( $context, 'iconVerticalAlignment' ) ? 'center' : 'top';
		$color    = self::color_to_css( $this->context_string( $context, 'iconColor' ) );
		$bg       = self::color_to_css( $this->context_string( $context, 'iconBackgroundColor' ) );
		$size     = isset( $context[ self::CONTEXT . 'iconSize' ] ) && is_numeric( $context[ self::CONTEXT . 'iconSize' ] )
			? (float) $context[ self::CONTEXT . 'iconSize' ]
			: null;

		// The defaults are exactly what save() stored: nothing to do.
		if ( 'left' === $position && 'top' === $valign && '' === $color && '' === $bg && null === $size ) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );

		// Item wrapper (<div> or <a>).
		if ( ! $processor->next_tag() || ! $processor->has_class( 'dsgo-icon-list-item' ) ) {
			return $block_content;
		}
		$processor->remove_class( 'dsgo-icon-list-item--icon-left' );
		$processor->add_class( 'dsgo-icon-list-item--icon-' . $position );
		if ( 'top' === $position ) {
			$align_items = 'center';
		} else {
			$align_items = 'center' === $valign ? 'center' : 'flex-start';
		}
		$direction = array(
			'left'  => 'row',
			'right' => 'row-reverse',
			'top'   => 'column',
		);
		$this->merge_style(
			$processor,
			array(
				'display'        => 'flex',
				'flex-direction' => $direction[ $position ],
				'align-items'    => $align_items,
			)
		);

		// Icon box.
		if ( $processor->next_tag( array( 'class_name' => 'dsgo-icon-list-item__icon' ) ) ) {
			$icon_styles = array();
			if ( null !== $size ) {
				$box = ( '' !== $bg ? $size + 16 : $size ) . 'px';
				$processor->remove_class( 'dsgo-icon-list-item__icon--inherit-size' );
				$icon_styles['width']     = $box;
				$icon_styles['height']    = $box;
				$icon_styles['min-width'] = $box;
			}
			if ( '' !== $bg ) {
				$icon_styles['background-color'] = $bg;
				$icon_styles['padding']          = '8px';
				$icon_styles['border-radius']    = '4px';
				$icon_styles['box-sizing']       = 'border-box';
			}
			if ( '' !== $color ) {
				$icon_styles['color']             = $color;
				$icon_styles['--dsgo-icon-color'] = $color;
			}
			if ( $icon_styles ) {
				$this->merge_style( $processor, $icon_styles );
			}
		}

		// Content column.
		if ( 'left' !== $position && $processor->next_tag( array( 'class_name' => 'dsgo-icon-list-item__content' ) ) ) {
			$this->merge_style( $processor, array( 'text-align' => 'top' === $position ? 'center' : 'right' ) );
		}

		return $processor->get_updated_html();
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

	/**
	 * Set declarations on the current tag's style attribute, replacing any
	 * existing declaration of the same property and keeping the rest.
	 *
	 * @param \WP_HTML_Tag_Processor $processor    Processor positioned on a tag.
	 * @param array<string,string>   $declarations Property => value.
	 */
	private function merge_style( \WP_HTML_Tag_Processor $processor, array $declarations ) {
		$existing = $processor->get_attribute( 'style' );
		$merged   = array();
		if ( is_string( $existing ) ) {
			foreach ( explode( ';', $existing ) as $declaration ) {
				$parts = explode( ':', $declaration, 2 );
				if ( 2 === count( $parts ) && '' !== trim( $parts[0] ) ) {
					$merged[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
				}
			}
		}
		foreach ( $declarations as $property => $value ) {
			// Values are px numbers, fixed keywords, or colours from
			// color_to_css(); never let one close the declaration.
			$merged[ $property ] = str_replace( array( ';', '"', '<', '>' ), '', $value );
		}
		$css = '';
		foreach ( $merged as $property => $value ) {
			$css .= $property . ':' . $value . ';';
		}
		$processor->set_attribute( 'style', $css );
	}

	/**
	 * Convert a colour attribute to CSS, mirroring convertColorToCSSVar()
	 * (src/utils/convert-preset-to-css-var.js).
	 *
	 * @param string $value Colour value: CSS, `var:preset|color|slug`, or a bare slug.
	 * @return string CSS value, or '' when unset.
	 */
	public static function color_to_css( $value ) {
		if ( '' === $value ) {
			return '';
		}
		if ( 0 === strpos( $value, 'var(--' ) ) {
			return $value;
		}
		if ( 0 === strpos( $value, 'var:preset|' ) ) {
			return 'var(--wp--preset--' . implode( '--', explode( '|', substr( $value, strlen( 'var:preset|' ) ) ) ) . ')';
		}
		if ( preg_match( '/^(#|rgb|hsl|hwb|lab|lch|oklch|oklab|color\(|var\(|url\(|\d)/i', $value )
			|| in_array( strtolower( $value ), array( 'transparent', 'inherit', 'initial', 'unset', 'revert', 'revert-layer', 'currentcolor', 'none', 'auto', 'normal' ), true )
		) {
			return $value;
		}
		return 'var(--wp--preset--color--' . sanitize_html_class( $value ) . ')';
	}
}

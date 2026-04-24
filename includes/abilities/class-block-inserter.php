<?php
/**
 * Block Inserter helper for DesignSetGo abilities.
 *
 * Provides common functionality for inserting blocks into posts,
 * including validation, positioning, and inner blocks handling.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.0.0
 */

namespace DesignSetGo\Abilities;

use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Inserter helper class.
 */
class Block_Inserter {

	/**
	 * Insert a block into a post at the specified position.
	 *
	 * @param int                              $post_id Post ID.
	 * @param string                           $block_name Block name (e.g., 'designsetgo/row').
	 * @param array<string, mixed>             $attributes Block attributes.
	 * @param array<int, array<string, mixed>> $inner_blocks Inner blocks.
	 * @param int                              $position Position to insert (-1 for append, 0 for prepend, or specific index).
	 * @return array<string, mixed>|WP_Error Success data or error.
	 */
	public static function insert_block( int $post_id, string $block_name, array $attributes = array(), array $inner_blocks = array(), int $position = -1 ) {
		// Validate post.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'invalid_post',
				__( 'Post not found.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'permission_denied',
				__( 'You do not have permission to edit this post.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		// Build block markup.
		$block_markup = self::build_block_markup( $block_name, $attributes, $inner_blocks );

		// Parse existing blocks.
		$blocks = parse_blocks( $post->post_content );

		// Parse new block.
		$new_block = parse_blocks( $block_markup )[0];

		// Insert at position.
		if ( -1 === $position ) {
			// Append to end.
			$blocks[] = $new_block;
		} elseif ( 0 === $position ) {
			// Prepend to beginning.
			array_unshift( $blocks, $new_block );
		} else {
			// Insert at specific index.
			array_splice( $blocks, $position, 0, array( $new_block ) );
		}

		// Serialize blocks back to content.
		$content = serialize_blocks( $blocks );

		// Update post.
		$updated = wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return array(
			'success'  => true,
			'post_id'  => $post->ID,
			'block_id' => wp_unique_id( 'block-' ),
			'position' => $position,
			'note'     => 'Blocks inserted successfully. Open the post in the WordPress editor to validate and save the blocks.',
		);
	}

	/**
	 * Build block markup from block name, attributes, and inner blocks.
	 *
	 * @param string                           $block_name Block name.
	 * @param array<string, mixed>             $attributes Block attributes.
	 * @param array<int, array<string, mixed>> $inner_blocks Inner blocks.
	 * @return string Block markup.
	 */
	public static function build_block_markup( string $block_name, array $attributes = array(), array $inner_blocks = array() ): string {
		// Convert simplified block structure to WordPress block array format.
		$block = self::convert_to_block_array( $block_name, $attributes, $inner_blocks );

		// Use WordPress's native serialize_block function.
		return serialize_block( $block );
	}

	/**
	 * Convert simplified block structure to WordPress block array format.
	 *
	 * Handles extraction of innerHTML from 'content' attribute for core blocks.
	 *
	 * @param string                           $block_name Block name.
	 * @param array<string, mixed>             $attributes Block attributes.
	 * @param array<int, array<string, mixed>> $inner_blocks Inner blocks.
	 * @return array<string, mixed> WordPress block array.
	 */
	private static function convert_to_block_array( string $block_name, array $attributes = array(), array $inner_blocks = array() ): array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- WordPress block format requires camelCase.
		$innerHTML     = '';
		$innerContent  = array();
		$parsed_inners = array();

		// Coerce attribute types and normalize defaults.
		$attrs = self::coerce_attribute_types( $block_name, $attributes );
		$attrs = self::normalize_block_attributes( $block_name, $attrs );
		// Apply block.json defaults so the HTML we build matches what save()
		// would emit from the same parsed attributes, preventing block
		// validation failures on first edit.
		$attrs = self::apply_block_json_defaults( $block_name, $attrs );

		// Convert CSS var() syntax to WordPress shorthand in style attribute.
		if ( isset( $attrs['style'] ) && is_array( $attrs['style'] ) ) {
			$attrs['style'] = self::convert_style_vars( $attrs['style'] );
		}
		if ( isset( $attrs['content'] ) && 0 === strpos( $block_name, 'core/' ) ) {
			$content = $attrs['content'];
			unset( $attrs['content'] );

			// Generate innerHTML based on block type.
			$innerHTML      = self::generate_core_block_html( $block_name, $content, $attrs );
			$innerContent[] = $innerHTML;
		}

		// Process inner blocks recursively.
		if ( ! empty( $inner_blocks ) ) {
			foreach ( $inner_blocks as $inner ) {
				$inner_name       = $inner['name'] ?? '';
				$inner_attributes = $inner['attributes'] ?? array();
				$inner_inner      = $inner['innerBlocks'] ?? array();

				if ( $inner_name ) {
					$parsed_inners[] = self::convert_to_block_array( $inner_name, $inner_attributes, $inner_inner );
					$innerContent[]  = null; // Placeholder for inner block.
				}
			}
		}

		// Generate HTML for DesignSetGo blocks.
		// Skip dynamic blocks (those with render callbacks) - they'll be rendered server-side.
		if ( 0 === strpos( $block_name, 'designsetgo/' ) && ! self::is_dynamic_block( $block_name ) ) {
			$wrapper_html = self::generate_designsetgo_wrapper_html( $block_name, $attrs );
			if ( ! empty( $wrapper_html ) ) {
				if ( ! empty( $inner_blocks ) ) {
					// Blocks with inner blocks: add opening/closing wrapper around inner blocks.
					array_unshift( $innerContent, $wrapper_html['opening'] );
					$innerContent[] = $wrapper_html['closing'];
					$innerHTML      = $wrapper_html['opening'] . $wrapper_html['closing'];
				} else {
					// Blocks without inner blocks: set full HTML as innerHTML.
					$innerHTML      = $wrapper_html['opening'] . $wrapper_html['closing'];
					$innerContent[] = $innerHTML;
				}
			}
		}

		// Generate HTML for standalone DesignSetGo blocks (form fields, etc.).
		// Only runs if wrapper HTML was not generated above.
		if ( 0 === strpos( $block_name, 'designsetgo/' ) && empty( $inner_blocks ) && empty( $innerHTML ) && ! self::is_dynamic_block( $block_name ) ) {
			$block_html = self::generate_designsetgo_block_html( $block_name, $attrs );
			if ( ! empty( $block_html ) ) {
				$innerHTML      = $block_html;
				$innerContent[] = $block_html;
			}
		}

		// Strip attributes that match block.json defaults so serialize_block
		// doesn't include them in the block comment (WordPress omits defaults).
		$attrs = self::strip_default_attributes( $block_name, $attrs );

		return array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => $parsed_inners,
			'innerHTML'    => $innerHTML,
			'innerContent' => ! empty( $innerContent ) ? $innerContent : array( $innerHTML ),
		);
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
	}

	/**
	 * Generate wrapper HTML for DesignSetGo blocks.
	 *
	 * Creates opening and closing HTML that approximates the block's save output.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string>|null Array with 'opening' and 'closing' keys, or null if not supported.
	 */
	private static function generate_designsetgo_wrapper_html( string $block_name, array $attributes ): ?array {
		$block_slug  = str_replace( 'designsetgo/', '', $block_name );
		$block_class = 'wp-block-designsetgo-' . $block_slug . ' dsgo-' . $block_slug;

		switch ( $block_name ) {
			case 'designsetgo/section':
				$constrain_width = isset( $attributes['constrainWidth'] ) ? $attributes['constrainWidth'] : true;
				$content_width   = isset( $attributes['contentWidth'] ) ? $attributes['contentWidth'] : '';
				$align           = isset( $attributes['align'] ) ? $attributes['align'] : 'full';
				$tag_name        = isset( $attributes['tagName'] ) && $attributes['tagName'] ? $attributes['tagName'] : 'div';

				// Build outer classes (order: wp-block-*, alignX, dsgo-*).
				$outer_class_parts = array( 'wp-block-designsetgo-section' );
				if ( 'full' === $align ) {
					$outer_class_parts[] = 'alignfull';
				} elseif ( 'wide' === $align ) {
					$outer_class_parts[] = 'alignwide';
				}
				$outer_class_parts[] = 'dsgo-stack';
				if ( ! $constrain_width ) {
					$outer_class_parts[] = 'dsgo-no-width-constraint';
				}

				// Process block support styles (colors, padding, etc.).
				$style             = isset( $attributes['style'] ) ? $attributes['style'] : array();
				$support_result    = self::get_block_support_styles( $style );
				$outer_class_parts = array_merge( $outer_class_parts, $support_result['classes'] );

				// Build outer inline styles: block support styles first, then default padding as fallback.
				$outer_styles = $support_result['styles'];
				if ( empty( $style['spacing']['padding'] ) ) {
					// Use default padding when none specified in style.
					$outer_styles[] = 'padding-top:var(--wp--preset--spacing--50)';
					$outer_styles[] = 'padding-right:var(--wp--preset--spacing--30)';
					$outer_styles[] = 'padding-bottom:var(--wp--preset--spacing--50)';
					$outer_styles[] = 'padding-left:var(--wp--preset--spacing--30)';
				}

				// Inner div always has max-width/margin for content centering.
				$max_width   = $content_width ? $content_width : 'var(--wp--style--global--content-size, 1140px)';
				$inner_style = 'max-width:' . esc_attr( $max_width ) . ';margin-left:auto;margin-right:auto';

				return array(
					'opening' => '<' . esc_attr( $tag_name ) . ' class="' . esc_attr( implode( ' ', $outer_class_parts ) ) . '" style="' . esc_attr( implode( ';', $outer_styles ) ) . '"><div class="dsgo-stack__inner" style="' . esc_attr( $inner_style ) . '">',
					'closing' => '</div></' . esc_attr( $tag_name ) . '>',
				);

			case 'designsetgo/row':
				$constrain_width = isset( $attributes['constrainWidth'] ) ? $attributes['constrainWidth'] : false;
				$content_width   = isset( $attributes['contentWidth'] ) ? $attributes['contentWidth'] : '';
				$mobile_stack    = isset( $attributes['mobileStack'] ) ? $attributes['mobileStack'] : false;
				$align           = isset( $attributes['align'] ) ? $attributes['align'] : 'full';
				$layout          = isset( $attributes['layout'] ) ? $attributes['layout'] : array();
				$justify_content = isset( $layout['justifyContent'] ) ? $layout['justifyContent'] : 'left';
				$flex_wrap       = isset( $layout['flexWrap'] ) ? $layout['flexWrap'] : 'nowrap';

				// Build outer classes (order: wp-block-*, alignX, dsgo-*).
				$outer_class_parts = array( 'wp-block-designsetgo-row' );
				if ( 'full' === $align ) {
					$outer_class_parts[] = 'alignfull';
				} elseif ( 'wide' === $align ) {
					$outer_class_parts[] = 'alignwide';
				}
				$outer_class_parts[] = 'dsgo-flex';
				if ( $mobile_stack ) {
					$outer_class_parts[] = 'dsgo-flex--mobile-stack';
				}
				if ( ! $constrain_width ) {
					$outer_class_parts[] = 'dsgo-no-width-constraint';
				}

				// Default padding from block supports.
				$default_padding = 'padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)';

				// Inner div styles with gap.
				$inner_styles = array(
					'display:flex',
					'justify-content:' . esc_attr( $justify_content ),
					'flex-wrap:' . esc_attr( $flex_wrap ),
					'gap:var(--wp--preset--spacing--30)',
				);
				if ( $constrain_width ) {
					$max_width      = $content_width ? $content_width : 'var(--wp--style--global--content-size, 1140px)';
					$inner_styles[] = 'max-width:' . esc_attr( $max_width );
					$inner_styles[] = 'margin-left:auto';
					$inner_styles[] = 'margin-right:auto';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $outer_class_parts ) ) . '" style="' . esc_attr( $default_padding ) . '"><div class="dsgo-flex__inner" style="' . esc_attr( implode( ';', $inner_styles ) ) . '">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/grid':
				$desktop_cols    = isset( $attributes['desktopColumns'] ) ? intval( $attributes['desktopColumns'] ) : 3;
				$tablet_cols     = isset( $attributes['tabletColumns'] ) ? intval( $attributes['tabletColumns'] ) : 2;
				$mobile_cols     = isset( $attributes['mobileColumns'] ) ? intval( $attributes['mobileColumns'] ) : 1;
				$align_items     = isset( $attributes['alignItems'] ) ? $attributes['alignItems'] : 'stretch';
				$constrain_width = isset( $attributes['constrainWidth'] ) ? $attributes['constrainWidth'] : false;
				$content_width   = isset( $attributes['contentWidth'] ) ? $attributes['contentWidth'] : '';
				$align           = isset( $attributes['align'] ) ? $attributes['align'] : 'full';

				// Build outer classes (order: wp-block-*, alignX, dsgo-*).
				$outer_class_parts = array( 'wp-block-designsetgo-grid' );
				if ( 'full' === $align ) {
					$outer_class_parts[] = 'alignfull';
				} elseif ( 'wide' === $align ) {
					$outer_class_parts[] = 'alignwide';
				}
				$outer_class_parts[] = 'dsgo-grid';
				$outer_class_parts[] = 'dsgo-grid-cols-' . $desktop_cols;
				$outer_class_parts[] = 'dsgo-grid-cols-tablet-' . $tablet_cols;
				$outer_class_parts[] = 'dsgo-grid-cols-mobile-' . $mobile_cols;
				if ( ! $constrain_width ) {
					$outer_class_parts[] = 'dsgo-no-width-constraint';
				}

				// Default padding from block supports.
				$default_padding = 'padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)';

				// Inner div styles.
				$default_gap  = 'var(--wp--preset--spacing--50)';
				$inner_styles = array(
					'display:grid',
					'grid-template-columns:repeat(' . $desktop_cols . ', 1fr)',
					'align-items:' . esc_attr( $align_items ),
					'row-gap:' . $default_gap,
					'column-gap:' . $default_gap,
				);
				if ( $constrain_width ) {
					$max_width      = $content_width ? $content_width : 'var(--wp--style--global--content-size, 1140px)';
					$inner_styles[] = 'max-width:' . esc_attr( $max_width );
					$inner_styles[] = 'margin-left:auto';
					$inner_styles[] = 'margin-right:auto';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $outer_class_parts ) ) . '" style="' . esc_attr( $default_padding ) . '"><div class="dsgo-grid__inner" style="' . esc_attr( implode( ';', $inner_styles ) ) . '">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/counter-group':
				$desktop_cols = isset( $attributes['desktopColumns'] ) ? intval( $attributes['desktopColumns'] ) : 3;
				$tablet_cols  = isset( $attributes['tabletColumns'] ) ? intval( $attributes['tabletColumns'] ) : 2;
				$mobile_cols  = isset( $attributes['mobileColumns'] ) ? intval( $attributes['mobileColumns'] ) : 1;
				$gap          = isset( $attributes['gap'] ) ? intval( $attributes['gap'] ) : 32;
				$duration     = isset( $attributes['animationDuration'] ) ? floatval( $attributes['animationDuration'] ) : 2;
				$delay        = isset( $attributes['animationDelay'] ) ? floatval( $attributes['animationDelay'] ) : 0;
				$easing       = isset( $attributes['animationEasing'] ) ? $attributes['animationEasing'] : 'easeOutQuad';
				$use_grouping = isset( $attributes['useGrouping'] ) ? $attributes['useGrouping'] : true;
				$separator    = isset( $attributes['separator'] ) ? $attributes['separator'] : ',';
				$decimal      = isset( $attributes['decimal'] ) ? $attributes['decimal'] : '.';
				$align        = isset( $attributes['alignment'] ) ? $attributes['alignment'] : 'center';

				$outer_style = 'align-self:stretch;--dsgo-counter-columns-desktop:' . (string) $desktop_cols . ';--dsgo-counter-columns-tablet:' . (string) $tablet_cols . ';--dsgo-counter-columns-mobile:' . (string) $mobile_cols . ';--dsgo-counter-gap:' . (string) $gap . 'px';

				$data_attrs  = ' data-animation-duration="' . esc_attr( (string) $duration ) . '"';
				$data_attrs .= ' data-animation-delay="' . esc_attr( (string) $delay ) . '"';
				$data_attrs .= ' data-animation-easing="' . esc_attr( $easing ) . '"';
				$data_attrs .= ' data-use-grouping="' . ( $use_grouping ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-separator="' . esc_attr( $separator ) . '"';
				$data_attrs .= ' data-decimal="' . esc_attr( $decimal ) . '"';

				return array(
					'opening' => '<div class="wp-block-designsetgo-counter-group dsgo-counter-group" style="' . esc_attr( $outer_style ) . '"' . $data_attrs . '><div class="dsgo-counter-group__inner dsgo-counter-group__inner--align-' . esc_attr( $align ) . '">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/counter':
				$unique_id    = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : wp_unique_id( 'counter-' );
				$start_value  = isset( $attributes['startValue'] ) ? floatval( $attributes['startValue'] ) : 0;
				$end_value    = isset( $attributes['endValue'] ) ? floatval( $attributes['endValue'] ) : 100;
				$decimals     = isset( $attributes['decimals'] ) ? intval( $attributes['decimals'] ) : 0;
				$prefix       = isset( $attributes['prefix'] ) ? $attributes['prefix'] : '';
				$suffix       = isset( $attributes['suffix'] ) ? $attributes['suffix'] : '';
				$label        = isset( $attributes['label'] ) ? $attributes['label'] : '';
				$duration     = isset( $attributes['duration'] ) ? floatval( $attributes['duration'] ) : 2;
				$delay        = isset( $attributes['delay'] ) ? floatval( $attributes['delay'] ) : 0;
				$easing       = isset( $attributes['easing'] ) ? $attributes['easing'] : 'easeOutQuad';
				$use_grouping = isset( $attributes['useGrouping'] ) ? $attributes['useGrouping'] : true;
				$separator    = isset( $attributes['separator'] ) ? $attributes['separator'] : ',';
				$decimal      = isset( $attributes['decimal'] ) ? $attributes['decimal'] : '.';

				$data_attrs  = ' data-start-value="' . esc_attr( (string) $start_value ) . '"';
				$data_attrs .= ' data-end-value="' . esc_attr( (string) $end_value ) . '"';
				$data_attrs .= ' data-decimals="' . esc_attr( (string) $decimals ) . '"';
				$data_attrs .= ' data-prefix="' . esc_attr( $prefix ) . '"';
				$data_attrs .= ' data-suffix="' . esc_attr( $suffix ) . '"';
				$data_attrs .= ' data-duration="' . esc_attr( (string) $duration ) . '"';
				$data_attrs .= ' data-delay="' . esc_attr( (string) $delay ) . '"';
				$data_attrs .= ' data-easing="' . esc_attr( $easing ) . '"';
				$data_attrs .= ' data-use-grouping="' . ( $use_grouping ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-separator="' . esc_attr( $separator ) . '"';
				$data_attrs .= ' data-decimal="' . esc_attr( $decimal ) . '"';

				$inner_html  = '<div class="dsgo-counter__content icon-top">';
				$inner_html .= '<div class="dsgo-counter__number">';
				$inner_html .= '<span class="dsgo-counter__value">' . esc_html( (string) $start_value ) . '</span>';
				$inner_html .= '</div></div>';
				if ( $label ) {
					$inner_html .= '<div class="dsgo-counter__label">' . esc_html( $label ) . '</div>';
				}

				return array(
					'opening' => '<div class="wp-block-designsetgo-counter dsgo-counter" id="' . esc_attr( $unique_id ) . '" style="text-align:center"' . $data_attrs . '>' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/flip-card':
				$flip_trigger   = isset( $attributes['flipTrigger'] ) ? $attributes['flipTrigger'] : 'hover';
				$flip_effect    = isset( $attributes['flipEffect'] ) ? $attributes['flipEffect'] : 'flip';
				$flip_direction = isset( $attributes['flipDirection'] ) ? $attributes['flipDirection'] : 'horizontal';
				$flip_duration  = isset( $attributes['flipDuration'] ) ? $attributes['flipDuration'] : '0.6s';

				$outer_class = 'wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--' . esc_attr( $flip_trigger ) . ' dsgo-flip-card--effect-' . esc_attr( $flip_effect ) . ' dsgo-flip-card--' . esc_attr( $flip_direction );
				$outer_style = '--dsgo-flip-duration:' . esc_attr( $flip_duration ) . ';width:100%';
				$data_attrs  = ' data-flip-trigger="' . esc_attr( $flip_trigger ) . '" data-flip-effect="' . esc_attr( $flip_effect ) . '" data-flip-direction="' . esc_attr( $flip_direction ) . '"';

				return array(
					'opening' => '<div class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $outer_style ) . '"' . $data_attrs . '><div class="dsgo-flip-card__container">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/flip-card-face':
				$side = isset( $attributes['side'] ) && 'back' === $attributes['side'] ? 'back' : 'front';
				return array(
					'opening' => '<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__' . esc_attr( $side ) . '">',
					'closing' => '</div>',
				);

			// Legacy — consolidated into designsetgo/flip-card-face in 2.0.51.
			// Kept so the inserter ability can still emit existing content
			// until it is transformed to the new block.
			case 'designsetgo/flip-card-front':
				return array(
					'opening' => '<div class="wp-block-designsetgo-flip-card-front dsgo-flip-card__face dsgo-flip-card__front">',
					'closing' => '</div>',
				);

			case 'designsetgo/flip-card-back':
				return array(
					'opening' => '<div class="wp-block-designsetgo-flip-card-back dsgo-flip-card__face dsgo-flip-card__back">',
					'closing' => '</div>',
				);

			case 'designsetgo/icon':
				$icon_name    = isset( $attributes['icon'] ) ? $attributes['icon'] : ( isset( $attributes['iconName'] ) ? $attributes['iconName'] : 'star' );
				$icon_style   = isset( $attributes['iconStyle'] ) ? $attributes['iconStyle'] : 'filled';
				$stroke_width = isset( $attributes['strokeWidth'] ) ? $attributes['strokeWidth'] : '1.5';
				$icon_size    = isset( $attributes['iconSize'] ) ? intval( $attributes['iconSize'] ) : ( isset( $attributes['size'] ) ? intval( $attributes['size'] ) : 48 );
				$aria_label   = isset( $attributes['ariaLabel'] ) ? $attributes['ariaLabel'] : ucwords( str_replace( '-', ' ', $icon_name ) );

				$wrapper_style = 'width:' . $icon_size . 'px;height:' . $icon_size . 'px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit';

				$inner_html  = '<div class="dsgo-icon__wrapper dsgo-lazy-icon" style="' . esc_attr( $wrapper_style ) . '"';
				$inner_html .= ' data-icon-name="' . esc_attr( $icon_name ) . '"';
				$inner_html .= ' data-icon-style="' . esc_attr( $icon_style ) . '"';
				$inner_html .= ' data-icon-stroke-width="' . esc_attr( $stroke_width ) . '"';
				$inner_html .= ' role="img" aria-label="' . esc_attr( $aria_label ) . '"></div>';

				return array(
					'opening' => '<div class="wp-block-designsetgo-icon dsgo-icon" style="display:flex;align-items:center;justify-content:center">' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/accordion':
				$allow_multiple = isset( $attributes['allowMultipleOpen'] ) ? $attributes['allowMultipleOpen'] : false;
				$icon_style     = isset( $attributes['iconStyle'] ) ? $attributes['iconStyle'] : 'chevron';
				$icon_position  = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'right';
				$border_between = isset( $attributes['borderBetween'] ) ? $attributes['borderBetween'] : true;
				$item_gap       = isset( $attributes['itemGap'] ) ? $attributes['itemGap'] : '0.5rem';
				$open_bg        = isset( $attributes['openBackgroundColor'] ) ? $attributes['openBackgroundColor'] : '';
				$open_text      = isset( $attributes['openTextColor'] ) ? $attributes['openTextColor'] : '';
				$hover_bg       = isset( $attributes['hoverBackgroundColor'] ) ? $attributes['hoverBackgroundColor'] : $open_bg;
				$hover_text     = isset( $attributes['hoverTextColor'] ) ? $attributes['hoverTextColor'] : $open_text;
				$border_color   = isset( $attributes['borderBetweenColor'] ) ? $attributes['borderBetweenColor'] : '';

				// Build modifier classes (must match save.js).
				$accordion_classes = array( 'dsgo-accordion' );
				if ( $allow_multiple ) {
					$accordion_classes[] = 'dsgo-accordion--multiple';
				}
				if ( 'left' === $icon_position ) {
					$accordion_classes[] = 'dsgo-accordion--icon-left';
				} elseif ( 'right' === $icon_position ) {
					$accordion_classes[] = 'dsgo-accordion--icon-right';
				}
				if ( 'none' === $icon_style ) {
					$accordion_classes[] = 'dsgo-accordion--no-icon';
				}
				if ( $border_between ) {
					$accordion_classes[] = 'dsgo-accordion--border-between';
				}

				// Build CSS custom properties style (must match save.js).
				$style_parts = array(
					'--dsgo-accordion-open-bg:' . esc_attr( $open_bg ),
					'--dsgo-accordion-open-text:' . esc_attr( $open_text ),
					'--dsgo-accordion-hover-bg:' . esc_attr( $hover_bg ),
					'--dsgo-accordion-hover-text:' . esc_attr( $hover_text ),
					'--dsgo-accordion-gap:' . esc_attr( $item_gap ),
				);
				if ( $border_color ) {
					$style_parts[] = '--dsgo-accordion-border-color:' . esc_attr( $border_color );
				}
				$custom_style = implode( ';', $style_parts );

				$full_class = 'wp-block-designsetgo-accordion ' . implode( ' ', $accordion_classes );

				return array(
					'opening' => '<div class="' . esc_attr( $full_class ) . '" style="' . esc_attr( $custom_style ) . '" data-allow-multiple="' . ( $allow_multiple ? 'true' : 'false' ) . '" data-icon-style="' . esc_attr( $icon_style ) . '"><div class="dsgo-accordion__items">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/accordion-item':
				$title     = isset( $attributes['title'] ) ? $attributes['title'] : '';
				$is_open   = isset( $attributes['isOpen'] ) ? $attributes['isOpen'] : false;
				$unique_id = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : wp_unique_id( 'accordion-item-' );

				// Get icon style/position from context or defaults.
				$icon_style    = isset( $attributes['iconStyle'] ) ? $attributes['iconStyle'] : 'chevron';
				$icon_position = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'right';

				// Build item classes.
				$item_classes   = array( 'dsgo-accordion-item' );
				$item_classes[] = $is_open ? 'dsgo-accordion-item--open' : 'dsgo-accordion-item--closed';

				// Build trigger classes.
				$trigger_classes = array( 'dsgo-accordion-item__trigger' );
				if ( 'left' === $icon_position ) {
					$trigger_classes[] = 'dsgo-accordion-item__trigger--icon-left';
				} elseif ( 'right' === $icon_position ) {
					$trigger_classes[] = 'dsgo-accordion-item__trigger--icon-right';
				}

				// Generate icon SVG based on style.
				$icon_svg = '';
				if ( 'none' !== $icon_style ) {
					switch ( $icon_style ) {
						case 'plus-minus':
							if ( $is_open ) {
								$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4 8h8v1H4z"></path></svg>';
							} else {
								$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 4v8M4 8h8" stroke="currentColor" stroke-width="1" fill="none"></path></svg>';
							}
							break;
						case 'caret':
							$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M6 7l2 2 2-2z"></path></svg>';
							break;
						case 'chevron':
						default:
							$icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg>';
							break;
					}
				}

				$header_id = esc_attr( $unique_id ) . '-header';
				$panel_id  = esc_attr( $unique_id ) . '-panel';

				// Build icon HTML.
				$icon_html = '';
				if ( $icon_svg ) {
					$icon_html = '<span class="dsgo-accordion-item__icon" aria-hidden="true">' . $icon_svg . '</span>';
				}

				// Build the full accordion item HTML structure.
				$opening  = '<div class="wp-block-designsetgo-accordion-item ' . esc_attr( implode( ' ', $item_classes ) ) . '" data-initially-open="' . ( $is_open ? 'true' : 'false' ) . '">';
				$opening .= '<div class="dsgo-accordion-item__header">';
				$opening .= '<button type="button" class="' . esc_attr( implode( ' ', $trigger_classes ) ) . '" aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" aria-controls="' . $panel_id . '" id="' . $header_id . '">';
				if ( 'left' === $icon_position ) {
					$opening .= $icon_html;
				}
				$opening .= '<span class="dsgo-accordion-item__title">' . esc_html( $title ) . '</span>';
				if ( 'right' === $icon_position ) {
					$opening .= $icon_html;
				}
				$opening .= '</button>';
				$opening .= '</div>';
				$opening .= '<div class="dsgo-accordion-item__panel" role="region" aria-labelledby="' . $header_id . '" id="' . $panel_id . '"' . ( $is_open ? '' : ' hidden' ) . '>';
				$opening .= '<div class="dsgo-accordion-item__content">';

				$closing = '</div></div></div>';

				return array(
					'opening' => $opening,
					'closing' => $closing,
				);

			case 'designsetgo/divider':
				$divider_style = isset( $attributes['dividerStyle'] ) ? $attributes['dividerStyle'] : 'solid';
				$width         = isset( $attributes['width'] ) ? $attributes['width'] : 100;
				$thickness     = isset( $attributes['thickness'] ) ? $attributes['thickness'] : 2;
				$icon_name     = isset( $attributes['iconName'] ) ? $attributes['iconName'] : '';

				$divider_class = 'wp-block-designsetgo-divider dsgo-divider dsgo-divider--' . esc_attr( $divider_style );

				$container_style = 'width:' . intval( $width ) . '%';
				$line_style      = 'height:' . intval( $thickness ) . 'px';

				if ( 'icon' === $divider_style ) {
					// Icon style with three elements.
					$inner_html  = '<div class="dsgo-divider__container" style="' . esc_attr( $container_style ) . '">';
					$inner_html .= '<div class="dsgo-divider__icon-wrapper">';
					$inner_html .= '<span class="dsgo-divider__line dsgo-divider__line--left" style="' . esc_attr( $line_style ) . '"></span>';
					$inner_html .= '<span class="dsgo-divider__icon dsgo-lazy-icon" data-icon-name="' . esc_attr( $icon_name ) . '"></span>';
					$inner_html .= '<span class="dsgo-divider__line dsgo-divider__line--right" style="' . esc_attr( $line_style ) . '"></span>';
					$inner_html .= '</div></div>';
				} else {
					// Standard divider.
					$inner_html  = '<div class="dsgo-divider__container" style="' . esc_attr( $container_style ) . '">';
					$inner_html .= '<div class="dsgo-divider__line" style="' . esc_attr( $line_style ) . '"></div>';
					$inner_html .= '</div>';
				}

				return array(
					'opening' => '<div class="' . esc_attr( $divider_class ) . '">' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/countdown-timer':
				$target_datetime    = isset( $attributes['targetDateTime'] ) ? $attributes['targetDateTime'] : '';
				$timezone           = isset( $attributes['timezone'] ) ? $attributes['timezone'] : '';
				$show_days          = isset( $attributes['showDays'] ) ? $attributes['showDays'] : true;
				$show_hours         = isset( $attributes['showHours'] ) ? $attributes['showHours'] : true;
				$show_minutes       = isset( $attributes['showMinutes'] ) ? $attributes['showMinutes'] : true;
				$show_seconds       = isset( $attributes['showSeconds'] ) ? $attributes['showSeconds'] : true;
				$layout             = isset( $attributes['layout'] ) ? $attributes['layout'] : 'boxed';
				$completion_action  = isset( $attributes['completionAction'] ) ? $attributes['completionAction'] : 'message';
				$completion_message = isset( $attributes['completionMessage'] ) ? $attributes['completionMessage'] : 'The countdown has ended!';
				$number_color       = isset( $attributes['numberColor'] ) ? $attributes['numberColor'] : '';
				$label_color        = isset( $attributes['labelColor'] ) ? $attributes['labelColor'] : '';
				$unit_bg_color      = isset( $attributes['unitBackgroundColor'] ) ? $attributes['unitBackgroundColor'] : '';
				$unit_border        = isset( $attributes['unitBorder'] ) ? $attributes['unitBorder'] : array();
				$unit_border_radius = isset( $attributes['unitBorderRadius'] ) ? intval( $attributes['unitBorderRadius'] ) : 12;
				$unit_gap           = isset( $attributes['unitGap'] ) ? $attributes['unitGap'] : '1rem';
				$unit_padding       = isset( $attributes['unitPadding'] ) ? $attributes['unitPadding'] : '1.5rem';

				// Build unit style.
				$border_color = isset( $unit_border['color'] ) && $unit_border['color'] ? $unit_border['color'] : 'var(--wp--preset--color--accent-2, currentColor)';
				$border_width = isset( $unit_border['width'] ) ? $unit_border['width'] : '2px';
				$border_style = isset( $unit_border['style'] ) ? $unit_border['style'] : 'solid';

				$unit_style_parts = array(
					'background-color:' . ( $unit_bg_color ? esc_attr( $unit_bg_color ) : 'transparent' ),
					'border-color:' . esc_attr( $border_color ),
					'border-width:' . esc_attr( $border_width ),
					'border-style:' . esc_attr( $border_style ),
					'border-radius:' . $unit_border_radius . 'px',
					'padding:' . esc_attr( $unit_padding ),
				);
				$unit_style       = implode( ';', $unit_style_parts );

				$number_style = 'color:' . ( $number_color ? esc_attr( $number_color ) : 'var(--wp--preset--color--accent-2, currentColor)' );
				$label_style  = 'color:' . ( $label_color ? esc_attr( $label_color ) : 'currentColor' );

				// Build units HTML.
				$units = array();
				if ( $show_days ) {
					$units[] = array(
						'type'  => 'days',
						'label' => 'Days',
					);
				}
				if ( $show_hours ) {
					$units[] = array(
						'type'  => 'hours',
						'label' => 'Hours',
					);
				}
				if ( $show_minutes ) {
					$units[] = array(
						'type'  => 'minutes',
						'label' => 'Min',
					);
				}
				if ( $show_seconds ) {
					$units[] = array(
						'type'  => 'seconds',
						'label' => 'Sec',
					);
				}

				$units_html = '';
				foreach ( $units as $unit ) {
					$units_html .= '<div class="dsgo-countdown-timer__unit" data-unit-type="' . esc_attr( $unit['type'] ) . '" style="' . esc_attr( $unit_style ) . '">';
					$units_html .= '<div class="dsgo-countdown-timer__number" style="' . esc_attr( $number_style ) . '">00</div>';
					$units_html .= '<div class="dsgo-countdown-timer__label" style="' . esc_attr( $label_style ) . '">' . esc_html( $unit['label'] ) . '</div>';
					$units_html .= '</div>';
				}

				// Build data attributes.
				$data_attrs  = ' data-target-datetime="' . esc_attr( $target_datetime ) . '"';
				$data_attrs .= ' data-timezone="' . esc_attr( $timezone ) . '"';
				$data_attrs .= ' data-show-days="' . ( $show_days ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-show-hours="' . ( $show_hours ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-show-minutes="' . ( $show_minutes ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-show-seconds="' . ( $show_seconds ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-completion-action="' . esc_attr( $completion_action ) . '"';
				$data_attrs .= ' data-completion-message="' . esc_attr( $completion_message ) . '"';

				$container_style = 'gap:' . esc_attr( $unit_gap );
				$outer_class     = 'wp-block-designsetgo-countdown-timer dsgo-countdown-timer dsgo-countdown-timer--' . esc_attr( $layout );

				$inner_html  = '<div class="dsgo-countdown-timer__units">' . $units_html . '</div>';
				$inner_html .= '<div class="dsgo-countdown-timer__completion-message" style="display:none">' . esc_html( $completion_message ) . '</div>';

				return array(
					'opening' => '<div class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $container_style ) . '"' . $data_attrs . '>' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/progress-bar':
				$percentage        = isset( $attributes['percentage'] ) ? intval( $attributes['percentage'] ) : 75;
				$bar_color         = isset( $attributes['barColor'] ) ? $attributes['barColor'] : '#2563eb';
				$bar_bg_color      = isset( $attributes['barBackgroundColor'] ) ? $attributes['barBackgroundColor'] : '#e5e7eb';
				$height            = isset( $attributes['height'] ) ? $attributes['height'] : '20px';
				$border_radius     = isset( $attributes['borderRadius'] ) ? $attributes['borderRadius'] : '4px';
				$show_percentage   = isset( $attributes['showPercentage'] ) ? $attributes['showPercentage'] : true;
				$label_position    = isset( $attributes['labelPosition'] ) ? $attributes['labelPosition'] : 'top';
				$animate_on_scroll = isset( $attributes['animateOnScroll'] ) ? $attributes['animateOnScroll'] : true;
				$animation_dur     = isset( $attributes['animationDuration'] ) ? floatval( $attributes['animationDuration'] ) : 1.5;

				// Clamp percentage.
				$bar_width = min( max( $percentage, 0 ), 100 );

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-progress-bar', 'dsgo-progress-bar' );
				if ( $animate_on_scroll ) {
					$class_parts[] = 'dsgo-progress-bar--animate';
				}

				// Data attributes for animation.
				$data_attrs = '';
				if ( $animate_on_scroll ) {
					$data_attrs = ' data-percentage="' . esc_attr( (string) $bar_width ) . '" data-duration="' . esc_attr( (string) $animation_dur ) . '"';
				}

				// Label.
				$label_html = '';
				if ( $show_percentage && 'top' === $label_position ) {
					$label_html = '<div class="dsgo-progress-bar__label dsgo-progress-bar__label--top">' . esc_html( $bar_width . '%' ) . '</div>';
				}

				// Container styles.
				$container_style = 'width:100%;height:' . esc_attr( $height ) . ';background-color:' . esc_attr( $bar_bg_color ) . ';border-radius:' . esc_attr( $border_radius ) . ';overflow:hidden;position:relative';

				// Fill styles.
				$fill_width = $animate_on_scroll ? '0%' : $bar_width . '%';
				$fill_style = 'width:' . $fill_width . ';height:100%;background-color:' . esc_attr( $bar_color ) . ';transition:width ' . esc_attr( (string) $animation_dur ) . 's ease-out;border-radius:' . esc_attr( $border_radius );

				$inner_html  = $label_html;
				$inner_html .= '<div class="dsgo-progress-bar__container" style="' . esc_attr( $container_style ) . '">';
				$inner_html .= '<div class="dsgo-progress-bar__fill" style="' . esc_attr( $fill_style ) . '"></div>';
				$inner_html .= '</div>';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $data_attrs . '>' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/pill':
				$content = isset( $attributes['content'] ) ? $attributes['content'] : '';
				$align   = isset( $attributes['align'] ) ? $attributes['align'] : 'center';

				// Build class list matching useBlockProps.save output.
				$class_parts = array( 'wp-block-designsetgo-pill' );
				if ( $align ) {
					$class_parts[] = 'align' . $align;
				}
				$class_parts[] = 'dsgo-pill';
				$class_parts[] = 'has-small-font-size';

				$inner_html = '<span class="dsgo-pill__content">' . wp_kses_post( $content ) . '</span>';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '">' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/map':
				$provider           = isset( $attributes['dsgoProvider'] ) ? $attributes['dsgoProvider'] : 'openstreetmap';
				$latitude           = isset( $attributes['dsgoLatitude'] ) ? floatval( $attributes['dsgoLatitude'] ) : 40.7128;
				$longitude          = isset( $attributes['dsgoLongitude'] ) ? floatval( $attributes['dsgoLongitude'] ) : -74.006;
				$zoom               = isset( $attributes['dsgoZoom'] ) ? intval( $attributes['dsgoZoom'] ) : 13;
				$address            = isset( $attributes['dsgoAddress'] ) ? $attributes['dsgoAddress'] : '';
				$marker_icon        = isset( $attributes['dsgoMarkerIcon'] ) ? $attributes['dsgoMarkerIcon'] : '📍';
				$marker_color       = isset( $attributes['dsgoMarkerColor'] ) ? $attributes['dsgoMarkerColor'] : '#e74c3c';
				$height             = isset( $attributes['dsgoHeight'] ) ? $attributes['dsgoHeight'] : '400px';
				$aspect_ratio       = isset( $attributes['dsgoAspectRatio'] ) ? $attributes['dsgoAspectRatio'] : 'custom';
				$privacy_mode       = isset( $attributes['dsgoPrivacyMode'] ) ? $attributes['dsgoPrivacyMode'] : false;
				$has_privacy_notice = array_key_exists( 'dsgoPrivacyNotice', $attributes );
				$privacy_notice     = $attributes['dsgoPrivacyNotice'] ?? __( 'This map will load content from external services. Click to load and view the map.', 'designsetgo' );
				$map_style          = isset( $attributes['dsgoMapStyle'] ) ? $attributes['dsgoMapStyle'] : 'standard';

				// Clamp coordinates.
				$safe_lat  = max( -90, min( 90, $latitude ) );
				$safe_lng  = max( -180, min( 180, $longitude ) );
				$safe_zoom = max( 1, min( 20, $zoom ) );

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-map', 'dsgo-map' );
				if ( $privacy_mode ) {
					$class_parts[] = 'dsgo-map--privacy-mode';
				}
				if ( 'custom' !== $aspect_ratio ) {
					$class_parts[] = 'dsgo-map--aspect-' . str_replace( ':', '-', $aspect_ratio );
				}

				// Style.
				$style = '';
				if ( 'custom' === $aspect_ratio ) {
					$style = 'height:' . esc_attr( $height );
				}

				// Data attributes.
				$data_attrs  = ' data-dsgo-provider="' . esc_attr( $provider ) . '"';
				$data_attrs .= ' data-dsgo-lat="' . esc_attr( (string) $safe_lat ) . '"';
				$data_attrs .= ' data-dsgo-lng="' . esc_attr( (string) $safe_lng ) . '"';
				$data_attrs .= ' data-dsgo-zoom="' . esc_attr( (string) $safe_zoom ) . '"';
				$data_attrs .= ' data-dsgo-address="' . esc_attr( $address ) . '"';
				$data_attrs .= ' data-dsgo-marker-icon="' . esc_attr( $marker_icon ) . '"';
				$data_attrs .= ' data-dsgo-marker-color="' . esc_attr( $marker_color ) . '"';
				$data_attrs .= ' data-dsgo-privacy-mode="' . ( $privacy_mode ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-dsgo-map-style="' . esc_attr( $map_style ) . '"';

				// Inner HTML.
				$aria_label = $address
					? sprintf(
						/* translators: %s: The address being shown on the map */
						__( 'Map showing %s', 'designsetgo' ),
						$address
					)
					: __( 'Interactive map', 'designsetgo' );

				if ( $privacy_mode ) {
					$privacy_text = ( $has_privacy_notice && '' === $privacy_notice )
						? __( 'Click to load map', 'designsetgo' )
						: $privacy_notice;

					$inner_html  = '<div class="dsgo-map__privacy-overlay"><div class="dsgo-map__privacy-content">';
					$inner_html .= '<svg class="dsgo-map__privacy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
					$inner_html .= '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>';
					$inner_html .= '<circle cx="12" cy="10" r="3"></circle>';
					$inner_html .= '</svg>';
					$inner_html .= '<p class="dsgo-map__privacy-text">' . esc_html( $privacy_text ) . '</p>';
					$inner_html .= '<button class="dsgo-map__load-button" type="button" aria-label="' . esc_attr__( 'Load map. This will connect to external map services.', 'designsetgo' ) . '">';
					$inner_html .= esc_html__( 'Load Map', 'designsetgo' );
					$inner_html .= '</button></div></div>';
				} else {
					$inner_html = '<div class="dsgo-map__container" role="region" aria-label="' . esc_attr( $aria_label ) . '"></div>';
				}

				$style_attr = $style ? ' style="' . esc_attr( $style ) . '"' : '';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $style_attr . $data_attrs . '>' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/card':
				$layout_preset     = isset( $attributes['layoutPreset'] ) ? $attributes['layoutPreset'] : 'standard';
				$visual_style      = isset( $attributes['visualStyle'] ) ? $attributes['visualStyle'] : 'default';
				$title             = isset( $attributes['title'] ) ? $attributes['title'] : '';
				$subtitle          = isset( $attributes['subtitle'] ) ? $attributes['subtitle'] : '';
				$body_text         = isset( $attributes['bodyText'] ) ? $attributes['bodyText'] : '';
				$show_title        = isset( $attributes['showTitle'] ) ? $attributes['showTitle'] : true;
				$show_subtitle     = isset( $attributes['showSubtitle'] ) ? $attributes['showSubtitle'] : true;
				$show_body         = isset( $attributes['showBody'] ) ? $attributes['showBody'] : true;
				$show_cta          = isset( $attributes['showCta'] ) ? $attributes['showCta'] : true;
				$content_alignment = isset( $attributes['contentAlignment'] ) ? $attributes['contentAlignment'] : 'left';

				$outer_class = 'wp-block-designsetgo-card dsgo-card dsgo-card--' . esc_attr( $layout_preset ) . ' dsgo-card--style-' . esc_attr( $visual_style );

				// Build content HTML.
				$content_class = 'dsgo-card__content ';
				if ( 'background' === $layout_preset ) {
					$content_class .= 'dsgo-card__content--' . esc_attr( $content_alignment );
				}

				$content_html = '';
				if ( $show_title && $title ) {
					$content_html .= '<h3 class="dsgo-card__title">' . wp_kses_post( $title ) . '</h3>';
				}
				if ( $show_subtitle && $subtitle ) {
					$content_html .= '<p class="dsgo-card__subtitle">' . wp_kses_post( $subtitle ) . '</p>';
				}
				if ( $show_body && $body_text ) {
					$content_html .= '<p class="dsgo-card__body">' . wp_kses_post( $body_text ) . '</p>';
				}

				// CTA area for inner blocks.
				$cta_opening = '';
				$cta_closing = '';
				if ( $show_cta ) {
					$cta_opening = '<div class="dsgo-card__cta">';
					$cta_closing = '</div>';
				}

				return array(
					'opening' => '<div class="' . esc_attr( $outer_class ) . '"><div class="dsgo-card__inner"><div class="' . esc_attr( $content_class ) . '">' . $content_html . $cta_opening,
					'closing' => $cta_closing . '</div></div></div>',
				);

			case 'designsetgo/icon-list':
				$layout    = isset( $attributes['layout'] ) ? $attributes['layout'] : 'vertical';
				$gap       = isset( $attributes['gap'] ) ? $attributes['gap'] : '24px';
				$columns   = isset( $attributes['columns'] ) ? intval( $attributes['columns'] ) : 2;
				$alignment = isset( $attributes['alignment'] ) ? $attributes['alignment'] : 'left';

				// Calculate alignment values.
				$align_items     = '';
				$justify_content = '';
				$flex_direction  = '';

				if ( 'vertical' === $layout ) {
					$flex_direction = 'column';
					if ( 'center' === $alignment ) {
						$align_items = 'center';
					} elseif ( 'right' === $alignment ) {
						$align_items = 'flex-end';
					} else {
						$align_items = 'flex-start';
					}
				} elseif ( 'horizontal' === $layout ) {
					$flex_direction = 'row';
					if ( 'center' === $alignment ) {
						$justify_content = 'center';
					} elseif ( 'right' === $alignment ) {
						$justify_content = 'flex-end';
					} else {
						$justify_content = 'flex-start';
					}
				}

				// Build container styles.
				$container_style_parts = array();
				if ( 'grid' === $layout ) {
					$container_style_parts[] = 'display:grid';
					$container_style_parts[] = 'grid-template-columns:repeat(' . $columns . ', 1fr)';
				} else {
					$container_style_parts[] = 'display:flex';
					$container_style_parts[] = 'flex-direction:' . $flex_direction;
				}
				$container_style_parts[] = 'gap:' . esc_attr( $gap );
				if ( $align_items ) {
					$container_style_parts[] = 'align-items:' . $align_items;
				}
				if ( $justify_content ) {
					$container_style_parts[] = 'justify-content:' . $justify_content;
				}
				$container_style_parts[] = 'width:100%';
				$container_style         = implode( ';', $container_style_parts );

				$outer_class = 'wp-block-designsetgo-icon-list dsgo-icon-list dsgo-icon-list--' . esc_attr( $layout );

				return array(
					'opening' => '<div class="' . esc_attr( $outer_class ) . '" style="width:100%"><div class="dsgo-icon-list__items" style="' . esc_attr( $container_style ) . '">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/icon-list-item':
				$icon        = isset( $attributes['icon'] ) ? $attributes['icon'] : 'star';
				$link_url    = isset( $attributes['linkUrl'] ) ? $attributes['linkUrl'] : '';
				$link_target = isset( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '';
				$link_rel    = isset( $attributes['linkRel'] ) ? $attributes['linkRel'] : '';
				$content_gap = isset( $attributes['contentGap'] ) ? intval( $attributes['contentGap'] ) : 8;
				// These values typically come from parent block context, read from attributes if provided.
				$icon_size     = isset( $attributes['iconSize'] ) ? intval( $attributes['iconSize'] ) : 32;
				$icon_position = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'left';

				// Calculate text alignment.
				$text_align = 'left';
				if ( 'top' === $icon_position ) {
					$text_align = 'center';
				} elseif ( 'right' === $icon_position ) {
					$text_align = 'right';
				}

				// Item styles.
				$flex_direction = 'top' === $icon_position ? 'column' : 'row';
				if ( 'right' === $icon_position ) {
					$flex_direction = 'row-reverse';
				}
				$item_align = 'top' === $icon_position ? 'center' : 'flex-start';
				$item_gap   = 'top' === $icon_position ? '12px' : '16px';
				$item_style = 'display:flex;flex-direction:' . $flex_direction . ';align-items:' . $item_align . ';gap:' . $item_gap;

				// Icon wrapper styles.
				$icon_style = 'display:flex;align-items:center;justify-content:center;width:' . $icon_size . 'px;height:' . $icon_size . 'px;min-width:' . $icon_size . 'px';

				// Content styles.
				$content_style = 'text-align:' . $text_align . ';display:flex;flex-direction:column;gap:' . $content_gap . 'px';

				$outer_class = 'wp-block-designsetgo-icon-list-item dsgo-icon-list-item dsgo-icon-list-item--icon-' . esc_attr( $icon_position );

				// Build icon HTML.
				$icon_html = '<div class="dsgo-icon-list-item__icon dsgo-lazy-icon" style="' . esc_attr( $icon_style ) . '" data-icon-name="' . esc_attr( $icon ) . '"></div>';

				// Build element (div or link).
				$tag         = $link_url ? 'a' : 'div';
				$extra_attrs = '';
				if ( $link_url ) {
					$extra_attrs .= ' href="' . esc_url( $link_url ) . '"';
					if ( $link_target ) {
						$extra_attrs .= ' target="' . esc_attr( $link_target ) . '"';
					}
					if ( $link_rel ) {
						$extra_attrs .= ' rel="' . esc_attr( $link_rel ) . '"';
					}
				}

				return array(
					'opening' => '<' . $tag . ' class="' . esc_attr( $outer_class ) . '" style="' . esc_attr( $item_style ) . '"' . $extra_attrs . '>' . $icon_html . '<div class="dsgo-icon-list-item__content" style="' . esc_attr( $content_style ) . '">',
					'closing' => '</div></' . $tag . '>',
				);

			case 'designsetgo/icon-button':
				$text           = isset( $attributes['text'] ) ? $attributes['text'] : '';
				$url            = isset( $attributes['url'] ) ? $attributes['url'] : '';
				$link_target    = isset( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '';
				$rel            = isset( $attributes['rel'] ) ? $attributes['rel'] : '';
				$icon           = isset( $attributes['icon'] ) ? $attributes['icon'] : 'lightbulb';
				$icon_position  = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'start';
				$icon_size      = isset( $attributes['iconSize'] ) ? intval( $attributes['iconSize'] ) : 20;
				$icon_gap       = isset( $attributes['iconGap'] ) ? $attributes['iconGap'] : '8px';
				$align          = isset( $attributes['align'] ) ? $attributes['align'] : '';
				$hover_anim     = isset( $attributes['hoverAnimation'] ) ? $attributes['hoverAnimation'] : 'none';
				$modal_close_id = isset( $attributes['modalCloseId'] ) ? $attributes['modalCloseId'] : '';

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-icon-button', 'dsgo-icon-button', 'wp-block-button', 'wp-block-button__link', 'wp-element-button' );
				if ( $hover_anim && 'none' !== $hover_anim ) {
					$class_parts[] = 'dsgo-icon-button--' . $hover_anim;
				}

				// Build button styles.
				$is_full_width  = 'full' === $align;
				$flex_direction = 'end' === $icon_position ? 'row-reverse' : 'row';
				$gap_value      = ( 'none' !== $icon_position && $icon ) ? $icon_gap : '0';
				$style_parts    = array(
					'display:' . ( $is_full_width ? 'flex' : 'inline-flex' ),
					'align-items:center',
					'justify-content:center',
					'gap:' . esc_attr( $gap_value ),
					'width:' . ( $is_full_width ? '100%' : 'auto' ),
					'flex-direction:' . $flex_direction,
				);
				$button_style   = implode( ';', $style_parts );

				// Icon HTML.
				$icon_html = '';
				if ( 'none' !== $icon_position && $icon ) {
					$icon_style = 'display:flex;align-items:center;justify-content:center;width:' . $icon_size . 'px;height:' . $icon_size . 'px;flex-shrink:0';
					$icon_html  = '<span class="dsgo-icon-button__icon dsgo-lazy-icon" style="' . esc_attr( $icon_style ) . '" data-icon-name="' . esc_attr( $icon ) . '" data-icon-size="' . esc_attr( (string) $icon_size ) . '"></span>';
				}

				// Text HTML.
				$text_html = '<span class="dsgo-icon-button__text">' . wp_kses_post( $text ) . '</span>';

				// Build element (button or link).
				$tag = $url ? 'a' : 'button';

				// Additional attributes.
				$extra_attrs = '';
				if ( $url ) {
					$extra_attrs .= ' href="' . esc_url( $url ) . '"';
					if ( $link_target ) {
						$extra_attrs .= ' target="' . esc_attr( $link_target ) . '"';
					}
					$rel_value = '_blank' === $link_target ? ( $rel ? $rel : 'noopener noreferrer' ) : $rel;
					if ( $rel_value ) {
						$extra_attrs .= ' rel="' . esc_attr( $rel_value ) . '"';
					}
				} else {
					$extra_attrs .= ' type="button"';
				}
				if ( $modal_close_id ) {
					$extra_attrs .= ' data-dsgo-modal-close="' . esc_attr( $modal_close_id ) . '"';
				}

				$inner_html = $icon_html . $text_html;

				return array(
					'opening' => '<' . $tag . ' class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $button_style ) . '"' . $extra_attrs . '>' . $inner_html,
					'closing' => '</' . $tag . '>',
				);

			case 'designsetgo/modal':
				$modal_id                = isset( $attributes['modalId'] ) ? $attributes['modalId'] : 'dsgo-modal-' . wp_generate_uuid4();
				$animation_type          = isset( $attributes['animationType'] ) ? $attributes['animationType'] : 'fade';
				$animation_duration      = isset( $attributes['animationDuration'] ) ? intval( $attributes['animationDuration'] ) : 300;
				$close_on_backdrop       = isset( $attributes['closeOnBackdrop'] ) ? $attributes['closeOnBackdrop'] : true;
				$close_on_esc            = isset( $attributes['closeOnEsc'] ) ? $attributes['closeOnEsc'] : true;
				$disable_body_scroll     = isset( $attributes['disableBodyScroll'] ) ? $attributes['disableBodyScroll'] : true;
				$allow_hash_trigger      = isset( $attributes['allowHashTrigger'] ) ? $attributes['allowHashTrigger'] : true;
				$update_url_on_open      = isset( $attributes['updateUrlOnOpen'] ) ? $attributes['updateUrlOnOpen'] : false;
				$auto_trigger_type       = isset( $attributes['autoTriggerType'] ) ? $attributes['autoTriggerType'] : 'none';
				$auto_trigger_delay      = isset( $attributes['autoTriggerDelay'] ) ? intval( $attributes['autoTriggerDelay'] ) : 0;
				$auto_trigger_frequency  = isset( $attributes['autoTriggerFrequency'] ) ? $attributes['autoTriggerFrequency'] : 'always';
				$cookie_duration         = isset( $attributes['cookieDuration'] ) ? intval( $attributes['cookieDuration'] ) : 7;
				$exit_intent_sensitivity = isset( $attributes['exitIntentSensitivity'] ) ? $attributes['exitIntentSensitivity'] : 'medium';
				$exit_intent_min_time    = isset( $attributes['exitIntentMinTime'] ) ? intval( $attributes['exitIntentMinTime'] ) : 5;
				$exit_intent_exclude_mob = isset( $attributes['exitIntentExcludeMobile'] ) ? $attributes['exitIntentExcludeMobile'] : true;
				$scroll_depth            = isset( $attributes['scrollDepth'] ) ? intval( $attributes['scrollDepth'] ) : 50;
				$scroll_direction        = isset( $attributes['scrollDirection'] ) ? $attributes['scrollDirection'] : 'down';
				$time_on_page            = isset( $attributes['timeOnPage'] ) ? intval( $attributes['timeOnPage'] ) : 30;
				$gallery_group_id        = isset( $attributes['galleryGroupId'] ) ? $attributes['galleryGroupId'] : '';
				$gallery_index           = isset( $attributes['galleryIndex'] ) ? intval( $attributes['galleryIndex'] ) : 0;
				$show_gallery_nav        = isset( $attributes['showGalleryNavigation'] ) ? $attributes['showGalleryNavigation'] : true;
				$nav_style               = isset( $attributes['navigationStyle'] ) ? $attributes['navigationStyle'] : 'arrows';
				$nav_position            = isset( $attributes['navigationPosition'] ) ? $attributes['navigationPosition'] : 'sides';
				$width                   = isset( $attributes['width'] ) ? $attributes['width'] : '600px';
				$max_width               = isset( $attributes['maxWidth'] ) ? $attributes['maxWidth'] : '90vw';
				$overlay_color           = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '#000000';
				$overlay_opacity         = isset( $attributes['overlayOpacity'] ) ? floatval( $attributes['overlayOpacity'] ) : 80;
				$show_close_button       = isset( $attributes['showCloseButton'] ) ? $attributes['showCloseButton'] : true;
				$close_button_position   = isset( $attributes['closeButtonPosition'] ) ? $attributes['closeButtonPosition'] : 'inside-top-right';
				$close_button_size       = isset( $attributes['closeButtonSize'] ) ? intval( $attributes['closeButtonSize'] ) : 24;

				// Build data attributes.
				$data_attrs  = ' data-dsgo-modal="true"';
				$data_attrs .= ' data-modal-id="' . esc_attr( $modal_id ) . '"';
				$data_attrs .= ' data-animation-type="' . esc_attr( $animation_type ) . '"';
				$data_attrs .= ' data-animation-duration="' . esc_attr( (string) $animation_duration ) . '"';
				$data_attrs .= ' data-close-on-backdrop="' . ( $close_on_backdrop ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-close-on-esc="' . ( $close_on_esc ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-disable-body-scroll="' . ( $disable_body_scroll ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-allow-hash-trigger="' . ( $allow_hash_trigger ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-update-url-on-open="' . ( $update_url_on_open ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-auto-trigger-type="' . esc_attr( $auto_trigger_type ) . '"';
				$data_attrs .= ' data-auto-trigger-delay="' . esc_attr( (string) $auto_trigger_delay ) . '"';
				$data_attrs .= ' data-auto-trigger-frequency="' . esc_attr( $auto_trigger_frequency ) . '"';
				$data_attrs .= ' data-cookie-duration="' . esc_attr( (string) $cookie_duration ) . '"';
				$data_attrs .= ' data-exit-intent-sensitivity="' . esc_attr( (string) $exit_intent_sensitivity ) . '"';
				$data_attrs .= ' data-exit-intent-min-time="' . esc_attr( (string) $exit_intent_min_time ) . '"';
				$data_attrs .= ' data-exit-intent-exclude-mobile="' . ( $exit_intent_exclude_mob ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-scroll-depth="' . esc_attr( (string) $scroll_depth ) . '"';
				$data_attrs .= ' data-scroll-direction="' . esc_attr( $scroll_direction ) . '"';
				$data_attrs .= ' data-time-on-page="' . esc_attr( (string) $time_on_page ) . '"';
				$data_attrs .= ' data-gallery-group-id="' . esc_attr( $gallery_group_id ) . '"';
				$data_attrs .= ' data-gallery-index="' . esc_attr( (string) $gallery_index ) . '"';
				$data_attrs .= ' data-show-gallery-navigation="' . ( $show_gallery_nav ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-navigation-style="' . esc_attr( $nav_style ) . '"';
				$data_attrs .= ' data-navigation-position="' . esc_attr( $nav_position ) . '"';

				// Overlay styles.
				$overlay_style = 'background-color:' . esc_attr( $overlay_color ) . ';opacity:' . ( $overlay_opacity / 100 );

				// Content styles.
				$content_style = 'border-style:none;border-width:0px;width:' . esc_attr( $width ) . ';max-width:' . esc_attr( $max_width );

				// Close button HTML.
				$close_button_html = '';
				if ( $show_close_button ) {
					$close_button_style = 'width:' . $close_button_size . 'px;height:' . $close_button_size . 'px';
					$close_button_html  = '<button class="dsgo-modal__close dsgo-modal__close--' . esc_attr( $close_button_position ) . '" style="' . esc_attr( $close_button_style ) . '" type="button" aria-label="Close modal">';
					$close_button_html .= '<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">';
					$close_button_html .= '<path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>';
					$close_button_html .= '</svg></button>';
				}

				$close_button_is_inside = strpos( $close_button_position, 'inside-' ) === 0;

				$outer_class = 'wp-block-designsetgo-modal dsgo-modal';

				$inner_html  = '<div class="dsgo-modal__backdrop" style="' . esc_attr( $overlay_style ) . '" aria-hidden="true"></div>';
				$inner_html .= '<div class="dsgo-modal__dialog">';
				if ( ! $close_button_is_inside ) {
					$inner_html .= $close_button_html;
				}
				$inner_html .= '<div class="dsgo-modal__content" style="' . esc_attr( $content_style ) . '">';

				$closing_html = '';
				if ( $close_button_is_inside ) {
					$closing_html .= $close_button_html;
				}
				$closing_html .= '</div></div></div>';

				return array(
					'opening' => '<div id="' . esc_attr( $modal_id ) . '" role="dialog" aria-modal="true" aria-label="Modal" aria-hidden="true"' . $data_attrs . ' class="' . esc_attr( $outer_class ) . '">' . $inner_html,
					'closing' => $closing_html,
				);

			case 'designsetgo/modal-trigger':
				$text          = isset( $attributes['text'] ) ? $attributes['text'] : 'Open Modal';
				$button_style  = isset( $attributes['buttonStyle'] ) ? $attributes['buttonStyle'] : 'fill';
				$width_style   = isset( $attributes['widthStyle'] ) ? $attributes['widthStyle'] : 'auto';
				$icon          = isset( $attributes['icon'] ) ? $attributes['icon'] : '';
				$icon_position = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'start';

				$outer_class = 'wp-block-designsetgo-modal-trigger dsgo-modal-trigger dsgo-modal-trigger--' . esc_attr( $button_style ) . ' dsgo-modal-trigger--width-' . esc_attr( $width_style );

				$flex_direction    = 'end' === $icon_position ? 'row-reverse' : 'row';
				$button_style_attr = 'flex-direction:' . $flex_direction;

				$inner_html = '<button class="dsgo-modal-trigger__button" data-dsgo-modal-trigger="" style="' . esc_attr( $button_style_attr ) . '" type="button">';
				if ( $icon && 'none' !== $icon_position ) {
					$inner_html .= '<span class="dsgo-modal-trigger__icon dsgo-lazy-icon" data-icon-name="' . esc_attr( $icon ) . '"></span>';
				}
				$inner_html .= '<span class="dsgo-modal-trigger__text">' . wp_kses_post( $text ) . '</span>';
				$inner_html .= '</button>';

				return array(
					'opening' => '<div class="' . esc_attr( $outer_class ) . '" style="display:inline-block">' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/table-of-contents':
				$unique_id     = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : substr( wp_generate_uuid4(), 0, 8 );
				$include_h2    = isset( $attributes['includeH2'] ) ? $attributes['includeH2'] : true;
				$include_h3    = isset( $attributes['includeH3'] ) ? $attributes['includeH3'] : true;
				$include_h4    = isset( $attributes['includeH4'] ) ? $attributes['includeH4'] : false;
				$include_h5    = isset( $attributes['includeH5'] ) ? $attributes['includeH5'] : false;
				$include_h6    = isset( $attributes['includeH6'] ) ? $attributes['includeH6'] : false;
				$display_mode  = isset( $attributes['displayMode'] ) ? $attributes['displayMode'] : 'hierarchical';
				$list_style    = isset( $attributes['listStyle'] ) ? $attributes['listStyle'] : 'unordered';
				$show_title    = isset( $attributes['showTitle'] ) ? $attributes['showTitle'] : true;
				$title_text    = isset( $attributes['titleText'] ) ? $attributes['titleText'] : 'Table of Contents';
				$scroll_smooth = isset( $attributes['scrollSmooth'] ) ? $attributes['scrollSmooth'] : true;
				$scroll_offset = isset( $attributes['scrollOffset'] ) ? intval( $attributes['scrollOffset'] ) : 0;

				// Build heading levels.
				$heading_levels = array();
				if ( $include_h2 ) {
					$heading_levels[] = 'h2';
				}
				if ( $include_h3 ) {
					$heading_levels[] = 'h3';
				}
				if ( $include_h4 ) {
					$heading_levels[] = 'h4';
				}
				if ( $include_h5 ) {
					$heading_levels[] = 'h5';
				}
				if ( $include_h6 ) {
					$heading_levels[] = 'h6';
				}

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-table-of-contents', 'dsgo-table-of-contents' );
				if ( 'hierarchical' === $display_mode ) {
					$class_parts[] = 'dsgo-table-of-contents--hierarchical';
				} else {
					$class_parts[] = 'dsgo-table-of-contents--flat';
				}
				if ( 'ordered' === $list_style ) {
					$class_parts[] = 'dsgo-table-of-contents--ordered';
				}
				if ( $scroll_smooth ) {
					$class_parts[] = 'dsgo-table-of-contents--smooth';
				}

				// Data attributes.
				$data_attrs  = ' data-unique-id="' . esc_attr( $unique_id ) . '"';
				$data_attrs .= ' data-heading-levels="' . esc_attr( implode( ',', $heading_levels ) ) . '"';
				$data_attrs .= ' data-display-mode="' . esc_attr( $display_mode ) . '"';
				$data_attrs .= ' data-scroll-smooth="' . ( $scroll_smooth ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-scroll-offset="' . esc_attr( (string) $scroll_offset ) . '"';

				// List tag.
				$list_tag = 'ordered' === $list_style ? 'ol' : 'ul';

				// Inner HTML.
				$inner_html = '<div class="dsgo-table-of-contents__content">';
				if ( $show_title ) {
					$inner_html .= '<div class="dsgo-table-of-contents__title">' . esc_html( $title_text ) . '</div>';
				}
				$inner_html .= '<' . $list_tag . ' class="dsgo-table-of-contents__list"></' . $list_tag . '>';
				$inner_html .= '</div>';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $data_attrs . '>' . $inner_html,
					'closing' => '</div>',
				);

			case 'designsetgo/image-accordion':
				$height                   = isset( $attributes['height'] ) ? $attributes['height'] : '500px';
				$gap                      = isset( $attributes['gap'] ) ? $attributes['gap'] : '4px';
				$expanded_ratio           = isset( $attributes['expandedRatio'] ) ? floatval( $attributes['expandedRatio'] ) : 3;
				$transition_duration      = isset( $attributes['transitionDuration'] ) ? $attributes['transitionDuration'] : '0.5s';
				$enable_overlay           = isset( $attributes['enableOverlay'] ) ? $attributes['enableOverlay'] : true;
				$overlay_color            = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '#000000';
				$overlay_opacity          = isset( $attributes['overlayOpacity'] ) ? floatval( $attributes['overlayOpacity'] ) : 40;
				$overlay_opacity_expanded = isset( $attributes['overlayOpacityExpanded'] ) ? floatval( $attributes['overlayOpacityExpanded'] ) : 20;
				$trigger_type             = isset( $attributes['triggerType'] ) ? $attributes['triggerType'] : 'hover';
				$default_expanded         = isset( $attributes['defaultExpanded'] ) ? intval( $attributes['defaultExpanded'] ) : 0;

				// Build classes.
				$class_parts   = array( 'wp-block-designsetgo-image-accordion', 'dsgo-image-accordion' );
				$class_parts[] = 'dsgo-image-accordion--' . esc_attr( $trigger_type );

				// Build style with CSS custom properties.
				$style_parts = array(
					'--dsgo-image-accordion-height:' . esc_attr( $height ),
					'--dsgo-image-accordion-gap:' . esc_attr( $gap ),
					'--dsgo-image-accordion-expanded-ratio:' . esc_attr( (string) $expanded_ratio ),
					'--dsgo-image-accordion-transition:' . esc_attr( $transition_duration ),
					'--dsgo-image-accordion-overlay-color:' . esc_attr( $overlay_color ),
					'--dsgo-image-accordion-overlay-opacity:' . esc_attr( (string) ( $overlay_opacity / 100 ) ),
					'--dsgo-image-accordion-overlay-opacity-expanded:' . esc_attr( (string) ( $overlay_opacity_expanded / 100 ) ),
				);
				$style       = implode( ';', $style_parts );

				// Data attributes.
				$data_attrs  = ' data-trigger-type="' . esc_attr( $trigger_type ) . '"';
				$data_attrs .= ' data-default-expanded="' . esc_attr( (string) $default_expanded ) . '"';
				$data_attrs .= ' data-enable-overlay="' . ( $enable_overlay ? 'true' : 'false' ) . '"';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '"' . $data_attrs . '><div class="dsgo-image-accordion__items">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/image-accordion-item':
				$unique_id            = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : 'image-accordion-item-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 );
				$vertical_alignment   = isset( $attributes['verticalAlignment'] ) ? $attributes['verticalAlignment'] : 'center';
				$horizontal_alignment = isset( $attributes['horizontalAlignment'] ) ? $attributes['horizontalAlignment'] : 'center';

				// These values come from parent block context (usesContext in block.json).
				// Read from attributes if provided, otherwise use defaults matching parent defaults.
				$enable_overlay           = isset( $attributes['enableOverlay'] ) ? (bool) $attributes['enableOverlay'] : true;
				$overlay_color            = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '#000000';
				$overlay_opacity          = isset( $attributes['overlayOpacity'] ) ? floatval( $attributes['overlayOpacity'] ) : 40;
				$overlay_opacity_expanded = isset( $attributes['overlayOpacityExpanded'] ) ? floatval( $attributes['overlayOpacityExpanded'] ) : 20;

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-image-accordion-item', 'dsgo-image-accordion-item' );
				if ( $enable_overlay ) {
					$class_parts[] = 'dsgo-image-accordion-item--has-overlay';
				}

				// Build style with CSS custom properties (overlay first, then alignment - must match save.js order).
				$style_parts = array();
				if ( $enable_overlay ) {
					$style_parts[] = '--dsgo-overlay-color:' . esc_attr( $overlay_color );
					$style_parts[] = '--dsgo-overlay-opacity:' . esc_attr( (string) ( $overlay_opacity / 100 ) );
					$style_parts[] = '--dsgo-overlay-opacity-expanded:' . esc_attr( (string) ( $overlay_opacity_expanded / 100 ) );
				}
				$style_parts[] = '--dsgo-vertical-alignment:' . esc_attr( $vertical_alignment );
				$style_parts[] = '--dsgo-horizontal-alignment:' . esc_attr( $horizontal_alignment );
				$style         = implode( ';', $style_parts );

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '" data-unique-id="' . esc_attr( $unique_id ) . '" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/scroll-accordion':
				$align_items = isset( $attributes['alignItems'] ) ? $attributes['alignItems'] : 'flex-start';

				// Inner styles.
				$inner_style = 'display:flex;flex-direction:column;align-items:' . esc_attr( $align_items );

				return array(
					'opening' => '<div class="wp-block-designsetgo-scroll-accordion dsgo-scroll-accordion" style="width:100%;align-self:stretch"><div class="dsgo-scroll-accordion__items" style="' . esc_attr( $inner_style ) . '">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/scroll-accordion-item':
				$overlay_color = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '';

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-scroll-accordion-item', 'dsgo-scroll-accordion-item' );
				if ( $overlay_color ) {
					$class_parts[] = 'dsgo-scroll-accordion-item--has-overlay';
				}

				// Build style.
				$style = '';
				if ( $overlay_color ) {
					$style = '--dsgo-overlay-color:' . esc_attr( $overlay_color ) . ';--dsgo-overlay-opacity:0.8';
				}

				$style_attr = $style ? ' style="' . esc_attr( $style ) . '"' : '';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $style_attr . '>',
					'closing' => '</div>',
				);

			case 'designsetgo/slider':
				// The inline isset() fallbacks below are normally unreachable
				// because apply_block_json_defaults() fills these from the
				// registry before we get here. They only fire when the block
				// isn't registered (e.g., build folder missing), so keep them
				// synced with src/blocks/slider/block.json — not with any
				// past convention like height=500px / arrowSize=48px.
				$slides_per_view        = isset( $attributes['slidesPerView'] ) ? intval( $attributes['slidesPerView'] ) : 1;
				$slides_per_view_tablet = isset( $attributes['slidesPerViewTablet'] ) ? intval( $attributes['slidesPerViewTablet'] ) : 1;
				$slides_per_view_mobile = isset( $attributes['slidesPerViewMobile'] ) ? intval( $attributes['slidesPerViewMobile'] ) : 1;
				$height                 = isset( $attributes['height'] ) ? $attributes['height'] : '';
				$aspect_ratio           = isset( $attributes['aspectRatio'] ) ? $attributes['aspectRatio'] : '16/9';
				$use_aspect_ratio       = isset( $attributes['useAspectRatio'] ) ? $attributes['useAspectRatio'] : false;
				$gap                    = isset( $attributes['gap'] ) ? $attributes['gap'] : '20px';
				$show_arrows            = isset( $attributes['showArrows'] ) ? $attributes['showArrows'] : true;
				$show_dots              = isset( $attributes['showDots'] ) ? $attributes['showDots'] : true;
				$arrow_style            = isset( $attributes['arrowStyle'] ) ? $attributes['arrowStyle'] : 'default';
				$arrow_position         = isset( $attributes['arrowPosition'] ) ? $attributes['arrowPosition'] : 'sides';
				$arrow_vertical_pos     = isset( $attributes['arrowVerticalPosition'] ) ? $attributes['arrowVerticalPosition'] : 'center';
				$arrow_color            = isset( $attributes['arrowColor'] ) ? $attributes['arrowColor'] : '';
				$arrow_bg_color         = isset( $attributes['arrowBackgroundColor'] ) ? $attributes['arrowBackgroundColor'] : '';
				$arrow_size             = isset( $attributes['arrowSize'] ) ? $attributes['arrowSize'] : '24px';
				$arrow_padding          = isset( $attributes['arrowPadding'] ) ? $attributes['arrowPadding'] : '';
				$dot_style              = isset( $attributes['dotStyle'] ) ? $attributes['dotStyle'] : 'default';
				$dot_position           = isset( $attributes['dotPosition'] ) ? $attributes['dotPosition'] : 'inside';
				$dot_color              = isset( $attributes['dotColor'] ) ? $attributes['dotColor'] : '';
				$effect                 = isset( $attributes['effect'] ) ? $attributes['effect'] : 'slide';
				$transition_duration    = isset( $attributes['transitionDuration'] ) ? $attributes['transitionDuration'] : '0.5s';
				$transition_easing      = isset( $attributes['transitionEasing'] ) ? $attributes['transitionEasing'] : 'ease-in-out';
				$autoplay               = isset( $attributes['autoplay'] ) ? $attributes['autoplay'] : false;
				$autoplay_interval      = isset( $attributes['autoplayInterval'] ) ? intval( $attributes['autoplayInterval'] ) : 3000;
				$pause_on_hover         = isset( $attributes['pauseOnHover'] ) ? $attributes['pauseOnHover'] : true;
				$pause_on_interaction   = isset( $attributes['pauseOnInteraction'] ) ? $attributes['pauseOnInteraction'] : true;
				$loop                   = isset( $attributes['loop'] ) ? $attributes['loop'] : true;
				$draggable              = isset( $attributes['draggable'] ) ? $attributes['draggable'] : true;
				$swipeable              = isset( $attributes['swipeable'] ) ? $attributes['swipeable'] : true;
				$free_mode              = isset( $attributes['freeMode'] ) ? $attributes['freeMode'] : false;
				$centered_slides        = isset( $attributes['centeredSlides'] ) ? $attributes['centeredSlides'] : false;
				$mobile_breakpoint      = isset( $attributes['mobileBreakpoint'] ) ? intval( $attributes['mobileBreakpoint'] ) : 768;
				$tablet_breakpoint      = isset( $attributes['tabletBreakpoint'] ) ? intval( $attributes['tabletBreakpoint'] ) : 1024;
				$active_slide           = isset( $attributes['activeSlide'] ) ? intval( $attributes['activeSlide'] ) : 0;
				$style_variation        = isset( $attributes['styleVariation'] ) ? $attributes['styleVariation'] : 'classic';
				$aria_label             = isset( $attributes['ariaLabel'] ) ? $attributes['ariaLabel'] : '';
				$scroll_driven          = isset( $attributes['scrollDriven'] ) ? $attributes['scrollDriven'] : false;
				$scroll_driven_speed    = isset( $attributes['scrollDrivenSpeed'] ) ? floatval( $attributes['scrollDrivenSpeed'] ) : 1;

				// Single slide effects.
				$single_slide_effects    = array( 'fade', 'zoom' );
				$requires_single         = in_array( $effect, $single_slide_effects, true );
				$effective_slides        = $requires_single ? 1 : $slides_per_view;
				$effective_slides_tablet = $requires_single ? 1 : $slides_per_view_tablet;
				$effective_slides_mobile = $requires_single ? 1 : $slides_per_view_mobile;

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-slider', 'dsgo-slider' );
				if ( $style_variation ) {
					$class_parts[] = 'dsgo-slider--' . esc_attr( $style_variation );
				}
				if ( $effect ) {
					$class_parts[] = 'dsgo-slider--effect-' . esc_attr( $effect );
				}
				if ( $show_arrows ) {
					$class_parts[] = 'dsgo-slider--has-arrows';
				}
				if ( $show_dots ) {
					$class_parts[] = 'dsgo-slider--has-dots';
				}
				if ( $centered_slides ) {
					$class_parts[] = 'dsgo-slider--centered';
				}
				if ( $free_mode ) {
					$class_parts[] = 'dsgo-slider--free-mode';
				}
				if ( $scroll_driven ) {
					$class_parts[] = 'dsgo-slider--scroll-driven';
				}

				// Build style. Mirror save.js: height is only included when
				// truthy (block.json default is ""), and arrow size follows the
				// same rule. Emitting them unconditionally here breaks
				// round-tripping against save().
				$style_parts = array();
				if ( $height ) {
					$style_parts[] = '--dsgo-slider-height:' . esc_attr( $height );
				}
				$style_parts[] = '--dsgo-slider-aspect-ratio:' . esc_attr( $aspect_ratio );
				$style_parts[] = '--dsgo-slider-gap:' . esc_attr( $gap );
				$style_parts[] = '--dsgo-slider-transition:' . esc_attr( $transition_duration );
				$style_parts[] = '--dsgo-slider-slides-per-view:' . esc_attr( (string) $effective_slides );
				$style_parts[] = '--dsgo-slider-slides-per-view-tablet:' . esc_attr( (string) $effective_slides_tablet );
				$style_parts[] = '--dsgo-slider-slides-per-view-mobile:' . esc_attr( (string) $effective_slides_mobile );
				if ( $arrow_color ) {
					$style_parts[] = '--dsgo-slider-arrow-color:' . esc_attr( self::convert_color_value_to_css_var( $arrow_color ) );
				}
				if ( $arrow_bg_color ) {
					$style_parts[] = '--dsgo-slider-arrow-bg-color:' . esc_attr( self::convert_color_value_to_css_var( $arrow_bg_color ) );
				}
				if ( $arrow_size ) {
					$style_parts[] = '--dsgo-slider-arrow-size:' . esc_attr( $arrow_size );
				}
				if ( $arrow_padding ) {
					$style_parts[] = '--dsgo-slider-arrow-padding:' . esc_attr( $arrow_padding );
				}
				if ( $dot_color ) {
					$style_parts[] = '--dsgo-slider-dot-color:' . esc_attr( self::convert_color_value_to_css_var( $dot_color ) );
				}
				$style = implode( ';', $style_parts );

				// Build data attributes.
				$data_attrs  = ' data-slides-per-view="' . esc_attr( (string) $effective_slides ) . '"';
				$data_attrs .= ' data-slides-per-view-tablet="' . esc_attr( (string) $effective_slides_tablet ) . '"';
				$data_attrs .= ' data-slides-per-view-mobile="' . esc_attr( (string) $effective_slides_mobile ) . '"';
				$data_attrs .= ' data-use-aspect-ratio="' . ( $use_aspect_ratio ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-show-arrows="' . ( $show_arrows ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-show-dots="' . ( $show_dots ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-arrow-style="' . esc_attr( $arrow_style ) . '"';
				$data_attrs .= ' data-arrow-position="' . esc_attr( $arrow_position ) . '"';
				$data_attrs .= ' data-arrow-vertical-position="' . esc_attr( $arrow_vertical_pos ) . '"';
				$data_attrs .= ' data-dot-style="' . esc_attr( $dot_style ) . '"';
				$data_attrs .= ' data-dot-position="' . esc_attr( $dot_position ) . '"';
				$data_attrs .= ' data-effect="' . esc_attr( $effect ) . '"';
				$data_attrs .= ' data-transition-duration="' . esc_attr( $transition_duration ) . '"';
				$data_attrs .= ' data-transition-easing="' . esc_attr( $transition_easing ) . '"';
				$data_attrs .= ' data-autoplay="' . ( $autoplay ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-autoplay-interval="' . esc_attr( (string) $autoplay_interval ) . '"';
				$data_attrs .= ' data-pause-on-hover="' . ( $pause_on_hover ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-pause-on-interaction="' . ( $pause_on_interaction ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-loop="' . ( $loop ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-draggable="' . ( $draggable ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-swipeable="' . ( $swipeable ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-free-mode="' . ( $free_mode ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-centered-slides="' . ( $centered_slides ? 'true' : 'false' ) . '"';
				$data_attrs .= ' data-mobile-breakpoint="' . esc_attr( (string) $mobile_breakpoint ) . '"';
				$data_attrs .= ' data-tablet-breakpoint="' . esc_attr( (string) $tablet_breakpoint ) . '"';
				$data_attrs .= ' data-active-slide="' . esc_attr( (string) $active_slide ) . '"';
				if ( $scroll_driven ) {
					$data_attrs .= ' data-scroll-driven="true"';
					$data_attrs .= ' data-scroll-driven-speed="' . esc_attr( (string) $scroll_driven_speed ) . '"';
				}

				$aria = $aria_label ? $aria_label : 'Image slider';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '"' . $data_attrs . ' role="region" aria-label="' . esc_attr( $aria ) . '" aria-roledescription="slider"><div class="dsgo-slider__viewport"><div class="dsgo-slider__track">',
					'closing' => '</div></div></div>',
				);

			case 'designsetgo/slide':
				$background_image    = isset( $attributes['backgroundImage'] ) ? $attributes['backgroundImage'] : array();
				$background_size     = isset( $attributes['backgroundSize'] ) ? $attributes['backgroundSize'] : 'cover';
				$background_position = isset( $attributes['backgroundPosition'] ) ? $attributes['backgroundPosition'] : 'center center';
				$background_repeat   = isset( $attributes['backgroundRepeat'] ) ? $attributes['backgroundRepeat'] : 'no-repeat';
				$overlay_color       = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '';
				$overlay_opacity     = isset( $attributes['overlayOpacity'] ) ? floatval( $attributes['overlayOpacity'] ) : 80;
				$content_v_align     = isset( $attributes['contentVerticalAlign'] ) ? $attributes['contentVerticalAlign'] : 'center';
				$content_h_align     = isset( $attributes['contentHorizontalAlign'] ) ? $attributes['contentHorizontalAlign'] : 'center';
				$min_height          = isset( $attributes['minHeight'] ) ? $attributes['minHeight'] : '';
				$bg_url              = isset( $background_image['url'] ) ? $background_image['url'] : '';

				// Build classes.
				$class_parts = array( 'wp-block-designsetgo-slide', 'dsgo-slide' );
				if ( $bg_url ) {
					$class_parts[] = 'dsgo-slide--has-background';
				}
				if ( $overlay_color ) {
					$class_parts[] = 'dsgo-slide--has-overlay';
				}

				// Build style.
				$style_parts = array();
				if ( $bg_url ) {
					$style_parts[] = 'background-image:url(' . esc_url( $bg_url ) . ')';
					$style_parts[] = 'background-size:' . esc_attr( $background_size );
					$style_parts[] = 'background-position:' . esc_attr( $background_position );
					$style_parts[] = 'background-repeat:' . esc_attr( $background_repeat );
				}
				if ( $overlay_color ) {
					$style_parts[] = '--dsgo-slide-overlay-color:' . esc_attr( $overlay_color );
					$style_parts[] = '--dsgo-slide-overlay-opacity:' . esc_attr( (string) ( $overlay_opacity / 100 ) );
				}
				$style_parts[] = '--dsgo-slide-content-vertical-align:' . esc_attr( $content_v_align );
				$style_parts[] = '--dsgo-slide-content-horizontal-align:' . esc_attr( $content_h_align );
				if ( $min_height ) {
					$style_parts[] = 'min-height:' . esc_attr( $min_height );
				}
				$style = implode( ';', $style_parts );

				// Overlay HTML.
				$overlay_html = '';
				if ( $overlay_color ) {
					$overlay_style = 'background-color:' . esc_attr( $overlay_color ) . ';opacity:' . esc_attr( (string) ( $overlay_opacity / 100 ) );
					$overlay_html  = '<div class="dsgo-slide__overlay" style="' . esc_attr( $overlay_style ) . '"></div>';
				}

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '" role="group" aria-roledescription="slide">' . $overlay_html . '<div class="dsgo-slide__content">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/scroll-marquee':
				$rows          = isset( $attributes['rows'] ) ? $attributes['rows'] : array();
				$scroll_speed  = isset( $attributes['scrollSpeed'] ) ? floatval( $attributes['scrollSpeed'] ) : 0.5;
				$image_height  = isset( $attributes['imageHeight'] ) ? $attributes['imageHeight'] : '200px';
				$image_width   = isset( $attributes['imageWidth'] ) ? $attributes['imageWidth'] : '300px';
				$gap           = isset( $attributes['gap'] ) ? $attributes['gap'] : '20px';
				$row_gap       = isset( $attributes['rowGap'] ) ? $attributes['rowGap'] : '20px';
				$border_radius = isset( $attributes['borderRadius'] ) ? $attributes['borderRadius'] : '8px';

				// Build style.
				$style_parts = array(
					'--dsgo-marquee-gap:' . esc_attr( $gap ),
					'--dsgo-marquee-row-gap:' . esc_attr( $row_gap ),
					'--dsgo-marquee-image-height:' . esc_attr( $image_height ),
					'--dsgo-marquee-image-width:' . esc_attr( $image_width ),
					'--dsgo-marquee-border-radius:' . esc_attr( $border_radius ),
				);
				$style       = implode( ';', $style_parts );

				// Build rows HTML.
				$rows_html = '';
				foreach ( $rows as $row ) {
					$direction = isset( $row['direction'] ) ? $row['direction'] : 'left';
					$images    = isset( $row['images'] ) ? $row['images'] : array();

					$rows_html .= '<div class="dsgo-scroll-marquee__row" data-direction="' . esc_attr( $direction ) . '">';
					$rows_html .= '<div class="dsgo-scroll-marquee__track">';

					// Render images 6 times for seamless infinite scroll.
					for ( $i = 0; $i < 6; $i++ ) {
						$rows_html .= '<div class="dsgo-scroll-marquee__track-segment">';
						foreach ( $images as $image ) {
							$img_url    = isset( $image['url'] ) ? $image['url'] : '';
							$img_alt    = isset( $image['alt'] ) ? $image['alt'] : '';
							$rows_html .= '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $img_alt ) . '" class="dsgo-scroll-marquee__image" loading="lazy"/>';
						}
						$rows_html .= '</div>';
					}

					$rows_html .= '</div></div>';
				}

				return array(
					'opening' => '<div class="wp-block-designsetgo-scroll-marquee dsgo-scroll-marquee" data-scroll-speed="' . esc_attr( (string) $scroll_speed ) . '" style="' . esc_attr( $style ) . '">' . $rows_html,
					'closing' => '</div>',
				);

			case 'designsetgo/tabs':
				$unique_id         = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 );
				$orientation       = isset( $attributes['orientation'] ) ? $attributes['orientation'] : 'horizontal';
				$active_tab        = isset( $attributes['activeTab'] ) ? intval( $attributes['activeTab'] ) : 0;
				$alignment         = isset( $attributes['alignment'] ) ? $attributes['alignment'] : 'left';
				$mobile_breakpoint = isset( $attributes['mobileBreakpoint'] ) ? intval( $attributes['mobileBreakpoint'] ) : 768;
				$mobile_mode       = isset( $attributes['mobileMode'] ) ? $attributes['mobileMode'] : 'accordion';
				$enable_deep_link  = isset( $attributes['enableDeepLinking'] ) ? $attributes['enableDeepLinking'] : false;
				$gap               = isset( $attributes['gap'] ) ? $attributes['gap'] : '8px';
				$tab_style         = isset( $attributes['tabStyle'] ) ? $attributes['tabStyle'] : 'default';
				$show_nav_border   = isset( $attributes['showNavBorder'] ) ? $attributes['showNavBorder'] : false;

				// Build classes.
				$class_parts   = array( 'wp-block-designsetgo-tabs', 'dsgo-tabs', 'dsgo-tabs-' . esc_attr( $unique_id ) );
				$class_parts[] = 'dsgo-tabs--' . esc_attr( $orientation );
				$class_parts[] = 'dsgo-tabs--' . esc_attr( $tab_style );
				$class_parts[] = 'dsgo-tabs--align-' . esc_attr( $alignment );
				if ( $show_nav_border ) {
					$class_parts[] = 'dsgo-tabs--show-nav-border';
				}

				// Build style.
				$style = '--dsgo-tabs-gap:' . esc_attr( $gap );

				// Data attributes.
				$data_attrs  = ' data-active-tab="' . esc_attr( (string) $active_tab ) . '"';
				$data_attrs .= ' data-mobile-breakpoint="' . esc_attr( (string) $mobile_breakpoint ) . '"';
				$data_attrs .= ' data-mobile-mode="' . esc_attr( $mobile_mode ) . '"';
				$data_attrs .= ' data-deep-linking="' . ( $enable_deep_link ? 'true' : 'false' ) . '"';

				return array(
					'opening' => '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '" style="' . esc_attr( $style ) . '"' . $data_attrs . '><div class="dsgo-tabs__nav" role="tablist"></div><div class="dsgo-tabs__panels">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/tab':
				$unique_id     = isset( $attributes['uniqueId'] ) ? $attributes['uniqueId'] : substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 );
				$title         = isset( $attributes['title'] ) ? $attributes['title'] : 'Tab';
				$anchor        = isset( $attributes['anchor'] ) ? $attributes['anchor'] : '';
				$icon          = isset( $attributes['icon'] ) ? $attributes['icon'] : '';
				$icon_position = isset( $attributes['iconPosition'] ) ? $attributes['iconPosition'] : 'none';

				// Build panel ID.
				$panel_id = 'panel-' . ( $anchor ? esc_attr( $anchor ) : esc_attr( $unique_id ) );

				// Build aria-label.
				$aria_label = $title ? $title : 'Tab ' . $unique_id;

				// Data attributes for icon.
				$icon_data = '';
				if ( $icon && $icon_position && 'none' !== $icon_position ) {
					$safe_icon     = strtolower( preg_replace( '/[^a-z0-9\-]/i', '', $icon ) );
					$safe_position = in_array( $icon_position, array( 'left', 'right' ), true ) ? $icon_position : 'left';
					$icon_data     = ' data-icon="' . esc_attr( $safe_icon ) . '" data-icon-position="' . esc_attr( $safe_position ) . '"';
				}

				return array(
					'opening' => '<div class="wp-block-designsetgo-tab dsgo-tab" role="tabpanel" aria-labelledby="tab-' . esc_attr( $unique_id ) . '" aria-label="' . esc_attr( $aria_label ) . '" id="' . esc_attr( $panel_id ) . '" hidden' . $icon_data . '><div class="dsgo-tab__content">',
					'closing' => '</div></div>',
				);

			case 'designsetgo/form-builder':
				return self::generate_form_builder_html( $block_class, $attributes );

			default:
				return null;
		}
	}

	/**
	 * Generate wrapper HTML for form-builder block.
	 *
	 * @param string               $block_class Base block class.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string> Array with 'opening' and 'closing' keys.
	 */
	private static function generate_form_builder_html( string $block_class, array $attributes ): array {
		// Get attributes with defaults from block.json.
		$form_id                          = $attributes['formId'] ?? '';
		$submit_button_text               = $attributes['submitButtonText'] ?? 'Submit';
		$submit_button_alignment          = $attributes['submitButtonAlignment'] ?? 'left';
		$submit_button_position           = $attributes['submitButtonPosition'] ?? 'below';
		$ajax_submit                      = $attributes['ajaxSubmit'] ?? true;
		$success_message                  = $attributes['successMessage'] ?? 'Thank you! Your form has been submitted successfully.';
		$error_message                    = $attributes['errorMessage'] ?? 'There was an error submitting the form. Please try again.';
		$field_spacing                    = $attributes['fieldSpacing'] ?? '1.5rem';
		$input_height                     = $attributes['inputHeight'] ?? '44px';
		$input_padding                    = $attributes['inputPadding'] ?? '0.75rem';
		$field_label_color                = $attributes['fieldLabelColor'] ?? '';
		$field_border_color               = $attributes['fieldBorderColor'] ?? '';
		$field_background_color           = $attributes['fieldBackgroundColor'] ?? '';
		$submit_button_color              = $attributes['submitButtonColor'] ?? '';
		$submit_button_background_color   = $attributes['submitButtonBackgroundColor'] ?? '';
		$submit_button_padding_vertical   = $attributes['submitButtonPaddingVertical'] ?? '0.75rem';
		$submit_button_padding_horizontal = $attributes['submitButtonPaddingHorizontal'] ?? '2rem';
		$submit_button_font_size          = $attributes['submitButtonFontSize'] ?? '';
		$submit_button_height             = $attributes['submitButtonHeight'] ?? '44px';
		$enable_honeypot                  = $attributes['enableHoneypot'] ?? true;
		$enable_turnstile                 = $attributes['enableTurnstile'] ?? false;
		$enable_email                     = $attributes['enableEmail'] ?? false;
		$email_to                         = $attributes['emailTo'] ?? '';
		$email_subject                    = $attributes['emailSubject'] ?? 'New Form Submission';
		$email_from_name                  = $attributes['emailFromName'] ?? '';
		$email_from_email                 = $attributes['emailFromEmail'] ?? '';
		$email_reply_to                   = $attributes['emailReplyTo'] ?? '';
		$email_body                       = $attributes['emailBody'] ?? '';

		// Build classes - must match save.js.
		$classes = $block_class;
		if ( $submit_button_alignment && 'below' === $submit_button_position ) {
			$classes .= ' dsgo-form-builder--align-' . $submit_button_alignment;
		}
		if ( 'inline' === $submit_button_position ) {
			$classes .= ' dsgo-form-builder--button-inline';
		}

		// Build CSS custom properties - must match save.js order.
		$style_parts = array(
			'--dsgo-form-field-spacing:' . esc_attr( $field_spacing ),
			'--dsgo-form-input-height:' . esc_attr( $input_height ),
			'--dsgo-form-input-padding:' . esc_attr( $input_padding ),
		);
		if ( $field_label_color ) {
			$style_parts[] = '--dsgo-form-label-color:' . esc_attr( $field_label_color );
		}
		// fieldBorderColor defaults to #d1d5db if empty.
		$style_parts[] = '--dsgo-form-border-color:' . esc_attr( $field_border_color ? $field_border_color : '#d1d5db' );
		if ( $field_background_color ) {
			$style_parts[] = '--dsgo-form-field-bg:' . esc_attr( $field_background_color );
		}
		$style = implode( ';', $style_parts );

		// Build data attributes.
		$data_attrs = array(
			'data-form-id="' . esc_attr( $form_id ) . '"',
			'data-ajax-submit="' . ( $ajax_submit ? 'true' : 'false' ) . '"',
			'data-success-message="' . esc_attr( $success_message ) . '"',
			'data-error-message="' . esc_attr( $error_message ) . '"',
			'data-submit-text="' . esc_attr( $submit_button_text ) . '"',
			'data-enable-email="' . ( $enable_email ? 'true' : 'false' ) . '"',
			'data-email-to="' . esc_attr( $email_to ) . '"',
			'data-email-subject="' . esc_attr( $email_subject ) . '"',
			'data-email-from-name="' . esc_attr( $email_from_name ) . '"',
			'data-email-from-email="' . esc_attr( $email_from_email ) . '"',
			'data-email-reply-to="' . esc_attr( $email_reply_to ) . '"',
			'data-email-body="' . esc_attr( $email_body ) . '"',
		);
		if ( $enable_turnstile ) {
			$data_attrs[] = 'data-dsgo-turnstile="true"';
		}
		$data_str = implode( ' ', $data_attrs );

		// Build button style - must match save.js order.
		$button_style_parts = array();
		if ( $submit_button_color ) {
			$button_style_parts[] = 'color:' . esc_attr( $submit_button_color );
		}
		if ( $submit_button_background_color ) {
			$button_style_parts[] = 'background-color:' . esc_attr( $submit_button_background_color );
		}
		$button_style_parts[] = 'min-height:' . esc_attr( $submit_button_height );
		$button_style_parts[] = 'padding-top:' . esc_attr( $submit_button_padding_vertical );
		$button_style_parts[] = 'padding-bottom:' . esc_attr( $submit_button_padding_vertical );
		$button_style_parts[] = 'padding-left:' . esc_attr( $submit_button_padding_horizontal );
		$button_style_parts[] = 'padding-right:' . esc_attr( $submit_button_padding_horizontal );
		if ( $submit_button_font_size ) {
			$button_style_parts[] = 'font-size:' . esc_attr( $submit_button_font_size );
		}
		$button_style = implode( ';', $button_style_parts );

		// Opening HTML: outer div + form + fields wrapper.
		$opening  = '<div class="' . esc_attr( $classes ) . '" style="' . $style . '" ' . $data_str . '>';
		$opening .= '<form class="dsgo-form" method="post" novalidate>';
		$opening .= '<div class="dsgo-form__fields">';

		// Closing HTML: depends on button position.
		$closing = '';

		// Inline button goes inside fields wrapper, before closing.
		if ( 'inline' === $submit_button_position ) {
			$closing .= '<button type="submit" class="dsgo-form__submit dsgo-form__submit--inline wp-element-button" style="' . $button_style . '">' . esc_html( $submit_button_text ) . '</button>';
		}

		// Close fields wrapper.
		$closing .= '</div>';

		// Honeypot field.
		if ( $enable_honeypot ) {
			$closing .= '<input type="text" name="dsg_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"/>';
		}

		// Hidden form ID.
		$closing .= '<input type="hidden" name="dsg_form_id" value="' . esc_attr( $form_id ) . '"/>';

		// Turnstile widget container.
		if ( $enable_turnstile ) {
			$closing .= '<div class="dsgo-turnstile-widget" data-dsgo-turnstile-container="true"></div>';
		}

		// Footer with button (below position).
		if ( 'below' === $submit_button_position ) {
			$closing .= '<div class="dsgo-form__footer">';
			$closing .= '<button type="submit" class="dsgo-form__submit wp-element-button" style="' . $button_style . '">' . esc_html( $submit_button_text ) . '</button>';
			$closing .= '</div>';
		}

		// Message container.
		$closing .= '<div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display:none"></div>';

		// Close form and outer div.
		$closing .= '</form></div>';

		return array(
			'opening' => $opening,
			'closing' => $closing,
		);
	}

	/**
	 * Generate HTML for DesignSetGo blocks without inner blocks.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string|null Generated HTML or null if not supported.
	 */
	private static function generate_designsetgo_block_html( string $block_name, array $attributes ): ?string {
		return Form_Field_Html_Generator::generate( $block_name, $attributes );
	}


	/**
	 * Generate HTML for core WordPress blocks.
	 *
	 * @param string               $block_name Block name.
	 * @param string               $content Content text.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Generated HTML.
	 */
	private static function generate_core_block_html( string $block_name, string $content, array $attributes ): string {
		$text_align = isset( $attributes['textAlign'] ) ? ' has-text-align-' . $attributes['textAlign'] : '';
		$align      = isset( $attributes['align'] ) ? ' has-text-align-' . $attributes['align'] : '';

		switch ( $block_name ) {
			case 'core/heading':
				$level = isset( $attributes['level'] ) ? (int) $attributes['level'] : 2;
				$class = 'wp-block-heading' . $text_align;
				return '<h' . $level . ' class="' . trim( $class ) . '">' . wp_kses_post( $content ) . '</h' . $level . '>';

			case 'core/paragraph':
				$class      = trim( $align );
				$class_attr = $class ? ' class="' . $class . '"' : '';
				return '<p' . $class_attr . '>' . wp_kses_post( $content ) . '</p>';

			default:
				return wp_kses_post( $content );
		}
	}

	/**
	 * Coerce attribute types to match block.json schema.
	 *
	 * Ensures numeric attributes are stored as numbers (not strings) so that
	 * WordPress block validation doesn't fail due to type mismatches.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Attributes to coerce.
	 * @return array<string, mixed> Attributes with corrected types.
	 */
	private static function coerce_attribute_types( string $block_name, array $attributes ): array {
		// Define numeric attributes for each block that needs type coercion.
		$numeric_attrs = array(
			'designsetgo/divider'       => array( 'width', 'thickness' ),
			'designsetgo/counter'       => array( 'startValue', 'endValue', 'duration', 'delay', 'decimals' ),
			'designsetgo/counter-group' => array( 'desktopColumns', 'tabletColumns', 'mobileColumns', 'gap', 'animationDuration', 'animationDelay' ),
			'designsetgo/grid'          => array( 'desktopColumns', 'tabletColumns', 'mobileColumns' ),
			'designsetgo/icon'          => array( 'size', 'iconSize', 'strokeWidth', 'rotation' ),
			'designsetgo/icon-button'   => array( 'iconSize' ),
		);

		if ( ! isset( $numeric_attrs[ $block_name ] ) ) {
			return $attributes;
		}

		foreach ( $numeric_attrs[ $block_name ] as $attr_name ) {
			if ( isset( $attributes[ $attr_name ] ) && is_string( $attributes[ $attr_name ] ) ) {
				// Cast to integer or float as appropriate.
				if ( false !== strpos( $attributes[ $attr_name ], '.' ) ) {
					$attributes[ $attr_name ] = (float) $attributes[ $attr_name ];
				} else {
					$attributes[ $attr_name ] = (int) $attributes[ $attr_name ];
				}
			}
		}

		return $attributes;
	}

	/**
	 * Normalize block attributes to include required defaults.
	 *
	 * Ensures attributes like layout have all required fields for block validation.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Attributes to normalize.
	 * @return array<string, mixed> Normalized attributes.
	 */
	private static function normalize_block_attributes( string $block_name, array $attributes ): array {
		switch ( $block_name ) {
			case 'designsetgo/row':
				// Ensure layout has all required fields for block validation.
				$layout               = isset( $attributes['layout'] ) ? $attributes['layout'] : array();
				$attributes['layout'] = array_merge(
					array(
						'type'           => 'flex',
						'orientation'    => 'horizontal',
						'justifyContent' => 'left',
						'flexWrap'       => 'nowrap',
					),
					$layout
				);
				break;

			case 'designsetgo/grid':
				// Ensure column counts are set for block validation.
				if ( ! isset( $attributes['desktopColumns'] ) ) {
					$attributes['desktopColumns'] = 3;
				}
				if ( ! isset( $attributes['tabletColumns'] ) ) {
					$attributes['tabletColumns'] = 2;
				}
				if ( ! isset( $attributes['mobileColumns'] ) ) {
					$attributes['mobileColumns'] = 1;
				}
				break;

			case 'designsetgo/accordion-item':
				// Ensure uniqueId is set for accessibility attributes.
				if ( ! isset( $attributes['uniqueId'] ) ) {
					$attributes['uniqueId'] = 'accordion-item-' . wp_generate_uuid4();
				}
				break;

			case 'designsetgo/counter':
				// Ensure uniqueId is set for element ID.
				if ( ! isset( $attributes['uniqueId'] ) ) {
					$attributes['uniqueId'] = 'counter-' . wp_generate_uuid4();
				}
				break;
		}

		return $attributes;
	}

	/**
	 * Fill missing attributes with their block.json defaults.
	 *
	 * The wrapper HTML generators previously carried their own fallback
	 * defaults, which drifted from block.json over time and produced markup
	 * that didn't round-trip through save(). Applying block.json defaults up
	 * front keeps the ability in sync with the single source of truth.
	 *
	 * Scoped to an allowlist rather than applied to every block: other
	 * wrapper generators have not been audited against their save() output,
	 * so forcing block.json defaults on them could expose unrelated latent
	 * mismatches. Add blocks here as their wrappers are verified.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Incoming attributes.
	 * @return array<string, mixed> Attributes merged with block.json defaults.
	 */
	private static function apply_block_json_defaults( string $block_name, array $attributes ): array {
		// Only slider and map have been audited end-to-end against their
		// save.js output. Add blocks here as their wrapper HTML is verified.
		$verified_blocks = array(
			'designsetgo/map',
			'designsetgo/slider',
		);

		if ( ! in_array( $block_name, $verified_blocks, true ) ) {
			return $attributes;
		}

		$registry   = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );

		if ( ! $block_type || empty( $block_type->attributes ) ) {
			return $attributes;
		}

		foreach ( $block_type->attributes as $attr_name => $attr_def ) {
			if ( array_key_exists( $attr_name, $attributes ) ) {
				continue;
			}
			if ( ! array_key_exists( 'default', $attr_def ) ) {
				continue;
			}
			$attributes[ $attr_name ] = $attr_def['default'];
		}

		return $attributes;
	}

	/**
	 * Strip attributes that match their block.json defaults.
	 *
	 * WordPress's block serialization omits attributes that equal the
	 * registered default. This method mirrors that behavior so inserted
	 * blocks produce the same comment markup as the editor.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed> Attributes with defaults removed.
	 */
	private static function strip_default_attributes( string $block_name, array $attributes ): array {
		$registry   = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );

		if ( ! $block_type || empty( $block_type->attributes ) ) {
			return $attributes;
		}

		foreach ( $block_type->attributes as $attr_name => $attr_def ) {
			if ( ! array_key_exists( $attr_name, $attributes ) || ! array_key_exists( 'default', $attr_def ) ) {
				continue;
			}
			// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- intentional loose comparison for type-coerced defaults.
			if ( $attributes[ $attr_name ] == $attr_def['default'] ) {
				unset( $attributes[ $attr_name ] );
			}
		}

		return $attributes;
	}

	/**
	 * Convert CSS var() syntax to WordPress shorthand for block comment serialization.
	 *
	 * WordPress stores preset values as `var:preset|spacing|60` in block comments,
	 * which gets converted to `var(--wp--preset--spacing--60)` at render time.
	 *
	 * @param string $value CSS value that may contain var(--wp--preset--*) syntax.
	 * @return string Converted value using WordPress shorthand, or original value.
	 */
	private static function css_var_to_wp_shorthand( string $value ): string {
		if ( preg_match( '/^var\(--wp--preset--([a-zA-Z]+)--(.+)\)$/', $value, $matches ) ) {
			return 'var:preset|' . $matches[1] . '|' . $matches[2];
		}
		return $value;
	}

	/**
	 * Convert WordPress shorthand var:preset|*|* to CSS var() syntax for inline styles.
	 *
	 * @param string $value WordPress shorthand value.
	 * @return string CSS var() value.
	 */
	private static function wp_shorthand_to_css_var( string $value ): string {
		if ( preg_match( '/^var:preset\|([a-zA-Z]+)\|(.+)$/', $value, $matches ) ) {
			return 'var(--wp--preset--' . $matches[1] . '--' . $matches[2] . ')';
		}
		return $value;
	}

	/**
	 * Convert a color value to CSS var() syntax, mirroring the JS save helper.
	 *
	 * Supports WordPress preset shorthand (`var:preset|color|slug`), already-
	 * valid CSS values, and bare preset slugs such as `accent-3`.
	 *
	 * @param string $value Color value.
	 * @return string Converted CSS value.
	 */
	private static function convert_color_value_to_css_var( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strpos( $value, 'var(--' ) ) {
			return $value;
		}

		if ( 0 === strpos( $value, 'var:preset|' ) ) {
			return self::wp_shorthand_to_css_var( $value );
		}

		if ( preg_match( '/^(#|rgb|hsl|hwb|lab|lch|oklch|oklab|color\(|var\(|url\(|\d)/i', $value ) ) {
			return $value;
		}

		$css_keywords = array(
			'transparent',
			'inherit',
			'initial',
			'unset',
			'revert',
			'revert-layer',
			'currentcolor',
			'none',
			'auto',
			'normal',
		);

		if ( in_array( strtolower( $value ), $css_keywords, true ) ) {
			return $value;
		}

		return 'var(--wp--preset--color--' . $value . ')';
	}

	/**
	 * Recursively convert CSS var() syntax to WordPress shorthand in style arrays.
	 *
	 * @param array<string, mixed> $style_array Style attribute array.
	 * @return array<string, mixed> Converted style array.
	 */
	private static function convert_style_vars( array $style_array ): array {
		foreach ( $style_array as $key => $value ) {
			if ( is_array( $value ) ) {
				$style_array[ $key ] = self::convert_style_vars( $value );
			} elseif ( is_string( $value ) ) {
				$style_array[ $key ] = self::css_var_to_wp_shorthand( $value );
			}
		}
		return $style_array;
	}

	/**
	 * Extract block support classes and inline styles from the style attribute.
	 *
	 * Processes color, spacing, and other block supports into CSS classes and
	 * inline style strings, mirroring what useBlockProps.save() does in JS.
	 *
	 * @param array<string, mixed> $style Style attribute from block.
	 * @return array{classes: string[], styles: string[]} Classes and style declarations.
	 */
	private static function get_block_support_styles( array $style ): array {
		$classes = array();
		$styles  = array();

		// Color support.
		if ( ! empty( $style['color']['background'] ) ) {
			$classes[] = 'has-background';
			$styles[]  = 'background-color:' . esc_attr( $style['color']['background'] );
		}
		if ( ! empty( $style['color']['text'] ) ) {
			$classes[] = 'has-text-color';
			$styles[]  = 'color:' . esc_attr( $style['color']['text'] );
		}
		if ( ! empty( $style['color']['gradient'] ) ) {
			$classes[] = 'has-background';
			$styles[]  = 'background:' . esc_attr( $style['color']['gradient'] );
		}

		// Spacing support - padding.
		if ( ! empty( $style['spacing']['padding'] ) ) {
			$padding = $style['spacing']['padding'];
			if ( ! empty( $padding['top'] ) ) {
				$styles[] = 'padding-top:' . esc_attr( self::wp_shorthand_to_css_var( $padding['top'] ) );
			}
			if ( ! empty( $padding['right'] ) ) {
				$styles[] = 'padding-right:' . esc_attr( self::wp_shorthand_to_css_var( $padding['right'] ) );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$styles[] = 'padding-bottom:' . esc_attr( self::wp_shorthand_to_css_var( $padding['bottom'] ) );
			}
			if ( ! empty( $padding['left'] ) ) {
				$styles[] = 'padding-left:' . esc_attr( self::wp_shorthand_to_css_var( $padding['left'] ) );
			}
		}

		// Spacing support - margin.
		if ( ! empty( $style['spacing']['margin'] ) ) {
			$margin = $style['spacing']['margin'];
			if ( ! empty( $margin['top'] ) ) {
				$styles[] = 'margin-top:' . esc_attr( self::wp_shorthand_to_css_var( $margin['top'] ) );
			}
			if ( ! empty( $margin['bottom'] ) ) {
				$styles[] = 'margin-bottom:' . esc_attr( self::wp_shorthand_to_css_var( $margin['bottom'] ) );
			}
		}

		// Dimensions support.
		if ( ! empty( $style['dimensions']['minHeight'] ) ) {
			$styles[] = 'min-height:' . esc_attr( self::wp_shorthand_to_css_var( $style['dimensions']['minHeight'] ) );
		}

		return array(
			'classes' => $classes,
			'styles'  => $styles,
		);
	}

	/**
	 * Blocks that carry BOTH a save.js (static HTML saved to post content)
	 * AND a render.php (dynamic transform at display time). For insertion
	 * purposes these should be treated as authored-mode blocks — their
	 * save.js output is the canonical wrapper HTML. Without this allowlist,
	 * Block_Inserter would skip wrapper HTML generation because the render
	 * callback is non-null, producing an empty innerHTML.
	 *
	 * V2.6: slider + scroll-slides became hybrid to support item-host
	 * rendering inside designsetgo/query.
	 */
	private const HYBRID_BLOCKS = array(
		'designsetgo/slider',
		'designsetgo/scroll-slides',
	);

	/**
	 * Check if a block is dynamic (has a render callback).
	 *
	 * Dynamic blocks are rendered server-side via PHP and should not have
	 * wrapper HTML generated during insertion. Hybrid blocks (see
	 * HYBRID_BLOCKS) return false here because their save.js output is still
	 * authoritative at insertion time.
	 *
	 * @param string $block_name Block name (e.g., 'designsetgo/section').
	 * @return bool True if block has a render callback, false otherwise.
	 */
	private static function is_dynamic_block( string $block_name ): bool {
		if ( in_array( $block_name, self::HYBRID_BLOCKS, true ) ) {
			return false;
		}

		$registry   = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );

		if ( ! $block_type ) {
			return false;
		}

		// Check if block has a render callback.
		return null !== $block_type->render_callback;
	}

	/**
	 * Sanitize block attributes recursively.
	 *
	 * @param array<string, mixed> $attributes Attributes to sanitize.
	 * @return array<string, mixed> Sanitized attributes.
	 */
	public static function sanitize_attributes( array $attributes ): array {
		$sanitized = array();

		foreach ( $attributes as $key => $value ) {
			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_attributes( $value );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || is_null( $value ) ) {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Validate inner blocks structure.
	 *
	 * @param array<int, array<string, mixed>> $inner_blocks Inner blocks array.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_inner_blocks( array $inner_blocks ) {
		foreach ( $inner_blocks as $index => $block ) {
			if ( ! isset( $block['name'] ) || ! is_string( $block['name'] ) ) {
				return new WP_Error(
					'invalid_inner_block',
					sprintf(
						/* translators: %d: Block index */
						__( 'Inner block at index %d is missing a valid name.', 'designsetgo' ),
						$index
					)
				);
			}

			if ( isset( $block['attributes'] ) && ! is_array( $block['attributes'] ) ) {
				return new WP_Error(
					'invalid_inner_block_attributes',
					sprintf(
						/* translators: %d: Block index */
						__( 'Inner block at index %d has invalid attributes (must be an array).', 'designsetgo' ),
						$index
					)
				);
			}

			if ( isset( $block['innerBlocks'] ) && ! is_array( $block['innerBlocks'] ) ) {
				return new WP_Error(
					'invalid_nested_blocks',
					sprintf(
						/* translators: %d: Block index */
						__( 'Inner block at index %d has invalid innerBlocks (must be an array).', 'designsetgo' ),
						$index
					)
				);
			}

			// Recursively validate nested inner blocks.
			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$nested_validation = self::validate_inner_blocks( $block['innerBlocks'] );
				if ( is_wp_error( $nested_validation ) ) {
					return $nested_validation;
				}
			}
		}

		return true;
	}

	/**
	 * Get default common input schema properties.
	 *
	 * @return array<string, mixed> Common schema properties.
	 */
	public static function get_common_input_schema(): array {
		return array(
			'post_id'  => array(
				'type'        => 'integer',
				'description' => __( 'Target post ID', 'designsetgo' ),
			),
			'position' => array(
				'type'        => 'integer',
				'description' => __( 'Block position (0 = prepend, -1 = append, or specific index)', 'designsetgo' ),
				'default'     => -1,
			),
		);
	}

	/**
	 * Get default output schema.
	 *
	 * @return array<string, mixed> Output schema.
	 */
	public static function get_default_output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'success'  => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the operation was successful', 'designsetgo' ),
				),
				'post_id'  => array(
					'type'        => 'integer',
					'description' => __( 'Post ID where block was inserted', 'designsetgo' ),
				),
				'block_id' => array(
					'type'        => 'string',
					'description' => __( 'Unique block identifier', 'designsetgo' ),
				),
				'position' => array(
					'type'        => 'integer',
					'description' => __( 'Position where block was inserted', 'designsetgo' ),
				),
			),
			'required'   => array( 'success' ),
		);
	}

	/**
	 * Build inner blocks array from simplified definitions.
	 *
	 * Converts a simplified block definition format into the WordPress
	 * block array format suitable for use in innerBlocks.
	 *
	 * @param array<int, array<string, mixed>> $definitions Block definitions with 'name', 'attributes', 'innerBlocks'.
	 * @return array<int, array<string, mixed>> WordPress-formatted blocks.
	 */
	public static function build_inner_blocks( array $definitions ): array {
		$blocks = array();

		foreach ( $definitions as $def ) {
			$block = array(
				'blockName'    => $def['name'] ?? 'core/paragraph',
				'attrs'        => self::sanitize_attributes( $def['attributes'] ?? array() ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);

			if ( ! empty( $def['innerBlocks'] ) ) {
				$block['innerBlocks']  = self::build_inner_blocks( $def['innerBlocks'] );
				$block['innerContent'] = array( null );
			}

			$blocks[] = $block;
		}

		return $blocks;
	}
}

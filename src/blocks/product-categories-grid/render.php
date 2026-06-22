<?php
/**
 * Product Categories Grid Block - Server-side Rendering
 *
 * Renders WooCommerce product categories in a responsive grid layout with
 * category images, names, and product counts. Supports both "all categories"
 * and manually curated category selection modes.
 *
 * @package DesignSetGo
 * @since 2.1.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content (unused for dynamic blocks).
 * @param WP_Block $block      Block instance.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'designsetgo_render_product_categories_grid' ) ) {
	/**
	 * Render the Product Categories Grid block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content.
	 * @param WP_Block $block      Block instance.
	 * @return void
	 */
	function designsetgo_render_product_categories_grid( $attributes, $content, $block ) {
		// Bail if WooCommerce is not active.
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		// ---------------------------------------------------------------------------
		// Extract and validate attributes.
		// ---------------------------------------------------------------------------

		$category_source     = isset( $attributes['categorySource'] ) && 'manual' === $attributes['categorySource'] ? 'manual' : 'all';
		$selected_categories = isset( $attributes['selectedCategories'] ) && is_array( $attributes['selectedCategories'] ) ? $attributes['selectedCategories'] : array();
		$exclude_categories  = isset( $attributes['excludeCategories'] ) && is_array( $attributes['excludeCategories'] ) ? $attributes['excludeCategories'] : array();
		$columns             = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 3;
		$show_product_count  = ! isset( $attributes['showProductCount'] ) || (bool) $attributes['showProductCount'];
		$show_empty          = ! empty( $attributes['showEmpty'] );
		$image_aspect_ratio  = isset( $attributes['imageAspectRatio'] ) ? $attributes['imageAspectRatio'] : '3/4';
		$overlay_position    = isset( $attributes['overlayPosition'] ) && 'center' === $attributes['overlayPosition'] ? 'center' : 'bottom-left';

		// Validate columns (2–5).
		if ( $columns < 2 || $columns > 5 ) {
			$columns = 3;
		}

		// Validate aspect ratio.
		$allowed_aspect_ratios = array( '1/1', '3/4', '4/3', '16/9' );
		if ( ! in_array( $image_aspect_ratio, $allowed_aspect_ratios, true ) ) {
			$image_aspect_ratio = '3/4';
		}

		// ---------------------------------------------------------------------------
		// Query categories based on the selected source mode.
		// ---------------------------------------------------------------------------

		if ( 'manual' === $category_source ) {

			// Build a list of sanitised category IDs in user-defined order.
			$selected_ids = array();
			$featured_map = array();

			foreach ( $selected_categories as $item ) {
				if ( ! isset( $item['id'] ) ) {
					continue;
				}
				$id = absint( $item['id'] );
				if ( ! $id ) {
					continue;
				}
				$selected_ids[] = $id;
				if ( ! empty( $item['featured'] ) ) {
					$featured_map[ $id ] = true;
				}
			}

			if ( empty( $selected_ids ) ) {
				return '';
			}

			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'include'    => $selected_ids,
					'hide_empty' => false,
					'orderby'    => 'include',
				)
			);

		} else {

			// All mode: exclude empty (optionally) and the actual "Uncategorized" category.
			$exclude_ids = array_map( 'absint', $exclude_categories );

			// Only exclude the default product category if its slug is 'uncategorized'.
			$default_cat_id = absint( get_option( 'default_product_cat', 0 ) );
			if ( $default_cat_id ) {
				$default_cat = get_term( $default_cat_id, 'product_cat' );
				if ( $default_cat && ! is_wp_error( $default_cat ) && 'uncategorized' === $default_cat->slug ) {
					$exclude_ids[] = $default_cat_id;
				}
			}
			$exclude_ids = array_unique( array_filter( $exclude_ids ) );

			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => 0,
					'hide_empty' => ! $show_empty,
					// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- get_terms() exclusion bounded to the default "uncategorized" product_cat term.
					'exclude'    => $exclude_ids,
					'orderby'    => 'menu_order',
					'order'      => 'ASC',
				)
			);

			$featured_map = array();
		}

		// Bail silently if there are no categories to display.
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		// ---------------------------------------------------------------------------
		// Build wrapper attributes.
		// ---------------------------------------------------------------------------

		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class'       => 'dsgo-product-categories-grid dsgo-product-categories-grid--cols-' . $columns,
				'role'        => 'navigation',
				'aria-label'  => __( 'Product categories', 'designsetgo' ),
				'style'       => '--dsgo-pcg-aspect-ratio: ' . esc_attr( $image_aspect_ratio ) . ';',
			)
		);

		// ---------------------------------------------------------------------------
		// Placeholder SVG (2×2 grid icon) for categories without an image.
		// ---------------------------------------------------------------------------

		$placeholder_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" aria-hidden="true" focusable="false">'
			. '<rect x="3" y="3" width="8" height="8" rx="1" fill="currentColor" opacity="0.4"/>'
			. '<rect x="13" y="3" width="8" height="8" rx="1" fill="currentColor" opacity="0.4"/>'
			. '<rect x="3" y="13" width="8" height="8" rx="1" fill="currentColor" opacity="0.4"/>'
			. '<rect x="13" y="13" width="8" height="8" rx="1" fill="currentColor" opacity="0.4"/>'
			. '</svg>';

		?>
		<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output. ?>>
			<?php foreach ( $terms as $term ) : ?>
				<?php
				$term_id    = absint( $term->term_id );
				$term_name  = $term->name;
				$term_count = absint( $term->count );
				$term_url   = get_term_link( $term );

				if ( is_wp_error( $term_url ) ) {
					continue;
				}

				// Determine if this card should span two columns (manual mode only).
				$is_featured = isset( $featured_map[ $term_id ] );

				// Resolve category image.
				$thumbnail_id = absint( get_term_meta( $term_id, 'thumbnail_id', true ) );
				$image_html   = '';
				if ( $thumbnail_id ) {
					$image_html = wp_get_attachment_image(
						$thumbnail_id,
						'medium_large',
						false,
						array(
							'class'   => 'dsgo-product-categories-grid__image',
							'alt'     => '',
							'loading' => 'lazy',
						)
					);
				}

				// Build card CSS classes.
				$card_class = 'dsgo-product-categories-grid__card';
				if ( $is_featured ) {
					$card_class .= ' dsgo-product-categories-grid__card--featured';
				}

				// Build accessible product count string.
				/* translators: %d: number of products in a category. */
				$count_text = sprintf( _n( '%d product', '%d products', $term_count, 'designsetgo' ), $term_count );
				?>
				<a href="<?php echo esc_url( $term_url ); ?>" class="<?php echo esc_attr( $card_class ); ?>">
					<?php if ( $image_html ) : ?>
						<?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output. ?>
					<?php else : ?>
						<div class="dsgo-product-categories-grid__placeholder-icon">
							<?php echo $placeholder_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG with no user data. ?>
						</div>
					<?php endif; ?>

					<div class="dsgo-product-categories-grid__overlay<?php echo 'center' === $overlay_position ? ' dsgo-product-categories-grid__overlay--center' : ''; ?>">
						<h3 class="dsgo-product-categories-grid__name">
							<?php echo esc_html( $term_name ); ?>
						</h3>

						<?php if ( $show_product_count ) : ?>
							<span class="dsgo-product-categories-grid__count" aria-hidden="true">
								<?php echo esc_html( $count_text ); ?>
							</span>
							<span class="screen-reader-text">
								<?php
								/* translators: 1: count text (e.g. "5 products"), 2: category name. */
								echo esc_html( sprintf( __( '%1$s in %2$s', 'designsetgo' ), $count_text, $term_name ) );
								?>
							</span>
						<?php endif; ?>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

designsetgo_render_product_categories_grid( $attributes, $content, $block );

<?php
/**
 * Product Showcase Hero Block - Server-side Rendering
 *
 * Renders a WooCommerce product in a full-width hero layout with
 * image, title, price, rating, stock status, and add-to-cart form.
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

if ( ! function_exists( 'designsetgo_render_product_showcase_hero' ) ) {
	/**
	 * Render the Product Showcase Hero block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content.
	 * @param WP_Block $block      Block instance.
	 * @return void
	 */
	function designsetgo_render_product_showcase_hero( $attributes, $content, $block ) {
		// Bail if WooCommerce is not active.
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		// Resolve product ID based on source mode.
		$product_source = isset( $attributes['productSource'] ) ? $attributes['productSource'] : 'manual';

		if ( 'current' === $product_source ) {
			// Use the current product context (single product pages, product templates).
			if ( isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof WC_Product ) {
				$product_id = $GLOBALS['product']->get_id();
			} else {
				$product_id = get_the_ID();
			}
		} else {
			$product_id = isset( $attributes['productId'] ) ? absint( $attributes['productId'] ) : 0;
		}

		if ( ! $product_id ) {
			return '';
		}

		// Get the product.
		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== get_post_status( $product_id ) ) {
			return '';
		}

		// Respect password-protected products.
		if ( post_password_required( $product_id ) ) {
			return get_the_password_form( $product_id );
		}

		// Extract display settings.
		$layout                = isset( $attributes['layout'] ) && 'media-right' === $attributes['layout'] ? 'media-right' : 'media-left';
		$image_size            = isset( $attributes['imageSize'] ) ? sanitize_key( $attributes['imageSize'] ) : 'large';
		$show_price            = ! isset( $attributes['showPrice'] ) || $attributes['showPrice'];
		$show_rating           = ! isset( $attributes['showRating'] ) || $attributes['showRating'];
		$show_stock_status     = ! isset( $attributes['showStockStatus'] ) || $attributes['showStockStatus'];
		$show_sale_badge       = ! isset( $attributes['showSaleBadge'] ) || $attributes['showSaleBadge'];
		$show_short_desc       = ! empty( $attributes['showShortDescription'] );
		$show_add_to_cart      = ! isset( $attributes['showAddToCart'] ) || $attributes['showAddToCart'];
		$show_variations       = ! isset( $attributes['showVariations'] ) || $attributes['showVariations'];
		$min_height            = isset( $attributes['minHeight'] ) ? $attributes['minHeight'] : '500px';
		$focal_point           = isset( $attributes['mediaFocalPoint'] ) ? $attributes['mediaFocalPoint'] : array( 'x' => 0.5, 'y' => 0.5 );
		$allowed_alignments    = array( 'top', 'center', 'bottom' );
		$vertical_alignment    = isset( $attributes['contentVerticalAlignment'] ) && in_array( $attributes['contentVerticalAlignment'], $allowed_alignments, true )
			? $attributes['contentVerticalAlignment']
			: 'center';

		// Validate image size.
		$allowed_sizes = array( 'medium', 'large', 'full' );
		if ( ! in_array( $image_size, $allowed_sizes, true ) ) {
			$image_size = 'large';
		}

		// Validate min height.
		$safe_min_height = ( $min_height && preg_match( '/^\d+(\.\d+)?(px|vh|vw|em|rem|%)$/', $min_height ) )
			? $min_height
			: '500px';

		// Build focal point CSS.
		$focal_x = isset( $focal_point['x'] ) ? max( 0, min( 1, floatval( $focal_point['x'] ) ) ) * 100 : 50;
		$focal_y = isset( $focal_point['y'] ) ? max( 0, min( 1, floatval( $focal_point['y'] ) ) ) * 100 : 50;
		$object_position = $focal_x . '% ' . $focal_y . '%';

		// Map vertical alignment.
		$align_map = array(
			'top'    => 'flex-start',
			'center' => 'center',
			'bottom' => 'flex-end',
		);
		$content_justify = isset( $align_map[ $vertical_alignment ] ) ? $align_map[ $vertical_alignment ] : 'center';

		// Get product image.
		$image_id  = $product->get_image_id();
		$image_html = '';
		if ( $image_id ) {
			$image_html = wp_get_attachment_image(
				$image_id,
				$image_size,
				false,
				array(
					'class'         => 'dsgo-product-showcase-hero__image',
					'style'         => 'object-position: ' . esc_attr( $object_position ) . ';',
					'loading'       => 'eager',
					'fetchpriority' => 'high',
				)
			);
		}

		// Build wrapper attributes.
		$wrapper_class = 'dsgo-product-showcase-hero dsgo-product-showcase-hero--' . $layout;
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class' => $wrapper_class,
				'style' => '--dsgo-psh-min-height: ' . esc_attr( $safe_min_height ) . '; --dsgo-psh-content-justify: ' . esc_attr( $content_justify ) . ';',
			)
		);

		// Set up global product for WooCommerce template functions.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		global $post;
		$original_post    = $post;
		$original_product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
		$post             = get_post( $product_id );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template functions require this global to be named exactly $product; swapped for loop context and restored below.
		$GLOBALS['product'] = $product;
		setup_postdata( $post );
		?>

		<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="dsgo-product-showcase-hero__media">
				<?php if ( $image_html ) : ?>
					<?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output. ?>
				<?php endif; ?>

				<?php if ( $show_sale_badge && $product->is_on_sale() ) : ?>
					<span class="dsgo-product-showcase-hero__sale-badge">
						<?php echo esc_html__( 'Sale!', 'designsetgo' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="dsgo-product-showcase-hero__content">
				<div class="dsgo-product-showcase-hero__content-inner">
					<h2 class="dsgo-product-showcase-hero__title">
						<?php echo esc_html( $product->get_name() ); ?>
					</h2>

					<?php if ( $show_price ) : ?>
						<div class="dsgo-product-showcase-hero__price">
							<?php echo wp_kses_post( $product->get_price_html() ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $show_rating && $product->get_average_rating() > 0 ) : ?>
						<div class="dsgo-product-showcase-hero__rating">
							<?php
							echo wp_kses_post(
								wc_get_rating_html( $product->get_average_rating(), $product->get_review_count() )
							);
							?>
						</div>
					<?php endif; ?>

					<?php if ( $show_stock_status ) : ?>
						<?php
						$stock_class = $product->is_in_stock() ? 'instock' : 'outofstock';
						$stock_text  = $product->is_in_stock()
							? __( 'In stock', 'designsetgo' )
							: __( 'Out of stock', 'designsetgo' );
						?>
						<div class="dsgo-product-showcase-hero__stock dsgo-product-showcase-hero__stock--<?php echo esc_attr( $stock_class ); ?>">
							<?php echo esc_html( $stock_text ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $show_short_desc && $product->get_short_description() ) : ?>
						<div class="dsgo-product-showcase-hero__description">
							<?php echo wp_kses_post( $product->get_short_description() ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $show_add_to_cart ) : ?>
						<div class="dsgo-product-showcase-hero__actions wp-block-button">
							<?php
							// Capture WC button output and add wp-element-button class
							// so the button inherits theme.json button styles.
							ob_start();
							if ( $show_variations && $product->is_type( 'variable' ) ) {
								woocommerce_template_single_add_to_cart();
							} else {
								woocommerce_template_loop_add_to_cart();
							}
							$add_to_cart_html = ob_get_clean();
							echo str_replace( 'class="button', 'class="button wp-block-button__link wp-element-button', $add_to_cart_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce template output.
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php
		// Restore original globals (manual restoration is safer than wp_reset_postdata()
		// because it correctly handles secondary query loops where $wp_query->post differs).
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post               = $original_post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- restoring WooCommerce's original global $product after the loop; the name is fixed by WooCommerce.
		$GLOBALS['product'] = $original_product;
		if ( $post ) {
			setup_postdata( $post );
		}
	}
}

designsetgo_render_product_showcase_hero( $attributes, $content, $block );

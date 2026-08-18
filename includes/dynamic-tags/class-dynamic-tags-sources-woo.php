<?php
/**
 * Dynamic Tags — WooCommerce product sources.
 *
 * Deliberately narrow. A spike (docs/plans/2026-08-17-woocommerce-surface.md, D4)
 * established that WooCommerce's own display blocks — product-price, product-sku,
 * product-rating, product-image, product-stock-indicator, product-button — already
 * render correctly inside a `designsetgo/query` product loop, because they consume
 * the core `postId` context that `render-posts.php` supplies per item. Duplicating
 * them as bindings would add surface without adding capability.
 *
 * What Woo exposes through no block at all is the *raw scalars*: an unformatted
 * price, a numeric stock quantity, a discount percentage. Those are what
 * `dsgoStyleBinding` needs to drive a CSS custom property — a stock bar is
 * `progress-bar` plus `--dsgo-progress: <stock>`, which no Woo block can provide.
 * That gap is this file's entire reason to exist.
 *
 * `woo-price-html` is the one formatted source kept, for the narrower case of
 * driving a *DesignSetGo* block (a price inside a `pill` or `advanced-heading`
 * with DSGo typography) rather than accepting Woo's own markup.
 *
 * @package DesignSetGo
 * @since   2.7.0
 */

namespace DesignSetGo\Blocks\DynamicTags;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `designsetgo/woo-*` binding sources.
 */
class WooSources {

	/**
	 * Registers all WooCommerce sources and their registry metadata.
	 *
	 * No-ops entirely when WooCommerce is absent, so the picker never offers a
	 * source that cannot resolve.
	 *
	 * @param Registry $registry Metadata registry.
	 */
	public static function register( Registry $registry ) {
		if ( ! function_exists( 'designsetgo_register_bindings_source' ) ) {
			return;
		}

		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		$registry->register_group( 'woocommerce', __( 'WooCommerce', 'designsetgo' ), 60 );

		self::register_one(
			$registry,
			'designsetgo/woo-price-html',
			__( 'Price (formatted)', 'designsetgo' ),
			array( 'html' ),
			static function ( $args ) {
				$product = self::resolve_product( $args );
				if ( ! $product ) {
					return null;
				}

				$html = $product->get_price_html();

				return '' === $html ? null : $html;
			}
		);

		self::register_one(
			$registry,
			'designsetgo/woo-price',
			__( 'Price (raw number)', 'designsetgo' ),
			array( 'number', 'text' ),
			static function ( $args ) {
				$product = self::resolve_product( $args );
				if ( ! $product ) {
					return null;
				}

				return self::numeric_or_null( $product->get_price() );
			}
		);

		self::register_one(
			$registry,
			'designsetgo/woo-regular-price',
			__( 'Regular price (raw number)', 'designsetgo' ),
			array( 'number', 'text' ),
			static function ( $args ) {
				$product = self::resolve_product( $args );
				if ( ! $product ) {
					return null;
				}

				return self::numeric_or_null( $product->get_regular_price() );
			}
		);

		self::register_one(
			$registry,
			'designsetgo/woo-discount-percent',
			__( 'Discount percent', 'designsetgo' ),
			array( 'number', 'text' ),
			array( self::class, 'get_discount_percent' )
		);

		self::register_one(
			$registry,
			'designsetgo/woo-stock-quantity',
			__( 'Stock quantity', 'designsetgo' ),
			array( 'number', 'text' ),
			static function ( $args ) {
				$product = self::resolve_product( $args );
				if ( ! $product ) {
					return null;
				}

				$quantity = $product->get_stock_quantity();

				// Null when the product does not manage stock — an unmanaged
				// product has no quantity, which is not the same as zero.
				return null === $quantity ? null : (string) (int) $quantity;
			}
		);

		self::register_one(
			$registry,
			'designsetgo/woo-average-rating',
			__( 'Average rating', 'designsetgo' ),
			array( 'number', 'text' ),
			static function ( $args ) {
				$product = self::resolve_product( $args );
				if ( ! $product ) {
					return null;
				}

				// Read once: the cast is only for the comparison, while the
				// returned value keeps Woo's own formatting (e.g. '4.00').
				$rating = $product->get_average_rating();

				// A product with no reviews rates 0.0; report nothing rather than
				// a misleading zero, matching how Woo's own rating block hides.
				return (float) $rating > 0 ? (string) $rating : null;
			}
		);
	}

	/**
	 * Percentage off, as a whole number, or null when not discounted.
	 *
	 * Public because it is registered as a callable rather than a closure, to
	 * keep register() inside the file-length budget.
	 *
	 * @param array $args Binding args, carrying the resolved `__dsgo_post_id`.
	 * @return string|null
	 */
	public static function get_discount_percent( $args ) {
		$product = self::resolve_product( $args );
		if ( ! $product ) {
			return null;
		}

		$regular = (float) $product->get_regular_price();
		$current = (float) $product->get_price();

		if ( $regular <= 0 || $current >= $regular ) {
			return null;
		}

		return (string) (int) round( ( 1 - ( $current / $regular ) ) * 100 );
	}

	/**
	 * Whether WooCommerce is loaded far enough for product reads.
	 *
	 * @return bool
	 */
	private static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Resolves the bound post to a WooCommerce product.
	 *
	 * Returns null when the post is not a product, so a Woo source dropped onto a
	 * regular post degrades to "no value" rather than erroring.
	 *
	 * @param array $args Binding args, carrying the resolved `__dsgo_post_id`.
	 * @return \WC_Product|null
	 */
	private static function resolve_product( $args ) {
		$post_id = isset( $args['__dsgo_post_id'] ) ? (int) $args['__dsgo_post_id'] : 0;

		if ( ! $post_id || ! self::is_woocommerce_active() ) {
			return null;
		}

		$product = wc_get_product( $post_id );

		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Normalises a Woo price string to a value, or null when it is empty.
	 *
	 * Woo returns '' (not 0) for an unset price, and '' would render as an empty
	 * bound attribute rather than falling back to the block's own content.
	 *
	 * NOTE for variable products: `get_price()` returns the *minimum* variation
	 * price, not a range. Authors who need the range must use
	 * `designsetgo/woo-price-html` or WooCommerce's own product-price block.
	 *
	 * @param mixed $value Raw price value from the product object.
	 * @return string|null
	 */
	private static function numeric_or_null( $value ) {
		if ( '' === $value || null === $value ) {
			return null;
		}

		return (string) $value;
	}

	/**
	 * Registers one source with both core Bindings and the metadata registry.
	 *
	 * Mirrors ArchiveSources::register_one().
	 *
	 * @param Registry $registry Metadata registry.
	 * @param string   $slug     Binding source slug.
	 * @param string   $label    Display label.
	 * @param string[] $returns  Return types.
	 * @param callable $callback Value callback.
	 */
	private static function register_one( Registry $registry, $slug, $label, array $returns, callable $callback ) {
		designsetgo_register_bindings_source(
			$slug,
			$callback,
			array( 'label' => $label )
		);

		$registry->register_source(
			$slug,
			array(
				'label'   => $label,
				'group'   => 'woocommerce',
				'returns' => $returns,
			)
		);
	}
}

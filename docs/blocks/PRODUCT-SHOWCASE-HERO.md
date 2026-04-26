# Product Showcase Hero Block - User Guide

**Block name**: `designsetgo/product-showcase-hero`
**Category**: DesignSetGo
**Keywords**: product, showcase, hero, woocommerce, shop, ecommerce

> **Requires WooCommerce.** This block will not render on the frontend (outputs nothing) if WooCommerce is not active.

> **Added in 2.1.0.**

## Overview

The **Product Showcase Hero** block renders a full-width two-column hero section featuring a single WooCommerce product. The product image fills one column edge-to-edge; the other column displays the product name, price, star rating, stock status, optional short description, and an add-to-cart button (including variation selectors for variable products).

The block supports two product source modes: **Manual** (you pick a specific product by searching) and **Current** (the block reads the product from page context, making it suitable for single-product templates in the Site Editor). In Current mode the editor shows a live sample preview using the most recently published product.

**Key Features:**
- Manual or context-driven product selection
- Media position: product image left or right (also togglable from the toolbar)
- Configurable minimum height with unit picker
- Focal point picker for the product image
- Content vertical alignment (top, center, bottom)
- Image size selector (medium, large, full)
- Individual toggles for price, rating, stock status, sale badge, short description, add-to-cart, and variation selectors
- Add-to-cart output uses `woocommerce_template_single_add_to_cart()` for variable products and `woocommerce_template_loop_add_to_cart()` for simple products, inheriting WooCommerce's full cart and variation logic
- Server-rendered via `render.php` — always reflects live product data
- Full Block Supports: color, text, gradients, link, margin, block gap, typography, border, shadow, anchor

---

## Block Attributes

| Attribute                  | Type    | Default        | Description |
|----------------------------|---------|----------------|-------------|
| `align`                    | string  | `"full"`       | Always full width. |
| `productSource`            | string  | `"manual"`     | `manual` (specific product) or `current` (page context). |
| `productId`                | number  | `0`            | WooCommerce product ID. Used when `productSource` is `manual`. `0` triggers the setup placeholder. |
| `layout`                   | string  | `"media-left"` | Image position: `media-left` or `media-right`. |
| `imageSize`                | string  | `"large"`      | WordPress image size: `medium`, `large`, or `full`. |
| `showPrice`                | boolean | `true`         | Whether to display the product price. |
| `showRating`               | boolean | `true`         | Whether to display the star rating (hidden automatically when `average_rating` is 0). |
| `showStockStatus`          | boolean | `true`         | Whether to display "In stock" or "Out of stock". |
| `showSaleBadge`            | boolean | `true`         | Whether to show a "Sale!" badge when the product is on sale. |
| `showShortDescription`     | boolean | `false`        | Whether to display the product's short description. |
| `showAddToCart`            | boolean | `true`         | Whether to render the add-to-cart form. |
| `showVariations`           | boolean | `true`         | When true and the product is variable, uses the full variation selector; otherwise uses the simple loop button. |
| `minHeight`                | string  | `"500px"`      | CSS min-height of the block. |
| `mediaFocalPoint`          | object  | `{x:0.5, y:0.5}` | Focal point for `object-position` on the product image. |
| `contentVerticalAlignment` | string  | `"center"`     | Vertical alignment of the content column: `top`, `center`, or `bottom`. |

---

## Inspector Controls

All controls live in a single **Settings** panel with per-control reset and a global "Reset all" button.

### Product Item

- **Product** — Search field for Manual mode. Typing opens a product picker that queries the WC Store API. After a product is selected, shows the product name with "Replace Product" and "Use Current Product Instead" buttons.
- **Use Current Product** / **Switch to Manual Selection** — Toggles between `productSource: "current"` and `productSource: "manual"`.

### Display Options (within the same Settings panel)

- **Show Price** — Toggle (default on).
- **Show Rating** — Toggle (default on). Rating is only visible on the frontend when `average_rating > 0`.
- **Show Stock Status** — Toggle (default on). Displays "In stock" or "Out of stock".
- **Show Sale Badge** — Toggle (default on). Shows a "Sale!" badge when `$product->is_on_sale()` returns true.
- **Show Short Description** — Toggle (default off).
- **Show Add to Cart** — Toggle (default on).
- **Show Variations** — Toggle (default on). When on and the product is variable, the full variation form renders. When off, a simple loop add-to-cart button renders instead.
- **Image Size** — Select: Medium, Large (default), Full.

### Layout (within the same Settings panel)

- **Media Position** — Select: Left (default) or Right. Also togglable from the toolbar's "Flip Layout" button.
- **Content Vertical Alignment** — Select: Top, Center (default), Bottom.
- **Min Height** — UnitControl (px, vh, vw, em, rem). Default `500px`.
- **Focal Point** — FocalPointPicker (visible once a product with an image is loaded in the editor).

---

## Usage Examples

### Manual product hero, image right

```json
{
  "productSource": "manual",
  "productId": 42,
  "layout": "media-right",
  "showShortDescription": true,
  "minHeight": "600px"
}
```

### Single-product template (Site Editor)

Set `productSource` to `current` (click "Use Current Product" in the inspector). Place the block in a single-product block template (`single-product.html`). On each product page the block resolves the displayed product automatically.

```json
{
  "productSource": "current",
  "layout": "media-left",
  "showVariations": true
}
```

---

## Frontend Behavior

The block is server-rendered by `render.php`. On every page load it:

1. Resolves the product: in `current` mode it reads `$GLOBALS['product']` or falls back to `get_the_ID()`; in `manual` mode it uses `productId`.
2. Returns an empty string if the product is not found, not published, or password-protected (password-protected posts return the password form).
3. Resolves the product image via `$product->get_image_id()` and calls `wp_get_attachment_image()` with `loading="eager"` and `fetchpriority="high"` since this is a hero.
4. Renders a sale badge if `$product->is_on_sale()` and `showSaleBadge` is true.
5. Outputs the price via `$product->get_price_html()`, rating via `wc_get_rating_html()`, and stock status as a string.
6. Renders the add-to-cart form: variable products use `woocommerce_template_single_add_to_cart()` (includes the variation dropdown and swatches); all other types use `woocommerce_template_loop_add_to_cart()`. The button element gets `wp-block-button__link wp-element-button` classes so it inherits `theme.json` button styles.
7. Sets global `$post` and `$GLOBALS['product']` for WooCommerce template functions, then restores the originals after rendering to prevent side-effects in surrounding blocks.

---

## Accessibility

- The product image uses `loading="eager"` and `fetchpriority="high"` as appropriate for a primary hero image, avoiding lazy-load delay on above-the-fold content.
- Star rating output is provided by WooCommerce's `wc_get_rating_html()`, which includes screen-reader text.
- Stock status is rendered as visible text in a `<div>` — no ARIA roles beyond the standard document flow are needed.
- The add-to-cart form inherits WooCommerce's built-in accessibility (labels, focus management, ARIA live regions for variation selection).
- Content vertical alignment and focal point controls do not affect the DOM order — content source order remains: title, price, rating, stock, description, actions.

---

## Related Blocks

- **Product Categories Grid** (`designsetgo/product-categories-grid`) — Use to display a grid of product categories rather than a single product. Also requires WooCommerce.
- **Fifty Fifty** (`designsetgo/fifty-fifty`) — Similar two-column split layout for non-product content with a static media library image.
- **Dynamic Image** (`designsetgo/dynamic-image`) — For embedding a per-post resolved image (including featured images from product posts) in a custom layout.

---

*DesignSetGo v2.1.0+ | WordPress 6.4+ | WooCommerce required*

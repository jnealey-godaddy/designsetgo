# Product Categories Grid Block - User Guide

**Block name**: `designsetgo/product-categories-grid`
**Category**: DesignSetGo
**Keywords**: product, categories, grid, woocommerce, shop, ecommerce

> **Requires WooCommerce.** This block will not render on the frontend (outputs nothing) if WooCommerce is not active.

> **Added in 2.1.0.**

## Overview

The **Product Categories Grid** block displays WooCommerce product categories as a visual image grid. Each category is rendered as a linked card with its thumbnail image, name, and optional product count. The block is server-rendered: category data is always fresh and reflects the current state of the store without re-saving the page.

Two source modes are available: **All Categories** automatically pulls top-level categories from WooCommerce (excluding the default "Uncategorized" category), while **Manual** lets you hand-pick and order specific categories. In Manual mode, individual categories can be marked as "featured" to span two columns.

The block editor shows a live preview of the grid using data fetched from the WC Store API.

**Key Features:**
- Two category source modes: All (automatic) or Manual (hand-picked)
- Manual mode supports featured cards that span two columns
- 2–5 column grid with a toolbar shortcut for quick column switching
- Four image aspect ratios: 1:1, 3:4 (default), 4:3, 16:9
- Two text overlay positions: Bottom Left (default) or Center
- Optional product count display
- Optional empty-category inclusion (All mode only)
- Server-rendered via `render.php` — always reflects live store data
- Full Block Supports: color, text, gradients, link, margin, padding, block gap, typography, border, shadow, anchor

---

## Block Attributes

| Attribute             | Type    | Default       | Description |
|-----------------------|---------|---------------|-------------|
| `align`               | string  | `"wide"`      | Block alignment. Supports `wide` and `full`. |
| `categorySource`      | string  | `"all"`       | Source mode: `all` (automatic) or `manual` (hand-picked). |
| `selectedCategories`  | array   | `[]`          | Array of `{id, featured}` objects used in Manual mode. Order is preserved. |
| `excludeCategories`   | array   | `[]`          | Array of category IDs to exclude from All mode. |
| `columns`             | number  | `3`           | Number of columns in the grid (2–5). |
| `showProductCount`    | boolean | `true`        | Whether to show the product count below the category name. |
| `showEmpty`           | boolean | `false`       | Whether to include categories with zero products (All mode only). |
| `imageAspectRatio`    | string  | `"3/4"`       | CSS aspect-ratio for category images: `1/1`, `3/4`, `4/3`, or `16/9`. |
| `overlayPosition`     | string  | `"bottom-left"` | Position of the name/count overlay: `bottom-left` or `center`. |

---

## Inspector Controls

All controls live in a single **Settings** panel with per-control reset and a global "Reset all" button.

### Settings Panel

- **Category Source** — ButtonGroup: All Categories or Manual. Switching to Manual reveals the category picker; switching to All reveals the "Show Empty Categories" toggle.
- **Show Empty Categories** — Toggle (All mode only, default off). When on, categories with zero products are included.
- **Manual Categories** — CategoryPicker + CategoryList (Manual mode only). Type to search categories and click to add them. The list shows selected categories in order with drag-to-reorder and remove controls. Each category has a "Featured" toggle to make it span two columns.
- **Show Product Count** — Toggle (default on). Displays the product count string (e.g., "5 products") beneath the category name on each card.
- **Text Position** — ButtonGroup: Bottom Left (default) or Center. Controls where the category name and count overlay appears on the card.
- **Columns** — RangeControl (2–5, default 3). Also accessible as numbered toolbar buttons (2, 3, 4, 5) in the block toolbar.
- **Image Aspect Ratio** — ButtonGroup: 1:1, 3:4 (default), 4:3, 16:9.

---

## Usage Examples

### All top-level categories, 4 columns, portrait images

```json
{
  "categorySource": "all",
  "columns": 4,
  "imageAspectRatio": "3/4",
  "showProductCount": true
}
```

### Manual selection with a featured hero card

In Manual mode, mark the first category as "Featured" in the category list. The featured card spans two columns; remaining cards fill single columns beside and below it.

```json
{
  "categorySource": "manual",
  "selectedCategories": [
    {"id": 12, "featured": true},
    {"id": 7,  "featured": false},
    {"id": 9,  "featured": false},
    {"id": 15, "featured": false}
  ],
  "columns": 3,
  "overlayPosition": "center"
}
```

---

## Frontend Behavior

The block is server-rendered by `render.php`. On every page load it:

1. Resolves categories from `get_terms( 'product_cat', … )` using the saved attributes.
2. In All mode, automatically excludes the default "Uncategorized" category (identified by its slug) and any IDs in `excludeCategories`.
3. Resolves each category's thumbnail via `get_term_meta( $term_id, 'thumbnail_id' )`. Categories without a thumbnail display an inline placeholder SVG icon.
4. Renders an `<a>` card for each category linking to the category archive. Featured cards (Manual mode) receive the `dsgo-product-categories-grid__card--featured` class, which the stylesheet uses to span two columns.
5. Sets `role="navigation"` and `aria-label="Product categories"` on the wrapper `<div>`.

The CSS aspect ratio is applied via the `--dsgo-pcg-aspect-ratio` CSS custom property on the wrapper.

If WooCommerce is not active, `render.php` returns an empty string immediately.

---

## Accessibility

- The grid wrapper has `role="navigation"` and `aria-label="Product categories"` so screen readers announce it as a navigation landmark.
- Each card is an `<a>` element linking to the category archive — fully keyboard navigable with standard tab order.
- Product counts are rendered twice: once visibly (`aria-hidden="true"`) and once as a screen-reader-only string in the format "5 products in Clothing" for full context.
- Category images use `alt=""` (empty) because the category name in the overlay already conveys the meaning; the image is decorative.
- Images are loaded with `loading="lazy"`.

---

## Related Blocks

- **Product Showcase Hero** (`designsetgo/product-showcase-hero`) — Highlights a single product rather than categories. Also requires WooCommerce.
- **Query** (`designsetgo/query`) — For custom product listings with filtering, pagination, and full layout control.

---

*DesignSetGo v2.1.0+ | WordPress 6.4+ | WooCommerce required*

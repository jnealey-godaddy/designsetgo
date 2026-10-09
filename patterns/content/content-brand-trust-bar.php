<?php
/**
 * Title: Brand Trust Bar
 * Slug: designsetgo/content/content-brand-trust-bar
 * Categories: dsgo-content
 * Description: A centered brand logo trust bar showing partner or client brand names in a horizontal layout
 * Keywords: brands, logos, trust, partners, clients, bar
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Brand Trust Bar', 'designsetgo' ),
	'categories' => array( 'dsgo-content' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"2px"}},"fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size" style="letter-spacing:2px;text-transform:uppercase">Trusted by Leading Brands</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"layout":{"type":"flex","orientation":"horizontal","justifyContent":"center","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:nowrap;gap:var(--wp--preset--spacing--30)"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","letterSpacing":"2px"},"spacing":{"padding":{"left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"textColor":"contrast-2","fontSize":"x-large"} -->
<p class="has-contrast-2-color has-text-color has-x-large-font-size" style="padding-right:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);font-style:normal;font-weight:700;letter-spacing:2px">HALCYON</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","letterSpacing":"2px"},"spacing":{"padding":{"left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"textColor":"contrast-2","fontSize":"x-large"} -->
<p class="has-contrast-2-color has-text-color has-x-large-font-size" style="padding-right:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);font-style:normal;font-weight:700;letter-spacing:2px">NORTHSTAR</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","letterSpacing":"2px"},"spacing":{"padding":{"left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"textColor":"contrast-2","fontSize":"x-large"} -->
<p class="has-contrast-2-color has-text-color has-x-large-font-size" style="padding-right:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);font-style:normal;font-weight:700;letter-spacing:2px">BRIGHTWAVE</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","letterSpacing":"2px"},"spacing":{"padding":{"left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"textColor":"contrast-2","fontSize":"x-large"} -->
<p class="has-contrast-2-color has-text-color has-x-large-font-size" style="padding-right:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);font-style:normal;font-weight:700;letter-spacing:2px">OAKRIDGE</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section -->',
);

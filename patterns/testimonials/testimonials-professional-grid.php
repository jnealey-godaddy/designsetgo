<?php
/**
 * Title: Professional Testimonials Grid
 * Slug: designsetgo/testimonials/testimonials-professional-grid
 * Categories: dsgo-testimonials
 * Description: A two-column testimonial grid with detailed client reviews for professional service firms
 * Keywords: testimonials, professional, clients, grid, reviews, firm
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Professional Testimonials Grid', 'designsetgo' ),
	'categories' => array( 'dsgo-testimonials' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"base-2"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-2-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="font-style:normal;font-weight:700">What Our Clients Say</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"desktopColumns":2,"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--30);column-gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"8px"}},"backgroundColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-background-color has-background" style="border-radius:8px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"italic","fontWeight":"400"}}} -->
<p style="font-style:italic;font-weight:400">"They handled our company merger with exceptional skill and attention to detail. Their guidance was invaluable throughout the entire process."</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--30);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap"><!-- wp:image {"width":"50px","height":"50px","scale":"cover","sizeSlug":"thumbnail","className":"is-style-rounded"} -->
<figure class="wp-block-image size-thumbnail is-resized is-style-rounded"><img src="{{dsgo:placeholder-avatar}}" alt="Thomas Anderson" style="object-fit:cover;width:50px;height:50px"/></figure>
<!-- /wp:image -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-small-font-size" style="font-style:normal;font-weight:600">Thomas Anderson</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">CEO, Anderson Industries</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"8px"}},"backgroundColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-background-color has-background" style="border-radius:8px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"italic","fontWeight":"400"}}} -->
<p style="font-style:italic;font-weight:400">"Professional, responsive, and truly invested in our success. They have been our trusted advisors for over a decade now."</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--30);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap"><!-- wp:image {"width":"50px","height":"50px","scale":"cover","sizeSlug":"thumbnail","className":"is-style-rounded"} -->
<figure class="wp-block-image size-thumbnail is-resized is-style-rounded"><img src="{{dsgo:placeholder-avatar}}" alt="Patricia Reynolds" style="object-fit:cover;width:50px;height:50px"/></figure>
<!-- /wp:image -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-small-font-size" style="font-style:normal;font-weight:600">Patricia Reynolds</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Managing Director, Reynolds Group</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

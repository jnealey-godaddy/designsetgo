<?php
/**
 * Title: Guest Reviews Section
 * Slug: designsetgo/testimonials/testimonials-guest-reviews
 * Categories: dsgo-testimonials
 * Description: A three-column guest review section with star ratings, quotes, and diner names on a dark background
 * Keywords: testimonials, reviews, restaurant, guests, ratings, dining
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Guest Reviews Section', 'designsetgo' ),
	'categories' => array( 'dsgo-testimonials' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"backgroundColor":"contrast","textColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-color has-contrast-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#d97706"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#d97706;letter-spacing:3px;text-transform:uppercase">Reviews</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:700">What Our Guests Say</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-3 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(3, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--30);column-gap:var(--wp--preset--spacing--30)"><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px","width":"1px"}},"borderColor":"contrast-2","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-border-color has-contrast-2-border-color" style="border-width:1px;border-radius:12px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"style":{"color":{"text":"#fbbf24"}}} -->
<p class="has-text-color" style="color:#fbbf24">★★★★★</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"typography":{"fontStyle":"italic","fontWeight":"400"}}} -->
<p style="margin-top:var(--wp--preset--spacing--20);font-style:italic;font-weight:400">"An unforgettable dining experience! The lobster risotto was pure perfection. Will definitely be back."</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:600">- Michael R., via Guest Reviews</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px","width":"1px"}},"borderColor":"contrast-2","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-border-color has-contrast-2-border-color" style="border-width:1px;border-radius:12px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"style":{"color":{"text":"#fbbf24"}}} -->
<p class="has-text-color" style="color:#fbbf24">★★★★★</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"typography":{"fontStyle":"italic","fontWeight":"400"}}} -->
<p style="margin-top:var(--wp--preset--spacing--20);font-style:italic;font-weight:400">"We celebrated our anniversary here and it was magical. The service was impeccable and the food was outstanding."</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:600">- Sarah T., via Diner Reviews</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px","width":"1px"}},"borderColor":"contrast-2","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-border-color has-contrast-2-border-color" style="border-width:1px;border-radius:12px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"style":{"color":{"text":"#fbbf24"}}} -->
<p class="has-text-color" style="color:#fbbf24">★★★★★</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"typography":{"fontStyle":"italic","fontWeight":"400"}}} -->
<p style="margin-top:var(--wp--preset--spacing--20);font-style:italic;font-weight:400">"Best restaurant in the city, hands down. The chef truly understands how to bring out the best in each ingredient."</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:600">- James L., via Travel Reviews</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

<?php
/**
 * Title: Restaurant Dining Hero
 * Slug: designsetgo/hero/hero-restaurant-dining
 * Categories: dsgo-hero
 * Description: A two-column restaurant hero with story-driven headline, description, menu and reservation CTAs, and food image
 * Keywords: hero, restaurant, dining, food, menu, reservation
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Restaurant Dining Hero', 'designsetgo' ),
	'categories' => array( 'dsgo-hero' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/grid {"desktopColumns":2,"style":{"spacing":{"blockGap":"var:preset|spacing|70","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"alignItems":"center"} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:center;row-gap:var(--wp--preset--spacing--70);column-gap:var(--wp--preset--spacing--70)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":"16px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Chef preparing food" style="border-radius:16px"/></figure>
<!-- /wp:image --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#d97706"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d97706;letter-spacing:3px;text-transform:uppercase">Our Story</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:700">A Passion for Authentic Flavors</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="margin-top:var(--wp--preset--spacing--20)">Founded by Chef Maria Santos, our restaurant celebrates the rich culinary traditions of the Mediterranean with a modern twist.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}}} -->
<p style="margin-top:var(--wp--preset--spacing--20)">Every dish tells a story, crafted with locally-sourced ingredients and time-honored techniques passed down through generations. We believe that great food brings people together, creating moments that last a lifetime.</p>
<!-- /wp:paragraph -->

<!-- wp:separator {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}}} -->
<hr class="wp-block-separator has-alpha-channel-opacity" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--40)"/>
<!-- /wp:separator -->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"padding":{"right":"var:preset|spacing|40"}}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group" style="padding-right:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"color":{"text":"#d97706"}},"fontSize":"xx-large"} -->
<p class="has-text-color has-xx-large-font-size" style="color:#d97706;font-style:normal;font-weight:700">15+</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Years of Excellence</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"right":"var:preset|spacing|40","left":"var:preset|spacing|40"}}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group" style="padding-right:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"color":{"text":"#d97706"}},"fontSize":"xx-large"} -->
<p class="has-text-color has-xx-large-font-size" style="color:#d97706;font-style:normal;font-weight:700">50k+</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Happy Guests</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"left":"var:preset|spacing|40"}}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group" style="padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"color":{"text":"#d97706"}},"fontSize":"xx-large"} -->
<p class="has-text-color has-xx-large-font-size" style="color:#d97706;font-style:normal;font-weight:700">4.9</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Star Rating</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

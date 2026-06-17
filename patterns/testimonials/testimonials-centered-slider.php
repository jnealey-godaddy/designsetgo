<?php
/**
 * Title: Testimonials Centered Slider
 * Slug: designsetgo/testimonials/testimonials-centered-slider
 * Categories: dsgo-testimonials
 * Description: A centered testimonial slider with italicized quotes, avatar photos, and client details on a light background
 * Keywords: testimonials, slider, centered, quotes, clients, carousel
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Testimonials Centered Slider', 'designsetgo' ),
	'categories' => array( 'dsgo-testimonials' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"base-2"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-2-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"}},"fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size" style="letter-spacing:3px;text-transform:uppercase">Testimonials</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:700">What Clients Say</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/slider {"autoplay":true,"autoplayInterval":5000} -->
<div class="wp-block-designsetgo-slider dsgo-slider dsgo-slider--classic dsgo-slider--effect-slide dsgo-slider--has-arrows dsgo-slider--has-dots" style="--dsgo-slider-height:500px;--dsgo-slider-aspect-ratio:16/9;--dsgo-slider-gap:20px;--dsgo-slider-transition:0.5s;--dsgo-slider-slides-per-view:1;--dsgo-slider-slides-per-view-tablet:1;--dsgo-slider-slides-per-view-mobile:1;--dsgo-slider-arrow-size:48px" data-slides-per-view="1" data-slides-per-view-tablet="1" data-slides-per-view-mobile="1" data-use-aspect-ratio="false" data-show-arrows="true" data-show-dots="true" data-arrow-style="default" data-arrow-position="sides" data-arrow-vertical-position="center" data-dot-style="default" data-dot-position="bottom" data-effect="slide" data-transition-duration="0.5s" data-transition-easing="ease-in-out" data-autoplay="true" data-autoplay-interval="5000" data-pause-on-hover="true" data-pause-on-interaction="true" data-loop="true" data-draggable="true" data-swipeable="true" data-free-mode="false" data-centered-slides="false" data-mobile-breakpoint="768" data-tablet-breakpoint="1024" data-active-slide="0" role="region" aria-label="Image slider" aria-roledescription="slider"><div class="dsgo-slider__viewport"><div class="dsgo-slider__track"><!-- wp:designsetgo/slide -->
<div class="wp-block-designsetgo-slide dsgo-slide" style="--dsgo-slide-content-vertical-align:center;--dsgo-slide-content-horizontal-align:center" role="group" aria-roledescription="slide"><div class="dsgo-slide__content"><!-- wp:designsetgo/section {"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"italic","fontWeight":"400"}},"fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size" style="font-style:italic;font-weight:400">"They completely transformed our brand. The results exceeded our expectations, and our customers love the new direction."</p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"60px","height":"60px","scale":"cover","sizeSlug":"thumbnail","align":"center","className":"is-style-rounded"} -->
<figure class="wp-block-image aligncenter size-thumbnail is-resized is-style-rounded"><img src="{{dsgo:placeholder-avatar}}" alt="David Park" style="object-fit:cover;width:60px;height:60px"/></figure>
<!-- /wp:image -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"600"}}} -->
<p class="has-text-align-center" style="font-style:normal;font-weight:600">Joey Walsh</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">CEO, Luxe Brands</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/slide -->

<!-- wp:designsetgo/slide {"style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-designsetgo-slide dsgo-slide" style="--dsgo-slide-content-vertical-align:center;--dsgo-slide-content-horizontal-align:center" role="group" aria-roledescription="slide"><div class="dsgo-slide__content"><!-- wp:designsetgo/section -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"italic","fontWeight":"400"}},"fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size" style="font-style:italic;font-weight:400">"Working with this team was a game-changer for our startup. They understood our vision and brought it to life beautifully."</p>
<!-- /wp:paragraph -->

<!-- wp:image {"width":"60px","height":"60px","scale":"cover","sizeSlug":"thumbnail","align":"center","className":"is-style-rounded"} -->
<figure class="wp-block-image aligncenter size-thumbnail is-resized is-style-rounded"><img src="{{dsgo:placeholder-avatar}}" alt="David Park" style="object-fit:cover;width:60px;height:60px"/></figure>
<!-- /wp:image -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"600"}}} -->
<p class="has-text-align-center" style="font-style:normal;font-weight:600">David Park</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">Founder, TechStart</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/slide --></div></div></div>
<!-- /wp:designsetgo/slider --></div></div>
<!-- /wp:designsetgo/section -->',
);

<?php
/**
 * Title: Product Demo Showcase
 * Slug: designsetgo/content/content-product-demo
 * Categories: dsgo-content
 * Description: An image accordion section showcasing product screenshots and demo views
 * Keywords: product, demo, showcase, screenshots, image accordion, saas
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Product Demo Showcase', 'designsetgo' ),
	'categories' => array( 'dsgo-content' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeIn"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeIn" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeIn"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--50);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#8b5cf6"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#8b5cf6;letter-spacing:3px;text-transform:uppercase">Product Showcase</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:700">See It in Action</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/image-accordion {"gap":"8px","overlayOpacity":30,"overlayOpacityExpanded":10} -->
<div class="wp-block-designsetgo-image-accordion dsgo-image-accordion dsgo-image-accordion--hover" style="--dsgo-image-accordion-gap:8px;--dsgo-image-accordion-expanded-ratio:3;--dsgo-image-accordion-transition:0.5s;--dsgo-image-accordion-overlay-opacity:0.3;--dsgo-image-accordion-overlay-opacity-expanded:0.1" data-trigger-type="hover" data-default-expanded="0" data-enable-overlay="true"><div class="dsgo-image-accordion__items"><!-- wp:designsetgo/image-accordion-item {"uniqueId":"acc-1","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"{{dsgo:placeholder-portrait}}"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="acc-1" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Dashboard</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">Powerful analytics at your fingertips</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item -->

<!-- wp:designsetgo/image-accordion-item {"uniqueId":"acc-2","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"{{dsgo:placeholder-portrait}}"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="acc-2" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Reports</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">Generate insights in seconds</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item -->

<!-- wp:designsetgo/image-accordion-item {"uniqueId":"acc-3","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"{{dsgo:placeholder-portrait}}"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="acc-3" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Team Hub</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">Collaborate in real-time</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item -->

<!-- wp:designsetgo/image-accordion-item {"uniqueId":"acc-4","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"{{dsgo:placeholder-portrait}}"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="acc-4" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Integrations</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">Connect with 100+ tools</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item --></div></div>
<!-- /wp:designsetgo/image-accordion --></div></div>
<!-- /wp:designsetgo/section -->',
);

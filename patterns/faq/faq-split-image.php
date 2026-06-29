<?php
/**
 * Title: FAQ Split with Image
 * Slug: designsetgo/faq/faq-split-image
 * Categories: dsgo-faq
 * Description: A two-column FAQ section with an accordion on the left and a complementary image on the right
 * Keywords: faq, accordion, split, image, questions, answers
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'FAQ Split with Image', 'designsetgo' ),
	'categories' => array( 'dsgo-faq' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"base-2"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-2-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/grid {"desktopColumns":2,"style":{"spacing":{"blockGap":"var:preset|spacing|70","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"alignItems":"center"} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:center;row-gap:var(--wp--preset--spacing--70);column-gap:var(--wp--preset--spacing--70)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#7c3aed"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#7c3aed;letter-spacing:3px;text-transform:uppercase">FAQ</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:700">Frequently Asked Questions</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}}} -->
<p style="margin-top:var(--wp--preset--spacing--20)">Find answers to common questions about our products, shipping, and return policies.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/accordion {"className":"dsgo-accordion\\u002d\\u002dicon-chevron","style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-designsetgo-accordion dsgo-accordion dsgo-accordion--icon-right dsgo-accordion--border-between dsgo-accordion--icon-chevron" style="margin-top:var(--wp--preset--spacing--40);--dsgo-accordion-open-bg:;--dsgo-accordion-open-text:;--dsgo-accordion-hover-bg:;--dsgo-accordion-hover-text:;--dsgo-accordion-gap:0.5rem" data-allow-multiple="false" data-icon-style="chevron"><div class="dsgo-accordion__items"><!-- wp:designsetgo/accordion-item {"title":"How long does shipping take?","isOpen":true,"uniqueId":"accordion-item-1avvv8bu6"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--open" data-initially-open="true"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="true" aria-controls="accordion-item-1avvv8bu6-panel" id="accordion-item-1avvv8bu6-header"><span class="dsgo-accordion-item__title">How long does shipping take?</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-1avvv8bu6-header" id="accordion-item-1avvv8bu6-panel"><div class="dsgo-accordion-item__content"><!-- wp:paragraph -->
<p>Standard shipping takes 3-5 business days within the US. Express shipping (1-2 days) is available for an additional fee. International orders typically arrive within 7-14 business days.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item -->

<!-- wp:designsetgo/accordion-item {"title":"What is your return policy?","uniqueId":"accordion-item-jsotpnsri"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed" data-initially-open="false"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="false" aria-controls="accordion-item-jsotpnsri-panel" id="accordion-item-jsotpnsri-header"><span class="dsgo-accordion-item__title">What is your return policy?</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-jsotpnsri-header" id="accordion-item-jsotpnsri-panel" hidden><div class="dsgo-accordion-item__content"><!-- wp:paragraph -->
<p>We offer a 30-day return policy for all unused items in original packaging. Simply initiate a return through your account dashboard or contact our support team.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item -->

<!-- wp:designsetgo/accordion-item {"title":"Do you offer international shipping?","uniqueId":"accordion-item-zuksonk77"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed" data-initially-open="false"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="false" aria-controls="accordion-item-zuksonk77-panel" id="accordion-item-zuksonk77-header"><span class="dsgo-accordion-item__title">Do you offer international shipping?</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-zuksonk77-header" id="accordion-item-zuksonk77-panel" hidden><div class="dsgo-accordion-item__content"><!-- wp:paragraph -->
<p>Yes! We ship to over 30 countries worldwide. Shipping costs and delivery times vary by location. Check our shipping page for specific details about your country.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item -->

<!-- wp:designsetgo/accordion-item {"title":"How can I track my order?","uniqueId":"accordion-item-yg1kvbxsk"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed" data-initially-open="false"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="false" aria-controls="accordion-item-yg1kvbxsk-panel" id="accordion-item-yg1kvbxsk-header"><span class="dsgo-accordion-item__title">How can I track my order?</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-yg1kvbxsk-header" id="accordion-item-yg1kvbxsk-panel" hidden><div class="dsgo-accordion-item__content"><!-- wp:paragraph -->
<p>Once your order ships, you will receive an email with a tracking number. You can also track your order status through your account dashboard at any time.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item --></div></div>
<!-- /wp:designsetgo/accordion --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":"16px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Shopping experience" style="border-radius:16px"/></figure>
<!-- /wp:image --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

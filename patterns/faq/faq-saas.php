<?php
/**
 * Title: SaaS FAQ Section
 * Slug: designsetgo/faq/faq-saas
 * Categories: dsgo-faq
 * Description: A centered FAQ section with accordion for common SaaS product questions
 * Keywords: faq, saas, questions, accordion, software, support
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'SaaS FAQ Section', 'designsetgo' ),
	'categories' => array( 'dsgo-faq' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="font-style:normal;font-weight:700">Frequently Asked Questions</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size" style="margin-top:var(--wp--preset--spacing--20)">Everything you need to know about our platform.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/accordion {"className":"dsgo-accordion\\u002d\\u002dicon-chevron"} -->
<div class="wp-block-designsetgo-accordion dsgo-accordion dsgo-accordion--icon-right dsgo-accordion--border-between dsgo-accordion--icon-chevron" style="--dsgo-accordion-open-bg:;--dsgo-accordion-open-text:;--dsgo-accordion-hover-bg:;--dsgo-accordion-hover-text:;--dsgo-accordion-gap:0.5rem" data-allow-multiple="false" data-icon-style="chevron"><div class="dsgo-accordion__items"><!-- wp:designsetgo/accordion-item {"title":"How does the free trial work?","uniqueId":"accordion-item-amhjsq5iu"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed" data-initially-open="false"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="false" aria-controls="accordion-item-amhjsq5iu-panel" id="accordion-item-amhjsq5iu-header"><span class="dsgo-accordion-item__title">How does the free trial work?</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-amhjsq5iu-header" id="accordion-item-amhjsq5iu-panel" hidden><div class="dsgo-accordion-item__content"><!-- wp:paragraph -->
<p>Our 14-day free trial gives you full access to all features with no credit card required. Simply sign up and start exploring. At the end of your trial, you can choose a plan that fits your needs or continue with our free tier.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item -->

<!-- wp:designsetgo/accordion-item {"title":"Can I change my plan later?","uniqueId":"accordion-item-zl9mpknnk"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed" data-initially-open="false"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="false" aria-controls="accordion-item-zl9mpknnk-panel" id="accordion-item-zl9mpknnk-header"><span class="dsgo-accordion-item__title">Can I change my plan later?</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-zl9mpknnk-header" id="accordion-item-zl9mpknnk-panel" hidden><div class="dsgo-accordion-item__content"><!-- wp:paragraph -->
<p>Yes, you can upgrade or downgrade your plan at any time. When you upgrade, you will immediately get access to additional features. When you downgrade, the change takes effect at the start of your next billing cycle.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item -->

<!-- wp:designsetgo/accordion-item {"title":"What payment methods do you accept?","isOpen":true,"uniqueId":"accordion-item-sgb8h1pyf"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--open" data-initially-open="true"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="true" aria-controls="accordion-item-sgb8h1pyf-panel" id="accordion-item-sgb8h1pyf-header"><span class="dsgo-accordion-item__title">What payment methods do you accept?</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-sgb8h1pyf-header" id="accordion-item-sgb8h1pyf-panel"><div class="dsgo-accordion-item__content"><!-- wp:paragraph -->
<p>We accept all major credit cards (Visa, MasterCard, American Express), PayPal, and bank transfers for annual enterprise plans. All payments are processed securely through Stripe.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item -->

<!-- wp:designsetgo/accordion-item {"title":"Is my data secure?","uniqueId":"accordion-item-pxyppsjq7"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed" data-initially-open="false"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="false" aria-controls="accordion-item-pxyppsjq7-panel" id="accordion-item-pxyppsjq7-header"><span class="dsgo-accordion-item__title">Is my data secure?</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-pxyppsjq7-header" id="accordion-item-pxyppsjq7-panel" hidden><div class="dsgo-accordion-item__content"><!-- wp:paragraph -->
<p>Absolutely. We use bank-level 256-bit SSL encryption for all data transfers and store your data in SOC 2 Type II certified data centers. We are also GDPR compliant and never sell your data to third parties.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item -->

<!-- wp:designsetgo/accordion-item {"title":"Do you offer customer support?","uniqueId":"accordion-item-oq36caiu5"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed" data-initially-open="false"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="false" aria-controls="accordion-item-oq36caiu5-panel" id="accordion-item-oq36caiu5-header"><span class="dsgo-accordion-item__title">Do you offer customer support?</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-oq36caiu5-header" id="accordion-item-oq36caiu5-panel" hidden><div class="dsgo-accordion-item__content"><!-- wp:paragraph -->
<p>Yes! We offer email support for all plans, with priority support for Professional plans and dedicated 24/7 support for Enterprise customers. Our average response time is under 2 hours during business hours.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item --></div></div>
<!-- /wp:designsetgo/accordion --></div></div>
<!-- /wp:designsetgo/section -->',
);

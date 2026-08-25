<?php
/**
 * Title: Features Accordion
 * Slug: designsetgo/features/features-accordion
 * Categories: dsgo-features
 * Description: A dark-themed expandable feature details section with accordion
 * Keywords: features, accordion, expandable, dark, details
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Features Accordion', 'designsetgo' ),
	'categories' => array( 'dsgo-features' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"backgroundColor":"contrast","textColor":"base","metadata":{"categories":["dsgo-features"],"patternName":"designsetgo/features/features-accordion","name":"Features Accordion"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-color has-contrast-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/grid {"desktopColumns":2,"tabletColumns":1,"style":{"spacing":{"blockGap":"var:preset|spacing|60","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"alignItems":"center"} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-1 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:center;row-gap:var(--wp--preset--spacing--60);column-gap:var(--wp--preset--spacing--60)"><!-- wp:designsetgo/section {"constrainWidth":false,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner"><!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"textColor":"base","fontSize":"x-large"} -->
<h2 class="wp-block-heading has-base-color has-text-color has-x-large-font-size" style="font-style:normal;font-weight:700">Dive Deeper Into Our Features</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"0"}}},"textColor":"base","fontSize":"medium"} -->
<p class="has-base-color has-text-color has-medium-font-size" style="margin-top:0">Explore the powerful capabilities that make our platform the choice of professionals worldwide.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/icon-button {"url":"#","icon":"arrow-right","iconPosition":"end","iconGap":"8px","backgroundColor":"base","textColor":"contrast","style":{"spacing":{"margin":{"top":"var:preset|spacing|40"},"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"4px"}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--left" style="margin-top:var(--wp--preset--spacing--40)"><a class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-contrast-color has-base-background-color has-text-color has-background dsgo-icon-button--has-icon dsgo-icon-button--icon-end" style="border-radius:4px;gap:8px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--30)" href="#" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" data-icon-name="arrow-right"></span><span class="dsgo-icon-button__text">View All Features</span></a></div>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/accordion {"iconStyle":"plus-minus","style":{"border":{"color":"rgba(255,255,255,0.2)"}}} -->
<div class="wp-block-designsetgo-accordion dsgo-accordion dsgo-accordion--icon-right dsgo-accordion--border-between has-border-color" style="border-color:rgba(255,255,255,0.2);--dsgo-accordion-open-bg:;--dsgo-accordion-open-text:;--dsgo-accordion-hover-bg:;--dsgo-accordion-hover-text:;--dsgo-accordion-gap:0.5rem" data-allow-multiple="false" data-icon-style="plus-minus"><div class="dsgo-accordion__items"><!-- wp:designsetgo/accordion-item {"title":"Advanced Layout System","uniqueId":"accordion-item-5xqx9bw3f"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed" data-initially-open="false"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="false" aria-controls="accordion-item-5xqx9bw3f-panel" id="accordion-item-5xqx9bw3f-header"><span class="dsgo-accordion-item__title">Advanced Layout System</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-5xqx9bw3f-header" id="accordion-item-5xqx9bw3f-panel" hidden><div class="dsgo-accordion-item__content"><!-- wp:paragraph {"textColor":"base"} -->
<p class="has-base-color has-text-color">Our flexible grid and row blocks give you complete control over your layouts. Create responsive designs that look perfect on any device with intuitive column controls and breakpoint settings.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item -->

<!-- wp:designsetgo/accordion-item {"title":"Interactive Components","isOpen":true,"uniqueId":"accordion-item-ir548rjjy"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--open" data-initially-open="true"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="true" aria-controls="accordion-item-ir548rjjy-panel" id="accordion-item-ir548rjjy-header"><span class="dsgo-accordion-item__title">Interactive Components</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-ir548rjjy-header" id="accordion-item-ir548rjjy-panel"><div class="dsgo-accordion-item__content"><!-- wp:paragraph {"textColor":"base"} -->
<p class="has-base-color has-text-color">Engage your visitors with tabs, accordions, sliders, and modals. All components are fully accessible and work seamlessly across devices with smooth animations.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item -->

<!-- wp:designsetgo/accordion-item {"title":"Form Builder","uniqueId":"accordion-item-wc9lhkek5"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed" data-initially-open="false"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="false" aria-controls="accordion-item-wc9lhkek5-panel" id="accordion-item-wc9lhkek5-header"><span class="dsgo-accordion-item__title">Form Builder</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-wc9lhkek5-header" id="accordion-item-wc9lhkek5-panel" hidden><div class="dsgo-accordion-item__content"><!-- wp:paragraph {"textColor":"base"} -->
<p class="has-base-color has-text-color">Create professional forms with our intuitive form builder. Includes AJAX submission, spam protection, email notifications, and integration with popular services.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item -->

<!-- wp:designsetgo/accordion-item {"title":"Performance Optimization","uniqueId":"accordion-item-rp0x61xg6"} -->
<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--closed" data-initially-open="false"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="false" aria-controls="accordion-item-rp0x61xg6-panel" id="accordion-item-rp0x61xg6-header"><span class="dsgo-accordion-item__title">Performance Optimization</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-rp0x61xg6-header" id="accordion-item-rp0x61xg6-panel" hidden><div class="dsgo-accordion-item__content"><!-- wp:paragraph {"textColor":"base"} -->
<p class="has-base-color has-text-color">Built with performance in mind. Lazy loading, minimal CSS, code splitting, and optimized assets ensure your site loads fast and scores high on Core Web Vitals.</p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:designsetgo/accordion-item --></div></div>
<!-- /wp:designsetgo/accordion --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

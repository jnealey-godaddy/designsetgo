<?php
/**
 * Title: Skills Progress Bars
 * Slug: designsetgo/content/content-progress-skills
 * Categories: dsgo-content
 * Description: Skills or expertise showcase with animated progress bars and statistics
 * Keywords: skills, progress, bars, expertise, about, stats
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Skills Progress Bars', 'designsetgo' ),
	'categories' => array( 'dsgo-content' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"backgroundColor":"base-2","metadata":{"categories":["dsgo-content"],"patternName":"designsetgo/content/progress-skills","name":"Progress Skills"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-2-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/grid {"desktopColumns":2,"tabletColumns":1,"style":{"spacing":{"blockGap":"var:preset|spacing|60","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"alignItems":"center"} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-1 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:center;row-gap:var(--wp--preset--spacing--60);column-gap:var(--wp--preset--spacing--60)"><!-- wp:designsetgo/section {"constrainWidth":false,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner"><!-- wp:heading {"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size">Our Expertise</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"0"}}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="margin-top:0">We have honed our skills over years of experience to deliver exceptional results in every project we undertake.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/icon-button {"url":"#","icon":"arrow-right","iconPosition":"end","iconGap":"8px","backgroundColor":"contrast","textColor":"base","style":{"spacing":{"margin":{"top":"var:preset|spacing|40"},"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"8px"}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--left" style="margin-top:var(--wp--preset--spacing--40)"><a class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-base-color has-contrast-background-color has-text-color has-background dsgo-icon-button--has-icon dsgo-icon-button--icon-end" style="border-radius:8px;gap:8px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--30)" href="#" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" data-icon-name="arrow-right"></span><span class="dsgo-icon-button__text">View Our Work</span></a></div>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"constrainWidth":false,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"var:preset|spacing|30","margin":{"top":"0","bottom":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-no-width-constraint" style="margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner"><!-- wp:designsetgo/progress-bar {"percentage":95,"barColor":"#6366f1","height":"12px","borderRadius":"6px","labelText":"WordPress Development","backgroundColor":"#e5e7eb"} -->
<div class="wp-block-designsetgo-progress-bar dsgo-progress-bar dsgo-progress-bar--animate has-e-5-e-7-eb-background-color has-background" data-percentage="95" data-duration="1.5"><div class="dsgo-progress-bar__label dsgo-progress-bar__label--top">WordPress Development - 95%</div><div class="dsgo-progress-bar__container" style="width:100%;height:12px;background-color:#e5e7eb;border-radius:6px;overflow:hidden;position:relative"><div class="dsgo-progress-bar__fill " style="width:0%;height:100%;background-color:#6366f1;transition:width 1.5s ease-out;border-radius:6px"></div></div></div>
<!-- /wp:designsetgo/progress-bar -->

<!-- wp:designsetgo/progress-bar {"percentage":90,"barColor":"#8b5cf6","height":"12px","borderRadius":"6px","labelText":"React \u0026 JavaScript","backgroundColor":"#e5e7eb"} -->
<div class="wp-block-designsetgo-progress-bar dsgo-progress-bar dsgo-progress-bar--animate has-e-5-e-7-eb-background-color has-background" data-percentage="90" data-duration="1.5"><div class="dsgo-progress-bar__label dsgo-progress-bar__label--top">React &amp; JavaScript - 90%</div><div class="dsgo-progress-bar__container" style="width:100%;height:12px;background-color:#e5e7eb;border-radius:6px;overflow:hidden;position:relative"><div class="dsgo-progress-bar__fill " style="width:0%;height:100%;background-color:#8b5cf6;transition:width 1.5s ease-out;border-radius:6px"></div></div></div>
<!-- /wp:designsetgo/progress-bar -->

<!-- wp:designsetgo/progress-bar {"percentage":85,"barColor":"#ec4899","height":"12px","borderRadius":"6px","labelText":"UI/UX Design","backgroundColor":"#e5e7eb"} -->
<div class="wp-block-designsetgo-progress-bar dsgo-progress-bar dsgo-progress-bar--animate has-e-5-e-7-eb-background-color has-background" data-percentage="85" data-duration="1.5"><div class="dsgo-progress-bar__label dsgo-progress-bar__label--top">UI/UX Design - 85%</div><div class="dsgo-progress-bar__container" style="width:100%;height:12px;background-color:#e5e7eb;border-radius:6px;overflow:hidden;position:relative"><div class="dsgo-progress-bar__fill " style="width:0%;height:100%;background-color:#ec4899;transition:width 1.5s ease-out;border-radius:6px"></div></div></div>
<!-- /wp:designsetgo/progress-bar -->

<!-- wp:designsetgo/progress-bar {"percentage":80,"barColor":"#14b8a6","height":"12px","borderRadius":"6px","labelText":"Performance Optimization","backgroundColor":"#e5e7eb"} -->
<div class="wp-block-designsetgo-progress-bar dsgo-progress-bar dsgo-progress-bar--animate has-e-5-e-7-eb-background-color has-background" data-percentage="80" data-duration="1.5"><div class="dsgo-progress-bar__label dsgo-progress-bar__label--top">Performance Optimization - 80%</div><div class="dsgo-progress-bar__container" style="width:100%;height:12px;background-color:#e5e7eb;border-radius:6px;overflow:hidden;position:relative"><div class="dsgo-progress-bar__fill " style="width:0%;height:100%;background-color:#14b8a6;transition:width 1.5s ease-out;border-radius:6px"></div></div></div>
<!-- /wp:designsetgo/progress-bar -->

<!-- wp:designsetgo/progress-bar {"percentage":88,"barColor":"#f59e0b","height":"12px","borderRadius":"6px","labelText":"SEO \u0026 Accessibility","backgroundColor":"#e5e7eb"} -->
<div class="wp-block-designsetgo-progress-bar dsgo-progress-bar dsgo-progress-bar--animate has-e-5-e-7-eb-background-color has-background" data-percentage="88" data-duration="1.5"><div class="dsgo-progress-bar__label dsgo-progress-bar__label--top">SEO &amp; Accessibility - 88%</div><div class="dsgo-progress-bar__container" style="width:100%;height:12px;background-color:#e5e7eb;border-radius:6px;overflow:hidden;position:relative"><div class="dsgo-progress-bar__fill " style="width:0%;height:100%;background-color:#f59e0b;transition:width 1.5s ease-out;border-radius:6px"></div></div></div>
<!-- /wp:designsetgo/progress-bar --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

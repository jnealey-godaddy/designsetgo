<?php
/**
 * Title: Education Platform Hero
 * Slug: designsetgo/hero/hero-education-platform
 * Categories: dsgo-hero
 * Description: A two-column education hero with heading, description, enrollment CTA, trust badges, and course image
 * Keywords: hero, education, learning, courses, platform, enrollment
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Education Platform Hero', 'designsetgo' ),
	'categories' => array( 'dsgo-hero' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"base-2","dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeIn","dsgoAnimationDuration":800} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-2-background-color has-background has-dsgo-animation dsgo-animation-fadeIn" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeIn" data-dsgo-animation-duration="800"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/grid {"desktopColumns":2,"style":{"spacing":{"blockGap":"var:preset|spacing|60","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"alignItems":"center"} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:center;row-gap:var(--wp--preset--spacing--60);column-gap:var(--wp--preset--spacing--60)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/pill {"content":"Learn Anytime, Anywhere","justification":"left","dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInDown","fontSize":"small","style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}},"border":{"radius":"50px"},"color":{"background":"#3b82f6","text":"#ffffff"}}} /-->

<!-- wp:heading {"level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","lineHeight":"1.1"},"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"fontSize":"xx-large","dsgoTextRevealEnabled":true,"dsgoTextRevealColor":"#3b82f6","dsgoTextRevealSplitMode":"words"} -->
<h1 class="wp-block-heading has-xx-large-font-size has-dsgo-text-reveal" style="margin-top:var(--wp--preset--spacing--30);font-style:normal;font-weight:700;line-height:1.1" data-dsgo-text-reveal-enabled="true" data-dsgo-text-reveal-color="#3b82f6" data-dsgo-text-reveal-split-mode="words" data-dsgo-text-reveal-transition="150">Master New Skills with Expert-Led Courses</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="margin-top:var(--wp--preset--spacing--20)">Join over 100,000 learners worldwide and transform your career with our comprehensive online courses taught by industry professionals.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--40);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:wrap;gap:var(--wp--preset--spacing--20)"><!-- wp:designsetgo/icon-button {"url":"#courses","icon":"book","iconGap":"8px","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"8px"},"color":{"background":"#3b82f6","text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--left has-text-color has-background"><a class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background dsgo-icon-button--has-icon" style="border-radius:8px;color:#ffffff;background-color:#3b82f6;gap:8px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#courses" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" data-icon-name="book"></span><span class="dsgo-icon-button__text">Browse Courses</span></a></div>
<!-- /wp:designsetgo/icon-button -->

<!-- wp:designsetgo/icon-button {"url":"#trial","icon":"play","iconGap":"8px","className":"has-border-color","backgroundColor":"transparent","textColor":"contrast","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"8px","width":"2px"}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--left has-border-color"><a class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-contrast-color has-transparent-background-color has-text-color has-background dsgo-icon-button--has-icon" style="border-width:2px;border-radius:8px;gap:8px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#trial" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" data-icon-name="play"></span><span class="dsgo-icon-button__text">Free Trial</span></a></div>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|40","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--40);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--40)"><!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/icon {"icon":"circle-check","iconSize":35,"style":{"color":{"text":"#22c55e"}}} /-->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">500+ Courses</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/icon {"icon":"circle-check","iconSize":35,"style":{"color":{"text":"#22c55e"}}} /-->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Expert Instructors</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/icon {"icon":"circle-check","iconSize":35,"style":{"color":{"text":"#22c55e"}}} /-->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Certificates</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":"16px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Students learning online" style="border-radius:16px"/></figure>
<!-- /wp:image --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

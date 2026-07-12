<?php
/**
 * Title: Nonprofit Cause Hero
 * Slug: designsetgo/hero/hero-nonprofit-cause
 * Categories: dsgo-hero
 * Description: An inspiring hero section for nonprofits with cause-driven headline, description, and donation CTAs
 * Keywords: hero, nonprofit, charity, cause, donation, inspire
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Nonprofit Cause Hero', 'designsetgo' ),
	'categories' => array( 'dsgo-hero' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:cover {"url":"{{dsgo:placeholder-landscape-wide}}","alt":"Volunteers helping community","dimRatio":60,"overlayColor":"contrast","minHeight":650,"metadata":{"categories":["dsgo-hero"],"patternName":"designsetgo/hero/hero-nonprofit-cause","name":"Nonprofit Cause Hero"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}}} -->
<div class="wp-block-cover alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30);min-height:650px"><img class="wp-block-cover__image-background" alt="Volunteers helping community" src="{{dsgo:placeholder-landscape-wide}}" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-60 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeIn","dsgoAnimationDuration":800} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeIn" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeIn" data-dsgo-animation-duration="800"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"4px"}},"textColor":"base","fontSize":"small","dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInDown"} -->
<p class="has-text-align-center has-base-color has-text-color has-small-font-size has-dsgo-animation dsgo-animation-fadeInDown" style="letter-spacing:4px;text-transform:uppercase" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInDown">Making a Difference Since 2010</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","lineHeight":"1.1"},"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"textColor":"base","fontSize":"xx-large","dsgoTextRevealEnabled":true,"dsgoTextRevealColor":"#10b981","dsgoTextRevealSplitMode":"words"} -->
<h1 class="wp-block-heading has-text-align-center has-base-color has-text-color has-xx-large-font-size has-dsgo-text-reveal" style="margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:700;line-height:1.1" data-dsgo-text-reveal-enabled="true" data-dsgo-text-reveal-color="#10b981" data-dsgo-text-reveal-split-mode="words" data-dsgo-text-reveal-transition="150">Together We Can<br>Change Lives</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"textColor":"base","fontSize":"medium"} -->
<p class="has-text-align-center has-base-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--30)">Join our mission to provide hope, resources, and opportunities to communities in need around the world.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--40);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:wrap;gap:var(--wp--preset--spacing--20)"><!-- wp:designsetgo/icon-button {"text":"Donate Now","url":"#donate","icon":"heart","iconGap":"8px","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"50px"},"color":{"background":"#10b981","text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--left has-text-color has-background"><a class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background dsgo-icon-button--has-icon" style="border-radius:50px;color:#ffffff;background-color:#10b981;gap:8px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--50)" href="#donate" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" data-icon-name="heart"></span><span class="dsgo-icon-button__text">Donate Now</span></a></div>
<!-- /wp:designsetgo/icon-button -->

<!-- wp:designsetgo/icon-button {"text":"Get Involved","url":"#volunteer","icon":"","iconPosition":"none","iconGap":"8px","backgroundColor":"transparent","textColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"50px","width":"2px","color":"#ffffff"}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--left"><a class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-border-color has-base-color has-transparent-background-color has-text-color has-background" style="border-color:#ffffff;border-width:2px;border-radius:50px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--50)" href="#volunteer" target="_self"><span class="dsgo-icon-button__text">Get Involved</span></a></div>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:cover -->',
);

<?php
/**
 * Title: Team Stats with Description
 * Slug: designsetgo/content/content-team-stats
 * Categories: dsgo-content
 * Description: A two-column section combining team-focused messaging with animated stat counters and progress indicators
 * Keywords: stats, counters, team, metrics, saas, progress
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Team Stats with Description', 'designsetgo' ),
	'categories' => array( 'dsgo-content' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"shapeDividerTop":"tilt","shapeDividerTopColor":"#ffffff","shapeDividerTopHeight":80,"shapeDividerTopFlipY":true,"shapeDividerBottom":"tilt-reverse","shapeDividerBottomColor":"#ffffff","shapeDividerBottomHeight":80,"shapeDividerBottomFlipY":true,"backgroundColor":"contrast","textColor":"base","metadata":{"categories":["dsgo-content"],"patternName":"designsetgo/content/content-team-stats","name":"Team Stats with Description"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-stack--has-shape-divider has-base-color has-contrast-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-shape-divider dsgo-shape-divider--top is-shape-tilt is-flip-y" style="--dsgo-shape-height:80px" aria-hidden="true"></div><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto;padding-top:80px;padding-bottom:80px"><!-- wp:designsetgo/grid {"desktopColumns":2,"style":{"spacing":{"blockGap":"var:preset|spacing|70","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"alignItems":"center"} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:center;row-gap:var(--wp--preset--spacing--70);column-gap:var(--wp--preset--spacing--70)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInLeft","dsgoAnimationDuration":700} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeInLeft" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInLeft" data-dsgo-animation-duration="700"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#8b5cf6"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#8b5cf6;letter-spacing:3px;text-transform:uppercase">Why Choose Us</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:700">Built for Teams Who Ship</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"color":{"text":"#94a3b8"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#94a3b8;margin-top:var(--wp--preset--spacing--20)">We understand the challenges of modern product development. That is why we built a platform that gets out of your way and lets you focus on what matters.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/counter-group {"columns":2,"animationDuration":2000,"animationDelay":200,"animationEasing":"easeOutExpo","className":"dsgo-counter-group-cols-2 dsgo-counter-group-cols-tablet-2 dsgo-counter-group-cols-mobile-1","textColor":"base","style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-designsetgo-counter-group dsgo-counter-group dsgo-counter-group-cols-2 dsgo-counter-group-cols-tablet-2 dsgo-counter-group-cols-mobile-1 has-base-color has-text-color" style="margin-top:var(--wp--preset--spacing--40);align-self:stretch;--dsgo-counter-columns-desktop:2;--dsgo-counter-columns-tablet:2;--dsgo-counter-columns-mobile:1;--dsgo-counter-gap:32px" data-animation-duration="2000" data-animation-delay="200" data-animation-easing="easeOutExpo" data-use-grouping="true" data-separator="," data-decimal="."><div class="dsgo-counter-group__inner dsgo-counter-group__inner--align-center"><!-- wp:designsetgo/counter {"uniqueId":"saas-1","endValue":99,"suffix":"%","label":"Uptime SLA"} -->
<div class="wp-block-designsetgo-counter dsgo-counter" id="saas-1" style="text-align:center" data-start-value="0" data-end-value="99" data-decimals="0" data-prefix="" data-suffix="%" data-duration="2" data-delay="0" data-easing="easeOutQuad" data-use-grouping="true" data-separator="," data-decimal="."><div class="dsgo-counter__content icon-top"><div class="dsgo-counter__number"><span class="dsgo-counter__value">0</span></div></div><div class="dsgo-counter__label">Uptime SLA</div></div>
<!-- /wp:designsetgo/counter -->

<!-- wp:designsetgo/counter {"uniqueId":"saas-2","endValue":50,"suffix":"ms","label":"Avg Response"} -->
<div class="wp-block-designsetgo-counter dsgo-counter" id="saas-2" style="text-align:center" data-start-value="0" data-end-value="50" data-decimals="0" data-prefix="" data-suffix="ms" data-duration="2" data-delay="0" data-easing="easeOutQuad" data-use-grouping="true" data-separator="," data-decimal="."><div class="dsgo-counter__content icon-top"><div class="dsgo-counter__number"><span class="dsgo-counter__value">0</span></div></div><div class="dsgo-counter__label">Avg Response</div></div>
<!-- /wp:designsetgo/counter -->

<!-- wp:designsetgo/counter {"uniqueId":"saas-3","endValue":10,"suffix":"K+","label":"Active Users"} -->
<div class="wp-block-designsetgo-counter dsgo-counter" id="saas-3" style="text-align:center" data-start-value="0" data-end-value="10" data-decimals="0" data-prefix="" data-suffix="K+" data-duration="2" data-delay="0" data-easing="easeOutQuad" data-use-grouping="true" data-separator="," data-decimal="."><div class="dsgo-counter__content icon-top"><div class="dsgo-counter__number"><span class="dsgo-counter__value">0</span></div></div><div class="dsgo-counter__label">Active Users</div></div>
<!-- /wp:designsetgo/counter -->

<!-- wp:designsetgo/counter {"uniqueId":"saas-4","endValue":24,"suffix":"/7","label":"Support"} -->
<div class="wp-block-designsetgo-counter dsgo-counter" id="saas-4" style="text-align:center" data-start-value="0" data-end-value="24" data-decimals="0" data-prefix="" data-suffix="/7" data-duration="2" data-delay="0" data-easing="easeOutQuad" data-use-grouping="true" data-separator="," data-decimal="."><div class="dsgo-counter__content icon-top"><div class="dsgo-counter__number"><span class="dsgo-counter__value">0</span></div></div><div class="dsgo-counter__label">Support</div></div>
<!-- /wp:designsetgo/counter --></div></div>
<!-- /wp:designsetgo/counter-group --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInRight","dsgoAnimationDuration":700} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeInRight" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInRight" data-dsgo-animation-duration="700"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":"16px"}},"dsgoParallaxEnabled":true} -->
<figure class="wp-block-image size-large has-custom-border dsgo-has-parallax" data-dsgo-parallax-enabled="true" data-dsgo-parallax-direction="up" data-dsgo-parallax-speed="5" data-dsgo-parallax-viewport-start="0" data-dsgo-parallax-viewport-end="100" data-dsgo-parallax-relative-to="viewport" data-dsgo-parallax-desktop="true" data-dsgo-parallax-tablet="true" data-dsgo-parallax-mobile="false" data-dsgo-parallax-rotate-enabled="false" data-dsgo-parallax-rotate-direction="cw" data-dsgo-parallax-rotate-speed="3"><img src="{{dsgo:placeholder-landscape}}" alt="Analytics dashboard" style="border-radius:16px"/></figure>
<!-- /wp:image --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div><div class="dsgo-shape-divider dsgo-shape-divider--bottom is-shape-tilt-reverse" style="--dsgo-shape-height:80px" aria-hidden="true"></div></div>
<!-- /wp:designsetgo/section -->',
);

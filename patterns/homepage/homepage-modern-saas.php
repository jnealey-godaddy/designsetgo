<?php
/**
 * Title: Modern SaaS Homepage
 * Slug: designsetgo/homepage/homepage-modern-saas
 * Categories: dsgo-homepage
 * Description: A stunning modern SaaS homepage with shape dividers, parallax effects, image accordion, flip cards, and premium animations
 * Keywords: homepage, saas, product, software, startup, modern, premium
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Modern SaaS Homepage', 'designsetgo' ),
	'categories' => array( 'dsgo-homepage' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"shapeDividerBottom":"wave","shapeDividerBottomHeight":120,"shapeDividerBottomFlipY":true,"backgroundColor":"contrast","textColor":"base","dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeIn","dsgoAnimationDuration":800} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-stack--has-shape-divider has-base-color has-contrast-background-color has-text-color has-background has-dsgo-animation dsgo-animation-fadeIn" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeIn" data-dsgo-animation-duration="800"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto;padding-bottom:120px"><!-- wp:designsetgo/pill {"content":"New: AI-Powered Analytics Now Available","style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|20","right":"var:preset|spacing|20"},"margin":{"bottom":"var:preset|spacing|30"}},"border":{"radius":"50px"},"color":{"background":"rgba(139,92,246,0.2)","text":"#a78bfa"}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInDown","dsgoAnimationDuration":500} -->
<div class="wp-block-designsetgo-pill aligncenter dsgo-pill has-text-color has-background has-small-font-size has-dsgo-animation dsgo-animation-fadeInDown" style="margin-bottom:var(--wp--preset--spacing--30);padding-top:var(--wp--preset--spacing--10);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--10);padding-left:var(--wp--preset--spacing--20)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInDown" data-dsgo-animation-duration="500"><span class="dsgo-pill__content" style="background-color:rgba(139,92,246,0.2);color:#a78bfa;border-radius:50px">New: AI-Powered Analytics Now Available</span></div>
<!-- /wp:designsetgo/pill -->

<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","lineHeight":"1.1"},"spacing":{"margin":{"top":"0"}}},"fontSize":"xx-large","dsgoTextRevealEnabled":true,"dsgoTextRevealColor":"#8b5cf6","dsgoTextRevealSplitMode":"words"} -->
<h1 class="wp-block-heading has-text-align-center has-xx-large-font-size has-dsgo-text-reveal" style="margin-top:0;font-style:normal;font-weight:700;line-height:1.1" data-dsgo-text-reveal-enabled="true" data-dsgo-text-reveal-color="#8b5cf6" data-dsgo-text-reveal-split-mode="words" data-dsgo-text-reveal-transition="150">Build Better Products<br>Ship Faster</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}},"color":{"text":"#94a3b8"}},"fontSize":"large"} -->
<p class="has-text-align-center has-text-color has-large-font-size" style="color:#94a3b8;margin-top:var(--wp--preset--spacing--30)">The all-in-one platform that helps teams collaborate, analyze, and deliver exceptional software experiences.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInUp","dsgoAnimationDelay":200} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint has-dsgo-animation dsgo-animation-fadeInUp" style="margin-top:var(--wp--preset--spacing--40);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInUp" data-dsgo-animation-delay="200"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:wrap;gap:var(--wp--preset--spacing--20)"><!-- wp:designsetgo/icon-button {"text":"Start Free Trial","url":"#trial","icon":"arrow-right","iconPosition":"end","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"8px"},"color":{"background":"#8b5cf6","text":"#ffffff"}}} -->
<a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background" style="border-radius:8px;display:inline-flex;align-items:center;justify-content:center;gap:8px;width:auto;flex-direction:row-reverse;background-color:#8b5cf6;color:#ffffff;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#trial" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" style="display:flex;align-items:center;justify-content:center;width:20px;height:20px;flex-shrink:0" data-icon-name="arrow-right" data-icon-size="20"></span><span class="dsgo-icon-button__text">Start Free Trial</span></a>
<!-- /wp:designsetgo/icon-button -->

<!-- wp:designsetgo/icon-button {"text":"Watch Demo","url":"#demo","icon":"play","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"8px","width":"1px","color":"#475569"},"color":{"background":"transparent","text":"#e2e8f0"}}} -->
<a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background has-border-color" style="border-color:#475569;border-width:1px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;gap:8px;width:auto;flex-direction:row;background-color:transparent;color:#e2e8f0;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#demo" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" style="display:flex;align-items:center;justify-content:center;width:20px;height:20px;flex-shrink:0" data-icon-name="play" data-icon-size="20"></span><span class="dsgo-icon-button__text">Watch Demo</span></a>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:image {"sizeSlug":"large","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}},"border":{"radius":"16px"}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInUp","dsgoAnimationDuration":800,"dsgoAnimationDelay":400,"dsgoParallaxEnabled":true,"dsgoParallaxSpeed":3} -->
<figure class="wp-block-image size-large has-custom-border has-dsgo-animation dsgo-animation-fadeInUp dsgo-has-parallax" style="margin-top:var(--wp--preset--spacing--60)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInUp" data-dsgo-animation-duration="800" data-dsgo-animation-delay="400" data-dsgo-parallax-enabled="true" data-dsgo-parallax-direction="up" data-dsgo-parallax-speed="3" data-dsgo-parallax-viewport-start="0" data-dsgo-parallax-viewport-end="100" data-dsgo-parallax-relative-to="viewport" data-dsgo-parallax-desktop="true" data-dsgo-parallax-tablet="true" data-dsgo-parallax-mobile="false"><img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&amp;h=700&amp;fit=crop" alt="Dashboard preview" style="border-radius:16px"/></figure>
<!-- /wp:image --></div><div class="dsgo-shape-divider dsgo-shape-divider--bottom" style="--dsgo-shape-height:120px;--dsgo-shape-width:100%;--dsgo-shape-offset:-0%;--dsgo-shape-color:currentColor" aria-hidden="true"><svg viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M0,0 C300,120 900,0 1200,80 L1200,120 L0,120 Z"></path></svg></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"base-2"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-2-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}},"color":{"text":"#64748b"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#64748b;margin-bottom:var(--wp--preset--spacing--30)">TRUSTED BY INNOVATIVE TEAMS WORLDWIDE</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|60","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:wrap;gap:var(--wp--preset--spacing--60)"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","letterSpacing":"2px"},"color":{"text":"#94a3b8"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#94a3b8;font-style:normal;font-weight:700;letter-spacing:2px">STRIPE</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","letterSpacing":"2px"},"color":{"text":"#94a3b8"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#94a3b8;font-style:normal;font-weight:700;letter-spacing:2px">NOTION</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","letterSpacing":"2px"},"color":{"text":"#94a3b8"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#94a3b8;font-style:normal;font-weight:700;letter-spacing:2px">FIGMA</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","letterSpacing":"2px"},"color":{"text":"#94a3b8"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#94a3b8;font-style:normal;font-weight:700;letter-spacing:2px">VERCEL</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","letterSpacing":"2px"},"color":{"text":"#94a3b8"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#94a3b8;font-style:normal;font-weight:700;letter-spacing:2px">LINEAR</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeIn"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeIn" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeIn"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#8b5cf6"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#8b5cf6;letter-spacing:3px;text-transform:uppercase">Features</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:700">Everything You Need to Scale</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"color":{"text":"#64748b"}},"fontSize":"medium"} -->
<p class="has-text-align-center has-text-color has-medium-font-size" style="color:#64748b;margin-top:var(--wp--preset--spacing--20)">Powerful tools designed to help your team move faster and build better products.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-3 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(3, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--30);column-gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/flip-card -->
<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-flip dsgo-flip-card--horizontal" style="--dsgo-flip-duration:0.6s;width:100%" data-flip-trigger="hover" data-flip-effect="flip" data-flip-direction="horizontal"><div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face {"side":"front","className":"dsgo-flip-card__face\u002d\u002dfront","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"16px"},"color":{"gradient":"linear-gradient(135deg,rgb(139,92,246) 0%,rgb(79,70,229) 100%)"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front dsgo-flip-card__face--front has-background" style="border-radius:16px;background:linear-gradient(135deg,rgb(139,92,246) 0%,rgb(79,70,229) 100%);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/icon {"icon":"chart","iconSize":56,"style":{"color":{"text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color" style="color:#ffffff;display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:56px;height:56px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="chart" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Chart"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--30)">Real-time Analytics</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}},"color":{"text":"rgba(255,255,255,0.8)"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:rgba(255,255,255,0.8);margin-top:var(--wp--preset--spacing--10)">Hover to learn more</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face -->

<!-- wp:designsetgo/flip-card-face {"side":"back","className":"dsgo-flip-card__face\u002d\u002dback","backgroundColor":"base-2","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"16px"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__back dsgo-flip-card__face--back has-base-2-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">Real-time Analytics</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--20)">Track every metric that matters with live dashboards. Get instant insights into user behavior, conversion rates, and performance metrics.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"color":{"text":"#8b5cf6"},"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#8b5cf6;margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:600">Learn more →</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face --></div></div>
<!-- /wp:designsetgo/flip-card -->

<!-- wp:designsetgo/flip-card -->
<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-flip dsgo-flip-card--horizontal" style="--dsgo-flip-duration:0.6s;width:100%" data-flip-trigger="hover" data-flip-effect="flip" data-flip-direction="horizontal"><div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face {"side":"front","className":"dsgo-flip-card__face\u002d\u002dfront","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"16px"},"color":{"gradient":"linear-gradient(135deg,rgb(6,182,212) 0%,rgb(59,130,246) 100%)"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front dsgo-flip-card__face--front has-background" style="border-radius:16px;background:linear-gradient(135deg,rgb(6,182,212) 0%,rgb(59,130,246) 100%);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/icon {"icon":"users","iconSize":56,"style":{"color":{"text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color" style="color:#ffffff;display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:56px;height:56px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="users" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Users"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--30)">Team Collaboration</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}},"color":{"text":"rgba(255,255,255,0.8)"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:rgba(255,255,255,0.8);margin-top:var(--wp--preset--spacing--10)">Hover to learn more</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face -->

<!-- wp:designsetgo/flip-card-face {"side":"back","className":"dsgo-flip-card__face\u002d\u002dback","backgroundColor":"base-2","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"16px"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__back dsgo-flip-card__face--back has-base-2-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">Team Collaboration</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--20)">Work together seamlessly with real-time editing, comments, and shared workspaces. Keep everyone aligned and moving fast.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"color":{"text":"#ec4899"},"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#ec4899;margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:600">Learn more →</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face --></div></div>
<!-- /wp:designsetgo/flip-card -->

<!-- wp:designsetgo/flip-card -->
<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-flip dsgo-flip-card--horizontal" style="--dsgo-flip-duration:0.6s;width:100%" data-flip-trigger="hover" data-flip-effect="flip" data-flip-direction="horizontal"><div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face {"side":"front","className":"dsgo-flip-card__face\u002d\u002dfront","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"16px"},"color":{"gradient":"linear-gradient(135deg,rgb(236,72,153) 0%,rgb(239,68,68) 100%)"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front dsgo-flip-card__face--front has-background" style="border-radius:16px;background:linear-gradient(135deg,rgb(236,72,153) 0%,rgb(239,68,68) 100%);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/icon {"icon":"lightning","iconSize":56,"style":{"color":{"text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color" style="color:#ffffff;display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:56px;height:56px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="lightning" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Lightning"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--30)">Automation</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}},"color":{"text":"rgba(255,255,255,0.8)"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:rgba(255,255,255,0.8);margin-top:var(--wp--preset--spacing--10)">Hover to learn more</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face -->

<!-- wp:designsetgo/flip-card-face {"side":"back","className":"dsgo-flip-card__face\u002d\u002dback","backgroundColor":"base-2","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"16px"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__back dsgo-flip-card__face--back has-base-2-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">Automation</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--20)">Automate repetitive tasks and workflows. Set up triggers, actions, and rules that work while you sleep.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"color":{"text":"#ec4899"},"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#ec4899;margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:600">Learn more →</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face --></div></div>
<!-- /wp:designsetgo/flip-card --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"shapeDividerTop":"tilt","shapeDividerTopColor":"#ffffff","shapeDividerTopHeight":80,"shapeDividerTopFlipY":true,"shapeDividerBottom":"tilt-reverse","shapeDividerBottomColor":"#ffffff","shapeDividerBottomHeight":80,"shapeDividerBottomFlipY":true,"backgroundColor":"contrast","textColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-stack--has-shape-divider has-base-color has-contrast-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-shape-divider dsgo-shape-divider--top" style="--dsgo-shape-height:80px;--dsgo-shape-width:100%;--dsgo-shape-offset:-0%;--dsgo-shape-color:#ffffff" aria-hidden="true"><svg viewBox="0 0 1200 120" preserveAspectRatio="none" style="transform:scaleY(-1)"><path d="M0,0 L1200,120 L1200,120 L0,120 Z"></path></svg></div><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto;padding-top:80px;padding-bottom:80px"><!-- wp:designsetgo/grid {"desktopColumns":2,"style":{"spacing":{"blockGap":"var:preset|spacing|70","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"alignItems":"center"} -->
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
<figure class="wp-block-image size-large has-custom-border dsgo-has-parallax" data-dsgo-parallax-enabled="true" data-dsgo-parallax-direction="up" data-dsgo-parallax-speed="5" data-dsgo-parallax-viewport-start="0" data-dsgo-parallax-viewport-end="100" data-dsgo-parallax-relative-to="viewport" data-dsgo-parallax-desktop="true" data-dsgo-parallax-tablet="true" data-dsgo-parallax-mobile="false"><img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&amp;h=500&amp;fit=crop" alt="Analytics dashboard" style="border-radius:16px"/></figure>
<!-- /wp:image --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div><div class="dsgo-shape-divider dsgo-shape-divider--bottom" style="--dsgo-shape-height:80px;--dsgo-shape-width:100%;--dsgo-shape-offset:-0%;--dsgo-shape-color:#ffffff" aria-hidden="true"><svg viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M0,120 L1200,0 L1200,120 L0,120 Z"></path></svg></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeIn"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeIn" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeIn"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--50);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#8b5cf6"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#8b5cf6;letter-spacing:3px;text-transform:uppercase">Product Showcase</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:700">See It in Action</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/image-accordion {"gap":"8px","overlayOpacity":30,"overlayOpacityExpanded":10} -->
<div class="wp-block-designsetgo-image-accordion dsgo-image-accordion dsgo-image-accordion--hover" style="--dsgo-image-accordion-height:500px;--dsgo-image-accordion-gap:8px;--dsgo-image-accordion-expanded-ratio:3;--dsgo-image-accordion-transition:0.5s;--dsgo-image-accordion-overlay-color:#000000;--dsgo-image-accordion-overlay-opacity:0.3;--dsgo-image-accordion-overlay-opacity-expanded:0.1" data-trigger-type="hover" data-default-expanded="0" data-enable-overlay="true"><div class="dsgo-image-accordion__items"><!-- wp:designsetgo/image-accordion-item {"uniqueId":"acc-1","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=600\u0026h=800\u0026fit=crop"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-overlay-color:#000000;--dsgo-overlay-opacity:0.4;--dsgo-overlay-opacity-expanded:0.2;--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="acc-1" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Dashboard</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">Powerful analytics at your fingertips</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item -->

<!-- wp:designsetgo/image-accordion-item {"uniqueId":"acc-2","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600\u0026h=800\u0026fit=crop"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-overlay-color:#000000;--dsgo-overlay-opacity:0.4;--dsgo-overlay-opacity-expanded:0.2;--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="acc-2" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Reports</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">Generate insights in seconds</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item -->

<!-- wp:designsetgo/image-accordion-item {"uniqueId":"acc-3","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=600\u0026h=800\u0026fit=crop"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-overlay-color:#000000;--dsgo-overlay-opacity:0.4;--dsgo-overlay-opacity-expanded:0.2;--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="acc-3" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Team Hub</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">Collaborate in real-time</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item -->

<!-- wp:designsetgo/image-accordion-item {"uniqueId":"acc-4","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"https://images.unsplash.com/photo-1553877522-43269d4ea984?w=600\u0026h=800\u0026fit=crop"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-overlay-color:#000000;--dsgo-overlay-opacity:0.4;--dsgo-overlay-opacity-expanded:0.2;--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="acc-4" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Integrations</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">Connect with 100+ tools</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item --></div></div>
<!-- /wp:designsetgo/image-accordion --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"base-2","dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeIn"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-2-background-color has-background has-dsgo-animation dsgo-animation-fadeIn" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeIn"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#8b5cf6"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#8b5cf6;letter-spacing:3px;text-transform:uppercase">Pricing</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:700">Simple, Transparent Pricing</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-3 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(3, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--30);column-gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"16px"}},"backgroundColor":"base","dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInUp","dsgoAnimationDuration":500} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-background-color has-background has-dsgo-animation dsgo-animation-fadeInUp" style="border-radius:16px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInUp" data-dsgo-animation-duration="500"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"textAlign":"center","level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-text-align-center has-large-font-size">Starter</h3>
<!-- /wp:heading -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"},"blockGap":"0","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"center","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:nowrap;gap:0"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"400"},"color":{"text":"#64748b"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#64748b;font-style:normal;font-weight:400">$</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"xx-large"} -->
<p class="has-xx-large-font-size" style="font-style:normal;font-weight:700">0</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"400"},"color":{"text":"#64748b"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#64748b;font-style:normal;font-weight:400">/month</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:separator {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<hr class="wp-block-separator has-alpha-channel-opacity" style="margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--30)"/>
<!-- /wp:separator -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"var:preset|spacing|15"}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">Up to 3 team members</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">Basic analytics</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">1GB storage</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">Community support</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/icon-button {"align":"center","text":"Get Started Free","url":"#starter","icon":"","iconPosition":"none","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"margin":{"top":"var:preset|spacing|30"}},"border":{"radius":"8px","width":"1px","color":"#e2e8f0"},"color":{"background":"transparent","text":"#0f172a"}}} -->
<a class="wp-block-designsetgo-icon-button aligncenter dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background has-border-color" style="border-color:#e2e8f0;border-width:1px;border-radius:8px;margin-top:var(--wp--preset--spacing--30);display:inline-flex;align-items:center;justify-content:center;gap:0;width:auto;flex-direction:row;background-color:transparent;color:#0f172a;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#starter" target="_self"><span class="dsgo-icon-button__text">Get Started Free</span></a>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"16px","width":"2px","color":"#8b5cf6"}},"backgroundColor":"base","dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInUp","dsgoAnimationDuration":500,"dsgoAnimationDelay":100} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-border-color has-base-background-color has-background has-dsgo-animation dsgo-animation-fadeInUp" style="border-color:#8b5cf6;border-width:2px;border-radius:16px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInUp" data-dsgo-animation-duration="500" data-dsgo-animation-delay="100"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/pill {"content":"Most Popular","style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|20","right":"var:preset|spacing|20"},"margin":{"bottom":"var:preset|spacing|20"}},"border":{"radius":"50px"},"color":{"background":"#8b5cf6","text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-pill aligncenter dsgo-pill has-text-color has-background has-small-font-size" style="margin-bottom:var(--wp--preset--spacing--20);padding-top:var(--wp--preset--spacing--10);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--10);padding-left:var(--wp--preset--spacing--20)"><span class="dsgo-pill__content" style="background-color:#8b5cf6;color:#ffffff;border-radius:50px">Most Popular</span></div>
<!-- /wp:designsetgo/pill -->

<!-- wp:heading {"textAlign":"center","level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-text-align-center has-large-font-size">Pro</h3>
<!-- /wp:heading -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"},"blockGap":"0","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"center","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:nowrap;gap:0"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"400"},"color":{"text":"#64748b"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#64748b;font-style:normal;font-weight:400">$</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"xx-large"} -->
<p class="has-xx-large-font-size" style="font-style:normal;font-weight:700">29</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"400"},"color":{"text":"#64748b"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#64748b;font-style:normal;font-weight:400">/month</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:separator {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<hr class="wp-block-separator has-alpha-channel-opacity" style="margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--30)"/>
<!-- /wp:separator -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"var:preset|spacing|15"}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">Unlimited team members</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">Advanced analytics</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">100GB storage</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">Priority support</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">API access</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/icon-button {"align":"center","text":"Start Free Trial","url":"#pro","icon":"","iconPosition":"none","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"margin":{"top":"var:preset|spacing|30"}},"border":{"radius":"8px"},"color":{"background":"#8b5cf6","text":"#ffffff"}}} -->
<a class="wp-block-designsetgo-icon-button aligncenter dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background" style="border-radius:8px;margin-top:var(--wp--preset--spacing--30);display:inline-flex;align-items:center;justify-content:center;gap:0;width:auto;flex-direction:row;background-color:#8b5cf6;color:#ffffff;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#pro" target="_self"><span class="dsgo-icon-button__text">Start Free Trial</span></a>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"16px"}},"backgroundColor":"base","dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInUp","dsgoAnimationDuration":500,"dsgoAnimationDelay":200} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-background-color has-background has-dsgo-animation dsgo-animation-fadeInUp" style="border-radius:16px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInUp" data-dsgo-animation-duration="500" data-dsgo-animation-delay="200"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"textAlign":"center","level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-text-align-center has-large-font-size">Enterprise</h3>
<!-- /wp:heading -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"},"blockGap":"0","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"center","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:nowrap;gap:0"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"xx-large"} -->
<p class="has-xx-large-font-size" style="font-style:normal;font-weight:700">Custom</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:separator {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<hr class="wp-block-separator has-alpha-channel-opacity" style="margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--30)"/>
<!-- /wp:separator -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"var:preset|spacing|15"}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">Everything in Pro</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">Custom integrations</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">Unlimited storage</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">Dedicated support</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">SLA guarantee</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/icon-button {"align":"center","text":"Contact Sales","url":"#enterprise","icon":"","iconPosition":"none","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"margin":{"top":"var:preset|spacing|30"}},"border":{"radius":"8px","width":"1px","color":"#e2e8f0"},"color":{"background":"transparent","text":"#0f172a"}}} -->
<a class="wp-block-designsetgo-icon-button aligncenter dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background has-border-color" style="border-color:#e2e8f0;border-width:1px;border-radius:8px;margin-top:var(--wp--preset--spacing--30);display:inline-flex;align-items:center;justify-content:center;gap:0;width:auto;flex-direction:row;background-color:transparent;color:#0f172a;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#enterprise" target="_self"><span class="dsgo-icon-button__text">Contact Sales</span></a>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"shapeDividerTop":"curve","shapeDividerTopFlipY":true,"backgroundColor":"contrast","textColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-stack--has-shape-divider has-base-color has-contrast-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-shape-divider dsgo-shape-divider--top" style="--dsgo-shape-height:100px;--dsgo-shape-width:100%;--dsgo-shape-offset:-0%;--dsgo-shape-color:currentColor" aria-hidden="true"><svg viewBox="0 0 1200 120" preserveAspectRatio="none" style="transform:scaleY(-1)"><path d="M0,120 Q600,0 1200,120 L1200,120 L0,120 Z"></path></svg></div><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto;padding-top:100px"><!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="font-style:normal;font-weight:700">Ready to Transform Your Workflow?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"color":{"text":"#94a3b8"}},"fontSize":"medium"} -->
<p class="has-text-align-center has-text-color has-medium-font-size" style="color:#94a3b8;margin-top:var(--wp--preset--spacing--20)">Join thousands of teams already using our platform to ship better products faster. Start your free trial today.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--40);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:wrap;gap:var(--wp--preset--spacing--20)"><!-- wp:designsetgo/icon-button {"text":"Start Free Trial","url":"#trial","icon":"arrow-right","iconPosition":"end","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"8px"},"color":{"background":"#8b5cf6","text":"#ffffff"}}} -->
<a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background" style="border-radius:8px;display:inline-flex;align-items:center;justify-content:center;gap:8px;width:auto;flex-direction:row-reverse;background-color:#8b5cf6;color:#ffffff;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--50)" href="#trial" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" style="display:flex;align-items:center;justify-content:center;width:20px;height:20px;flex-shrink:0" data-icon-name="arrow-right" data-icon-size="20"></span><span class="dsgo-icon-button__text">Start Free Trial</span></a>
<!-- /wp:designsetgo/icon-button -->

<!-- wp:designsetgo/icon-button {"text":"Talk to Sales","url":"#sales","icon":"","iconPosition":"none","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"8px","width":"1px","color":"#475569"},"color":{"background":"transparent","text":"#e2e8f0"}}} -->
<a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background has-border-color" style="border-color:#475569;border-width:1px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;gap:0;width:auto;flex-direction:row;background-color:transparent;color:#e2e8f0;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--50)" href="#sales" target="_self"><span class="dsgo-icon-button__text">Talk to Sales</span></a>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section -->',
);

<?php
/**
 * Title: Features Flip Card Grid
 * Slug: designsetgo/features/features-flip-card-grid
 * Categories: dsgo-features
 * Description: A three-column feature grid using interactive flip cards with icons and detailed descriptions
 * Keywords: features, flip cards, grid, interactive, saas, hover
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Features Flip Card Grid', 'designsetgo' ),
	'categories' => array( 'dsgo-features' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeIn"} -->
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
<!-- /wp:designsetgo/section -->',
);

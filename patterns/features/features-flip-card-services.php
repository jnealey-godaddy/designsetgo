<?php
/**
 * Title: Flip Card Services
 * Slug: designsetgo/features/features-flip-card-services
 * Categories: dsgo-features
 * Description: A three-column service showcase using interactive flip cards with front and back content
 * Keywords: features, flip cards, services, interactive, real estate, hover
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Flip Card Services', 'designsetgo' ),
	'categories' => array( 'dsgo-features' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeIn"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeIn" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeIn"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"4px"},"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#d4af37;letter-spacing:4px;text-transform:uppercase">Our Services</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"300","letterSpacing":"-0.5px"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:300;letter-spacing:-0.5px">White-Glove Service at Every Step</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-3 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(3, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--30);column-gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/flip-card -->
<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-flip dsgo-flip-card--horizontal" style="--dsgo-flip-duration:0.6s;width:100%" data-flip-trigger="hover" data-flip-effect="flip" data-flip-direction="horizontal"><div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face {"side":"front","className":"dsgo-flip-card__face\u002d\u002dfront","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"0"},"color":{"gradient":"linear-gradient(135deg,rgb(15,23,42) 0%,rgb(30,41,59) 100%)"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front dsgo-flip-card__face--front has-background" style="border-radius:0;background:linear-gradient(135deg,rgb(15,23,42) 0%,rgb(30,41,59) 100%);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)"><!-- wp:designsetgo/icon {"icon":"home","style":{"color":{"text":"#d4af37"}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"zoomIn","dsgoAnimationDuration":500} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color has-dsgo-animation dsgo-animation-zoomIn" style="color:#d4af37;display:flex;align-items:center;justify-content:center" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="zoomIn" data-dsgo-animation-duration="500"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:48px;height:48px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="home" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Home"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--30)">Buyer Representation</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}},"color":{"text":"rgba(255,255,255,0.6)"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:rgba(255,255,255,0.6);margin-top:var(--wp--preset--spacing--10)">Hover to learn more</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face -->

<!-- wp:designsetgo/flip-card-face {"side":"back","className":"dsgo-flip-card__face\u002d\u002dback","backgroundColor":"base-2","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"0"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__back dsgo-flip-card__face--back has-base-2-background-color has-background" style="border-radius:0;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">Buyer Representation</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|15"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--15)">Our expert agents guide you through every step of finding and acquiring your perfect property. From initial search to closing, we negotiate on your behalf to secure the best terms.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|15"}},"color":{"text":"#d4af37"},"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37;margin-top:var(--wp--preset--spacing--15);font-style:normal;font-weight:600">Learn more →</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face --></div></div>
<!-- /wp:designsetgo/flip-card -->

<!-- wp:designsetgo/flip-card -->
<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-flip dsgo-flip-card--horizontal" style="--dsgo-flip-duration:0.6s;width:100%" data-flip-trigger="hover" data-flip-effect="flip" data-flip-direction="horizontal"><div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face {"side":"front","className":"dsgo-flip-card__face\u002d\u002dfront","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"0"},"color":{"gradient":"linear-gradient(135deg,rgb(15,23,42) 0%,rgb(30,41,59) 100%)"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front dsgo-flip-card__face--front has-background" style="border-radius:0;background:linear-gradient(135deg,rgb(15,23,42) 0%,rgb(30,41,59) 100%);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)"><!-- wp:designsetgo/icon {"icon":"chart","style":{"color":{"text":"#d4af37"}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"zoomIn","dsgoAnimationDuration":500,"dsgoAnimationDelay":100} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color has-dsgo-animation dsgo-animation-zoomIn" style="color:#d4af37;display:flex;align-items:center;justify-content:center" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="zoomIn" data-dsgo-animation-duration="500" data-dsgo-animation-delay="100"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:48px;height:48px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="chart" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Chart"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--30)">Property Valuation</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}},"color":{"text":"rgba(255,255,255,0.6)"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:rgba(255,255,255,0.6);margin-top:var(--wp--preset--spacing--10)">Hover to learn more</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face -->

<!-- wp:designsetgo/flip-card-face {"side":"back","className":"dsgo-flip-card__face\u002d\u002dback","backgroundColor":"base-2","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"0"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__back dsgo-flip-card__face--back has-base-2-background-color has-background" style="border-radius:0;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">Property Valuation</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|15"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--15)">Receive accurate, data-driven valuations backed by our deep knowledge of luxury markets. We analyze comparable sales, market trends, and unique property features.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|15"}},"color":{"text":"#d4af37"},"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37;margin-top:var(--wp--preset--spacing--15);font-style:normal;font-weight:600">Learn more →</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face --></div></div>
<!-- /wp:designsetgo/flip-card -->

<!-- wp:designsetgo/flip-card -->
<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-flip dsgo-flip-card--horizontal" style="--dsgo-flip-duration:0.6s;width:100%" data-flip-trigger="hover" data-flip-effect="flip" data-flip-direction="horizontal"><div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face {"side":"front","className":"dsgo-flip-card__face\u002d\u002dfront","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"0"},"color":{"gradient":"linear-gradient(135deg,rgb(15,23,42) 0%,rgb(30,41,59) 100%)"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front dsgo-flip-card__face--front has-background" style="border-radius:0;background:linear-gradient(135deg,rgb(15,23,42) 0%,rgb(30,41,59) 100%);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)"><!-- wp:designsetgo/icon {"icon":"globe","style":{"color":{"text":"#d4af37"}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"zoomIn","dsgoAnimationDuration":500,"dsgoAnimationDelay":200} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color has-dsgo-animation dsgo-animation-zoomIn" style="color:#d4af37;display:flex;align-items:center;justify-content:center" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="zoomIn" data-dsgo-animation-duration="500" data-dsgo-animation-delay="200"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:48px;height:48px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="globe" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Globe"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--30)">Global Reach</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}},"color":{"text":"rgba(255,255,255,0.6)"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:rgba(255,255,255,0.6);margin-top:var(--wp--preset--spacing--10)">Hover to learn more</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face -->

<!-- wp:designsetgo/flip-card-face {"side":"back","className":"dsgo-flip-card__face\u002d\u002dback","backgroundColor":"base-2","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"0"}}} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__back dsgo-flip-card__face--back has-base-2-background-color has-background" style="border-radius:0;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">Global Reach</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|15"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--15)">Access exclusive listings worldwide through our international network. From New York penthouses to Mediterranean villas, we connect you with extraordinary properties globally.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|15"}},"color":{"text":"#d4af37"},"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37;margin-top:var(--wp--preset--spacing--15);font-style:normal;font-weight:600">Learn more →</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face --></div></div>
<!-- /wp:designsetgo/flip-card --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

<?php
/**
 * Title: Legacy Stats with Description
 * Slug: designsetgo/content/content-legacy-stats
 * Categories: dsgo-content
 * Description: A two-column section combining a company legacy narrative with animated stat counters
 * Keywords: stats, counters, legacy, about, real estate, luxury
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Legacy Stats with Description', 'designsetgo' ),
	'categories' => array( 'dsgo-content' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"metadata":{"categories":["dsgo-content"],"patternName":"designsetgo/content/content-legacy-stats","name":"Legacy Stats with Description"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/grid {"desktopColumns":2,"style":{"spacing":{"blockGap":"var:preset|spacing|70","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"alignItems":"center"} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:center;row-gap:var(--wp--preset--spacing--70);column-gap:var(--wp--preset--spacing--70)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInLeft","dsgoAnimationDuration":700} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeInLeft" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInLeft" data-dsgo-animation-duration="700"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"4px"},"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37;letter-spacing:4px;text-transform:uppercase">About Us</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"300","letterSpacing":"-0.5px"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:300;letter-spacing:-0.5px">A Legacy of Excellence in Luxury Real Estate</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"color":{"text":"#64748b"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#64748b;margin-top:var(--wp--preset--spacing--20)">For over two decades, we have been the trusted advisors for clients seeking extraordinary properties. Our deep expertise in luxury markets, combined with unparalleled discretion and personalized service, sets us apart.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"color":{"text":"#64748b"}}} -->
<p class="has-text-color" style="color:#64748b;margin-top:var(--wp--preset--spacing--20)">We understand that finding the perfect home is about more than square footage and amenities. It is about discovering a residence that reflects your lifestyle, aspirations, and vision for the future.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/counter-group {"columns":2,"animationDuration":2500,"animationDelay":200,"animationEasing":"easeOutExpo","className":"dsgo-counter-group-cols-2 dsgo-counter-group-cols-tablet-2 dsgo-counter-group-cols-mobile-2","style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-designsetgo-counter-group dsgo-counter-group dsgo-counter-group-cols-2 dsgo-counter-group-cols-tablet-2 dsgo-counter-group-cols-mobile-2" style="margin-top:var(--wp--preset--spacing--40);align-self:stretch;--dsgo-counter-columns-desktop:2;--dsgo-counter-columns-tablet:2;--dsgo-counter-columns-mobile:1;--dsgo-counter-gap:32px" data-animation-duration="2500" data-animation-delay="200" data-animation-easing="easeOutExpo" data-use-grouping="true" data-separator="," data-decimal="."><div class="dsgo-counter-group__inner dsgo-counter-group__inner--align-center"><!-- wp:designsetgo/counter {"uniqueId":"re-1","endValue":2,"prefix":"$","suffix":"B+","label":"In Sales Volume"} -->
<div class="wp-block-designsetgo-counter dsgo-counter" id="re-1" style="text-align:center" data-start-value="0" data-end-value="2" data-decimals="0" data-prefix="$" data-suffix="B+" data-duration="2" data-delay="0" data-easing="easeOutQuad" data-use-grouping="true" data-separator="," data-decimal="."><div class="dsgo-counter__content icon-top"><div class="dsgo-counter__number"><span class="dsgo-counter__value">0</span></div></div><div class="dsgo-counter__label">In Sales Volume</div></div>
<!-- /wp:designsetgo/counter -->

<!-- wp:designsetgo/counter {"uniqueId":"re-2","endValue":500,"suffix":"+","label":"Properties Sold"} -->
<div class="wp-block-designsetgo-counter dsgo-counter" id="re-2" style="text-align:center" data-start-value="0" data-end-value="500" data-decimals="0" data-prefix="" data-suffix="+" data-duration="2" data-delay="0" data-easing="easeOutQuad" data-use-grouping="true" data-separator="," data-decimal="."><div class="dsgo-counter__content icon-top"><div class="dsgo-counter__number"><span class="dsgo-counter__value">0</span></div></div><div class="dsgo-counter__label">Properties Sold</div></div>
<!-- /wp:designsetgo/counter --></div></div>
<!-- /wp:designsetgo/counter-group --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInRight","dsgoAnimationDuration":700} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeInRight" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInRight" data-dsgo-animation-duration="700"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":"0"}},"dsgoParallaxEnabled":true,"dsgoParallaxSpeed":4} -->
<figure class="wp-block-image size-large has-custom-border dsgo-has-parallax" data-dsgo-parallax-enabled="true" data-dsgo-parallax-direction="up" data-dsgo-parallax-speed="4" data-dsgo-parallax-viewport-start="0" data-dsgo-parallax-viewport-end="100" data-dsgo-parallax-relative-to="viewport" data-dsgo-parallax-desktop="true" data-dsgo-parallax-tablet="true" data-dsgo-parallax-mobile="false" data-dsgo-parallax-rotate-enabled="false" data-dsgo-parallax-rotate-direction="cw" data-dsgo-parallax-rotate-speed="3"><img src="{{dsgo:placeholder-portrait}}" alt="Luxury home interior" style="border-radius:0"/></figure>
<!-- /wp:image --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

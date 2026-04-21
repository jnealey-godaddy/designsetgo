<?php
/**
 * Title: Luxury Real Estate Homepage
 * Slug: designsetgo/homepage/homepage-luxury-realestate
 * Categories: dsgo-homepage
 * Description: An elegant luxury real estate homepage with parallax effects, image accordion property showcase, sophisticated animations, and premium design
 * Keywords: homepage, real estate, luxury, property, homes, agent, broker, premium
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Luxury Real Estate Homepage', 'designsetgo' ),
	'categories' => array( 'dsgo-homepage' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:cover {"url":"https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1800\u0026h=1000\u0026fit=crop","alt":"Luxury modern home exterior","dimRatio":70,"overlayColor":"contrast","minHeight":750,"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}}} -->
<div class="wp-block-cover alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30);min-height:750px"><img class="wp-block-cover__image-background" alt="Luxury modern home exterior" src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1800&amp;h=1000&amp;fit=crop" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-70 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeIn","dsgoAnimationDuration":1000} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeIn" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeIn" data-dsgo-animation-duration="1000"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"6px"},"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#d4af37;letter-spacing:6px;text-transform:uppercase">Exceptional Properties</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"300","lineHeight":"1.1","letterSpacing":"-1px"},"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"textColor":"base","fontSize":"xx-large","dsgoTextRevealEnabled":true,"dsgoTextRevealColor":"#d4af37","dsgoTextRevealSplitMode":"words"} -->
<h1 class="wp-block-heading has-text-align-center has-base-color has-text-color has-xx-large-font-size has-dsgo-text-reveal" style="margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:300;letter-spacing:-1px;line-height:1.1" data-dsgo-text-reveal-enabled="true" data-dsgo-text-reveal-color="#d4af37" data-dsgo-text-reveal-split-mode="words" data-dsgo-text-reveal-transition="150">Discover Your<br>Dream Residence</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}},"color":{"text":"rgba(255,255,255,0.85)"}},"fontSize":"medium"} -->
<p class="has-text-align-center has-text-color has-medium-font-size" style="color:rgba(255,255,255,0.85);margin-top:var(--wp--preset--spacing--30)">Curating the world\'s most prestigious properties for discerning buyers who demand excellence.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInUp","dsgoAnimationDelay":300} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint has-dsgo-animation dsgo-animation-fadeInUp" style="margin-top:var(--wp--preset--spacing--50);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInUp" data-dsgo-animation-delay="300"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:wrap;gap:var(--wp--preset--spacing--20)"><!-- wp:designsetgo/icon-button {"text":"View Properties","url":"#properties","icon":"arrow-right","iconPosition":"end","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"0"},"color":{"background":"#d4af37","text":"#0f172a"}}} -->
<a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background" style="border-radius:0;display:inline-flex;align-items:center;justify-content:center;gap:8px;width:auto;flex-direction:row-reverse;background-color:#d4af37;color:#0f172a;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--50)" href="#properties" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" style="display:flex;align-items:center;justify-content:center;width:20px;height:20px;flex-shrink:0" data-icon-name="arrow-right" data-icon-size="20"></span><span class="dsgo-icon-button__text">View Properties</span></a>
<!-- /wp:designsetgo/icon-button -->

<!-- wp:designsetgo/icon-button {"text":"Schedule Consultation","url":"#contact","icon":"","iconPosition":"none","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"0","width":"1px","color":"rgba(255,255,255,0.5)"},"color":{"background":"transparent","text":"#ffffff"}}} -->
<a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background has-border-color" style="border-color:rgba(255,255,255,0.5);border-width:1px;border-radius:0;display:inline-flex;align-items:center;justify-content:center;gap:0;width:auto;flex-direction:row;background-color:transparent;color:#ffffff;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--50)" href="#contact" target="_self"><span class="dsgo-icon-button__text">Schedule Consultation</span></a>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:cover -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}}} -->
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
<figure class="wp-block-image size-large has-custom-border dsgo-has-parallax" data-dsgo-parallax-enabled="true" data-dsgo-parallax-direction="up" data-dsgo-parallax-speed="4" data-dsgo-parallax-viewport-start="0" data-dsgo-parallax-viewport-end="100" data-dsgo-parallax-relative-to="viewport" data-dsgo-parallax-desktop="true" data-dsgo-parallax-tablet="true" data-dsgo-parallax-mobile="false"><img src="https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=600&amp;h=700&amp;fit=crop" alt="Luxury home interior" style="border-radius:0"/></figure>
<!-- /wp:image --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"shapeDividerTop":"tilt","shapeDividerTopHeight":60,"shapeDividerTopFlipY":true,"shapeDividerBottom":"tilt-reverse","shapeDividerBottomHeight":60,"shapeDividerBottomFlipY":true,"backgroundColor":"contrast","textColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-stack--has-shape-divider has-base-color has-contrast-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-shape-divider dsgo-shape-divider--top" style="--dsgo-shape-height:60px;--dsgo-shape-width:100%;--dsgo-shape-offset:-0%;--dsgo-shape-color:currentColor" aria-hidden="true"><svg viewBox="0 0 1200 120" preserveAspectRatio="none" style="transform:scaleY(-1)"><path d="M0,0 L1200,120 L1200,120 L0,120 Z"></path></svg></div><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto;padding-top:60px;padding-bottom:60px"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"4px"},"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#d4af37;letter-spacing:4px;text-transform:uppercase">Featured Listings</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"300","letterSpacing":"-0.5px"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:300;letter-spacing:-0.5px">Exceptional Properties Await</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/image-accordion {"height":"550px","overlayOpacityExpanded":15} -->
<div class="wp-block-designsetgo-image-accordion dsgo-image-accordion dsgo-image-accordion--hover" style="--dsgo-image-accordion-height:550px;--dsgo-image-accordion-gap:4px;--dsgo-image-accordion-expanded-ratio:3;--dsgo-image-accordion-transition:0.5s;--dsgo-image-accordion-overlay-color:#000000;--dsgo-image-accordion-overlay-opacity:0.4;--dsgo-image-accordion-overlay-opacity-expanded:0.15" data-trigger-type="hover" data-default-expanded="0" data-enable-overlay="true"><div class="dsgo-image-accordion__items"><!-- wp:designsetgo/image-accordion-item {"uniqueId":"prop-1","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=600\u0026h=900\u0026fit=crop"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-overlay-color:#000000;--dsgo-overlay-opacity:0.4;--dsgo-overlay-opacity-expanded:0.2;--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="prop-1" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37;letter-spacing:3px;text-transform:uppercase">Beverly Hills</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Modern Estate</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">$12,500,000 | 6 Bed | 8 Bath</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item -->

<!-- wp:designsetgo/image-accordion-item {"uniqueId":"prop-2","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=600\u0026h=900\u0026fit=crop"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-overlay-color:#000000;--dsgo-overlay-opacity:0.4;--dsgo-overlay-opacity-expanded:0.2;--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="prop-2" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37;letter-spacing:3px;text-transform:uppercase">Malibu</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Oceanfront Villa</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">$28,000,000 | 5 Bed | 7 Bath</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item -->

<!-- wp:designsetgo/image-accordion-item {"uniqueId":"prop-3","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?w=600\u0026h=900\u0026fit=crop"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-overlay-color:#000000;--dsgo-overlay-opacity:0.4;--dsgo-overlay-opacity-expanded:0.2;--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="prop-3" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37;letter-spacing:3px;text-transform:uppercase">Bel Air</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Contemporary Mansion</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">$45,000,000 | 8 Bed | 12 Bath</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item -->

<!-- wp:designsetgo/image-accordion-item {"uniqueId":"prop-4","className":"dsgo-image-accordion__item","style":{"background":{"backgroundImage":{"url":"https://images.unsplash.com/photo-1600573472591-ee6c8e695481?w=600\u0026h=900\u0026fit=crop"}}}} -->
<div class="wp-block-designsetgo-image-accordion-item dsgo-image-accordion-item dsgo-image-accordion-item--has-overlay dsgo-image-accordion__item" style="--dsgo-overlay-color:#000000;--dsgo-overlay-opacity:0.4;--dsgo-overlay-opacity-expanded:0.2;--dsgo-vertical-alignment:center;--dsgo-horizontal-alignment:center" data-unique-id="prop-4" role="button" tabindex="0"><div class="dsgo-image-accordion-item__content"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37;letter-spacing:3px;text-transform:uppercase">Hollywood Hills</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"textColor":"base","fontSize":"large"} -->
<h3 class="wp-block-heading has-base-color has-text-color has-large-font-size">Architectural Masterpiece</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"small"} -->
<p class="has-base-color has-text-color has-small-font-size">$18,900,000 | 5 Bed | 6 Bath</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/image-accordion-item --></div></div>
<!-- /wp:designsetgo/image-accordion -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--50);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:wrap;gap:var(--wp--preset--spacing--20)"><!-- wp:designsetgo/icon-button {"text":"View All Properties","url":"#all-properties","icon":"arrow-right","iconPosition":"end","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"0"},"color":{"background":"#d4af37","text":"#0f172a"}}} -->
<a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background" style="border-radius:0;display:inline-flex;align-items:center;justify-content:center;gap:8px;width:auto;flex-direction:row-reverse;background-color:#d4af37;color:#0f172a;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--50)" href="#all-properties" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" style="display:flex;align-items:center;justify-content:center;width:20px;height:20px;flex-shrink:0" data-icon-name="arrow-right" data-icon-size="20"></span><span class="dsgo-icon-button__text">View All Properties</span></a>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/row --></div><div class="dsgo-shape-divider dsgo-shape-divider--bottom" style="--dsgo-shape-height:60px;--dsgo-shape-width:100%;--dsgo-shape-offset:-0%;--dsgo-shape-color:currentColor" aria-hidden="true"><svg viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M0,120 L1200,0 L1200,120 L0,120 Z"></path></svg></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeIn"} -->
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
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"base-2"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-2-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"4px"},"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#d4af37;letter-spacing:4px;text-transform:uppercase">Testimonials</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"300","letterSpacing":"-0.5px"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:300;letter-spacing:-0.5px">What Our Clients Say</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/slider {"slidesPerView":2,"autoplay":true,"autoplayInterval":7000} -->
<div class="wp-block-designsetgo-slider dsgo-slider dsgo-slider--classic dsgo-slider--effect-slide dsgo-slider--has-arrows dsgo-slider--has-dots" style="--dsgo-slider-height:500px;--dsgo-slider-aspect-ratio:16/9;--dsgo-slider-gap:20px;--dsgo-slider-transition:0.5s;--dsgo-slider-slides-per-view:2;--dsgo-slider-slides-per-view-tablet:1;--dsgo-slider-slides-per-view-mobile:1;--dsgo-slider-arrow-size:48px" data-slides-per-view="2" data-slides-per-view-tablet="1" data-slides-per-view-mobile="1" data-use-aspect-ratio="false" data-show-arrows="true" data-show-dots="true" data-arrow-style="default" data-arrow-position="sides" data-arrow-vertical-position="center" data-dot-style="default" data-dot-position="bottom" data-effect="slide" data-transition-duration="0.5s" data-transition-easing="ease-in-out" data-autoplay="true" data-autoplay-interval="7000" data-pause-on-hover="true" data-pause-on-interaction="true" data-loop="true" data-draggable="true" data-swipeable="true" data-free-mode="false" data-centered-slides="false" data-mobile-breakpoint="768" data-tablet-breakpoint="1024" data-active-slide="0" role="region" aria-label="Image slider" aria-roledescription="slider"><div class="dsgo-slider__viewport"><div class="dsgo-slider__track"><!-- wp:designsetgo/slide -->
<div class="wp-block-designsetgo-slide dsgo-slide" style="--dsgo-slide-content-vertical-align:center;--dsgo-slide-content-horizontal-align:center" role="group" aria-roledescription="slide"><div class="dsgo-slide__content"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"0"}},"backgroundColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-background-color has-background" style="border-radius:0;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"italic"},"color":{"text":"#64748b"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#64748b;font-style:italic">"The team made finding our dream home an absolute pleasure. Their knowledge of the luxury market is unmatched, and they found us a property that exceeded all our expectations."</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"},"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--30);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--20)"><!-- wp:image {"sizeSlug":"thumbnail","style":{"border":{"radius":"100px"}}} -->
<figure class="wp-block-image size-thumbnail has-custom-border"><img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&amp;h=80&amp;fit=crop" alt="Client photo" style="border-radius:100px"/></figure>
<!-- /wp:image -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600"}}} -->
<p style="font-style:normal;font-weight:600">Michael Richardson</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37">Beverly Hills Buyer</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/slide -->

<!-- wp:designsetgo/slide -->
<div class="wp-block-designsetgo-slide dsgo-slide" style="--dsgo-slide-content-vertical-align:center;--dsgo-slide-content-horizontal-align:center" role="group" aria-roledescription="slide"><div class="dsgo-slide__content"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"0"}},"backgroundColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-background-color has-background" style="border-radius:0;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"italic"},"color":{"text":"#64748b"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#64748b;font-style:italic">"Selling our estate was a seamless experience. They positioned our property perfectly in the market and attracted qualified buyers from around the world. Truly exceptional service."</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"},"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--30);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--20)"><!-- wp:image {"sizeSlug":"thumbnail","style":{"border":{"radius":"100px"}}} -->
<figure class="wp-block-image size-thumbnail has-custom-border"><img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=80&amp;h=80&amp;fit=crop" alt="Client photo" style="border-radius:100px"/></figure>
<!-- /wp:image -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600"}}} -->
<p style="font-style:normal;font-weight:600">Sarah Chen</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37">Bel Air Seller</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/slide -->

<!-- wp:designsetgo/slide -->
<div class="wp-block-designsetgo-slide dsgo-slide" style="--dsgo-slide-content-vertical-align:center;--dsgo-slide-content-horizontal-align:center" role="group" aria-roledescription="slide"><div class="dsgo-slide__content"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"0"}},"backgroundColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-background-color has-background" style="border-radius:0;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"italic"},"color":{"text":"#64748b"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#64748b;font-style:italic">"As international buyers, we relied heavily on their expertise. They navigated complex legal requirements and found us a stunning oceanfront property that serves as our perfect retreat."</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"},"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--30);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--20)"><!-- wp:image {"sizeSlug":"thumbnail","style":{"border":{"radius":"100px"}}} -->
<figure class="wp-block-image size-thumbnail has-custom-border"><img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=80&amp;h=80&amp;fit=crop" alt="Client photo" style="border-radius:100px"/></figure>
<!-- /wp:image -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600"}}} -->
<p style="font-style:normal;font-weight:600">David Laurent</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37">International Buyer</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/slide --></div></div></div>
<!-- /wp:designsetgo/slider --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"shapeDividerTop":"wave","shapeDividerTopHeight":80,"shapeDividerTopFlipY":true,"backgroundColor":"contrast","textColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-stack--has-shape-divider has-base-color has-contrast-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-shape-divider dsgo-shape-divider--top" style="--dsgo-shape-height:80px;--dsgo-shape-width:100%;--dsgo-shape-offset:-0%;--dsgo-shape-color:currentColor" aria-hidden="true"><svg viewBox="0 0 1200 120" preserveAspectRatio="none" style="transform:scaleY(-1)"><path d="M0,0 C300,120 900,0 1200,80 L1200,120 L0,120 Z"></path></svg></div><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto;padding-top:80px"><!-- wp:designsetgo/grid {"desktopColumns":2,"style":{"spacing":{"blockGap":"var:preset|spacing|70","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"alignItems":"center"} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:center;row-gap:var(--wp--preset--spacing--70);column-gap:var(--wp--preset--spacing--70)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInLeft","dsgoAnimationDuration":700} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeInLeft" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInLeft" data-dsgo-animation-duration="700"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"4px"},"color":{"text":"#d4af37"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#d4af37;letter-spacing:4px;text-transform:uppercase">Contact Us</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"300","letterSpacing":"-0.5px"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:300;letter-spacing:-0.5px">Begin Your Journey to Extraordinary Living</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"color":{"text":"#94a3b8"}},"fontSize":"medium"} -->
<p class="has-text-color has-medium-font-size" style="color:#94a3b8;margin-top:var(--wp--preset--spacing--20)">Whether you are searching for your dream home or considering selling your property, our team is ready to provide the exceptional service you deserve.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-top:var(--wp--preset--spacing--40);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"bottom":"var:preset|spacing|20"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-bottom:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--20)"><!-- wp:designsetgo/icon {"icon":"phone","iconSize":24,"style":{"color":{"text":"#d4af37"}}} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color" style="color:#d4af37;display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="phone" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Phone"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">+1 (310) 555-0100</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"bottom":"var:preset|spacing|20"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-bottom:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--20)"><!-- wp:designsetgo/icon {"icon":"mail","iconSize":24,"style":{"color":{"text":"#d4af37"}}} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color" style="color:#d4af37;display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="mail" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Mail"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">inquiries@luxuryestates.com</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--20)"><!-- wp:designsetgo/icon {"icon":"location","iconSize":24,"style":{"color":{"text":"#d4af37"}}} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color" style="color:#d4af37;display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="location" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Location"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">9876 Wilshire Blvd, Beverly Hills, CA 90210</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"0"}},"backgroundColor":"base","textColor":"contrast","dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInRight","dsgoAnimationDuration":700} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-contrast-color has-base-background-color has-text-color has-background has-dsgo-animation dsgo-animation-fadeInRight" style="border-radius:0;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInRight" data-dsgo-animation-duration="700"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}},"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">Schedule a Consultation</h3>
<!-- /wp:heading -->

<!-- wp:designsetgo/form-builder {"formId":"consultation-form","submitButtonText":"Request Consultation","className":"dsgo-form"} -->
<div class="wp-block-designsetgo-form-builder dsgo-form-builder dsgo-form-builder--align-left dsgo-form" style="--dsgo-form-field-spacing:1.5rem;--dsgo-form-input-height:44px;--dsgo-form-input-padding:0.75rem;--dsgo-form-label-color:;--dsgo-form-border-color:#d1d5db;--dsgo-form-field-bg:" data-form-id="consultation-form" data-ajax-submit="true" data-success-message="Thank you! Your form has been submitted successfully." data-error-message="There was an error submitting the form. Please try again." data-submit-text="Request Consultation"><form class="dsgo-form" method="post" novalidate><div class="dsgo-form__fields"><!-- wp:designsetgo/form-text-field {"fieldName":"field_name_re","label":"Full Name","placeholder":"Enter your full name","required":true} -->
<div class="wp-block-designsetgo-form-text-field dsgo-form-field dsgo-form-field--text" style="flex-basis:100%;max-width:100%"><label for="field-field_name_re" class="dsgo-form-field__label">Full Name<span class="dsgo-form-field__required" aria-label="required">*</span></label><input type="text" id="field-field_name_re" name="field_name_re" class="dsgo-form-field__input" placeholder="Enter your full name" required aria-required="true" data-field-type="text"/></div>
<!-- /wp:designsetgo/form-text-field -->

<!-- wp:designsetgo/form-email-field {"fieldName":"field_email_re","label":"Email Address","placeholder":"Enter your email","required":true} -->
<div class="wp-block-designsetgo-form-email-field dsgo-form-field dsgo-form-field--email" style="flex-basis:100%;max-width:100%"><label for="field-field_email_re" class="dsgo-form-field__label">Email Address<span class="dsgo-form-field__required" aria-label="required">*</span></label><input type="email" id="field-field_email_re" name="field_email_re" class="dsgo-form-field__input" placeholder="Enter your email" required aria-required="true" data-field-type="email"/></div>
<!-- /wp:designsetgo/form-email-field -->

<!-- wp:designsetgo/form-phone-field {"fieldName":"field_phone_re","label":"Phone Number","placeholder":"(310) 555-0100"} -->
<div class="wp-block-designsetgo-form-phone-field dsgo-form-field dsgo-form-field--phone" style="flex-basis:100%;max-width:100%"><label for="field-field_phone_re" class="dsgo-form-field__label">Phone Number</label><div class="dsgo-form-field__phone-wrapper" style="display:flex;gap:0.5rem" data-auto-format="true"><select name="field_phone_re_country_code" class="dsgo-form-field__country-code" data-dsgo-country-code="+1" style="min-width:85px;flex-shrink:0" aria-label="Country Code"></select><input type="tel" id="field-field_phone_re" name="field_phone_re" class="dsgo-form-field__input" placeholder="(310) 555-0100" data-field-type="tel" data-phone-format="any" style="flex:1"/></div></div>
<!-- /wp:designsetgo/form-phone-field -->

<!-- wp:designsetgo/form-select-field {"fieldName":"select_interest_re","label":"I am interested in","options":[{"value":"buying","label":"Buying a Property"},{"value":"selling","label":"Selling a Property"},{"value":"both","label":"Both Buying and Selling"},{"value":"valuation","label":"Property Valuation"}]} -->
<div class="wp-block-designsetgo-form-select-field dsgo-form-field dsgo-form-field--select" style="flex-basis:100%;max-width:100%"><label for="field-select_interest_re" class="dsgo-form-field__label">I am interested in</label><select id="field-select_interest_re" name="select_interest_re" class="dsgo-form-field__select" data-field-type="select"><option value="">-- Select an option --</option><option value="buying">Buying a Property</option><option value="selling">Selling a Property</option><option value="both">Both Buying and Selling</option><option value="valuation">Property Valuation</option></select></div>
<!-- /wp:designsetgo/form-select-field --></div><input type="text" name="dsg_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"/><input type="hidden" name="dsg_form_id" value="consultation-form"/><div class="dsgo-form__footer"><button type="submit" class="dsgo-form__submit wp-element-button" style="min-height:44px;padding-top:0.75rem;padding-bottom:0.75rem;padding-left:2rem;padding-right:2rem">Request Consultation</button></div><div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display:none"></div></form></div>
<!-- /wp:designsetgo/form-builder --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

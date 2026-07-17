<?php
/**
 * Title: Hero with Video Modal
 * Slug: designsetgo/hero/hero-video-modal
 * Categories: dsgo-hero
 * Description: A creative hero section with a play button that opens a video modal
 * Keywords: hero, video, modal, play, creative
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Hero with Video Modal', 'designsetgo' ),
	'categories' => array( 'dsgo-hero' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"dimensions":{"minHeight":"80vh"}},"backgroundColor":"contrast","layout":{"type":"flex","orientation":"vertical","justifyContent":"center","verticalAlignment":"center"},"metadata":{"categories":["dsgo-hero"],"patternName":"designsetgo/hero/hero-video-modal","name":"Hero with Video Modal"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-contrast-background-color has-background" style="min-height:80vh;padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"800","letterSpacing":"-0.02em"}},"textColor":"base","fontSize":"xx-large"} -->
<h1 class="wp-block-heading has-text-align-center has-base-color has-text-color has-xx-large-font-size" style="font-style:normal;font-weight:800;letter-spacing:-0.02em">See It In Action</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"textColor":"base","fontSize":"large"} -->
<p class="has-text-align-center has-base-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--20)">Watch how our platform transforms the way you build websites</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/modal-trigger {"targetModalId":"video-demo-modal","backgroundColor":"base","textColor":"contrast","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}}} -->
<div class="wp-block-designsetgo-modal-trigger dsgo-justify dsgo-justify--left"><button class="dsgo-modal-trigger dsgo-modal-trigger--fill wp-block-button wp-block-button__link wp-element-button has-contrast-color has-base-background-color has-text-color has-background has-link-color" type="button" data-dsgo-modal-trigger="video-demo-modal"><span class="dsgo-modal-trigger__text">Open Modal</span></button></div>
<!-- /wp:designsetgo/modal-trigger -->

<!-- wp:designsetgo/modal {"modalId":"video-demo-modal","width":"900px","overlayOpacity":90} -->
<div id="video-demo-modal" role="dialog" aria-modal="true" aria-label="Modal" aria-hidden="true" data-dsgo-modal="true" data-modal-id="video-demo-modal" data-animation-type="fade" data-animation-duration="300" data-close-on-backdrop="true" data-close-on-esc="true" data-disable-body-scroll="true" data-allow-hash-trigger="true" data-update-url-on-open="false" data-auto-trigger-type="none" data-auto-trigger-delay="0" data-auto-trigger-frequency="always" data-cookie-duration="7" data-exit-intent-sensitivity="medium" data-exit-intent-min-time="5" data-exit-intent-exclude-mobile="true" data-scroll-depth="50" data-scroll-direction="down" data-time-on-page="30" data-gallery-group-id="" data-gallery-index="0" data-show-gallery-navigation="true" data-navigation-style="arrows" data-navigation-position="sides" class="wp-block-designsetgo-modal dsgo-modal"><div class="dsgo-modal__backdrop" style="opacity:0.9" aria-hidden="true"></div><div class="dsgo-modal__dialog"><div class="dsgo-modal__content" style="border-style:none;border-width:0px;width:900px;max-width:90vw"><!-- wp:embed {"url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.youtube.com/watch?v=dQw4w9WgXcQ
</div></figure>
<!-- /wp:embed --><button class="dsgo-modal__close dsgo-modal__close--inside-top-right" style="width:24px;height:24px" type="button" aria-label="Close modal"><svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></button></div></div></div>
<!-- /wp:designsetgo/modal -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"textColor":"base","fontSize":"small"} -->
<p class="has-text-align-center has-base-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--50)">No credit card required • Free 14-day trial</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section -->',
);

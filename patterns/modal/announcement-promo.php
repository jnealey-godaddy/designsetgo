<?php
/**
 * Title: Announcement & Promo Modal
 * Slug: designsetgo/modal/announcement-promo
 * Categories: dsgo-modal
 * Description: Promotional announcement modal that auto-opens on page load
 * Keywords: modal, announcement, promo, popup, offer
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Announcement & Promo Modal', 'designsetgo' ),
	'categories' => array( 'dsgo-modal' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/modal {"modalId":"dsgo-modal-promo","autoTriggerType":"pageLoad","autoTriggerDelay":2,"autoTriggerFrequency":"session","animationType":"zoom","overlayOpacity":70,"metadata":{"categories":["dsgo-modal"],"patternName":"designsetgo/modal/announcement-promo","name":"Announcement \u0026 Promo Modal"}} -->
<div id="dsgo-modal-promo" role="dialog" aria-modal="true" aria-label="Modal" aria-hidden="true" data-dsgo-modal="true" data-modal-id="dsgo-modal-promo" data-animation-type="zoom" data-animation-duration="300" data-close-on-backdrop="true" data-close-on-esc="true" data-disable-body-scroll="true" data-allow-hash-trigger="true" data-update-url-on-open="false" data-auto-trigger-type="pageLoad" data-auto-trigger-delay="2" data-auto-trigger-frequency="session" data-cookie-duration="7" data-exit-intent-sensitivity="medium" data-exit-intent-min-time="5" data-exit-intent-exclude-mobile="true" data-scroll-depth="50" data-scroll-direction="down" data-time-on-page="30" data-gallery-group-id="" data-gallery-index="0" data-show-gallery-navigation="true" data-navigation-style="arrows" data-navigation-position="sides" class="wp-block-designsetgo-modal dsgo-modal"><div class="dsgo-modal__backdrop" style="background-color:#000000;opacity:0.7" aria-hidden="true"></div><div class="dsgo-modal__dialog"><div class="dsgo-modal__content" style="border-style:none;border-width:0px;width:600px;max-width:90vw"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">🎉 Special Offer!</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size">Get 20% off your first purchase. Use code <strong>WELCOME20</strong> at checkout.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/icon-button {"justification":"center","text":"Shop Now","icon":"cart","iconPosition":"end","iconGap":"8px","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--center" style="margin-top:var(--wp--preset--spacing--50)"><button class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button dsgo-icon-button--has-icon dsgo-icon-button--icon-end" style="gap:8px" type="button"><span class="dsgo-icon-button__icon dsgo-lazy-icon" data-icon-name="cart"></span><span class="dsgo-icon-button__text">Shop Now</span></button></div>
<!-- /wp:designsetgo/icon-button --><button class="dsgo-modal__close dsgo-modal__close--inside-top-right" style="width:24px;height:24px" type="button" aria-label="Close modal"><svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></button></div></div></div>
<!-- /wp:designsetgo/modal -->',
);

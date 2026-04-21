<?php
/**
 * Title: Newsletter Signup Modal
 * Slug: designsetgo/modal/newsletter-signup
 * Categories: dsgo-modal
 * Description: Newsletter subscription modal with exit intent trigger and form
 * Keywords: modal, newsletter, signup, subscribe, email
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Newsletter Signup Modal', 'designsetgo' ),
	'categories' => array( 'dsgo-modal' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:group {"metadata":{"categories":["dsgo-modal"],"patternName":"designsetgo/modal/newsletter-signup","name":"Newsletter Signup Modal"},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:designsetgo/icon-button {"text":"Subscribe to Newsletter","url":"#dsgo-modal-newsletter","icon":"envelope","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}}} -->
<a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;width:auto;flex-direction:row;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--60)" href="#dsgo-modal-newsletter" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" style="display:flex;align-items:center;justify-content:center;width:20px;height:20px;flex-shrink:0" data-icon-name="envelope" data-icon-size="20"></span><span class="dsgo-icon-button__text">Subscribe to Newsletter</span></a>
<!-- /wp:designsetgo/icon-button -->

<!-- wp:designsetgo/modal {"modalId":"dsgo-modal-newsletter","autoTriggerType":"exitIntent","autoTriggerFrequency":"once","cookieDuration":30,"width":"500px","animationType":"slide-up"} -->
<div id="dsgo-modal-newsletter" role="dialog" aria-modal="true" aria-label="Modal" aria-hidden="true" data-dsgo-modal="true" data-modal-id="dsgo-modal-newsletter" data-animation-type="slide-up" data-animation-duration="300" data-close-on-backdrop="true" data-close-on-esc="true" data-disable-body-scroll="true" data-allow-hash-trigger="true" data-update-url-on-open="false" data-auto-trigger-type="exitIntent" data-auto-trigger-delay="0" data-auto-trigger-frequency="once" data-cookie-duration="30" data-exit-intent-sensitivity="medium" data-exit-intent-min-time="5" data-exit-intent-exclude-mobile="true" data-scroll-depth="50" data-scroll-direction="down" data-time-on-page="30" data-gallery-group-id="" data-gallery-index="0" data-show-gallery-navigation="true" data-navigation-style="arrows" data-navigation-position="sides" class="wp-block-designsetgo-modal dsgo-modal"><div class="dsgo-modal__backdrop" style="background-color:#000000;opacity:0.8" aria-hidden="true"></div><div class="dsgo-modal__dialog"><div class="dsgo-modal__content" style="border-style:none;border-width:0px;width:500px;max-width:90vw"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Subscribe to Our Newsletter</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Get the latest updates and exclusive content delivered to your inbox.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/form-builder {"formId":"8ec8a00c","submitButtonText":"Subscribe","submitButtonAlignment":"center","successMessage":"Thank you for subscribing!"} -->
<div class="wp-block-designsetgo-form-builder dsgo-form-builder dsgo-form-builder--align-center" style="--dsgo-form-field-spacing:1.5rem;--dsgo-form-input-height:44px;--dsgo-form-input-padding:0.75rem;--dsgo-form-label-color:;--dsgo-form-border-color:#d1d5db;--dsgo-form-field-bg:" data-form-id="8ec8a00c" data-ajax-submit="true" data-success-message="Thank you for subscribing!" data-error-message="There was an error submitting the form. Please try again." data-submit-text="Subscribe"><form class="dsgo-form" method="post" novalidate><div class="dsgo-form__fields"><!-- wp:designsetgo/form-email-field {"fieldName":"email","label":"Email Address","placeholder":"your@email.com","required":true} -->
<div class="wp-block-designsetgo-form-email-field dsgo-form-field dsgo-form-field--email" style="flex-basis:100%;max-width:100%"><label for="field-email" class="dsgo-form-field__label">Email Address<span class="dsgo-form-field__required" aria-label="required">*</span></label><input type="email" id="field-email" name="email" class="dsgo-form-field__input" placeholder="your@email.com" required aria-required="true" data-field-type="email"/></div>
<!-- /wp:designsetgo/form-email-field --></div><input type="text" name="dsg_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"/><input type="hidden" name="dsg_form_id" value="8ec8a00c"/><div class="dsgo-form__footer"><button type="submit" class="dsgo-form__submit wp-element-button" style="min-height:44px;padding-top:0.75rem;padding-bottom:0.75rem;padding-left:2rem;padding-right:2rem">Subscribe</button></div><div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display:none"></div></form></div>
<!-- /wp:designsetgo/form-builder --><button class="dsgo-modal__close dsgo-modal__close--inside-top-right" style="width:24px;height:24px" type="button" aria-label="Close modal"><svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></button></div></div></div>
<!-- /wp:designsetgo/modal --></div>
<!-- /wp:group -->',
);

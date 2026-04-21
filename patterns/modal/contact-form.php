<?php
/**
 * Title: Contact Form Modal
 * Slug: designsetgo/modal/contact-form
 * Categories: dsgo-modal
 * Description: Contact form in a modal with trigger button
 * Keywords: modal, contact, form, popup, email
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Contact Form Modal', 'designsetgo' ),
	'categories' => array( 'dsgo-modal' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:group {"metadata":{"categories":["dsgo-modal"],"patternName":"designsetgo/modal/contact-form","name":"Contact Form Modal"},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:designsetgo/icon-button {"text":"Contact Us","url":"#dsgo-modal-contact","icon":"envelope"} -->
<a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;width:auto;flex-direction:row" href="#dsgo-modal-contact" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" style="display:flex;align-items:center;justify-content:center;width:20px;height:20px;flex-shrink:0" data-icon-name="envelope" data-icon-size="20"></span><span class="dsgo-icon-button__text">Contact Us</span></a>
<!-- /wp:designsetgo/icon-button -->

<!-- wp:designsetgo/modal {"modalId":"dsgo-modal-contact","animationType":"slide-up","overlayOpacity":75} -->
<div id="dsgo-modal-contact" role="dialog" aria-modal="true" aria-label="Modal" aria-hidden="true" data-dsgo-modal="true" data-modal-id="dsgo-modal-contact" data-animation-type="slide-up" data-animation-duration="300" data-close-on-backdrop="true" data-close-on-esc="true" data-disable-body-scroll="true" data-allow-hash-trigger="true" data-update-url-on-open="false" data-auto-trigger-type="none" data-auto-trigger-delay="0" data-auto-trigger-frequency="always" data-cookie-duration="7" data-exit-intent-sensitivity="medium" data-exit-intent-min-time="5" data-exit-intent-exclude-mobile="true" data-scroll-depth="50" data-scroll-direction="down" data-time-on-page="30" data-gallery-group-id="" data-gallery-index="0" data-show-gallery-navigation="true" data-navigation-style="arrows" data-navigation-position="sides" class="wp-block-designsetgo-modal dsgo-modal"><div class="dsgo-modal__backdrop" style="background-color:#000000;opacity:0.75" aria-hidden="true"></div><div class="dsgo-modal__dialog"><div class="dsgo-modal__content" style="border-style:none;border-width:0px;width:600px;max-width:90vw"><!-- wp:heading -->
<h2 class="wp-block-heading">Get in Touch</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Have a question? We\'d love to hear from you.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/form-builder {"formId":"b375c2c9","submitButtonText":"Send Message","successMessage":"Thank you! Your message has been sent."} -->
<div class="wp-block-designsetgo-form-builder dsgo-form-builder dsgo-form-builder--align-left" style="--dsgo-form-field-spacing:1.5rem;--dsgo-form-input-height:44px;--dsgo-form-input-padding:0.75rem;--dsgo-form-label-color:;--dsgo-form-border-color:#d1d5db;--dsgo-form-field-bg:" data-form-id="b375c2c9" data-ajax-submit="true" data-success-message="Thank you! Your message has been sent." data-error-message="There was an error submitting the form. Please try again." data-submit-text="Send Message"><form class="dsgo-form" method="post" novalidate><div class="dsgo-form__fields"><!-- wp:designsetgo/form-text-field {"fieldName":"name","label":"Name","placeholder":"Your name","required":true} -->
<div class="wp-block-designsetgo-form-text-field dsgo-form-field dsgo-form-field--text" style="flex-basis:100%;max-width:100%"><label for="field-name" class="dsgo-form-field__label">Name<span class="dsgo-form-field__required" aria-label="required">*</span></label><input type="text" id="field-name" name="name" class="dsgo-form-field__input" placeholder="Your name" required aria-required="true" data-field-type="text"/></div>
<!-- /wp:designsetgo/form-text-field -->

<!-- wp:designsetgo/form-email-field {"fieldName":"email","placeholder":"your@email.com","required":true} -->
<div class="wp-block-designsetgo-form-email-field dsgo-form-field dsgo-form-field--email" style="flex-basis:100%;max-width:100%"><label for="field-email" class="dsgo-form-field__label">Email<span class="dsgo-form-field__required" aria-label="required">*</span></label><input type="email" id="field-email" name="email" class="dsgo-form-field__input" placeholder="your@email.com" required aria-required="true" data-field-type="email"/></div>
<!-- /wp:designsetgo/form-email-field -->

<!-- wp:designsetgo/form-textarea-field {"fieldName":"message","placeholder":"Your message...","required":true,"className":"wp-block-designsetgo-form-textarea"} -->
<div class="wp-block-designsetgo-form-textarea-field dsgo-form-field dsgo-form-field--textarea wp-block-designsetgo-form-textarea" style="flex-basis:100%;max-width:100%"><label for="field-message" class="dsgo-form-field__label">Message<span class="dsgo-form-field__required" aria-label="required">*</span></label><textarea id="field-message" name="message" class="dsgo-form-field__textarea" placeholder="Your message..." required rows="4" aria-required="true" data-field-type="textarea"></textarea></div>
<!-- /wp:designsetgo/form-textarea-field --></div><input type="text" name="dsg_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"/><input type="hidden" name="dsg_form_id" value="b375c2c9"/><div class="dsgo-form__footer"><button type="submit" class="dsgo-form__submit wp-element-button" style="min-height:44px;padding-top:0.75rem;padding-bottom:0.75rem;padding-left:2rem;padding-right:2rem">Send Message</button></div><div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display:none"></div></form></div>
<!-- /wp:designsetgo/form-builder --><button class="dsgo-modal__close dsgo-modal__close--inside-top-right" style="width:24px;height:24px" type="button" aria-label="Close modal"><svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></button></div></div></div>
<!-- /wp:designsetgo/modal --></div>
<!-- /wp:group -->',
);

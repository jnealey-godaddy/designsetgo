<?php
/**
 * Title: Contact Map Split
 * Slug: designsetgo/contact/contact-map-split
 * Categories: dsgo-contact
 * Description: Split contact section with form on one side and map with contact details on the other
 * Keywords: contact, map, form, split, location
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Contact Map Split', 'designsetgo' ),
	'categories' => array( 'dsgo-contact' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"base-2","metadata":{"categories":["dsgo-contact"],"patternName":"designsetgo/contact/contact-map-split","name":"Contact Map Split"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-2-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#6366f1"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#6366f1;letter-spacing:3px;text-transform:uppercase">Get In Touch</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:700">Contact Us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}},"color":{"text":"#64748b"}},"fontSize":"medium"} -->
<p class="has-text-align-center has-text-color has-medium-font-size" style="color:#64748b;margin-top:var(--wp--preset--spacing--20)">Have a question or want to work together? We would love to hear from you.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"desktopColumns":2,"style":{"spacing":{"blockGap":"var:preset|spacing|40","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--40);column-gap:var(--wp--preset--spacing--40)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"16px"}},"backgroundColor":"base","dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInLeft"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-background-color has-background has-dsgo-animation dsgo-animation-fadeInLeft" style="border-radius:16px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInLeft"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}},"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size" style="margin-bottom:var(--wp--preset--spacing--30)">Send Us a Message</h3>
<!-- /wp:heading -->

<!-- wp:designsetgo/form-builder {"formId":"contact-map-form","submitButtonText":"Send Message","className":"dsgo-form"} -->
<div class="wp-block-designsetgo-form-builder dsgo-form-builder dsgo-form-builder--align-left dsgo-form" style="--dsgo-form-field-spacing:1.5rem;--dsgo-form-input-height:44px;--dsgo-form-input-padding:0.75rem;--dsgo-form-label-color:;--dsgo-form-border-color:#d1d5db;--dsgo-form-field-bg:" data-form-id="contact-map-form" data-ajax-submit="true" data-success-message="Thank you! Your form has been submitted successfully." data-error-message="There was an error submitting the form. Please try again." data-submit-text="Send Message"><form class="dsgo-form" method="post" novalidate><div class="dsgo-form__fields"><!-- wp:designsetgo/form-text-field {"fieldName":"contact_name","label":"Your Name","placeholder":"John Doe","required":true} -->
<div class="wp-block-designsetgo-form-text-field dsgo-form-field dsgo-form-field--text" style="flex-basis:100%;max-width:100%"><label for="field-contact_name" class="dsgo-form-field__label">Your Name<span class="dsgo-form-field__required" aria-label="required">*</span></label><input type="text" id="field-contact_name" name="contact_name" class="dsgo-form-field__input" placeholder="John Doe" required aria-required="true" data-field-type="text"/></div>
<!-- /wp:designsetgo/form-text-field -->

<!-- wp:designsetgo/form-email-field {"fieldName":"contact_email","label":"Email Address","placeholder":"john@example.com","required":true} -->
<div class="wp-block-designsetgo-form-email-field dsgo-form-field dsgo-form-field--email" style="flex-basis:100%;max-width:100%"><label for="field-contact_email" class="dsgo-form-field__label">Email Address<span class="dsgo-form-field__required" aria-label="required">*</span></label><input type="email" id="field-contact_email" name="contact_email" class="dsgo-form-field__input" placeholder="john@example.com" required aria-required="true" data-field-type="email"/></div>
<!-- /wp:designsetgo/form-email-field -->

<!-- wp:designsetgo/form-select-field {"fieldName":"contact_subject","label":"Subject","options":[{"value":"general","label":"General Inquiry"},{"value":"support","label":"Technical Support"},{"value":"sales","label":"Sales Question"},{"value":"partnership","label":"Partnership Opportunity"}]} -->
<div class="wp-block-designsetgo-form-select-field dsgo-form-field dsgo-form-field--select" style="flex-basis:100%;max-width:100%"><label for="field-contact_subject" class="dsgo-form-field__label">Subject</label><select id="field-contact_subject" name="contact_subject" class="dsgo-form-field__select" data-field-type="select"><option value="">-- Select an option --</option><option value="general">General Inquiry</option><option value="support">Technical Support</option><option value="sales">Sales Question</option><option value="partnership">Partnership Opportunity</option></select></div>
<!-- /wp:designsetgo/form-select-field -->

<!-- wp:designsetgo/form-textarea-field {"fieldName":"field_c450fe5d","placeholder":"Enter your message","className":"wp-block-designsetgo-form-textarea"} -->
<div class="wp-block-designsetgo-form-textarea-field dsgo-form-field dsgo-form-field--textarea wp-block-designsetgo-form-textarea" style="flex-basis:100%;max-width:100%"><label for="field-field_c450fe5d" class="dsgo-form-field__label">Message</label><textarea id="field-field_c450fe5d" name="field_c450fe5d" class="dsgo-form-field__textarea" placeholder="Enter your message" rows="4" data-field-type="textarea"></textarea></div>
<!-- /wp:designsetgo/form-textarea-field --></div><input type="text" name="dsg_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"/><input type="hidden" name="dsg_form_id" value="contact-map-form"/><div class="dsgo-form__footer"><button type="submit" class="dsgo-form__submit wp-element-button" style="min-height:44px;padding-top:0.75rem;padding-bottom:0.75rem;padding-left:2rem;padding-right:2rem">Send Message</button></div><div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display:none"></div></form></div>
<!-- /wp:designsetgo/form-builder --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInRight"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeInRight" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInRight"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/map {"style":{"border":{"radius":"16px 16px 0 0"}}} -->
<div class="wp-block-designsetgo-map dsgo-map" style="border-radius:16px 16px 0 0;height:400px" data-dsgo-provider="openstreetmap" data-dsgo-lat="40.7128" data-dsgo-lng="-74.006" data-dsgo-zoom="13" data-dsgo-address="" data-dsgo-marker-icon="📍" data-dsgo-marker-color="#e74c3c" data-dsgo-privacy-mode="false" data-dsgo-map-style="standard"><div class="dsgo-map__container" role="region" aria-label="Interactive map"></div></div>
<!-- /wp:designsetgo/map -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"blockGap":"0"},"border":{"radius":"0 0 16px 16px"}},"backgroundColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-background-color has-background" style="border-radius:0 0 16px 16px;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"bottom":"var:preset|spacing|20"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/icon {"icon":"location","iconSize":20,"style":{"color":{"text":"#6366f1"}}} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color" style="color:#6366f1;display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="location" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Location"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">123 Business Avenue, New York, NY 10001</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"bottom":"var:preset|spacing|20"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/icon {"icon":"phone","iconSize":20,"style":{"color":{"text":"#6366f1"}}} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color" style="color:#6366f1;display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="phone" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Phone"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">+1 (555) 123-4567</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"bottom":"var:preset|spacing|20"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/icon {"icon":"envelope","iconSize":20,"style":{"color":{"text":"#6366f1"}}} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color" style="color:#6366f1;display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="envelope" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Envelope"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">hello@company.com</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:nowrap;gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/icon {"icon":"clock","iconSize":20,"style":{"color":{"text":"#6366f1"}}} -->
<div class="wp-block-designsetgo-icon dsgo-icon has-text-color" style="color:#6366f1;display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="clock" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Clock"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Mon - Fri: 9:00 AM - 6:00 PM</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

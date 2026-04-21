<?php
/**
 * Title: Contact Form with Map
 * Slug: designsetgo/contact/contact-form
 * Categories: dsgo-contact
 * Description: A professional contact form with map display
 * Keywords: contact, form, map, professional
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Contact Form with Map', 'designsetgo' ),
	'categories' => array( 'dsgo-contact' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"base-2","metadata":{"categories":["dsgo-contact"],"patternName":"designsetgo/contact/contact-form","name":"Contact Form with Map"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-2-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--50);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"textAlign":"center","fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size">Get In Touch</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size">We would love to hear from you. Send us a message and we will respond as soon as possible.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"desktopColumns":2,"tabletColumns":1,"style":{"spacing":{"blockGap":"var:preset|spacing|60","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-1 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--60);column-gap:var(--wp--preset--spacing--60)"><!-- wp:designsetgo/section {"constrainWidth":false,"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"16px"}},"backgroundColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-no-width-constraint has-base-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><div class="dsgo-stack__inner"><!-- wp:designsetgo/form-builder {"formId":"90836d04","successMessage":"Thank you! Your message has been sent.","className":"dsgo-form","style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-designsetgo-form-builder dsgo-form-builder dsgo-form-builder--align-left dsgo-form" style="--dsgo-form-field-spacing:1.5rem;--dsgo-form-input-height:44px;--dsgo-form-input-padding:0.75rem;--dsgo-form-label-color:;--dsgo-form-border-color:#d1d5db;--dsgo-form-field-bg:" data-form-id="90836d04" data-ajax-submit="true" data-success-message="Thank you! Your message has been sent." data-error-message="There was an error submitting the form. Please try again." data-submit-text="Submit"><form class="dsgo-form" method="post" novalidate><div class="dsgo-form__fields"><!-- wp:designsetgo/form-text-field {"fieldName":"field_918c2e0e","label":"Your Name","placeholder":"John Doe","required":true} -->
<div class="wp-block-designsetgo-form-text-field dsgo-form-field dsgo-form-field--text" style="flex-basis:100%;max-width:100%"><label for="field-field_918c2e0e" class="dsgo-form-field__label">Your Name<span class="dsgo-form-field__required" aria-label="required">*</span></label><input type="text" id="field-field_918c2e0e" name="field_918c2e0e" class="dsgo-form-field__input" placeholder="John Doe" required aria-required="true" data-field-type="text"/></div>
<!-- /wp:designsetgo/form-text-field -->

<!-- wp:designsetgo/form-email-field {"fieldName":"field_374f24b5","label":"Email Address","placeholder":"john@example.com","required":true} -->
<div class="wp-block-designsetgo-form-email-field dsgo-form-field dsgo-form-field--email" style="flex-basis:100%;max-width:100%"><label for="field-field_374f24b5" class="dsgo-form-field__label">Email Address<span class="dsgo-form-field__required" aria-label="required">*</span></label><input type="email" id="field-field_374f24b5" name="field_374f24b5" class="dsgo-form-field__input" placeholder="john@example.com" required aria-required="true" data-field-type="email"/></div>
<!-- /wp:designsetgo/form-email-field -->

<!-- wp:designsetgo/form-select-field {"fieldName":"select-ac75524a","label":"Subject","required":true,"options":[{"value":"general","label":"General Inquiry"},{"value":"sales","label":"Sales Question"},{"value":"support","label":"Technical Support"},{"value":"partnership","label":"Partnership"},{"value":"other","label":"Other"}]} -->
<div class="wp-block-designsetgo-form-select-field dsgo-form-field dsgo-form-field--select" style="flex-basis:100%;max-width:100%"><label for="field-select-ac75524a" class="dsgo-form-field__label">Subject<span class="dsgo-form-field__required" aria-label="required">*</span></label><select id="field-select-ac75524a" name="select-ac75524a" class="dsgo-form-field__select" required aria-required="true" data-field-type="select"><option value="">-- Select an option --</option><option value="general">General Inquiry</option><option value="sales">Sales Question</option><option value="support">Technical Support</option><option value="partnership">Partnership</option><option value="other">Other</option></select></div>
<!-- /wp:designsetgo/form-select-field -->

<!-- wp:designsetgo/form-textarea-field {"fieldName":"field_3773006e","placeholder":"How can we help you?","required":true,"rows":5,"className":"wp-block-designsetgo-form-textarea"} -->
<div class="wp-block-designsetgo-form-textarea-field dsgo-form-field dsgo-form-field--textarea wp-block-designsetgo-form-textarea" style="flex-basis:100%;max-width:100%"><label for="field-field_3773006e" class="dsgo-form-field__label">Message<span class="dsgo-form-field__required" aria-label="required">*</span></label><textarea id="field-field_3773006e" name="field_3773006e" class="dsgo-form-field__textarea" placeholder="How can we help you?" required rows="5" aria-required="true" data-field-type="textarea"></textarea></div>
<!-- /wp:designsetgo/form-textarea-field --></div><input type="text" name="dsg_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"/><input type="hidden" name="dsg_form_id" value="90836d04"/><div class="dsgo-form__footer"><button type="submit" class="dsgo-form__submit wp-element-button" style="min-height:44px;padding-top:0.75rem;padding-bottom:0.75rem;padding-left:2rem;padding-right:2rem">Submit</button></div><div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display:none"></div></form></div>
<!-- /wp:designsetgo/form-builder --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"constrainWidth":false,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner"><!-- wp:designsetgo/map {"style":{"border":{"radius":"16px"}}} -->
<div class="wp-block-designsetgo-map dsgo-map" style="border-radius:16px;height:400px" data-dsgo-provider="openstreetmap" data-dsgo-lat="40.7128" data-dsgo-lng="-74.006" data-dsgo-zoom="13" data-dsgo-address="" data-dsgo-marker-icon="📍" data-dsgo-marker-color="#e74c3c" data-dsgo-privacy-mode="false" data-dsgo-map-style="standard"><div class="dsgo-map__container" role="region" aria-label="Interactive map"></div></div>
<!-- /wp:designsetgo/map -->

<!-- wp:designsetgo/section {"constrainWidth":false,"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--40);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="font-style:normal;font-weight:600">Our Office</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>123 Business Street<br>New York, NY 10001<br>United States</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}}} -->
<p style="margin-top:var(--wp--preset--spacing--30)"><strong>Phone:</strong> +1 (555) 123-4567<br><strong>Email:</strong> hello@example.com</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

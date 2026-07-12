<?php
/**
 * Title: Footer Dark
 * Slug: designsetgo/footer/footer-dark
 * Categories: dsgo-footer
 * Description: A bold dark footer with site logo, navigation, CTA button, and copyright
 * Keywords: footer, dark, bold, modern, agency
 * Block Types: core/template-part/footer
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Footer Dark', 'designsetgo' ),
	'categories' => array( 'dsgo-footer' ),
	'blockTypes' => array( 'core/template-part/footer' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"tagName":"footer","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"blockGap":"var:preset|spacing|50"},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"backgroundColor":"contrast","textColor":"base","metadata":{"categories":["dsgo-footer"],"patternName":"designsetgo/footer/footer-dark","name":"Footer Dark"}} -->
<footer class="wp-block-designsetgo-section alignfull dsgo-stack has-base-color has-contrast-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/row {"style":{"spacing":{"padding":{"top":"0","bottom":"0"}}},"layout":{"type":"flex","justifyContent":"space-between","verticalAlignment":"center","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-bottom:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:nowrap"><!-- wp:site-logo /-->

<!-- wp:designsetgo/row {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dspacing\u002d\u002d30)"}},"layout":{"type":"flex","justifyContent":"right","verticalAlignment":"center","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:right;align-items:center;flex-wrap:nowrap;gap:var(--wp--preset--spacing--30)"><!-- wp:navigation {"overlayBackgroundColor":"base","overlayTextColor":"contrast","layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} /-->

<!-- wp:designsetgo/icon-button {"text":"Contact Us","url":"#","icon":"arrow-right","iconPosition":"end","iconGap":"8px","hoverBackgroundColor":"var:preset|color|accent-3","hoverTextColor":"var:preset|color|base","backgroundColor":"base","textColor":"contrast","fontSize":"small","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--left"><a class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-contrast-color has-base-background-color has-text-color has-background has-small-font-size dsgo-icon-button--has-icon dsgo-icon-button--icon-end" style="gap:8px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--30);--dsgo-button-hover-bg:var(--wp--preset--color--accent-3);--dsgo-button-hover-color:var(--wp--preset--color--base)" href="#" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" data-icon-name="arrow-right"></span><span class="dsgo-icon-button__text">Contact Us</span></a></div>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:designsetgo/row {"style":{"spacing":{"padding":{"top":"0","bottom":"0"}}},"layout":{"type":"flex","justifyContent":"space-between","verticalAlignment":"center","flexWrap":"nowrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-bottom:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:nowrap"><!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">© 2025 Your Company. All rights reserved.</p>
<!-- /wp:paragraph -->

<!-- wp:social-links {"openInNewTab":true,"size":"has-normal-icon-size","className":"is-style-logos-only","layout":{"type":"flex","justifyContent":"right"}} -->
<ul class="wp-block-social-links has-normal-icon-size is-style-logos-only"><!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"x"} /-->

<!-- wp:social-link {"url":"#","service":"instagram"} /-->

<!-- wp:social-link {"url":"#","service":"linkedin"} /--></ul>
<!-- /wp:social-links --></div></div>
<!-- /wp:designsetgo/row --></div></footer>
<!-- /wp:designsetgo/section -->',
);

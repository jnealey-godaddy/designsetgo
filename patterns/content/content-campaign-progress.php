<?php
/**
 * Title: Campaign Progress Section
 * Slug: designsetgo/content/content-campaign-progress
 * Categories: dsgo-content
 * Description: A campaign fundraising section with progress bars, goal tracking, and donation amounts
 * Keywords: campaign, progress, fundraising, donation, goals, nonprofit
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Campaign Progress Section', 'designsetgo' ),
	'categories' => array( 'dsgo-content' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"contrast","textColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-color has-contrast-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#10b981"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:#10b981;letter-spacing:3px;text-transform:uppercase">Current Campaign</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:700">Help Us Reach Our Goal</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size" style="margin-top:var(--wp--preset--spacing--20)">We are building 10 new schools in rural communities. Every donation brings us closer to making education accessible to 5,000 more children.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/progress-bar -->
<div class="wp-block-designsetgo-progress-bar dsgo-progress-bar dsgo-progress-bar--animate" data-percentage="75" data-duration="1.5"><div class="dsgo-progress-bar__label dsgo-progress-bar__label--top">75%</div><div class="dsgo-progress-bar__container" style="width:100%;height:20px;background-color:#e5e7eb;border-radius:4px;overflow:hidden;position:relative"><div class="dsgo-progress-bar__fill " style="width:0%;height:100%;background-color:#2563eb;transition:width 1.5s ease-out;border-radius:4px"></div></div></div>
<!-- /wp:designsetgo/progress-bar -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--40);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:wrap;gap:var(--wp--preset--spacing--20)"><!-- wp:designsetgo/icon-button {"text":"Donate $25","url":"#donate-25","icon":"","iconPosition":"none","iconGap":"8px","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"50px","width":"2px","color":"#ffffff"},"color":{"background":"transparent","text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--left has-text-color has-background"><a class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-border-color has-text-color has-background" style="border-color:#ffffff;border-width:2px;border-radius:50px;color:#ffffff;background-color:transparent;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#donate-25" target="_self"><span class="dsgo-icon-button__text">Donate $25</span></a></div>
<!-- /wp:designsetgo/icon-button -->

<!-- wp:designsetgo/icon-button {"text":"Donate $50","url":"#donate-50","icon":"","iconPosition":"none","iconGap":"8px","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"50px","width":"2px","color":"#ffffff"},"color":{"background":"transparent","text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--left has-text-color has-background"><a class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-border-color has-text-color has-background" style="border-color:#ffffff;border-width:2px;border-radius:50px;color:#ffffff;background-color:transparent;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#donate-50" target="_self"><span class="dsgo-icon-button__text">Donate $50</span></a></div>
<!-- /wp:designsetgo/icon-button -->

<!-- wp:designsetgo/icon-button {"text":"Donate $100","url":"#donate-100","icon":"","iconPosition":"none","iconGap":"8px","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"50px"},"color":{"background":"#10b981","text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--left has-text-color has-background"><a class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-text-color has-background" style="border-radius:50px;color:#ffffff;background-color:#10b981;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#donate-100" target="_self"><span class="dsgo-icon-button__text">Donate $100</span></a></div>
<!-- /wp:designsetgo/icon-button -->

<!-- wp:designsetgo/icon-button {"text":"Custom Amount","url":"#donate-custom","icon":"","iconPosition":"none","iconGap":"8px","className":"has-text-color has-background","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"50px","width":"2px","color":"#ffffff"},"color":{"background":"transparent","text":"#ffffff"}}} -->
<div class="wp-block-designsetgo-icon-button dsgo-justify dsgo-justify--left has-text-color has-background"><a class="dsgo-icon-button wp-block-button wp-block-button__link wp-element-button has-border-color has-text-color has-background" style="border-color:#ffffff;border-width:2px;border-radius:50px;color:#ffffff;background-color:transparent;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#donate-custom" target="_self"><span class="dsgo-icon-button__text">Custom Amount</span></a></div>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section -->',
);

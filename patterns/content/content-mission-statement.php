<?php
/**
 * Title: Mission Statement Section
 * Slug: designsetgo/content/content-mission-statement
 * Categories: dsgo-content
 * Description: A two-column mission statement section with heading, description, icon list, and image
 * Keywords: mission, statement, about, nonprofit, charity, purpose
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Mission Statement Section', 'designsetgo' ),
	'categories' => array( 'dsgo-content' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"metadata":{"categories":["dsgo-content"],"patternName":"designsetgo/content/content-mission-statement","name":"Mission Statement Section"},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInUp","dsgoAnimationDuration":700} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-animation dsgo-animation-fadeInUp" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInUp" data-dsgo-animation-duration="700"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/grid {"desktopColumns":2,"style":{"spacing":{"blockGap":"var:preset|spacing|70","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"alignItems":"center"} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:center;row-gap:var(--wp--preset--spacing--70);column-gap:var(--wp--preset--spacing--70)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#10b981"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#10b981;letter-spacing:3px;text-transform:uppercase">Our Mission</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:700">Empowering Communities, One Life at a Time</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="margin-top:var(--wp--preset--spacing--20)">We believe everyone deserves access to clean water, education, healthcare, and economic opportunity. Through sustainable programs and community partnerships, we work to break the cycle of poverty.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}}} -->
<p style="margin-top:var(--wp--preset--spacing--20)">Our approach focuses on long-term solutions that empower communities to thrive independently. We invest in local leaders, provide training and resources, and measure our impact to ensure every dollar makes a difference.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/icon-button {"align":"left","text":"Learn About Our Work","url":"#about","icon":"arrow-right","iconPosition":"end","backgroundColor":"contrast","textColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"margin":{"top":"var:preset|spacing|30"}},"border":{"radius":"50px"}}} -->
<a class="wp-block-designsetgo-icon-button alignleft dsgo-icon-button wp-block-button wp-block-button__link wp-element-button" style="border-radius:50px;margin-top:var(--wp--preset--spacing--30);display:inline-flex;align-items:center;justify-content:center;gap:8px;width:auto;flex-direction:row-reverse;background-color:var(--wp--preset--color--contrast);color:var(--wp--preset--color--base);padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)" href="#about" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" style="display:flex;align-items:center;justify-content:center;width:20px;height:20px;flex-shrink:0" data-icon-name="arrow-right" data-icon-size="20"></span><span class="dsgo-icon-button__text">Learn About Our Work</span></a>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":"16px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Children receiving education" style="border-radius:16px"/></figure>
<!-- /wp:image --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

<?php
/**
 * Title: Course Cards with Ratings
 * Slug: designsetgo/content/content-course-cards
 * Categories: dsgo-content
 * Description: A three-column course card layout with images, ratings, titles, instructor info, and pricing
 * Keywords: courses, cards, ratings, education, instructor, pricing
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Course Cards with Ratings', 'designsetgo' ),
	'categories' => array( 'dsgo-content' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/row {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:space-between;flex-wrap:wrap"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"},"color":{"text":"#3b82f6"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#3b82f6;letter-spacing:3px;text-transform:uppercase">Featured Courses</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:700">Top Rated This Month</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/icon-button {"text":"View All Courses","url":"#courses","icon":"arrow-right","iconPosition":"end","backgroundColor":"transparent","textColor":"contrast","style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}},"border":{"radius":"8px"}}} -->
<a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button" style="border-radius:8px;display:inline-flex;align-items:center;justify-content:center;gap:8px;width:auto;flex-direction:row-reverse;background-color:var(--wp--preset--color--transparent);color:var(--wp--preset--color--contrast);padding-top:var(--wp--preset--spacing--10);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--10);padding-left:var(--wp--preset--spacing--20)" href="#courses" target="_self"><span class="dsgo-icon-button__icon dsgo-lazy-icon" style="display:flex;align-items:center;justify-content:center;width:20px;height:20px;flex-shrink:0" data-icon-name="arrow-right" data-icon-size="20"></span><span class="dsgo-icon-button__text">View All Courses</span></a>
<!-- /wp:designsetgo/icon-button --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-3 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(3, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--30);column-gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"var:preset|spacing|30","left":"0","right":"0"}},"border":{"radius":"12px","width":"1px"}},"borderColor":"contrast-3"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-border-color has-contrast-3-border-color" style="border-width:1px;border-radius:12px;padding-top:0;padding-right:0;padding-bottom:var(--wp--preset--spacing--30);padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":{"topLeft":"12px","topRight":"12px"}}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Web development course" style="border-top-left-radius:12px;border-top-right-radius:12px"/></figure>
<!-- /wp:image -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"0","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:0;padding-left:var(--wp--preset--spacing--20)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:wrap;gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/pill {"content":"Bestseller","style":{"spacing":{"padding":{"top":"4px","bottom":"4px","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}},"border":{"radius":"4px"},"color":{"background":"#fef3c7","text":"#92400e"}}} -->
<div class="wp-block-designsetgo-pill aligncenter dsgo-pill has-text-color has-background has-small-font-size" style="padding-top:4px;padding-right:var(--wp--preset--spacing--10);padding-bottom:4px;padding-left:var(--wp--preset--spacing--10)"><span class="dsgo-pill__content" style="background-color:#fef3c7;color:#92400e;border-radius:4px">Bestseller</span></div>
<!-- /wp:designsetgo/pill -->

<!-- wp:paragraph {"style":{"color":{"text":"#fbbf24"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#fbbf24">★★★★★ 4.9</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|15"}}},"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size" style="margin-top:var(--wp--preset--spacing--15)">Complete Web Development Bootcamp</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}},"color":{"text":"#6b7280"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#6b7280;margin-top:var(--wp--preset--spacing--10)">Dr. Angela Yu • 65 hours</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"},"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:wrap;gap:var(--wp--preset--spacing--30)"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"color":{"text":"#3b82f6"}},"fontSize":"large"} -->
<p class="has-text-color has-large-font-size" style="color:#3b82f6;font-style:normal;font-weight:700">$89.99</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"textDecoration":"line-through"},"color":{"text":"#9ca3af"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#9ca3af;text-decoration:line-through">$199.99</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"var:preset|spacing|30","left":"0","right":"0"}},"border":{"radius":"12px","width":"1px"}},"borderColor":"contrast-3"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-border-color has-contrast-3-border-color" style="border-width:1px;border-radius:12px;padding-top:0;padding-right:0;padding-bottom:var(--wp--preset--spacing--30);padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":{"topLeft":"12px","topRight":"12px"}}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Data science course" style="border-top-left-radius:12px;border-top-right-radius:12px"/></figure>
<!-- /wp:image -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"0","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:0;padding-left:var(--wp--preset--spacing--20)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"right":"0","left":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-right:0;margin-left:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:wrap;gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/pill {"content":"Hot","style":{"spacing":{"padding":{"top":"4px","bottom":"4px","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}},"border":{"radius":"4px"},"color":{"background":"#fee2e2","text":"#dc2626"}}} -->
<div class="wp-block-designsetgo-pill aligncenter dsgo-pill has-text-color has-background has-small-font-size" style="padding-top:4px;padding-right:var(--wp--preset--spacing--10);padding-bottom:4px;padding-left:var(--wp--preset--spacing--10)"><span class="dsgo-pill__content" style="background-color:#fee2e2;color:#dc2626;border-radius:4px">Hot</span></div>
<!-- /wp:designsetgo/pill -->

<!-- wp:paragraph {"style":{"color":{"text":"#fbbf24"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#fbbf24">★★★★★ 4.8</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|15"}}},"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size" style="margin-top:var(--wp--preset--spacing--15)">Python for Data Science &amp; Machine Learning</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}},"color":{"text":"#6b7280"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#6b7280;margin-top:var(--wp--preset--spacing--10)">Jose Portilla • 45 hours</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20","left":"0"},"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--20);margin-left:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:wrap;gap:var(--wp--preset--spacing--30)"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"color":{"text":"#3b82f6"}},"fontSize":"large"} -->
<p class="has-text-color has-large-font-size" style="color:#3b82f6;font-style:normal;font-weight:700">$94.99</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"textDecoration":"line-through"},"color":{"text":"#9ca3af"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#9ca3af;text-decoration:line-through">$179.99</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"var:preset|spacing|30","left":"0","right":"0"}},"border":{"radius":"12px","width":"1px"}},"borderColor":"contrast-3"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-border-color has-contrast-3-border-color" style="border-width:1px;border-radius:12px;padding-top:0;padding-right:0;padding-bottom:var(--wp--preset--spacing--30);padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":{"topLeft":"12px","topRight":"12px"}}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="UI/UX design course" style="border-top-left-radius:12px;border-top-right-radius:12px"/></figure>
<!-- /wp:image -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"0","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:0;padding-left:var(--wp--preset--spacing--20)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/row {"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:wrap;gap:var(--wp--preset--spacing--30)"><!-- wp:designsetgo/pill {"content":"New","style":{"spacing":{"padding":{"top":"4px","bottom":"4px","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}},"border":{"radius":"4px"},"color":{"background":"#dbeafe","text":"#1d4ed8"}}} -->
<div class="wp-block-designsetgo-pill aligncenter dsgo-pill has-text-color has-background has-small-font-size" style="padding-top:4px;padding-right:var(--wp--preset--spacing--10);padding-bottom:4px;padding-left:var(--wp--preset--spacing--10)"><span class="dsgo-pill__content" style="background-color:#dbeafe;color:#1d4ed8;border-radius:4px">New</span></div>
<!-- /wp:designsetgo/pill -->

<!-- wp:paragraph {"style":{"color":{"text":"#fbbf24"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#fbbf24">★★★★★ 4.9</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|15"}}},"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size" style="margin-top:var(--wp--preset--spacing--15)">UI/UX Design Masterclass</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}},"color":{"text":"#6b7280"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#6b7280;margin-top:var(--wp--preset--spacing--10)">Sarah Chen • 38 hours</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/row {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"},"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","justifyContent":"left","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="margin-top:var(--wp--preset--spacing--20);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:left;flex-wrap:wrap;gap:var(--wp--preset--spacing--30)"><!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"color":{"text":"#3b82f6"}},"fontSize":"large"} -->
<p class="has-text-color has-large-font-size" style="color:#3b82f6;font-style:normal;font-weight:700">$79.99</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"textDecoration":"line-through"},"color":{"text":"#9ca3af"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#9ca3af;text-decoration:line-through">$149.99</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

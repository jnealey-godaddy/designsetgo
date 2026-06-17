<?php
/**
 * Title: Leadership Team Grid
 * Slug: designsetgo/team/team-leadership
 * Categories: dsgo-team
 * Description: A three-column leadership team grid with professional photos, names, titles, and credentials
 * Keywords: team, leadership, partners, executives, professional, grid
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Leadership Team Grid', 'designsetgo' ),
	'categories' => array( 'dsgo-team' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="font-style:normal;font-weight:700">Our Leadership Team</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size" style="margin-top:var(--wp--preset--spacing--20)">Experienced professionals dedicated to your success.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"style":{"spacing":{"blockGap":"var:preset|spacing|40","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-3 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(3, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--40);column-gap:var(--wp--preset--spacing--40)"><!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"medium","style":{"border":{"radius":"8px"}}} -->
<figure class="wp-block-image size-medium has-custom-border"><img src="{{dsgo:placeholder-avatar}}" alt="Robert Mitchell" style="border-radius:8px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size" style="margin-top:var(--wp--preset--spacing--20)">Robert Mitchell</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"#2563eb"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#2563eb">Managing Partner</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--10)">Harvard Law, 25+ years in corporate law and complex litigation.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"medium","style":{"border":{"radius":"8px"}}} -->
<figure class="wp-block-image size-medium has-custom-border"><img src="{{dsgo:placeholder-avatar}}" alt="Sarah Williams" style="border-radius:8px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size" style="margin-top:var(--wp--preset--spacing--20)">Sarah Williams</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"#2563eb"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#2563eb">Senior Partner, Real Estate</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--10)">Yale Law, specializing in commercial real estate and development.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:image {"sizeSlug":"medium","style":{"border":{"radius":"8px"}}} -->
<figure class="wp-block-image size-medium has-custom-border"><img src="{{dsgo:placeholder-avatar}}" alt="Michael Chen" style="border-radius:8px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size" style="margin-top:var(--wp--preset--spacing--20)">Michael Chen</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"#2563eb"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#2563eb">Senior Partner, Tax Advisory</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}},"fontSize":"small"} -->
<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--10)">Stanford Law, CPA with expertise in tax strategy and IRS matters.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

<?php
/**
 * Title: Team Grid Dark
 * Slug: designsetgo/team/team-dark-minimal
 * Categories: dsgo-team
 * Description: A dark-themed four-column team member grid with photos, names, and roles on a contrast background
 * Keywords: team, dark, grid, members, minimal, contrast
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Team Grid Dark', 'designsetgo' ),
	'categories' => array( 'dsgo-team' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"contrast","textColor":"base"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-color has-contrast-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--60);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px"}},"fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size" style="letter-spacing:3px;text-transform:uppercase">The Team</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--20);font-style:normal;font-weight:700">Meet the Creatives</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"desktopColumns":4,"style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-4 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(4, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--30);column-gap:var(--wp--preset--spacing--30)"><!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:image {"sizeSlug":"medium","style":{"border":{"radius":"0"}}} -->
<figure class="wp-block-image size-medium has-custom-border"><img src="{{dsgo:placeholder-avatar}}" alt="Alex Rivera" style="border-radius:0"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size" style="margin-top:var(--wp--preset--spacing--20)">Alex Rivera</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Creative Director</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:image {"sizeSlug":"medium","style":{"border":{"radius":"0"}}} -->
<figure class="wp-block-image size-medium has-custom-border"><img src="{{dsgo:placeholder-avatar}}" alt="Jordan Kim" style="border-radius:0"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size" style="margin-top:var(--wp--preset--spacing--20)">Jordan Kim</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Lead Designer</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:image {"sizeSlug":"medium","style":{"border":{"radius":"0"}}} -->
<figure class="wp-block-image size-medium has-custom-border"><img src="{{dsgo:placeholder-avatar}}" alt="Marcus Chen" style="border-radius:0"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size" style="margin-top:var(--wp--preset--spacing--20)">Marcus Chen</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Strategy Director</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:image {"sizeSlug":"medium","style":{"border":{"radius":"0"}}} -->
<figure class="wp-block-image size-medium has-custom-border"><img src="{{dsgo:placeholder-avatar}}" alt="Sophie Taylor" style="border-radius:0"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size" style="margin-top:var(--wp--preset--spacing--20)">Sophie Taylor</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Marketing Lead</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

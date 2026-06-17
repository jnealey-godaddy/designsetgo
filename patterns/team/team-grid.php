<?php
/**
 * Title: Team Grid
 * Slug: designsetgo/team/team-grid
 * Categories: dsgo-team
 * Description: A clean minimal team member grid with photos and bios
 * Keywords: team, grid, members, staff, minimal
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Team Grid', 'designsetgo' ),
	'categories' => array( 'dsgo-team' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"metadata":{"categories":["dsgo-team"],"patternName":"designsetgo/team/team-grid","name":"Team Grid"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--50);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"textAlign":"center","fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size">Meet Our Team</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size">The talented people behind our success</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"desktopColumns":4,"style":{"spacing":{"blockGap":"var:preset|spacing|40","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-4 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(4, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--40);column-gap:var(--wp--preset--spacing--40)"><!-- wp:designsetgo/card {"imageUrl":"{{dsgo:placeholder-avatar}}","imageAspectRatio":"1-1","title":"Alex Morgan","subtitle":"CEO \u0026 Founder","bodyText":"Visionary leader with 15+ years in tech startups.","visualStyle":"minimal","showBadge":false,"showCta":false,"style":{"border":{"radius":"12px"}}} -->
<div class="wp-block-designsetgo-card dsgo-card dsgo-card--standard dsgo-card--style-minimal" style="border-radius:12px"><div class="dsgo-card__inner"><div class="dsgo-card__image-wrapper"><img src="{{dsgo:placeholder-avatar}}" alt="Card image" class="dsgo-card__image" style="aspect-ratio:1 / 1;object-fit:cover;object-position:50% 50%" loading="lazy" aria-hidden="true"/></div><div class="dsgo-card__content "><h3 class="dsgo-card__title">Alex Morgan</h3><p class="dsgo-card__subtitle">CEO & Founder</p><p class="dsgo-card__body">Visionary leader with 15+ years in tech startups.</p></div></div></div>
<!-- /wp:designsetgo/card -->

<!-- wp:designsetgo/card {"imageUrl":"{{dsgo:placeholder-avatar}}","imageAspectRatio":"1-1","title":"Jordan Lee","subtitle":"CTO","bodyText":"Full-stack architect passionate about performance.","visualStyle":"minimal","showBadge":false,"showCta":false,"style":{"border":{"radius":"12px"}}} -->
<div class="wp-block-designsetgo-card dsgo-card dsgo-card--standard dsgo-card--style-minimal" style="border-radius:12px"><div class="dsgo-card__inner"><div class="dsgo-card__image-wrapper"><img src="{{dsgo:placeholder-avatar}}" alt="Card image" class="dsgo-card__image" style="aspect-ratio:1 / 1;object-fit:cover;object-position:50% 50%" loading="lazy" aria-hidden="true"/></div><div class="dsgo-card__content "><h3 class="dsgo-card__title">Jordan Lee</h3><p class="dsgo-card__subtitle">CTO</p><p class="dsgo-card__body">Full-stack architect passionate about performance.</p></div></div></div>
<!-- /wp:designsetgo/card -->

<!-- wp:designsetgo/card {"imageUrl":"{{dsgo:placeholder-avatar}}","imageAspectRatio":"1-1","title":"Sam Rivera","subtitle":"Design Lead","bodyText":"Award-winning designer focused on user experience.","visualStyle":"minimal","showBadge":false,"showCta":false,"style":{"border":{"radius":"12px"}}} -->
<div class="wp-block-designsetgo-card dsgo-card dsgo-card--standard dsgo-card--style-minimal" style="border-radius:12px"><div class="dsgo-card__inner"><div class="dsgo-card__image-wrapper"><img src="{{dsgo:placeholder-avatar}}" alt="Card image" class="dsgo-card__image" style="aspect-ratio:1 / 1;object-fit:cover;object-position:50% 50%" loading="lazy" aria-hidden="true"/></div><div class="dsgo-card__content "><h3 class="dsgo-card__title">Sam Rivera</h3><p class="dsgo-card__subtitle">Design Lead</p><p class="dsgo-card__body">Award-winning designer focused on user experience.</p></div></div></div>
<!-- /wp:designsetgo/card -->

<!-- wp:designsetgo/card {"imageUrl":"{{dsgo:placeholder-avatar}}","imageAspectRatio":"1-1","title":"Taylor Kim","subtitle":"Marketing Director","bodyText":"Growth strategist driving brand awareness.","visualStyle":"minimal","showBadge":false,"showCta":false,"style":{"border":{"radius":"12px"}}} -->
<div class="wp-block-designsetgo-card dsgo-card dsgo-card--standard dsgo-card--style-minimal" style="border-radius:12px"><div class="dsgo-card__inner"><div class="dsgo-card__image-wrapper"><img src="{{dsgo:placeholder-avatar}}" alt="Card image" class="dsgo-card__image" style="aspect-ratio:1 / 1;object-fit:cover;object-position:50% 50%" loading="lazy" aria-hidden="true"/></div><div class="dsgo-card__content "><h3 class="dsgo-card__title">Taylor Kim</h3><p class="dsgo-card__subtitle">Marketing Director</p><p class="dsgo-card__body">Growth strategist driving brand awareness.</p></div></div></div>
<!-- /wp:designsetgo/card --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

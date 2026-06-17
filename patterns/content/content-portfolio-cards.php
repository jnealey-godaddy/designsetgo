<?php
/**
 * Title: Portfolio Cards
 * Slug: designsetgo/content/content-portfolio-cards
 * Categories: dsgo-content
 * Description: Portfolio or case study cards with hover effects
 * Keywords: portfolio, projects, work, case study, gallery
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Portfolio Cards', 'designsetgo' ),
	'categories' => array( 'dsgo-content' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"contrast","textColor":"base","metadata":{"categories":["dsgo-content"],"patternName":"designsetgo/content/content-portfolio-cards","name":"Portfolio Cards"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-color has-contrast-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--50);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"textAlign":"center","textColor":"base","fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-base-color has-text-color has-x-large-font-size">Our Work</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"rgba(255,255,255,0.7)"}},"fontSize":"medium"} -->
<p class="has-text-align-center has-text-color has-medium-font-size" style="color:rgba(255,255,255,0.7)">Selected projects we are proud of</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"style":{"spacing":{"blockGap":"var:preset|spacing|40","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-3 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(3, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--40);column-gap:var(--wp--preset--spacing--40)"><!-- wp:designsetgo/section {"constrainWidth":false,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}},"border":{"radius":"16px"}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInUp"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-no-width-constraint has-dsgo-animation dsgo-animation-fadeInUp" style="border-radius:16px;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInUp"><div class="dsgo-stack__inner"><!-- wp:designsetgo/card {"imageUrl":"{{dsgo:placeholder-landscape}}","badgeText":"Web Design","title":"E-commerce Platform","subtitle":"Fashion \u0026amp; Retail","bodyText":"Complete redesign of a fashion e-commerce platform with improved UX.","showCta":false,"className":"dsgo-card\u002d\u002dhas-hover","textColor":"contrast","style":{"border":{"radius":"16px"},"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}}} -->
<div class="wp-block-designsetgo-card dsgo-card dsgo-card--standard dsgo-card--style-default dsgo-card--has-hover has-contrast-color has-text-color has-link-color" style="border-radius:16px"><span class="dsgo-card__badge dsgo-card__badge--floating dsgo-card__badge--top-right" role="status" aria-label="Badge">Web Design</span><div class="dsgo-card__inner"><div class="dsgo-card__image-wrapper"><img src="{{dsgo:placeholder-landscape}}" alt="Card image" class="dsgo-card__image" style="aspect-ratio:16 / 9;object-fit:cover;object-position:50% 50%" loading="lazy" aria-hidden="true"/></div><div class="dsgo-card__content "><h3 class="dsgo-card__title">E-commerce Platform</h3><p class="dsgo-card__subtitle">Fashion &amp; Retail</p><p class="dsgo-card__body">Complete redesign of a fashion e-commerce platform with improved UX.</p></div></div></div>
<!-- /wp:designsetgo/card --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"constrainWidth":false,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}},"border":{"radius":"16px"}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInUp"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-no-width-constraint has-dsgo-animation dsgo-animation-fadeInUp" style="border-radius:16px;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInUp"><div class="dsgo-stack__inner"><!-- wp:designsetgo/card {"imageUrl":"{{dsgo:placeholder-landscape}}","badgeText":"Mobile App","title":"Fitness Tracker App","subtitle":"Health \u0026amp; Wellness","bodyText":"A comprehensive fitness tracking app with social features and gamification.","showCta":false,"className":"dsgo-card\u002d\u002dhas-hover","textColor":"contrast","style":{"border":{"radius":"16px"},"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}}} -->
<div class="wp-block-designsetgo-card dsgo-card dsgo-card--standard dsgo-card--style-default dsgo-card--has-hover has-contrast-color has-text-color has-link-color" style="border-radius:16px"><span class="dsgo-card__badge dsgo-card__badge--floating dsgo-card__badge--top-right" role="status" aria-label="Badge">Mobile App</span><div class="dsgo-card__inner"><div class="dsgo-card__image-wrapper"><img src="{{dsgo:placeholder-landscape}}" alt="Card image" class="dsgo-card__image" style="aspect-ratio:16 / 9;object-fit:cover;object-position:50% 50%" loading="lazy" aria-hidden="true"/></div><div class="dsgo-card__content "><h3 class="dsgo-card__title">Fitness Tracker App</h3><p class="dsgo-card__subtitle">Health &amp; Wellness</p><p class="dsgo-card__body">A comprehensive fitness tracking app with social features and gamification.</p></div></div></div>
<!-- /wp:designsetgo/card --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section {"constrainWidth":false,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}},"border":{"radius":"16px"}},"dsgoAnimationEnabled":true,"dsgoEntranceAnimation":"fadeInUp"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-no-width-constraint has-dsgo-animation dsgo-animation-fadeInUp" style="border-radius:16px;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInUp"><div class="dsgo-stack__inner"><!-- wp:designsetgo/card {"imageUrl":"{{dsgo:placeholder-landscape}}","badgeText":"Branding","title":"Brand Identity System","subtitle":"Visual Design","bodyText":"Complete brand identity including logo, guidelines, and marketing materials.","showCta":false,"className":"dsgo-card\u002d\u002dhas-hover","textColor":"contrast","style":{"border":{"radius":"16px"},"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}}} -->
<div class="wp-block-designsetgo-card dsgo-card dsgo-card--standard dsgo-card--style-default dsgo-card--has-hover has-contrast-color has-text-color has-link-color" style="border-radius:16px"><span class="dsgo-card__badge dsgo-card__badge--floating dsgo-card__badge--top-right" role="status" aria-label="Badge">Branding</span><div class="dsgo-card__inner"><div class="dsgo-card__image-wrapper"><img src="{{dsgo:placeholder-landscape}}" alt="Card image" class="dsgo-card__image" style="aspect-ratio:16 / 9;object-fit:cover;object-position:50% 50%" loading="lazy" aria-hidden="true"/></div><div class="dsgo-card__content "><h3 class="dsgo-card__title">Brand Identity System</h3><p class="dsgo-card__subtitle">Visual Design</p><p class="dsgo-card__body">Complete brand identity including logo, guidelines, and marketing materials.</p></div></div></div>
<!-- /wp:designsetgo/card --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

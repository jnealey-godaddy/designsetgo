<?php
/**
 * Title: Team Flip Cards
 * Slug: designsetgo/team/team-flip-cards
 * Categories: dsgo-team
 * Description: Interactive team member flip cards with bios on the back
 * Keywords: team, flip cards, interactive, hover, members
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Team Flip Cards', 'designsetgo' ),
	'categories' => array( 'dsgo-team' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"base-2","metadata":{"categories":["dsgo-team"],"patternName":"designsetgo/team/team-flip-cards","name":"Team Flip Cards"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-base-2-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--50);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"textAlign":"center","fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size">Get to Know Us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size">Hover over our team members to learn more</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/grid {"style":{"spacing":{"blockGap":"var:preset|spacing|40","padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-3 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(3, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--40);column-gap:var(--wp--preset--spacing--40)"><!-- wp:designsetgo/flip-card {"style":{"dimensions":{"minHeight":"350px"}}} -->
<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-flip dsgo-flip-card--horizontal" style="min-height:350px;--dsgo-flip-duration:0.6s;width:100%" data-flip-trigger="hover" data-flip-effect="flip" data-flip-direction="horizontal"><div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face {"side":"front","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"blockGap":"1rem"},"border":{"radius":"16px"}},"backgroundColor":"base"} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front has-base-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:image {"width":"120px","height":"120px","scale":"cover","sizeSlug":"thumbnail","className":"is-style-rounded"} -->
<figure class="wp-block-image size-thumbnail is-resized is-style-rounded"><img src="https://i.pravatar.cc/200?img=68" alt="Chris Anderson" style="object-fit:cover;width:120px;height:120px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"textAlign":"center","level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-text-align-center has-large-font-size">Chris Anderson</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Product Manager</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face -->

<!-- wp:designsetgo/flip-card-face {"side":"back","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"blockGap":"1rem"},"border":{"radius":"16px"}},"backgroundColor":"contrast","textColor":"base"} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__back has-base-color has-contrast-background-color has-text-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size">Chris brings 10 years of product experience from leading tech companies. When not building products, you can find him hiking or playing guitar.</p>
<!-- /wp:paragraph -->

<!-- wp:social-links {"iconColor":"base","iconColorValue":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dcolor\u002d\u002dbase)","className":"is-style-logos-only","layout":{"type":"flex","justifyContent":"center"}} -->
<ul class="wp-block-social-links has-icon-color is-style-logos-only"><!-- wp:social-link {"url":"#","service":"linkedin"} /-->

<!-- wp:social-link {"url":"#","service":"x"} /--></ul>
<!-- /wp:social-links --></div>
<!-- /wp:designsetgo/flip-card-face --></div></div>
<!-- /wp:designsetgo/flip-card -->

<!-- wp:designsetgo/flip-card {"style":{"dimensions":{"minHeight":"350px"}}} -->
<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-flip dsgo-flip-card--horizontal" style="min-height:350px;--dsgo-flip-duration:0.6s;width:100%" data-flip-trigger="hover" data-flip-effect="flip" data-flip-direction="horizontal"><div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face {"side":"front","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"blockGap":"1rem"},"border":{"radius":"16px"}},"backgroundColor":"base"} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front has-base-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:image {"width":"120px","height":"120px","scale":"cover","sizeSlug":"thumbnail","className":"is-style-rounded"} -->
<figure class="wp-block-image size-thumbnail is-resized is-style-rounded"><img src="https://i.pravatar.cc/200?img=26" alt="Maria Santos" style="object-fit:cover;width:120px;height:120px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"textAlign":"center","level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-text-align-center has-large-font-size">Maria Santos</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Lead Developer</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face -->

<!-- wp:designsetgo/flip-card-face {"side":"back","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"blockGap":"1rem"},"border":{"radius":"16px"}},"backgroundColor":"contrast","textColor":"base"} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__back has-base-color has-contrast-background-color has-text-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size">Maria is a React and WordPress expert who loves building accessible interfaces. She contributes to open source and mentors junior developers.</p>
<!-- /wp:paragraph -->

<!-- wp:social-links {"iconColor":"base","iconColorValue":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dcolor\u002d\u002dbase)","className":"is-style-logos-only","layout":{"type":"flex","justifyContent":"center"}} -->
<ul class="wp-block-social-links has-icon-color is-style-logos-only"><!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"linkedin"} /--></ul>
<!-- /wp:social-links --></div>
<!-- /wp:designsetgo/flip-card-face --></div></div>
<!-- /wp:designsetgo/flip-card -->

<!-- wp:designsetgo/flip-card {"style":{"dimensions":{"minHeight":"350px"}}} -->
<div class="wp-block-designsetgo-flip-card dsgo-flip-card dsgo-flip-card--hover dsgo-flip-card--effect-flip dsgo-flip-card--horizontal" style="min-height:350px;--dsgo-flip-duration:0.6s;width:100%" data-flip-trigger="hover" data-flip-effect="flip" data-flip-direction="horizontal"><div class="dsgo-flip-card__container"><!-- wp:designsetgo/flip-card-face {"side":"front","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"blockGap":"1rem"},"border":{"radius":"16px"}},"backgroundColor":"base"} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front has-base-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:image {"width":"120px","height":"120px","scale":"cover","sizeSlug":"thumbnail","className":"is-style-rounded"} -->
<figure class="wp-block-image size-thumbnail is-resized is-style-rounded"><img src="https://i.pravatar.cc/200?img=59" alt="James Wright" style="object-fit:cover;width:120px;height:120px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"textAlign":"center","level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-text-align-center has-large-font-size">James Wright</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">UX Designer</p>
<!-- /wp:paragraph --></div>
<!-- /wp:designsetgo/flip-card-face -->

<!-- wp:designsetgo/flip-card-face {"side":"back","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"blockGap":"1rem"},"border":{"radius":"16px"}},"backgroundColor":"contrast","textColor":"base"} -->
<div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__back has-base-color has-contrast-background-color has-text-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size">James creates intuitive user experiences backed by research. He is passionate about accessibility and inclusive design practices.</p>
<!-- /wp:paragraph -->

<!-- wp:social-links {"iconColor":"base","iconColorValue":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dcolor\u002d\u002dbase)","className":"is-style-logos-only","layout":{"type":"flex","justifyContent":"center"}} -->
<ul class="wp-block-social-links has-icon-color is-style-logos-only"><!-- wp:social-link {"url":"#","service":"dribbble"} /-->

<!-- wp:social-link {"url":"#","service":"linkedin"} /--></ul>
<!-- /wp:social-links --></div>
<!-- /wp:designsetgo/flip-card-face --></div></div>
<!-- /wp:designsetgo/flip-card --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

<?php
/**
 * Title: Quote Highlight
 * Slug: designsetgo/content/content-quote-highlight
 * Categories: dsgo-content
 * Description: Featured quote or testimonial with large typography
 * Keywords: quote, testimonial, blockquote, highlight, featured
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Quote Highlight', 'designsetgo' ),
	'categories' => array( 'dsgo-content' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"color":{"gradient":"linear-gradient(135deg,rgb(30,27,75) 0%,rgb(49,46,129) 100%)"}},"className":"has-dsgo-parallax","metadata":{"categories":["dsgo-content"],"patternName":"designsetgo/content/content-quote-highlight","name":"Quote Highlight"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-dsgo-parallax has-background" style="background:linear-gradient(135deg,rgb(30,27,75) 0%,rgb(49,46,129) 100%);padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"constrainWidth":false,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"className":"has-dsgo-text-reveal"} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-no-width-constraint has-dsgo-text-reveal" style="padding-top:0;padding-right:var(--wp--preset--spacing--50);padding-bottom:0;padding-left:var(--wp--preset--spacing--50)"><div class="dsgo-stack__inner"><!-- wp:designsetgo/icon {"icon":"quote","align":"center","className":"dsgo-lazy-icon","textColor":"base","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}}} -->
<div class="wp-block-designsetgo-icon aligncenter dsgo-icon dsgo-lazy-icon has-base-color has-text-color has-link-color" style="margin-bottom:var(--wp--preset--spacing--40);display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:48px;height:48px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="quote" data-icon-style="filled" data-icon-stroke-width="1.5" role="img" aria-label="Quote"></div></div>
<!-- /wp:designsetgo/icon -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"clamp(1.5rem, 4vw, 2.5rem)","lineHeight":"1.4","fontStyle":"italic","fontWeight":"400"}},"textColor":"base"} -->
<p class="has-text-align-center has-base-color has-text-color" style="font-size:clamp(1.5rem, 4vw, 2.5rem);font-style:italic;font-weight:400;line-height:1.4">The best way to predict the future is to create it. Innovation distinguishes between a leader and a follower.</p>
<!-- /wp:paragraph -->

<!-- wp:designsetgo/divider {"thickness":1,"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}}} -->
<div class="wp-block-designsetgo-divider aligncenter dsgo-divider dsgo-divider--solid" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--40)"><div class="dsgo-divider__container" style="width:100%"><div class="dsgo-divider__line" style="height:1px"></div></div></div>
<!-- /wp:designsetgo/divider -->

<!-- wp:designsetgo/row {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"flex","orientation":"horizontal","justifyContent":"center","flexWrap":"wrap"}} -->
<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:wrap"><!-- wp:image {"width":"60px","height":"60px","scale":"cover","sizeSlug":"thumbnail","align":"center","className":"is-style-rounded"} -->
<figure class="wp-block-image aligncenter size-thumbnail is-resized is-style-rounded"><img src="{{dsgo:placeholder-avatar}}" alt="Steve Jobs" style="object-fit:cover;width:60px;height:60px"/></figure>
<!-- /wp:image -->

<!-- wp:designsetgo/section {"constrainWidth":false,"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-no-width-constraint" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner"><!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"600"}},"textColor":"base"} -->
<p class="has-text-align-center has-base-color has-text-color" style="font-style:normal;font-weight:600">Steve Jobs</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"rgba(255,255,255,0.7)"}},"fontSize":"small"} -->
<p class="has-text-align-center has-text-color has-small-font-size" style="color:rgba(255,255,255,0.7)">Co-founder, Apple Inc.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/row --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/section -->',
);

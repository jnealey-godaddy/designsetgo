<?php
/**
 * Title: Gallery Grid
 * Slug: designsetgo/gallery/gallery-grid
 * Categories: dsgo-gallery
 * Description: A clean image gallery grid with rounded corners
 * Keywords: gallery, grid, images, portfolio, work
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'Gallery Grid', 'designsetgo' ),
	'categories' => array( 'dsgo-gallery' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"metadata":{"categories":["dsgo-gallery"],"patternName":"designsetgo/gallery/gallery-grid","name":"Gallery Grid"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/section {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="margin-bottom:var(--wp--preset--spacing--50);padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"textAlign":"center","fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size">Our Work</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size">A showcase of our recent projects and creative work</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:gallery {"columns":3,"linkTo":"none","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|30","left":"var:preset|spacing|30"}}}} -->
<figure class="wp-block-gallery has-nested-images columns-3 is-cropped"><!-- wp:image {"sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"12px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Project screenshot" style="border-radius:12px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"12px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Project screenshot" style="border-radius:12px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"12px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Project screenshot" style="border-radius:12px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"12px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Project screenshot" style="border-radius:12px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"12px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Project screenshot" style="border-radius:12px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"12px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="{{dsgo:placeholder-landscape}}" alt="Project screenshot" style="border-radius:12px"/></figure>
<!-- /wp:image --></figure>
<!-- /wp:gallery --></div></div>
<!-- /wp:designsetgo/section -->',
);

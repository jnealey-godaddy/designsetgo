<?php
/**
 * Title: CTA Banner with Countdown
 * Slug: designsetgo/cta/cta-banner
 * Categories: dsgo-cta
 * Description: A bold urgency CTA with countdown timer
 * Keywords: cta, banner, countdown, urgency, bold
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'      => __( 'CTA Banner with Countdown', 'designsetgo' ),
	'categories' => array( 'dsgo-cta' ),
	'viewportWidth' => 1200,
	'content'    => '<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"gradient":"vivid-cyan-blue-to-vivid-purple","metadata":{"categories":["dsgo-cta"],"patternName":"designsetgo/cta/cta-banner","name":"CTA Banner with Countdown"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack has-vivid-cyan-blue-to-vivid-purple-gradient-background has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/grid {"desktopColumns":2} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-2 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--50);column-gap:var(--wp--preset--spacing--50)"><!-- wp:designsetgo/section {"layout":{"type":"flex","orientation":"vertical","justifyContent":"left"}} -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"textColor":"base","fontSize":"x-large"} -->
<h2 class="wp-block-heading has-base-color has-text-color has-x-large-font-size" style="font-style:normal;font-weight:700">Limited Time Offer!</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"base","fontSize":"medium"} -->
<p class="has-base-color has-text-color has-medium-font-size">Get 50% off your first year. This exclusive deal ends soon.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:designsetgo/section -->

<!-- wp:designsetgo/section -->
<div class="wp-block-designsetgo-section alignfull dsgo-stack" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"><!-- wp:designsetgo/countdown-timer {"targetDateTime":"2026-02-10T18:56:16.324Z","unitBackgroundColor":"rgba(255,255,255,0.2)","unitPadding":"var:preset|spacing|30","className":"dsgo-countdown dsgo-countdown\u002d\u002dboxed","textColor":"base","fontSize":"small"} -->
<div class="wp-block-designsetgo-countdown-timer dsgo-countdown-timer dsgo-countdown-timer--boxed dsgo-countdown dsgo-countdown--boxed has-base-color has-text-color has-small-font-size" style="gap:1rem" data-target-datetime="2026-02-10T18:56:16.324Z" data-timezone="" data-show-days="true" data-show-hours="true" data-show-minutes="true" data-show-seconds="true" data-completion-action="message"><div class="dsgo-countdown-timer__units"><div class="dsgo-countdown-timer__unit" data-unit-type="days" style="background-color:rgba(255,255,255,0.2);border-color:var(--wp--preset--color--accent-2, currentColor);border-width:2px;border-style:solid;border-radius:12px;padding:var:preset|spacing|30"><div class="dsgo-countdown-timer__number" style="color:var(--wp--preset--color--accent-2, currentColor)">00</div><div class="dsgo-countdown-timer__label" style="color:currentColor">Days</div></div><div class="dsgo-countdown-timer__unit" data-unit-type="hours" style="background-color:rgba(255,255,255,0.2);border-color:var(--wp--preset--color--accent-2, currentColor);border-width:2px;border-style:solid;border-radius:12px;padding:var:preset|spacing|30"><div class="dsgo-countdown-timer__number" style="color:var(--wp--preset--color--accent-2, currentColor)">00</div><div class="dsgo-countdown-timer__label" style="color:currentColor">Hours</div></div><div class="dsgo-countdown-timer__unit" data-unit-type="minutes" style="background-color:rgba(255,255,255,0.2);border-color:var(--wp--preset--color--accent-2, currentColor);border-width:2px;border-style:solid;border-radius:12px;padding:var:preset|spacing|30"><div class="dsgo-countdown-timer__number" style="color:var(--wp--preset--color--accent-2, currentColor)">00</div><div class="dsgo-countdown-timer__label" style="color:currentColor">Min</div></div><div class="dsgo-countdown-timer__unit" data-unit-type="seconds" style="background-color:rgba(255,255,255,0.2);border-color:var(--wp--preset--color--accent-2, currentColor);border-width:2px;border-style:solid;border-radius:12px;padding:var:preset|spacing|30"><div class="dsgo-countdown-timer__number" style="color:var(--wp--preset--color--accent-2, currentColor)">00</div><div class="dsgo-countdown-timer__label" style="color:currentColor">Sec</div></div></div><div class="dsgo-countdown-timer__completion-message">Offer has ended!</div></div>
<!-- /wp:designsetgo/countdown-timer --></div></div>
<!-- /wp:designsetgo/section --></div></div>
<!-- /wp:designsetgo/grid --></div></div>
<!-- /wp:designsetgo/section -->',
);

<?php
/**
 * Catalog the retired block and extension allowlists were saved against.
 *
 * DesignSetGo 2.8.1 and earlier stored `enabled_blocks` / `enabled_extensions`
 * allowlists. Settings converts each into a denylist by listing what the
 * allowlist left out, and it must do that against the names that existed
 * when the allowlist could have been saved: these, as of 2.8.1. Converting
 * against the live catalog instead would mark every block or extension added
 * in a later release as disabled, which is the bug the denylists fixed.
 *
 * Never add to these lists. Removing a name is fine if the block or
 * extension itself is removed.
 *
 * @package DesignSetGo
 * @since 2.8.2
 */

defined( 'ABSPATH' ) || exit;

return array(
	'disabled_blocks'     => array(
		'designsetgo/grid',
		'designsetgo/row',
		'designsetgo/section',
		'designsetgo/fifty-fifty',
		'designsetgo/sticky-sections',
		'designsetgo/icon',
		'designsetgo/icon-button',
		'designsetgo/icon-list',
		'designsetgo/icon-list-item',
		'designsetgo/pill',
		'designsetgo/card',
		'designsetgo/divider',
		'designsetgo/accordion',
		'designsetgo/accordion-item',
		'designsetgo/tabs',
		'designsetgo/tab',
		'designsetgo/scroll-accordion',
		'designsetgo/scroll-accordion-item',
		'designsetgo/scroll-marquee',
		'designsetgo/image-accordion',
		'designsetgo/image-accordion-item',
		'designsetgo/scroll-slides',
		'designsetgo/scroll-slide',
		'designsetgo/advanced-heading',
		'designsetgo/heading-segment',
		'designsetgo/comparison-table',
		'designsetgo/section-divider',
		'designsetgo/text-path',
		'designsetgo/timeline',
		'designsetgo/timeline-item',
		'designsetgo/flip-card',
		'designsetgo/flip-card-face',
		'designsetgo/slider',
		'designsetgo/slide',
		'designsetgo/blobs',
		'designsetgo/modal',
		'designsetgo/modal-trigger',
		'designsetgo/hotspot',
		'designsetgo/hotspot-item',
		'designsetgo/flip-card-front',
		'designsetgo/flip-card-back',
		'designsetgo/counter-group',
		'designsetgo/counter',
		'designsetgo/progress-bar',
		'designsetgo/star-rating',
		'designsetgo/countdown-timer',
		'designsetgo/map',
		'designsetgo/breadcrumbs',
		'designsetgo/table-of-contents',
		'designsetgo/chart',
		'designsetgo/query',
		'designsetgo/query-results',
		'designsetgo/query-filter',
		'designsetgo/query-pagination',
		'designsetgo/query-no-results',
		'designsetgo/query-group-header',
		'designsetgo/dynamic-image',
		'designsetgo/product-categories-grid',
		'designsetgo/product-showcase-hero',
		'designsetgo/form-builder',
		'designsetgo/form-text-field',
		'designsetgo/form-email-field',
		'designsetgo/form-textarea-field',
		'designsetgo/form-number-field',
		'designsetgo/form-phone-field',
		'designsetgo/form-url-field',
		'designsetgo/form-date-field',
		'designsetgo/form-time-field',
		'designsetgo/form-select-field',
		'designsetgo/form-checkbox-field',
		'designsetgo/form-hidden-field',
	),
	'disabled_extensions' => array(
		'animation',
		'background-video',
		'block-animations',
		'clickable-group',
		'custom-css',
		'grid-span',
		'max-width',
		'responsive',
		'reveal-control',
		'sticky-header-controls',
		'text-alignment-inheritance',
		'expanding-background',
		'text-reveal',
		'vertical-scroll-parallax',
		'draft-mode',
		'dynamic-tags',
	),
);

<?php
/**
 * Translatable strings for blocks-registry.json
 *
 * This file exists solely so that `wp i18n make-pot` can extract the
 * translatable strings that live in blocks-registry.json. It is never
 * loaded at runtime — the JSON file is the single source of truth.
 *
 * Regenerate after editing blocks-registry.json:
 *   php -r '$d=json_decode(file_get_contents("blocks-registry.json"),true);
 *     foreach($d as $c){echo "__(\x27{$c["label"]}\x27,\x27designsetgo\x27);\n";
 *     foreach($c["blocks"] as $b){echo "__(\x27{$b["title"]}\x27,\x27designsetgo\x27);\n";
 *     echo "__(\x27{$b["description"]}\x27,\x27designsetgo\x27);\n";}}'
 *
 * @package DesignSetGo
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable
// Category labels.
__( 'Container Blocks', 'designsetgo' );
__( 'UI Elements', 'designsetgo' );
__( 'Interactive Blocks', 'designsetgo' );
__( 'Dynamic Blocks', 'designsetgo' );
__( 'Dynamic Content Blocks', 'designsetgo' );
__( 'WooCommerce Blocks', 'designsetgo' );
__( 'Form Blocks', 'designsetgo' );

// Container Blocks.
__( 'Grid Container', 'designsetgo' );
__( 'CSS Grid-based responsive layouts', 'designsetgo' );
__( 'Row', 'designsetgo' );
__( 'Flexible horizontal or vertical layouts with wrapping', 'designsetgo' );
__( 'Section', 'designsetgo' );
__( 'Vertical stacking container for sections and content areas', 'designsetgo' );
__( 'Fifty Fifty', 'designsetgo' );
__( 'Full-width 50/50 split with edge-to-edge media', 'designsetgo' );
__( 'Sticky Sections', 'designsetgo' );
__( 'Sections that stick and stack as you scroll', 'designsetgo' );

// UI Elements.
__( 'Icon', 'designsetgo' );
__( 'Inline SVG icons with styling', 'designsetgo' );
__( 'Icon Button', 'designsetgo' );
__( 'Button with icon support', 'designsetgo' );
__( 'Icon List', 'designsetgo' );
__( 'List with custom icons', 'designsetgo' );
__( 'Icon List Item', 'designsetgo' );
__( 'Individual list item', 'designsetgo' );
__( 'Pill', 'designsetgo' );
__( 'Badge/tag style elements', 'designsetgo' );
__( 'Card', 'designsetgo' );
__( 'Display content in a card layout with image, badge, title, and CTA', 'designsetgo' );
__( 'Divider', 'designsetgo' );
__( 'Visual separator with multiple style options', 'designsetgo' );
__( 'Accordion', 'designsetgo' );
__( 'Traditional accordion container', 'designsetgo' );
__( 'Accordion Item', 'designsetgo' );
__( 'Individual accordion panel', 'designsetgo' );
__( 'Tabs', 'designsetgo' );
__( 'Tabbed content with deep linking', 'designsetgo' );
__( 'Tab', 'designsetgo' );
__( 'Individual tab panel', 'designsetgo' );
__( 'Scroll Accordion', 'designsetgo' );
__( 'Sticky stacking scroll effect', 'designsetgo' );
__( 'Scroll Accordion Item', 'designsetgo' );
__( 'Individual scroll panel', 'designsetgo' );
__( 'Scroll Marquee', 'designsetgo' );
__( 'Infinite scrolling content', 'designsetgo' );
__( 'Image Accordion', 'designsetgo' );
__( 'Accordion with images', 'designsetgo' );
__( 'Image Accordion Item', 'designsetgo' );
__( 'Individual image item', 'designsetgo' );
__( 'Scroll Slides', 'designsetgo' );
__( 'Scroll-pinned fullscreen slideshow with crossfading transitions', 'designsetgo' );
__( 'Scroll Slide', 'designsetgo' );
__( 'Individual slide within Scroll Slides', 'designsetgo' );
__( 'Advanced Heading', 'designsetgo' );
__( 'Headings that mix fonts, weights, and colors', 'designsetgo' );
__( 'Heading Segment', 'designsetgo' );
__( 'Individual styled segment of an Advanced Heading', 'designsetgo' );
__( 'Comparison Table', 'designsetgo' );
__( 'Feature comparison for products, services, or plans', 'designsetgo' );
__( 'Section Divider', 'designsetgo' );
__( 'Standalone shape divider between two blocks', 'designsetgo' );
__( 'Text Path', 'designsetgo' );
__( 'Text flowed along an SVG path', 'designsetgo' );
__( 'Timeline', 'designsetgo' );
__( 'Chronological events with scroll-reveal animation', 'designsetgo' );
__( 'Timeline Item', 'designsetgo' );
__( 'Individual milestone within a timeline', 'designsetgo' );

// Interactive Blocks.
__( 'Flip Card', 'designsetgo' );
__( '3D flip card container', 'designsetgo' );
__( 'Flip Card Face', 'designsetgo' );
__( 'Front or back face of a flip card', 'designsetgo' );
__( 'Slider', 'designsetgo' );
__( 'Modern carousel with effects', 'designsetgo' );
__( 'Slide', 'designsetgo' );
__( 'Individual slider slide', 'designsetgo' );
__( 'Blobs', 'designsetgo' );
__( 'Animated blob shapes', 'designsetgo' );
__( 'Modal', 'designsetgo' );
__( 'Accessible modal dialogs with customizable triggers', 'designsetgo' );
__( 'Modal Trigger', 'designsetgo' );
__( 'Button or link that opens a modal dialog', 'designsetgo' );
__( 'Hotspot', 'designsetgo' );
__( 'Interactive markers placed over an image', 'designsetgo' );
__( 'Hotspot Item', 'designsetgo' );
__( 'Individual marker within a Hotspot image', 'designsetgo' );
__( 'Flip Card Front', 'designsetgo' );
__( 'Legacy front face, kept for existing content', 'designsetgo' );
__( 'Flip Card Back', 'designsetgo' );
__( 'Legacy back face, kept for existing content', 'designsetgo' );

// Dynamic Blocks.
__( 'Counter Group', 'designsetgo' );
__( 'Animated statistics container', 'designsetgo' );
__( 'Counter', 'designsetgo' );
__( 'Individual animated counter', 'designsetgo' );
__( 'Progress Bar', 'designsetgo' );
__( 'Animated progress indicators', 'designsetgo' );
__( 'Star Rating', 'designsetgo' );
__( 'Star rating from a fixed value or a dynamic source', 'designsetgo' );
__( 'Countdown Timer', 'designsetgo' );
__( 'Display a countdown timer to a specific date and time', 'designsetgo' );
__( 'Map', 'designsetgo' );
__( 'Interactive map using OpenStreetMap or Google Maps', 'designsetgo' );
__( 'Breadcrumbs', 'designsetgo' );
__( 'Navigation breadcrumbs with Schema.org markup', 'designsetgo' );
__( 'Table of Contents', 'designsetgo' );
__( 'Auto-generate table of contents from page headings', 'designsetgo' );
__( 'Chart', 'designsetgo' );
__( 'Bar, line, or donut chart rendered without JavaScript', 'designsetgo' );

// Dynamic Content Blocks.
__( 'Dynamic Query', 'designsetgo' );
__( 'Query posts, users, or terms and design the results', 'designsetgo' );
__( 'Query Results', 'designsetgo' );
__( 'Repeating item template inside a Dynamic Query', 'designsetgo' );
__( 'Query Filter', 'designsetgo' );
__( 'Filter, search, sort, or reset a Dynamic Query', 'designsetgo' );
__( 'Query Pagination', 'designsetgo' );
__( 'Numbered, load-more, or infinite-scroll pagination', 'designsetgo' );
__( 'No Results', 'designsetgo' );
__( 'Content shown when a Dynamic Query returns nothing', 'designsetgo' );
__( 'Query Group Header', 'designsetgo' );
__( 'Heading rendered once per group in a Dynamic Query', 'designsetgo' );
__( 'Dynamic Image', 'designsetgo' );
__( 'Image whose source resolves from post, site, or field data', 'designsetgo' );

// WooCommerce Blocks.
__( 'Product Categories Grid', 'designsetgo' );
__( 'WooCommerce product categories in a visual grid', 'designsetgo' );
__( 'Product Showcase Hero', 'designsetgo' );
__( 'Full-width hero for a single WooCommerce product', 'designsetgo' );

// Form Blocks.
__( 'Form Builder', 'designsetgo' );
__( 'Complete form with AJAX submission', 'designsetgo' );
__( 'Text Field', 'designsetgo' );
__( 'Text input field', 'designsetgo' );
__( 'Email Field', 'designsetgo' );
__( 'Email input field', 'designsetgo' );
__( 'Textarea', 'designsetgo' );
__( 'Multi-line text input', 'designsetgo' );
__( 'Number Field', 'designsetgo' );
__( 'Number input field', 'designsetgo' );
__( 'Phone Field', 'designsetgo' );
__( 'Phone number input', 'designsetgo' );
__( 'URL Field', 'designsetgo' );
__( 'URL input field', 'designsetgo' );
__( 'Date Field', 'designsetgo' );
__( 'Date picker input', 'designsetgo' );
__( 'Time Field', 'designsetgo' );
__( 'Time picker input', 'designsetgo' );
__( 'Select Field', 'designsetgo' );
__( 'Dropdown select', 'designsetgo' );
__( 'Checkbox Field', 'designsetgo' );
__( 'Checkbox input', 'designsetgo' );
__( 'Hidden Field', 'designsetgo' );
__( 'Hidden form field', 'designsetgo' );
// phpcs:enable

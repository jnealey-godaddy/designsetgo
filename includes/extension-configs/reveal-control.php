<?php
/**
 * Reveal control extension attribute schema (child attribute for all blocks).
 *
 * @see src/extensions/reveal-control/index.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoRevealOnHover' => array(
			'type'    => 'boolean',
			'default' => false,
		),
	),
);

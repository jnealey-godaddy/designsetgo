<?php
/**
 * Responsive extension attribute schema.
 *
 * @see src/extensions/responsive/index.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoHideOnDesktop' => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'dsgoHideOnTablet'  => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'dsgoHideOnMobile'  => array(
			'type'    => 'boolean',
			'default' => false,
		),
	),
);

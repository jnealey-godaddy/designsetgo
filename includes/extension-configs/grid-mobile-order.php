<?php
/**
 * Grid mobile order extension attribute schema.
 *
 * @see src/extensions/grid-mobile-order/index.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoMobileOrder' => array(
			'type'    => 'number',
			'default' => 1,
		),
	),
);

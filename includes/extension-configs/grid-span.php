<?php
/**
 * Grid span extension attribute schema.
 *
 * @see src/extensions/grid-span/index.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoColumnSpan' => array(
			'type'    => 'number',
			'default' => 1,
		),
		'dsgoRowSpan'    => array(
			'type'    => 'number',
			'default' => 1,
		),
	),
);

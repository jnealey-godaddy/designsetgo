<?php
/**
 * Custom CSS extension attribute schema.
 *
 * @see src/extensions/custom-css/index.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array( 'core/html', 'core/code' ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoCustomCSS' => array(
			'type'    => 'string',
			'default' => '',
		),
	),
);

<?php
/**
 * Dynamic CSS style binding extension attribute schema.
 *
 * @see src/extensions/style-binding/filters.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array( 'core/freeform', 'core/missing', 'core/template-part' ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoStyleBinding' => array(
			'type'    => 'object',
			'default' => array(),
		),
	),
);

<?php
/**
 * Schema extension attribute schema.
 *
 * Mirrors the JS allowlist so the attribute survives server-side block
 * registration. Keep this list identical to SCHEMA_BLOCKS.
 *
 * @see src/extensions/schema/constants.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => array(
		'designsetgo/accordion',
		'designsetgo/star-rating',
	),
	'exclude'    => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoSchema' => array(
			'type'    => 'string',
			'default' => 'none',
		),
	),
);

<?php
/**
 * Interaction layers extension attribute schema.
 *
 * @see src/extensions/interactions/attributes.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array( 'core/freeform', 'core-embed/*' ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoInteractions' => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
);

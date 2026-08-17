<?php
/**
 * Block visibility extension attribute schema.
 *
 * @see src/extensions/visibility/filters.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array( 'core/freeform', 'core/missing', 'core/template-part' ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoVisibility' => array(
			'type'    => 'object',
			'default' => null,
		),
	),
);

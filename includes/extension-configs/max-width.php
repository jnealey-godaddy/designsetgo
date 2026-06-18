<?php
/**
 * Max width extension attribute schema.
 *
 * @see src/extensions/max-width/index.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array( // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
		'core/spacer',
		'core/separator',
		'core/page-list',
		'core/navigation',
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
	),
	'attributes' => array(
		'dsgoMaxWidth' => array(
			'type'    => 'string',
			'default' => '',
		),
	),
);

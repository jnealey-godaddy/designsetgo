<?php
/**
 * SVG patterns extension attribute schema.
 *
 * @see src/extensions/svg-patterns/attributes.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => array(
		'core/group',
		'designsetgo/section',
	),
	'exclude'    => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoSvgPatternEnabled' => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'dsgoSvgPatternType'    => array(
			'type'    => 'string',
			'default' => '',
		),
		'dsgoSvgPatternColor'   => array(
			'type'    => 'string',
			'default' => '',
		),
		'dsgoSvgPatternOpacity' => array(
			'type'    => 'number',
			'default' => 0.4,
		),
		'dsgoSvgPatternScale'   => array(
			'type'    => 'number',
			'default' => 1,
		),
		'dsgoSvgPatternFixed'   => array(
			'type'    => 'boolean',
			'default' => false,
		),
	),
);

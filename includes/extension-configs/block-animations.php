<?php
/**
 * Block animations extension attribute schema.
 *
 * @see src/extensions/block-animations/attributes.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array( 'core/freeform', 'core-embed/*' ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoAnimationEnabled'  => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'dsgoEntranceAnimation' => array(
			'type'    => 'string',
			'default' => '',
		),
		'dsgoExitAnimation'     => array(
			'type'    => 'string',
			'default' => '',
		),
		'dsgoAnimationTrigger'  => array(
			'type'    => 'string',
			'default' => 'scroll',
		),
		'dsgoAnimationDuration' => array(
			'type'    => 'number',
			'default' => 600,
		),
		'dsgoAnimationDelay'    => array(
			'type'    => 'number',
			'default' => 0,
		),
		'dsgoAnimationEasing'   => array(
			'type'    => 'string',
			'default' => 'ease-out',
		),
		'dsgoAnimationOffset'   => array(
			'type'    => 'number',
			'default' => 100,
		),
		'dsgoAnimationOnce'     => array(
			'type'    => 'boolean',
			'default' => true,
		),
	),
);

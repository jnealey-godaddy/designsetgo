<?php
/**
 * Expanding background extension attribute schema.
 *
 * @see src/extensions/expanding-background/attributes.js
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
		'dsgoExpandingBgEnabled'         => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'dsgoExpandingBgColor'           => array(
			'type'    => 'string',
			'default' => '#e8e8e8',
		),
		'dsgoExpandingBgInitialSize'     => array(
			'type'    => 'number',
			'default' => 50,
		),
		'dsgoExpandingBgBlur'            => array(
			'type'    => 'number',
			'default' => 30,
		),
		'dsgoExpandingBgSpeed'           => array(
			'type'    => 'number',
			'default' => 1,
		),
		'dsgoExpandingBgTriggerOffset'   => array(
			'type'    => 'number',
			'default' => 0,
		),
		'dsgoExpandingBgCompletionPoint' => array(
			'type'    => 'number',
			'default' => 80,
		),
	),
);

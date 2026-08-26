<?php
/**
 * Text reveal extension attribute schema.
 *
 * @see src/extensions/text-reveal/attributes.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => array(
		'core/paragraph',
		'core/heading',
	),
	'exclude'    => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
	'attributes' => array(
		'dsgoTextRevealEnabled'    => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'dsgoTextRevealColor'      => array(
			'type'    => 'string',
			'default' => '#2563eb',
		),
		'dsgoTextRevealSplitMode'  => array(
			'type'    => 'string',
			'default' => 'word',
		),
		'dsgoTextRevealTransition' => array(
			'type'    => 'number',
			'default' => 150,
		),
		'dsgoTextRevealEffect'     => array(
			'type'    => 'string',
			'default' => 'color',
		),
	),
);

<?php
/**
 * Grid span extension attribute schema.
 *
 * @see src/extensions/grid-span/index.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array(),
	'attributes' => array(
		'dsgoColumnSpan' => array(
			'type'    => 'number',
			'default' => 1,
		),
		'dsgoRowSpan'    => array(
			'type'    => 'number',
			'default' => 1,
		),
	),
);

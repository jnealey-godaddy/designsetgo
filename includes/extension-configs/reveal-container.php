<?php
/**
 * Reveal container extension attribute schema.
 *
 * @see src/extensions/reveal-control/index.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => array(
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
	),
	'exclude'    => array(),
	'attributes' => array(
		'enableRevealOnHover'  => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'revealAnimationType'  => array(
			'type'    => 'string',
			'default' => 'fade',
		),
	),
);

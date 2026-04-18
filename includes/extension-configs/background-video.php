<?php
/**
 * Background video extension attribute schema.
 *
 * @see src/extensions/background-video/index.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => array(
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
		'designsetgo/flip-card',
		'designsetgo/flip-card-face',
		'designsetgo/flip-card-front',
		'designsetgo/flip-card-back',
		'designsetgo/accordion',
		'designsetgo/accordion-item',
		'designsetgo/tabs',
		'designsetgo/tab',
		'designsetgo/scroll-accordion',
		'designsetgo/scroll-accordion-item',
		'designsetgo/scroll-marquee',
		'designsetgo/image-accordion',
		'designsetgo/image-accordion-item',
	),
	'exclude'    => array(),
	'attributes' => array(
		'dsgoVideoUrl'          => array(
			'type'    => 'string',
			'default' => '',
		),
		'dsgoVideoPoster'       => array(
			'type'    => 'string',
			'default' => '',
		),
		'dsgoVideoMuted'        => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'dsgoVideoLoop'         => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'dsgoVideoAutoplay'     => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'dsgoVideoMobileHide'   => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'dsgoVideoOverlayColor' => array(
			'type'    => 'string',
			'default' => '',
		),
	),
);

<?php
/**
 * Vertical scroll parallax extension attribute schema.
 *
 * @see src/extensions/vertical-scroll-parallax/attributes.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => array(
		// Core blocks.
		'core/group',
		'core/cover',
		'core/image',
		'core/media-text',
		'core/columns',
		'core/column',
		// DesignSetGo container blocks.
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
		// DesignSetGo visual blocks.
		'designsetgo/flip-card',
		'designsetgo/flip-card-face',
		'designsetgo/flip-card-front',
		'designsetgo/flip-card-back',
		'designsetgo/icon',
		'designsetgo/icon-button',
		'designsetgo/image-accordion',
		'designsetgo/image-accordion-item',
		'designsetgo/scroll-accordion',
		'designsetgo/scroll-accordion-item',
	),
	'exclude'    => array(),
	'attributes' => array(
		'dsgoParallaxEnabled'       => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'dsgoParallaxDirection'     => array(
			'type'    => 'string',
			'default' => 'up',
		),
		'dsgoParallaxSpeed'         => array(
			'type'    => 'number',
			'default' => 5,
		),
		'dsgoParallaxViewportStart' => array(
			'type'    => 'number',
			'default' => 0,
		),
		'dsgoParallaxViewportEnd'   => array(
			'type'    => 'number',
			'default' => 100,
		),
		'dsgoParallaxRelativeTo'    => array(
			'type'    => 'string',
			'default' => 'viewport',
		),
		'dsgoParallaxDesktop'       => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'dsgoParallaxTablet'        => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'dsgoParallaxMobile'            => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'dsgoParallaxRotateEnabled'     => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'dsgoParallaxRotateDirection'   => array(
			'type'    => 'string',
			'default' => 'cw',
			'enum'    => array( 'cw', 'ccw' ),
		),
		'dsgoParallaxRotateSpeed'       => array(
			'type'    => 'number',
			'default' => 3,
		),
	),
);

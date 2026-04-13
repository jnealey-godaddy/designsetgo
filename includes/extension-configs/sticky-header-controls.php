<?php
/**
 * Sticky header controls extension attribute schema.
 *
 * @see src/extensions/sticky-header-controls/index.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => array( 'core/template-part' ),
	'exclude'    => array(),
	'attributes' => array(
		'dsgoStickyEnabled'      => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'dsgoStickyShadow'       => array(
			'type'    => 'string',
			'default' => 'medium',
		),
		'dsgoStickyShrink'       => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'dsgoStickyShrinkAmount' => array(
			'type'    => 'number',
			'default' => 50,
		),
		'dsgoStickyHideOnScroll' => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'dsgoStickyBackground'   => array(
			'type'    => 'boolean',
			'default' => false,
		),
	),
);

/**
 * Hover Effects - Constants
 *
 * Preset hover micro-interactions and supported block list.
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Hover effect presets
 *
 * Values map 1:1 to CSS class modifiers:
 * `.dsgo-hover-effect--{value}` in styles.scss.
 */
export const HOVER_EFFECTS = [
	{ label: __('None', 'designsetgo'), value: '' },
	{ label: __('Lift', 'designsetgo'), value: 'lift' },
	{ label: __('Sink', 'designsetgo'), value: 'sink' },
	{ label: __('Grow', 'designsetgo'), value: 'grow' },
	{ label: __('Shrink', 'designsetgo'), value: 'shrink' },
	{ label: __('Tilt', 'designsetgo'), value: 'tilt' },
	{ label: __('Glow', 'designsetgo'), value: 'glow' },
];

/**
 * Blocks that receive the hover effect control
 */
export const SUPPORTED_BLOCKS = [
	'core/group',
	'core/cover',
	'core/column',
	'core/columns',
	'core/image',
	'core/button',
	'core/buttons',
	'core/media-text',
	'core/post-template',
	'core/query',
];

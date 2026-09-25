/**
 * Map Block
 *
 * Displays an interactive map with custom markers using OpenStreetMap or Google Maps.
 */

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import Save from './save';
import deprecated from './deprecated';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

// Import styles
import './editor.scss';
import './style.scss';

/**
 * Register the Map block.
 */
registerBlockType(metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				fill="none"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
			>
				<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
				<circle cx="12" cy="10" r="3" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	// The inserter creates maps at 0,0 so a new block opens on the "find a
	// location" placeholder instead of silently showing the New York default.
	// It has to be a variation, not a new attribute default: WordPress omits
	// default-valued attributes from the block comment, so every existing map
	// that relies on the 40.7128 / -74.006 defaults would move too. Explicit
	// 0s serialize, so only blocks inserted from here on carry them, while
	// createBlock() calls (patterns, transforms) keep the defaults.
	variations: [
		{
			name: 'map',
			title: metadata.title,
			description: metadata.description,
			isDefault: true,
			scope: ['inserter'],
			attributes: { dsgoLatitude: 0, dsgoLongitude: 0 },
		},
	],
	deprecated,
	edit: Edit,
	save: Save,
});

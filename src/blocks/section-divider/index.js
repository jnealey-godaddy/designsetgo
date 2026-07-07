/**
 * Section Divider Block Registration
 *
 * Standalone solid-filled shape divider. Shape, height, and fill color
 * inherit from theme.json tokens by default; users override per instance.
 *
 * @since 2.7.0
 */

import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';

import edit from './edit';
import save from './save';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

import './editor.scss';
import './style.scss';

/**
 * Register Section Divider Block
 */
registerBlockType(metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg
				width="24"
				height="24"
				viewBox="0 0 24 24"
				fill="none"
				xmlns="http://www.w3.org/2000/svg"
			>
				<path
					d="M2 15 C7 8 17 8 22 15 L22 20 L2 20 Z"
					fill="currentColor"
				/>
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
});

// A freshly inserted divider should show a visible shape immediately, even
// on themes with no `--wp--custom--designsetgo--shape-divider--color` set.
// A default block variation only changes the INSERT-time attributes — the
// block.json schema default for `fillColor` stays `""`, so parsed/existing
// content and a manually cleared color still fall through to theme.json
// global styles (see utils/getDividerStyle).
registerBlockVariation(metadata.name, {
	name: 'default',
	title: metadata.title,
	description: metadata.description,
	isDefault: true,
	attributes: { fillColor: '#000000' },
	scope: ['inserter', 'block'],
});

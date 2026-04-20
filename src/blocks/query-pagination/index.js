import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';

import edit from './edit';
import save from './save';
import metadata from './block.json';
import variations from './variations';
import { ICON_COLOR } from '../shared/constants';

import './editor.scss';
import './style.scss';

registerBlockType(metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				fill="none"
				stroke="currentColor"
				strokeWidth="1.5"
				strokeLinecap="round"
				strokeLinejoin="round"
			>
				<rect x="3" y="9" width="4" height="6" rx="1" />
				<rect x="10" y="9" width="4" height="6" rx="1" />
				<rect x="17" y="9" width="4" height="6" rx="1" />
				<circle cx="5" cy="12" r="0.75" fill="currentColor" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
});

// Register the Infinite Scroll variation.
variations.forEach((variation) => {
	registerBlockVariation(metadata.name, variation);
});

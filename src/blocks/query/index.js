import { registerBlockType } from '@wordpress/blocks';

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
				<rect x="3" y="4" width="18" height="4" rx="1" />
				<rect x="3" y="10" width="18" height="4" rx="1" />
				<rect x="3" y="16" width="18" height="4" rx="1" />
				<circle cx="7" cy="6" r="0.75" fill="currentColor" />
				<circle cx="7" cy="12" r="0.75" fill="currentColor" />
				<circle cx="7" cy="18" r="0.75" fill="currentColor" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	variations,
	edit,
	save,
});

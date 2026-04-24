/**
 * Dynamic Image block — registration.
 */
import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import save from './save';
import metadata from './block.json';

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
				strokeWidth="1.75"
				strokeLinecap="round"
				strokeLinejoin="round"
			>
				<rect x="3" y="4" width="18" height="16" rx="2" />
				<path d="M3 16l5-5 4 4 3-3 6 6" />
				<circle cx="9" cy="9" r="1.5" fill="currentColor" />
			</svg>
		),
	},
	edit,
	save,
});

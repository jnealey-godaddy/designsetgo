/**
 * Query Filter block — registration.
 *
 * @since 2.1.0
 */
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
				<path d="M3 5h18l-7 9v5l-4 2v-7L3 5z" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
});

// Register the 6 filter variations.
variations.forEach((variation) => {
	registerBlockVariation(metadata.name, variation);
});

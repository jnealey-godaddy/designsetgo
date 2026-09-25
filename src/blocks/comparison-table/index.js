/**
 * Comparison Table Block
 *
 * Feature comparison table with dynamic columns, multiple cell types,
 * and responsive design for comparing products, services, or plans.
 */

import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
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
				<rect x="3" y="3" width="18" height="18" rx="2" />
				<line x1="3" y1="9" x2="21" y2="9" />
				<line x1="3" y1="14" x2="21" y2="14" />
				<line x1="3" y1="19" x2="21" y2="19" />
				<line x1="9" y1="3" x2="9" y2="21" />
				<line x1="15" y1="3" x2="15" y2="21" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
	deprecated,
});

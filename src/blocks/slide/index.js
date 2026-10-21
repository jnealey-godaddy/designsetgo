/**
 * Slide Block Registration
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import deprecated from './deprecated';
import { ICON_COLOR } from '../shared/constants';
import './style.scss';
import './editor.scss';

registerBlockType(metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
				<rect
					x="3"
					y="5"
					width="18"
					height="14"
					rx="1"
					fill="none"
					stroke="currentColor"
					strokeWidth="2"
				/>
				<circle cx="8" cy="10" r="1.5" />
				<path d="M14 16l-3-4-2 2.67L14 16z" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit: Edit,
	save: Save,
	deprecated,
});

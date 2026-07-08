import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import Save from './save';
import deprecated from './deprecated';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

// Import styles
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
				<rect x="2" y="4" width="5" height="16" rx="1" />
				<rect x="9" y="4" width="5" height="16" rx="1" />
				<rect x="16" y="4" width="6" height="16" rx="1" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit: Edit,
	save: Save,
	deprecated,
});

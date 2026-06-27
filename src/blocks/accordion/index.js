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
	deprecated,
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
				<path d="M3 6h18M3 12h18M3 18h18" />
				<circle cx="7" cy="6" r="1.5" fill="currentColor" />
				<circle cx="7" cy="12" r="1.5" fill="currentColor" />
				<circle cx="7" cy="18" r="1.5" fill="currentColor" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit: Edit,
	save: Save,
});

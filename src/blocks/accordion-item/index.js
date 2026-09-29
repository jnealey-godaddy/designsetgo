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
				strokeWidth="2"
			>
				<rect x="3" y="6" width="18" height="12" rx="1" />
				<polyline points="19,12 17,12 17,15" />
				<line x1="3" y1="12" x2="21" y2="12" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit: Edit,
	save: Save,
	deprecated,
});

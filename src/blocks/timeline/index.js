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
				{/* Vertical timeline line */}
				<line x1="12" y1="3" x2="12" y2="21" />
				{/* Timeline markers */}
				<circle cx="12" cy="8" r="2.5" fill="currentColor" />
				<circle cx="12" cy="16" r="2.5" fill="currentColor" />
				{/* Content indicators */}
				<line x1="16" y1="8" x2="20" y2="8" />
				<line x1="4" y1="16" x2="8" y2="16" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit: Edit,
	save: Save,
	deprecated,
});

import { registerBlockType } from '@wordpress/blocks';
import edit from './edit';
import save from './save';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

registerBlockType( metadata.name, {
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
				<rect x="3" y="3" width="18" height="5" rx="1" />
				<line x1="3" y1="12" x2="21" y2="12" />
				<line x1="3" y1="17" x2="15" y2="17" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
} );

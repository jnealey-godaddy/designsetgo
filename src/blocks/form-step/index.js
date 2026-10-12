import { registerBlockType } from '@wordpress/blocks';
import edit from './edit';
import save from './save';
import metadata from './block.json';
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
				<rect x="4" y="3" width="16" height="4" rx="1" />
				<rect x="4" y="10" width="16" height="4" rx="1" />
				<rect x="4" y="17" width="16" height="4" rx="1" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
});

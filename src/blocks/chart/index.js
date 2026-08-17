/**
 * Chart Block
 *
 * @since 1.3.0
 */
import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

import './style.scss';

registerBlockType(metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
				<path
					d="M4 19h2v-8H4v8zm5 0h2V5H9v14zm5 0h2v-6h-2v6zm5 0h2V9h-2v10z"
					fill="currentColor"
				/>
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	// Dynamic block: no save(), the server renders it.
	save: () => null,
});

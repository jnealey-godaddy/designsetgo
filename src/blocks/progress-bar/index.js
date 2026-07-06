/**
 * Progress Bar Block
 *
 * Displays an animated progress bar with customizable appearance.
 *
 * @since 1.0.0
 */

import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

import './editor.scss';
import './style.scss';

/**
 * Register Progress Bar Block
 */
registerBlockType(metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg
				viewBox="0 0 24 24"
				xmlns="http://www.w3.org/2000/svg"
				fill="currentColor"
			>
				<path d="M20 11H4c-1.1 0-2 .9-2 2s.9 2 2 2h16c1.1 0 2-.9 2-2s-.9-2-2-2z" />
				<path d="M4 12h10v2H4z" fillOpacity="0.6" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
	deprecated,
});

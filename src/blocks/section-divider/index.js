/**
 * Section Divider Block Registration
 *
 * Standalone solid-filled shape divider. Shape, height, and fill color
 * inherit from theme.json tokens by default; users override per instance.
 *
 * @since 2.7.0
 */

import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import save from './save';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

import './editor.scss';
import './style.scss';

/**
 * Register Section Divider Block
 */
registerBlockType( metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg
				width="24"
				height="24"
				viewBox="0 0 24 24"
				fill="none"
				xmlns="http://www.w3.org/2000/svg"
			>
				<path
					d="M2 15 C7 8 17 8 22 15 L22 20 L2 20 Z"
					fill="currentColor"
				/>
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
} );

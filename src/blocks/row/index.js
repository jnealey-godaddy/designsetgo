/**
 * Row Block Registration
 *
 * Flexible horizontal layouts with wrapping.
 *
 * @since 1.0.0
 */

import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

import './editor.scss';
import './style.scss';

/**
 * Register Row Block
 */
registerBlockType(metadata.name, {
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
				<rect
					x="4"
					y="9"
					width="4"
					height="6"
					rx="1"
					fill="currentColor"
				/>
				<rect
					x="10"
					y="9"
					width="4"
					height="6"
					rx="1"
					fill="currentColor"
				/>
				<rect
					x="16"
					y="9"
					width="4"
					height="6"
					rx="1"
					fill="currentColor"
				/>
				<path
					d="M2 7L4 5L6 7M18 7L20 5L22 7M2 17L4 19L6 17M18 17L20 19L22 17"
					stroke="currentColor"
					strokeWidth="1.5"
					strokeLinecap="round"
					strokeLinejoin="round"
					opacity="0.5"
				/>
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
	transforms,
	deprecated,
});

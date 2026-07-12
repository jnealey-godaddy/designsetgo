/**
 * Flip Card Block Registration
 *
 * Interactive card that flips to reveal content on the back.
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
 * Register Flip Card Block
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
				{/* Card front */}
				<rect
					x="4"
					y="4"
					width="16"
					height="16"
					rx="2"
					stroke="currentColor"
					strokeWidth="2"
					fill="none"
				/>
				{/* Card back (offset) */}
				<rect
					x="6"
					y="6"
					width="16"
					height="16"
					rx="2"
					stroke="currentColor"
					strokeWidth="2"
					fill="white"
					strokeDasharray="3 3"
					opacity="0.5"
				/>
				{/* Rotation arrow */}
				<path
					d="M16 8C16 8 17.5 9.5 17.5 11.5C17.5 13.5 16 15 16 15"
					stroke="currentColor"
					strokeWidth="2"
					strokeLinecap="round"
				/>
				<path
					d="M17.5 9L16 8L17.5 7"
					stroke="currentColor"
					strokeWidth="2"
					strokeLinecap="round"
					strokeLinejoin="round"
				/>
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
	deprecated,
});

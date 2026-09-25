/**
 * Fifty Fifty Block Registration
 *
 * Full-width 50/50 split with edge-to-edge media and constrained content.
 *
 * @since 1.5.0
 */

import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import save from './save';
import metadata from './block.json';
import transforms from './transforms';
import { ICON_COLOR } from '../shared/constants';

import './editor.scss';
import './style.scss';

/**
 * Register Fifty Fifty Block
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
				{/* Left half: media (filled rectangle) */}
				<rect
					x="3"
					y="4"
					width="8"
					height="16"
					rx="1"
					fill="currentColor"
				/>
				{/* Right half: content (outlined rectangle with text lines) */}
				<rect
					x="13"
					y="4"
					width="8"
					height="16"
					rx="1"
					stroke="currentColor"
					strokeWidth="1.5"
					fill="none"
				/>
				<line
					x1="15"
					y1="9"
					x2="19"
					y2="9"
					stroke="currentColor"
					strokeWidth="1.5"
				/>
				<line
					x1="15"
					y1="12"
					x2="19"
					y2="12"
					stroke="currentColor"
					strokeWidth="1.5"
				/>
				<line
					x1="15"
					y1="15"
					x2="18"
					y2="15"
					stroke="currentColor"
					strokeWidth="1.5"
				/>
			</svg>
		),
		foreground: ICON_COLOR,
	},
	transforms,
	edit,
	save,
});

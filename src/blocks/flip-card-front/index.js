/**
 * Flip Card Front Block Registration
 *
 * Deprecated in 2.0.52 in favour of designsetgo/flip-card-face. The block
 * stays registered with inserter:false so existing content keeps rendering;
 * the transforms.to entry lets editors one-click convert it to the new
 * consolidated block.
 *
 * @since 1.0.0
 */

import { registerBlockType, createBlock } from '@wordpress/blocks';

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
				width="24"
				height="24"
				viewBox="0 0 24 24"
				fill="none"
				xmlns="http://www.w3.org/2000/svg"
			>
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
				<circle cx="12" cy="12" r="3" fill="currentColor" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
	transforms: {
		to: [
			{
				type: 'block',
				blocks: ['designsetgo/flip-card-face'],
				transform: (attributes, innerBlocks) =>
					createBlock(
						'designsetgo/flip-card-face',
						{ ...attributes, side: 'front' },
						innerBlocks
					),
			},
		],
	},
});

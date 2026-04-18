/**
 * Flip Card Face Block Registration
 *
 * @since 2.0.52
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
	// Spread preserves core-managed attrs (style, className, color, etc.)
	// across the transform. flip-card-front / flip-card-back defined no
	// custom attributes, so this is safe today — if either legacy block ever
	// picks up a custom attribute, audit the spread before shipping.
	transforms: {
		from: [
			{
				type: 'block',
				blocks: ['designsetgo/flip-card-front'],
				transform: (attributes, innerBlocks) =>
					createBlock(
						'designsetgo/flip-card-face',
						{ ...attributes, side: 'front' },
						innerBlocks
					),
			},
			{
				type: 'block',
				blocks: ['designsetgo/flip-card-back'],
				transform: (attributes, innerBlocks) =>
					createBlock(
						'designsetgo/flip-card-face',
						{ ...attributes, side: 'back' },
						innerBlocks
					),
			},
		],
	},
});

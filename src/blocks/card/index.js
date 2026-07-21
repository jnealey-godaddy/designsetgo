/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import Edit from './edit';
import save from './save';
import deprecated from './deprecated';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';
import './style.scss';
import './editor.scss';

/**
 * Register: Card Block
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/
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
					x="3"
					y="4"
					width="18"
					height="16"
					rx="2"
					stroke="currentColor"
					strokeWidth="1.5"
				/>
				<rect
					x="6"
					y="7"
					width="5"
					height="4"
					rx="1"
					fill="currentColor"
				/>
				<rect
					x="6"
					y="13"
					width="12"
					height="1.5"
					rx="0.75"
					fill="currentColor"
				/>
				<rect
					x="6"
					y="16"
					width="8"
					height="1.5"
					rx="0.75"
					fill="currentColor"
				/>
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit: Edit,
	save,
	deprecated,
});

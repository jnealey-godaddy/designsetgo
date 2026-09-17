/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import Edit from './edit';
import Save from './save';
import deprecated from './deprecated';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

/**
 * Register the Scroll Accordion Item block.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType(metadata.name, {
	edit: Edit,
	save: Save,
	deprecated,
	icon: {
		src: (
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				fill="currentColor"
			>
				<rect
					x="4"
					y="6"
					width="16"
					height="12"
					rx="2"
					fill="none"
					stroke="currentColor"
					strokeWidth="2"
				/>
				<path
					d="M7 10h10M7 14h7"
					stroke="currentColor"
					strokeWidth="1.5"
					strokeLinecap="round"
				/>
			</svg>
		),
		foreground: ICON_COLOR,
	},
});

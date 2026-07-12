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
 * Register the Scroll Accordion block.
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
				<path d="M4 4h16v2H4V4zm0 5h16v2H4V9zm0 5h16v2H4v-2zm0 5h16v2H4v-2z" />
				<path d="M22 12l-4-4v8l4-4z" opacity="0.5" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
});

/**
 * Counter Block - Registration
 *
 * Registers the Counter block with WordPress.
 * Edit component extracted to edit.js for better maintainability.
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
 * Register Counter Block
 *
 * Individual counter item within a Counter Group block.
 * Displays an animated counting number with optional icon and label.
 */
registerBlockType(metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				fill="none"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
			>
				<path d="M4 8l4 4-4 4" />
				<line x1="12" y1="4" x2="10" y2="20" />
				<path d="M20 8l-4 4 4 4" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
	deprecated,
});

/**
 * Modal Block
 *
 * Creates accessible modal dialogs with customizable triggers and content.
 *
 * @package
 */

import { registerBlockType } from '@wordpress/blocks';

import './style.scss';
import './editor.scss';

import Edit from './edit';
import save from './save';
import deprecated from './deprecated';
import metadata from './block.json';
import variations from './variations';
import { ICON_COLOR } from '../shared/constants';

/**
 * Register the Modal block.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType(metadata.name, {
	...metadata,
	icon: {
		src: 'feedback',
		foreground: ICON_COLOR,
	},
	edit: Edit,
	save,
	deprecated,
	variations,
});

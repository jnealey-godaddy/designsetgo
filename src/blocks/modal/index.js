/**
 * Modal Block
 *
 * Creates accessible modal dialogs with customizable triggers and content.
 *
 * @package
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

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
	// Keep the one-block variation model while giving editor controls and
	// assistive technology the name authors selected in the inserter.
	__experimentalLabel: ({ displayMode }) =>
		displayMode === 'panel'
			? __('Off-Canvas Panel', 'designsetgo')
			: undefined,
});

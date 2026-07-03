/**
 * Form Checkbox Field Block - Registration
 *
 * @since 1.0.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { check as icon } from '@wordpress/icons';

import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import deprecated from './deprecated';
import { ICON_COLOR } from '../shared/constants';

import './style.scss';
import './editor.scss';

registerBlockType(metadata.name, {
	icon: {
		src: icon,
		foreground: ICON_COLOR,
	},
	edit: Edit,
	save: Save,
	deprecated,
});

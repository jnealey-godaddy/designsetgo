/**
 * Icon Button Block
 *
 * A button with optional icon at the start or end.
 * Leverages the icon library from the Icon block.
 *
 * @since 1.0.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { button as icon } from '@wordpress/icons';

import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

// Mirror core Button's style variations (Fill, Outline, …) onto Icon Button.
import './mirror-button-styles';

import './editor.scss';
import './style.scss';

/**
 * Register Icon Button Block
 */
registerBlockType(metadata.name, {
	...metadata,
	icon: {
		src: icon,
		foreground: ICON_COLOR,
	},
	edit,
	save,
	transforms,
	deprecated,
});

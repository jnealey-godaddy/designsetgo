import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import './style.scss';
import './editor.scss';

registerBlockType(metadata.name, {
	...metadata,
	edit,
	save,
	deprecated,
});

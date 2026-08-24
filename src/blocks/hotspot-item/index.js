import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import deprecated from './deprecated';
import edit from './edit';
import save from './save';
import './style.scss';
import './editor.scss';

registerBlockType(metadata.name, { ...metadata, deprecated, edit, save });

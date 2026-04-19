/**
 * Query Filter block — registration.
 *
 * @since 2.1.0
 */
import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';

import edit from './edit';
import save from './save';
import metadata from './block.json';
import variations from './variations';

import './editor.scss';
import './style.scss';

registerBlockType( metadata.name, {
	...metadata,
	edit,
	save,
} );

// Register the 6 filter variations.
variations.forEach( ( variation ) => {
	registerBlockVariation( metadata.name, variation );
} );

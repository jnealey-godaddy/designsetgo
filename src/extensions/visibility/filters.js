import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { Fragment } from '@wordpress/element';
import VisibilityPanel from './VisibilityPanel';

const BLOCKED = new Set( [
	'core/freeform',
	'core/missing',
	'core/template-part',
] );

function addVisibilityAttribute( settings, name ) {
	if ( BLOCKED.has( name ) ) return settings;
	return {
		...settings,
		attributes: {
			...( settings.attributes ?? {} ),
			dsgoVisibility: { type: 'object', default: null },
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/visibility/add-attribute',
	addVisibilityAttribute
);

const withVisibilityPanel = createHigherOrderComponent( ( BlockEdit ) => ( props ) => {
	if ( BLOCKED.has( props.name ) ) return <BlockEdit { ...props } />;
	return (
		<Fragment>
			<BlockEdit { ...props } />
			<InspectorControls group="advanced">
				<VisibilityPanel
					value={ props.attributes.dsgoVisibility }
					onChange={ ( value ) => props.setAttributes( { dsgoVisibility: value } ) }
				/>
			</InspectorControls>
		</Fragment>
	);
}, 'withDsgoVisibilityPanel' );

addFilter(
	'editor.BlockEdit',
	'designsetgo/visibility/inspector',
	withVisibilityPanel
);

import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	Button,
	SelectControl,
	TextControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';

const BLOCKED = new Set( [ 'core/freeform', 'core/missing', 'core/template-part' ] );

const SOURCE_OPTIONS = [
	{ label: __( 'Post meta', 'designsetgo' ), value: 'designsetgo/post-meta' },
	{ label: __( 'ACF', 'designsetgo' ), value: 'designsetgo/acf' },
	{ label: __( 'Meta Box', 'designsetgo' ), value: 'designsetgo/metabox' },
	{ label: __( 'Pods', 'designsetgo' ), value: 'designsetgo/pods' },
	{ label: __( 'JetEngine', 'designsetgo' ), value: 'designsetgo/jetengine' },
];

addFilter(
	'blocks.registerBlockType',
	'designsetgo/style-binding-attribute',
	( settings ) => {
		if ( BLOCKED.has( settings.name ) ) {
			return settings;
		}
		if ( ! settings.attributes ) {
			settings.attributes = {};
		}
		settings.attributes.dsgoStyleBinding = {
			type: 'object',
			default: {},
		};
		return settings;
	}
);

const withStyleBindingInspector = createHigherOrderComponent( ( BlockEdit ) => {
	return function WithStyleBindingInspector( props ) {
		if ( BLOCKED.has( props.name ) ) {
			return <BlockEdit { ...props } />;
		}
		const { attributes, setAttributes } = props;
		const binding = attributes.dsgoStyleBinding ?? {};
		const entries = Object.entries( binding );

		const updateEntry = ( oldProp, newProp, config ) => {
			const next = { ...binding };
			if ( oldProp !== newProp ) {
				delete next[ oldProp ];
			}
			next[ newProp ] = config;
			setAttributes( { dsgoStyleBinding: next } );
		};

		const removeEntry = ( prop ) => {
			const next = { ...binding };
			delete next[ prop ];
			setAttributes( { dsgoStyleBinding: next } );
		};

		const addEntry = () => {
			const key = `--dsgo-binding-${ Date.now() }`;
			setAttributes( {
				dsgoStyleBinding: {
					...binding,
					[ key ]: { source: 'designsetgo/post-meta', args: { key: '' } },
				},
			} );
		};

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls group="advanced">
					<PanelBody
						title={ __( 'Style Bindings', 'designsetgo' ) }
						initialOpen={ entries.length > 0 }
					>
						{ entries.map( ( [ prop, config ] ) => (
							<VStack key={ prop } spacing={ 1 } style={ { marginBottom: '12px' } }>
								<HStack>
									<TextControl
										label={ __( 'CSS property', 'designsetgo' ) }
										value={ prop }
										placeholder="--brand-color"
										onChange={ ( val ) => updateEntry( prop, val, config ) }
										__nextHasNoMarginBottom
									/>
									<Button
										variant="tertiary"
										isDestructive
										size="small"
										onClick={ () => removeEntry( prop ) }
										style={ { alignSelf: 'flex-end' } }
									>
										{ __( 'Remove', 'designsetgo' ) }
									</Button>
								</HStack>
								<SelectControl
									label={ __( 'Source', 'designsetgo' ) }
									value={ config.source }
									options={ SOURCE_OPTIONS }
									onChange={ ( val ) => updateEntry( prop, prop, { ...config, source: val } ) }
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={ __( 'Field key / name', 'designsetgo' ) }
									value={ config.args?.key ?? config.args?.name ?? config.args?.id ?? config.args?.field ?? '' }
									onChange={ ( val ) => {
										const argKey = [ 'designsetgo/acf' ].includes( config.source ) ? 'name'
											: [ 'designsetgo/pods' ].includes( config.source ) ? 'field'
											: 'key';
										updateEntry( prop, prop, { ...config, args: { [ argKey ]: val } } );
									} }
									__nextHasNoMarginBottom
								/>
							</VStack>
						) ) }
						<Button variant="secondary" size="small" onClick={ addEntry } __next40pxDefaultSize>
							{ __( '+ Add style binding', 'designsetgo' ) }
						</Button>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withStyleBindingInspector' );

addFilter(
	'editor.BlockEdit',
	'designsetgo/style-binding-inspector',
	withStyleBindingInspector
);

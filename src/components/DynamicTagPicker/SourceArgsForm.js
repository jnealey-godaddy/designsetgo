/**
 * SourceArgsForm — argument inputs for a selected Dynamic Tag source.
 *
 * Renders one control per declared arg in the source's `args` schema.
 * Sources that opt into field discovery (ACF, post-meta, …) get a
 * combined Select + TextControl for the `key` argument so authors can
 * pick from discovered fields or type a key manually.
 */
import {
	SelectControl,
	TextControl,
	Spinner,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const ARG_LABEL_MAP = {
	key: __( 'Field key', 'designsetgo' ),
	subkey: __( 'Sub-value', 'designsetgo' ),
	size: __( 'Image size', 'designsetgo' ),
	scope: __( 'Scope', 'designsetgo' ),
	format: __( 'Date format', 'designsetgo' ),
	taxonomy: __( 'Taxonomy', 'designsetgo' ),
	separator: __( 'Separator', 'designsetgo' ),
};

function argLabel( name ) {
	return ARG_LABEL_MAP[ name ] || name;
}

export default function SourceArgsForm( { source, args, onChange, fieldDiscovery } ) {
	const schema = source.args || {};
	const entries = Object.entries( schema );

	if ( entries.length === 0 ) {
		return null;
	}

	const setArg = ( key, value ) => {
		const next = { ...args };
		if ( value === '' || value === undefined || value === null ) {
			delete next[ key ];
		} else {
			next[ key ] = value;
		}
		onChange( next );
	};

	return (
		<VStack spacing={ 3 }>
			{ entries.map( ( [ argName, argSchema ] ) => {
				if ( argName === 'key' && source.supportsFieldDiscovery ) {
					const fieldOptions = [
						{ label: __( '— Select a field —', 'designsetgo' ), value: '' },
						...fieldDiscovery.fields.map( ( f ) => ( {
							label: f.group ? `${ f.group } — ${ f.label }` : f.label,
							value: f.key,
						} ) ),
					];
					return (
						<div key={ argName }>
							{ fieldDiscovery.status === 'loading' ? (
								<Spinner />
							) : (
								<SelectControl
									label={ __( 'Field', 'designsetgo' ) }
									value={ args[ argName ] || '' }
									options={ fieldOptions }
									onChange={ ( value ) => setArg( argName, value ) }
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							) }
							<TextControl
								label={ __( 'Or enter a field key manually', 'designsetgo' ) }
								value={ args[ argName ] || '' }
								onChange={ ( value ) => setArg( argName, value ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</div>
					);
				}

				if ( Array.isArray( argSchema.enum ) && argSchema.enum.length > 0 ) {
					return (
						<SelectControl
							key={ argName }
							label={ argLabel( argName ) }
							value={ args[ argName ] ?? argSchema.default ?? '' }
							options={ [
								{ label: __( '— Default —', 'designsetgo' ), value: '' },
								...argSchema.enum.map( ( v ) => ( { label: v, value: v } ) ),
							] }
							onChange={ ( value ) => setArg( argName, value ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					);
				}

				return (
					<TextControl
						key={ argName }
						label={ argLabel( argName ) }
						help={ argSchema.description || '' }
						value={ args[ argName ] ?? '' }
						onChange={ ( value ) => setArg( argName, value ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				);
			} ) }
		</VStack>
	);
}

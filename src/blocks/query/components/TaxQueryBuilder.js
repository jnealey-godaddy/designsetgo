/* eslint-disable @wordpress/no-unsafe-wp-apis -- experimental layout/control primitives intentionally used; stable replacements not yet available */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import {
	Button,
	SelectControl,
	ToggleControl,
	FormTokenField,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';
import ClauseGroupShell from './ClauseGroupShell';

const OPERATORS = [
	{ value: 'IN', label: __( 'In', 'designsetgo' ) },
	{ value: 'NOT IN', label: __( 'Not in', 'designsetgo' ) },
	{ value: 'AND', label: __( 'All of', 'designsetgo' ) },
];

export default function TaxQueryBuilder( {
	attributes,
	setAttributes,
	clientId,
} ) {
	const { postType, taxQuery } = attributes;

	const taxonomies = useSelect(
		( select ) => select( coreStore ).getTaxonomies( { per_page: -1 } ) || [],
		[]
	);
	const relevant = ( taxonomies || [] ).filter(
		( t ) => t && t.types && t.types.includes( postType )
	);

	return (
		<DsgoInspectorPanel
			title={ __( 'Settings', 'designsetgo' ) }
			panelName="settings"
			panelId={ clientId }
			resetAll={ () =>
				setAttributes( { taxQuery: { relation: 'AND', clauses: [] } } )
			}
		>
			<DsgoInspectorPanel.Item
				label={ __( 'Taxonomy filters', 'designsetgo' ) }
				hasValue={ () => taxQuery.clauses.length > 0 }
				onDeselect={ () =>
					setAttributes( { taxQuery: { relation: 'AND', clauses: [] } } )
				}
				isShownByDefault
			>
				<ClauseGroupShell
					group={ taxQuery }
					onChange={ ( patch ) =>
						setAttributes( { taxQuery: { ...taxQuery, ...patch } } )
					}
					depth={ 0 }
					isAddDisabled={ relevant.length === 0 }
					newClause={ {
						taxonomy: relevant[ 0 ]?.slug ?? 'category',
						terms: [],
						operator: 'IN',
						include_children: true,
					} }
					renderClause={ ( clause, idx, updateEntry, removeEntry ) => (
						<VStack key={ idx } spacing={ 2 } className="dsgo-query-tax-clause">
							<SelectControl
								label={ __( 'Taxonomy', 'designsetgo' ) }
								value={ clause.taxonomy }
								options={ relevant.map( ( t ) => ( {
									label: t.labels?.singular_name || t.slug,
									value: t.slug,
								} ) ) }
								onChange={ ( val ) =>
									updateEntry( idx, { taxonomy: val, terms: [] } )
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<TermPicker
								taxonomy={ clause.taxonomy }
								selected={ clause.terms }
								onChange={ ( ids ) => updateEntry( idx, { terms: ids } ) }
							/>
							<HStack>
								<SelectControl
									label={ __( 'Operator', 'designsetgo' ) }
									value={ clause.operator || 'IN' }
									options={ OPERATORS }
									onChange={ ( val ) =>
										updateEntry( idx, { operator: val } )
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<Button
									isDestructive
									variant="tertiary"
									onClick={ () => removeEntry( idx ) }
									aria-label={ __(
										'Remove taxonomy filter',
										'designsetgo'
									) }
									__next40pxDefaultSize
								>
									{ __( 'Remove', 'designsetgo' ) }
								</Button>
							</HStack>
							<ToggleControl
								label={ __( 'Include child terms', 'designsetgo' ) }
								checked={ clause.include_children ?? true }
								onChange={ ( val ) =>
									updateEntry( idx, { include_children: val } )
								}
								__nextHasNoMarginBottom
							/>
						</VStack>
					) }
				/>
			</DsgoInspectorPanel.Item>
		</DsgoInspectorPanel>
	);
}

function TermPicker( { taxonomy, selected, onChange } ) {
	const terms = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecords( 'taxonomy', taxonomy, {
				per_page: -1,
			} ) || [],
		[ taxonomy ]
	);
	const suggestions = ( terms || [] ).map( ( t ) => t.name );
	const selectedNames = ( terms || [] )
		.filter( ( t ) => selected.includes( t.id ) )
		.map( ( t ) => t.name );

	return (
		<FormTokenField
			label={ __( 'Terms', 'designsetgo' ) }
			value={ selectedNames }
			suggestions={ suggestions }
			onChange={ ( names ) => {
				const ids = ( terms || [] )
					.filter( ( t ) => names.includes( t.name ) )
					.map( ( t ) => t.id );
				onChange( ids );
			} }
			__experimentalExpandOnFocus
			__nextHasNoMarginBottom
		/>
	);
}

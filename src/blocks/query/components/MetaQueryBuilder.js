import { __ } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	TextControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

const COMPARE_OPTIONS = [
	{ value: '=',          label: '=' },
	{ value: '!=',         label: '!=' },
	{ value: '>',          label: '>' },
	{ value: '>=',         label: '>=' },
	{ value: '<',          label: '<' },
	{ value: '<=',         label: '<=' },
	{ value: 'LIKE',       label: 'LIKE' },
	{ value: 'NOT LIKE',   label: 'NOT LIKE' },
	{ value: 'IN',         label: 'IN' },
	{ value: 'NOT IN',     label: 'NOT IN' },
	{ value: 'EXISTS',     label: 'EXISTS' },
	{ value: 'NOT EXISTS', label: 'NOT EXISTS' },
];

const TYPE_OPTIONS = [
	{ value: 'CHAR',    label: __( 'Text', 'designsetgo' ) },
	{ value: 'NUMERIC', label: __( 'Numeric', 'designsetgo' ) },
	{ value: 'DATE',    label: __( 'Date', 'designsetgo' ) },
];

const EMPTY_DEFAULT = { relation: 'AND', clauses: [] };

export default function MetaQueryBuilder( { attributes, setAttributes, clientId } ) {
	const { metaQuery } = attributes;

	const updateClause = ( i, patch ) => {
		const next = [ ...metaQuery.clauses ];
		next[ i ] = { ...next[ i ], ...patch };
		setAttributes( { metaQuery: { ...metaQuery, clauses: next } } );
	};

	const removeClause = ( i ) => {
		setAttributes( {
			metaQuery: {
				...metaQuery,
				clauses: metaQuery.clauses.filter( ( _, idx ) => idx !== i ),
			},
		} );
	};

	const addClause = () => {
		setAttributes( {
			metaQuery: {
				...metaQuery,
				clauses: [
					...metaQuery.clauses,
					{ key: '', compare: '=', value: '', type: 'CHAR' },
				],
			},
		} );
	};

	return (
		<DsgoInspectorPanel
			title={ __( 'Meta query', 'designsetgo' ) }
			panelName="settings"
			panelId={ clientId }
			resetAll={ () => setAttributes( { metaQuery: EMPTY_DEFAULT } ) }
		>
			<DsgoInspectorPanel.Item
				label={ __( 'Meta query', 'designsetgo' ) }
				hasValue={ () => metaQuery.clauses.length > 0 }
				onDeselect={ () => setAttributes( { metaQuery: EMPTY_DEFAULT } ) }
				isShownByDefault
			>
				<VStack spacing={ 3 }>
					{ metaQuery.clauses.length > 1 && (
						<SelectControl
							label={ __( 'Relation', 'designsetgo' ) }
							value={ metaQuery.relation }
							options={ [
								{ value: 'AND', label: 'AND' },
								{ value: 'OR',  label: 'OR' },
							] }
							onChange={ ( v ) =>
								setAttributes( { metaQuery: { ...metaQuery, relation: v } } )
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					) }

					{ metaQuery.clauses.map( ( clause, i ) => {
						const hideValue =
							clause.compare === 'EXISTS' || clause.compare === 'NOT EXISTS';
						return (
							<VStack key={ i } spacing={ 2 }>
								<TextControl
									label={ __( 'Key', 'designsetgo' ) }
									value={ clause.key }
									onChange={ ( v ) => updateClause( i, { key: v } ) }
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<HStack>
									<SelectControl
										label={ __( 'Compare', 'designsetgo' ) }
										value={ clause.compare }
										options={ COMPARE_OPTIONS }
										onChange={ ( v ) => updateClause( i, { compare: v } ) }
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
									<SelectControl
										label={ __( 'Type', 'designsetgo' ) }
										value={ clause.type }
										options={ TYPE_OPTIONS }
										onChange={ ( v ) => updateClause( i, { type: v } ) }
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
								</HStack>
								{ ! hideValue && (
									<TextControl
										label={ __( 'Value', 'designsetgo' ) }
										value={ clause.value }
										onChange={ ( v ) => updateClause( i, { value: v } ) }
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
								) }
								<Button
									isDestructive
									variant="tertiary"
									onClick={ () => removeClause( i ) }
									aria-label={ __( 'Remove meta condition', 'designsetgo' ) }
								>
									{ __( 'Remove', 'designsetgo' ) }
								</Button>
							</VStack>
						);
					} ) }

					<Button variant="secondary" onClick={ addClause }>
						{ __( 'Add meta condition', 'designsetgo' ) }
					</Button>
				</VStack>
			</DsgoInspectorPanel.Item>
		</DsgoInspectorPanel>
	);
}

/**
 * Query Filter — block editor.
 *
 * Inspector controls vary by filterKind. Canvas preview shows a static
 * representation so the block is recognisable in the editor.
 *
 * @since 2.1.0
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { SelectControl, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { DsgoInspectorPanel } from '../../components/shared';
import FilterPreview from './components/FilterPreview';

const FILTER_KIND_OPTIONS = [
	{ value: 'checkbox', label: __( 'Taxonomy checkboxes', 'designsetgo' ) },
	{ value: 'select',   label: __( 'Taxonomy dropdown', 'designsetgo' ) },
	{ value: 'search',   label: __( 'Search input', 'designsetgo' ) },
	{ value: 'sort',     label: __( 'Sort dropdown', 'designsetgo' ) },
	{ value: 'active',   label: __( 'Active filters', 'designsetgo' ) },
	{ value: 'reset',    label: __( 'Reset button', 'designsetgo' ) },
];

const DEFAULTS = {
	filterKind: 'checkbox',
	taxonomy:   'category',
	paramName:  'filter_category',
	label:      '',
	placeholder: '',
};

export default function QueryFilterEdit( {
	attributes,
	setAttributes,
	clientId,
} ) {
	const {
		filterKind,
		taxonomy,
		paramName,
		label,
		placeholder,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'dsgo-query-filter is-editor',
	} );

	// Load available taxonomies for the checkbox/select filter kinds.
	const taxonomies = useSelect( ( select ) => {
		if ( filterKind !== 'checkbox' && filterKind !== 'select' ) {
			return [];
		}
		const all = select( coreStore ).getTaxonomies( { per_page: -1 } ) || [];
		return all
			.filter( ( t ) => t.show_in_rest !== false )
			.map( ( t ) => ( { value: t.slug, label: t.name } ) );
	}, [ filterKind ] );

	const showTaxonomyControl =
		filterKind === 'checkbox' || filterKind === 'select';
	const showLabelControl  = true; // all kinds
	const showSearchControl = filterKind === 'search';

	function handleTaxonomyChange( slug ) {
		setAttributes( {
			taxonomy: slug,
			paramName: `filter_${ slug }`,
		} );
	}

	// Sensible default paramName per filterKind so switching kinds never
	// leaves a stale `filter_category` on a search/sort/etc variation.
	function defaultParamNameForKind( kind, tax ) {
		switch ( kind ) {
			case 'search':
				return 'q';
			case 'sort':
				return 'sort';
			case 'active':
			case 'reset':
				return '';
			case 'checkbox':
			case 'select':
			default:
				return `filter_${ tax || 'category' }`;
		}
	}

	function handleFilterKindChange( nextKind ) {
		setAttributes( {
			filterKind: nextKind,
			paramName: defaultParamNameForKind( nextKind, taxonomy ),
		} );
	}

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={ __( 'Settings', 'designsetgo' ) }
					panelName="settings"
					panelId={ clientId }
					resetAll={ () => setAttributes( DEFAULTS ) }
				>
					<DsgoInspectorPanel.Item
						label={ __( 'Filter type', 'designsetgo' ) }
						hasValue={ () => filterKind !== 'checkbox' }
						onDeselect={ () =>
							setAttributes( {
								filterKind: 'checkbox',
								paramName:  'filter_category',
								taxonomy:   'category',
							} )
						}
						isShownByDefault
					>
						<SelectControl
							label={ __( 'Filter type', 'designsetgo' ) }
							value={ filterKind }
							options={ FILTER_KIND_OPTIONS }
							onChange={ handleFilterKindChange }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{ showTaxonomyControl && taxonomies.length > 0 && (
						<DsgoInspectorPanel.Item
							label={ __( 'Taxonomy', 'designsetgo' ) }
							hasValue={ () => taxonomy !== 'category' }
							onDeselect={ () =>
								handleTaxonomyChange( 'category' )
							}
							isShownByDefault
						>
							<SelectControl
								label={ __( 'Taxonomy', 'designsetgo' ) }
								value={ taxonomy }
								options={ taxonomies }
								onChange={ handleTaxonomyChange }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					) }

					{ showTaxonomyControl && (
						<DsgoInspectorPanel.Item
							label={ __( 'URL parameter name', 'designsetgo' ) }
							hasValue={ () =>
								paramName !== `filter_${ taxonomy }`
							}
							onDeselect={ () =>
								setAttributes( {
									paramName: `filter_${ taxonomy }`,
								} )
							}
						>
							<TextControl
								label={ __(
									'URL parameter name',
									'designsetgo'
								) }
								value={ paramName }
								onChange={ ( v ) =>
									setAttributes( { paramName: v } )
								}
								help={ __(
									'e.g. filter_category, filter_tag',
									'designsetgo'
								) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					) }

					{ showLabelControl && (
						<DsgoInspectorPanel.Item
							label={ __( 'Label', 'designsetgo' ) }
							hasValue={ () => label !== '' }
							onDeselect={ () =>
								setAttributes( { label: '' } )
							}
						>
							<TextControl
								label={ __( 'Label', 'designsetgo' ) }
								value={ label }
								onChange={ ( v ) =>
									setAttributes( { label: v } )
								}
								placeholder={ __(
									'Optional label\u2026',
									'designsetgo'
								) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					) }

					{ showSearchControl && (
						<DsgoInspectorPanel.Item
							label={ __( 'Placeholder text', 'designsetgo' ) }
							hasValue={ () => placeholder !== '' }
							onDeselect={ () =>
								setAttributes( { placeholder: '' } )
							}
						>
							<TextControl
								label={ __(
									'Placeholder text',
									'designsetgo'
								) }
								value={ placeholder }
								onChange={ ( v ) =>
									setAttributes( { placeholder: v } )
								}
								placeholder={ __(
									'Search\u2026',
									'designsetgo'
								) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					) }
				</DsgoInspectorPanel>
			</InspectorControls>

			<div { ...blockProps }>
				<FilterPreview
					filterKind={ filterKind }
					label={ label }
					placeholder={ placeholder }
				/>
			</div>
		</>
	);
}

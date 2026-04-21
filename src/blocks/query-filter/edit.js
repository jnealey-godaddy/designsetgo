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
import {
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { store as coreStore } from '@wordpress/core-data';
import { DsgoInspectorPanel } from '../../components/shared';
import FilterPreview from './components/FilterPreview';

// Stable references so useSelect doesn't flag "returns different values when
// called with the same state" on every consumer render.
const EMPTY_TAXONOMIES = Object.freeze([]);

const FILTER_KIND_OPTIONS = [
	{ value: 'checkbox', label: __('Taxonomy checkboxes', 'designsetgo') },
	{ value: 'select', label: __('Taxonomy dropdown', 'designsetgo') },
	{ value: 'search', label: __('Search input', 'designsetgo') },
	{ value: 'sort', label: __('Sort dropdown', 'designsetgo') },
	{ value: 'active', label: __('Active filters', 'designsetgo') },
	{ value: 'reset', label: __('Reset button', 'designsetgo') },
];

const DEFAULTS = {
	filterKind: 'checkbox',
	taxonomy: 'category',
	paramName: 'filter_category',
	label: '',
	placeholder: '',
	showCounts: true,
	orientation: 'vertical',
};

const ORIENTATION_OPTIONS = [
	{ value: 'vertical', label: __('Vertical', 'designsetgo') },
	{ value: 'horizontal', label: __('Horizontal', 'designsetgo') },
];

export default function QueryFilterEdit({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		filterKind,
		taxonomy,
		paramName,
		label,
		placeholder,
		showCounts,
		orientation,
	} = attributes;

	const blockProps = useBlockProps({
		className: 'dsgo-query-filter is-editor',
	});

	// Return the raw taxonomies array from core-data — core-data caches by
	// query args and returns a ref-stable array, so useSelect's shallow
	// equality stays happy across unrelated store updates.
	const rawTaxonomies = useSelect(
		(select) =>
			select(coreStore).getTaxonomies({ per_page: -1 }) ||
			EMPTY_TAXONOMIES,
		[]
	);

	// Transform into SelectControl option shape in a useMemo so the mapped
	// array keeps the same identity until raw data or filterKind changes.
	const taxonomies = useMemo(() => {
		if (filterKind !== 'checkbox' && filterKind !== 'select') {
			return EMPTY_TAXONOMIES;
		}
		return rawTaxonomies
			.filter((t) => t.show_in_rest !== false)
			.map((t) => ({ value: t.slug, label: t.name }));
	}, [filterKind, rawTaxonomies]);

	const showTaxonomyControl =
		filterKind === 'checkbox' || filterKind === 'select';
	const showLabelControl = true; // all kinds
	const showSearchControl = filterKind === 'search';
	// Show counts toggle only for taxonomy-backed filter kinds.
	const showCountsControl =
		filterKind === 'checkbox' || filterKind === 'select';

	function handleTaxonomyChange(slug) {
		setAttributes({
			taxonomy: slug,
			paramName: `filter_${slug}`,
		});
	}

	// Sensible default paramName per filterKind so switching kinds never
	// leaves a stale `filter_category` on a search/sort/etc variation.
	function defaultParamNameForKind(kind, tax) {
		switch (kind) {
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
				return `filter_${tax || 'category'}`;
		}
	}

	function handleFilterKindChange(nextKind) {
		setAttributes({
			filterKind: nextKind,
			paramName: defaultParamNameForKind(nextKind, taxonomy),
		});
	}

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() => setAttributes(DEFAULTS)}
				>
					<DsgoInspectorPanel.Item
						label={__('Filter type', 'designsetgo')}
						hasValue={() => filterKind !== 'checkbox'}
						onDeselect={() =>
							setAttributes({
								filterKind: 'checkbox',
								paramName: 'filter_category',
								taxonomy: 'category',
							})
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Filter type', 'designsetgo')}
							value={filterKind}
							options={FILTER_KIND_OPTIONS}
							onChange={handleFilterKindChange}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{showTaxonomyControl && taxonomies.length > 0 && (
						<DsgoInspectorPanel.Item
							label={__('Taxonomy', 'designsetgo')}
							hasValue={() => taxonomy !== 'category'}
							onDeselect={() => handleTaxonomyChange('category')}
							isShownByDefault
						>
							<SelectControl
								label={__('Taxonomy', 'designsetgo')}
								value={taxonomy}
								options={taxonomies}
								onChange={handleTaxonomyChange}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					{showTaxonomyControl && (
						<DsgoInspectorPanel.Item
							label={__('URL parameter name', 'designsetgo')}
							hasValue={() => paramName !== `filter_${taxonomy}`}
							onDeselect={() =>
								setAttributes({
									paramName: `filter_${taxonomy}`,
								})
							}
						>
							<TextControl
								label={__('URL parameter name', 'designsetgo')}
								value={paramName}
								onChange={(v) =>
									setAttributes({ paramName: v })
								}
								help={__(
									'e.g. filter_category, filter_tag',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					{showLabelControl && (
						<DsgoInspectorPanel.Item
							label={__('Label', 'designsetgo')}
							hasValue={() => label !== ''}
							onDeselect={() => setAttributes({ label: '' })}
						>
							<TextControl
								label={__('Label', 'designsetgo')}
								value={label}
								onChange={(v) => setAttributes({ label: v })}
								placeholder={__(
									'Optional label\u2026',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					{showSearchControl && (
						<DsgoInspectorPanel.Item
							label={__('Placeholder text', 'designsetgo')}
							hasValue={() => placeholder !== ''}
							onDeselect={() =>
								setAttributes({ placeholder: '' })
							}
						>
							<TextControl
								label={__('Placeholder text', 'designsetgo')}
								value={placeholder}
								onChange={(v) =>
									setAttributes({ placeholder: v })
								}
								placeholder={__('Search\u2026', 'designsetgo')}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					{showCountsControl && (
						<DsgoInspectorPanel.Item
							label={__(
								'Show counts next to options',
								'designsetgo'
							)}
							hasValue={() => showCounts !== true}
							onDeselect={() =>
								setAttributes({ showCounts: true })
							}
							isShownByDefault
						>
							<ToggleControl
								label={__(
									'Show counts next to options',
									'designsetgo'
								)}
								checked={showCounts}
								onChange={(v) =>
									setAttributes({ showCounts: v })
								}
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					{filterKind === 'checkbox' && (
						<DsgoInspectorPanel.Item
							label={__('Orientation', 'designsetgo')}
							hasValue={() => orientation !== 'vertical'}
							onDeselect={() =>
								setAttributes({ orientation: 'vertical' })
							}
							isShownByDefault
						>
							<SelectControl
								label={__('Orientation', 'designsetgo')}
								value={orientation || 'vertical'}
								options={ORIENTATION_OPTIONS}
								onChange={(v) =>
									setAttributes({ orientation: v })
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			<div {...blockProps}>
				<FilterPreview
					filterKind={filterKind}
					label={label}
					placeholder={placeholder}
					orientation={orientation || 'vertical'}
				/>
			</div>
		</>
	);
}

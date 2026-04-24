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
import { store as coreStore, useEntityRecords } from '@wordpress/core-data';
import { DsgoInspectorPanel } from '../../components/shared';
import FilterPreview from './components/FilterPreview';

// Stable references so useSelect doesn't flag "returns different values when
// called with the same state" on every consumer render.
const EMPTY_TAXONOMIES = Object.freeze([]);

const FILTER_KIND_OPTIONS = [
	{ value: 'checkbox', label: __('Taxonomy (multi-select)', 'designsetgo') },
	{ value: 'select', label: __('Taxonomy (dropdown)', 'designsetgo') },
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
	filterStyle: 'underline',
};

const ORIENTATION_OPTIONS = [
	{ value: 'vertical', label: __('Vertical', 'designsetgo') },
	{ value: 'horizontal', label: __('Horizontal', 'designsetgo') },
];

const FILTER_STYLE_OPTIONS = [
	{ value: 'default', label: __('Checkboxes', 'designsetgo') },
	{ value: 'pill', label: __('Pills', 'designsetgo') },
	{ value: 'underline', label: __('Underlined tabs', 'designsetgo') },
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
		filterStyle,
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

	// Pull the real taxonomy terms so the editor preview shows the same
	// options visitors will see, not placeholder strings. Gated on taxonomy
	// filter kinds so we don't fire the REST request for search/sort/etc.
	// `useEntityRecords` must still run every render (rules of hooks), so
	// we call it unconditionally with an empty taxonomy slug when disabled —
	// core-data returns null records and never issues a request.
	const isTaxonomyKind = filterKind === 'checkbox' || filterKind === 'select';
	const termQuery = useMemo(
		() => ({ per_page: 20, hide_empty: false }),
		[]
	);
	const termsResult = useEntityRecords(
		'taxonomy',
		isTaxonomyKind ? taxonomy || 'category' : '',
		termQuery
	);
	const previewTerms = useMemo(() => {
		if (!isTaxonomyKind || !termsResult.records) {
			return null;
		}
		return termsResult.records.map((term) => ({
			id: term.id,
			slug: term.slug,
			name: term.name,
		}));
	}, [isTaxonomyKind, termsResult.records]);

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
		const next = {
			filterKind: nextKind,
			paramName: defaultParamNameForKind(nextKind, taxonomy),
		};
		// Seed a sensible default button label when switching to Reset so
		// newly-inserted reset filters don't render as a bare "Reset filters"
		// fallback. Skip when the author has already typed their own label so
		// we never overwrite intentional copy.
		if (nextKind === 'reset' && !label) {
			next.label = __('Reset', 'designsetgo');
		}
		setAttributes(next);
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
						<>
							<DsgoInspectorPanel.Item
								label={__('Style', 'designsetgo')}
								hasValue={() =>
									(filterStyle || 'underline') !== 'underline'
								}
								onDeselect={() =>
									setAttributes({ filterStyle: 'underline' })
								}
								isShownByDefault
							>
								<SelectControl
									label={__('Style', 'designsetgo')}
									value={filterStyle || 'underline'}
									options={FILTER_STYLE_OPTIONS}
									onChange={(v) =>
										setAttributes({ filterStyle: v })
									}
									help={__(
										'Underlined tabs (default) and pills render the filter as a modern horizontal selector. Switch to Checkboxes for a classic multi-select list. The underlying input stays accessible to keyboard + screen-reader users.',
										'designsetgo'
									)}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</DsgoInspectorPanel.Item>
							{(filterStyle || 'underline') === 'default' && (
								<DsgoInspectorPanel.Item
									label={__('Orientation', 'designsetgo')}
									hasValue={() => orientation !== 'vertical'}
									onDeselect={() =>
										setAttributes({
											orientation: 'vertical',
										})
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
						</>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			<div {...blockProps}>
				<FilterPreview
					filterKind={filterKind}
					label={label}
					placeholder={placeholder}
					orientation={orientation || 'vertical'}
					filterStyle={filterStyle || 'underline'}
					terms={previewTerms}
					termsLoading={
						isTaxonomyKind && !termsResult.hasResolved
					}
				/>
			</div>
		</>
	);
}

import { __ } from '@wordpress/i18n';
import {
	RangeControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';

import { DsgoInspectorPanel } from '../../../components/shared';
import { GROUP_BY_OPTIONS, DATE_PRECISION_OPTIONS } from './QuerySourcePanel';

export const RESULTS_DEFAULTS = {
	tagName: 'ul',
	itemTagName: 'li',
	columns: 1,
	columnsTablet: 0,
	columnsMobile: 0,
	firstItemColumnSpan: 1,
	firstItemRowSpan: 1,
	groupBy: null,
};

/**
 * Shared "Results layout" inspector panel used by both the parent
 * designsetgo/query proxy and the child designsetgo/query-results own inspector.
 *
 * Both callers read and write the same block attributes (the child's), so a
 * change in either panel is immediately reflected in the other.
 *
 * @param {Object}   root0
 * @param {Object}   root0.attributes      The query-results block's attributes.
 * @param {Function} root0.set             Partial-attribute setter (maps to setAttributes or updateBlockAttributes).
 * @param {string}   root0.panelId         clientId for ToolsPanel reset-state scoping.
 * @param {Array}    root0.taxonomyOptions [{value, label}] list from core-data.
 */
export default function ResultsLayoutControls({
	attributes,
	set,
	panelId,
	taxonomyOptions,
}) {
	const a = { ...RESULTS_DEFAULTS, ...attributes };
	const groupByField = a.groupBy?.field || 'none';

	const handleGroupByFieldChange = (value) => {
		if (value === 'none') {
			set({ groupBy: null });
		} else {
			set({ groupBy: { field: value, key: '' } });
		}
	};

	return (
		<DsgoInspectorPanel
			title={__('Results layout', 'designsetgo')}
			panelName="settings"
			panelId={panelId}
			resetAll={() => set(RESULTS_DEFAULTS)}
		>
			<DsgoInspectorPanel.Item
				label={__('Columns', 'designsetgo')}
				hasValue={() => a.columns !== RESULTS_DEFAULTS.columns}
				onDeselect={() => set({ columns: RESULTS_DEFAULTS.columns })}
				isShownByDefault
			>
				<RangeControl
					label={__('Columns', 'designsetgo')}
					value={a.columns || 1}
					min={1}
					max={6}
					onChange={(v) => set({ columns: v })}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Columns (tablet)', 'designsetgo')}
				hasValue={() =>
					a.columnsTablet !== RESULTS_DEFAULTS.columnsTablet
				}
				onDeselect={() =>
					set({ columnsTablet: RESULTS_DEFAULTS.columnsTablet })
				}
				isShownByDefault
			>
				<RangeControl
					label={__('Columns (tablet)', 'designsetgo')}
					help={__(
						'0 inherits the desktop column count.',
						'designsetgo'
					)}
					value={a.columnsTablet || 0}
					min={0}
					max={6}
					onChange={(v) => set({ columnsTablet: v })}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Columns (mobile)', 'designsetgo')}
				hasValue={() =>
					a.columnsMobile !== RESULTS_DEFAULTS.columnsMobile
				}
				onDeselect={() =>
					set({ columnsMobile: RESULTS_DEFAULTS.columnsMobile })
				}
				isShownByDefault
			>
				<RangeControl
					label={__('Columns (mobile)', 'designsetgo')}
					value={a.columnsMobile || 1}
					min={1}
					max={3}
					onChange={(v) => set({ columnsMobile: v })}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('First item column span', 'designsetgo')}
				hasValue={() =>
					a.firstItemColumnSpan !== RESULTS_DEFAULTS.firstItemColumnSpan
				}
				onDeselect={() =>
					set({
						firstItemColumnSpan: RESULTS_DEFAULTS.firstItemColumnSpan,
					})
				}
				isShownByDefault
			>
				<RangeControl
					label={__('First item column span', 'designsetgo')}
					help={__(
						'Make the first result a featured callout by spanning extra columns. 1 = no span. Capped at the current column count on smaller screens.',
						'designsetgo'
					)}
					value={a.firstItemColumnSpan || 1}
					min={1}
					max={Math.max(1, a.columns || 1)}
					onChange={(v) => set({ firstItemColumnSpan: v })}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('First item row span', 'designsetgo')}
				hasValue={() =>
					a.firstItemRowSpan !== RESULTS_DEFAULTS.firstItemRowSpan
				}
				onDeselect={() =>
					set({ firstItemRowSpan: RESULTS_DEFAULTS.firstItemRowSpan })
				}
				isShownByDefault
			>
				<RangeControl
					label={__('First item row span', 'designsetgo')}
					help={__(
						'Extra height for the featured first item (like a Pinterest hero). 1 = no span.',
						'designsetgo'
					)}
					value={a.firstItemRowSpan || 1}
					min={1}
					max={4}
					onChange={(v) => set({ firstItemRowSpan: v })}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('List tag', 'designsetgo')}
				hasValue={() => a.tagName !== RESULTS_DEFAULTS.tagName}
				onDeselect={() => set({ tagName: RESULTS_DEFAULTS.tagName })}
				isShownByDefault
			>
				<SelectControl
					label={__('List tag', 'designsetgo')}
					value={a.tagName || 'ul'}
					options={[
						{ label: 'ul', value: 'ul' },
						{ label: 'ol', value: 'ol' },
						{ label: 'div', value: 'div' },
					]}
					onChange={(v) => set({ tagName: v })}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Item tag', 'designsetgo')}
				hasValue={() => a.itemTagName !== RESULTS_DEFAULTS.itemTagName}
				onDeselect={() =>
					set({ itemTagName: RESULTS_DEFAULTS.itemTagName })
				}
				isShownByDefault
			>
				<SelectControl
					label={__('Item tag', 'designsetgo')}
					value={a.itemTagName || 'li'}
					options={[
						{ label: 'li', value: 'li' },
						{ label: 'div', value: 'div' },
						{ label: 'article', value: 'article' },
					]}
					onChange={(v) => set({ itemTagName: v })}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Group by', 'designsetgo')}
				hasValue={() => groupByField !== 'none'}
				onDeselect={() => set({ groupBy: null })}
				isShownByDefault
			>
				<SelectControl
					label={__('Group by', 'designsetgo')}
					value={groupByField}
					options={GROUP_BY_OPTIONS}
					onChange={handleGroupByFieldChange}
					help={__(
						'Grouped output requires a Query group header block inside the results template.',
						'designsetgo'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</DsgoInspectorPanel.Item>

			{groupByField === 'taxonomy' && (
				<DsgoInspectorPanel.Item
					label={__('Group taxonomy', 'designsetgo')}
					hasValue={() => !!a.groupBy?.key}
					onDeselect={() =>
						set({ groupBy: { ...a.groupBy, key: '' } })
					}
					isShownByDefault
				>
					<SelectControl
						label={__('Group taxonomy', 'designsetgo')}
						value={a.groupBy?.key || ''}
						options={[
							{
								value: '',
								label: __('— Select —', 'designsetgo'),
							},
							...(taxonomyOptions || []),
						]}
						onChange={(v) =>
							set({ groupBy: { ...a.groupBy, key: v } })
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</DsgoInspectorPanel.Item>
			)}

			{groupByField === 'meta' && (
				<DsgoInspectorPanel.Item
					label={__('Group meta key', 'designsetgo')}
					hasValue={() => !!a.groupBy?.key}
					onDeselect={() =>
						set({ groupBy: { ...a.groupBy, key: '' } })
					}
					isShownByDefault
				>
					<TextControl
						label={__('Group meta key', 'designsetgo')}
						value={a.groupBy?.key || ''}
						onChange={(v) =>
							set({ groupBy: { ...a.groupBy, key: v } })
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</DsgoInspectorPanel.Item>
			)}

			{groupByField === 'date' && (
				<DsgoInspectorPanel.Item
					label={__('Date precision', 'designsetgo')}
					hasValue={() => (a.groupBy?.key || 'Y') !== 'Y'}
					onDeselect={() =>
						set({ groupBy: { ...a.groupBy, key: 'Y' } })
					}
					isShownByDefault
				>
					<SelectControl
						label={__('Date precision', 'designsetgo')}
						value={a.groupBy?.key || 'Y'}
						options={DATE_PRECISION_OPTIONS}
						onChange={(v) =>
							set({ groupBy: { ...a.groupBy, key: v } })
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</DsgoInspectorPanel.Item>
			)}
		</DsgoInspectorPanel>
	);
}

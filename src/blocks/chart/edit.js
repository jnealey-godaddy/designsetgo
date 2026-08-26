/**
 * Chart Block - Edit
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	SelectControl,
	RangeControl,
	ToggleControl,
	TextControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { DsgoInspectorPanel } from '../../components/shared';
import { DataEditor } from './components/DataEditor';
import { SeriesColors } from './components/SeriesColors';
import { ValueFormatControls } from './components/ValueFormatControls';
import { stripWrapperAttributes } from './utils/strip-wrapper-attributes';

const TYPES = [
	{ value: 'bar', label: __('Bar', 'designsetgo') },
	{ value: 'line', label: __('Line', 'designsetgo') },
	{ value: 'donut', label: __('Donut', 'designsetgo') },
];

const SOURCES = [
	{ value: 'manual', label: __('Enter data', 'designsetgo') },
	{ value: 'meta', label: __('Post meta field', 'designsetgo') },
];

// Meta-sourced rows are only known to the server, so offer a fixed set of
// palette slots rather than hiding the colour controls entirely.
const META_SERIES_SLOTS = 6;

export default function Edit({ attributes, setAttributes, clientId }) {
	const {
		chartType,
		data,
		dataSource,
		metaKey,
		height,
		showLegend,
		showGrid,
		showValues,
		valuePrefix,
		valueSuffix,
		groupThousands,
		palette,
		label,
	} = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
				>
					<DsgoInspectorPanel.Item
						label={__('Chart type', 'designsetgo')}
						hasValue={() => 'bar' !== chartType}
						onDeselect={() => setAttributes({ chartType: 'bar' })}
						isShownByDefault
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={__('Chart type', 'designsetgo')}
							value={chartType}
							options={TYPES}
							onChange={(value) =>
								setAttributes({ chartType: value })
							}
							help={
								'donut' === chartType
									? __(
											'Slices are shares of a total, so rows of zero or less are left out.',
											'designsetgo'
										)
									: undefined
							}
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Description', 'designsetgo')}
						hasValue={() => !!label}
						onDeselect={() => setAttributes({ label: '' })}
						isShownByDefault
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={__('Description', 'designsetgo')}
							value={label}
							onChange={(value) =>
								setAttributes({ label: value })
							}
							help={__(
								'Read by screen readers as the data table caption.',
								'designsetgo'
							)}
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Data source', 'designsetgo')}
						hasValue={() => 'manual' !== dataSource}
						onDeselect={() =>
							setAttributes({ dataSource: 'manual' })
						}
						isShownByDefault
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={__('Data source', 'designsetgo')}
							value={dataSource}
							options={SOURCES}
							onChange={(value) =>
								setAttributes({ dataSource: value })
							}
						/>
					</DsgoInspectorPanel.Item>

					{'meta' === dataSource && (
						<DsgoInspectorPanel.Item
							label={__('Meta key', 'designsetgo')}
							hasValue={() => !!metaKey}
							onDeselect={() => setAttributes({ metaKey: '' })}
							isShownByDefault
						>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={__('Meta key', 'designsetgo')}
								value={metaKey}
								onChange={(value) =>
									setAttributes({ metaKey: value })
								}
								help={__(
									'The field must hold a JSON array of {label, value} objects.',
									'designsetgo'
								)}
							/>
						</DsgoInspectorPanel.Item>
					)}

					{'manual' === dataSource && (
						<DsgoInspectorPanel.Item
							label={__('Data', 'designsetgo')}
							hasValue={() => !!data?.length}
							onDeselect={() => setAttributes({ data: [] })}
							isShownByDefault
						>
							<DataEditor
								value={data}
								onChange={(value) =>
									setAttributes({ data: value })
								}
							/>
						</DsgoInspectorPanel.Item>
					)}
				</DsgoInspectorPanel>

				<DsgoInspectorPanel
					title={__('Style', 'designsetgo')}
					panelName="style"
					panelId={clientId}
				>
					<DsgoInspectorPanel.Item
						label={__('Height', 'designsetgo')}
						hasValue={() => 240 !== height}
						onDeselect={() => setAttributes({ height: 240 })}
						isShownByDefault
					>
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={__('Height', 'designsetgo')}
							value={height}
							min={80}
							max={800}
							step={10}
							onChange={(value) =>
								setAttributes({ height: value })
							}
						/>
					</DsgoInspectorPanel.Item>

					{/* A donut has no axis to label, so its legend is the only
					    sighted route from a slice to its category. */}
					{'donut' !== chartType && (
						<DsgoInspectorPanel.Item
							label={__('Legend', 'designsetgo')}
							hasValue={() => true !== showLegend}
							onDeselect={() =>
								setAttributes({ showLegend: true })
							}
							isShownByDefault
						>
							<ToggleControl
								__nextHasNoMarginBottom
								label={__('Show legend', 'designsetgo')}
								checked={showLegend}
								onChange={(value) =>
									setAttributes({ showLegend: value })
								}
								help={__(
									'Category names stay on the axis either way.',
									'designsetgo'
								)}
							/>
						</DsgoInspectorPanel.Item>
					)}

					<DsgoInspectorPanel.Item
						label={__('Values', 'designsetgo')}
						hasValue={() => true !== showValues}
						onDeselect={() => setAttributes({ showValues: true })}
						isShownByDefault
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={__('Show values', 'designsetgo')}
							checked={showValues}
							onChange={(value) =>
								setAttributes({ showValues: value })
							}
							help={
								'donut' === chartType
									? __(
											'Labels each slice with its share of the total.',
											'designsetgo'
										)
									: __(
											'Labels each bar or point with its value.',
											'designsetgo'
										)
							}
						/>
					</DsgoInspectorPanel.Item>

					{'donut' !== chartType && (
						<DsgoInspectorPanel.Item
							label={__('Grid', 'designsetgo')}
							hasValue={() => true !== showGrid}
							onDeselect={() => setAttributes({ showGrid: true })}
							isShownByDefault
						>
							<ToggleControl
								__nextHasNoMarginBottom
								label={__('Show grid', 'designsetgo')}
								checked={showGrid}
								onChange={(value) =>
									setAttributes({ showGrid: value })
								}
								help={__(
									'Draws horizontal gridlines and axis labels.',
									'designsetgo'
								)}
							/>
						</DsgoInspectorPanel.Item>
					)}

					<ValueFormatControls
						chartType={chartType}
						valuePrefix={valuePrefix}
						valueSuffix={valueSuffix}
						groupThousands={groupThousands}
						setAttributes={setAttributes}
					/>
				</DsgoInspectorPanel>
			</InspectorControls>

			<SeriesColors
				rows={
					'meta' === dataSource
						? Array.from({ length: META_SERIES_SLOTS }, () => ({}))
						: data
				}
				palette={palette}
				clientId={clientId}
				onChange={(value) => setAttributes({ palette: value })}
			/>

			<div {...blockProps}>
				<ServerSideRender
					block="designsetgo/chart"
					attributes={stripWrapperAttributes(attributes)}
					EmptyResponsePlaceholder={() => (
						<p>
							{__(
								'Add at least one data row to preview the chart.',
								'designsetgo'
							)}
						</p>
					)}
				/>
			</div>
		</>
	);
}

/**
 * Icon List - List Settings Panel Component
 *
 * Renders DsgoInspectorPanel.Item entries for icon-list layout and icon
 * attributes. Meant to be composed inside the Settings DsgoInspectorPanel
 * in icon-list/edit.js.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	RangeControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

export const ListSettingsPanel = ({
	layout,
	iconSize,
	gap,
	iconPosition,
	columns,
	alignment,
	iconVerticalAlignment,
	setAttributes,
}) => {
	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Layout', 'designsetgo')}
				hasValue={() => layout !== 'vertical'}
				onDeselect={() => setAttributes({ layout: 'vertical' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Layout', 'designsetgo')}
					value={layout}
					options={[
						{
							label: __('Vertical', 'designsetgo'),
							value: 'vertical',
						},
						{
							label: __('Horizontal', 'designsetgo'),
							value: 'horizontal',
						},
						{ label: __('Grid', 'designsetgo'), value: 'grid' },
					]}
					onChange={(value) => setAttributes({ layout: value })}
					help={__(
						'Choose how list items are arranged',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{(layout === 'vertical' || layout === 'horizontal') && (
				<DsgoInspectorPanel.Item
					label={__('Alignment', 'designsetgo')}
					hasValue={() => alignment !== 'left'}
					onDeselect={() => setAttributes({ alignment: 'left' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Alignment', 'designsetgo')}
						value={alignment}
						options={[
							{ label: __('Left', 'designsetgo'), value: 'left' },
							{
								label: __('Center', 'designsetgo'),
								value: 'center',
							},
							{
								label: __('Right', 'designsetgo'),
								value: 'right',
							},
						]}
						onChange={(value) =>
							setAttributes({ alignment: value })
						}
						help={
							layout === 'vertical'
								? __(
										'Align list items horizontally',
										'designsetgo'
									)
								: __(
										'Distribute items horizontally',
										'designsetgo'
									)
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{layout === 'grid' && (
				<DsgoInspectorPanel.Item
					label={__('Columns', 'designsetgo')}
					hasValue={() => columns !== 1}
					onDeselect={() => setAttributes({ columns: 1 })}
					isShownByDefault
				>
					<RangeControl
						label={__('Columns', 'designsetgo')}
						value={columns}
						onChange={(value) => setAttributes({ columns: value })}
						min={1}
						max={4}
						help={__(
							'Number of columns in grid layout',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			<DsgoInspectorPanel.Item
				label={__('Icon Position', 'designsetgo')}
				hasValue={() => iconPosition !== 'left'}
				onDeselect={() => setAttributes({ iconPosition: 'left' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Icon Position', 'designsetgo')}
					value={iconPosition}
					options={[
						{ label: __('Left', 'designsetgo'), value: 'left' },
						{ label: __('Right', 'designsetgo'), value: 'right' },
						{ label: __('Top', 'designsetgo'), value: 'top' },
					]}
					onChange={(value) => setAttributes({ iconPosition: value })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{iconPosition !== 'top' && (
				<DsgoInspectorPanel.Item
					label={__('Icon Vertical Alignment', 'designsetgo')}
					hasValue={() => iconVerticalAlignment !== 'top'}
					onDeselect={() =>
						setAttributes({ iconVerticalAlignment: 'top' })
					}
					isShownByDefault
				>
					<SelectControl
						label={__('Vertical Alignment', 'designsetgo')}
						value={iconVerticalAlignment}
						options={[
							{
								label: __('Top', 'designsetgo'),
								value: 'top',
							},
							{
								label: __('Center', 'designsetgo'),
								value: 'center',
							},
						]}
						onChange={(value) =>
							setAttributes({ iconVerticalAlignment: value })
						}
						help={__(
							'Vertically align the icon with the text content',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			<DsgoInspectorPanel.Item
				label={__('Icon Size', 'designsetgo')}
				hasValue={() => iconSize !== 32}
				onDeselect={() => setAttributes({ iconSize: 32 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Icon Size', 'designsetgo')}
					value={iconSize}
					onChange={(value) => setAttributes({ iconSize: value })}
					min={16}
					max={128}
					help={__('Default icon size for all items', 'designsetgo')}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Gap', 'designsetgo')}
				hasValue={() => gap !== '24px'}
				onDeselect={() => setAttributes({ gap: '24px' })}
				isShownByDefault
			>
				<UnitControl
					label={__('Gap', 'designsetgo')}
					value={gap}
					onChange={(value) => setAttributes({ gap: value })}
					units={[
						{ value: 'px', label: 'px' },
						{ value: 'em', label: 'em' },
						{ value: 'rem', label: 'rem' },
					]}
					help={__('Space between list items', 'designsetgo')}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
};

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	SelectControl,
	RangeControl,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import classnames from 'classnames';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';

export default function TimelineEdit({ attributes, setAttributes, clientId }) {
	const {
		orientation,
		layout,
		lineColor,
		lineThickness,
		connectorStyle,
		markerStyle,
		markerSize,
		markerColor,
		markerBorderColor,
		itemSpacing,
		animateOnScroll,
		animationDuration,
		staggerDelay,
	} = attributes;

	// Get theme color palette
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// CSS custom properties for styling
	const customStyles = {
		'--dsgo-timeline-line-color':
			lineColor || 'var(--wp--preset--color--contrast, #e5e7eb)',
		'--dsgo-timeline-line-thickness': `${lineThickness}px`,
		'--dsgo-timeline-connector-style': connectorStyle,
		'--dsgo-timeline-marker-size': `${markerSize}px`,
		'--dsgo-timeline-marker-color':
			markerColor || 'var(--wp--preset--color--primary, #2563eb)',
		'--dsgo-timeline-marker-border-color':
			markerBorderColor ||
			markerColor ||
			'var(--wp--preset--color--primary, #2563eb)',
		'--dsgo-timeline-item-spacing': itemSpacing,
		'--dsgo-timeline-animation-duration': `${animationDuration}ms`,
	};

	// Build class names
	const timelineClasses = classnames('dsgo-timeline', {
		[`dsgo-timeline--${orientation}`]: orientation,
		[`dsgo-timeline--layout-${layout}`]: layout,
		[`dsgo-timeline--marker-${markerStyle}`]: markerStyle,
		'dsgo-timeline--animate': animateOnScroll,
	});

	const blockProps = useBlockProps({
		className: timelineClasses,
		style: customStyles,
	});

	// Inner blocks configuration
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-timeline__items',
		},
		{
			allowedBlocks: ['designsetgo/timeline-item'],
			template: [
				[
					'designsetgo/timeline-item',
					{
						date: __('2020', 'designsetgo'),
						title: __('First Milestone', 'designsetgo'),
					},
				],
				[
					'designsetgo/timeline-item',
					{
						date: __('2022', 'designsetgo'),
						title: __('Second Milestone', 'designsetgo'),
					},
				],
				[
					'designsetgo/timeline-item',
					{
						date: __('2024', 'designsetgo'),
						title: __('Third Milestone', 'designsetgo'),
					},
				],
			],
			orientation:
				orientation === 'horizontal' ? 'horizontal' : 'vertical',
		}
	);

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							orientation: 'vertical',
							layout: 'alternating',
							lineThickness: 2,
							connectorStyle: 'solid',
							markerStyle: 'circle',
							markerSize: 16,
							itemSpacing: '2rem',
							animateOnScroll: true,
							animationDuration: 600,
							staggerDelay: 100,
						})
					}
				>
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
							value={orientation}
							options={[
								{
									label: __('Vertical', 'designsetgo'),
									value: 'vertical',
								},
								{
									label: __('Horizontal', 'designsetgo'),
									value: 'horizontal',
								},
							]}
							onChange={(value) =>
								setAttributes({ orientation: value })
							}
							help={__(
								'Horizontal timelines automatically switch to vertical on mobile devices.',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{orientation === 'vertical' && (
						<DsgoInspectorPanel.Item
							label={__('Content Layout', 'designsetgo')}
							hasValue={() => layout !== 'alternating'}
							onDeselect={() =>
								setAttributes({ layout: 'alternating' })
							}
							isShownByDefault
						>
							<SelectControl
								label={__('Content Layout', 'designsetgo')}
								value={layout}
								options={[
									{
										label: __(
											'Alternating Sides',
											'designsetgo'
										),
										value: 'alternating',
									},
									{
										label: __(
											'Right Side Only',
											'designsetgo'
										),
										value: 'right',
									},
								]}
								onChange={(value) =>
									setAttributes({ layout: value })
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					<DsgoInspectorPanel.Item
						label={__('Item Spacing', 'designsetgo')}
						hasValue={() => itemSpacing !== '2rem'}
						onDeselect={() =>
							setAttributes({ itemSpacing: '2rem' })
						}
						isShownByDefault
					>
						<UnitControl
							label={__('Item Spacing', 'designsetgo')}
							value={itemSpacing}
							onChange={(value) =>
								setAttributes({ itemSpacing: value ?? '2rem' })
							}
							units={[
								{ value: 'px', label: 'px', default: 32 },
								{ value: 'rem', label: 'rem', default: 2 },
								{ value: 'em', label: 'em', default: 2 },
							]}
							min={0}
							max={200}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Line Thickness', 'designsetgo')}
						hasValue={() => lineThickness !== 2}
						onDeselect={() => setAttributes({ lineThickness: 2 })}
						isShownByDefault
					>
						<RangeControl
							label={__('Line Thickness', 'designsetgo')}
							value={lineThickness}
							onChange={(value) =>
								setAttributes({ lineThickness: value })
							}
							min={1}
							max={8}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Connector Style', 'designsetgo')}
						hasValue={() => connectorStyle !== 'solid'}
						onDeselect={() =>
							setAttributes({ connectorStyle: 'solid' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Connector Style', 'designsetgo')}
							value={connectorStyle}
							options={[
								{
									label: __('Solid', 'designsetgo'),
									value: 'solid',
								},
								{
									label: __('Dashed', 'designsetgo'),
									value: 'dashed',
								},
								{
									label: __('Dotted', 'designsetgo'),
									value: 'dotted',
								},
							]}
							onChange={(value) =>
								setAttributes({ connectorStyle: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Marker Shape', 'designsetgo')}
						hasValue={() => markerStyle !== 'circle'}
						onDeselect={() =>
							setAttributes({ markerStyle: 'circle' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Marker Shape', 'designsetgo')}
							value={markerStyle}
							options={[
								{
									label: __('Circle', 'designsetgo'),
									value: 'circle',
								},
								{
									label: __('Square', 'designsetgo'),
									value: 'square',
								},
								{
									label: __('Diamond', 'designsetgo'),
									value: 'diamond',
								},
							]}
							onChange={(value) =>
								setAttributes({ markerStyle: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Marker Size', 'designsetgo')}
						hasValue={() => markerSize !== 16}
						onDeselect={() => setAttributes({ markerSize: 16 })}
						isShownByDefault
					>
						<RangeControl
							label={__('Marker Size', 'designsetgo')}
							value={markerSize}
							onChange={(value) =>
								setAttributes({ markerSize: value })
							}
							min={8}
							max={48}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Animate on Scroll', 'designsetgo')}
						hasValue={() => animateOnScroll !== true}
						onDeselect={() =>
							setAttributes({ animateOnScroll: true })
						}
						isShownByDefault
					>
						<ToggleControl
							label={__('Animate on Scroll', 'designsetgo')}
							help={
								animateOnScroll
									? __(
											'Items will fade in as they scroll into view',
											'designsetgo'
										)
									: __(
											'All items will be visible immediately',
											'designsetgo'
										)
							}
							checked={animateOnScroll}
							onChange={(value) =>
								setAttributes({ animateOnScroll: value })
							}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{animateOnScroll && (
						<DsgoInspectorPanel.Item
							label={__('Animation Duration (ms)', 'designsetgo')}
							hasValue={() => animationDuration !== 600}
							onDeselect={() =>
								setAttributes({ animationDuration: 600 })
							}
							isShownByDefault
						>
							<RangeControl
								label={__(
									'Animation Duration (ms)',
									'designsetgo'
								)}
								value={animationDuration}
								onChange={(value) =>
									setAttributes({ animationDuration: value })
								}
								min={100}
								max={2000}
								step={50}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					{animateOnScroll && (
						<DsgoInspectorPanel.Item
							label={__('Stagger Delay (ms)', 'designsetgo')}
							hasValue={() => staggerDelay !== 100}
							onDeselect={() =>
								setAttributes({ staggerDelay: 100 })
							}
							isShownByDefault
						>
							<RangeControl
								label={__('Stagger Delay (ms)', 'designsetgo')}
								value={staggerDelay}
								onChange={(value) =>
									setAttributes({ staggerDelay: value })
								}
								min={0}
								max={500}
								step={25}
								help={__(
									'Delay between each item animation',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					settings={[
						{
							label: __('Line Color', 'designsetgo'),
							colorValue: decodeColorValue(
								lineColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									lineColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Marker Fill', 'designsetgo'),
							colorValue: decodeColorValue(
								markerColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									markerColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Marker Border', 'designsetgo'),
							colorValue: decodeColorValue(
								markerBorderColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									markerBorderColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
					]}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<div {...blockProps}>
				<div className="dsgo-timeline__line" aria-hidden="true" />
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}

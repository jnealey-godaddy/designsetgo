/**
 * Divider Block - Edit Component
 *
 * Visual separator with multiple style options including
 * solid, dashed, gradient, and decorative patterns.
 *
 * @since 1.0.0
 */

import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	SelectControl,
	RangeControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { getIcon, IconPicker } from '../shared/icon-utils';
import { useIconDefaults } from '../../hooks';

/**
 * Divider Edit Component
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @param {string}   props.clientId      - Block client ID
 * @return {JSX.Element} Divider block edit component
 */
export default function DividerEdit({ attributes, setAttributes, clientId }) {
	const { dividerStyle, width, thickness, iconName, iconStyle, strokeWidth } =
		attributes;

	// Theme-level icon defaults inherited when style is left unset.
	const iconDefaults = useIconDefaults();
	const effectiveStyle = iconStyle || iconDefaults.style;

	// Block wrapper props - Block Supports automatically applies color styles
	const blockProps = useBlockProps({
		className: `dsgo-divider dsgo-divider--${dividerStyle}`,
	});

	// Divider container styles
	const containerStyle = {
		width: `${width}%`,
	};

	// Divider line styles
	const lineStyle = {
		height: `${thickness}px`,
	};

	return (
		<>
			{/* ========================================
			     INSPECTOR CONTROLS - SETTINGS TAB
			    ======================================== */}
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							dividerStyle: 'solid',
							width: 100,
							thickness: 2,
							iconName: 'star',
							iconStyle: undefined,
							strokeWidth: 1.5,
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Style', 'designsetgo')}
						hasValue={() => dividerStyle !== 'solid'}
						onDeselect={() =>
							setAttributes({ dividerStyle: 'solid' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Style', 'designsetgo')}
							value={dividerStyle}
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
								{
									label: __('Double', 'designsetgo'),
									value: 'double',
								},
								{
									label: __('Gradient Fade', 'designsetgo'),
									value: 'gradient',
								},
								{
									label: __('Dots Pattern', 'designsetgo'),
									value: 'dots',
								},
								{
									label: __('Wave Pattern', 'designsetgo'),
									value: 'wave',
								},
								{
									label: __('Icon Centered', 'designsetgo'),
									value: 'icon',
								},
							]}
							onChange={(value) =>
								setAttributes({ dividerStyle: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{dividerStyle === 'icon' && (
						<DsgoInspectorPanel.Item
							label={__('Icon', 'designsetgo')}
							hasValue={() => iconName !== 'star'}
							onDeselect={() =>
								setAttributes({ iconName: 'star' })
							}
							isShownByDefault
						>
							<IconPicker
								label={__('Icon', 'designsetgo')}
								value={iconName}
								onChange={(value) =>
									setAttributes({ iconName: value })
								}
							/>
						</DsgoInspectorPanel.Item>
					)}

					{dividerStyle === 'icon' && (
						<DsgoInspectorPanel.Item
							label={__('Icon Style', 'designsetgo')}
							hasValue={() => typeof iconStyle === 'string'}
							onDeselect={() =>
								setAttributes({ iconStyle: undefined })
							}
							isShownByDefault
						>
							<ToggleGroupControl
								label={__('Icon Style', 'designsetgo')}
								value={effectiveStyle}
								onChange={(value) =>
									setAttributes({ iconStyle: value })
								}
								help={
									!iconStyle &&
									sprintf(
										/* translators: %s: inherited icon style (Filled or Outlined). */
										__(
											'Inheriting theme default (%s).',
											'designsetgo'
										),
										iconDefaults.style === 'outlined'
											? __('Outlined', 'designsetgo')
											: __('Filled', 'designsetgo')
									)
								}
								isBlock
								__nextHasNoMarginBottom
							>
								<ToggleGroupControlOption
									value="filled"
									label={__('Filled', 'designsetgo')}
								/>
								<ToggleGroupControlOption
									value="outlined"
									label={__('Outlined', 'designsetgo')}
								/>
							</ToggleGroupControl>
						</DsgoInspectorPanel.Item>
					)}

					{dividerStyle === 'icon' &&
						effectiveStyle === 'outlined' && (
							<DsgoInspectorPanel.Item
								label={__('Stroke Width', 'designsetgo')}
								hasValue={() => strokeWidth !== 1.5}
								onDeselect={() =>
									setAttributes({ strokeWidth: 1.5 })
								}
								isShownByDefault
							>
								<RangeControl
									label={__('Stroke Width', 'designsetgo')}
									value={strokeWidth}
									onChange={(value) =>
										setAttributes({ strokeWidth: value })
									}
									min={0.5}
									max={4}
									step={0.5}
									help={__(
										'Thinner strokes work better for detailed icons',
										'designsetgo'
									)}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</DsgoInspectorPanel.Item>
						)}

					<DsgoInspectorPanel.Item
						label={__('Width (%)', 'designsetgo')}
						hasValue={() => width !== 100}
						onDeselect={() => setAttributes({ width: 100 })}
						isShownByDefault
					>
						<RangeControl
							label={__('Width (%)', 'designsetgo')}
							value={width}
							onChange={(value) =>
								setAttributes({ width: value })
							}
							min={10}
							max={100}
							step={5}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{dividerStyle !== 'icon' && (
						<DsgoInspectorPanel.Item
							label={__('Thickness (px)', 'designsetgo')}
							hasValue={() => thickness !== 2}
							onDeselect={() => setAttributes({ thickness: 2 })}
							isShownByDefault
						>
							<RangeControl
								label={__('Thickness (px)', 'designsetgo')}
								value={thickness}
								onChange={(value) =>
									setAttributes({ thickness: value })
								}
								min={1}
								max={20}
								step={1}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			{/* ========================================
			     BLOCK CONTENT
			    ======================================== */}
			<div {...blockProps}>
				<div className="dsgo-divider__container" style={containerStyle}>
					{dividerStyle === 'icon' ? (
						<div className="dsgo-divider__icon-wrapper">
							<span
								className="dsgo-divider__line dsgo-divider__line--left"
								style={lineStyle}
							/>
							<span className="dsgo-divider__icon">
								{getIcon(iconName, effectiveStyle, strokeWidth)}
							</span>
							<span
								className="dsgo-divider__line dsgo-divider__line--right"
								style={lineStyle}
							/>
						</div>
					) : (
						<div className="dsgo-divider__line" style={lineStyle} />
					)}
				</div>
			</div>
		</>
	);
}

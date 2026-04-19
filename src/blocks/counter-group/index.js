/**
 * Counter Group Block - Edit Component
 *
 * Parent block that contains individual Counter blocks
 * Provides layout controls and global animation settings
 */

import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
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
	RangeControl,
	SelectControl,
	ToggleControl,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';

import metadata from './block.json';
import save from './save';
import { ICON_COLOR } from '../shared/constants';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import './editor.scss';
import './style.scss';

/**
 * Edit component
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @param {string}   props.clientId      - Block client ID
 * @return {JSX.Element} Counter Group edit component
 */
function CounterGroupEdit({ attributes, setAttributes, clientId }) {
	const {
		columns,
		columnsTablet,
		columnsMobile,
		gap,
		alignContent,
		animationDuration,
		animationDelay,
		animationEasing,
		useGrouping,
		separator,
		decimal,
		hoverColor,
	} = attributes;

	// Get theme color palette and gradient settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Block wrapper props
	const blockProps = useBlockProps({
		className: 'dsgo-counter-group',
		style: {
			// CRITICAL: Use align-self: stretch to fill parent width
			alignSelf: 'stretch',
			// Cast to string to prevent React from adding "px" suffix
			'--dsgo-counter-columns-desktop': String(columns),
			'--dsgo-counter-columns-tablet': String(columnsTablet),
			'--dsgo-counter-columns-mobile': String(columnsMobile),
			'--dsgo-counter-gap': gap,
			// Apply hover color for child Counter blocks to inherit
			...(hoverColor && {
				'--dsgo-counter-hover-color': convertColorToCSSVar(hoverColor),
			}),
		},
	});

	// Inner blocks configuration
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: `dsgo-counter-group__inner dsgo-counter-group__inner--align-${alignContent}`,
		},
		{
			allowedBlocks: ['designsetgo/counter'],
			template: [
				[
					'designsetgo/counter',
					{
						endValue: 500,
						suffix: '+',
						label: __('Happy Customers', 'designsetgo'),
					},
				],
				[
					'designsetgo/counter',
					{
						prefix: '$',
						endValue: 1000,
						suffix: 'K+',
						label: __('Revenue Generated', 'designsetgo'),
					},
				],
				[
					'designsetgo/counter',
					{
						endValue: 99.9,
						decimals: 1,
						suffix: '%',
						label: __('Uptime', 'designsetgo'),
					},
				],
			],
			orientation: 'horizontal',
		}
	);

	return (
		<>
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Hover Color', 'designsetgo')}
					settings={[
						{
							label: __('Number Hover Color', 'designsetgo'),
							colorValue: decodeColorValue(
								hoverColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									hoverColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							clearable: true,
						},
					]}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							columns: 3,
							columnsTablet: 2,
							columnsMobile: 1,
							gap: '32px',
							alignContent: 'center',
							animationDuration: 2,
							animationDelay: 0,
							animationEasing: 'easeOutQuad',
							useGrouping: true,
							separator: ',',
							decimal: '.',
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Columns (Desktop)', 'designsetgo')}
						hasValue={() => columns !== 3}
						onDeselect={() => setAttributes({ columns: 3 })}
						isShownByDefault
					>
						<RangeControl
							label={__('Columns (Desktop)', 'designsetgo')}
							value={columns}
							onChange={(value) =>
								setAttributes({ columns: value })
							}
							min={1}
							max={6}
							help={__('>1024px', 'designsetgo')}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Columns (Tablet)', 'designsetgo')}
						hasValue={() => columnsTablet !== 2}
						onDeselect={() => setAttributes({ columnsTablet: 2 })}
						isShownByDefault
					>
						<RangeControl
							label={__('Columns (Tablet)', 'designsetgo')}
							value={columnsTablet}
							onChange={(value) =>
								setAttributes({ columnsTablet: value })
							}
							min={1}
							max={columns}
							help={__('768px - 1023px', 'designsetgo')}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Columns (Mobile)', 'designsetgo')}
						hasValue={() => columnsMobile !== 1}
						onDeselect={() => setAttributes({ columnsMobile: 1 })}
						isShownByDefault
					>
						<RangeControl
							label={__('Columns (Mobile)', 'designsetgo')}
							value={columnsMobile}
							onChange={(value) =>
								setAttributes({ columnsMobile: value })
							}
							min={1}
							max={columnsTablet}
							help={__('<768px', 'designsetgo')}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Gap', 'designsetgo')}
						hasValue={() => gap !== '32px'}
						onDeselect={() => setAttributes({ gap: '32px' })}
						isShownByDefault
					>
						<UnitControl
							label={__('Gap', 'designsetgo')}
							value={gap}
							onChange={(value) => setAttributes({ gap: value })}
							units={[
								{ value: 'px', label: 'px' },
								{ value: 'rem', label: 'rem' },
								{ value: 'em', label: 'em' },
							]}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Content Alignment', 'designsetgo')}
						hasValue={() => alignContent !== 'center'}
						onDeselect={() =>
							setAttributes({ alignContent: 'center' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Content Alignment', 'designsetgo')}
							value={alignContent}
							options={[
								{
									label: __('Left', 'designsetgo'),
									value: 'left',
								},
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
								setAttributes({ alignContent: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__(
							'Animation Duration (seconds)',
							'designsetgo'
						)}
						hasValue={() => animationDuration !== 2}
						onDeselect={() =>
							setAttributes({ animationDuration: 2 })
						}
						isShownByDefault
					>
						<RangeControl
							label={__(
								'Animation Duration (seconds)',
								'designsetgo'
							)}
							value={animationDuration}
							onChange={(value) =>
								setAttributes({ animationDuration: value })
							}
							min={0.5}
							max={5}
							step={0.1}
							help={__(
								'How long the count animation takes',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Animation Delay (seconds)', 'designsetgo')}
						hasValue={() => animationDelay !== 0}
						onDeselect={() => setAttributes({ animationDelay: 0 })}
						isShownByDefault
					>
						<RangeControl
							label={__(
								'Animation Delay (seconds)',
								'designsetgo'
							)}
							value={animationDelay}
							onChange={(value) =>
								setAttributes({ animationDelay: value })
							}
							min={0}
							max={2}
							step={0.1}
							help={__(
								'Delay before animation starts',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Easing Function', 'designsetgo')}
						hasValue={() => animationEasing !== 'easeOutQuad'}
						onDeselect={() =>
							setAttributes({ animationEasing: 'easeOutQuad' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Easing Function', 'designsetgo')}
							value={animationEasing}
							options={[
								{
									label: __('Ease Out Quad', 'designsetgo'),
									value: 'easeOutQuad',
								},
								{
									label: __('Ease Out Cubic', 'designsetgo'),
									value: 'easeOutCubic',
								},
								{
									label: __('Ease In Out', 'designsetgo'),
									value: 'easeInOutQuad',
								},
								{
									label: __('Linear', 'designsetgo'),
									value: 'linear',
								},
							]}
							onChange={(value) =>
								setAttributes({ animationEasing: value })
							}
							help={__('Animation timing curve', 'designsetgo')}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Use Thousands Separator', 'designsetgo')}
						hasValue={() => useGrouping !== true}
						onDeselect={() => setAttributes({ useGrouping: true })}
						isShownByDefault
					>
						<ToggleControl
							label={__('Use Thousands Separator', 'designsetgo')}
							checked={useGrouping}
							onChange={(value) =>
								setAttributes({ useGrouping: value })
							}
							help={__(
								'Format numbers like "1,000" or "1000"',
								'designsetgo'
							)}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{useGrouping && (
						<DsgoInspectorPanel.Item
							label={__('Thousands Separator', 'designsetgo')}
							hasValue={() => separator !== ','}
							onDeselect={() => setAttributes({ separator: ',' })}
							isShownByDefault
						>
							<TextControl
								label={__('Thousands Separator', 'designsetgo')}
								value={separator}
								onChange={(value) =>
									setAttributes({ separator: value })
								}
								help={__(
									'Character for thousands (e.g., "," or ".")',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					<DsgoInspectorPanel.Item
						label={__('Decimal Point', 'designsetgo')}
						hasValue={() => decimal !== '.'}
						onDeselect={() => setAttributes({ decimal: '.' })}
						isShownByDefault
					>
						<TextControl
							label={__('Decimal Point', 'designsetgo')}
							value={decimal}
							onChange={(value) =>
								setAttributes({ decimal: value })
							}
							help={__(
								'Character for decimals (e.g., "." or ",")',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}

/**
 * Register block
 */
registerBlockType(metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				fill="none"
				stroke="currentColor"
				strokeWidth="1.5"
				strokeLinecap="round"
				strokeLinejoin="round"
			>
				<rect x="3" y="4" width="7" height="7" rx="1" />
				<rect x="13" y="4" width="7" height="7" rx="1" />
				<rect x="3" y="13" width="7" height="7" rx="1" />
				<rect x="13" y="13" width="7" height="7" rx="1" />
				<line x1="6.5" y1="6.5" x2="6.5" y2="9.5" />
				<line x1="16.5" y1="6.5" x2="16.5" y2="9.5" />
				<line x1="6.5" y1="15.5" x2="6.5" y2="18.5" />
				<line x1="16.5" y1="15.5" x2="16.5" y2="18.5" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit: CounterGroupEdit,
	save,
});

/**
 * Icon List Block - Edit Component
 *
 * Parent block that contains icon list items with shared settings.
 *
 * @since 1.0.0
 */

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
import { DsgoInspectorPanel } from '../../components/shared';
import { ListSettingsPanel } from './components/inspector/ListSettingsPanel';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { useIconDefaults } from '../../hooks';

/**
 * Icon List Edit Component
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @param {string}   props.clientId      - Block client ID
 * @return {JSX.Element} Icon List edit component
 */
export default function IconListEdit({ attributes, setAttributes, clientId }) {
	const {
		layout,
		iconSize,
		iconStyle,
		strokeWidth,
		iconColor,
		iconBackgroundColor,
		gap,
		iconPosition,
		columns,
		columnMinWidth,
		alignment,
		iconVerticalAlignment,
	} = attributes;

	// Get theme color palette and gradient settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Theme-level icon defaults inherited when size/style are left unset.
	const iconDefaults = useIconDefaults({
		sizeKey: 'iconList',
		sizeFallback: 32,
	});
	const effectiveStyle = iconStyle || iconDefaults.style;

	// Calculate alignment value to avoid nested ternary
	let alignItemsValue;
	let justifyContentValue;

	if (layout === 'vertical') {
		// For vertical layout, alignItems controls horizontal alignment
		if (alignment === 'center') {
			alignItemsValue = 'center';
		} else if (alignment === 'right') {
			alignItemsValue = 'flex-end';
		} else {
			alignItemsValue = 'flex-start';
		}
	} else if (layout === 'horizontal') {
		// For horizontal layout, justifyContent controls horizontal distribution
		if (alignment === 'center') {
			justifyContentValue = 'center';
		} else if (alignment === 'right') {
			justifyContentValue = 'flex-end';
		} else {
			justifyContentValue = 'flex-start';
		}
	}

	// Calculate container styles declaratively
	// Determine flex direction based on layout
	let flexDirection;
	if (layout === 'vertical') {
		flexDirection = 'column';
	} else if (layout === 'horizontal') {
		flexDirection = 'row';
	}

	// Grid columns: when a min width is set, auto-fit responsively (wraps,
	// never overflows thanks to the min(100%, …) clamp); otherwise a fixed
	// column count. Computed ahead of the object to avoid a nested ternary.
	let gridTemplateColumns;
	if (layout === 'grid') {
		gridTemplateColumns = columnMinWidth
			? `repeat(auto-fit, minmax(min(100%, ${columnMinWidth}), 1fr))`
			: `repeat(${columns}, 1fr)`;
	}

	const containerStyles = {
		display: layout === 'grid' ? 'grid' : 'flex',
		flexDirection,
		gridTemplateColumns,
		gap,
		alignItems: alignItemsValue,
		justifyContent: justifyContentValue,
		width: '100%', // Ensure container fills available space
	};

	// Mirror the wrapper's inheritable icon style/stroke (see save.js) so the
	// editor DOM matches the frontend and any editor-side lazy injection reads
	// the same inherited values. Only emitted when iconStyle is explicitly set.
	const inheritedIconAttrs = iconStyle
		? {
				'data-dsgo-icon-style': iconStyle,
				...(iconStyle === 'outlined' && strokeWidth
					? { 'data-dsgo-icon-stroke-width': String(strokeWidth) }
					: {}),
			}
		: {};

	// Get block wrapper props
	const blockProps = useBlockProps({
		className: `dsgo-icon-list dsgo-icon-list--${layout}`,
		style: { width: '100%' }, // Ensure block fills parent width
		...inheritedIconAttrs,
	});

	// Configure inner blocks
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-icon-list__items',
			style: containerStyles,
		},
		{
			allowedBlocks: ['designsetgo/icon-list-item'],
			template: [
				[
					'designsetgo/icon-list-item',
					{ icon: 'check', title: __('First item', 'designsetgo') },
				],
				[
					'designsetgo/icon-list-item',
					{ icon: 'check', title: __('Second item', 'designsetgo') },
				],
				[
					'designsetgo/icon-list-item',
					{ icon: 'check', title: __('Third item', 'designsetgo') },
				],
			],
			orientation: layout === 'vertical' ? 'vertical' : undefined,
		}
	);

	return (
		<>
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Icon Colors', 'designsetgo')}
					settings={[
						{
							label: __('Icon Color', 'designsetgo'),
							colorValue: decodeColorValue(
								iconColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									iconColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Icon Background Color', 'designsetgo'),
							colorValue: decodeColorValue(
								iconBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									iconBackgroundColor:
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

			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							layout: 'vertical',
							iconSize: undefined,
							iconStyle: undefined,
							strokeWidth: 1.5,
							gap: '24px',
							iconPosition: 'left',
							columns: 1,
							columnMinWidth: '',
							alignment: 'left',
							iconVerticalAlignment: 'top',
						})
					}
				>
					<ListSettingsPanel
						layout={layout}
						iconSize={iconSize}
						iconStyle={iconStyle}
						strokeWidth={strokeWidth}
						effectiveStyle={effectiveStyle}
						iconDefaults={iconDefaults}
						gap={gap}
						iconPosition={iconPosition}
						columns={columns}
						columnMinWidth={columnMinWidth}
						alignment={alignment}
						iconVerticalAlignment={iconVerticalAlignment}
						setAttributes={setAttributes}
					/>
				</DsgoInspectorPanel>
			</InspectorControls>

			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}

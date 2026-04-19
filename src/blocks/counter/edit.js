/**
 * Counter Block - Edit Component (Refactored for Maintainability)
 *
 * Individual counter item that displays an animated counting number.
 * Gets animation and formatting settings from parent Counter Group.
 *
 * File size: ~120 lines (down from 357 lines in index.js - 66% reduction!)
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
	// WordPress 6.5+ - useSettings (plural) replaces useSetting (singular)
	useSettings,
} from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';

// Extracted Inspector Panel Components
import { DsgoInspectorPanel } from '../../components/shared';
import { CounterSettingsPanel } from './components/inspector/CounterSettingsPanel';
import { LabelSettingsPanel } from './components/inspector/LabelSettingsPanel';
import { IconSettingsPanel } from './components/inspector/IconSettingsPanel';
import { AnimationPanel } from './components/inspector/AnimationPanel';

// Extracted Utilities
import { formatCounterValue } from './utils/number-formatter';
import { getIconSvg } from './utils/icon-library';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Counter Edit Component
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @param {Object}   props.context       - Block context from parent Counter Group
 * @param {string}   props.clientId      - Block client ID
 * @return {JSX.Element} Counter block edit component
 */
export default function CounterEdit({
	attributes,
	setAttributes,
	context,
	clientId,
}) {
	const {
		uniqueId,
		startValue,
		endValue,
		decimals,
		prefix,
		suffix,
		label,
		showIcon,
		icon,
		iconPosition,
		overrideAnimation,
		customDuration,
		customDelay,
		customEasing,
		hoverColor,
	} = attributes;

	// Get theme color palette and gradient settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Get theme color palette (WordPress 6.5+ - useSettings returns array)
	const [colorSettings] = useSettings('color.palette');

	// Get formatting settings from parent Counter Group context (with fallback defaults)
	const parentUseGrouping =
		context?.['designsetgo/counterGroup/useGrouping'] ?? true;
	const parentSeparator =
		context?.['designsetgo/counterGroup/separator'] || ',';
	const parentDecimal = context?.['designsetgo/counterGroup/decimal'] || '.';
	const parentHoverColor =
		context?.['designsetgo/counterGroup/hoverColor'] || '';

	// Get theme accent-2 color as default
	const themeColors = colorSettings?.theme || [];
	const accent2Color = themeColors.find((color) => color.slug === 'accent-2');
	const defaultHoverColor = accent2Color?.color || '';

	// Determine effective hover color: individual override > parent > theme accent-2
	const effectiveHoverColor =
		hoverColor || parentHoverColor || defaultHoverColor;

	// Generate unique ID on mount (acceptable use of useEffect for ID generation)
	useEffect(() => {
		if (!uniqueId) {
			setAttributes({
				uniqueId: `counter-${Math.random().toString(36).substr(2, 9)}`,
			});
		}
	}, [uniqueId, setAttributes]);

	// Calculate display value using extracted utility
	const displayValue = formatCounterValue(endValue, {
		prefix,
		suffix,
		decimals,
		useGrouping: parentUseGrouping,
		separator: parentSeparator,
		decimal: parentDecimal,
	});

	// Block wrapper props
	const blockProps = useBlockProps({
		className: 'dsgo-counter',
		style: {
			textAlign: 'center',
			// Apply effective hover color as CSS custom property
			...(effectiveHoverColor && {
				'--dsgo-counter-hover-color':
					convertColorToCSSVar(effectiveHoverColor),
			}),
		},
	});

	return (
		<>
			{/* ========================================
			     INSPECTOR CONTROLS
			    ======================================== */}
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
							startValue: 0,
							endValue: 100,
							decimals: 0,
							prefix: '',
							suffix: '',
							label: '',
							showIcon: false,
							icon: 'star',
							iconPosition: 'top',
							overrideAnimation: false,
							customDuration: 2,
							customDelay: 0,
							customEasing: 'easeOutQuad',
						})
					}
				>
					<CounterSettingsPanel
						startValue={startValue}
						endValue={endValue}
						decimals={decimals}
						prefix={prefix}
						suffix={suffix}
						setAttributes={setAttributes}
					/>

					<LabelSettingsPanel
						label={label}
						setAttributes={setAttributes}
					/>

					<IconSettingsPanel
						showIcon={showIcon}
						icon={icon}
						iconPosition={iconPosition}
						setAttributes={setAttributes}
					/>

					<AnimationPanel
						overrideAnimation={overrideAnimation}
						customDuration={customDuration}
						customDelay={customDelay}
						customEasing={customEasing}
						context={context}
						setAttributes={setAttributes}
					/>
				</DsgoInspectorPanel>
			</InspectorControls>

			{/* ========================================
			     BLOCK CONTENT
			    ======================================== */}
			<div {...blockProps}>
				{/* Icon (if enabled and position is top) */}
				{showIcon && iconPosition === 'top' && (
					<div className="dsgo-counter__icon dsgo-counter__icon--top">
						{getIconSvg(icon)}
					</div>
				)}

				<div className={`dsgo-counter__content icon-${iconPosition}`}>
					{/* Icon (if enabled and position is left) */}
					{showIcon && iconPosition === 'left' && (
						<div className="dsgo-counter__icon dsgo-counter__icon--left">
							{getIconSvg(icon)}
						</div>
					)}

					{/* Number */}
					<div className="dsgo-counter__number">{displayValue}</div>

					{/* Icon (if enabled and position is right) */}
					{showIcon && iconPosition === 'right' && (
						<div className="dsgo-counter__icon dsgo-counter__icon--right">
							{getIconSvg(icon)}
						</div>
					)}
				</div>

				{/* Label */}
				{label && <div className="dsgo-counter__label">{label}</div>}
			</div>
		</>
	);
}

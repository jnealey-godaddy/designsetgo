/**
 * Counter Block - Save Function
 *
 * WordPress Best Practice Approach:
 * - Declarative style application (matches edit.js exactly)
 * - Data attributes for frontend JavaScript animation
 */

import { useBlockProps } from '@wordpress/block-editor';
import { getIconSvg } from './utils/icon-library';

export default function CounterSave({ attributes, context }) {
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
		iconSize,
		overrideAnimation,
		customDuration,
		customDelay,
		customEasing,
		hoverColor,
	} = attributes;

	// Get settings from parent Counter Group context (with fallback defaults)
	const parentDuration =
		context?.['designsetgo/counterGroup/animationDuration'] || 2;
	const parentDelay =
		context?.['designsetgo/counterGroup/animationDelay'] || 0;
	const parentEasing =
		context?.['designsetgo/counterGroup/animationEasing'] || 'easeOutQuad';
	const parentUseGrouping =
		context?.['designsetgo/counterGroup/useGrouping'] ?? true;
	const parentSeparator =
		context?.['designsetgo/counterGroup/separator'] || ',';
	const parentDecimal = context?.['designsetgo/counterGroup/decimal'] || '.';
	const parentHoverColor =
		context?.['designsetgo/counterGroup/hoverColor'] || '';

	// Determine active animation settings
	const activeDuration = overrideAnimation ? customDuration : parentDuration;
	const activeDelay = overrideAnimation ? customDelay : parentDelay;
	const activeEasing = overrideAnimation ? customEasing : parentEasing;

	// Determine effective hover color: individual override > parent
	const effectiveHoverColor = hoverColor || parentHoverColor;

	// Icon size is written inline ONLY when the author sets an explicit
	// iconSize. Left unset, style.scss sizes the icon from the theme token
	// (--wp--custom--designsetgo--counter--icon-size), so no size is baked
	// into stored markup. Must match edit.js.
	const iconSizeStyle =
		typeof iconSize === 'number'
			? { '--dsgo-counter-icon-size': `${iconSize}px` }
			: undefined;

	// Block wrapper props
	const blockProps = useBlockProps.save({
		className: 'dsgo-counter',
		id: uniqueId,
		style: {
			textAlign: 'center',
			// Apply effective hover color as CSS custom property
			...(effectiveHoverColor && {
				'--dsgo-counter-hover-color': effectiveHoverColor,
			}),
		},
		// Data attributes for frontend JavaScript
		'data-start-value': startValue,
		'data-end-value': endValue,
		'data-decimals': decimals,
		'data-prefix': prefix || '',
		'data-suffix': suffix || '',
		'data-duration': activeDuration,
		'data-delay': activeDelay,
		'data-easing': activeEasing,
		'data-use-grouping': parentUseGrouping ? 'true' : 'false',
		'data-separator': parentSeparator || ',',
		'data-decimal': parentDecimal || '.',
	});

	return (
		<div {...blockProps}>
			{/* Icon (if enabled and position is top) */}
			{showIcon && iconPosition === 'top' && (
				<div
					className="dsgo-counter__icon dsgo-counter__icon--top"
					style={iconSizeStyle}
				>
					{getIconSvg(icon)}
				</div>
			)}

			<div className={`dsgo-counter__content icon-${iconPosition}`}>
				{/* Icon (if enabled and position is left) */}
				{showIcon && iconPosition === 'left' && (
					<div
						className="dsgo-counter__icon dsgo-counter__icon--left"
						style={iconSizeStyle}
					>
						{getIconSvg(icon)}
					</div>
				)}

				{/* Number - Will be animated by frontend JavaScript */}
				<div className="dsgo-counter__number">
					{/* Initial value (0 or startValue), will be animated to endValue */}
					<span className="dsgo-counter__value">{startValue}</span>
				</div>

				{/* Icon (if enabled and position is right) */}
				{showIcon && iconPosition === 'right' && (
					<div
						className="dsgo-counter__icon dsgo-counter__icon--right"
						style={iconSizeStyle}
					>
						{getIconSvg(icon)}
					</div>
				)}
			</div>

			{/* Label */}
			{label && <div className="dsgo-counter__label">{label}</div>}
		</div>
	);
}

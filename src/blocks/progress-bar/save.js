/**
 * Progress Bar Block - Save Component
 *
 * Renders the frontend markup for the progress bar.
 *
 * @since 1.0.0
 */

import { useBlockProps } from '@wordpress/block-editor';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Save component for Progress Bar block
 *
 * @param {Object} props            - Component props
 * @param {Object} props.attributes - Block attributes
 * @return {JSX.Element} Save component
 */
export default function ProgressBarSave({ attributes }) {
	const {
		percentage,
		barColor,
		barBackgroundColor,
		height,
		borderRadius,
		showLabel,
		labelText,
		showPercentage,
		labelPosition,
		barStyle,
		animateOnScroll,
		animationDuration,
		stripedAnimation,
	} = attributes;

	// Calculate bar width (clamped between 0-100)
	const barWidth = Math.min(Math.max(percentage, 0), 100);

	// Resolve chosen colors. When unset we emit no inline color so the bar
	// inherits the CSS default (which references an FSE preset var) instead
	// of a baked hex literal.
	const barFillColor = convertColorToCSSVar(barColor);
	const barTrackColor = convertColorToCSSVar(barBackgroundColor);

	// The fill's width is a CSS custom-property formula, not a literal
	// percentage, so a `dsgoStyleBinding` on `--dsgo-progress` (e.g. bound to
	// `designsetgo/woo-stock-quantity`) can drive it from the frontend
	// render_block filter — custom properties set on the block's root element
	// inherit down to the fill. `--dsgo-progress` and `--dsgo-progress-max`
	// are raw numbers, not percentages: the formula divides one by the other
	// and multiplies by 100% itself. Neither var is set here, so with no
	// binding both fall back to the literals baked into this expression
	// (`barWidth` / `100`), which resolves to exactly `${barWidth}%` — the
	// same width this block has always rendered when unbound.
	const STATIC_WIDTH_FORMULA = `clamp(0%, calc(100% * var(--dsgo-progress, ${barWidth}) / var(--dsgo-progress-max, 100)), 100%)`;

	// Build bar fill styles (same as edit.js)
	const barFillStyles = {
		width: animateOnScroll ? '0%' : STATIC_WIDTH_FORMULA, // Start at 0 if animating
		height: '100%',
		backgroundColor: barFillColor || undefined,
		transition: `width ${animationDuration}s ease-out`,
		borderRadius,
	};

	// Add striped background if enabled
	if (barStyle === 'striped' || barStyle === 'striped-animated') {
		barFillStyles.backgroundImage =
			'linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent)';
		barFillStyles.backgroundSize = '1rem 1rem';
	}

	// Build bar container styles (same as edit.js)
	const barContainerStyles = {
		width: '100%',
		height,
		backgroundColor: barTrackColor || undefined,
		borderRadius,
		overflow: 'hidden',
		position: 'relative',
	};

	// Build label display text (same as edit.js).
	//
	// NOTE: this text is baked into the saved HTML at edit time from the
	// `percentage` attribute. A `dsgoStyleBinding` on `--dsgo-progress` is
	// resolved later, by a PHP render_block filter running against the
	// already-saved markup — there is no hook for it to also rewrite this
	// label. A bound progress bar's fill width tracks the binding, but its
	// percentage label (when shown) always reflects the static `percentage`
	// attribute instead. Authors binding a value should turn showPercentage
	// off (see the block's Settings panel) rather than ship a label that
	// silently disagrees with the fill.
	const displayText = (() => {
		const parts = [];
		if (showLabel && labelText) {
			parts.push(labelText);
		}
		if (showPercentage) {
			parts.push(`${barWidth}%`);
		}
		return parts.join(' - ');
	})();

	// Get block props
	const blockProps = useBlockProps.save({
		className: `dsgo-progress-bar ${animateOnScroll ? 'dsgo-progress-bar--animate' : ''}`,
		'data-percentage': animateOnScroll ? barWidth : undefined,
		'data-duration': animateOnScroll ? animationDuration : undefined,
	});

	return (
		<div {...blockProps}>
			{/* Label Above */}
			{displayText && labelPosition === 'top' && (
				<div className="dsgo-progress-bar__label dsgo-progress-bar__label--top">
					{displayText}
				</div>
			)}

			{/* Progress Bar */}
			<div
				className="dsgo-progress-bar__container"
				style={barContainerStyles}
			>
				<div
					className={`dsgo-progress-bar__fill ${
						barStyle === 'striped-animated' || stripedAnimation
							? 'dsgo-progress-bar__fill--animated'
							: ''
					}`}
					style={barFillStyles}
				>
					{/* Label Inside */}
					{displayText && labelPosition === 'inside' && (
						<div className="dsgo-progress-bar__label dsgo-progress-bar__label--inside">
							{displayText}
						</div>
					)}
				</div>
			</div>

			{/* Label Below */}
			{displayText && labelPosition === 'bottom' && (
				<div className="dsgo-progress-bar__label dsgo-progress-bar__label--bottom">
					{displayText}
				</div>
			)}
		</div>
	);
}

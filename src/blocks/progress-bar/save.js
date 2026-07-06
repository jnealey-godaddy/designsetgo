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

	// Build bar fill styles (same as edit.js)
	const barFillStyles = {
		width: animateOnScroll ? '0%' : `${barWidth}%`, // Start at 0 if animating
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

	// Build label display text (same as edit.js)
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

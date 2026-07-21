/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Save component for Countdown Timer block
 *
 * @param {Object} props            - Block properties
 * @param {Object} props.attributes - Block attributes
 * @return {JSX.Element} Save component
 */
export default function save({ attributes }) {
	const {
		targetDateTime,
		timezone,
		showDays,
		showHours,
		showMinutes,
		showSeconds,
		layout,
		completionAction,
		completionMessage,
		numberColor,
		labelColor,
		unitBackgroundColor,
		unitBorder,
		unitBorderRadius,
		unitGap,
		unitPadding,
	} = attributes;

	// Build unit styles - use CSS variable for accent-2 fallback
	const unitStyle = {
		backgroundColor:
			convertColorToCSSVar(unitBackgroundColor) || 'transparent',
		borderColor:
			unitBorder?.color ||
			'var(--wp--preset--color--accent-2, currentColor)',
		borderWidth: unitBorder?.width || '2px',
		borderStyle: unitBorder?.style || 'solid',
		borderRadius: `${unitBorderRadius}px`,
		padding: unitPadding || '1.5rem',
	};

	const numberStyle = {
		color:
			convertColorToCSSVar(numberColor) ||
			'var(--wp--preset--color--accent-2, currentColor)',
	};

	const labelStyle = {
		color: convertColorToCSSVar(labelColor) || 'currentColor',
	};

	const containerStyle = {
		gap: unitGap || '1rem',
	};

	// Create data attributes for frontend JavaScript
	const blockProps = useBlockProps.save({
		className: `dsgo-countdown-timer dsgo-countdown-timer--${layout}`,
		style: containerStyle,
		'data-target-datetime': targetDateTime,
		'data-timezone': timezone,
		'data-show-days': showDays,
		'data-show-hours': showHours,
		'data-show-minutes': showMinutes,
		'data-show-seconds': showSeconds,
		'data-completion-action': completionAction,
	});

	// Build initial display (will be updated by frontend JS)
	const units = [];

	if (showDays) {
		units.push({
			type: 'days',
			label: 'Days',
			value: '00',
		});
	}

	if (showHours) {
		units.push({
			type: 'hours',
			label: 'Hours',
			value: '00',
		});
	}

	if (showMinutes) {
		units.push({
			type: 'minutes',
			label: 'Min',
			value: '00',
		});
	}

	if (showSeconds) {
		units.push({
			type: 'seconds',
			label: 'Sec',
			value: '00',
		});
	}

	return (
		<div {...blockProps}>
			<div className="dsgo-countdown-timer__units">
				{units.map((unit) => (
					<div
						key={unit.type}
						className="dsgo-countdown-timer__unit"
						data-unit-type={unit.type}
						style={unitStyle}
					>
						<div
							className="dsgo-countdown-timer__number"
							style={numberStyle}
						>
							{unit.value}
						</div>
						<div
							className="dsgo-countdown-timer__label"
							style={labelStyle}
						>
							{unit.label}
						</div>
					</div>
				))}
			</div>
			{/*
			 * The message text is the single source of truth (sourced into the
			 * `completionMessage` attribute), so it stays translatable via the
			 * visible text and no longer needs a duplicate `data-completion-message`
			 * copy on the wrapper — that duplication meant translating one copy
			 * left the other stale and failed validation.
			 *
			 * Hidden by default via style.scss
			 * (`.dsgo-countdown-timer__completion-message { display: none }`);
			 * view.js reveals it by setting `style.display = 'block'`.
			 */}
			<div className="dsgo-countdown-timer__completion-message">
				{completionMessage}
			</div>
		</div>
	);
}

/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';

/**
 * Internal dependencies
 */
// formatTimeUnit not used in deprecated - kept for potential future deprecations
// import { formatTimeUnit } from './utils/time-calculator';

/**
 * Shared supports for all deprecated versions.
 * Uses __experimentalBorder (the historical name) instead of border.
 */
const sharedSupports = {
	anchor: true,
	align: ['wide', 'full'],
	spacing: {
		margin: true,
		padding: true,
		blockGap: true,
		__experimentalDefaultControls: {
			padding: true,
		},
	},
	color: {
		background: true,
		text: true,
		gradients: true,
		__experimentalDefaultControls: {
			background: false,
			text: false,
		},
	},
	typography: {
		fontSize: true,
		lineHeight: true,
		fontWeight: true,
		textAlign: true,
		__experimentalDefaultControls: {
			fontSize: true,
			textAlign: true,
		},
	},
	__experimentalBorder: {
		color: true,
		radius: true,
		style: true,
		width: true,
		__experimentalDefaultControls: {
			radius: true,
			width: true,
		},
	},
};

/**
 * Deprecated version 2: Pre-BorderControl migration
 *
 * This deprecation handles blocks created before we migrated to WordPress BorderControl.
 *
 * Changes in current version:
 * - Replaced unitBorderColor and unitBorderWidth with unitBorder object
 * - Unit border now uses WordPress core BorderControl component
 */
/**
 * Attributes/supports as of v3 — identical to the current block. Only the
 * MARKUP changed, so these are re-declared (not migrated) below.
 *
 * NOTE: this deliberately does NOT reuse `sharedSupports` above, which declares
 * typography.fontWeight with the un-prefixed key. WordPress's hasBlockSupport()
 * only recognises `__experimentalFontWeight`, so the un-prefixed name silently
 * fails — the support looks declared but isn't. block.json uses the prefixed
 * key, so this version must too.
 */
const v3Supports = {
	anchor: true,
	align: ['wide', 'full'],
	spacing: {
		margin: true,
		padding: true,
		blockGap: true,
		__experimentalDefaultControls: { padding: true },
	},
	color: {
		background: true,
		text: true,
		gradients: true,
		__experimentalDefaultControls: { background: false, text: false },
	},
	typography: {
		fontSize: true,
		lineHeight: true,
		textAlign: true,
		__experimentalDefaultControls: { fontSize: true, textAlign: true },
		__experimentalFontWeight: true,
	},
	__experimentalBorder: {
		color: true,
		radius: true,
		style: true,
		width: true,
		__experimentalDefaultControls: { radius: true, width: true },
	},
};

/**
 * Inline `display:none` on the completion message — the version before that
 * constant moved to style.scss.
 *
 * The message div serialized `style="display:none"` into every saved timer.
 * style.scss already declared `display: none` on
 * `.dsgo-countdown-timer__completion-message`, and view.js reveals the message
 * by setting `style.display = 'block'` (an inline style, which beats the
 * stylesheet either way) — so the baked copy was pure duplication.
 *
 * Markup-only change: migrate() is a passthrough.
 *
 * NOTE: WordPress calls isEligible(attributes, innerBlocks, { blockNode, block })
 * — there is no `innerHTML` key on that third argument.
 */
const v3 = {
	supports: v3Supports,
	attributes: {
		targetDateTime: { type: 'string', default: '' },
		timezone: { type: 'string', default: '' },
		showDays: { type: 'boolean', default: true },
		showHours: { type: 'boolean', default: true },
		showMinutes: { type: 'boolean', default: true },
		showSeconds: { type: 'boolean', default: true },
		layout: {
			type: 'string',
			default: 'boxed',
			enum: ['boxed', 'inline', 'compact'],
		},
		completionAction: {
			type: 'string',
			default: 'message',
			enum: ['message', 'hide'],
		},
		completionMessage: {
			type: 'string',
			default: 'The countdown has ended!',
		},
		numberColor: { type: 'string', default: '' },
		labelColor: { type: 'string', default: '' },
		unitBackgroundColor: { type: 'string', default: '' },
		unitBorder: {
			type: 'object',
			default: { color: '', style: 'solid', width: '2px' },
		},
		unitBorderRadius: { type: 'number', default: 12 },
		unitGap: { type: 'string', default: '1rem' },
		unitPadding: { type: 'string', default: '1.5rem' },
	},
	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const html = blockNode?.innerHTML ?? block?.originalContent ?? '';
		return (
			html.includes('dsgo-countdown-timer__completion-message') &&
			html.includes('display:none')
		);
	},
	migrate(attributes) {
		// Markup-only change.
		return attributes;
	},
	save({ attributes }) {
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
			'data-completion-message': completionMessage,
		});

		const units = [];

		if (showDays) {
			units.push({ type: 'days', label: 'Days', value: '00' });
		}

		if (showHours) {
			units.push({ type: 'hours', label: 'Hours', value: '00' });
		}

		if (showMinutes) {
			units.push({ type: 'minutes', label: 'Min', value: '00' });
		}

		if (showSeconds) {
			units.push({ type: 'seconds', label: 'Sec', value: '00' });
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
				<div
					className="dsgo-countdown-timer__completion-message"
					style={{ display: 'none' }}
				>
					{completionMessage}
				</div>
			</div>
		);
	},
};

const v2 = {
	supports: sharedSupports,
	attributes: {
		targetDateTime: {
			type: 'string',
			default: '',
		},
		timezone: {
			type: 'string',
			default: '',
		},
		showDays: {
			type: 'boolean',
			default: true,
		},
		showHours: {
			type: 'boolean',
			default: true,
		},
		showMinutes: {
			type: 'boolean',
			default: true,
		},
		showSeconds: {
			type: 'boolean',
			default: true,
		},
		layout: {
			type: 'string',
			default: 'boxed',
			enum: ['boxed', 'inline', 'compact'],
		},
		completionAction: {
			type: 'string',
			default: 'message',
			enum: ['message', 'hide'],
		},
		completionMessage: {
			type: 'string',
			default: 'The countdown has ended!',
		},
		numberColor: {
			type: 'string',
			default: '',
		},
		labelColor: {
			type: 'string',
			default: '',
		},
		unitBackgroundColor: {
			type: 'string',
			default: '',
		},
		unitBorderColor: {
			type: 'string',
			default: '',
		},
		unitBorderWidth: {
			type: 'number',
			default: 2,
		},
		unitBorderRadius: {
			type: 'number',
			default: 12,
		},
		unitGap: {
			type: 'string',
			default: '1rem',
		},
		unitPadding: {
			type: 'string',
			default: '1.5rem',
		},
	},

	isEligible(attributes) {
		// v2 blocks have unitBorderColor/unitBorderWidth but NOT v1-specific attrs
		// v1 blocks also have textAlign, numberFontSize, labelFontSize
		return (
			(Object.prototype.hasOwnProperty.call(
				attributes,
				'unitBorderColor'
			) ||
				Object.prototype.hasOwnProperty.call(
					attributes,
					'unitBorderWidth'
				)) &&
			!attributes.unitBorder &&
			!Object.prototype.hasOwnProperty.call(attributes, 'textAlign') &&
			!Object.prototype.hasOwnProperty.call(attributes, 'numberFontSize')
		);
	},

	migrate(attributes) {
		const { unitBorderColor, unitBorderWidth, ...otherAttributes } =
			attributes;

		// Convert old attributes to new unitBorder object
		return {
			...otherAttributes,
			unitBorder: {
				color: unitBorderColor || '',
				style: 'solid',
				width: unitBorderWidth ? `${unitBorderWidth}px` : '2px',
			},
		};
	},

	save({ attributes }) {
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
			unitBorderColor,
			unitBorderWidth,
			unitBorderRadius,
			unitGap,
			unitPadding,
		} = attributes;

		// Build unit styles - use CSS variable for accent-2 fallback
		const unitStyle = {
			backgroundColor:
				convertPresetToCSSVar(unitBackgroundColor) || 'transparent',
			borderColor:
				convertPresetToCSSVar(unitBorderColor) ||
				'var(--wp--preset--color--accent-2, currentColor)',
			borderWidth: `${unitBorderWidth}px`,
			borderStyle: 'solid',
			borderRadius: `${unitBorderRadius}px`,
			padding: unitPadding || '1.5rem',
		};

		const numberStyle = {
			color:
				convertPresetToCSSVar(numberColor) ||
				'var(--wp--preset--color--accent-2, currentColor)',
		};

		const labelStyle = {
			color: convertPresetToCSSVar(labelColor) || 'currentColor',
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
			'data-completion-message': completionMessage,
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
				<div
					className="dsgo-countdown-timer__completion-message"
					style={{ display: 'none' }}
				>
					{completionMessage}
				</div>
			</div>
		);
	},
};

/**
 * Deprecated version 1: Pre-Block Supports refactoring
 *
 * This deprecation handles blocks created before we migrated to WordPress Block Supports.
 *
 * Changes in current version:
 * - Removed textAlign attribute (now uses typography.__experimentalTextAlign support)
 * - Removed numberFontSize attribute (now uses em units relative to parent fontSize)
 * - Removed labelFontSize attribute (now uses em units relative to parent fontSize)
 * - Removed unitBorderWidth attribute (now uses __experimentalBorder support)
 * - Removed unitBorderRadius attribute (now uses __experimentalBorder support)
 */
const v1 = {
	supports: sharedSupports,
	attributes: {
		targetDateTime: {
			type: 'string',
			default: '',
		},
		timezone: {
			type: 'string',
			default: '',
		},
		showDays: {
			type: 'boolean',
			default: true,
		},
		showHours: {
			type: 'boolean',
			default: true,
		},
		showMinutes: {
			type: 'boolean',
			default: true,
		},
		showSeconds: {
			type: 'boolean',
			default: true,
		},
		layout: {
			type: 'string',
			default: 'boxed',
			enum: ['boxed', 'inline', 'compact'],
		},
		textAlign: {
			type: 'string',
			default: 'center',
		},
		completionAction: {
			type: 'string',
			default: 'message',
			enum: ['message', 'hide'],
		},
		completionMessage: {
			type: 'string',
			default: 'The countdown has ended!',
		},
		numberColor: {
			type: 'string',
			default: '',
		},
		labelColor: {
			type: 'string',
			default: '',
		},
		unitBackgroundColor: {
			type: 'string',
			default: '',
		},
		unitBorderColor: {
			type: 'string',
			default: '',
		},
		unitBorderWidth: {
			type: 'number',
			default: 2,
		},
		unitBorderRadius: {
			type: 'number',
			default: 12,
		},
		unitGap: {
			type: 'string',
			default: '1rem',
		},
		unitPadding: {
			type: 'string',
			default: '1.5rem',
		},
		numberFontSize: {
			type: 'string',
			default: '3rem',
		},
		labelFontSize: {
			type: 'string',
			default: '1rem',
		},
	},

	isEligible(attributes) {
		// v1 blocks have textAlign, numberFontSize, labelFontSize attributes
		return (
			Object.prototype.hasOwnProperty.call(attributes, 'textAlign') ||
			Object.prototype.hasOwnProperty.call(
				attributes,
				'numberFontSize'
			) ||
			Object.prototype.hasOwnProperty.call(attributes, 'labelFontSize')
		);
	},

	save({ attributes }) {
		const {
			targetDateTime,
			timezone,
			showDays,
			showHours,
			showMinutes,
			showSeconds,
			layout,
			textAlign,
			completionAction,
			completionMessage,
			numberColor,
			labelColor,
			unitBackgroundColor,
			unitBorderColor,
			unitBorderWidth,
			unitBorderRadius,
			unitGap,
			unitPadding,
			numberFontSize,
			labelFontSize,
		} = attributes;

		// Build unit styles - use CSS variable for accent-2 fallback
		const unitStyle = {
			backgroundColor:
				convertPresetToCSSVar(unitBackgroundColor) || 'transparent',
			borderColor:
				convertPresetToCSSVar(unitBorderColor) ||
				'var(--wp--preset--color--accent-2, currentColor)',
			borderWidth: `${unitBorderWidth}px`,
			borderStyle: 'solid',
			borderRadius: `${unitBorderRadius}px`,
			padding: unitPadding || '1.5rem',
		};

		const numberStyle = {
			color:
				convertPresetToCSSVar(numberColor) ||
				'var(--wp--preset--color--accent-2, currentColor)',
			fontSize: numberFontSize || '3rem',
		};

		const labelStyle = {
			color: convertPresetToCSSVar(labelColor) || 'currentColor',
			fontSize: labelFontSize || '1rem',
		};

		// Calculate text alignment
		let justifyValue = 'center';
		if (textAlign === 'left') {
			justifyValue = 'flex-start';
		} else if (textAlign === 'right') {
			justifyValue = 'flex-end';
		}

		const containerStyle = {
			gap: unitGap || '1rem',
			justifyContent: justifyValue,
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
			'data-completion-message': completionMessage,
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
				<div
					className="dsgo-countdown-timer__completion-message"
					style={{ display: 'none' }}
				>
					{completionMessage}
				</div>
			</div>
		);
	},

	migrate(attributes) {
		const {
			textAlign,
			numberFontSize,
			labelFontSize,
			unitBorderColor,
			unitBorderWidth,
			...otherAttributes
		} = attributes;

		return {
			...otherAttributes,
			unitBorder: {
				color: unitBorderColor || '',
				style: 'solid',
				width: unitBorderWidth ? `${unitBorderWidth}px` : '2px',
			},
		};
	},
};

export { v3, v2, v1 };

export default [v3, v2, v1];

/**
 * Progress Bar Block - Deprecations
 *
 * v1: Save before the bar/track colors stopped baking hex defaults into the
 * serialized markup. Previously the fill and container always emitted an inline
 * `background-color` — the chosen color when set, otherwise the literal
 * `#2563eb` (fill) / `#e5e7eb` (track). Current saves omit the inline color when
 * the author has not chosen one so the bar inherits an FSE-overridable CSS
 * default. Blocks saved with a default color therefore carry a hex the current
 * save() no longer emits; this deprecation reproduces the old markup and
 * migrates them silently (attribute schema is unchanged).
 *
 * v2: Save before the fill's width became the `--dsgo-progress` formula
 * (literal `width: N%`).
 *
 * v3: Save before the track carried `role="progressbar"` and its
 * `aria-value*` / `aria-label` attributes (2.8.2's markup). Markup-only
 * change, so no isEligible: the stored HTML no longer matches the current
 * save(), and this frozen copy reproduces it.
 *
 * @package
 */

import { useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { getDeprecatedBlockHTML } from '../../utils/deprecated-block-html';

const v1 = {
	apiVersion: 3,
	attributes: metadata.attributes,
	supports: metadata.supports,
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		if (typeof innerHTML !== 'string') {
			return false;
		}
		// Only the pre-change save baked these hex literals into the markup.
		// A block whose colors were set to presets/custom values produces
		// identical current-save markup and validates without this path.
		return innerHTML.includes('#2563eb') || innerHTML.includes('#e5e7eb');
	},
	migrate(attributes) {
		// Attribute schema is unchanged — only the emitted default moved to
		// CSS. barColor/barBackgroundColor stay '' and the frontend CSS paints
		// the same neutral/primary defaults.
		return attributes;
	},
	save({ attributes }) {
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

		const barWidth = Math.min(Math.max(percentage, 0), 100);

		const barFillStyles = {
			width: animateOnScroll ? '0%' : `${barWidth}%`,
			height: '100%',
			backgroundColor: convertColorToCSSVar(barColor) || '#2563eb',
			transition: `width ${animationDuration}s ease-out`,
			borderRadius,
		};

		if (barStyle === 'striped' || barStyle === 'striped-animated') {
			barFillStyles.backgroundImage =
				'linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent)';
			barFillStyles.backgroundSize = '1rem 1rem';
		}

		const barContainerStyles = {
			width: '100%',
			height,
			backgroundColor:
				convertColorToCSSVar(barBackgroundColor) || '#e5e7eb',
			borderRadius,
			overflow: 'hidden',
			position: 'relative',
		};

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

		const blockProps = useBlockProps.save({
			className: `dsgo-progress-bar ${animateOnScroll ? 'dsgo-progress-bar--animate' : ''}`,
			'data-percentage': animateOnScroll ? barWidth : undefined,
			'data-duration': animateOnScroll ? animationDuration : undefined,
		});

		return (
			<div {...blockProps}>
				{displayText && labelPosition === 'top' && (
					<div className="dsgo-progress-bar__label dsgo-progress-bar__label--top">
						{displayText}
					</div>
				)}

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
						{displayText && labelPosition === 'inside' && (
							<div className="dsgo-progress-bar__label dsgo-progress-bar__label--inside">
								{displayText}
							</div>
						)}
					</div>
				</div>

				{displayText && labelPosition === 'bottom' && (
					<div className="dsgo-progress-bar__label dsgo-progress-bar__label--bottom">
						{displayText}
					</div>
				)}
			</div>
		);
	},
};

/**
 * v2: Save before the fill's `width` became a CSS custom-property formula.
 *
 * Previously the fill always emitted a literal `width: N%` (or `width: 0%`
 * when animateOnScroll, animated in by view.js). The current save() instead
 * writes a `clamp(0%, calc(...), 100%)` formula over `--dsgo-progress` and
 * `--dsgo-progress-max` (see STATIC_WIDTH_FORMULA in save.js) so a
 * `dsgoStyleBinding` on `--dsgo-progress` can drive the fill from the
 * frontend (e.g. a stock bar bound to `designsetgo/woo-stock-quantity`).
 * Attribute schema is unchanged — only the emitted markup differs — so this
 * is a pure save() reproduction with no isEligible/migrate.
 */
const v2 = {
	apiVersion: 3,
	attributes: metadata.attributes,
	supports: metadata.supports,
	save({ attributes }) {
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

		const barWidth = Math.min(Math.max(percentage, 0), 100);

		const barFillColor = convertColorToCSSVar(barColor);
		const barTrackColor = convertColorToCSSVar(barBackgroundColor);

		const barFillStyles = {
			width: animateOnScroll ? '0%' : `${barWidth}%`,
			height: '100%',
			backgroundColor: barFillColor || undefined,
			transition: `width ${animationDuration}s ease-out`,
			borderRadius,
		};

		if (barStyle === 'striped' || barStyle === 'striped-animated') {
			barFillStyles.backgroundImage =
				'linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent)';
			barFillStyles.backgroundSize = '1rem 1rem';
		}

		const barContainerStyles = {
			width: '100%',
			height,
			backgroundColor: barTrackColor || undefined,
			borderRadius,
			overflow: 'hidden',
			position: 'relative',
		};

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

		const blockProps = useBlockProps.save({
			className: `dsgo-progress-bar ${animateOnScroll ? 'dsgo-progress-bar--animate' : ''}`,
			'data-percentage': animateOnScroll ? barWidth : undefined,
			'data-duration': animateOnScroll ? animationDuration : undefined,
		});

		return (
			<div {...blockProps}>
				{displayText && labelPosition === 'top' && (
					<div className="dsgo-progress-bar__label dsgo-progress-bar__label--top">
						{displayText}
					</div>
				)}

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
						{displayText && labelPosition === 'inside' && (
							<div className="dsgo-progress-bar__label dsgo-progress-bar__label--inside">
								{displayText}
							</div>
						)}
					</div>
				</div>

				{displayText && labelPosition === 'bottom' && (
					<div className="dsgo-progress-bar__label dsgo-progress-bar__label--bottom">
						{displayText}
					</div>
				)}
			</div>
		);
	},
};

/**
 * v3: Save before the track carried `role="progressbar"`, `aria-value*` and
 * `aria-label` — the markup 2.8.2 shipped, with the `--dsgo-progress` width
 * formula. A frozen copy of that save(), so posts saved with it validate
 * silently. Markup-only change: no migrate, no isEligible.
 */
const v3 = {
	apiVersion: 3,
	attributes: metadata.attributes,
	supports: metadata.supports,
	save({ attributes }) {
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
		// and multiplies by 100% itself.
		//
		// With no bound value (unmanaged stock returns null, so the binding adds
		// nothing), the fallback must still resolve to exactly `${barWidth}%`
		// even when `--dsgo-progress-max` IS set (a theme rule or a second
		// binding). A bare `var(--dsgo-progress, ${barWidth})` would be divided
		// by that max, so a 10% bar with a max of 50 would claim 20%. The
		// fallback is therefore `barWidth / 100 * max`, which the division
		// cancels back to `barWidth%` whatever the max is.
		//
		// The denominator is wrapped in `max(1, ...)` because `--dsgo-progress-max`
		// can itself be bound (e.g. to a "low stock threshold" field) and resolve
		// to `0` or a negative number. calc() dividing by zero makes the WHOLE
		// `width` declaration invalid at computed-value time — not just that one
		// term — which drops the fill's width entirely rather than clamping it.
		// Flooring the denominator at 1 keeps the declaration always valid; view.js's
		// resolveTargetPercent() mirrors this floor for the animateOnScroll path.
		const PROGRESS_MAX = 'max(1, var(--dsgo-progress-max, 100))';
		const STATIC_WIDTH_FORMULA = `clamp(0%, calc(100% * var(--dsgo-progress, calc(${barWidth} / 100 * ${PROGRESS_MAX})) / ${PROGRESS_MAX}), 100%)`;

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
	},
};

// Named too, so tests can pick an entry without depending on the order.
export { v1, v2, v3 };

export default [v3, v2, v1];

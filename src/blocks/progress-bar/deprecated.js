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
 * @package
 */

import { useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

const v1 = {
	attributes: metadata.attributes,
	supports: metadata.supports,
	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const innerHTML = blockNode?.innerHTML ?? block?.originalContent ?? '';
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

export default [v1];

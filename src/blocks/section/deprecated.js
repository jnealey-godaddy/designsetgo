/**
 * Stack Block - Deprecated versions
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import { getLegacyShapeDivider } from './utils/legacy-shape-dividers';
import { sanitizeColor } from './utils/sanitize-color';

// Shared supports for deprecations (must match what was in block.json when blocks were saved).
// Without this, useBlockProps.save() in deprecated save functions won't generate
// the correct classes/styles (has-*-color, padding, etc.), causing validation to fail.
const sharedSupports = {
	anchor: true,
	align: ['wide', 'full'],
	html: false,
	inserter: true,
	layout: {
		allowSwitching: false,
		allowInheriting: false,
		allowEditing: true,
		allowSizingOnChildren: true,
		default: {
			type: 'flex',
			orientation: 'vertical',
			justifyContent: 'center',
		},
	},
	spacing: {
		margin: true,
		padding: true,
		blockGap: true,
		__experimentalDefaultControls: {
			padding: true,
			blockGap: true,
		},
	},
	dimensions: {
		minHeight: true,
	},
	color: {
		background: true,
		text: true,
		gradients: true,
		link: true,
		__experimentalDefaultControls: {
			background: true,
			text: true,
		},
	},
	background: {
		backgroundImage: true,
		backgroundSize: true,
		backgroundPosition: true,
		backgroundRepeat: true,
		__experimentalDefaultControls: {
			backgroundImage: true,
		},
	},
	typography: {
		fontSize: true,
		lineHeight: true,
		__experimentalDefaultControls: {
			fontSize: true,
		},
	},
	shadow: true,
	position: {
		sticky: true,
	},
	__experimentalBorder: {
		color: true,
		radius: true,
		style: true,
		width: true,
		__experimentalDefaultControls: {
			color: true,
			radius: true,
			style: true,
			width: true,
		},
	},
};

/**
 * Old ShapeDivider component for v3 deprecation.
 * Uses currentColor fallback (the old behavior before background color inheritance).
 * @param {Object}  root0                 Component props
 * @param {string}  root0.shape           Shape name
 * @param {string}  root0.color           Fill color
 * @param {string}  root0.backgroundColor Background color
 * @param {number}  root0.height          Shape height
 * @param {number}  root0.width           Shape width percentage
 * @param {boolean} root0.flipX           Flip horizontal
 * @param {boolean} root0.flipY           Flip vertical
 * @param {boolean} root0.front           Bring to front
 * @param {string}  root0.position        Position (top/bottom)
 */
function OldShapeDivider({
	shape,
	color,
	backgroundColor,
	height = 100,
	width = 100,
	flipX = false,
	flipY = false,
	front = false,
	position = 'top',
}) {
	if (!shape) {
		return null;
	}

	const shapeElement = getLegacyShapeDivider(shape);
	if (!shapeElement) {
		return null;
	}

	const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
	const safeHeight = clamp(Number(height) || 100, 10, 500);
	const safeWidth = clamp(Number(width) || 100, 100, 300);
	const safeColor = sanitizeColor(color);
	const safeBackgroundColor = sanitizeColor(backgroundColor);

	const transforms = [];
	if (flipX) {
		transforms.push('scaleX(-1)');
	}
	if (position === 'bottom' && !flipY) {
		transforms.push('scaleY(-1)');
	} else if (position !== 'bottom' && flipY) {
		transforms.push('scaleY(-1)');
	}

	const widthOffset = Math.max(0, (safeWidth - 100) / 2);

	const className = [
		'dsgo-shape-divider',
		`dsgo-shape-divider--${position}`,
		front && 'dsgo-shape-divider--front',
	]
		.filter(Boolean)
		.join(' ');

	const style = {
		'--dsgo-shape-height': `${safeHeight}px`,
		'--dsgo-shape-width': `${safeWidth}%`,
		'--dsgo-shape-offset': `-${widthOffset}%`,
		'--dsgo-shape-color': safeColor || 'currentColor',
		...(safeBackgroundColor && {
			'--dsgo-shape-background': safeBackgroundColor,
		}),
	};

	return (
		<div className={className} style={style} aria-hidden="true">
			<svg
				viewBox="0 0 1200 120"
				preserveAspectRatio="none"
				style={{
					transform:
						transforms.length > 0
							? transforms.join(' ')
							: undefined,
				}}
			>
				{shapeElement}
			</svg>
		</div>
	);
}

/**
 * V4ShapeDivider component for v4 deprecation.
 * Same as current ShapeDivider but used inline for deprecation stability.
 * This version uses background color inheritance for shape fill but has
 * no text color inheritance for shape background.
 * @param {Object}  root0                 Component props
 * @param {string}  root0.shape           Shape name
 * @param {string}  root0.color           Fill color
 * @param {string}  root0.backgroundColor Background color
 * @param {number}  root0.height          Shape height
 * @param {number}  root0.width           Shape width percentage
 * @param {boolean} root0.flipX           Flip horizontal
 * @param {boolean} root0.flipY           Flip vertical
 * @param {boolean} root0.front           Bring to front
 * @param {string}  root0.position        Position (top/bottom)
 */
function V4ShapeDivider({
	shape,
	color,
	backgroundColor,
	height = 100,
	width = 100,
	flipX = false,
	flipY = false,
	front = false,
	position = 'top',
}) {
	if (!shape) {
		return null;
	}

	const shapeElement = getLegacyShapeDivider(shape);
	if (!shapeElement) {
		return null;
	}

	const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
	const safeHeight = clamp(Number(height) || 100, 10, 500);
	const safeWidth = clamp(Number(width) || 100, 100, 300);
	const safeColor = sanitizeColor(color);
	const safeBackgroundColor = sanitizeColor(backgroundColor);

	const transforms = [];
	if (flipX) {
		transforms.push('scaleX(-1)');
	}
	if (position === 'bottom' && !flipY) {
		transforms.push('scaleY(-1)');
	} else if (position !== 'bottom' && flipY) {
		transforms.push('scaleY(-1)');
	}

	const widthOffset = Math.max(0, (safeWidth - 100) / 2);

	const className = [
		'dsgo-shape-divider',
		`dsgo-shape-divider--${position}`,
		front && 'dsgo-shape-divider--front',
	]
		.filter(Boolean)
		.join(' ');

	const style = {
		'--dsgo-shape-height': `${safeHeight}px`,
		'--dsgo-shape-width': `${safeWidth}%`,
		'--dsgo-shape-offset': `-${widthOffset}%`,
		'--dsgo-shape-color': safeColor || 'transparent',
		...(safeBackgroundColor && {
			'--dsgo-shape-background': safeBackgroundColor,
		}),
	};

	return (
		<div className={className} style={style} aria-hidden="true">
			<svg
				viewBox="0 0 1200 120"
				preserveAspectRatio="none"
				style={{
					transform:
						transforms.length > 0
							? transforms.join(' ')
							: undefined,
				}}
			>
				{shapeElement}
			</svg>
		</div>
	);
}

// Version 6: Block animations extension before lean serialization.
// In commit 634833e5, addAnimationSaveProps was changed to only output data
// attributes that differ from the defaults. Previously it always output all
// animation data attrs (trigger, duration, delay, easing, offset, once,
// exit-animation) regardless of their values. Patterns saved before that
// change (e.g. pricing/pricing-tabs) have all attrs in their stored HTML.
// Current serialization omits the default-value attrs, so the stored HTML no
// longer matches and the block shows "Attempt Recovery".
//
// isEligible targets the legacy signature: sections that are animated AND
// whose stored HTML contains data-dsgo-animation-trigger= (only the old
// save-props filter always emitted that attribute; the current filter omits
// it when it equals the default "scroll").
//
// save() reproduces the legacy HTML by passing the missing default-value attrs
// directly into useBlockProps.save(). blocks.getSaveContent.extraProps still
// runs on top and overrides any non-default-value attrs with the same values,
// so the net output matches the stored markup exactly.
//
// migrate() is a passthrough — only the serialised HTML differs, not the
// attribute values themselves.
//
// NOTE: the dsgoAnimation* attributes referenced in isEligible()/save() below
// are intentionally NOT listed in `attributes` here. They are injected onto
// every block's schema by the block-animations extension's
// blocks.registerBlockType filter at registration time — and that filter pass
// runs on each deprecated entry too, so the deprecated block type ends up with
// them automatically. This is the same extension-injected attribute pattern
// documented on the accordion and pill v1 deprecations in this repo.
const v6 = {
	supports: sharedSupports,
	attributes: {
		align: { type: 'string', default: 'full' },
		tagName: { type: 'string', default: 'div' },
		constrainWidth: { type: 'boolean', default: true },
		contentWidth: { type: 'string', default: '' },
		style: { type: 'object' },
		hoverBackgroundColor: { type: 'string', default: '' },
		hoverTextColor: { type: 'string', default: '' },
		hoverIconBackgroundColor: { type: 'string', default: '' },
		hoverButtonBackgroundColor: { type: 'string', default: '' },
		overlayColor: { type: 'string', default: '' },
		shapeDividerTop: { type: 'string', default: '' },
		shapeDividerTopColor: { type: 'string', default: '' },
		shapeDividerTopHeight: { type: 'number', default: 100 },
		shapeDividerTopWidth: { type: 'number', default: 100 },
		shapeDividerTopFlipX: { type: 'boolean', default: false },
		shapeDividerTopFlipY: { type: 'boolean', default: false },
		shapeDividerTopFront: { type: 'boolean', default: false },
		shapeDividerTopBackgroundColor: { type: 'string', default: '' },
		shapeDividerBottom: { type: 'string', default: '' },
		shapeDividerBottomColor: { type: 'string', default: '' },
		shapeDividerBottomHeight: { type: 'number', default: 100 },
		shapeDividerBottomWidth: { type: 'number', default: 100 },
		shapeDividerBottomFlipX: { type: 'boolean', default: false },
		shapeDividerBottomFlipY: { type: 'boolean', default: false },
		shapeDividerBottomFront: { type: 'boolean', default: false },
		shapeDividerBottomBackgroundColor: { type: 'string', default: '' },
	},
	/**
	 * Matches sections saved with the old animation filter that always emitted
	 * all data attrs. The presence of data-dsgo-animation-trigger= in the
	 * stored HTML is the unique signature — current serialization never outputs
	 * that attribute when it holds the default value "scroll".
	 *
	 * @param {Object} attributes      - Block attributes
	 * @param {Array}  innerBlocks     - Inner blocks
	 * @param {Object} extra           - Extra data including innerHTML
	 * @param {string} extra.innerHTML - The stored serialised HTML
	 * @return {boolean} True when the legacy animation-attrs pattern is detected
	 */
	isEligible(attributes, innerBlocks, { innerHTML }) {
		return !!(
			attributes.dsgoAnimationEnabled &&
			innerHTML &&
			innerHTML.includes('data-dsgo-animation-trigger=')
		);
	},
	/**
	 * Reproduces the legacy outer-element HTML by passing the data attrs that
	 * the old animation filter always emitted (trigger, delay, easing, offset,
	 * once, exit-animation, duration) directly into useBlockProps.save().
	 *
	 * blocks.getSaveContent.extraProps (the current lean animation filter) still
	 * runs on top. For non-default values it overrides with the same value;
	 * for default values the current filter emits nothing so these props survive
	 * in the final markup — matching the stored legacy HTML exactly.
	 *
	 * @param {Object} root0            Props
	 * @param {Object} root0.attributes Block attributes
	 * @return {JSX.Element} Save element
	 */
	save({ attributes }) {
		const {
			tagName = 'div',
			backgroundColor,
			textColor,
			constrainWidth,
			contentWidth,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			overlayColor,
			// Shape divider attributes
			shapeDividerTop,
			shapeDividerTopColor,
			shapeDividerTopBackgroundColor,
			shapeDividerTopHeight,
			shapeDividerTopWidth,
			shapeDividerTopFlipX,
			shapeDividerTopFlipY,
			shapeDividerTopFront,
			shapeDividerBottom,
			shapeDividerBottomColor,
			shapeDividerBottomBackgroundColor,
			shapeDividerBottomHeight,
			shapeDividerBottomWidth,
			shapeDividerBottomFlipX,
			shapeDividerBottomFlipY,
			shapeDividerBottomFront,
			// Animation attributes injected by the block-animations extension
			dsgoAnimationEnabled,
			dsgoEntranceAnimation,
			dsgoExitAnimation,
			dsgoAnimationTrigger,
			dsgoAnimationDuration,
			dsgoAnimationDelay,
			dsgoAnimationEasing,
			dsgoAnimationOffset,
			dsgoAnimationOnce,
		} = attributes;

		const sectionBackgroundColor =
			attributes.style?.color?.background ||
			(backgroundColor
				? `var(--wp--preset--color--${backgroundColor})`
				: '');

		const sectionTextColor =
			attributes.style?.color?.text ||
			(textColor ? `var(--wp--preset--color--${textColor})` : '');

		const className = [
			'dsgo-stack',
			!constrainWidth && 'dsgo-no-width-constraint',
			overlayColor && 'dsgo-stack--has-overlay',
			(shapeDividerTop || shapeDividerBottom) &&
				'dsgo-stack--has-shape-divider',
		]
			.filter(Boolean)
			.join(' ');

		const TagName = tagName || 'div';

		// When animated, include ALL the data attrs that the old save-props filter
		// always emitted. The current lean filter (blocks.getSaveContent.extraProps)
		// still runs on top — it overrides non-default values with the same value
		// and leaves default-value attrs (trigger, easing, offset, once, delay=0,
		// exit-animation="") that it no longer emits, reproducing the legacy HTML.
		const legacyAnimationAttrs = dsgoAnimationEnabled
			? {
					'data-dsgo-animation-enabled': 'true',
					'data-dsgo-entrance-animation': dsgoEntranceAnimation || '',
					'data-dsgo-exit-animation': dsgoExitAnimation || '',
					'data-dsgo-animation-trigger':
						dsgoAnimationTrigger || 'scroll',
					'data-dsgo-animation-duration':
						dsgoAnimationDuration ?? 600,
					'data-dsgo-animation-delay': dsgoAnimationDelay ?? 0,
					'data-dsgo-animation-easing':
						dsgoAnimationEasing || 'ease-out',
					'data-dsgo-animation-offset': dsgoAnimationOffset ?? 100,
					'data-dsgo-animation-once':
						dsgoAnimationOnce !== false ? 'true' : 'false',
				}
			: {};

		const blockProps = useBlockProps.save({
			className,
			...legacyAnimationAttrs,
			style: {
				...(hoverBackgroundColor && {
					'--dsgo-hover-bg-color':
						convertColorToCSSVar(hoverBackgroundColor),
				}),
				...(hoverTextColor && {
					'--dsgo-hover-text-color':
						convertColorToCSSVar(hoverTextColor),
				}),
				...(hoverIconBackgroundColor && {
					'--dsgo-parent-hover-icon-bg': convertColorToCSSVar(
						hoverIconBackgroundColor
					),
				}),
				...(hoverButtonBackgroundColor && {
					'--dsgo-parent-hover-button-bg': convertColorToCSSVar(
						hoverButtonBackgroundColor
					),
				}),
				...(overlayColor && {
					'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
					'--dsgo-overlay-opacity': '0.8',
				}),
			},
		});

		const innerStyle = {};
		if (constrainWidth) {
			innerStyle.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyle.marginLeft = 'auto';
			innerStyle.marginRight = 'auto';
		}

		if (shapeDividerTop) {
			innerStyle.paddingTop = `${shapeDividerTopHeight || 100}px`;
		}
		if (shapeDividerBottom) {
			innerStyle.paddingBottom = `${shapeDividerBottomHeight || 100}px`;
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-stack__inner',
			style: innerStyle,
		});

		// Uses V4ShapeDivider — same as current save (no --dsgo-shape-gradient-dir)
		return (
			<TagName {...blockProps}>
				<V4ShapeDivider
					shape={shapeDividerTop}
					color={
						convertColorToCSSVar(shapeDividerTopColor) ||
						sectionBackgroundColor
					}
					backgroundColor={
						convertColorToCSSVar(shapeDividerTopBackgroundColor) ||
						sectionTextColor
					}
					height={shapeDividerTopHeight}
					width={shapeDividerTopWidth}
					flipX={shapeDividerTopFlipX}
					flipY={shapeDividerTopFlipY}
					front={shapeDividerTopFront}
					position="top"
				/>
				<div {...innerBlocksProps} />
				<V4ShapeDivider
					shape={shapeDividerBottom}
					color={
						convertColorToCSSVar(shapeDividerBottomColor) ||
						sectionBackgroundColor
					}
					backgroundColor={
						convertColorToCSSVar(
							shapeDividerBottomBackgroundColor
						) || sectionTextColor
					}
					height={shapeDividerBottomHeight}
					width={shapeDividerBottomWidth}
					flipX={shapeDividerBottomFlipX}
					flipY={shapeDividerBottomFlipY}
					front={shapeDividerBottomFront}
					position="bottom"
				/>
			</TagName>
		);
	},
	migrate(attributes) {
		return attributes;
	},
};

// Version 5: Shape dividers before gradient direction fix.
// The anti-aliasing gradient was always applied at the top for bottom dividers
// and bottom for top dividers, regardless of flipY. When flipY was true, this
// created a visible line where the wave curve didn't reach the container edge.
// Fix: added --dsgo-shape-gradient-dir CSS custom property to ShapeDivider.
//
// NOTE ON THE SVG -> CLASS-BASED MIGRATION (commits 88f98fa/b81ba13/c01f810d):
// The current save() in save.js was changed to emit an empty classed <div>
// (no inline <svg>). No NEW deprecation entry was added for that change,
// because the existing v3-v6 chain already migrates old content. WordPress's
// applyBlockDeprecatedVersions() walks the deprecations in order and, for
// each, first tries to byte-match that deprecation's own save() against the
// stored HTML and, failing that, falls back to its isEligible(). Old
// inline-SVG posts byte-match the frozen save() of whichever era wrote them
// (OldShapeDivider/`currentColor` in v3, V4ShapeDivider/background-color
// inheritance in v4-v6); that deprecation's passthrough migrate() then
// carries the attributes forward unchanged so the new class-based save()
// re-renders them. v5's broad isEligible (below) is an additional safety net
// for near-miss markup. Verified in src/blocks/section/test/deprecated.test.js
// — do not delete that test; it is the regression guard for this migration.
const v5 = {
	supports: sharedSupports,
	attributes: {
		align: { type: 'string', default: 'full' },
		tagName: { type: 'string', default: 'div' },
		constrainWidth: { type: 'boolean', default: true },
		contentWidth: { type: 'string', default: '' },
		style: { type: 'object' },
		hoverBackgroundColor: { type: 'string', default: '' },
		hoverTextColor: { type: 'string', default: '' },
		hoverIconBackgroundColor: { type: 'string', default: '' },
		hoverButtonBackgroundColor: { type: 'string', default: '' },
		overlayColor: { type: 'string', default: '' },
		shapeDividerTop: { type: 'string', default: '' },
		shapeDividerTopColor: { type: 'string', default: '' },
		shapeDividerTopHeight: { type: 'number', default: 100 },
		shapeDividerTopWidth: { type: 'number', default: 100 },
		shapeDividerTopFlipX: { type: 'boolean', default: false },
		shapeDividerTopFlipY: { type: 'boolean', default: false },
		shapeDividerTopFront: { type: 'boolean', default: false },
		shapeDividerTopBackgroundColor: { type: 'string', default: '' },
		shapeDividerBottom: { type: 'string', default: '' },
		shapeDividerBottomColor: { type: 'string', default: '' },
		shapeDividerBottomHeight: { type: 'number', default: 100 },
		shapeDividerBottomWidth: { type: 'number', default: 100 },
		shapeDividerBottomFlipX: { type: 'boolean', default: false },
		shapeDividerBottomFlipY: { type: 'boolean', default: false },
		shapeDividerBottomFront: { type: 'boolean', default: false },
		shapeDividerBottomBackgroundColor: { type: 'string', default: '' },
	},
	isEligible(attributes) {
		return !!(attributes.shapeDividerTop || attributes.shapeDividerBottom);
	},
	save({ attributes }) {
		const {
			tagName = 'div',
			backgroundColor,
			textColor,
			constrainWidth,
			contentWidth,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			overlayColor,
			shapeDividerTop,
			shapeDividerTopColor,
			shapeDividerTopBackgroundColor,
			shapeDividerTopHeight,
			shapeDividerTopWidth,
			shapeDividerTopFlipX,
			shapeDividerTopFlipY,
			shapeDividerTopFront,
			shapeDividerBottom,
			shapeDividerBottomColor,
			shapeDividerBottomBackgroundColor,
			shapeDividerBottomHeight,
			shapeDividerBottomWidth,
			shapeDividerBottomFlipX,
			shapeDividerBottomFlipY,
			shapeDividerBottomFront,
		} = attributes;

		const sectionBackgroundColor =
			attributes.style?.color?.background ||
			(backgroundColor
				? `var(--wp--preset--color--${backgroundColor})`
				: '');

		const sectionTextColor =
			attributes.style?.color?.text ||
			(textColor ? `var(--wp--preset--color--${textColor})` : '');

		const className = [
			'dsgo-stack',
			!constrainWidth && 'dsgo-no-width-constraint',
			overlayColor && 'dsgo-stack--has-overlay',
			(shapeDividerTop || shapeDividerBottom) &&
				'dsgo-stack--has-shape-divider',
		]
			.filter(Boolean)
			.join(' ');

		const TagName = tagName || 'div';
		const blockProps = useBlockProps.save({
			className,
			style: {
				...(hoverBackgroundColor && {
					'--dsgo-hover-bg-color':
						convertColorToCSSVar(hoverBackgroundColor),
				}),
				...(hoverTextColor && {
					'--dsgo-hover-text-color':
						convertColorToCSSVar(hoverTextColor),
				}),
				...(hoverIconBackgroundColor && {
					'--dsgo-parent-hover-icon-bg': convertColorToCSSVar(
						hoverIconBackgroundColor
					),
				}),
				...(hoverButtonBackgroundColor && {
					'--dsgo-parent-hover-button-bg': convertColorToCSSVar(
						hoverButtonBackgroundColor
					),
				}),
				...(overlayColor && {
					'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
					'--dsgo-overlay-opacity': '0.8',
				}),
			},
		});

		const innerStyle = {};
		if (constrainWidth) {
			innerStyle.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyle.marginLeft = 'auto';
			innerStyle.marginRight = 'auto';
		}

		if (shapeDividerTop) {
			innerStyle.paddingTop = `${shapeDividerTopHeight || 100}px`;
		}
		if (shapeDividerBottom) {
			innerStyle.paddingBottom = `${shapeDividerBottomHeight || 100}px`;
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-stack__inner',
			style: innerStyle,
		});

		// Uses V4ShapeDivider (no --dsgo-shape-gradient-dir property)
		return (
			<TagName {...blockProps}>
				<V4ShapeDivider
					shape={shapeDividerTop}
					color={
						convertColorToCSSVar(shapeDividerTopColor) ||
						sectionBackgroundColor
					}
					backgroundColor={
						convertColorToCSSVar(shapeDividerTopBackgroundColor) ||
						sectionTextColor
					}
					height={shapeDividerTopHeight}
					width={shapeDividerTopWidth}
					flipX={shapeDividerTopFlipX}
					flipY={shapeDividerTopFlipY}
					front={shapeDividerTopFront}
					position="top"
				/>
				<div {...innerBlocksProps} />
				<V4ShapeDivider
					shape={shapeDividerBottom}
					color={
						convertColorToCSSVar(shapeDividerBottomColor) ||
						sectionBackgroundColor
					}
					backgroundColor={
						convertColorToCSSVar(
							shapeDividerBottomBackgroundColor
						) || sectionTextColor
					}
					height={shapeDividerBottomHeight}
					width={shapeDividerBottomWidth}
					flipX={shapeDividerBottomFlipX}
					flipY={shapeDividerBottomFlipY}
					front={shapeDividerBottomFront}
					position="bottom"
				/>
			</TagName>
		);
	},
	migrate(attributes) {
		return attributes;
	},
};

// Version 4: Shape dividers with background color inheritance but no text color for shape background
const v4 = {
	supports: sharedSupports,
	attributes: {
		align: { type: 'string', default: 'full' },
		tagName: { type: 'string', default: 'div' },
		constrainWidth: { type: 'boolean', default: true },
		contentWidth: { type: 'string', default: '' },
		style: { type: 'object' },
		hoverBackgroundColor: { type: 'string', default: '' },
		hoverTextColor: { type: 'string', default: '' },
		hoverIconBackgroundColor: { type: 'string', default: '' },
		hoverButtonBackgroundColor: { type: 'string', default: '' },
		overlayColor: { type: 'string', default: '' },
		shapeDividerTop: { type: 'string', default: '' },
		shapeDividerTopColor: { type: 'string', default: '' },
		shapeDividerTopHeight: { type: 'number', default: 100 },
		shapeDividerTopWidth: { type: 'number', default: 100 },
		shapeDividerTopFlipX: { type: 'boolean', default: false },
		shapeDividerTopFlipY: { type: 'boolean', default: false },
		shapeDividerTopFront: { type: 'boolean', default: false },
		shapeDividerTopBackgroundColor: { type: 'string', default: '' },
		shapeDividerBottom: { type: 'string', default: '' },
		shapeDividerBottomColor: { type: 'string', default: '' },
		shapeDividerBottomHeight: { type: 'number', default: 100 },
		shapeDividerBottomWidth: { type: 'number', default: 100 },
		shapeDividerBottomFlipX: { type: 'boolean', default: false },
		shapeDividerBottomFlipY: { type: 'boolean', default: false },
		shapeDividerBottomFront: { type: 'boolean', default: false },
		shapeDividerBottomBackgroundColor: { type: 'string', default: '' },
	},
	isEligible(attributes) {
		return !!(attributes.shapeDividerTop || attributes.shapeDividerBottom);
	},
	save({ attributes }) {
		const {
			tagName = 'div',
			backgroundColor,
			constrainWidth,
			contentWidth,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			overlayColor,
			shapeDividerTop,
			shapeDividerTopColor,
			shapeDividerTopBackgroundColor,
			shapeDividerTopHeight,
			shapeDividerTopWidth,
			shapeDividerTopFlipX,
			shapeDividerTopFlipY,
			shapeDividerTopFront,
			shapeDividerBottom,
			shapeDividerBottomColor,
			shapeDividerBottomBackgroundColor,
			shapeDividerBottomHeight,
			shapeDividerBottomWidth,
			shapeDividerBottomFlipX,
			shapeDividerBottomFlipY,
			shapeDividerBottomFront,
		} = attributes;

		// Previous behavior: shape color inherits background, shape background has no text color fallback
		const sectionBackgroundColor =
			attributes.style?.color?.background ||
			(backgroundColor
				? `var(--wp--preset--color--${backgroundColor})`
				: '');

		const className = [
			'dsgo-stack',
			!constrainWidth && 'dsgo-no-width-constraint',
			overlayColor && 'dsgo-stack--has-overlay',
			(shapeDividerTop || shapeDividerBottom) &&
				'dsgo-stack--has-shape-divider',
		]
			.filter(Boolean)
			.join(' ');

		const TagName = tagName || 'div';
		const blockProps = useBlockProps.save({
			className,
			style: {
				...(hoverBackgroundColor && {
					'--dsgo-hover-bg-color':
						convertPresetToCSSVar(hoverBackgroundColor),
				}),
				...(hoverTextColor && {
					'--dsgo-hover-text-color':
						convertPresetToCSSVar(hoverTextColor),
				}),
				...(hoverIconBackgroundColor && {
					'--dsgo-parent-hover-icon-bg': convertPresetToCSSVar(
						hoverIconBackgroundColor
					),
				}),
				...(hoverButtonBackgroundColor && {
					'--dsgo-parent-hover-button-bg': convertPresetToCSSVar(
						hoverButtonBackgroundColor
					),
				}),
				...(overlayColor && {
					'--dsgo-overlay-color': convertPresetToCSSVar(overlayColor),
					'--dsgo-overlay-opacity': '0.8',
				}),
			},
		});

		const innerStyle = {};
		if (constrainWidth) {
			innerStyle.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyle.marginLeft = 'auto';
			innerStyle.marginRight = 'auto';
		}

		if (shapeDividerTop) {
			innerStyle.paddingTop = `${shapeDividerTopHeight || 100}px`;
		}
		if (shapeDividerBottom) {
			innerStyle.paddingBottom = `${shapeDividerBottomHeight || 100}px`;
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-stack__inner',
			style: innerStyle,
		});

		return (
			<TagName {...blockProps}>
				<V4ShapeDivider
					shape={shapeDividerTop}
					color={
						convertPresetToCSSVar(shapeDividerTopColor) ||
						sectionBackgroundColor
					}
					backgroundColor={convertPresetToCSSVar(
						shapeDividerTopBackgroundColor
					)}
					height={shapeDividerTopHeight}
					width={shapeDividerTopWidth}
					flipX={shapeDividerTopFlipX}
					flipY={shapeDividerTopFlipY}
					front={shapeDividerTopFront}
					position="top"
				/>
				<div {...innerBlocksProps} />
				<V4ShapeDivider
					shape={shapeDividerBottom}
					color={
						convertPresetToCSSVar(shapeDividerBottomColor) ||
						sectionBackgroundColor
					}
					backgroundColor={convertPresetToCSSVar(
						shapeDividerBottomBackgroundColor
					)}
					height={shapeDividerBottomHeight}
					width={shapeDividerBottomWidth}
					flipX={shapeDividerBottomFlipX}
					flipY={shapeDividerBottomFlipY}
					front={shapeDividerBottomFront}
					position="bottom"
				/>
			</TagName>
		);
	},
	migrate(attributes) {
		return attributes;
	},
};

// Version 3: Shape dividers with currentColor fallback (before background color inheritance)
const v3 = {
	supports: sharedSupports,
	attributes: {
		align: { type: 'string', default: 'full' },
		tagName: { type: 'string', default: 'div' },
		constrainWidth: { type: 'boolean', default: true },
		contentWidth: { type: 'string', default: '' },
		style: { type: 'object' },
		hoverBackgroundColor: { type: 'string', default: '' },
		hoverTextColor: { type: 'string', default: '' },
		hoverIconBackgroundColor: { type: 'string', default: '' },
		hoverButtonBackgroundColor: { type: 'string', default: '' },
		overlayColor: { type: 'string', default: '' },
		shapeDividerTop: { type: 'string', default: '' },
		shapeDividerTopColor: { type: 'string', default: '' },
		shapeDividerTopHeight: { type: 'number', default: 100 },
		shapeDividerTopWidth: { type: 'number', default: 100 },
		shapeDividerTopFlipX: { type: 'boolean', default: false },
		shapeDividerTopFlipY: { type: 'boolean', default: false },
		shapeDividerTopFront: { type: 'boolean', default: false },
		shapeDividerTopBackgroundColor: { type: 'string', default: '' },
		shapeDividerBottom: { type: 'string', default: '' },
		shapeDividerBottomColor: { type: 'string', default: '' },
		shapeDividerBottomHeight: { type: 'number', default: 100 },
		shapeDividerBottomWidth: { type: 'number', default: 100 },
		shapeDividerBottomFlipX: { type: 'boolean', default: false },
		shapeDividerBottomFlipY: { type: 'boolean', default: false },
		shapeDividerBottomFront: { type: 'boolean', default: false },
		shapeDividerBottomBackgroundColor: { type: 'string', default: '' },
	},
	isEligible(attributes) {
		// Matches blocks with shape dividers from before background color inheritance
		return !!(attributes.shapeDividerTop || attributes.shapeDividerBottom);
	},
	save({ attributes }) {
		const {
			tagName = 'div',
			constrainWidth,
			contentWidth,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			overlayColor,
			shapeDividerTop,
			shapeDividerTopColor,
			shapeDividerTopBackgroundColor,
			shapeDividerTopHeight,
			shapeDividerTopWidth,
			shapeDividerTopFlipX,
			shapeDividerTopFlipY,
			shapeDividerTopFront,
			shapeDividerBottom,
			shapeDividerBottomColor,
			shapeDividerBottomBackgroundColor,
			shapeDividerBottomHeight,
			shapeDividerBottomWidth,
			shapeDividerBottomFlipX,
			shapeDividerBottomFlipY,
			shapeDividerBottomFront,
		} = attributes;

		const className = [
			'dsgo-stack',
			!constrainWidth && 'dsgo-no-width-constraint',
			overlayColor && 'dsgo-stack--has-overlay',
			(shapeDividerTop || shapeDividerBottom) &&
				'dsgo-stack--has-shape-divider',
		]
			.filter(Boolean)
			.join(' ');

		const TagName = tagName || 'div';
		const blockProps = useBlockProps.save({
			className,
			style: {
				...(hoverBackgroundColor && {
					'--dsgo-hover-bg-color':
						convertPresetToCSSVar(hoverBackgroundColor),
				}),
				...(hoverTextColor && {
					'--dsgo-hover-text-color':
						convertPresetToCSSVar(hoverTextColor),
				}),
				...(hoverIconBackgroundColor && {
					'--dsgo-parent-hover-icon-bg': convertPresetToCSSVar(
						hoverIconBackgroundColor
					),
				}),
				...(hoverButtonBackgroundColor && {
					'--dsgo-parent-hover-button-bg': convertPresetToCSSVar(
						hoverButtonBackgroundColor
					),
				}),
				...(overlayColor && {
					'--dsgo-overlay-color': convertPresetToCSSVar(overlayColor),
					'--dsgo-overlay-opacity': '0.8',
				}),
			},
		});

		const innerStyle = {};
		if (constrainWidth) {
			innerStyle.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyle.marginLeft = 'auto';
			innerStyle.marginRight = 'auto';
		}

		if (shapeDividerTop) {
			innerStyle.paddingTop = `${shapeDividerTopHeight || 100}px`;
		}
		if (shapeDividerBottom) {
			innerStyle.paddingBottom = `${shapeDividerBottomHeight || 100}px`;
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-stack__inner',
			style: innerStyle,
		});

		return (
			<TagName {...blockProps}>
				<OldShapeDivider
					shape={shapeDividerTop}
					color={convertPresetToCSSVar(shapeDividerTopColor)}
					backgroundColor={convertPresetToCSSVar(
						shapeDividerTopBackgroundColor
					)}
					height={shapeDividerTopHeight}
					width={shapeDividerTopWidth}
					flipX={shapeDividerTopFlipX}
					flipY={shapeDividerTopFlipY}
					front={shapeDividerTopFront}
					position="top"
				/>
				<div {...innerBlocksProps} />
				<OldShapeDivider
					shape={shapeDividerBottom}
					color={convertPresetToCSSVar(shapeDividerBottomColor)}
					backgroundColor={convertPresetToCSSVar(
						shapeDividerBottomBackgroundColor
					)}
					height={shapeDividerBottomHeight}
					width={shapeDividerBottomWidth}
					flipX={shapeDividerBottomFlipX}
					flipY={shapeDividerBottomFlipY}
					front={shapeDividerBottomFront}
					position="bottom"
				/>
			</TagName>
		);
	},
	migrate(attributes) {
		return attributes;
	},
};

// Version 2: Before shape dividers - current save without shape dividers
const v2 = {
	supports: sharedSupports,
	attributes: {
		align: {
			type: 'string',
			default: 'full',
		},
		tagName: {
			type: 'string',
			default: 'div',
		},
		constrainWidth: {
			type: 'boolean',
			default: true,
		},
		contentWidth: {
			type: 'string',
			default: '',
		},
		style: {
			type: 'object',
		},
		hoverBackgroundColor: {
			type: 'string',
			default: '',
		},
		hoverTextColor: {
			type: 'string',
			default: '',
		},
		hoverIconBackgroundColor: {
			type: 'string',
			default: '',
		},
		hoverButtonBackgroundColor: {
			type: 'string',
			default: '',
		},
		overlayColor: {
			type: 'string',
			default: '',
		},
	},
	/**
	 * Determine if this deprecation should be used
	 * Matches blocks created before shape dividers were added
	 *
	 * @param {Object} attributes Block attributes
	 * @return {boolean} True if block matches this deprecation
	 */
	isEligible(attributes) {
		// This deprecation is for blocks without shape divider attributes
		// If any shape divider attribute exists, this is not the right version
		// Use constrainWidth to distinguish v2 from v1 (v1 used layout.contentSize)
		return (
			!attributes.shapeDividerTop &&
			!attributes.shapeDividerBottom &&
			Object.prototype.hasOwnProperty.call(attributes, 'constrainWidth')
		);
	},
	save({ attributes }) {
		const {
			tagName = 'div',
			constrainWidth,
			contentWidth,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			overlayColor,
		} = attributes;

		// Build className with conditional no-width-constraint and overlay classes
		const className = [
			'dsgo-stack',
			!constrainWidth && 'dsgo-no-width-constraint',
			overlayColor && 'dsgo-stack--has-overlay',
		]
			.filter(Boolean)
			.join(' ');

		// Block wrapper props - outer div stays full width
		const TagName = tagName || 'div';
		const blockProps = useBlockProps.save({
			className,
			style: {
				...(hoverBackgroundColor && {
					'--dsgo-hover-bg-color':
						convertPresetToCSSVar(hoverBackgroundColor),
				}),
				...(hoverTextColor && {
					'--dsgo-hover-text-color':
						convertPresetToCSSVar(hoverTextColor),
				}),
				...(hoverIconBackgroundColor && {
					'--dsgo-parent-hover-icon-bg': convertPresetToCSSVar(
						hoverIconBackgroundColor
					),
				}),
				...(hoverButtonBackgroundColor && {
					'--dsgo-parent-hover-button-bg': convertPresetToCSSVar(
						hoverButtonBackgroundColor
					),
				}),
				...(overlayColor && {
					'--dsgo-overlay-color': convertPresetToCSSVar(overlayColor),
					'--dsgo-overlay-opacity': '0.8',
				}),
			},
		});

		// Inner container props with width constraints
		const innerStyle = {};
		if (constrainWidth) {
			innerStyle.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyle.marginLeft = 'auto';
			innerStyle.marginRight = 'auto';
		}

		// Merge inner blocks props without the outer block props
		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-stack__inner',
			style: innerStyle,
		});

		return (
			<TagName {...blockProps}>
				<div {...innerBlocksProps} />
			</TagName>
		);
	},
	migrate(attributes) {
		// Shape divider attributes default to empty/false, so no transformation needed
		return attributes;
	},
};

// Version 1: Before align attribute - used className for alignment
// Note: sharedSupports includes align, but it has no effect here because
// v1 blocks have no align attribute value — WordPress only emits alignment
// classes when the attribute is present and set.
const v1 = {
	supports: sharedSupports,
	attributes: {
		// Old blocks don't have align attribute, only className
		style: {
			type: 'object',
		},
		layout: {
			type: 'object',
		},
		contentWidth: {
			type: 'string',
		},
		hoverBackgroundColor: {
			type: 'string',
		},
		hoverTextColor: {
			type: 'string',
		},
		hoverIconBackgroundColor: {
			type: 'string',
		},
		hoverButtonBackgroundColor: {
			type: 'string',
		},
	},
	/**
	 * Determine if this deprecation should be used
	 * Matches blocks created before align attribute was added
	 *
	 * @param {Object} attributes Block attributes
	 * @return {boolean} True if block matches this deprecation
	 */
	isEligible(attributes) {
		// This deprecation is for the earliest blocks without align attribute
		// They used className for alignment instead
		return attributes.align === undefined;
	},
	save({ attributes }) {
		const {
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			layout,
			contentWidth,
		} = attributes;

		let contentSize;
		if (layout && 'contentSize' in layout) {
			contentSize = layout.contentSize;
		} else {
			contentSize = contentWidth || '1200px';
		}

		const className = [
			'dsgo-stack',
			!contentSize && 'dsgo-no-width-constraint',
		]
			.filter(Boolean)
			.join(' ');

		const blockProps = useBlockProps.save({
			className,
			style: {
				...(hoverBackgroundColor && {
					'--dsgo-hover-bg-color':
						convertPresetToCSSVar(hoverBackgroundColor),
				}),
				...(hoverTextColor && {
					'--dsgo-hover-text-color':
						convertPresetToCSSVar(hoverTextColor),
				}),
				...(hoverIconBackgroundColor && {
					'--dsgo-parent-hover-icon-bg': convertPresetToCSSVar(
						hoverIconBackgroundColor
					),
				}),
				...(hoverButtonBackgroundColor && {
					'--dsgo-parent-hover-button-bg': convertPresetToCSSVar(
						hoverButtonBackgroundColor
					),
				}),
			},
		});

		const innerStyle = {};
		if (contentSize) {
			innerStyle.maxWidth = contentSize;
			innerStyle.marginLeft = 'auto';
			innerStyle.marginRight = 'auto';
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-stack__inner',
			style: innerStyle,
		});

		return (
			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		);
	},
	migrate(oldAttributes) {
		// Extract align from className if it exists
		const className = oldAttributes.className || '';
		let align;

		if (className.includes('alignfull')) {
			align = 'full';
		} else if (className.includes('alignwide')) {
			align = 'wide';
		}

		// Remove align classes from className since they'll be auto-added by WordPress
		const cleanClassName = className
			.split(' ')
			.filter((cls) => cls !== 'alignfull' && cls !== 'alignwide')
			.join(' ')
			.trim();

		// Return migrated attributes
		return {
			...oldAttributes,
			align,
			className: cleanClassName || undefined,
		};
	},
};

// Export deprecations in reverse chronological order (newest first)
export default [v6, v5, v4, v3, v2, v1];

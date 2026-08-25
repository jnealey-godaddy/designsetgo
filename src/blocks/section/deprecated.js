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
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from './utils/has-overlay-style';
import { getDeprecatedBlockHTML } from '../../utils/deprecated-block-html';
import metadata from './block.json';
import currentSave from './save';
// The SAME predicate the live renderer uses. Migration and render must agree on
// what counts as an explicit size, or a pinned clearance can desync from the
// divider it is meant to clear — see isUntouchedLegacyShapeSize below.
import { isExplicitShapeSize } from '../../utils/shape-size';

// The height/width every shape-divider deprecation schema defaults to. A legacy
// block carrying this value never had it written to the block comment (
// WordPress omits default-valued attributes), so it cannot represent a
// deliberate author choice — it is simply "never touched".
const LEGACY_DEFAULT_SHAPE_SIZE = 100;

/**
 * Whether a legacy size attribute should be treated as "never touched", and so
 * collapsed to `null` to inherit the theme token.
 *
 * Two cases, and BOTH must route here or the divider desyncs from its
 * clearance:
 *
 * 1. The historical default (100) — indistinguishable from untouched, since
 *    WordPress omits default-valued attributes from the block comment.
 * 2. Anything the renderer will not accept as an explicit size. This delegates
 *    to the same `isExplicitShapeSize` predicate the live component uses rather
 *    than hand-rolling a second definition, because the two MUST agree. A
 *    legacy `shapeDividerTopHeight: 0` is reachable (the Abilities API's
 *    `configure-shape-divider` has always allowed `minimum => 0` for height),
 *    and if this treated 0 as explicit while the renderer treated it as unset,
 *    migrate() would pin `padding-top:0px` against a divider painting at the
 *    theme token height — putting content under the shape.
 *
 * @param {number|null|undefined} value Parsed size attribute.
 * @return {boolean} True when the value is indistinguishable from untouched.
 */
function isUntouchedLegacyShapeSize(value) {
	return !isExplicitShapeSize(value) || value === LEGACY_DEFAULT_SHAPE_SIZE;
}

/**
 * Migrate one position's legacy shape-divider size + clearance.
 *
 * Two cases, and the split matters:
 *
 * - **The author set an explicit height.** Preserve it, and carry the legacy
 *   height-derived clearance into `shapeDivider{Position}Spacing` as a raw CSS
 *   length (`${height}px`), exactly as before. The current save() serializes a
 *   raw length unchanged, so the stored padding is reproduced on next render.
 * - **The height was never touched (the historical default 100).** Collapse it
 *   to `null` so the block starts inheriting the theme.json height token like
 *   any untouched divider, and deliberately DO NOT pin a clearance. Pinning one
 *   here is the trap: the divider would resolve its height from the token while
 *   the clearance stayed frozen at 100px, so on a theme setting a 200px divider
 *   the content would sit under the shape. Leaving the clearance unset routes
 *   BOTH through the same `--wp--custom--designsetgo--shape-divider--height`
 *   fallback in `_shape-divider.scss`, so they cannot desync. With no token set
 *   the fallback is 100px — byte-identical rendering to the legacy output.
 *
 * Width is independent of clearance, so it collapses on its own terms.
 *
 * @param {Object} attributes Parsed block attributes.
 * @param {Object} migrated   Mutable migration target.
 * @param {string} position   'Top' or 'Bottom'.
 */
function migrateShapeDividerPosition(attributes, migrated, position) {
	if (!attributes[`shapeDivider${position}`]) {
		return;
	}

	const heightKey = `shapeDivider${position}Height`;
	const widthKey = `shapeDivider${position}Width`;
	const spacingKey = `shapeDivider${position}Spacing`;

	if (isUntouchedLegacyShapeSize(attributes[widthKey])) {
		migrated[widthKey] = null;
	}

	if (isUntouchedLegacyShapeSize(attributes[heightKey])) {
		migrated[heightKey] = null;
		return;
	}

	if (!attributes[spacingKey]) {
		migrated[spacingKey] = `${attributes[heightKey]}px`;
	}
}

/**
 * Carry a legacy shape divider's height-derived clearance and size attributes
 * onto the current schema. See `migrateShapeDividerPosition` for the per-
 * position rules.
 *
 * This MUST be shared by every shape-divider-era deprecation (v3–v9), not just
 * the newest: WordPress runs exactly ONE deprecation entry per stored block —
 * whichever version's save() reproduces the stored HTML — so a block that
 * matches an older signature (e.g. an overlay/hover variation missing its
 * activation class) never reaches v9.migrate(). If the carry-over lived only in
 * v9, that block would migrate successfully but silently lose its clearance,
 * since the current save() emits inner padding only when a spacing attribute is
 * set. See CLAUDE.md, "deprecations do not cascade".
 *
 * @param {Object} attributes Parsed block attributes.
 * @return {Object} Attributes with the carry-over applied.
 */
function migrateShapeDividerSpacing(attributes) {
	const migrated = { ...attributes };
	migrateShapeDividerPosition(attributes, migrated, 'Top');
	migrateShapeDividerPosition(attributes, migrated, 'Bottom');
	return migrated;
}

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

/**
 * V7ShapeDivider — frozen copy of the class-based divider as v7, v8 and v9
 * wrote it.
 *
 * Those three versions share the CURRENT class-based markup contract (mask
 * classes + `--dsgo-shape-*` custom properties, no inline <svg>), so they
 * originally rendered the live `ShapeDivider` component. That stopped being
 * safe once height/width became nullable: their attribute schemas still
 * default both to 100, and the live component now emits an explicit
 * `--dsgo-shape-height:100px` / `--dsgo-shape-width:100%` for that value
 * whereas the historical output emitted no size property at all. Sharing the
 * live component would therefore break byte-matching for every section saved
 * at the old default size and surface "unexpected or invalid content".
 *
 * Frozen here instead, at the emit-only-when-it-differs-from-100 contract.
 * Do not "simplify" this back to the live component.
 *
 * @param {Object}  root0           Component props
 * @param {string}  root0.shape     Shape slug or 'inherit'
 * @param {string}  root0.position  Position (top/bottom)
 * @param {number}  root0.height    Shape height in px
 * @param {number}  root0.width     Shape width percentage
 * @param {boolean} root0.flipX     Flip horizontal
 * @param {boolean} root0.flipY     Flip vertical
 * @param {boolean} root0.front     Bring to front
 * @param {string}  root0.bandColor Band color beside the shape
 */
function V7ShapeDivider({
	shape,
	position = 'top',
	height = 100,
	width = 100,
	flipX = false,
	flipY = false,
	front = false,
	bandColor,
}) {
	if (!shape) {
		return null;
	}

	const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
	const safeHeight = clamp(Number(height) || 100, 10, 500);
	const safeWidth = clamp(Number(width) || 100, 100, 300);
	const safeBandColor = sanitizeColor(bandColor);

	const flipYActive = position === 'bottom' ? !flipY : flipY;

	const className = [
		'dsgo-shape-divider',
		`dsgo-shape-divider--${position}`,
		`is-shape-${shape}`,
		flipX && 'is-flip-x',
		flipYActive && 'is-flip-y',
		front && 'is-front',
	]
		.filter(Boolean)
		.join(' ');

	const style = {
		...(safeHeight !== 100 && { '--dsgo-shape-height': `${safeHeight}px` }),
		...(safeWidth !== 100 && { '--dsgo-shape-width': `${safeWidth}%` }),
		...(safeBandColor && { '--dsgo-shape-band': safeBandColor }),
	};

	const styleProps = Object.keys(style).length > 0 ? { style } : {};

	return <div className={className} {...styleProps} aria-hidden="true" />;
}

// Version 10: Unconstrained inner container with no `constrainWidth` in the
// block comment. This entry is the CURRENT save(), reused verbatim; the only
// thing it changes is the default `constrainWidth` parses to — `false` instead
// of `true` — so a stored `.dsgo-stack__inner` carrying no width style is read
// back as "the constraint was off" rather than as broken markup.
//
// Two kinds of content land here, and the stored HTML says the same thing in
// both cases:
//
// 1. Sections saved during the 92 minutes on 2025-11-10 between 6cbf8183 (which
//    introduced `constrainWidth` with `default: false`) and 1bbdbefa (which
//    flipped it to `true`). WordPress omits default-valued attributes from the
//    block comment, so those sections stored no `constrainWidth` at all and are
//    indistinguishable — by attributes alone — from a current section that
//    simply left the toggle on.
// 2. Markup that never came from save(): generated or hand-edited content that
//    turns the constraint off the way it looks like it works, by putting
//    `dsgo-no-width-constraint` on `className`, and writes the inner container
//    with no style. The class is inert (the block reads `constrainWidth`), so
//    the current save() emits a `max-width` the stored HTML lacks.
//
// The attributes are ambiguous but the markup is not: an inner container with no
// width style is what an unconstrained section renders as, under every version
// of this block. migrate() therefore writes that intent back into
// `constrainWidth` explicitly, so it lands in the comment and the block stops
// depending on whatever the default happens to be.
//
// No isEligible: a section reaching this entry is INVALID (its stored HTML does
// not match the current save()), and WordPress skips isEligible for invalid
// blocks — it picks the version whose save() reproduces the stored HTML. A VALID
// section must never be claimed here, and none can be: one that left the toggle
// on stored the inner width style, which this save() does not emit under a
// `false` default, and one that explicitly turned it off already carries
// `"constrainWidth":false` in its comment and matches the current save() outright.
const v10 = {
	apiVersion: 3,
	// Current-era supports and schema, taken from block.json so they cannot drift
	// out of step with the save() below, which IS the current save().
	supports: metadata.supports,
	attributes: {
		...metadata.attributes,
		constrainWidth: { type: 'boolean', default: false },
	},
	save: currentSave,
	migrate(attributes) {
		return { ...attributes, constrainWidth: false };
	},
};

// Version 9: Height-derived pixel clearance padding. Before this version the
// inner container's shape-divider clearance was computed from the divider
// height — `padding-top:${shapeDividerTopHeight || 100}px` (and the bottom
// equivalent) — so save() could only ever emit a px value the author could not
// control. The current save() instead serializes the block-user-defined
// `shapeDividerTopSpacing` / `shapeDividerBottomSpacing` attributes (a WordPress
// spacing token) and emits NOTHING when they are unset. Sections saved before
// this change carry the px padding in their stored HTML while the new save()
// emits none — a markup mismatch that invalidates the block.
//
// This is a markup-change deprecation: WordPress reaches it by byte-matching
// this frozen save() against the stored HTML of an INVALID block (isEligible is
// skipped for invalid blocks), so no isEligible is declared. save() reproduces
// the pre-change output exactly — including the hover-variation activation
// classes the current save() derives (v8 predates those, so v9 must sit ahead
// of v8 to claim divider blocks that also carry a hover variation). migrate()
// maps the old height-derived px value into the new spacing attribute as a raw
// CSS length so the exact clearance is preserved and the current save() round-
// trips it byte-for-byte.
const v9 = {
	apiVersion: 3,
	supports: sharedSupports,
	attributes: {
		align: { type: 'string', default: 'full' },
		tagName: { type: 'string', default: 'div' },
		constrainWidth: { type: 'boolean', default: true },
		contentWidth: { type: 'string', default: '' },
		// Mirror block.json's `style` default (see v7's identical note).
		style: {
			type: 'object',
			default: {
				spacing: {
					padding: {
						top: 'var:preset|spacing|50',
						bottom: 'var:preset|spacing|50',
						left: 'var:preset|spacing|30',
						right: 'var:preset|spacing|30',
					},
				},
			},
		},
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
			shapeDividerTopBackgroundColor,
			shapeDividerTopHeight,
			shapeDividerTopWidth,
			shapeDividerTopFlipX,
			shapeDividerTopFlipY,
			shapeDividerTopFront,
			shapeDividerBottom,
			shapeDividerBottomBackgroundColor,
			shapeDividerBottomHeight,
			shapeDividerBottomWidth,
			shapeDividerBottomFlipX,
			shapeDividerBottomFlipY,
			shapeDividerBottomFront,
		} = attributes;

		const shapeDividerTopBandColor = convertColorToCSSVar(
			shapeDividerTopBackgroundColor
		);
		const shapeDividerBottomBandColor = convertColorToCSSVar(
			shapeDividerBottomBackgroundColor
		);

		// Current-era className: overlay class from overlayColor OR overlay
		// variation, plus hover-variation activation classes.
		const hasOverlay =
			!!overlayColor || hasOverlayStyleClass(attributes.className);
		const className = [
			'dsgo-stack',
			!constrainWidth && 'dsgo-no-width-constraint',
			hasOverlay && 'dsgo-stack--has-overlay',
			(shapeDividerTop || shapeDividerBottom) &&
				'dsgo-stack--has-shape-divider',
			...hoverVariationClasses(attributes.className),
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

		// Old behavior: clearance padding derived from the divider height.
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
				<V7ShapeDivider
					shape={shapeDividerTop}
					position="top"
					height={shapeDividerTopHeight}
					width={shapeDividerTopWidth}
					flipX={shapeDividerTopFlipX}
					flipY={shapeDividerTopFlipY}
					front={shapeDividerTopFront}
					bandColor={shapeDividerTopBandColor}
				/>
				<div {...innerBlocksProps} />
				<V7ShapeDivider
					shape={shapeDividerBottom}
					position="bottom"
					height={shapeDividerBottomHeight}
					width={shapeDividerBottomWidth}
					flipX={shapeDividerBottomFlipX}
					flipY={shapeDividerBottomFlipY}
					front={shapeDividerBottomFront}
					bandColor={shapeDividerBottomBandColor}
				/>
			</TagName>
		);
	},
	migrate(attributes) {
		// Preserve the exact clearance by carrying the old height-derived px
		// value into the new spacing attribute as a raw CSS length.
		return migrateShapeDividerSpacing(attributes);
	},
};

// Version 8: Pre-hover-variation-classes output. The current save() also
// emits `dsgo-stack--has-hover-text` / `-icon` / `-button` when a style-kit
// hover variation (`is-style-hover-{text,icon,button}-*`) is present on
// className, so the corresponding `!important` hover override can activate
// from a variation's stylesheet instead of only the inline-style gate.
// Sections saved with such a variation but no matching `dsgo-stack--has-hover-*`
// class in their stored HTML therefore mismatch the current save() — an
// "invalid content" mismatch, the same failure mode v7 fixes for the overlay
// class.
//
// isEligible targets that signature (a hover-variation family present on
// className whose activation class is missing from the stored HTML) so those
// blocks migrate SILENTLY. save() reproduces the pre-hover-variation-classes
// output — i.e. v7's *current* (non-deprecated) behavior at the time hover
// classes were added, which already includes the overlay-variation class
// derivation from v7/60c99058, just without the hover activation classes —
// so it also byte-matches on WP versions that still validate the
// deprecation's save() before migrating. migrate() is a passthrough — only
// the serialised class differs, not the attribute values; the current save()
// then re-derives the hover classes from the variation.
const v8 = {
	supports: sharedSupports,
	attributes: {
		align: { type: 'string', default: 'full' },
		tagName: { type: 'string', default: 'div' },
		constrainWidth: { type: 'boolean', default: true },
		contentWidth: { type: 'string', default: '' },
		// Mirror block.json's `style` default (see v7's identical note).
		style: {
			type: 'object',
			default: {
				spacing: {
					padding: {
						top: 'var:preset|spacing|50',
						bottom: 'var:preset|spacing|50',
						left: 'var:preset|spacing|30',
						right: 'var:preset|spacing|30',
					},
				},
			},
		},
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
	 * Silently migrate sections that carry a style-kit hover variation
	 * (`is-style-hover-{text,icon,button}-*`) but whose stored HTML predates
	 * the matching `dsgo-stack--has-hover-*` activation class being derived
	 * from that variation.
	 *
	 * @param {Object} attributes      Block attributes.
	 * @param {Array}  innerBlocks     Inner blocks.
	 * @param {Object} extra           Extra data.
	 * @param {Object} extra.blockNode Raw parsed block (carries innerHTML).
	 * @param {Object} extra.block     Parsed block (carries originalContent).
	 * @return {boolean} True when a hover-variation family is present without
	 *                    its matching activation class in the stored HTML.
	 */
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		if (!innerHTML || !innerHTML.includes('dsgo-stack')) {
			return false;
		}

		return hoverVariationClasses(attributes.className).some(
			(activationClass) => !innerHTML.includes(activationClass)
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
			shapeDividerTop,
			shapeDividerTopBackgroundColor,
			shapeDividerTopHeight,
			shapeDividerTopWidth,
			shapeDividerTopFlipX,
			shapeDividerTopFlipY,
			shapeDividerTopFront,
			shapeDividerBottom,
			shapeDividerBottomBackgroundColor,
			shapeDividerBottomHeight,
			shapeDividerBottomWidth,
			shapeDividerBottomFlipX,
			shapeDividerBottomFlipY,
			shapeDividerBottomFront,
		} = attributes;

		const shapeDividerTopBandColor = convertColorToCSSVar(
			shapeDividerTopBackgroundColor
		);
		const shapeDividerBottomBandColor = convertColorToCSSVar(
			shapeDividerBottomBackgroundColor
		);

		// Pre-hover-variation-classes className: overlay class already derives
		// from the style variation (v7/60c99058), but no hover activation classes.
		const hasOverlay =
			!!overlayColor || hasOverlayStyleClass(attributes.className);
		const className = [
			'dsgo-stack',
			!constrainWidth && 'dsgo-no-width-constraint',
			hasOverlay && 'dsgo-stack--has-overlay',
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

		return (
			<TagName {...blockProps}>
				<V7ShapeDivider
					shape={shapeDividerTop}
					position="top"
					height={shapeDividerTopHeight}
					width={shapeDividerTopWidth}
					flipX={shapeDividerTopFlipX}
					flipY={shapeDividerTopFlipY}
					front={shapeDividerTopFront}
					bandColor={shapeDividerTopBandColor}
				/>
				<div {...innerBlocksProps} />
				<V7ShapeDivider
					shape={shapeDividerBottom}
					position="bottom"
					height={shapeDividerBottomHeight}
					width={shapeDividerBottomWidth}
					flipX={shapeDividerBottomFlipX}
					flipY={shapeDividerBottomFlipY}
					front={shapeDividerBottomFront}
					bandColor={shapeDividerBottomBandColor}
				/>
			</TagName>
		);
	},
	migrate(attributes) {
		// The serialised hover-activation classes differ (the current save()
		// derives them from the style variation on className). Also carry the
		// legacy height-derived clearance into the new spacing attribute — a
		// block matching THIS signature never reaches v9.migrate().
		return migrateShapeDividerSpacing(attributes);
	},
};

// Version 7: Overlay class from overlayColor only (before style-kit overlay
// variations). The current save() also emits `dsgo-stack--has-overlay` when a
// style-kit overlay variation (`is-style-overlay-*`) is present on className,
// so the overlay color can move out of the `overlayColor` attribute and into
// the variation's stylesheet. Sections saved with such a variation but no
// `overlayColor` therefore lack `dsgo-stack--has-overlay` in their stored HTML
// while the new save() adds it — an "invalid content" mismatch.
//
// isEligible targets exactly that signature (overlay variation on className +
// no overlay class in the stored HTML) so those blocks migrate SILENTLY. save()
// reproduces the pre-change output (overlay class from `overlayColor` only) so
// it also byte-matches on WP versions that still validate the deprecation's
// save() before migrating. migrate() is a passthrough — only the serialised
// class differs, not the attribute values; the current save() then re-renders
// the block with the overlay class derived from the variation.
const v7 = {
	supports: sharedSupports,
	attributes: {
		align: { type: 'string', default: 'full' },
		tagName: { type: 'string', default: 'div' },
		constrainWidth: { type: 'boolean', default: true },
		contentWidth: { type: 'string', default: '' },
		// Mirror block.json's `style` default. Without it, migration parses
		// `style` as undefined and v7.save() omits the default spacing padding,
		// so it no longer byte-matches stored markup (which carries the padding)
		// and the deprecation never fires. Must stay in sync with block.json.
		style: {
			type: 'object',
			default: {
				spacing: {
					padding: {
						top: 'var:preset|spacing|50',
						bottom: 'var:preset|spacing|50',
						left: 'var:preset|spacing|30',
						right: 'var:preset|spacing|30',
					},
				},
			},
		},
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
	 * Silently migrate sections that carry a style-kit overlay variation
	 * (`is-style-overlay-*`) but whose stored HTML predates the overlay class
	 * being derived from that variation.
	 *
	 * @param {Object} attributes      Block attributes.
	 * @param {Array}  innerBlocks     Inner blocks.
	 * @param {Object} extra           Extra data.
	 * @param {Object} extra.blockNode Raw parsed block (carries innerHTML).
	 * @param {Object} extra.block     Parsed block (carries originalContent).
	 * @return {boolean} True when the pre-variation overlay signature is found.
	 */
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		return !!(
			hasOverlayStyleClass(attributes.className) &&
			innerHTML &&
			innerHTML.includes('dsgo-stack') &&
			!innerHTML.includes('dsgo-stack--has-overlay')
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
			shapeDividerTop,
			shapeDividerTopBackgroundColor,
			shapeDividerTopHeight,
			shapeDividerTopWidth,
			shapeDividerTopFlipX,
			shapeDividerTopFlipY,
			shapeDividerTopFront,
			shapeDividerBottom,
			shapeDividerBottomBackgroundColor,
			shapeDividerBottomHeight,
			shapeDividerBottomWidth,
			shapeDividerBottomFlipX,
			shapeDividerBottomFlipY,
			shapeDividerBottomFront,
		} = attributes;

		const shapeDividerTopBandColor = convertColorToCSSVar(
			shapeDividerTopBackgroundColor
		);
		const shapeDividerBottomBandColor = convertColorToCSSVar(
			shapeDividerBottomBackgroundColor
		);

		// Pre-change className: overlay class from overlayColor ONLY.
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

		return (
			<TagName {...blockProps}>
				<V7ShapeDivider
					shape={shapeDividerTop}
					position="top"
					height={shapeDividerTopHeight}
					width={shapeDividerTopWidth}
					flipX={shapeDividerTopFlipX}
					flipY={shapeDividerTopFlipY}
					front={shapeDividerTopFront}
					bandColor={shapeDividerTopBandColor}
				/>
				<div {...innerBlocksProps} />
				<V7ShapeDivider
					shape={shapeDividerBottom}
					position="bottom"
					height={shapeDividerBottomHeight}
					width={shapeDividerBottomWidth}
					flipX={shapeDividerBottomFlipX}
					flipY={shapeDividerBottomFlipY}
					front={shapeDividerBottomFront}
					bandColor={shapeDividerBottomBandColor}
				/>
			</TagName>
		);
	},
	migrate(attributes) {
		// The serialised overlay class differs (the current save() derives it
		// from the style variation on className). Also carry the legacy
		// height-derived clearance into the new spacing attribute — a block
		// matching THIS signature never reaches v9.migrate().
		return migrateShapeDividerSpacing(attributes);
	},
};

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
	 * @param {Object} extra           - Extra data
	 * @param {Object} extra.blockNode - Raw parsed block (carries innerHTML)
	 * @param {Object} extra.block     - Parsed block (carries originalContent)
	 * @return {boolean} True when the legacy animation-attrs pattern is detected
	 */
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
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
		// Carry the legacy height-derived clearance into the new spacing
		// attribute. A block matching THIS signature never reaches v9.migrate(),
		// so the carry-over must run here too (see migrateShapeDividerSpacing).
		return migrateShapeDividerSpacing(attributes);
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
	// No isEligible: markup-change deprecation, reached by save-matching on an
	// INVALID block (WordPress skips isEligible for those). The old guard was
	// just "has a shape divider", which is equally true of a CURRENT section —
	// three separate versions shared it, so a divider claimed all three.
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
		// Carry the legacy height-derived clearance into the new spacing
		// attribute. A block matching THIS signature never reaches v9.migrate(),
		// so the carry-over must run here too (see migrateShapeDividerSpacing).
		return migrateShapeDividerSpacing(attributes);
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
	// No isEligible: markup-change deprecation, reached by save-matching on an
	// INVALID block (WordPress skips isEligible for those). The old guard was
	// just "has a shape divider", which is equally true of a CURRENT section —
	// three separate versions shared it, so a divider claimed all three.
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
		// Carry the legacy height-derived clearance into the new spacing
		// attribute. A block matching THIS signature never reaches v9.migrate(),
		// so the carry-over must run here too (see migrateShapeDividerSpacing).
		return migrateShapeDividerSpacing(attributes);
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
	// No isEligible: markup-change deprecation, reached by save-matching on an
	// INVALID block (WordPress skips isEligible for those). The old guard was
	// just "has a shape divider", which is equally true of a CURRENT section —
	// three separate versions shared it, so a divider claimed all three.
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
		// Carry the legacy height-derived clearance into the new spacing
		// attribute. A block matching THIS signature never reaches v9.migrate(),
		// so the carry-over must run here too (see migrateShapeDividerSpacing).
		return migrateShapeDividerSpacing(attributes);
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
	// Matches blocks created before shape dividers were added.
	//
	// No isEligible: markup-change deprecation, reached by save-matching on an
	// INVALID block (WordPress skips isEligible for those). The old guard keyed on
	// `constrainWidth` being present in the comment, which is true of any CURRENT
	// section whose constrainWidth is non-default — so it claimed current content.
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
	// No isEligible: markup-change deprecation, reached by save-matching on an
	// INVALID block (WordPress skips isEligible for those). The old guard,
	// `attributes.align === undefined`, matched nearly every CURRENT section:
	// `align` has no default, so it is absent from the raw comment attributes on
	// any section the author never aligned wide/full.
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
export default [v10, v9, v8, v7, v6, v5, v4, v3, v2, v1];

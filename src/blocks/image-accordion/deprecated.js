/**
 * Image Accordion Block - Deprecated Versions
 *
 * Handles backward compatibility for blocks saved with previous versions.
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { hasExplicitString } from '../../utils/has-explicit-value';

/**
 * Strip overlay attributes whose stored value still equals the historical
 * default so migrated content inherits the scrim from the theme token
 * (--wp--custom--designsetgo--image-accordion--overlay-*) instead of a baked-in
 * value. An explicitly-customised overlay (any value other than the old default)
 * is preserved. Every other attribute — height, gap, triggerType, etc. — passes
 * through untouched, so height/gap pinning in v1 is unaffected.
 *
 * Old defaults: overlayColor #000000, overlayOpacity 40, overlayOpacityExpanded
 * 20. These were serialized into every block's markup because block.json carried
 * them as attribute defaults; dropping the attribute here lets the current
 * save() omit the custom property so the stylesheet default can own it.
 *
 * @param {Object} attributes Attributes parsed under a deprecated schema.
 * @return {Object} A new attributes object with default-valued overlay dropped.
 */
function migrateOverlayDefaultsToInherit(attributes) {
	const next = { ...attributes };
	if (next.overlayColor === '#000000') {
		delete next.overlayColor;
	}
	if (next.overlayOpacity === 40) {
		delete next.overlayOpacity;
	}
	if (next.overlayOpacityExpanded === 20) {
		delete next.overlayOpacityExpanded;
	}
	return next;
}

/**
 * Shared supports definition for the deprecated version.
 * Mirrors the current block.json supports.
 */
const sharedSupports = {
	anchor: true,
	align: ['wide', 'full'],
	html: false,
	inserter: true,
	spacing: {
		margin: true,
		padding: false,
		blockGap: false,
		__experimentalDefaultControls: {
			margin: true,
		},
	},
	color: {
		background: false,
		text: true,
		link: true,
		__experimentalDefaultControls: {
			text: true,
		},
	},
	typography: {
		fontSize: true,
		lineHeight: true,
		__experimentalDefaultControls: {
			fontSize: true,
		},
		__experimentalFontFamily: true,
		__experimentalFontWeight: true,
	},
};

/**
 * Version 1: Before the themeable height/gap refactor
 *
 * The pre-refactor format always baked `--dsgo-image-accordion-height` and
 * `--dsgo-image-accordion-gap` inline on the root element (from attribute
 * defaults "500px" / "4px"), so patterns had to override them with magic
 * numbers and Style Kits could not retheme them. The current version omits both
 * custom properties when the author leaves them unset and lets the stylesheet
 * default (`.dsgo-image-accordion`, resolving through the theme token then the
 * literal fallback) own them; an explicit author value is still written inline.
 *
 * The deprecated attribute schema keeps the old "500px" / "4px" defaults so an
 * implicit-default old block re-parses to those values; `migrate` is a
 * passthrough that PINS whatever height/gap the old markup carried, so an
 * existing accordion keeps rendering exactly as authored. It intentionally does
 * NOT strip default-valued height/gap back to "inherit": in the old format an
 * explicit "500px"/"4px" and an implicit default are byte-identical, so
 * stripping could silently un-pin a value the author deliberately chose and let
 * a later Style Kit / theme.json change it. New content and patterns get the
 * themeable default by simply omitting the attribute (current save()).
 *
 * There is deliberately NO `isEligible` here. Only genuinely old content needs
 * migrating, and that content is invalid against the current save() (which omits
 * the props it always baked), so it reaches this deprecation through the normal
 * save()-matching path regardless. An `isEligible` would only add the ability to
 * force-migrate blocks that are ALREADY valid — and because the old "always
 * both props inline" markup is byte-identical to a current block that simply
 * sets both height and gap explicitly, such a check can't tell them apart and
 * would needlessly route valid, deliberately-customized accordions through
 * migrate() on every parse. Leaving it out means valid content is skipped and
 * only invalid old markup migrates.
 */
const v1 = {
	supports: sharedSupports,

	attributes: {
		height: {
			type: 'string',
			default: '500px',
		},
		gap: {
			type: 'string',
			default: '4px',
		},
		expandedRatio: {
			type: 'number',
			default: 3,
		},
		transitionDuration: {
			type: 'string',
			default: '0.5s',
		},
		enableOverlay: {
			type: 'boolean',
			default: true,
		},
		overlayColor: {
			type: 'string',
			default: '#000000',
		},
		overlayOpacity: {
			type: 'number',
			default: 40,
		},
		overlayOpacityExpanded: {
			type: 'number',
			default: 20,
		},
		triggerType: {
			type: 'string',
			default: 'hover',
			enum: ['hover', 'click'],
		},
		defaultExpanded: {
			type: 'number',
			default: 0,
		},
	},

	save({ attributes }) {
		const {
			height,
			gap,
			expandedRatio,
			transitionDuration,
			enableOverlay,
			overlayColor,
			overlayOpacity,
			overlayOpacityExpanded,
			triggerType,
			defaultExpanded,
		} = attributes;

		// Same classes as edit.js - MUST MATCH EXACTLY
		const accordionClasses = classnames('dsgo-image-accordion', {
			'dsgo-image-accordion--hover': triggerType === 'hover',
			'dsgo-image-accordion--click': triggerType === 'click',
		});

		// OLD: height and gap were always baked inline from the attribute defaults.
		// Note: Unitless values must be strings to prevent React from adding 'px'
		const customStyles = {
			'--dsgo-image-accordion-height': height,
			'--dsgo-image-accordion-gap': gap,
			'--dsgo-image-accordion-expanded-ratio': String(expandedRatio), // Unitless
			'--dsgo-image-accordion-transition': transitionDuration,
			'--dsgo-image-accordion-overlay-color':
				convertColorToCSSVar(overlayColor),
			'--dsgo-image-accordion-overlay-opacity': String(
				overlayOpacity / 100
			), // Unitless
			'--dsgo-image-accordion-overlay-opacity-expanded': String(
				overlayOpacityExpanded / 100
			), // Unitless
		};

		// Use .save() variant for save function
		const blockProps = useBlockProps.save({
			className: accordionClasses,
			style: customStyles,
			'data-trigger-type': triggerType,
			'data-default-expanded': defaultExpanded,
			'data-enable-overlay': enableOverlay,
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-image-accordion__items',
		});

		return (
			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		);
	},

	migrate(attributes) {
		// Pin whatever height/gap the old markup carried (they are re-parsed from
		// this schema's "500px"/"4px" defaults for an implicit-default block) so
		// the accordion keeps its authored dimensions — height/gap can't tell an
		// explicit default from an implicit one, so we never strip them. The
		// overlay is different: v1-era markup ALWAYS baked all three overlay props
		// from block.json defaults, so a default-valued overlay here is provably
		// implicit and is dropped to inherit the theme token (same policy as v2).
		return migrateOverlayDefaultsToInherit(attributes);
	},
};

/**
 * Version 2: Before the overlay props became themeable (always baked inline)
 *
 * This is the format that shipped between the height/gap refactor (v1) and the
 * overlay refactor: height/gap already omit-when-unset, but the three overlay
 * custom properties (`--dsgo-image-accordion-overlay-color` / `-opacity` /
 * `-opacity-expanded`) were STILL always written inline, filled from the
 * block.json attribute defaults (#000000 / 40 / 20) whenever the author left them
 * unset. A pattern or author therefore could not remove the scrim's color/opacity
 * from the HTML — save() regenerated them from the defaults on the next parse.
 *
 * The current save() writes each overlay property ONLY when the author set it,
 * so the stylesheet default (parent var → theme token → literal) owns the rest
 * while the scrim stays enabled. Any existing block that relied on an overlay
 * default now mismatches the current save() and is routed here.
 *
 * `save()` reproduces the always-baked overlay markup exactly (the overlay
 * attributes carry their old defaults so an unset value re-parses to the baked
 * number). `migrate` drops overlay attributes still equal to the old default so
 * the block inherits the theme token; an explicitly customised overlay value is
 * preserved. There is deliberately NO `isEligible`: a block that set all three
 * overlay props to non-default values already serializes identically under the
 * current save() (nothing to migrate), and a partial/default block is invalid
 * against the current save() so it reaches this deprecation through normal
 * save()-matching. This mirrors the isEligible-free policy already used for v1
 * (see commit "drop force-migrating isEligible from image-accordion").
 */
const v2 = {
	supports: sharedSupports,

	attributes: {
		height: {
			type: 'string',
		},
		gap: {
			type: 'string',
		},
		expandedRatio: {
			type: 'number',
			default: 3,
		},
		transitionDuration: {
			type: 'string',
			default: '0.5s',
		},
		enableOverlay: {
			type: 'boolean',
			default: true,
		},
		overlayColor: {
			type: 'string',
			default: '#000000',
		},
		overlayOpacity: {
			type: 'number',
			default: 40,
		},
		overlayOpacityExpanded: {
			type: 'number',
			default: 20,
		},
		triggerType: {
			type: 'string',
			default: 'hover',
			enum: ['hover', 'click'],
		},
		defaultExpanded: {
			type: 'number',
			default: 0,
		},
	},

	save({ attributes }) {
		const {
			height,
			gap,
			expandedRatio,
			transitionDuration,
			enableOverlay,
			overlayColor,
			overlayOpacity,
			overlayOpacityExpanded,
			triggerType,
			defaultExpanded,
		} = attributes;

		// Same classes as edit.js - MUST MATCH EXACTLY
		const accordionClasses = classnames('dsgo-image-accordion', {
			'dsgo-image-accordion--hover': triggerType === 'hover',
			'dsgo-image-accordion--click': triggerType === 'click',
		});

		// v2 trait: height/gap already omit-when-unset, but the three overlay
		// props were ALWAYS written (from the #000000 / 40 / 20 defaults).
		const hasExplicitHeight = hasExplicitString(height);
		const hasExplicitGap = hasExplicitString(gap);

		// Note: Unitless values must be strings to prevent React from adding 'px'
		const customStyles = {
			...(hasExplicitHeight && {
				'--dsgo-image-accordion-height': height,
			}),
			...(hasExplicitGap && { '--dsgo-image-accordion-gap': gap }),
			'--dsgo-image-accordion-expanded-ratio': String(expandedRatio), // Unitless
			'--dsgo-image-accordion-transition': transitionDuration,
			'--dsgo-image-accordion-overlay-color':
				convertColorToCSSVar(overlayColor),
			'--dsgo-image-accordion-overlay-opacity': String(
				overlayOpacity / 100
			), // Unitless
			'--dsgo-image-accordion-overlay-opacity-expanded': String(
				overlayOpacityExpanded / 100
			), // Unitless
		};

		const blockProps = useBlockProps.save({
			className: accordionClasses,
			style: customStyles,
			'data-trigger-type': triggerType,
			'data-default-expanded': defaultExpanded,
			'data-enable-overlay': enableOverlay,
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-image-accordion__items',
		});

		return (
			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		);
	},

	migrate(attributes) {
		// Drop default-valued overlay props so the block inherits the theme
		// token; explicit overlay values and every other attribute pass through.
		return migrateOverlayDefaultsToInherit(attributes);
	},
};

// Newest-first: v2 (overlay always baked, height/gap already conditional) is
// newer than v1 (height/gap also always baked). WordPress tries them in this
// order after the current save() fails to match.
export default [v2, v1];

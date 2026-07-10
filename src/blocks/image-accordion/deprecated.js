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
		// Passthrough: pin whatever height/gap the old markup carried (they are
		// re-parsed from this schema's "500px"/"4px" defaults for an
		// implicit-default block) so the accordion renders exactly as before.
		// We deliberately do not strip default values back to "inherit" — old
		// content can't distinguish an explicit default from an implicit one, so
		// stripping risks silently changing an author's deliberate choice.
		return attributes;
	},
};

/**
 * Version 2: Before overlay output was gated on `enableOverlay`
 *
 * This format (post height/gap refactor) always baked the three overlay custom
 * properties (`--dsgo-image-accordion-overlay-color` / `-opacity` /
 * `-opacity-expanded`) and a `data-enable-overlay` marker onto the root element,
 * even when the overlay was disabled. Because the item's overlay pseudo-element
 * is gated on the `--has-overlay` class (driven by the enableOverlay context,
 * not this markup), those properties were dead weight whenever the overlay was
 * off — and, more importantly, an author could not remove them from a pattern's
 * HTML because save() regenerated them from the (still-set) attributes.
 *
 * The current save() emits the overlay properties and `data-enable-overlay` ONLY
 * when `enableOverlay` is true. The enabled output is byte-identical to this
 * version, so overlay-on blocks (the overwhelming majority, since enableOverlay
 * defaults true) match the current save() directly and never reach this
 * deprecation. Only overlay-off blocks — which this version serialized WITH the
 * now-omitted overlay properties and `data-enable-overlay="false"` — mismatch the
 * current save() and are routed here so they re-serialize into the leaner format.
 *
 * `migrate` is a passthrough: the attributes already describe the block exactly
 * (enableOverlay:false plus whatever overlay values were set), so no attribute
 * change is needed — only a re-save into the current markup. There is no
 * `isEligible`: the enabled output is byte-identical to the current save(), so
 * a valid overlay-on block can't be told apart from current markup and must not
 * be force-migrated; only the genuinely stale overlay-off markup reaches here.
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

		// Height/gap already omit-when-unset (post-refactor). The distinguishing
		// trait of this version is that the overlay props and data-enable-overlay
		// were ALWAYS written, regardless of enableOverlay.
		const hasExplicitHeight =
			typeof height === 'string' && height.trim() !== '';
		const hasExplicitGap = typeof gap === 'string' && gap.trim() !== '';

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
		// Passthrough: attributes already describe the block; only the markup
		// changes (overlay props/data-enable-overlay dropped when disabled).
		return attributes;
	},
};

// Newest-first: v2 (overlay always emitted) is newer than v1 (height/gap
// always inline). WordPress tries them in this order after the current save().
export default [v2, v1];

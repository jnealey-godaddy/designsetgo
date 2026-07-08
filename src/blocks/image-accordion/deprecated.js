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

export default [v1];

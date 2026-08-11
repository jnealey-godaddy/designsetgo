/**
 * Grid Block - Deprecated versions
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from '../../utils/style-variation-classes';
import metadata from './block.json';
import { getDeprecatedBlockHTML } from '../../utils/deprecated-block-html';

// Captures the column min width from a `minmax(<width>, 1fr)` grid track.
const MIN_WIDTH_RE = /minmax\(\s*(\d+(?:\.\d+)?[a-z%]+)\s*,\s*1fr\s*\)/i;

const sharedSupports = {
	anchor: true,
	align: ['wide', 'full'],
	html: false,
	inserter: true,
	layout: {
		allowSwitching: false,
		allowInheriting: false,
		allowEditing: false,
		allowSizingOnChildren: true,
		allowContentEditing: false,
		default: {
			type: 'constrained',
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

// Before style-kit overlay/hover variation detection (and, for overlay,
// before the `overlayColor` attribute existed at all). The current save()
// emits `dsgo-grid--has-overlay` when a style-kit overlay variation
// (`is-style-overlay-*`) is present on className (or `overlayColor` is set),
// and emits `dsgo-grid--has-hover-{text,icon,button}` activation classes for
// the matching `is-style-hover-*` variation families — mirroring Section's
// behavior. Grids saved with such a variation but no matching class in their
// stored HTML fail validation against the new save().
//
// Note the asymmetry with Row's equivalent deprecation: the overlay branch
// here can ONLY ever be reached via a className variation, never via
// `overlayColor` — that attribute didn't exist on Grid before this change, so
// no stored Grid content could have set it. That's intentional, not a gap to
// "fix" for symmetry.
//
// isEligible targets exactly that signature (a variation on className with
// no matching class in the stored HTML) so those grids migrate SILENTLY.
// save() reproduces this file's pre-change output (no overlay logic at all,
// no hover activation classes) so it also byte-matches on WP versions that
// still validate the deprecation's save() before migrating. migrate() is a
// passthrough — only the serialised class differs, not the attribute
// values; the current save() then re-renders with the classes derived from
// the variation (and, for overlay, the new overlayColor attribute default
// of '').
const styleVariationClasses = {
	supports: metadata.supports,
	attributes: { ...metadata.attributes },
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		if (!innerHTML || !innerHTML.includes('dsgo-grid')) {
			return false;
		}

		const overlayMismatch =
			hasOverlayStyleClass(attributes.className) &&
			!innerHTML.includes('dsgo-grid--has-overlay');

		const hoverMismatch = hoverVariationClasses(
			attributes.className,
			'dsgo-grid'
		).some((activationClass) => !innerHTML.includes(activationClass));

		return overlayMismatch || hoverMismatch;
	},
	save({ attributes }) {
		const {
			tagName = 'div',
			constrainWidth,
			contentWidth,
			desktopColumns,
			tabletColumns,
			mobileColumns,
			rowGap,
			columnGap,
			alignItems,
			columnMinWidth,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			style,
		} = attributes;

		// Pre-change className: no overlay support at all, no hover
		// activation classes.
		const className = [
			'dsgo-grid',
			`dsgo-grid-cols-${desktopColumns}`,
			`dsgo-grid-cols-tablet-${tabletColumns}`,
			`dsgo-grid-cols-mobile-${mobileColumns}`,
			!constrainWidth && 'dsgo-no-width-constraint',
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
			},
		});

		const blockGapValue = style?.spacing?.blockGap;
		const isBlockGapObject =
			typeof blockGapValue === 'object' && blockGapValue !== null;
		const blockGapRow = convertPresetToCSSVar(
			isBlockGapObject ? blockGapValue?.top : blockGapValue
		);
		const blockGapColumn = convertPresetToCSSVar(
			isBlockGapObject ? blockGapValue?.left : blockGapValue
		);
		const defaultGap = 'var(--wp--preset--spacing--50)';

		const innerStyles = {
			display: 'grid',
			gridTemplateColumns: columnMinWidth
				? `repeat(${desktopColumns || 3}, minmax(${columnMinWidth}, 1fr))`
				: `repeat(${desktopColumns || 3}, 1fr)`,
			alignItems: alignItems || 'stretch',
			rowGap: blockGapRow || rowGap || defaultGap,
			columnGap: blockGapColumn || columnGap || defaultGap,
		};

		if (constrainWidth) {
			innerStyles.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyles.marginLeft = 'auto';
			innerStyles.marginRight = 'auto';
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-grid__inner',
			style: innerStyles,
		});

		return (
			<TagName {...blockProps}>
				<div {...innerBlocksProps} />
			</TagName>
		);
	},
	migrate(attributes) {
		// Only the serialised class differs; the current save() derives it
		// from the style variation (and the new overlayColor attribute,
		// which defaults to '' for this old content) so no attribute change.
		return attributes;
	},
};

// Before the column min width switched from a fixed `repeat(N, minmax(<min>,
// 1fr))` track list to the auto-fill form in ./grid-columns.js. The fixed
// repeat count could never drop a track, so a grid whose columns could not
// all fit their min width (a narrow theme contentSize, say) overflowed its
// container instead of wrapping items to the next row.
//
// Markup changed, so stored grids with a columnMinWidth are invalid against
// the current save() and WordPress picks this entry by reproducing their HTML
// — no isEligible needed, and none wanted (grids WITHOUT a columnMinWidth are
// byte-identical under both forms and must not be re-migrated). migrate() is
// a passthrough: only the serialised track list differs, no attribute does.
//
// This entry is listed FIRST so it wins over styleVariationClasses, whose
// save() collapses to the same output for content with no style variation.
const fixedColumnMinWidthTracks = {
	apiVersion: 3,
	supports: metadata.supports,
	attributes: { ...metadata.attributes },
	save({ attributes }) {
		const {
			tagName = 'div',
			constrainWidth,
			contentWidth,
			columnMinWidth,
			desktopColumns,
			tabletColumns,
			mobileColumns,
			rowGap,
			columnGap,
			alignItems,
			matchRowHeights,
			overlayColor,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			style,
		} = attributes;

		const hasOverlay =
			!!overlayColor || hasOverlayStyleClass(attributes.className);

		const className = [
			'dsgo-grid',
			`dsgo-grid-cols-${desktopColumns}`,
			`dsgo-grid-cols-tablet-${tabletColumns}`,
			`dsgo-grid-cols-mobile-${mobileColumns}`,
			!constrainWidth && 'dsgo-no-width-constraint',
			matchRowHeights && 'dsgo-grid--match-rows',
			hasOverlay && 'dsgo-grid--has-overlay',
			...hoverVariationClasses(attributes.className, 'dsgo-grid'),
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

		const blockGapValue = style?.spacing?.blockGap;
		const isBlockGapObject =
			typeof blockGapValue === 'object' && blockGapValue !== null;
		const blockGapRow = convertPresetToCSSVar(
			isBlockGapObject ? blockGapValue?.top : blockGapValue
		);
		const blockGapColumn = convertPresetToCSSVar(
			isBlockGapObject ? blockGapValue?.left : blockGapValue
		);
		const defaultGap = 'var(--wp--preset--spacing--50)';

		const innerStyles = {
			display: 'grid',
			gridTemplateColumns: columnMinWidth
				? `repeat(${desktopColumns || 3}, minmax(${columnMinWidth}, 1fr))`
				: `repeat(${desktopColumns || 3}, 1fr)`,
			alignItems: alignItems || 'stretch',
			rowGap: blockGapRow || rowGap || defaultGap,
			columnGap: blockGapColumn || columnGap || defaultGap,
		};

		if (constrainWidth) {
			innerStyles.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyles.marginLeft = 'auto';
			innerStyles.marginRight = 'auto';
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-grid__inner',
			style: innerStyles,
		});

		return (
			<TagName {...blockProps}>
				<div {...innerBlocksProps} />
			</TagName>
		);
	},
	migrate(attributes) {
		return attributes;
	},
};

// Version 1: Before align attribute - used className for alignment
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
	// `attributes.align === undefined`, matched nearly every CURRENT grid too:
	// `align` has no default, so it is absent from the raw comment attributes on
	// any grid the author never aligned wide/full.
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

/**
 * Site-designer responsive-grid markup where the tablet column count lived in a
 * `className` (e.g. `dsgo-grid-cols-tablet-1`) rather than the `tabletColumns`
 * attribute — and the block comment's `tabletColumns` drifted away from it.
 *
 * These grids combine the legacy min-width-in-CSS shape (see `legacyMinWidth`
 * below) with a `dsgo-grid-cols-tablet-N` class supplied through `className`,
 * while the comment still carries a stale `tabletColumns` (usually the default).
 * The current save() emits a SECOND tablet class from that attribute
 * (`dsgo-grid-cols-tablet-2`), which the stored markup never had, so the block
 * fails validation. WordPress compares the `class` attribute as an unordered
 * SET, so reproducing the class set exactly is what matters.
 *
 * This entry omits the attribute-derived tablet class (the stored one comes from
 * `className`) and reproduces the inner grid-template-columns verbatim from the
 * captured style, so the block validates. migrate() recovers the min width into
 * `columnMinWidth`, lifts the real tablet count out of the class into
 * `tabletColumns`, and drops the now-redundant `dsgo-grid-cols-tablet-N` class —
 * after which the current save() reproduces the (now consistent) markup.
 */
const legacyResponsiveTabletClass = {
	supports: sharedSupports,
	attributes: {
		...metadata.attributes,
		legacyInnerStyle: {
			type: 'string',
			source: 'attribute',
			selector: '.dsgo-grid__inner',
			attribute: 'style',
		},
	},
	// No isEligible: markup-change deprecation, reached by save-matching on the
	// invalid stored HTML. The omitted tablet class means this only matches grids
	// whose stored class set lacks an attribute-derived tablet class (i.e. it came
	// from className) — normal grids keep their tablet class and fall through.
	save({ attributes }) {
		const {
			tagName = 'div',
			constrainWidth,
			contentWidth,
			desktopColumns,
			mobileColumns,
			rowGap,
			columnGap,
			alignItems,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			style,
			legacyInnerStyle,
		} = attributes;

		// NOTE: no `dsgo-grid-cols-tablet-${tabletColumns}` — the stored tablet
		// class is supplied via className, and adding one from the drifted
		// attribute would introduce a class the stored markup never had.
		const className = [
			'dsgo-grid',
			`dsgo-grid-cols-${desktopColumns}`,
			`dsgo-grid-cols-mobile-${mobileColumns}`,
			!constrainWidth && 'dsgo-no-width-constraint',
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
			},
		});

		const blockGapValue = style?.spacing?.blockGap;
		const isBlockGapObject =
			typeof blockGapValue === 'object' && blockGapValue !== null;
		const blockGapRow = convertPresetToCSSVar(
			isBlockGapObject ? blockGapValue?.top : blockGapValue
		);
		const blockGapColumn = convertPresetToCSSVar(
			isBlockGapObject ? blockGapValue?.left : blockGapValue
		);
		const defaultGap = 'var(--wp--preset--spacing--50)';

		const gtc = (legacyInnerStyle || '').match(
			/grid-template-columns:\s*([^;]+)/i
		);

		const innerStyles = {
			display: 'grid',
			gridTemplateColumns: gtc
				? gtc[1].trim()
				: `repeat(${desktopColumns || 3}, 1fr)`,
			alignItems: alignItems || 'stretch',
			rowGap: blockGapRow || rowGap || defaultGap,
			columnGap: blockGapColumn || columnGap || defaultGap,
		};

		if (constrainWidth) {
			innerStyles.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyles.marginLeft = 'auto';
			innerStyles.marginRight = 'auto';
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-grid__inner',
			style: innerStyles,
		});

		return (
			<TagName {...blockProps}>
				<div {...innerBlocksProps} />
			</TagName>
		);
	},
	migrate(attributes) {
		const { legacyInnerStyle, className, ...rest } = attributes;
		const gtc = (legacyInnerStyle || '').match(
			/grid-template-columns:\s*([^;]+)/i
		);
		const mm = gtc ? gtc[1].match(MIN_WIDTH_RE) : null;
		const tabletMatch = (className || '').match(
			/dsgo-grid-cols-tablet-(\d+)/
		);
		const cleanClassName = (className || '')
			.split(/\s+/)
			.filter((c) => c && !/^dsgo-grid-cols-tablet-\d+$/.test(c))
			.join(' ');
		return {
			...rest,
			columnMinWidth: mm ? mm[1] : '',
			...(tabletMatch && { tabletColumns: Number(tabletMatch[1]) }),
			className: cleanClassName || undefined,
		};
	},
};

/**
 * Legacy responsive-grid markup from gd-pattern-library patterns.
 *
 * AI-generated patterns hard-coded `grid-template-columns: repeat(N, minmax(
 * <width>, 1fr))` directly in the inner div's INLINE style, with no
 * columnMinWidth block attribute. The width lived only in CSS, so the current
 * save() (which reads columnMinWidth) can't reproduce it and the block fails
 * validation ("Attempt Recovery"). This deprecation captures the stored inner
 * style, reproduces its grid-template-columns verbatim so validation passes,
 * and migrate() recovers the width into the columnMinWidth attribute — after
 * which the current save() reproduces the markup from the attribute as normal.
 */
const legacyMinWidth = {
	supports: sharedSupports,
	attributes: {
		...metadata.attributes,
		legacyInnerStyle: {
			type: 'string',
			source: 'attribute',
			selector: '.dsgo-grid__inner',
			attribute: 'style',
		},
	},
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		// A minmax() track alone does NOT mean "legacy": the current save() emits
		// one too, as soon as columnMinWidth is set. What marks the old
		// AI-generated pattern content is the track being baked into the HTML with
		// no columnMinWidth in the block comment — and migrate() below recovers it
		// from the markup. A current grid that renders minmax() always carries the
		// attribute (it is non-default, so WordPress serializes it), so requiring
		// its absence excludes current content without missing any legacy content.
		return (
			!!innerHTML &&
			!attributes.columnMinWidth &&
			/grid-template-columns:\s*repeat\([^)]*minmax/i.test(innerHTML)
		);
	},
	save({ attributes }) {
		const {
			tagName = 'div',
			constrainWidth,
			contentWidth,
			desktopColumns,
			tabletColumns,
			mobileColumns,
			rowGap,
			columnGap,
			alignItems,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			style,
			legacyInnerStyle,
		} = attributes;

		const className = [
			'dsgo-grid',
			`dsgo-grid-cols-${desktopColumns}`,
			`dsgo-grid-cols-tablet-${tabletColumns}`,
			`dsgo-grid-cols-mobile-${mobileColumns}`,
			!constrainWidth && 'dsgo-no-width-constraint',
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
			},
		});

		const blockGapValue = style?.spacing?.blockGap;
		const isBlockGapObject =
			typeof blockGapValue === 'object' && blockGapValue !== null;
		const blockGapRow = convertPresetToCSSVar(
			isBlockGapObject ? blockGapValue?.top : blockGapValue
		);
		const blockGapColumn = convertPresetToCSSVar(
			isBlockGapObject ? blockGapValue?.left : blockGapValue
		);
		const defaultGap = 'var(--wp--preset--spacing--50)';

		// Reproduce the stored grid-template-columns verbatim from the captured
		// inline style so this matches byte-for-byte.
		const gtc = (legacyInnerStyle || '').match(
			/grid-template-columns:\s*([^;]+)/i
		);

		const innerStyles = {
			display: 'grid',
			gridTemplateColumns: gtc
				? gtc[1].trim()
				: `repeat(${desktopColumns || 3}, 1fr)`,
			alignItems: alignItems || 'stretch',
			rowGap: blockGapRow || rowGap || defaultGap,
			columnGap: blockGapColumn || columnGap || defaultGap,
		};

		if (constrainWidth) {
			innerStyles.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyles.marginLeft = 'auto';
			innerStyles.marginRight = 'auto';
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-grid__inner',
			style: innerStyles,
		});

		return (
			<TagName {...blockProps}>
				<div {...innerBlocksProps} />
			</TagName>
		);
	},
	migrate(attributes) {
		const { legacyInnerStyle, ...rest } = attributes;
		const gtc = (legacyInnerStyle || '').match(
			/grid-template-columns:\s*([^;]+)/i
		);
		const mm = gtc ? gtc[1].match(MIN_WIDTH_RE) : null;
		return {
			...rest,
			columnMinWidth: mm ? mm[1] : '',
		};
	},
};

// legacyMinWidth must come before styleVariationClasses: a legacy grid can
// match BOTH isEligible checks (a minmax(...) inline style AND a style-kit
// variation class), and only legacyMinWidth's migrate() recovers the
// columnMinWidth attribute from stored HTML. styleVariationClasses.migrate()
// is a passthrough, so if it "won" for such content, columnMinWidth would be
// silently dropped (columns collapse to 1fr) with no recovery warning.
// Named exports exist so tests can reference an entry without depending on its
// position in the array below.
export {
	fixedColumnMinWidthTracks,
	legacyResponsiveTabletClass,
	legacyMinWidth,
	styleVariationClasses,
};

export default [
	fixedColumnMinWidthTracks,
	legacyResponsiveTabletClass,
	legacyMinWidth,
	styleVariationClasses,
	v1,
];

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
import metadata from './block.json';

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
	isEligible(attributes) {
		// v1 blocks don't have the align attribute - used className for alignment
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
	isEligible(attributes, innerBlocks, { innerHTML }) {
		return (
			!!innerHTML &&
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

export default [legacyMinWidth, v1];

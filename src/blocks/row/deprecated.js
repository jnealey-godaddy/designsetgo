/**
 * Row Block - Deprecated versions
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

/**
 * Convert WordPress vertical alignment value to CSS align-items value.
 * Kept local to this file so deprecations stay self-contained.
 *
 * @param {string} value The WordPress vertical alignment value
 * @return {string|undefined} CSS align-items value
 */
function getAlignItemsValue(value) {
	if (!value) {
		return undefined;
	}

	const alignMap = {
		stretch: 'stretch',
		center: 'center',
		top: 'flex-start',
		bottom: 'flex-end',
		'space-between': 'space-between',
	};

	return alignMap[value];
}

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
		allowVerticalAlignment: true,
		default: {
			type: 'flex',
			orientation: 'horizontal',
			justifyContent: 'left',
			flexWrap: 'nowrap',
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
		minWidth: true,
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

// Version 5: Before style-kit overlay/hover variation detection. The current
// save() also emits `dsgo-flex--has-overlay` when a style-kit overlay
// variation (`is-style-overlay-*`) is present on className, and emits
// `dsgo-flex--has-hover-{text,icon,button}` activation classes for the
// matching `is-style-hover-*` variation families — both mirroring Section's
// behavior. Rows saved with such a variation but no matching class in their
// stored HTML fail validation against the new save().
//
// isEligible targets exactly that signature (a variation on className with
// no matching class in the stored HTML) so those rows migrate SILENTLY.
// save() reproduces this block's pre-change output (overlay class from
// overlayColor only, no hover activation classes) so it also byte-matches on
// WP versions that still validate the deprecation's save() before migrating.
// migrate() is a passthrough — only the serialised class differs, not the
// attribute values; the current save() then re-renders with the classes
// derived from the variation.
const v5 = {
	supports: metadata.supports,
	attributes: { ...metadata.attributes },
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		if (!innerHTML || !innerHTML.includes('dsgo-flex')) {
			return false;
		}

		const overlayMismatch =
			hasOverlayStyleClass(attributes.className) &&
			!innerHTML.includes('dsgo-flex--has-overlay');

		const hoverMismatch = hoverVariationClasses(
			attributes.className,
			'dsgo-flex'
		).some((activationClass) => !innerHTML.includes(activationClass));

		return overlayMismatch || hoverMismatch;
	},
	save({ attributes }) {
		const {
			tagName = 'div',
			constrainWidth,
			contentWidth,
			overlayColor,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			mobileStack,
			layout,
		} = attributes;

		// Pre-change className: overlay class from overlayColor ONLY, no hover
		// activation classes.
		const className = [
			'dsgo-flex',
			mobileStack && 'dsgo-flex--mobile-stack',
			!constrainWidth && 'dsgo-no-width-constraint',
			overlayColor && 'dsgo-flex--has-overlay',
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

		const rawGapValue = attributes.style?.spacing?.blockGap;
		const gapValue = convertPresetToCSSVar(rawGapValue);

		if (blockProps.style?.gap) {
			delete blockProps.style.gap;
		}

		const alignItems = getAlignItemsValue(layout?.verticalAlignment);
		const innerStyle = {
			display: 'flex',
			justifyContent: layout?.justifyContent || 'left',
			...(alignItems && { alignItems }),
			flexWrap: layout?.flexWrap || 'nowrap',
			...(gapValue && { gap: gapValue }),
		};

		if (constrainWidth) {
			innerStyle.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyle.marginLeft = 'auto';
			innerStyle.marginRight = 'auto';
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-flex__inner',
			style: innerStyle,
		});

		return (
			<TagName {...blockProps}>
				<div {...innerBlocksProps} />
			</TagName>
		);
	},
	migrate(attributes) {
		// Only the serialised class differs; the current save() derives it
		// from the style variation on className, so no attribute change.
		return attributes;
	},
};

// Version 4: Before flex-wrap fallback was aligned with block.json default.
// This version's save() uses the old "wrap" fallback (instead of "nowrap") and
// is here so already-saved rows continue to validate. Migration is a no-op:
// attributes are untouched, and on the next user edit the current save() will
// re-serialize the block with the correct "nowrap" inline style, fixing the
// stacking behavior.
const v4 = {
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
			default: false,
		},
		contentWidth: {
			type: 'string',
			default: '',
		},
		mobileStack: {
			type: 'boolean',
			default: false,
		},
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
					blockGap: 'var:preset|spacing|30',
				},
			},
		},
		layout: {
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
	save({ attributes }) {
		const {
			tagName = 'div',
			constrainWidth,
			contentWidth,
			overlayColor,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			mobileStack,
			layout,
		} = attributes;

		const className = [
			'dsgo-flex',
			mobileStack && 'dsgo-flex--mobile-stack',
			!constrainWidth && 'dsgo-no-width-constraint',
			overlayColor && 'dsgo-flex--has-overlay',
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

		const rawGapValue = attributes.style?.spacing?.blockGap;
		const gapValue = convertPresetToCSSVar(rawGapValue);

		if (blockProps.style?.gap) {
			delete blockProps.style.gap;
		}

		const alignItems = getAlignItemsValue(layout?.verticalAlignment);
		const innerStyle = {
			display: 'flex',
			justifyContent: layout?.justifyContent || 'left',
			...(alignItems && { alignItems }),
			// Old fallback: "wrap" (the source of the stacking bug). Kept here
			// only for backward-compatible validation of previously saved HTML.
			flexWrap: layout?.flexWrap || 'wrap',
			...(gapValue && { gap: gapValue }),
		};

		if (constrainWidth) {
			innerStyle.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyle.marginLeft = 'auto';
			innerStyle.marginRight = 'auto';
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-flex__inner',
			style: innerStyle,
		});

		return (
			<TagName {...blockProps}>
				<div {...innerBlocksProps} />
			</TagName>
		);
	},
	migrate(oldAttributes) {
		// No attribute changes — the serialization difference (flex-wrap) is
		// re-emitted by the current save() on the next save cycle.
		return {
			...oldAttributes,
		};
	},
};

// Version 3: Before align-items (vertical alignment) was added to inner div
// This version has width constraints but no alignItems CSS property
const v3 = {
	supports: sharedSupports,
	attributes: {
		align: {
			type: 'string',
		},
		tagName: {
			type: 'string',
			default: 'div',
		},
		constrainWidth: {
			type: 'boolean',
		},
		contentWidth: {
			type: 'string',
		},
		mobileStack: {
			type: 'boolean',
		},
		style: {
			type: 'object',
		},
		layout: {
			type: 'object',
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
		overlayColor: {
			type: 'string',
		},
	},
	// No isEligible: markup-change deprecation, reached by save-matching on an
	// INVALID block (WordPress skips isEligible for those). The old guard fired on
	// any CURRENT row with a non-default tagName, because the rest of its
	// signature — the dsgo-flex__inner wrapper, and no `align-items` when
	// alignItems sits at its default — is exactly what the current save() emits.
	save({ attributes }) {
		const {
			tagName = 'div',
			constrainWidth,
			contentWidth,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			mobileStack,
			overlayColor,
			layout,
		} = attributes;

		const className = [
			'dsgo-flex',
			mobileStack && 'dsgo-flex--mobile-stack',
			!constrainWidth && 'dsgo-no-width-constraint',
			overlayColor && 'dsgo-flex--has-overlay',
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

		// Extract gap
		const rawGapValue = attributes.style?.spacing?.blockGap;
		const gapValue = convertPresetToCSSVar(rawGapValue);

		// Remove gap from outer div's inline styles
		if (blockProps.style?.gap) {
			delete blockProps.style.gap;
		}

		// Inner container WITHOUT alignItems (this is the key difference from current save)
		const innerStyle = {
			display: 'flex',
			justifyContent: layout?.justifyContent || 'left',
			flexWrap: layout?.flexWrap || 'wrap',
			...(gapValue && { gap: gapValue }),
		};

		// Apply width constraints if enabled
		if (constrainWidth) {
			innerStyle.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyle.marginLeft = 'auto';
			innerStyle.marginRight = 'auto';
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-flex__inner',
			style: innerStyle,
		});

		return (
			<TagName {...blockProps}>
				<div {...innerBlocksProps} />
			</TagName>
		);
	},
	migrate(oldAttributes) {
		// Preserve all attributes - the new version automatically applies alignItems
		return {
			...oldAttributes,
		};
	},
};

// Version 2: Before width constraint styles were added to inner div
// This version had dsgo-has-max-width class from max-width extension
// but didn't output width constraints on inner div when constrainWidth was true
const v2 = {
	supports: sharedSupports,
	attributes: {
		align: {
			type: 'string',
		},
		constrainWidth: {
			type: 'boolean',
		},
		contentWidth: {
			type: 'string',
		},
		mobileStack: {
			type: 'boolean',
		},
		style: {
			type: 'object',
		},
		layout: {
			type: 'object',
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
		overlayColor: {
			type: 'string',
		},
	},
	// No isEligible: markup-change deprecation, reached by save-matching on an
	// INVALID block (WordPress skips isEligible for those). The old guard read
	// "has align, but no tagName and no constrainWidth" as "old block" — but
	// WordPress omits any attribute sitting at its default from the comment, so an
	// aligned CURRENT row with a default tagName and constrainWidth looks
	// identical. It claimed current content.
	save({ attributes }) {
		const {
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			mobileStack,
			overlayColor,
			layout,
		} = attributes;

		const className = [
			'dsgo-flex',
			mobileStack && 'dsgo-flex--mobile-stack',
			overlayColor && 'dsgo-flex--has-overlay',
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
				...(overlayColor && {
					'--dsgo-overlay-color': convertPresetToCSSVar(overlayColor),
					'--dsgo-overlay-opacity': '0.8',
				}),
			},
		});

		// Extract gap
		const rawGapValue = attributes.style?.spacing?.blockGap;
		const gapValue = convertPresetToCSSVar(rawGapValue);

		// Remove gap from outer div's inline styles
		if (blockProps.style?.gap) {
			delete blockProps.style.gap;
		}

		// Inner container WITHOUT width constraints
		// This is the key difference - old version didn't apply width constraints here
		const innerStyle = {
			display: 'flex',
			justifyContent: layout?.justifyContent || 'left',
			flexWrap: layout?.flexWrap || 'wrap',
			...(gapValue && { gap: gapValue }),
		};

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-flex__inner',
			style: innerStyle,
		});

		return (
			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		);
	},
	migrate(oldAttributes) {
		// Clean up dsgo-has-max-width class that was added by old max-width extension
		const className = oldAttributes.className || '';
		const cleanClassName = className
			.split(' ')
			.filter((cls) => cls !== 'dsgo-has-max-width')
			.join(' ')
			.trim();

		return {
			...oldAttributes,
			className: cleanClassName || undefined,
		};
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
	// `attributes.align === undefined && !hasOwnProperty('constrainWidth')`,
	// matched CURRENT rows too — neither attribute is serialized into the
	// comment when it sits at its default, so "absent" does not mean "old".
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
			'dsgo-flex',
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
			className: 'dsgo-flex__inner',
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

		// Remove align classes and dsgo-has-max-width from className
		const cleanClassName = className
			.split(' ')
			.filter(
				(cls) =>
					cls !== 'alignfull' &&
					cls !== 'alignwide' &&
					cls !== 'dsgo-has-max-width'
			)
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

export default [v5, v4, v3, v2, v1];

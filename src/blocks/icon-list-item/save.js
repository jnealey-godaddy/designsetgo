/**
 * Icon List Item Block - Save Component
 *
 * Renders the frontend output for a single icon list item with flexible content area.
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Icon List Item Save Component
 *
 * @param {Object} props            - Component props
 * @param {Object} props.attributes - Block attributes
 * @param {Object} props.context    - Block context from parent
 * @return {JSX.Element} Icon List Item save component
 */
export default function IconListItemSave({ attributes, context = {} }) {
	const { icon, linkUrl, linkTarget, linkRel, contentGap } = attributes;

	// Get settings from parent via context with safe defaults.
	// iconSize is only a concrete number when the parent has an explicit
	// value; when the parent leaves it unset, context yields undefined and
	// the item inherits the theme default token via CSS custom properties
	// (see iconWrapperStyles below) instead of an inline pixel value.
	const ctxIconSize = context['designsetgo/iconList/iconSize'];
	const hasExplicitSize = typeof ctxIconSize === 'number';
	const iconColor = context['designsetgo/iconList/iconColor'] || '';
	const iconBackgroundColor =
		context['designsetgo/iconList/iconBackgroundColor'] || '';
	const iconPosition = context['designsetgo/iconList/iconPosition'] || 'left';
	const iconVerticalAlignment =
		context['designsetgo/iconList/iconVerticalAlignment'] || 'top';
	// Style/stroke: omitted (undefined) when the parent leaves them unset so
	// the frontend injector falls back to the theme-wide default style
	// (settings.custom.designsetgo.icon.defaultStyle).
	const ctxIconStyle = context['designsetgo/iconList/iconStyle'] || undefined;
	const ctxStrokeWidth = context['designsetgo/iconList/strokeWidth'];

	// Calculate text alignment based on icon position (must match edit.js)
	const getTextAlign = () => {
		if (iconPosition === 'top') {
			return 'center';
		}
		if (iconPosition === 'right') {
			return 'right';
		}
		return 'left';
	};

	// Calculate vertical alignment for left/right icon positions (must match edit.js)
	const getVerticalAlignItems = () => {
		if (iconPosition === 'top') {
			return 'center';
		}
		return iconVerticalAlignment === 'center' ? 'center' : 'flex-start';
	};

	// Calculate item layout styles (must match edit.js).
	// The icon↔content gap is NOT written inline: it is a position-derived
	// design default (12px stacked / 16px inline), owned by the stylesheet so
	// Style Kits and patterns can retheme it. It resolves through a layered
	// chain keyed on the position modifier classes
	// (.dsgo-icon-list-item / --icon-top), see style.scss:
	//   var(--dsgo-icon-list-gap[-top], var(--wp--custom--…--gap[-top], 16|12px))
	// There is no author attribute for this gap, so there is no inline override.
	const itemStyles = {
		display: 'flex',
		flexDirection: iconPosition === 'top' ? 'column' : 'row',
		alignItems: getVerticalAlignItems(),
		...(iconPosition === 'right' && { flexDirection: 'row-reverse' }),
	};

	// Calculate icon wrapper styles (must match edit.js).
	// When the parent has an explicit iconSize, keep today's inline pixel
	// output exactly (byte-identical for existing content). When inherited,
	// emit NO inline size — the stylesheet owns --dsgo-icon-list-size on the
	// .dsgo-icon-list-item__icon--inherit-size class and resolves both the icon
	// box and the +16 background box from a kit hook + the theme default token.
	let sizeStyles;
	if (hasExplicitSize) {
		const explicitSize = iconBackgroundColor
			? ctxIconSize + 16
			: ctxIconSize;
		sizeStyles = {
			width: `${explicitSize}px`,
			height: `${explicitSize}px`,
			minWidth: `${explicitSize}px`,
		};
	} else {
		// Inherited size: emit NOTHING inline. The stylesheet owns
		// --dsgo-icon-list-size on the .dsgo-icon-list-item__icon--inherit-size
		// class (sourced from a kit hook + the theme default token), so kits and
		// patterns can retheme it. See style.scss.
		sizeStyles = {};
	}

	const iconWrapperStyles = {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		...sizeStyles,
		...(iconBackgroundColor && {
			backgroundColor: convertColorToCSSVar(iconBackgroundColor),
			padding: '8px',
			borderRadius: '4px',
			boxSizing: 'border-box',
		}),
		...(iconColor && {
			color: convertColorToCSSVar(iconColor),
			'--dsgo-icon-color': convertColorToCSSVar(iconColor),
		}),
	};

	// Get block wrapper props
	const blockProps = useBlockProps.save({
		className: `dsgo-icon-list-item dsgo-icon-list-item--icon-${iconPosition}`,
		style: itemStyles,
	});

	// Configure inner blocks props. The content gap is written inline ONLY for
	// an explicit author value; left unset (undefined) it is omitted so the
	// stylesheet default (.dsgo-icon-list-item__content, resolving through
	// --dsgo-icon-list-content-gap / the theme token) owns it and kits/patterns
	// can retheme it. Mirrors icon-button's iconGap.
	const hasExplicitContentGap = typeof contentGap === 'number';
	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-icon-list-item__content',
		style: {
			textAlign: getTextAlign(),
			display: 'flex',
			flexDirection: 'column',
			...(hasExplicitContentGap && { gap: `${contentGap}px` }),
		},
	});

	// Wrap in link if URL is provided
	const ItemWrapper = linkUrl ? 'a' : 'div';
	const wrapperProps = linkUrl
		? {
				...blockProps,
				href: linkUrl,
				target: linkTarget,
				rel: linkRel || undefined,
			}
		: blockProps;

	// When the size is inherited (no explicit parent iconSize), style.scss
	// needs a hook to size the box from --dsgo-icon-list-size: the plain icon
	// box uses that value directly, the background box adds the +16 padding
	// box. This modifier class is a no-op for explicit-size lists, which keep
	// their inline pixel dimensions.
	const iconClassName = [
		'dsgo-icon-list-item__icon',
		'dsgo-lazy-icon',
		!hasExplicitSize && 'dsgo-icon-list-item__icon--inherit-size',
	]
		.filter(Boolean)
		.join(' ');

	return (
		<ItemWrapper {...wrapperProps}>
			<div
				className={iconClassName}
				style={iconWrapperStyles}
				data-icon-name={icon}
				// Omit when unset so the frontend injector inherits the
				// theme-wide default style (settings.custom.designsetgo.icon.defaultStyle).
				data-icon-style={ctxIconStyle}
				data-icon-stroke-width={
					ctxIconStyle === 'outlined' ? ctxStrokeWidth : undefined
				}
			/>

			<div {...innerBlocksProps} />
		</ItemWrapper>
	);
}

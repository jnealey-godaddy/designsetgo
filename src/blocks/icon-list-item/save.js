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

	// Calculate item layout styles (must match edit.js)
	const itemStyles = {
		display: 'flex',
		flexDirection: iconPosition === 'top' ? 'column' : 'row',
		alignItems: getVerticalAlignItems(),
		gap: iconPosition === 'top' ? '12px' : '16px',
		...(iconPosition === 'right' && { flexDirection: 'row-reverse' }),
	};

	// Calculate icon wrapper styles (must match edit.js).
	// When the parent has an explicit iconSize, keep today's inline pixel
	// output exactly (byte-identical for existing content). When inherited,
	// omit the inline width/height/minWidth and instead set the
	// --dsgo-icon-list-size custom property so style.scss can resolve both
	// the icon box and the +16 background box from the theme default token.
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
		sizeStyles = {
			'--dsgo-icon-list-size':
				'calc(var(--wp--custom--designsetgo--icon-list--default-size, 32) * 1px)',
		};
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

	// Configure inner blocks props
	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-icon-list-item__content',
		style: {
			textAlign: getTextAlign(),
			display: 'flex',
			flexDirection: 'column',
			gap: `${contentGap}px`,
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

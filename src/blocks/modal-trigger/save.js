/**
 * Modal Trigger Block - Save Component
 *
 * @package
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save({ attributes }) {
	const {
		targetModalId,
		text,
		buttonStyle,
		align,
		icon,
		iconPosition,
		iconStyle,
		strokeWidth,
		iconSize,
		iconGap,
		style,
		backgroundColor,
		textColor,
		fontSize,
	} = attributes;

	// Extract WordPress color values (must match edit.js)
	// Custom colors come from style.color.background (hex/rgb)
	// Preset colors come from backgroundColor/textColor (slugs that need conversion)
	const bgColor =
		style?.color?.background ||
		(backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
	const txtColor =
		style?.color?.text ||
		(textColor && `var(--wp--preset--color--${textColor})`);

	// Extract font size (must match edit.js)
	// Custom font sizes come from style.typography.fontSize (px/rem/em)
	// Preset font sizes come from fontSize (slug that needs conversion)
	const fontSizeValue =
		style?.typography?.fontSize ||
		(fontSize && `var(--wp--preset--font-size--${fontSize})`);

	// Extract padding (must match edit.js)
	const paddingValue = style?.spacing?.padding;

	// Calculate if full width based on alignment
	const isFullWidth = align === 'full';

	// Calculate button styles
	const buttonStyles = {
		display: isFullWidth ? 'flex' : 'inline-flex',
		alignItems: 'center',
		justifyContent: 'center',
		width: isFullWidth ? '100%' : 'auto',
		gap: iconPosition !== 'none' && icon ? iconGap : undefined,
		flexDirection: iconPosition === 'end' ? 'row-reverse' : 'row',
		...(bgColor && { backgroundColor: bgColor }),
		...(txtColor && { color: txtColor }),
		...(fontSizeValue && { fontSize: fontSizeValue }),
		...(paddingValue?.top !== undefined && {
			paddingTop: paddingValue.top,
		}),
		...(paddingValue?.right !== undefined && {
			paddingRight: paddingValue.right,
		}),
		...(paddingValue?.bottom !== undefined && {
			paddingBottom: paddingValue.bottom,
		}),
		...(paddingValue?.left !== undefined && {
			paddingLeft: paddingValue.left,
		}),
	};

	// Size is only written inline when the author sets an explicit iconSize;
	// otherwise it is omitted so the theme default token
	// (--wp--custom--designsetgo--modal-trigger--default-size, via style.scss)
	// applies.
	const hasExplicitSize = typeof iconSize === 'number';
	const iconWrapperStyles = {
		...(hasExplicitSize && {
			width: `${iconSize}px`,
			height: `${iconSize}px`,
		}),
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		flexShrink: 0,
	};

	// wp-block-button and wp-element-button enable theme.json button styles
	// wp-block-button__link ensures theme compatibility
	// WordPress automatically adds alignment classes (alignleft, aligncenter, alignright, alignfull)
	const blockProps = useBlockProps.save({
		className: `dsgo-modal-trigger dsgo-modal-trigger--${buttonStyle} wp-block-button wp-block-button__link wp-element-button`,
		style: buttonStyles,
		'data-dsgo-modal-trigger': targetModalId,
		type: 'button',
	});

	return (
		<button {...blockProps}>
			{icon && iconPosition === 'start' && (
				<span
					className="dsgo-modal-trigger__icon dsgo-lazy-icon"
					style={iconWrapperStyles}
					data-icon-name={icon}
					// Omit when unset so the injector inherits the theme default
					// (settings.custom.designsetgo.icon.defaultStyle).
					data-icon-style={iconStyle || undefined}
					data-icon-stroke-width={
						iconStyle === 'outlined' ? strokeWidth : undefined
					}
				/>
			)}
			<RichText.Content
				tagName="span"
				value={text}
				className="dsgo-modal-trigger__text"
			/>
			{icon && iconPosition === 'end' && (
				<span
					className="dsgo-modal-trigger__icon dsgo-lazy-icon"
					style={iconWrapperStyles}
					data-icon-name={icon}
					data-icon-style={iconStyle || undefined}
					data-icon-stroke-width={
						iconStyle === 'outlined' ? strokeWidth : undefined
					}
				/>
			)}
		</button>
	);
}

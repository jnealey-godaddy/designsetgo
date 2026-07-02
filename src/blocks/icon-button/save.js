/**
 * Icon Button Block - Save Component
 *
 * Renders the frontend output for the icon button.
 *
 * @since 1.0.0
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';
import { convertPaddingValue } from './utils/padding';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Icon Button Save Component
 *
 * @param {Object} props            - Component props
 * @param {Object} props.attributes - Block attributes
 * @return {JSX.Element} Icon Button save component
 */
export default function IconButtonSave({ attributes }) {
	const {
		text,
		url,
		linkTarget,
		rel,
		icon,
		iconPosition,
		iconStyle,
		strokeWidth,
		iconSize,
		iconGap,
		align,
		hoverAnimation,
		hoverBackgroundColor,
		hoverTextColor,
		style,
		backgroundColor,
		textColor,
		fontSize,
		modalCloseId,
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

	// Combined styles for single element (must match edit.js)
	// Visual styles (colors, padding, font size, hover) + layout styles (flexbox)
	// Use flex for full-width (alignfull), inline-flex for auto
	const isFullWidth = align === 'full';
	const buttonStyles = {
		display: isFullWidth ? 'flex' : 'inline-flex',
		alignItems: 'center',
		justifyContent: 'center',
		gap: iconPosition !== 'none' && icon ? iconGap : 0,
		width: isFullWidth ? '100%' : 'auto',
		flexDirection: iconPosition === 'end' ? 'row-reverse' : 'row',
		...(bgColor && { backgroundColor: bgColor }),
		...(txtColor && { color: txtColor }),
		...(fontSizeValue && { fontSize: fontSizeValue }),
		...(paddingValue && {
			paddingTop: convertPaddingValue(paddingValue.top),
			paddingRight: convertPaddingValue(paddingValue.right),
			paddingBottom: convertPaddingValue(paddingValue.bottom),
			paddingLeft: convertPaddingValue(paddingValue.left),
		}),
		...(hoverBackgroundColor && {
			'--dsgo-button-hover-bg':
				convertColorToCSSVar(hoverBackgroundColor),
		}),
		...(hoverTextColor && {
			'--dsgo-button-hover-color': convertColorToCSSVar(hoverTextColor),
		}),
	};

	// Calculate icon wrapper styles (must match edit.js)
	// Size is only written inline when the author sets an explicit iconSize;
	// otherwise it is omitted so the theme default token
	// (--wp--custom--designsetgo--icon-button--default-size, via style.scss)
	// applies.
	const hasExplicitSize = typeof iconSize === 'number';
	const iconWrapperStyles = {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		...(hasExplicitSize && {
			width: `${iconSize}px`,
			height: `${iconSize}px`,
		}),
		flexShrink: 0,
	};

	// Build animation class (must match edit.js)
	// "none" = use site default (no class, PHP will inject default at render)
	// "explicit-none" = explicitly no animation (marker class blocks PHP injection)
	let animationClass = '';
	if (hoverAnimation === 'explicit-none') {
		animationClass = ' dsgo-icon-button--no-hover';
	} else if (hoverAnimation && hoverAnimation !== 'none') {
		animationClass = ` dsgo-icon-button--${hoverAnimation}`;
	}

	// Single element with all classes and styles combined
	// wp-block-button and wp-element-button enable theme.json button styles
	// wp-block-button__link ensures theme compatibility
	// WordPress automatically adds alignfull class when align="full"
	const ButtonElement = url ? 'a' : 'button';

	const blockProps = useBlockProps.save({
		className: `dsgo-icon-button wp-block-button wp-block-button__link wp-element-button${animationClass}`,
		style: buttonStyles,
		...(url && {
			href: url,
			target: linkTarget,
			rel:
				linkTarget === '_blank'
					? rel || 'noopener noreferrer'
					: rel || undefined,
		}),
		...(!url && {
			type: 'button',
		}),
		...(modalCloseId && {
			'data-dsgo-modal-close': modalCloseId,
		}),
	});

	return (
		<ButtonElement {...blockProps}>
			{iconPosition !== 'none' && icon && (
				<span
					className="dsgo-icon-button__icon dsgo-lazy-icon"
					style={iconWrapperStyles}
					data-icon-name={icon}
					data-icon-size={iconSize || undefined}
					// Omit when unset so the injector inherits the theme default
					// (settings.custom.designsetgo.icon.defaultStyle).
					data-icon-style={iconStyle || undefined}
					// Only emitted for explicit outlined so existing filled
					// buttons with an explicit size stay byte-identical (no
					// new attributes) and remain valid without migration.
					data-icon-stroke-width={
						iconStyle === 'outlined' ? strokeWidth : undefined
					}
				/>
			)}
			<RichText.Content
				tagName="span"
				className="dsgo-icon-button__text"
				value={text}
			/>
		</ButtonElement>
	);
}

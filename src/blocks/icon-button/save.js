/**
 * Icon Button Block - Save Component
 *
 * Renders the frontend output for the icon button.
 *
 * The block root is a plain block-level "justification wrapper" (`.dsgo-justify`)
 * that core's constrained layout caps at the content column — align: left/right
 * is deliberately NOT used here, since core excludes aligned blocks from that cap
 * (see wp-includes/block-supports/layout.php). The visible button shrink-wraps
 * inside it and is positioned via `justify-content` from the `justification`
 * attribute. Visual supports (border/color/shadow/typography) are skip-serialized
 * off the wrapper in block.json and re-derived here with the official block-support
 * helpers so they land on the button, not the invisible wrapper.
 *
 * @since 1.0.0
 */

import {
	useBlockProps,
	RichText,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetBorderClassesAndStyles as getBorderClassesAndStyles,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetColorClassesAndStyles as getColorClassesAndStyles,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetShadowClassesAndStyles as getShadowClassesAndStyles,
	getTypographyClassesAndStyles,
} from '@wordpress/block-editor';
import clsx from 'clsx';
import { convertPaddingValue } from './utils/padding';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { hasExplicitString } from '../../utils/has-explicit-value';
import { getJustificationClass } from '../../utils/justification';

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
		justification,
		fullWidth,
		hoverAnimation,
		hoverBackgroundColor,
		hoverTextColor,
		style,
		modalCloseId,
	} = attributes;

	// The wrapper is the block root: a plain block-level box that core's
	// constrained layout caps at the content column. It carries NO visual styles
	// (those are skip-serialized in block.json and re-applied to the button below).
	const blockProps = useBlockProps.save({
		className: clsx('dsgo-justify', getJustificationClass(justification)),
	});

	const border = getBorderClassesAndStyles(attributes);
	const colors = getColorClassesAndStyles(attributes);
	const shadow = getShadowClassesAndStyles(attributes);
	const typography = getTypographyClassesAndStyles(attributes);
	const paddingValue = style?.spacing?.padding;

	// Icon presence gates the icon↔text gap. When there is no icon the button
	// has a single flex child, so a gap is inert — we omit it entirely rather
	// than serialize an inert `gap:0`.
	const hasIcon = iconPosition !== 'none' && !!icon;
	// Gap is written inline ONLY when the author sets an explicit iconGap. Left
	// unset it is omitted so the stylesheet default (.dsgo-icon-button--has-icon)
	// owns it and kits/patterns can retheme via --dsgo-icon-button-gap or the
	// --wp--custom--designsetgo--icon-button--gap token.
	const hasExplicitGap = hasExplicitString(iconGap);

	const buttonStyles = {
		...border.style,
		...colors.style,
		...shadow.style,
		...typography.style,
		...(hasIcon && hasExplicitGap && { gap: iconGap }),
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

	// Calculate icon wrapper styles (must match edit.js). Size is only written
	// inline when the author sets an explicit iconSize; otherwise it is omitted
	// so the theme default token
	// (--wp--custom--designsetgo--icon-button--default-size, via style.scss)
	// applies.
	// Layout (display/align-items/justify-content/flex-shrink) is NOT serialized
	// here — it is constant for every button, so it lives in style.scss
	// (.dsgo-icon-button__icon). Only an explicit iconSize is written inline;
	// with none set this object is empty and React emits no style attribute at
	// all, matching modal-trigger's save().
	const hasExplicitSize = typeof iconSize === 'number';
	const iconWrapperStyles = {
		...(hasExplicitSize && {
			width: `${iconSize}px`,
			height: `${iconSize}px`,
		}),
	};

	const ButtonElement = url ? 'a' : 'button';

	const buttonClasses = clsx(
		'dsgo-icon-button',
		'wp-block-button',
		'wp-block-button__link',
		'wp-element-button',
		border.className,
		colors.className,
		shadow.className,
		typography.className,
		hasIcon && 'dsgo-icon-button--has-icon',
		fullWidth && 'dsgo-icon-button--full-width',
		hoverAnimation === 'explicit-none' && 'dsgo-icon-button--no-hover',
		hoverAnimation &&
			hoverAnimation !== 'none' &&
			hoverAnimation !== 'explicit-none' &&
			`dsgo-icon-button--${hoverAnimation}`,
		iconPosition === 'end' && 'dsgo-icon-button--icon-end'
	);

	return (
		<div {...blockProps}>
			<ButtonElement
				className={buttonClasses}
				style={buttonStyles}
				{...(url && {
					href: url,
					target: linkTarget,
					rel:
						linkTarget === '_blank'
							? rel || 'noopener noreferrer'
							: rel || undefined,
				})}
				{...(!url && { type: 'button' })}
				{...(modalCloseId && { 'data-dsgo-modal-close': modalCloseId })}
			>
				{hasIcon && (
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
		</div>
	);
}

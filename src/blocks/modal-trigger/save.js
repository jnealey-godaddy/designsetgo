/**
 * Modal Trigger Block - Save Component
 *
 * Renders the frontend output for the modal trigger.
 *
 * The block root is a plain block-level "justification wrapper" (`.dsgo-justify`)
 * that core's constrained layout caps at the content column — align: left/right
 * is deliberately NOT used here, since core excludes aligned blocks from that cap
 * (see wp-includes/block-supports/layout.php). The visible button shrink-wraps
 * inside it and is positioned via `justify-content` from the `justification`
 * attribute. Visual supports (border/color/typography) are skip-serialized off
 * the wrapper in block.json and re-derived here with the official block-support
 * helpers so they land on the button, not the invisible wrapper.
 *
 * @package
 */

import {
	useBlockProps,
	RichText,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetBorderClassesAndStyles as getBorderClassesAndStyles,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetColorClassesAndStyles as getColorClassesAndStyles,
	getTypographyClassesAndStyles,
} from '@wordpress/block-editor';
import clsx from 'clsx';
import { getJustificationClass } from '../../utils/justification';

export default function save({ attributes }) {
	const {
		targetModalId,
		text,
		buttonStyle,
		justification,
		fullWidth,
		icon,
		iconPosition,
		iconStyle,
		strokeWidth,
		iconSize,
		iconGap,
		style,
	} = attributes;

	// The wrapper is the block root: a plain block-level box that core's
	// constrained layout caps at the content column. It carries NO visual
	// styles (those are skip-serialized in block.json and re-applied to the
	// button below).
	const blockProps = useBlockProps.save({
		className: clsx('dsgo-justify', getJustificationClass(justification)),
	});

	const border = getBorderClassesAndStyles(attributes);
	const colors = getColorClassesAndStyles(attributes);
	const typography = getTypographyClassesAndStyles(attributes);
	const paddingValue = style?.spacing?.padding;
	const hasIcon = iconPosition !== 'none' && !!icon;

	const buttonStyles = {
		...border.style,
		...colors.style,
		...typography.style,
		...(hasIcon && iconGap && { gap: iconGap }),
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

	const buttonClasses = clsx(
		'dsgo-modal-trigger',
		`dsgo-modal-trigger--${buttonStyle}`,
		'wp-block-button',
		'wp-block-button__link',
		'wp-element-button',
		border.className,
		colors.className,
		typography.className,
		fullWidth && 'dsgo-modal-trigger--full-width',
		iconPosition === 'end' && 'dsgo-modal-trigger--icon-end'
	);

	// Size is only written inline when the author sets an explicit iconSize;
	// otherwise it is omitted so the theme default token
	// (--wp--custom--designsetgo--modal-trigger--default-size, via style.scss)
	// applies.
	const hasExplicitSize = typeof iconSize === 'number';
	const iconSpan = hasIcon && (
		<span
			className="dsgo-modal-trigger__icon dsgo-lazy-icon"
			style={{
				...(hasExplicitSize && {
					width: `${iconSize}px`,
					height: `${iconSize}px`,
				}),
			}}
			data-icon-name={icon}
			// Omit when unset so the injector inherits the theme default
			// (settings.custom.designsetgo.icon.defaultStyle).
			data-icon-style={iconStyle || undefined}
			data-icon-stroke-width={
				iconStyle === 'outlined' ? strokeWidth : undefined
			}
		/>
	);

	return (
		<div {...blockProps}>
			<button
				className={buttonClasses}
				style={buttonStyles}
				type="button"
				data-dsgo-modal-trigger={targetModalId}
			>
				{iconSpan}
				<RichText.Content
					tagName="span"
					value={text}
					className="dsgo-modal-trigger__text"
				/>
			</button>
		</div>
	);
}

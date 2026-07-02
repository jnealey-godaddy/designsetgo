/**
 * Modal Trigger Block - Deprecated Versions
 *
 * Handles backward compatibility for blocks saved with previous versions.
 *
 * @since 1.2.0
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';
import { getIcon } from '../icon/utils/svg-icons';

/**
 * Shared supports for all deprecated versions.
 * Uses __experimentalBorder (the historical name) instead of border.
 */
const sharedSupports = {
	anchor: true,
	align: ['left', 'center', 'right', 'full'],
	alignWide: true,
	html: false,
	spacing: {
		margin: true,
		padding: true,
		__experimentalSkipSerialization: ['padding'],
		__experimentalDefaultControls: {
			margin: true,
			padding: true,
		},
	},
	color: {
		background: true,
		text: true,
		__experimentalSkipSerialization: true,
		__experimentalDefaultControls: {
			background: true,
			text: true,
		},
	},
	typography: {
		fontSize: true,
		lineHeight: true,
		fontFamily: true,
		fontWeight: true,
		letterSpacing: true,
	},
	__experimentalBorder: {
		color: true,
		radius: true,
		style: true,
		width: true,
		__experimentalDefaultControls: {
			radius: true,
		},
	},
};

/**
 * Version 3: Before the theme icon-size / icon-style tokens
 *
 * The pre-token format always wrote an explicit `width:Npx;height:Npx` on the
 * icon wrapper span and never emitted `data-icon-style` / `data-icon-stroke-width`,
 * so implicit-default icons were baked to a fixed 20px / "filled". The current
 * version omits the inline size when the author leaves iconSize unset so the
 * theme default token (settings.custom.designsetgo.modalTrigger.defaultSize)
 * can take over, and always emits data-icon-style/data-icon-stroke-width so the
 * lazy-icon injector can render outlined icons.
 *
 * The deprecated attribute schema intentionally has NO default for iconSize
 * (iconStyle is optional, no default): an implicit-default old block re-parses
 * to `undefined` for both, so the passthrough migrate lets it inherit the theme
 * default. Blocks that set an explicit size keep their stored value as an
 * override.
 */
const v3 = {
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Current-structure block (single <button>, dsgo-lazy-icon) that still
		// carries an inline size pair — the signature of the pre-token
		// serialization. Exclude the legacy nested wrapper+button markup
		// (dsgo-modal-trigger--width-*), which is handled by v2/v1 below and
		// also happens to carry dsgo-lazy-icon + inline width/height.
		return (
			innerHTML &&
			innerHTML.includes('dsgo-lazy-icon') &&
			!innerHTML.includes('dsgo-modal-trigger--width-') &&
			/width:\s*\d+px\s*;\s*height:\s*\d+px/.test(innerHTML)
		);
	},

	attributes: {
		targetModalId: { type: 'string', default: '' },
		text: { type: 'string', default: 'Open Modal' },
		buttonStyle: { type: 'string', default: 'fill' },
		icon: { type: 'string', default: '' },
		iconPosition: { type: 'string', default: 'none' },
		iconStyle: { type: 'string' },
		strokeWidth: { type: 'number', default: 1.5 },
		iconSize: { type: 'number' },
		iconGap: { type: 'string', default: '8px' },
	},

	save({ attributes }) {
		const {
			targetModalId,
			text,
			buttonStyle,
			align,
			icon,
			iconPosition,
			iconSize,
			iconGap,
			style,
			backgroundColor,
			textColor,
			fontSize,
		} = attributes;

		const bgColor =
			style?.color?.background ||
			(backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
		const txtColor =
			style?.color?.text ||
			(textColor && `var(--wp--preset--color--${textColor})`);

		const fontSizeValue =
			style?.typography?.fontSize ||
			(fontSize && `var(--wp--preset--font-size--${fontSize})`);

		const paddingValue = style?.spacing?.padding;

		const isFullWidth = align === 'full';

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

		// Pre-token format always baked an explicit size.
		const size = typeof iconSize === 'number' ? iconSize : 20;

		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			width: `${size}px`,
			height: `${size}px`,
			flexShrink: 0,
		};

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
					/>
				)}
			</button>
		);
	},

	migrate(attributes) {
		// Passthrough. An implicit-default old block has iconSize/iconStyle
		// === undefined here (no default in this schema), so it inherits the
		// theme token; explicit values are preserved as overrides.
		return attributes;
	},
};

/**
 * Version 2: Pre-alignment support with nested wrapper + button structure
 *
 * Behavior of this saved version:
 * - Uses custom `width` attribute instead of the WordPress `align` attribute
 * - Renders an outer <div> wrapper containing an inner <button> element
 * - Applies legacy `dsgo-modal-trigger--width-*` classes and inline display styling
 * - Does not include WordPress button classes (wp-block-button, wp-element-button)
 * - Does not use __experimentalSkipSerialization flags
 */
const v2 = {
	supports: sharedSupports,
	attributes: {
		targetModalId: {
			type: 'string',
			default: '',
		},
		text: {
			type: 'string',
			default: 'Open Modal',
		},
		buttonStyle: {
			type: 'string',
			default: 'fill',
		},
		width: {
			type: 'string',
			default: 'auto',
		},
		icon: {
			type: 'string',
			default: '',
		},
		iconPosition: {
			type: 'string',
			default: 'none',
		},
		iconSize: {
			type: 'number',
			default: 20,
		},
		iconGap: {
			type: 'string',
			default: '8px',
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v2 blocks have dsgo-lazy-icon class but still use two-div wrapper+button structure
		// and have dsgo-modal-trigger--width-* classes
		return (
			innerHTML &&
			innerHTML.includes('dsgo-modal-trigger--width-') &&
			innerHTML.includes('dsgo-lazy-icon')
		);
	},
	save({ attributes }) {
		const {
			targetModalId,
			text,
			buttonStyle,
			width,
			icon,
			iconPosition,
			iconSize,
			iconGap,
		} = attributes;

		const buttonStyles = {
			gap: iconPosition !== 'none' && icon ? iconGap : undefined,
			flexDirection: iconPosition === 'end' ? 'row-reverse' : 'row',
		};

		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			width: `${iconSize}px`,
			height: `${iconSize}px`,
			flexShrink: 0,
		};

		const blockProps = useBlockProps.save({
			className: `dsgo-modal-trigger dsgo-modal-trigger--${buttonStyle} dsgo-modal-trigger--width-${width}`,
			style: { display: width === 'full' ? 'block' : 'inline-block' },
		});

		return (
			<div {...blockProps}>
				<button
					className="dsgo-modal-trigger__button"
					data-dsgo-modal-trigger={targetModalId}
					style={buttonStyles}
					type="button"
				>
					{icon && iconPosition === 'start' && (
						<span
							className="dsgo-modal-trigger__icon dsgo-lazy-icon"
							style={iconWrapperStyles}
							data-icon-name={icon}
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
						/>
					)}
				</button>
			</div>
		);
	},
	migrate(attributes) {
		// Migrate width to align
		const { width, ...rest } = attributes;
		return {
			...rest,
			align: width === 'full' ? 'full' : undefined,
		};
	},
};

/**
 * Version 1: Before lazy loading icon library
 *
 * Changes in v2:
 * - Icons now use data attributes for frontend lazy loading
 * - Frontend icons injected via PHP to avoid bundling 51KB library
 */
const v1 = {
	supports: sharedSupports,
	attributes: {
		targetModalId: {
			type: 'string',
			default: '',
		},
		text: {
			type: 'string',
			default: 'Open Modal',
		},
		buttonStyle: {
			type: 'string',
			default: 'fill',
		},
		width: {
			type: 'string',
			default: 'auto',
		},
		icon: {
			type: 'string',
			default: '',
		},
		iconPosition: {
			type: 'string',
			default: 'none',
		},
		iconSize: {
			type: 'number',
			default: 20,
		},
		iconGap: {
			type: 'string',
			default: '8px',
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v1 blocks have inline SVG icons (no dsgo-lazy-icon class)
		return (
			innerHTML &&
			innerHTML.includes('dsgo-modal-trigger--width-') &&
			!innerHTML.includes('dsgo-lazy-icon')
		);
	},
	save({ attributes }) {
		const {
			targetModalId,
			text,
			buttonStyle,
			width,
			icon,
			iconPosition,
			iconSize,
			iconGap,
		} = attributes;

		const buttonStyles = {
			gap: iconPosition !== 'none' && icon ? iconGap : undefined,
			flexDirection: iconPosition === 'end' ? 'row-reverse' : 'row',
		};

		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			width: `${iconSize}px`,
			height: `${iconSize}px`,
			flexShrink: 0,
		};

		const blockProps = useBlockProps.save({
			className: `dsgo-modal-trigger dsgo-modal-trigger--${buttonStyle} dsgo-modal-trigger--width-${width}`,
			style: { display: width === 'full' ? 'block' : 'inline-block' },
		});

		// Get icon element with fallback
		const iconElement = icon ? getIcon(icon) : null;

		return (
			<div {...blockProps}>
				<button
					className="dsgo-modal-trigger__button"
					data-dsgo-modal-trigger={targetModalId}
					style={buttonStyles}
					type="button"
				>
					{iconElement && iconPosition === 'start' && (
						<span
							className="dsgo-modal-trigger__icon"
							style={iconWrapperStyles}
						>
							{iconElement}
						</span>
					)}
					<RichText.Content
						tagName="span"
						value={text}
						className="dsgo-modal-trigger__text"
					/>
					{iconElement && iconPosition === 'end' && (
						<span
							className="dsgo-modal-trigger__icon"
							style={iconWrapperStyles}
						>
							{iconElement}
						</span>
					)}
				</button>
			</div>
		);
	},
	migrate(attributes) {
		// Migrate width to align
		const { width, ...rest } = attributes;
		return {
			...rest,
			align: width === 'full' ? 'full' : undefined,
		};
	},
};

export default [v3, v2, v1];

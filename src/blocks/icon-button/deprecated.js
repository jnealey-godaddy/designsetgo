/**
 * Icon Button Block - Deprecated Versions
 *
 * Handles backward compatibility for blocks saved with previous versions.
 *
 * @since 1.0.0
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';
import { getIcon } from '../icon/utils/svg-icons';
import { convertPaddingValue } from './utils/padding';
import { convertPresetToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Shared supports definition for all deprecated versions.
 * Mirrors block.json supports but uses __experimentalBorder (the historical key).
 */
const sharedSupports = {
	anchor: true,
	align: ['left', 'center', 'right', 'full'],
	alignWide: true,
	html: false,
	inserter: true,
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
		gradients: true,
		__experimentalSkipSerialization: true,
		__experimentalDefaultControls: {
			background: true,
			text: true,
		},
	},
	typography: {
		fontSize: true,
		lineHeight: true,
		fontWeight: true,
		__experimentalDefaultControls: {
			fontSize: true,
		},
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
	shadow: true,
};

/**
 * Version 7: Before the theme icon-size / icon-style tokens
 *
 * The pre-token format always wrote an explicit `width:Npx;height:Npx` on the
 * icon span and a plain `data-icon-size` (defaulting to 20), with no
 * `data-icon-style` / `data-icon-stroke-width` attributes at all. The current
 * version omits the inline size when the author leaves iconSize unset so the
 * theme default token (settings.custom.designsetgo.iconButton.defaultSize)
 * can take over, and only emits data-icon-style / data-icon-stroke-width for
 * an explicit outlined style.
 *
 * The deprecated attribute schema intentionally has NO default for iconSize:
 * an implicit-default old block re-parses to `undefined`, so the passthrough
 * migrate lets it inherit the theme token. Blocks that set an explicit size
 * keep their stored value as an override.
 */
const v7 = {
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Lazy-format block (post-v6) that still carries an inline size pair
		// on the icon span — the signature of the pre-token serialization.
		return (
			innerHTML &&
			innerHTML.includes('dsgo-lazy-icon') &&
			/width:\s*\d+px\s*;\s*height:\s*\d+px/.test(innerHTML)
		);
	},

	attributes: {
		align: { type: 'string' },
		text: { type: 'string', default: '' },
		url: { type: 'string', default: '' },
		linkTarget: { type: 'string', default: '_self' },
		rel: { type: 'string', default: '' },
		icon: { type: 'string', default: 'lightbulb' },
		iconPosition: { type: 'string', default: 'start' },
		iconSize: { type: 'number' },
		iconGap: { type: 'string', default: '8px' },
		hoverAnimation: { type: 'string', default: 'none' },
		hoverBackgroundColor: { type: 'string', default: '' },
		hoverTextColor: { type: 'string', default: '' },
		modalCloseId: { type: 'string', default: '' },
	},

	save({ attributes }) {
		const {
			text,
			url,
			linkTarget,
			rel,
			icon,
			iconPosition,
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
		const bgColor =
			style?.color?.background ||
			(backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
		const txtColor =
			style?.color?.text ||
			(textColor && `var(--wp--preset--color--${textColor})`);

		// Extract font size (must match edit.js)
		const fontSizeValue =
			style?.typography?.fontSize ||
			(fontSize && `var(--wp--preset--font-size--${fontSize})`);

		// Extract padding (must match edit.js)
		const paddingValue = style?.spacing?.padding;

		// Combined styles for single element (must match edit.js)
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
					convertPresetToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-button-hover-color':
					convertPresetToCSSVar(hoverTextColor),
			}),
		};

		// Pre-token format always baked an explicit size.
		const size = typeof iconSize === 'number' ? iconSize : 20;

		// Icon wrapper styles (OLD: always explicit width/height)
		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			width: `${size}px`,
			height: `${size}px`,
			flexShrink: 0,
		};

		// Build animation class
		let animationClass = '';
		if (hoverAnimation === 'explicit-none') {
			animationClass = ' dsgo-icon-button--no-hover';
		} else if (hoverAnimation && hoverAnimation !== 'none') {
			animationClass = ` dsgo-icon-button--${hoverAnimation}`;
		}

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
						data-icon-size={size}
					/>
				)}
				<RichText.Content
					tagName="span"
					className="dsgo-icon-button__text"
					value={text}
				/>
			</ButtonElement>
		);
	},

	migrate(attributes) {
		// Passthrough. An implicit-default old block has iconSize === undefined
		// here (no default in this schema), so it inherits the theme token;
		// an explicit value is preserved as an override.
		return attributes;
	},
};

/**
 * Version 6: Before align-based full-width
 *
 * Changes in current version:
 * - Removed width attribute toggle (auto/100%)
 * - Full-width now uses WordPress alignment system (alignfull)
 * - Removed dsgo-icon-button--width-full and --width-auto classes
 * - Width attribute "100%" migrated to align: "full"
 */
const v6 = {
	supports: sharedSupports,
	attributes: {
		text: {
			type: 'string',
			default: '',
		},
		url: {
			type: 'string',
			default: '',
		},
		linkTarget: {
			type: 'string',
			default: '_self',
		},
		rel: {
			type: 'string',
			default: '',
		},
		icon: {
			type: 'string',
			default: 'lightbulb',
		},
		iconPosition: {
			type: 'string',
			default: 'start',
		},
		iconSize: {
			type: 'number',
			default: 20,
		},
		iconGap: {
			type: 'string',
			default: '8px',
		},
		width: {
			type: 'string',
			default: 'auto',
		},
		hoverAnimation: {
			type: 'string',
			default: 'none',
		},
		hoverBackgroundColor: {
			type: 'string',
			default: '',
		},
		hoverTextColor: {
			type: 'string',
			default: '',
		},
		modalCloseId: {
			type: 'string',
			default: '',
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v6 blocks use flex (not inline-flex) for full-width; v5 always uses inline-flex
		return (
			innerHTML &&
			innerHTML.includes('dsgo-icon-button--width-') &&
			!innerHTML.includes('inline-flex')
		);
	},
	save({ attributes }) {
		const {
			text,
			url,
			linkTarget,
			rel,
			icon,
			iconPosition,
			iconSize,
			iconGap,
			width,
			hoverAnimation,
			hoverBackgroundColor,
			hoverTextColor,
			style,
			backgroundColor,
			textColor,
			fontSize,
			modalCloseId,
		} = attributes;

		// Extract WordPress color values
		const bgColor =
			style?.color?.background ||
			(backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
		const txtColor =
			style?.color?.text ||
			(textColor && `var(--wp--preset--color--${textColor})`);

		// Extract font size
		const fontSizeValue =
			style?.typography?.fontSize ||
			(fontSize && `var(--wp--preset--font-size--${fontSize})`);

		// Extract padding
		const paddingValue = style?.spacing?.padding;

		// OLD: Combined styles - used width attribute for full-width
		const buttonStyles = {
			display: width === '100%' ? 'flex' : 'inline-flex',
			alignItems: 'center',
			justifyContent: 'center',
			gap: iconPosition !== 'none' && icon ? iconGap : 0,
			width: width === '100%' ? '100%' : 'auto',
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
					convertPresetToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-button-hover-color':
					convertPresetToCSSVar(hoverTextColor),
			}),
		};

		// Icon wrapper styles
		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			width: `${iconSize}px`,
			height: `${iconSize}px`,
			flexShrink: 0,
		};

		// Animation class
		const animationClass =
			hoverAnimation && hoverAnimation !== 'none'
				? ` dsgo-icon-button--${hoverAnimation}`
				: '';

		// OLD: Width class based on width attribute
		const widthClass =
			width === '100%'
				? ' dsgo-icon-button--width-full'
				: ' dsgo-icon-button--width-auto';

		const ButtonElement = url ? 'a' : 'button';

		const blockProps = useBlockProps.save({
			className: `dsgo-icon-button wp-block-button wp-block-button__link wp-element-button${animationClass}${widthClass}`,
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
						data-icon-size={iconSize}
					/>
				)}
				<RichText.Content
					tagName="span"
					className="dsgo-icon-button__text"
					value={text}
				/>
			</ButtonElement>
		);
	},
	migrate(attributes) {
		// Migrate width="100%" to align="full"
		const { width, ...rest } = attributes;
		return {
			...rest,
			align: width === '100%' ? 'full' : attributes.align,
			width: 'auto', // Reset width to auto (no longer used)
		};
	},
};

/**
 * Version 5: Before simplified width options
 *
 * Changes in current version:
 * - Removed 50% and 25% width options (now only auto and 100%)
 * - Changed display to flex for 100% width (was inline-flex for all)
 * - Width values 50% and 25% migrated to auto
 */
const v5 = {
	supports: sharedSupports,
	attributes: {
		text: {
			type: 'string',
			default: '',
		},
		url: {
			type: 'string',
			default: '',
		},
		linkTarget: {
			type: 'string',
			default: '_self',
		},
		rel: {
			type: 'string',
			default: '',
		},
		icon: {
			type: 'string',
			default: 'lightbulb',
		},
		iconPosition: {
			type: 'string',
			default: 'start',
		},
		iconSize: {
			type: 'number',
			default: 20,
		},
		iconGap: {
			type: 'string',
			default: '8px',
		},
		width: {
			type: 'string',
			default: 'auto',
		},
		hoverAnimation: {
			type: 'string',
			default: 'none',
		},
		hoverBackgroundColor: {
			type: 'string',
			default: '',
		},
		hoverTextColor: {
			type: 'string',
			default: '',
		},
		modalCloseId: {
			type: 'string',
			default: '',
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v5 blocks use inline-flex for all widths (including 100%) and raw width value
		return (
			innerHTML &&
			innerHTML.includes('dsgo-icon-button--width-') &&
			innerHTML.includes('inline-flex')
		);
	},
	save({ attributes }) {
		const {
			text,
			url,
			linkTarget,
			rel,
			icon,
			iconPosition,
			iconSize,
			iconGap,
			width,
			hoverAnimation,
			hoverBackgroundColor,
			hoverTextColor,
			style,
			backgroundColor,
			textColor,
			fontSize,
			modalCloseId,
		} = attributes;

		// Extract WordPress color values
		const bgColor =
			style?.color?.background ||
			(backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
		const txtColor =
			style?.color?.text ||
			(textColor && `var(--wp--preset--color--${textColor})`);

		// Extract font size
		const fontSizeValue =
			style?.typography?.fontSize ||
			(fontSize && `var(--wp--preset--font-size--${fontSize})`);

		// Extract padding
		const paddingValue = style?.spacing?.padding;

		// OLD: Combined styles - always used inline-flex and raw width value
		const buttonStyles = {
			display: 'inline-flex',
			alignItems: 'center',
			justifyContent: 'center',
			gap: iconPosition !== 'none' && icon ? iconGap : 0,
			width: width === 'auto' ? 'auto' : width,
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
					convertPresetToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-button-hover-color':
					convertPresetToCSSVar(hoverTextColor),
			}),
		};

		// Icon wrapper styles
		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			width: `${iconSize}px`,
			height: `${iconSize}px`,
			flexShrink: 0,
		};

		// Animation class
		const animationClass =
			hoverAnimation && hoverAnimation !== 'none'
				? ` dsgo-icon-button--${hoverAnimation}`
				: '';

		// Width class
		const widthClass =
			width === '100%'
				? ' dsgo-icon-button--width-full'
				: ' dsgo-icon-button--width-auto';

		const ButtonElement = url ? 'a' : 'button';

		const blockProps = useBlockProps.save({
			className: `dsgo-icon-button wp-block-button wp-block-button__link wp-element-button${animationClass}${widthClass}`,
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
						data-icon-size={iconSize}
					/>
				)}
				<RichText.Content
					tagName="span"
					className="dsgo-icon-button__text"
					value={text}
				/>
			</ButtonElement>
		);
	},
	migrate(attributes) {
		// Convert old percentage widths to auto, and 100% to alignfull
		const { width, ...rest } = attributes;
		const isFullWidth = width === '100%';
		return {
			...rest,
			align: isFullWidth ? 'full' : attributes.align,
			width: 'auto', // Reset width to auto (no longer used for full-width)
		};
	},
};

/**
 * Version 4: Before collapsing to single element structure
 *
 * Changes in current version:
 * - Removed inner wrapper div/a element
 * - Merged all classes and styles onto single element
 * - Button is now single <a> tag instead of <div><a>...</a></div>
 * - Fixes wp-block-button__link class conflicts with theme.json
 * - Visual styles moved to outer wrapper (border-radius fix)
 */
const v4 = {
	supports: sharedSupports,
	attributes: {
		text: {
			type: 'string',
			default: '',
		},
		url: {
			type: 'string',
			default: '',
		},
		linkTarget: {
			type: 'string',
			default: '_self',
		},
		rel: {
			type: 'string',
			default: '',
		},
		icon: {
			type: 'string',
			default: 'lightbulb',
		},
		iconPosition: {
			type: 'string',
			default: 'start',
		},
		iconSize: {
			type: 'number',
			default: 20,
		},
		iconGap: {
			type: 'string',
			default: '8px',
		},
		width: {
			type: 'string',
			default: 'auto',
		},
		hoverAnimation: {
			type: 'string',
			default: 'none',
		},
		hoverBackgroundColor: {
			type: 'string',
			default: '',
		},
		hoverTextColor: {
			type: 'string',
			default: '',
		},
		modalCloseId: {
			type: 'string',
			default: '',
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v4 blocks have the two-div structure with dsgo-icon-button__wrapper
		return (
			innerHTML &&
			innerHTML.includes('dsgo-icon-button__wrapper') &&
			innerHTML.includes('wp-block-button__link')
		);
	},
	save({ attributes }) {
		const {
			text,
			url,
			linkTarget,
			rel,
			icon,
			iconPosition,
			iconSize,
			iconGap,
			width,
			hoverAnimation,
			hoverBackgroundColor,
			hoverTextColor,
			style,
			backgroundColor,
			textColor,
			fontSize,
			modalCloseId,
		} = attributes;

		// Extract WordPress color values
		const bgColor =
			style?.color?.background ||
			(backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
		const txtColor =
			style?.color?.text ||
			(textColor && `var(--wp--preset--color--${textColor})`);

		// Extract font size
		const fontSizeValue =
			style?.typography?.fontSize ||
			(fontSize && `var(--wp--preset--font-size--${fontSize})`);

		// Extract padding
		const paddingValue = style?.spacing?.padding;

		// Visual styles applied to outer wrapper
		const visualStyles = {
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
					convertPresetToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-button-hover-color':
					convertPresetToCSSVar(hoverTextColor),
			}),
		};

		// Layout styles for inner wrapper
		const layoutStyles = {
			display: 'inline-flex',
			alignItems: 'center',
			justifyContent: 'center',
			gap: iconPosition !== 'none' && icon ? iconGap : 0,
			width: width === 'auto' ? 'auto' : width,
			flexDirection: iconPosition === 'end' ? 'row-reverse' : 'row',
		};

		// Calculate icon wrapper styles
		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			width: `${iconSize}px`,
			height: `${iconSize}px`,
			flexShrink: 0,
		};

		// Build animation class
		const animationClass =
			hoverAnimation && hoverAnimation !== 'none'
				? ` dsgo-icon-button--${hoverAnimation}`
				: '';

		// Build width class
		const widthClass =
			width === '100%'
				? ' dsgo-icon-button--width-full'
				: ' dsgo-icon-button--width-auto';

		// OLD: Two-div structure with outer wrapper and inner wrapper
		const blockProps = useBlockProps.save({
			className: `dsgo-icon-button wp-block-button wp-element-button${animationClass}${widthClass}`,
			style: visualStyles,
		});

		// OLD: Inner wrapper with wp-block-button__link class
		const ButtonWrapper = url ? 'a' : 'div';
		const wrapperProps = url
			? {
					className:
						'dsgo-icon-button__wrapper wp-block-button__link',
					style: layoutStyles,
					href: url,
					target: linkTarget,
					rel:
						linkTarget === '_blank'
							? rel || 'noopener noreferrer'
							: rel || undefined,
					...(modalCloseId && {
						'data-dsgo-modal-close': modalCloseId || 'true',
					}),
				}
			: {
					className:
						'dsgo-icon-button__wrapper wp-block-button__link',
					style: layoutStyles,
					...(modalCloseId && {
						'data-dsgo-modal-close': modalCloseId || 'true',
					}),
				};

		return (
			<div {...blockProps}>
				<ButtonWrapper {...wrapperProps}>
					{iconPosition !== 'none' && icon && (
						<span
							className="dsgo-icon-button__icon dsgo-lazy-icon"
							style={iconWrapperStyles}
							data-icon-name={icon}
							data-icon-size={iconSize}
						/>
					)}
					<RichText.Content
						tagName="span"
						className="dsgo-icon-button__text"
						value={text}
					/>
				</ButtonWrapper>
			</div>
		);
	},
	migrate(attributes) {
		// No attribute changes needed - only save function changed
		return attributes;
	},
};

/**
 * Version 3: Before CSS-based width handling
 *
 * Changes in current version:
 * - Removed inline display/width styles from block wrapper
 * - Added CSS classes for width control (--width-auto, --width-full)
 * - Block wrapper is now block-level by default to respect WordPress content width
 */
const v3 = {
	supports: sharedSupports,
	attributes: {
		text: {
			type: 'string',
			default: '',
		},
		url: {
			type: 'string',
			default: '',
		},
		linkTarget: {
			type: 'string',
			default: '_self',
		},
		rel: {
			type: 'string',
			default: '',
		},
		icon: {
			type: 'string',
			default: 'lightbulb',
		},
		iconPosition: {
			type: 'string',
			default: 'start',
		},
		iconSize: {
			type: 'number',
			default: 20,
		},
		iconGap: {
			type: 'string',
			default: '8px',
		},
		width: {
			type: 'string',
			default: 'auto',
		},
		hoverAnimation: {
			type: 'string',
			default: 'none',
		},
		hoverBackgroundColor: {
			type: 'string',
			default: '',
		},
		hoverTextColor: {
			type: 'string',
			default: '',
		},
		modalCloseId: {
			type: 'string',
			default: '',
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v3 blocks have inline display/width styles on outer wrapper and dsgo-icon-button__wrapper without wp-block-button__link
		return (
			innerHTML &&
			innerHTML.includes('dsgo-icon-button__wrapper') &&
			!innerHTML.includes('wp-block-button__link') &&
			innerHTML.includes('fit-content')
		);
	},
	save({ attributes }) {
		const {
			text,
			url,
			linkTarget,
			rel,
			icon,
			iconPosition,
			iconSize,
			iconGap,
			width,
			hoverAnimation,
			hoverBackgroundColor,
			hoverTextColor,
			style,
			backgroundColor,
			textColor,
			modalCloseId,
		} = attributes;

		// Extract WordPress color values
		const bgColor =
			style?.color?.background ||
			(backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
		const txtColor =
			style?.color?.text ||
			(textColor && `var(--wp--preset--color--${textColor})`);

		// Calculate button styles
		const buttonStyles = {
			display: 'inline-flex',
			alignItems: 'center',
			justifyContent: 'center',
			gap: iconPosition !== 'none' && icon ? iconGap : 0,
			width: width === 'auto' ? 'auto' : width,
			flexDirection: iconPosition === 'end' ? 'row-reverse' : 'row',
			...(bgColor && { backgroundColor: bgColor }),
			...(txtColor && { color: txtColor }),
			...(hoverBackgroundColor && {
				'--dsgo-button-hover-bg':
					convertPresetToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-button-hover-color':
					convertPresetToCSSVar(hoverTextColor),
			}),
		};

		// Calculate icon wrapper styles
		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			width: `${iconSize}px`,
			height: `${iconSize}px`,
			flexShrink: 0,
		};

		// Build animation class
		const animationClass =
			hoverAnimation && hoverAnimation !== 'none'
				? ` dsgo-icon-button--${hoverAnimation}`
				: '';

		// OLD: Inline display/width styles on block wrapper
		const blockProps = useBlockProps.save({
			className: `dsgo-icon-button${animationClass}`,
			style: {
				display: width === '100%' ? 'block' : 'inline-block',
				...(width === 'auto' && {
					width: 'fit-content',
					maxWidth: 'fit-content',
				}),
			},
		});

		// Wrap in link if URL is provided
		const ButtonWrapper = url ? 'a' : 'div';
		const wrapperProps = url
			? {
					className: 'dsgo-icon-button__wrapper',
					style: buttonStyles,
					href: url,
					target: linkTarget,
					rel:
						linkTarget === '_blank'
							? rel || 'noopener noreferrer'
							: rel || undefined,
					...(modalCloseId && {
						'data-dsgo-modal-close': modalCloseId || 'true',
					}),
				}
			: {
					className: 'dsgo-icon-button__wrapper',
					style: buttonStyles,
					...(modalCloseId && {
						'data-dsgo-modal-close': modalCloseId || 'true',
					}),
				};

		return (
			<div {...blockProps}>
				<ButtonWrapper {...wrapperProps}>
					{iconPosition !== 'none' && icon && (
						<span
							className="dsgo-icon-button__icon dsgo-lazy-icon"
							style={iconWrapperStyles}
							data-icon-name={icon}
							data-icon-size={iconSize}
						/>
					)}
					<RichText.Content
						tagName="span"
						className="dsgo-icon-button__text"
						value={text}
					/>
				</ButtonWrapper>
			</div>
		);
	},
	migrate(attributes) {
		// No attribute changes needed - only save function changed
		return attributes;
	},
};

/**
 * Version 2: Before lazy loading icon library
 *
 * Changes in current version:
 * - Icons now use data attributes for frontend lazy loading
 * - Frontend icons injected via PHP to avoid bundling 51KB library
 * - Editor still uses getIcon() from shared library
 */
const v2 = {
	supports: sharedSupports,
	attributes: {
		text: {
			type: 'string',
			default: '',
		},
		url: {
			type: 'string',
			default: '',
		},
		linkTarget: {
			type: 'string',
			default: '_self',
		},
		rel: {
			type: 'string',
			default: '',
		},
		icon: {
			type: 'string',
			default: 'lightbulb',
		},
		iconPosition: {
			type: 'string',
			default: 'start',
		},
		iconSize: {
			type: 'number',
			default: 20,
		},
		iconGap: {
			type: 'string',
			default: '8px',
		},
		width: {
			type: 'string',
			default: 'auto',
		},
		hoverAnimation: {
			type: 'string',
			default: 'none',
		},
		hoverBackgroundColor: {
			type: 'string',
			default: '',
		},
		hoverTextColor: {
			type: 'string',
			default: '',
		},
		modalCloseId: {
			type: 'string',
			default: '',
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v2 blocks have inline SVG icons (no dsgo-lazy-icon class)
		return (
			innerHTML &&
			!innerHTML.includes('dsgo-lazy-icon') &&
			innerHTML.includes('dsgo-icon-button__icon')
		);
	},
	save({ attributes }) {
		const {
			text,
			url,
			linkTarget,
			rel,
			icon,
			iconPosition,
			iconSize,
			iconGap,
			width,
			hoverAnimation,
			hoverBackgroundColor,
			hoverTextColor,
			style,
			backgroundColor,
			textColor,
			modalCloseId,
		} = attributes;

		// Extract WordPress color values
		const bgColor =
			style?.color?.background ||
			(backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
		const txtColor =
			style?.color?.text ||
			(textColor && `var(--wp--preset--color--${textColor})`);

		// Calculate button styles
		const buttonStyles = {
			display: 'inline-flex',
			alignItems: 'center',
			justifyContent: 'center',
			gap: iconPosition !== 'none' && icon ? iconGap : 0,
			width: width === 'auto' ? 'auto' : width,
			flexDirection: iconPosition === 'end' ? 'row-reverse' : 'row',
			...(bgColor && { backgroundColor: bgColor }),
			...(txtColor && { color: txtColor }),
			...(hoverBackgroundColor && {
				'--dsgo-button-hover-bg':
					convertPresetToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-button-hover-color':
					convertPresetToCSSVar(hoverTextColor),
			}),
		};

		// Calculate icon wrapper styles
		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			width: `${iconSize}px`,
			height: `${iconSize}px`,
			flexShrink: 0,
		};

		// Build animation class
		const animationClass =
			hoverAnimation && hoverAnimation !== 'none'
				? ` dsgo-icon-button--${hoverAnimation}`
				: '';

		const blockProps = useBlockProps.save({
			className: `dsgo-icon-button${animationClass}`,
			style: {
				display: width === '100%' ? 'block' : 'inline-block',
				...(width === 'auto' && {
					width: 'fit-content',
					maxWidth: 'fit-content',
				}),
			},
		});

		// Wrap in link if URL is provided
		const ButtonWrapper = url ? 'a' : 'div';
		const wrapperProps = url
			? {
					className: 'dsgo-icon-button__wrapper',
					style: buttonStyles,
					href: url,
					target: linkTarget,
					rel:
						linkTarget === '_blank'
							? rel || 'noopener noreferrer'
							: rel || undefined,
					...(modalCloseId && {
						'data-dsgo-modal-close': modalCloseId,
					}),
				}
			: {
					className: 'dsgo-icon-button__wrapper',
					style: buttonStyles,
					...(modalCloseId && {
						'data-dsgo-modal-close': modalCloseId,
					}),
				};

		return (
			<div {...blockProps}>
				<ButtonWrapper {...wrapperProps}>
					{iconPosition !== 'none' && icon && (
						<span
							className="dsgo-icon-button__icon"
							style={iconWrapperStyles}
						>
							{getIcon(icon)}
						</span>
					)}
					<RichText.Content
						tagName="span"
						className="dsgo-icon-button__text"
						value={text}
					/>
				</ButtonWrapper>
			</div>
		);
	},
	migrate(attributes) {
		// No attribute changes needed - only save function changed
		return attributes;
	},
};

/**
 * Version 1: Before padding split logic
 *
 * Changes in current version:
 * - Added splitPaddingStyles utility to properly handle padding
 * - Padding is now applied to button wrapper instead of outer div
 */
const v1 = {
	supports: sharedSupports,
	attributes: {
		text: {
			type: 'string',
			default: '',
		},
		url: {
			type: 'string',
			default: '',
		},
		linkTarget: {
			type: 'string',
			default: '_self',
		},
		rel: {
			type: 'string',
			default: '',
		},
		icon: {
			type: 'string',
			default: 'lightbulb',
		},
		iconPosition: {
			type: 'string',
			default: 'start',
		},
		iconSize: {
			type: 'number',
			default: 20,
		},
		iconGap: {
			type: 'string',
			default: '8px',
		},
		width: {
			type: 'string',
			default: 'auto',
		},
		hoverAnimation: {
			type: 'string',
			default: 'none',
		},
		hoverBackgroundColor: {
			type: 'string',
			default: '',
		},
		hoverTextColor: {
			type: 'string',
			default: '',
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v1 blocks don't have splitPaddingStyles and render icon position differently
		// They also don't have dsgo-lazy-icon and have role="button" on non-link wrappers
		return (
			innerHTML &&
			innerHTML.includes('role="button"') &&
			!innerHTML.includes('dsgo-lazy-icon')
		);
	},
	save({ attributes }) {
		const {
			text,
			url,
			linkTarget,
			rel,
			icon,
			iconPosition,
			iconSize,
			iconGap,
			width,
			hoverAnimation,
			hoverBackgroundColor,
			hoverTextColor,
			style,
			backgroundColor,
			textColor,
		} = attributes;

		// Extract WordPress color values (must match edit.js)
		const bgColor =
			style?.color?.background ||
			(backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
		const txtColor =
			style?.color?.text ||
			(textColor && `var(--wp--preset--color--${textColor})`);

		// Calculate button styles - OLD VERSION (without splitPaddingStyles)
		const buttonStyles = {
			display: 'inline-flex',
			alignItems: 'center',
			justifyContent: 'center',
			gap: iconPosition !== 'none' && icon ? iconGap : 0,
			width: width === 'auto' ? 'auto' : width,
			flexDirection: iconPosition === 'end' ? 'row-reverse' : 'row',
			...(bgColor && { backgroundColor: bgColor }),
			...(txtColor && { color: txtColor }),
			...(hoverBackgroundColor && {
				'--dsgo-button-hover-bg':
					convertPresetToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-button-hover-color':
					convertPresetToCSSVar(hoverTextColor),
			}),
		};

		// Calculate icon wrapper styles
		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			width: `${iconSize}px`,
			height: `${iconSize}px`,
			flexShrink: 0,
		};

		// Build animation class
		const animationClass =
			hoverAnimation && hoverAnimation !== 'none'
				? ` dsgo-icon-button--${hoverAnimation}`
				: '';

		const blockProps = useBlockProps.save({
			className: `dsgo-icon-button${animationClass}`,
			style: { display: width === '100%' ? 'block' : 'inline-block' },
		});

		// Wrap in link if URL is provided
		const ButtonWrapper = url ? 'a' : 'div';
		const wrapperProps = url
			? {
					className: 'dsgo-icon-button__wrapper',
					style: buttonStyles,
					href: url,
					target: linkTarget,
					rel:
						linkTarget === '_blank'
							? rel || 'noopener noreferrer'
							: rel || undefined,
				}
			: {
					className: 'dsgo-icon-button__wrapper',
					style: buttonStyles,
					role: 'button',
				};

		return (
			<div {...blockProps}>
				<ButtonWrapper {...wrapperProps}>
					{iconPosition !== 'none' &&
						iconPosition !== 'end' &&
						icon && (
							<span
								className="dsgo-icon-button__icon"
								style={iconWrapperStyles}
							>
								{getIcon(icon)}
							</span>
						)}
					{text && (
						<RichText.Content
							tagName="span"
							value={text}
							className="dsgo-icon-button__text"
						/>
					)}
					{iconPosition === 'end' && icon && (
						<span
							className="dsgo-icon-button__icon"
							style={iconWrapperStyles}
						>
							{getIcon(icon)}
						</span>
					)}
				</ButtonWrapper>
			</div>
		);
	},
	migrate(attributes) {
		// No attribute changes needed - only save function changed
		return attributes;
	},
};

export default [v7, v6, v5, v4, v3, v2, v1];

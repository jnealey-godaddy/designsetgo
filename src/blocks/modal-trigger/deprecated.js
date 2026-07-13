/**
 * Modal Trigger Block - Deprecated Versions
 *
 * Handles backward compatibility for blocks saved with previous versions.
 *
 * @since 1.2.0
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';
import { getIcon } from '../icon/utils/svg-icons';
import { getDeprecatedBlockHTML } from '../../utils/deprecated-block-html';

/**
 * Every deprecation must land on the CURRENT attribute schema — deprecations do
 * not cascade, so exactly one migrate() runs for any given stored block. All
 * four deprecations below (v4 down to v1) carried `align: left|center|right|full`;
 * the current block uses a `justification` wrapper + `fullWidth` toggle instead,
 * because core's constrained layout excludes aligned blocks (`alignleft`/
 * `alignright`) from the content-size cap (see wp-includes/block-supports/layout.php),
 * and the block root moved from the `<button>` itself to a block-level
 * positioning wrapper (v4's own eligibility signature) that core CAN cap.
 * `align: "full"` used to mean "stretch the button to 100%"; that meaning now
 * lives in `fullWidth` since `full` on the wrapper instead bleeds the wrapper
 * itself edge-to-edge, matching every other WordPress `alignfull` block.
 *
 * @param {Object} attributes Attributes as sourced from a matched deprecation.
 *                            May or may not include `align`, depending on
 *                            whether the old markup set it.
 * @return {Object} Attributes with `align` replaced by `justification`/`fullWidth`.
 */
function migrateAlign(attributes) {
	const { align, ...rest } = attributes;
	return {
		...rest,
		justification: ['left', 'center', 'right'].includes(align)
			? align
			: 'left',
		fullWidth: align === 'full',
	};
}

/**
 * Shared supports for all deprecated versions.
 * Uses __experimentalBorder (the historical name) instead of border. This is
 * also the exact supports block the (now-superseded) current block.json
 * carried immediately before the justification-wrapper refactor — including
 * `color.__experimentalSkipSerialization` (already present pre-refactor) but
 * NOT the new border/typography skip-serialization, which only applies to the
 * post-refactor block whose root moved to the wrapper.
 *
 * typography MUST use the __experimental-prefixed keys
 * (__experimentalFontFamily / __experimentalFontWeight /
 * __experimentalLetterSpacing) — those are what WP's `hasBlockSupport()`
 * actually checks (see packages/block-editor/src/hooks/font-family.js et
 * al.). Un-prefixed `fontFamily`/`fontWeight`/`letterSpacing` keys here are
 * silently ignored by WP's support-detection filters, so `core/fontFamily/
 * addAttribute` never registers a `fontFamily` attribute on the
 * deprecation and any stored `fontFamily` value is stripped from parsed
 * attributes before `migrate()` ever runs — silent, unrecoverable data loss.
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
		__experimentalFontFamily: true,
		__experimentalFontWeight: true,
		__experimentalLetterSpacing: true,
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
 * Version 4: Before the justification wrapper
 *
 * The block root USED to be the `<button>` itself, positioned via WordPress's
 * `align: left|center|right|full` support. That was a misuse: core's
 * constrained layout excludes `.alignleft`/`.alignright` from the content-size
 * cap (see wp-includes/block-supports/layout.php), and an inline-flex root
 * makes auto margins inert even when `align: center` narrowly worked. The
 * current version introduces a block-level `.dsgo-justify` wrapper as the
 * block root — which core CAN cap at the content column — with the button
 * shrink-wrapped inside it and positioned via `justify-content` from a new
 * `justification` attribute. `align: "full"` used to mean "stretch the button
 * to 100%"; that meaning now lives in the `fullWidth` attribute, since `full`
 * on the wrapper instead bleeds the wrapper itself edge-to-edge.
 *
 * `supports` here is `sharedSupports` — the exact supports block the
 * (now-superseded) current block.json carried immediately before this change.
 */
const v4 = {
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		// Pre-wrapper markup: the block root IS the button, not a `<div>`.
		return !!innerHTML && !innerHTML.trimStart().startsWith('<div');
	},

	attributes: {
		align: { type: 'string' },
		targetModalId: { type: 'string', default: '' },
		text: { type: 'string', default: 'Open Modal' },
		buttonStyle: { type: 'string', default: 'fill' },
		icon: { type: 'string', default: '' },
		iconPosition: { type: 'string', default: 'none' },
		iconStyle: { type: 'string', enum: ['filled', 'outlined'] },
		strokeWidth: { type: 'number', default: 1.5 },
		iconSize: { type: 'number' },
		iconGap: { type: 'string', default: '8px' },
	},

	// Verbatim copy of the pre-wrapper save.js: single `<button>` root
	// carrying inline display/width/flexDirection and the align classes, with
	// icon rendered as two conditional spans (start/end).
	save({ attributes }) {
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
	},

	migrate(attributes) {
		return migrateAlign(attributes);
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
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
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
		// Passthrough (plus the shared align→justification/fullWidth
		// conversion). An implicit-default old block has iconSize/iconStyle
		// === undefined here (no default in this schema), so it inherits the
		// theme token; explicit values are preserved as overrides.
		return migrateAlign(attributes);
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
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
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
		// This version only ever encoded a boolean auto/full toggle via the
		// legacy `width` attribute — it never stored left/center/right — so
		// merge it into `align` (preserving any align a user separately set
		// via the toolbar, since sharedSupports.align was active even though
		// this version's own save() never read it) and run it through the
		// shared conversion, exactly like v1 below.
		const { width, ...rest } = attributes;
		return migrateAlign({
			...rest,
			align: width === 'full' ? 'full' : rest.align,
		});
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
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
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
		// Same width→align merge as v2, then the shared conversion.
		const { width, ...rest } = attributes;
		return migrateAlign({
			...rest,
			align: width === 'full' ? 'full' : rest.align,
		});
	},
};

export default [v4, v3, v2, v1];

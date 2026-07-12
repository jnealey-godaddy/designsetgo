/**
 * Icon Block - Deprecated Versions
 *
 * Handles backward compatibility for blocks saved with previous versions.
 *
 * @since 1.2.0
 */

import { useBlockProps } from '@wordpress/block-editor';
import { getIcon } from './utils/svg-icons';

/**
 * Shared supports definition for all deprecated versions.
 * Mirrors block.json supports but uses __experimentalBorder (the historical key).
 */
const sharedSupports = {
	anchor: true,
	align: ['left', 'center', 'right', 'wide', 'full'],
	html: false,
	inserter: true,
	spacing: {
		margin: true,
		padding: true,
		__experimentalDefaultControls: {
			margin: true,
			padding: true,
		},
	},
	color: {
		background: true,
		text: true,
		__experimentalDefaultControls: {
			background: true,
			text: true,
		},
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
 * Sanitize URL to prevent XSS attacks
 *
 * @param {string} url URL to sanitize
 * @return {string} Sanitized URL or empty string if dangerous
 */
function sanitizeUrl(url) {
	if (!url || typeof url !== 'string') {
		return '';
	}

	// Block dangerous protocols
	const dangerousProtocols = /^(javascript|data|vbscript|file|about):/i;
	if (dangerousProtocols.test(url.trim())) {
		return '';
	}

	return url;
}

/**
 * vAlign — `align: left|center|right` replaced by `justification`.
 *
 * Icon was already dynamic here (save() === null), so the stored markup for a
 * modern icon is a self-closing comment — there is no HTML to reproduce. Only
 * the attribute schema changed: `align: left|center|right` became
 * `justification`, because core's constrained layout excludes aligned blocks
 * from the content-size cap (see wp-includes/block-supports/layout.php). Icon
 * keeps `align: wide|full` (unaffected — those stay in supports on the
 * current block), so isEligible narrows to exactly the three values being
 * converted.
 *
 * `supports` MUST declare the full support set (color / border / spacing),
 * not just `align`. WP re-runs the `blocks.registerBlockType` filters
 * (color.js, border.js, spacing.js, align.js) against EACH deprecation entry
 * at registration time, and those filters add `backgroundColor` / `textColor`
 * / `gradient` / `borderColor` / `style` to `attributes` only when the
 * matching support is present. A `supports` block that declares only `align`
 * silently tells WordPress the old icon had no colour/border/spacing
 * supports, so `getBlockAttributes()` strips those attributes before
 * `migrate()` ever runs, permanently discarding stored styling — the exact
 * bug found and fixed on Pill's equivalent deprecation. `sharedSupports`
 * already carries color/border/spacing (Icon's actual historical support
 * set — no gradients), so it's reused here as-is with only `align` narrowed
 * to the pre-justification set.
 */
const vAlign = {
	attributes: {
		icon: { type: 'string', default: 'star' },
		iconStyle: { type: 'string', enum: ['filled', 'outlined'] },
		strokeWidth: { type: 'number', default: 1.5 },
		iconSize: { type: 'number' },
		rotation: { type: 'number', default: 0 },
		linkUrl: { type: 'string', default: '' },
		linkTarget: { type: 'string', default: '_self' },
		linkRel: { type: 'string', default: '' },
		ariaLabel: { type: 'string', default: '' },
		isDecorative: { type: 'boolean', default: false },
	},
	supports: sharedSupports,
	isEligible(attributes) {
		return ['left', 'center', 'right'].includes(attributes.align);
	},
	save() {
		return null;
	},
	migrate(attributes) {
		// isEligible() already narrowed align to left|center|right, so no
		// wide/full branch is needed here (contrast with vLazy/v2/v1 below,
		// whose isEligible does not filter on align's value).
		const { align, ...rest } = attributes;
		return { ...rest, justification: align };
	},
};

/**
 * Lazy-placeholder format — the last STATIC version, in use immediately before
 * the Icon block became server-rendered (dynamic).
 *
 * Static icons saved a `.dsgo-lazy-icon` placeholder whose SVG was injected
 * client-side. The dynamic block now renders the SVG in PHP (render.php) and its
 * save() returns null, so any stored placeholder markup would otherwise trip the
 * editor's "Attempt Recovery" warning. This deprecation reproduces that markup
 * and migrates existing content untouched (passthrough).
 *
 * isEligible matches ANY lazy-icon placeholder, so it also covers the earlier
 * always-inline-size format that v2 handled — both simply migrate to the dynamic
 * block with their attributes preserved.
 */
const vLazy = {
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, { innerHTML }) {
		return Boolean(innerHTML) && innerHTML.includes('dsgo-lazy-icon');
	},

	attributes: {
		icon: { type: 'string', default: 'star' },
		iconStyle: { type: 'string', enum: ['filled', 'outlined'] },
		strokeWidth: { type: 'number', default: 1.5 },
		iconSize: { type: 'number' },
		rotation: { type: 'number', default: 0 },
		linkUrl: { type: 'string', default: '' },
		linkTarget: { type: 'string', default: '_self' },
		linkRel: { type: 'string', default: '' },
		ariaLabel: { type: 'string', default: '' },
		isDecorative: { type: 'boolean', default: false },
	},

	save({ attributes }) {
		const {
			icon,
			iconStyle,
			strokeWidth,
			iconSize,
			rotation,
			linkUrl,
			linkTarget,
			linkRel,
			ariaLabel,
			isDecorative,
		} = attributes;

		const blockProps = useBlockProps.save({
			className: 'dsgo-icon',
			style: {
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'center',
			},
		});

		// Size is only written inline when explicitly set; otherwise the theme
		// default token (via style.scss) applies.
		const hasExplicitSize = typeof iconSize === 'number';
		const iconWrapperStyle = {
			...(hasExplicitSize && {
				width: `${iconSize}px`,
				height: `${iconSize}px`,
			}),
			display: 'inline-flex',
			alignItems: 'center',
			justifyContent: 'center',
			transform: rotation !== 0 ? `rotate(${rotation}deg)` : undefined,
			borderRadius: 'inherit',
		};

		const getAriaAttributes = () => {
			if (isDecorative) {
				return { role: 'presentation', 'aria-hidden': 'true' };
			}
			if (ariaLabel) {
				return { role: 'img', 'aria-label': ariaLabel };
			}
			const fallbackLabel = icon
				.replace(/-/g, ' ')
				.replace(/\b\w/g, (l) => l.toUpperCase());
			return { role: 'img', 'aria-label': fallbackLabel };
		};

		const ariaAttributes = getAriaAttributes();

		const iconElement = (
			<div
				className="dsgo-icon__wrapper dsgo-lazy-icon"
				style={iconWrapperStyle}
				data-icon-name={icon}
				data-icon-style={iconStyle || undefined}
				data-icon-stroke-width={strokeWidth}
				{...ariaAttributes}
			/>
		);

		const safeUrl = sanitizeUrl(linkUrl);

		return (
			<div {...blockProps}>
				{safeUrl ? (
					<a
						href={safeUrl}
						target={linkTarget}
						rel={
							linkTarget === '_blank'
								? linkRel || 'noopener noreferrer'
								: linkRel || undefined
						}
					>
						{iconElement}
					</a>
				) : (
					iconElement
				)}
			</div>
		);
	},

	migrate(attributes) {
		// Deprecations do not cascade — a legacy lazy-placeholder icon matches
		// THIS entry, never vAlign above, so migrate() must land on the
		// CURRENT schema (justification) itself, not the intermediate `align`
		// schema. wide/full stay on align; left/center/right become
		// justification.
		const { align, ...rest } = attributes;
		const isJustify = ['left', 'center', 'right'].includes(align);
		return {
			...rest,
			...(isJustify ? {} : { align }),
			justification: isJustify ? align : 'center',
		};
	},
};

/**
 * Version 2: Before the theme icon-size / icon-style tokens
 *
 * The pre-token format always wrote an explicit `width:Npx;height:Npx` on the
 * wrapper and a `data-icon-style` attribute, so implicit-default icons were
 * baked to a fixed 48px / "filled". The current version omits both when the
 * author leaves them unset so the theme default token
 * (settings.custom.designsetgo.icon.*) can take over.
 *
 * The deprecated attribute schema intentionally has NO default for iconSize /
 * iconStyle: an implicit-default old block re-parses to `undefined`, so the
 * passthrough migrate lets it inherit the theme default. Blocks that set an
 * explicit size/style keep their stored value as an override.
 */
const v2 = {
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Lazy-format block (post-v1) that still carries an inline size pair —
		// the signature of the pre-token serialization.
		return (
			innerHTML &&
			innerHTML.includes('dsgo-lazy-icon') &&
			/width:\s*\d+px\s*;\s*height:\s*\d+px/.test(innerHTML)
		);
	},

	attributes: {
		icon: { type: 'string', default: 'star' },
		iconStyle: { type: 'string' },
		strokeWidth: { type: 'number', default: 1.5 },
		iconSize: { type: 'number' },
		rotation: { type: 'number', default: 0 },
		linkUrl: { type: 'string', default: '' },
		linkTarget: { type: 'string', default: '_self' },
		linkRel: { type: 'string', default: '' },
		ariaLabel: { type: 'string', default: '' },
		isDecorative: { type: 'boolean', default: false },
	},

	save({ attributes }) {
		const {
			icon,
			iconStyle,
			strokeWidth,
			iconSize,
			rotation,
			linkUrl,
			linkTarget,
			linkRel,
			ariaLabel,
			isDecorative,
		} = attributes;

		const blockProps = useBlockProps.save({
			className: 'dsgo-icon',
			style: {
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'center',
			},
		});

		// Pre-token format always baked an explicit size.
		const size = typeof iconSize === 'number' ? iconSize : 48;
		const style = iconStyle || 'filled';

		const iconWrapperStyle = {
			width: `${size}px`,
			height: `${size}px`,
			display: 'inline-flex',
			alignItems: 'center',
			justifyContent: 'center',
			transform: rotation !== 0 ? `rotate(${rotation}deg)` : undefined,
			borderRadius: 'inherit',
		};

		const getAriaAttributes = () => {
			if (isDecorative) {
				return { role: 'presentation', 'aria-hidden': 'true' };
			}
			if (ariaLabel) {
				return { role: 'img', 'aria-label': ariaLabel };
			}
			const fallbackLabel = icon
				.replace(/-/g, ' ')
				.replace(/\b\w/g, (l) => l.toUpperCase());
			return { role: 'img', 'aria-label': fallbackLabel };
		};

		const ariaAttributes = getAriaAttributes();

		const iconElement = (
			<div
				className="dsgo-icon__wrapper dsgo-lazy-icon"
				style={iconWrapperStyle}
				data-icon-name={icon}
				data-icon-style={style}
				data-icon-stroke-width={strokeWidth}
				{...ariaAttributes}
			/>
		);

		const safeUrl = sanitizeUrl(linkUrl);

		return (
			<div {...blockProps}>
				{safeUrl ? (
					<a
						href={safeUrl}
						target={linkTarget}
						rel={
							linkTarget === '_blank'
								? linkRel || 'noopener noreferrer'
								: linkRel || undefined
						}
					>
						{iconElement}
					</a>
				) : (
					iconElement
				)}
			</div>
		);
	},

	migrate(attributes) {
		// iconSize/iconStyle: passthrough as before — an implicit-default old
		// block has them === undefined here (no default in this schema), so
		// it inherits the theme token; explicit values are preserved as
		// overrides. align: deprecations do not cascade — a legacy pre-token
		// icon matches THIS entry, never vAlign above, so migrate() must land
		// on the CURRENT schema (justification) itself. wide/full stay on
		// align; left/center/right become justification.
		const { align, ...rest } = attributes;
		const isJustify = ['left', 'center', 'right'].includes(align);
		return {
			...rest,
			...(isJustify ? {} : { align }),
			justification: isJustify ? align : 'center',
		};
	},
};

/**
 * Version 1: Before lazy loading icon library
 *
 * Changes in current version:
 * - Icons now use data attributes for frontend lazy loading
 * - Frontend icons injected via PHP to avoid bundling 51KB library
 * - Editor still uses getIcon() from shared library
 */
const v1 = {
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v1 is eligible if the block DOES NOT have dsgo-lazy-icon class
		// New blocks use dsgo-lazy-icon for frontend injection
		// Old blocks have inline SVG without this class
		return innerHTML && !innerHTML.includes('dsgo-lazy-icon');
	},

	attributes: {
		icon: {
			type: 'string',
			default: 'star',
		},
		iconStyle: {
			type: 'string',
			default: 'filled',
		},
		strokeWidth: {
			type: 'number',
			default: 1.5,
		},
		iconSize: {
			type: 'number',
			default: 48,
		},
		rotation: {
			type: 'number',
			default: 0,
		},
		linkUrl: {
			type: 'string',
			default: '',
		},
		linkTarget: {
			type: 'string',
			default: '_self',
		},
		linkRel: {
			type: 'string',
			default: '',
		},
		ariaLabel: {
			type: 'string',
			default: '',
		},
		isDecorative: {
			type: 'boolean',
			default: false,
		},
	},
	save({ attributes }) {
		const {
			icon,
			iconStyle,
			strokeWidth,
			iconSize,
			rotation,
			linkUrl,
			linkTarget,
			linkRel,
			ariaLabel,
			isDecorative,
		} = attributes;

		const blockProps = useBlockProps.save({
			className: 'dsgo-icon',
			style: {
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'center',
			},
		});

		// Icon wrapper styles
		const iconWrapperStyle = {
			width: `${iconSize}px`,
			height: `${iconSize}px`,
			display: 'inline-flex',
			alignItems: 'center',
			justifyContent: 'center',
			transform: rotation !== 0 ? `rotate(${rotation}deg)` : undefined,
			borderRadius: 'inherit',
		};

		// Determine ARIA attributes based on accessibility settings
		const getAriaAttributes = () => {
			if (isDecorative) {
				return {
					role: 'presentation',
					'aria-hidden': 'true',
				};
			}

			if (ariaLabel) {
				return {
					role: 'img',
					'aria-label': ariaLabel,
				};
			}

			// Fallback to icon name (convert to readable format)
			const fallbackLabel = icon
				.replace(/-/g, ' ')
				.replace(/\b\w/g, (l) => l.toUpperCase());

			return {
				role: 'img',
				'aria-label': fallbackLabel,
			};
		};

		const ariaAttributes = getAriaAttributes();

		// Render icon with getIcon() - OLD VERSION
		const iconElement = (
			<div
				className={`dsgo-icon__wrapper${
					iconStyle === 'outlined' ? ' dsgo-icon-outlined' : ''
				}`}
				style={{
					...iconWrapperStyle,
					...(iconStyle === 'outlined' && {
						'--icon-stroke-width': strokeWidth,
					}),
				}}
				{...ariaAttributes}
			>
				{getIcon(icon)}
			</div>
		);

		// Sanitize URL for security
		const safeUrl = sanitizeUrl(linkUrl);

		return (
			<div {...blockProps}>
				{safeUrl ? (
					<a
						href={safeUrl}
						target={linkTarget}
						rel={
							linkTarget === '_blank'
								? linkRel || 'noopener noreferrer'
								: linkRel || undefined
						}
					>
						{iconElement}
					</a>
				) : (
					iconElement
				)}
			</div>
		);
	},
	migrate(attributes) {
		// Save function changed (lazy-loading icon library), plus align:
		// deprecations do not cascade — a legacy v1 icon matches THIS entry,
		// never vAlign above, so migrate() must land on the CURRENT schema
		// (justification) itself. wide/full stay on align; left/center/right
		// become justification.
		const { align, ...rest } = attributes;
		const isJustify = ['left', 'center', 'right'].includes(align);
		return {
			...rest,
			...(isJustify ? {} : { align }),
			justification: isJustify ? align : 'center',
		};
	},
};

export default [vAlign, vLazy, v2, v1];

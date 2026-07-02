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
		// Passthrough. An implicit-default old block has iconSize/iconStyle
		// === undefined here (no default in this schema), so it inherits the
		// theme token; explicit values are preserved as overrides.
		return attributes;
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
		// No attribute changes needed - only save function changed
		return attributes;
	},
};

export default [v2, v1];

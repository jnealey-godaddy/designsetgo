/**
 * Icon List Item Block - Deprecated Versions
 *
 * Handles backward compatibility for blocks saved with previous versions.
 *
 * @since 1.2.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { getIcon } from '../icon/utils/svg-icons';
import { convertPresetToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Shared supports definition for all deprecated versions.
 * Mirrors block.json supports.
 */
const sharedSupports = {
	html: false,
	inserter: true,
	reusable: false,
	spacing: {
		margin: false,
		padding: false,
	},
};

/**
 * Version 2: Before the theme icon-list default-size token
 *
 * Before this version, an icon-list-item ALWAYS baked an explicit inline
 * `width:Npx;height:Npx` (and matching minWidth) onto the icon wrapper,
 * reading the size from context with a hardcoded `|| 32` fallback. That
 * fallback meant items in a list whose parent left `iconSize` unset were
 * indistinguishable, at save time, from a list explicitly set to 32 — both
 * baked the literal pixel value.
 *
 * The current version keeps that behavior byte-identical for lists with an
 * EXPLICIT parent iconSize (context still yields the same number, so no
 * migration is needed there). It only changes output for the IMPLICIT case:
 * `iconSize` context is now `undefined` (the parent attribute has no
 * default), so the current save() omits the inline size and instead relies
 * on the `--dsgo-icon-list-size` custom property + the
 * `dsgo-icon-list-item__icon--inherit-size` class, letting the theme token
 * (settings.custom.designsetgo.iconList.defaultSize) resolve the box size
 * via CSS.
 *
 * This deprecation's `isEligible` matches the pre-token markup signature
 * (dsgo-lazy-icon + an inline width/height pixel pair, no inherit-size
 * class) so implicit-default items migrate silently instead of showing
 * "Attempt Recovery". The `save()` reproduces the old (always-explicit)
 * output verbatim using the same `|| 32` fallback so validation passes for
 * every existing block, explicit or implicit. `migrate()` is a passthrough:
 * explicit-size lists keep resolving to the same context number under the
 * current save(); implicit-default lists resolve context to `undefined` and
 * pick up the new inherited-token behavior going forward.
 */
const v2 = {
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, { innerHTML }) {
		return (
			innerHTML &&
			innerHTML.includes('dsgo-lazy-icon') &&
			/width:\s*\d+px\s*;\s*height:\s*\d+px/.test(innerHTML) &&
			!innerHTML.includes('dsgo-icon-list-item__icon--inherit-size')
		);
	},
	attributes: {
		icon: { type: 'string', default: 'star' },
		contentGap: { type: 'number', default: 8 },
		linkUrl: { type: 'string', default: '' },
		linkTarget: { type: 'string', default: '_self' },
		linkRel: { type: 'string', default: '' },
	},
	save({ attributes, context = {} }) {
		const { icon, linkUrl, linkTarget, linkRel, contentGap } = attributes;

		// Pre-token format always baked an explicit size via this fallback,
		// regardless of whether the parent had an explicit iconSize.
		const iconSize = context['designsetgo/iconList/iconSize'] || 32;
		const iconColor = context['designsetgo/iconList/iconColor'] || '';
		const iconBackgroundColor =
			context['designsetgo/iconList/iconBackgroundColor'] || '';
		const iconPosition =
			context['designsetgo/iconList/iconPosition'] || 'left';
		const iconVerticalAlignment =
			context['designsetgo/iconList/iconVerticalAlignment'] || 'top';

		const getTextAlign = () => {
			if (iconPosition === 'top') {
				return 'center';
			}
			if (iconPosition === 'right') {
				return 'right';
			}
			return 'left';
		};

		const getVerticalAlignItems = () => {
			if (iconPosition === 'top') {
				return 'center';
			}
			return iconVerticalAlignment === 'center' ? 'center' : 'flex-start';
		};

		const itemStyles = {
			display: 'flex',
			flexDirection: iconPosition === 'top' ? 'column' : 'row',
			alignItems: getVerticalAlignItems(),
			gap: iconPosition === 'top' ? '12px' : '16px',
			...(iconPosition === 'right' && { flexDirection: 'row-reverse' }),
		};

		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			...(iconBackgroundColor
				? {
						width: `${iconSize + 16}px`,
						height: `${iconSize + 16}px`,
						minWidth: `${iconSize + 16}px`,
						backgroundColor:
							convertPresetToCSSVar(iconBackgroundColor),
						padding: '8px',
						borderRadius: '4px',
						boxSizing: 'border-box',
					}
				: {
						width: `${iconSize}px`,
						height: `${iconSize}px`,
						minWidth: `${iconSize}px`,
					}),
			...(iconColor && {
				color: convertPresetToCSSVar(iconColor),
				'--dsgo-icon-color': convertPresetToCSSVar(iconColor),
			}),
		};

		const blockProps = useBlockProps.save({
			className: `dsgo-icon-list-item dsgo-icon-list-item--icon-${iconPosition}`,
			style: itemStyles,
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-icon-list-item__content',
			style: {
				textAlign: getTextAlign(),
				display: 'flex',
				flexDirection: 'column',
				gap: `${contentGap}px`,
			},
		});

		const ItemWrapper = linkUrl ? 'a' : 'div';
		const wrapperProps = linkUrl
			? {
					...blockProps,
					href: linkUrl,
					target: linkTarget,
					rel: linkRel || undefined,
				}
			: blockProps;

		return (
			<ItemWrapper {...wrapperProps}>
				<div
					className="dsgo-icon-list-item__icon dsgo-lazy-icon"
					style={iconWrapperStyles}
					data-icon-name={icon}
				/>

				<div {...innerBlocksProps} />
			</ItemWrapper>
		);
	},
	migrate(attributes) {
		// Passthrough. Explicit-size lists keep resolving the same context
		// number under the current save(); implicit-default lists now
		// resolve context to undefined and inherit the theme token.
		return attributes;
	},
};

/**
 * Version 1: Before lazy loading icon library
 *
 * Changes in current version:
 * - Icons now use data attributes for frontend lazy loading
 * - Frontend icons injected via PHP to avoid bundling 51KB library
 */
const v1 = {
	supports: sharedSupports,
	attributes: {
		icon: {
			type: 'string',
			default: 'star',
		},
		contentGap: {
			type: 'number',
			default: 8,
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
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v1 blocks have inline SVG icons instead of dsgo-lazy-icon class
		return innerHTML && !innerHTML.includes('dsgo-lazy-icon');
	},
	save({ attributes, context = {} }) {
		const { icon, linkUrl, linkTarget, linkRel, contentGap } = attributes;

		// Get settings from parent via context with safe defaults
		const iconSize = context['designsetgo/iconList/iconSize'] || 32;
		const iconColor = context['designsetgo/iconList/iconColor'] || '';
		const iconBackgroundColor =
			context['designsetgo/iconList/iconBackgroundColor'] || '';
		const iconPosition =
			context['designsetgo/iconList/iconPosition'] || 'left';

		// Calculate text alignment based on icon position
		const getTextAlign = () => {
			if (iconPosition === 'top') {
				return 'center';
			}
			if (iconPosition === 'right') {
				return 'right';
			}
			return 'left';
		};

		// Calculate item layout styles
		const itemStyles = {
			display: 'flex',
			flexDirection: iconPosition === 'top' ? 'column' : 'row',
			alignItems: iconPosition === 'top' ? 'center' : 'flex-start',
			gap: iconPosition === 'top' ? '12px' : '16px',
			...(iconPosition === 'right' && { flexDirection: 'row-reverse' }),
		};

		// Calculate icon wrapper styles
		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			...(iconBackgroundColor
				? {
						width: `${iconSize + 16}px`,
						height: `${iconSize + 16}px`,
						minWidth: `${iconSize + 16}px`,
						backgroundColor:
							convertPresetToCSSVar(iconBackgroundColor),
						padding: '8px',
						borderRadius: '4px',
						boxSizing: 'border-box',
					}
				: {
						width: `${iconSize}px`,
						height: `${iconSize}px`,
						minWidth: `${iconSize}px`,
					}),
			...(iconColor && {
				color: convertPresetToCSSVar(iconColor),
				'--dsgo-icon-color': convertPresetToCSSVar(iconColor),
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

		return (
			<ItemWrapper {...wrapperProps}>
				<div
					className="dsgo-icon-list-item__icon"
					style={iconWrapperStyles}
				>
					{getIcon(icon)}
				</div>

				<div {...innerBlocksProps} />
			</ItemWrapper>
		);
	},
	migrate(attributes) {
		// No attribute changes needed - only save function changed
		return attributes;
	},
};

export default [v2, v1];

/**
 * Icon List Item Block - Deprecated Versions
 *
 * Handles backward compatibility for blocks saved with previous versions.
 *
 * @since 1.2.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { getIcon } from '../icon/utils/svg-icons';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import { getOwnOpeningTag } from '../../utils/get-own-opening-tag';

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
 * Version 3: Before the kit-controllable gaps + icon-size tokens
 *
 * The pre-refactor format baked several design defaults as raw inline pixel
 * values in save():
 *  - the icon↔content gap on the item root (`gap:12px` stacked / `gap:16px`
 *    inline) — a position-derived default, never an author attribute;
 *  - the content gap on the inner content div (`gap:<contentGap>px`, defaulting
 *    to 8);
 *  - in the INHERITED icon-size case, `--dsgo-icon-list-size` set inline to the
 *    theme default token calc.
 *
 * The current version moves all three defaults into the stylesheet (keyed on
 * the existing position / inherit-size modifier classes, resolving through
 * layered `--dsgo-icon-list-*` / `--wp--custom--designsetgo--icon-list--*`
 * chains) so Style Kits and patterns can retheme them and patterns can omit
 * them and inherit. save() now emits the item gap and the inline
 * `--dsgo-icon-list-size` never, and the content gap only for an explicit
 * author value. The EXPLICIT icon-size case (inline width/height/minWidth) is
 * unchanged.
 *
 * `isEligible` matches the pre-refactor signature: a `gap:` written directly
 * after `align-items:` on the item root — the current save() no longer emits
 * that, and it is unique to the item wrapper (the content div's inline gap is
 * never preceded by align-items). `save()` reproduces the old output verbatim
 * (item gap inline, content gap inline at the default 8, inline
 * `--dsgo-icon-list-size` in the inherited case) so every existing block —
 * explicit or inherited size — validates. `migrate()` strips a default (8)
 * contentGap so it inherits the themeable default, preserving an explicit one.
 */
/**
 * Inline icon layout — the version before the icon box's constant layout
 * declarations moved to style.scss.
 *
 * The icon `<div>` serialized `display:flex; align-items:center;
 * justify-content:center` into every saved item. None of it varied by attribute
 * (size and colour, which DO vary, are still inline), so it now lives on
 * `.dsgo-icon-list-item__icon` in style.scss — an inline style beat any
 * stylesheet rule, so a Style Kit could never retheme the icon box.
 *
 * Markup-only change: same attributes, same classes — migrate() is a passthrough.
 *
 * NOTE: WordPress calls isEligible(attributes, innerBlocks, { blockNode, block })
 * — there is no `innerHTML` key on that third argument. Like v3 below, the check
 * is scoped to the icon element's OWN attributes rather than the whole subtree,
 * since the content area accepts arbitrary nested blocks whose inline styles
 * could otherwise false-match.
 */
const v4 = {
	supports: sharedSupports,
	attributes: {
		icon: { type: 'string', default: 'star' },
		contentGap: { type: 'number' },
		linkUrl: { type: 'string', default: '' },
		linkTarget: { type: 'string', default: '_self' },
		linkRel: { type: 'string', default: '' },
	},
	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const html = blockNode?.innerHTML ?? block?.originalContent ?? '';
		// Match the icon box's own style attribute carrying the layout trio.
		return /class="[^"]*dsgo-icon-list-item__icon[^"]*"\s+style="display:flex;align-items:center;justify-content:center/.test(
			html
		);
	},
	migrate(attributes) {
		// Markup-only change.
		return attributes;
	},
	save({ attributes, context = {} }) {
		const { icon, linkUrl, linkTarget, linkRel, contentGap } = attributes;

		const ctxIconSize = context['designsetgo/iconList/iconSize'];
		const hasExplicitSize =
			typeof ctxIconSize === 'number' && !Number.isNaN(ctxIconSize);
		const iconColor = context['designsetgo/iconList/iconColor'] || '';
		const iconBackgroundColor =
			context['designsetgo/iconList/iconBackgroundColor'] || '';
		const iconPosition =
			context['designsetgo/iconList/iconPosition'] || 'left';
		const iconVerticalAlignment =
			context['designsetgo/iconList/iconVerticalAlignment'] || 'top';
		const ctxIconStyle =
			context['designsetgo/iconList/iconStyle'] || undefined;
		const ctxStrokeWidth = context['designsetgo/iconList/strokeWidth'];

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
			...(iconPosition === 'right' && { flexDirection: 'row-reverse' }),
		};

		let sizeStyles;
		if (hasExplicitSize) {
			const explicitSize = iconBackgroundColor
				? ctxIconSize + 16
				: ctxIconSize;
			sizeStyles = {
				width: `${explicitSize}px`,
				height: `${explicitSize}px`,
				minWidth: `${explicitSize}px`,
			};
		} else {
			sizeStyles = {};
		}

		// The layout constants this deprecation exists to remove.
		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			...sizeStyles,
			...(iconBackgroundColor && {
				backgroundColor: convertColorToCSSVar(iconBackgroundColor),
				padding: '8px',
				borderRadius: '4px',
				boxSizing: 'border-box',
			}),
			...(iconColor && {
				color: convertColorToCSSVar(iconColor),
				'--dsgo-icon-color': convertColorToCSSVar(iconColor),
			}),
		};

		const blockProps = useBlockProps.save({
			className: `dsgo-icon-list-item dsgo-icon-list-item--icon-${iconPosition}`,
			style: itemStyles,
		});

		const hasExplicitContentGap =
			typeof contentGap === 'number' && !Number.isNaN(contentGap);
		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-icon-list-item__content',
			style: {
				textAlign: getTextAlign(),
				display: 'flex',
				flexDirection: 'column',
				...(hasExplicitContentGap && { gap: `${contentGap}px` }),
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

		const iconClassName = [
			'dsgo-icon-list-item__icon',
			'dsgo-lazy-icon',
			!hasExplicitSize && 'dsgo-icon-list-item__icon--inherit-size',
		]
			.filter(Boolean)
			.join(' ');

		return (
			<ItemWrapper {...wrapperProps}>
				<div
					className={iconClassName}
					style={iconWrapperStyles}
					data-icon-name={icon}
					data-icon-style={ctxIconStyle}
					data-icon-stroke-width={
						ctxIconStyle === 'outlined' ? ctxStrokeWidth : undefined
					}
				/>

				<div {...innerBlocksProps} />
			</ItemWrapper>
		);
	},
};

const v3 = {
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Old serialization baked the icon↔content gap on the item root right
		// after align-items; the current save() omits it. Scope the check to the
		// item wrapper's OWN opening tag rather than the whole subtree: the
		// content area accepts arbitrary nested blocks, and matching their inline
		// styles could false-migrate a valid current item if some nested block
		// ever emits align-items directly followed by gap.
		const openingTag = getOwnOpeningTag(innerHTML, 'dsgo-icon-list-item');
		if (!openingTag) {
			return false;
		}
		return /align-items:[^;"]+;gap:/.test(openingTag);
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

		const ctxIconSize = context['designsetgo/iconList/iconSize'];
		const hasExplicitSize = typeof ctxIconSize === 'number';
		const iconColor = context['designsetgo/iconList/iconColor'] || '';
		const iconBackgroundColor =
			context['designsetgo/iconList/iconBackgroundColor'] || '';
		const iconPosition =
			context['designsetgo/iconList/iconPosition'] || 'left';
		const iconVerticalAlignment =
			context['designsetgo/iconList/iconVerticalAlignment'] || 'top';
		const ctxIconStyle =
			context['designsetgo/iconList/iconStyle'] || undefined;
		const ctxStrokeWidth = context['designsetgo/iconList/strokeWidth'];

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

		// OLD: item gap always baked inline.
		const itemStyles = {
			display: 'flex',
			flexDirection: iconPosition === 'top' ? 'column' : 'row',
			alignItems: getVerticalAlignItems(),
			gap: iconPosition === 'top' ? '12px' : '16px',
			...(iconPosition === 'right' && { flexDirection: 'row-reverse' }),
		};

		// OLD: inherited size set --dsgo-icon-list-size inline; explicit size
		// baked width/height/minWidth.
		let sizeStyles;
		if (hasExplicitSize) {
			const explicitSize = iconBackgroundColor
				? ctxIconSize + 16
				: ctxIconSize;
			sizeStyles = {
				width: `${explicitSize}px`,
				height: `${explicitSize}px`,
				minWidth: `${explicitSize}px`,
			};
		} else {
			sizeStyles = {
				'--dsgo-icon-list-size':
					'calc(var(--wp--custom--designsetgo--icon-list--default-size, 32) * 1px)',
			};
		}

		const iconWrapperStyles = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			...sizeStyles,
			...(iconBackgroundColor && {
				backgroundColor: convertColorToCSSVar(iconBackgroundColor),
				padding: '8px',
				borderRadius: '4px',
				boxSizing: 'border-box',
			}),
			...(iconColor && {
				color: convertColorToCSSVar(iconColor),
				'--dsgo-icon-color': convertColorToCSSVar(iconColor),
			}),
		};

		const blockProps = useBlockProps.save({
			className: `dsgo-icon-list-item dsgo-icon-list-item--icon-${iconPosition}`,
			style: itemStyles,
		});

		// OLD: content gap always baked inline.
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

		const iconClassName = [
			'dsgo-icon-list-item__icon',
			'dsgo-lazy-icon',
			!hasExplicitSize && 'dsgo-icon-list-item__icon--inherit-size',
		]
			.filter(Boolean)
			.join(' ');

		return (
			<ItemWrapper {...wrapperProps}>
				<div
					className={iconClassName}
					style={iconWrapperStyles}
					data-icon-name={icon}
					data-icon-style={ctxIconStyle}
					data-icon-stroke-width={
						ctxIconStyle === 'outlined' ? ctxStrokeWidth : undefined
					}
				/>

				<div {...innerBlocksProps} />
			</ItemWrapper>
		);
	},
	migrate(attributes) {
		// Passthrough: pin whatever contentGap the old markup carried (an
		// implicit-default item re-parses to this schema's default of 8) so an
		// existing item renders exactly as authored. We do not strip a
		// default-valued gap back to "inherit" — matching image-accordion /
		// scroll-marquee / icon-button. New content inherits by omitting it.
		return attributes;
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

export { v4, v3, v2, v1 };

export default [v4, v3, v2, v1];

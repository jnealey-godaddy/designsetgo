/**
 * Modal Block - Deprecated Versions
 *
 * Handles backward compatibility for blocks saved with previous versions.
 *
 * @package
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { transferStylesToContent } from './utils/style-transfer';

/**
 * Full supports set v1 had. A deprecation that omits a support group loses
 * those attributes BEFORE migrate() runs, so this must stay complete.
 *
 * Note `anchor: true` is historical: v1 declared it, but the current
 * block.json disables it because core's anchor addSaveProps overwrites the
 * root `id` (which must stay equal to modalId for hash triggers) and the
 * sourced anchor attribute broke serialize→parse attribute round-trips.
 */
const v1Supports = {
	anchor: true,
	align: false,
	html: false,
	customClassName: true,
	className: true,
	spacing: {
		margin: false,
		padding: true,
		blockGap: true,
		__experimentalDefaultControls: {
			padding: true,
			blockGap: true,
		},
	},
	color: {
		background: true,
		text: true,
		link: true,
		gradients: true,
		__experimentalDefaultControls: {
			background: true,
			text: true,
		},
	},
	typography: {
		fontSize: true,
		lineHeight: true,
		__experimentalDefaultControls: {
			fontSize: true,
		},
		__experimentalFontFamily: true,
		__experimentalFontWeight: true,
		__experimentalFontStyle: true,
		__experimentalTextTransform: true,
		__experimentalTextDecoration: true,
		__experimentalLetterSpacing: true,
	},
	dimensions: {
		minHeight: true,
		__experimentalDefaultControls: {
			minHeight: false,
		},
	},
	shadow: true,
	interactivity: {
		clientNavigation: true,
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
};

/**
 * Version 1: Before the backdrop color became themeable (always baked inline)
 *
 * The pre-refactor format always wrote `background-color` inline on
 * `.dsgo-modal__backdrop`, filled from the block.json `overlayColor` default
 * (#000000) whenever the author left it unset. A pattern or Style Kit therefore
 * could not retheme the scrim — save() regenerated the baked black on the next
 * parse, and markup authored without the inline color failed validation.
 *
 * The current save() writes the backdrop color ONLY when the author set it
 * explicitly; left unset, the stylesheet default owns it
 * (--wp--custom--designsetgo--modal--overlay-color → #000).
 *
 * `save()` reproduces the always-baked markup exactly (overlayColor carries its
 * old #000000 default so an unset value re-parses to the baked color).
 * `migrate` drops an overlayColor still equal to the old default so the block
 * inherits the theme token; an explicitly customised color is preserved — a
 * color that differs from #000000 was serialized into the block comment and
 * passes through untouched. A deliberately-chosen black is byte-identical to
 * the implicit default in the old markup and is treated as inherit; that is
 * the same deliberate policy image-accordion's overlay migration documents.
 *
 * There is deliberately NO `isEligible`. Only old default-colored content needs
 * migrating, and that content is invalid against the current save() (which
 * omits the color it always baked), so it reaches this deprecation through the
 * normal save()-matching path. A block with an explicit overlayColor serializes
 * identically under both versions and never needs to migrate.
 */
const v1 = {
	// REQUIRED. WordPress omits apiVersion from the deprecated block type
	// (it is in DEPRECATED_ENTRY_KEYS), so without redeclaring it here the
	// deprecation's save() runs under apiVersion ≤ 1 semantics, produces
	// different markup, and never validates. Same trap pill/deprecated.js
	// documents.
	apiVersion: 3,

	supports: v1Supports,

	attributes: {
		style: {
			type: 'object',
			default: {
				border: {
					width: '0px',
					style: 'none',
				},
			},
		},
		modalId: {
			type: 'string',
			default: '',
		},
		modalLabel: {
			type: 'string',
			default: '',
		},
		allowHashTrigger: {
			type: 'boolean',
			default: true,
		},
		updateUrlOnOpen: {
			type: 'boolean',
			default: false,
		},
		autoTriggerType: {
			type: 'string',
			default: 'none',
		},
		autoTriggerDelay: {
			type: 'number',
			default: 0,
		},
		autoTriggerFrequency: {
			type: 'string',
			default: 'always',
		},
		cookieDuration: {
			type: 'number',
			default: 7,
		},
		exitIntentSensitivity: {
			type: 'string',
			default: 'medium',
		},
		exitIntentMinTime: {
			type: 'number',
			default: 5,
		},
		exitIntentExcludeMobile: {
			type: 'boolean',
			default: true,
		},
		scrollDepth: {
			type: 'number',
			default: 50,
		},
		scrollDirection: {
			type: 'string',
			default: 'down',
		},
		timeOnPage: {
			type: 'number',
			default: 30,
		},
		galleryGroupId: {
			type: 'string',
			default: '',
		},
		galleryIndex: {
			type: 'number',
			default: 0,
		},
		showGalleryNavigation: {
			type: 'boolean',
			default: true,
		},
		navigationStyle: {
			type: 'string',
			default: 'arrows',
		},
		navigationPosition: {
			type: 'string',
			default: 'sides',
		},
		width: {
			type: 'string',
			default: '600px',
		},
		maxWidth: {
			type: 'string',
			default: '90vw',
		},
		height: {
			type: 'string',
			default: 'auto',
		},
		maxHeight: {
			type: 'string',
			default: '90vh',
		},
		animationType: {
			type: 'string',
			default: 'fade',
		},
		animationDuration: {
			type: 'number',
			default: 300,
		},
		overlayOpacity: {
			type: 'number',
			default: 80,
		},
		overlayColor: {
			type: 'string',
			default: '#000000',
		},
		overlayBlur: {
			type: 'number',
			default: 0,
		},
		closeOnBackdrop: {
			type: 'boolean',
			default: true,
		},
		closeOnEsc: {
			type: 'boolean',
			default: true,
		},
		showCloseButton: {
			type: 'boolean',
			default: true,
		},
		closeButtonPosition: {
			type: 'string',
			default: 'inside-top-right',
		},
		closeButtonSize: {
			type: 'number',
			default: 24,
		},
		closeButtonLabel: {
			type: 'string',
			default: '',
		},
		closeButtonIconColor: {
			type: 'string',
			default: '',
		},
		closeButtonBgColor: {
			type: 'string',
			default: '',
		},
		disableBodyScroll: {
			type: 'boolean',
			default: true,
		},
	},

	save({ attributes }) {
		const {
			modalId,
			allowHashTrigger,
			updateUrlOnOpen,
			autoTriggerType,
			autoTriggerDelay,
			autoTriggerFrequency,
			cookieDuration,
			exitIntentSensitivity,
			exitIntentMinTime,
			exitIntentExcludeMobile,
			scrollDepth,
			scrollDirection,
			timeOnPage,
			galleryGroupId,
			galleryIndex,
			showGalleryNavigation,
			navigationStyle,
			navigationPosition,
			width,
			maxWidth,
			height,
			maxHeight,
			animationType,
			animationDuration,
			overlayOpacity,
			overlayColor,
			overlayBlur,
			closeOnBackdrop,
			closeOnEsc,
			showCloseButton,
			closeButtonPosition,
			closeButtonSize,
			closeButtonLabel,
			closeButtonIconColor,
			closeButtonBgColor,
			disableBodyScroll,
		} = attributes;

		const blockProps = useBlockProps.save({
			className: 'dsgo-modal',
			id: modalId,
			role: 'dialog',
			'aria-modal': 'true',
			// Use aria-label for accessibility; do not set aria-labelledby unless title element is guaranteed
			'aria-label':
				attributes.modalLabel?.trim() || __('Modal', 'designsetgo'),
			'aria-hidden': 'true',
			'data-dsgo-modal': 'true',
			'data-modal-id': modalId,
			'data-animation-type': animationType,
			'data-animation-duration': animationDuration,
			'data-close-on-backdrop': closeOnBackdrop,
			'data-close-on-esc': closeOnEsc,
			'data-disable-body-scroll': disableBodyScroll,
			'data-allow-hash-trigger': allowHashTrigger,
			'data-update-url-on-open': updateUrlOnOpen,
			'data-auto-trigger-type': autoTriggerType,
			'data-auto-trigger-delay': autoTriggerDelay,
			'data-auto-trigger-frequency': autoTriggerFrequency,
			'data-cookie-duration': cookieDuration,
			'data-exit-intent-sensitivity': exitIntentSensitivity,
			'data-exit-intent-min-time': exitIntentMinTime,
			'data-exit-intent-exclude-mobile': exitIntentExcludeMobile,
			'data-scroll-depth': scrollDepth,
			'data-scroll-direction': scrollDirection,
			'data-time-on-page': timeOnPage,
			'data-gallery-group-id': galleryGroupId,
			'data-gallery-index': galleryIndex,
			'data-show-gallery-navigation': showGalleryNavigation,
			'data-navigation-style': navigationStyle,
			'data-navigation-position': navigationPosition,
		});

		// OLD: the backdrop color was always baked inline from the attribute
		// default (#000000) even when the author never touched it.
		const overlayStyle = {
			backgroundColor: convertColorToCSSVar(overlayColor),
			opacity: overlayOpacity / 100,
			backdropFilter:
				overlayBlur > 0 ? `blur(${overlayBlur}px)` : undefined,
		};

		const closeButtonIsInside = closeButtonPosition.startsWith('inside-');

		const closeButton = showCloseButton ? (
			<button
				className={`dsgo-modal__close dsgo-modal__close--${closeButtonPosition}`}
				style={{
					width: `${closeButtonSize}px`,
					height: `${closeButtonSize}px`,
					color:
						convertColorToCSSVar(closeButtonIconColor) || undefined,
					backgroundColor:
						convertColorToCSSVar(closeButtonBgColor) || undefined,
				}}
				type="button"
				aria-label={
					closeButtonLabel?.trim() || __('Close modal', 'designsetgo')
				}
			>
				<svg
					width="100%"
					height="100%"
					viewBox="0 0 24 24"
					fill="none"
					xmlns="http://www.w3.org/2000/svg"
					aria-hidden="true"
				>
					<path
						d="M18 6L6 18M6 6L18 18"
						stroke="currentColor"
						strokeWidth="2"
						strokeLinecap="round"
						strokeLinejoin="round"
					/>
				</svg>
			</button>
		) : null;

		// Transfer block support styles from wrapper to content using shared utility
		const { contentStyle, wrapperProps, contentClasses } =
			transferStylesToContent(blockProps, {
				width,
				maxWidth,
				height,
				maxHeight,
			});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: ['dsgo-modal__content', ...contentClasses].join(' '),
			style: contentStyle,
		});

		return (
			<div {...wrapperProps}>
				<div
					className="dsgo-modal__backdrop"
					style={overlayStyle}
					aria-hidden="true"
				/>
				<div className="dsgo-modal__dialog">
					{!closeButtonIsInside && closeButton}
					<div {...innerBlocksProps}>
						{innerBlocksProps.children}
						{closeButtonIsInside && closeButton}
					</div>
				</div>
			</div>
		);
	},

	migrate(attributes) {
		// Drop the `anchor` this schema sources from the legacy wrapper's id
		// (v1Supports.anchor materializes it on every old block since the id
		// is always modalId) — anchor support is gone from the current
		// block.json, so the value is orphaned data. Also drop a
		// default-valued backdrop color so the block inherits the theme
		// token; an explicit non-default color and every other attribute
		// pass through untouched.
		const { anchor, ...rest } = attributes;
		if (rest.overlayColor === '#000000') {
			delete rest.overlayColor;
		}
		return rest;
	},
};

export default [v1];

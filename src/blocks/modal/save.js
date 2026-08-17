/**
 * Modal Block - Save Component
 *
 * @package
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { hasExplicitString } from '../../utils/has-explicit-value';
import { transferStylesToContent } from './utils/style-transfer';

export default function save({ attributes }) {
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
		displayMode,
		panelEdge,
		panelSize,
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

	// Off-canvas panel mode. Every branch below is gated on displayMode being
	// something other than its 'dialog' default, so a modal that predates this
	// feature emits character-identical markup and needs no deprecation.
	// tests/unit/modal-panel-save.test.js pins that.
	const isPanel = 'panel' === displayMode;

	const panelClasses = isPanel
		? ` dsgo-modal--panel dsgo-modal--panel-${panelEdge}`
		: '';

	// Kept on the wrapper rather than folded into blockProps.style, which
	// transferStylesToContent() relocates onto .dsgo-modal__content — a
	// descendant of the .dsgo-modal__dialog that consumes this property.
	const panelStyle = isPanel ? { '--dsgo-panel-size': panelSize } : undefined;

	const blockProps = useBlockProps.save({
		className: `dsgo-modal${panelClasses}`,
		// Omit a blank id: React serializes `id=""`, which the anchor support
		// (sourced from the id attribute) would re-parse as anchor: "". In
		// real content modalId is always seeded by useUniqueBlockId; the
		// blank-id case only exists pre-seed and is covered by deprecated v1.
		id: modalId || undefined,
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

	// Backdrop color is written inline ONLY when the author set it explicitly.
	// Left unset it is omitted so the stylesheet default owns it (resolving
	// through --wp--custom--designsetgo--modal--overlay-color → the literal
	// black fallback) and Style Kits / patterns can retheme the scrim without
	// fighting a baked-in default. MUST MATCH edit.js.
	const overlayStyle = {
		...(hasExplicitString(overlayColor) && {
			backgroundColor: convertColorToCSSVar(overlayColor),
		}),
		opacity: overlayOpacity / 100,
		backdropFilter: overlayBlur > 0 ? `blur(${overlayBlur}px)` : undefined,
	};

	const closeButtonIsInside = closeButtonPosition.startsWith('inside-');

	const closeButton = showCloseButton ? (
		<button
			className={`dsgo-modal__close dsgo-modal__close--${closeButtonPosition}`}
			style={{
				width: `${closeButtonSize}px`,
				height: `${closeButtonSize}px`,
				color: convertColorToCSSVar(closeButtonIconColor) || undefined,
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
		<div {...wrapperProps} style={panelStyle}>
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
}

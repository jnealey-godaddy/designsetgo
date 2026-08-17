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

/**
 * Edges a panel can anchor to.
 *
 * The inspector only ever writes one of these, but createBlock(), hand-edited
 * post content and third-party code can put anything in the attribute. An
 * unrecognised value would emit a dsgo-modal--panel-<value> class that matches
 * no rule in style.scss, leaving the dialog `position: fixed` with no offsets —
 * a small box floating mid-viewport, with no validation error to signal it.
 * Clamping here keeps that state unreachable from the markup.
 */
const PANEL_EDGES = ['left', 'right', 'top', 'bottom'];
const DEFAULT_PANEL_EDGE = 'right';

/**
 * A single, plain CSS length — the only thing the Panel Size UnitControl can
 * produce.
 *
 * panelSize is interpolated into the root's style attribute as
 * `--dsgo-panel-size:<value>`. React does not escape `;` there, so an
 * unvalidated value appends further declarations to the modal root:
 * `10px;position:fixed;inset:0;background:red` emits all four. Reaching that
 * needs post-edit capability and it only yields CSS, not script — but the same
 * rigor applied to panelEdge belongs here.
 *
 * Deliberately an ALLOW-list. A denylist of dangerous substrings is the pattern
 * that had to be patched three times on the interaction-layers PR.
 */
const CSS_LENGTH =
	/^(0|\d+(\.\d+)?(px|rem|em|%|vw|vh|vmin|vmax|ch|ex|pt|pc|cm|mm|in))$/;
const DEFAULT_PANEL_SIZE = '24rem';

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

	const safeEdge = PANEL_EDGES.includes(panelEdge)
		? panelEdge
		: DEFAULT_PANEL_EDGE;

	const panelClasses = isPanel
		? ` dsgo-modal--panel dsgo-modal--panel-${safeEdge}`
		: '';

	// Kept on the wrapper rather than folded into blockProps.style, which
	// transferStylesToContent() relocates onto .dsgo-modal__content — a
	// descendant of the .dsgo-modal__dialog that consumes this property.
	const safeSize = CSS_LENGTH.test(String(panelSize ?? ''))
		? panelSize
		: DEFAULT_PANEL_SIZE;

	const panelStyle = isPanel ? { '--dsgo-panel-size': safeSize } : undefined;

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

	// Transfer block support styles from wrapper to content using shared utility.
	//
	// A panel is sized by panelSize on the dialog, so the dialog-mode dimensions
	// must NOT be written inline onto the content. An inline `width: 600px`
	// outranks the stylesheet's `.dsgo-modal--panel .dsgo-modal__content
	// { width: 100% }`, and the content then only appears to fill the panel
	// because flex-shrink clamps it — which stops working the moment the panel
	// is wider than that inline width. Top and bottom panels span the viewport,
	// so they hit that case almost always. MUST MATCH edit.js.
	const { contentStyle, wrapperProps, contentClasses } =
		transferStylesToContent(
			blockProps,
			isPanel ? {} : { width, maxWidth, height, maxHeight }
		);

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

/**
 * Form Builder Block - Save Component
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { validateCSSLength } from '../../utils/css-generator';

// Non-default submitButtonVariation values from block.json. Allowlisted before
// interpolation into the class name. MUST MATCH edit.js.
const SUBMIT_BUTTON_VARIATIONS = ['secondary', 'outline'];

export default function FormBuilderSave({ attributes }) {
	const {
		formId,
		hasFields,
		submitButtonText,
		submitButtonAlignment,
		submitButtonPosition,
		submitButtonVariation,
		ajaxSubmit,
		successMessage,
		errorMessage,
		fieldSpacing,
		inputHeight,
		inputPadding,
		fieldLabelColor,
		fieldBorderColor,
		fieldBackgroundColor,
		fieldBorderRadius,
		submitButtonColor,
		submitButtonBackgroundColor,
		submitButtonPaddingVertical,
		submitButtonPaddingHorizontal,
		submitButtonFontSize,
		submitButtonHeight,
		submitButtonHoverColor,
		submitButtonHoverBackgroundColor,
		enableHoneypot,
		enableTurnstile,
		redirectUrl,
	} = attributes;

	// If the author never picked a template (so the form has no fields),
	// render nothing instead of a lonely submit button.
	if (!hasFields) {
		return null;
	}

	// Optional semantic style for the submit button (e.g. secondary/outline for
	// forms placed on alternate backgrounds). The class is only appended for a
	// non-default variation, so existing forms serialize byte-identically and
	// need no deprecation. Validated against the block.json enum (like the PHP
	// block-inserter) so a stray attribute value never reaches the class name.
	// Kits (and section/group style variations) paint the class; style.scss ships
	// a self-contained fallback. MUST MATCH edit.js.
	const submitVariationClass = SUBMIT_BUTTON_VARIATIONS.includes(
		submitButtonVariation
	)
		? ` dsgo-form__submit--${submitButtonVariation}`
		: '';

	// Same classes as edit.js - MUST MATCH
	const formClasses = classnames('dsgo-form-builder', {
		[`dsgo-form-builder--align-${submitButtonAlignment}`]:
			submitButtonAlignment && submitButtonPosition === 'below',
		'dsgo-form-builder--button-inline': submitButtonPosition === 'inline',
	});

	// Apply form settings as CSS custom properties - MUST MATCH edit.js.
	// Spacing/sizing tokens are only written when the author set an explicit
	// value; when omitted they fall back to the theme.json custom properties
	// (--wp--custom--designsetgo--form--*) defined in style.scss, so a pattern
	// can drop them to inherit the theme.
	const formStyles = {
		...(fieldSpacing && { '--dsgo-form-field-spacing': fieldSpacing }),
		...(inputHeight && { '--dsgo-form-input-height': inputHeight }),
		...(inputPadding && { '--dsgo-form-input-padding': inputPadding }),
		'--dsgo-form-label-color': convertColorToCSSVar(fieldLabelColor),
		'--dsgo-form-border-color': convertColorToCSSVar(fieldBorderColor),
		'--dsgo-form-field-bg': convertColorToCSSVar(fieldBackgroundColor),
		'--dsgo-form-border-radius': validateCSSLength(fieldBorderRadius),
		// Button colors now applied as inline styles on button element
	};

	// Submit button style - MUST MATCH edit.js. Sizing (height/padding/font) is
	// only written when explicitly set; otherwise the button inherits the
	// theme's global button styles via the wp-element-button class.
	const submitButtonStyle = {
		...(submitButtonColor && {
			color: convertColorToCSSVar(submitButtonColor),
		}),
		...(submitButtonBackgroundColor && {
			backgroundColor: convertColorToCSSVar(submitButtonBackgroundColor),
		}),
		...(submitButtonHeight && { minHeight: submitButtonHeight }),
		...(submitButtonPaddingVertical && {
			paddingTop: submitButtonPaddingVertical,
			paddingBottom: submitButtonPaddingVertical,
		}),
		...(submitButtonPaddingHorizontal && {
			paddingLeft: submitButtonPaddingHorizontal,
			paddingRight: submitButtonPaddingHorizontal,
		}),
		...(submitButtonFontSize && { fontSize: submitButtonFontSize }),
		...(submitButtonHoverBackgroundColor && {
			'--dsgo-button-hover-bg': convertColorToCSSVar(
				submitButtonHoverBackgroundColor
			),
		}),
		...(submitButtonHoverColor && {
			'--dsgo-button-hover-color': convertColorToCSSVar(
				submitButtonHoverColor
			),
		}),
	};

	const blockProps = useBlockProps.save({
		className: formClasses,
		style: formStyles,
		'data-form-id': formId,
		'data-ajax-submit': ajaxSubmit,
		'data-success-message': successMessage,
		'data-error-message': errorMessage,
		'data-submit-text': submitButtonText,
		...(enableTurnstile && {
			'data-dsgo-turnstile': 'true',
		}),
		...(redirectUrl && {
			'data-redirect-url': redirectUrl,
		}),
	});

	// Extract children from innerBlocksProps so we can add button inside fields container
	const { children, ...innerBlocksPropsWithoutChildren } =
		useInnerBlocksProps.save({
			className: 'dsgo-form__fields',
		});

	return (
		<div {...blockProps}>
			<form className="dsgo-form" method="post" noValidate>
				<div {...innerBlocksPropsWithoutChildren}>
					{children}
					{submitButtonPosition === 'inline' && (
						<button
							type="submit"
							className={`dsgo-form__submit dsgo-form__submit--inline${submitVariationClass} wp-element-button`}
							style={submitButtonStyle}
						>
							{submitButtonText}
						</button>
					)}
				</div>

				{enableHoneypot && (
					<input
						type="text"
						name="dsg_website"
						value=""
						tabIndex="-1"
						autoComplete="off"
						aria-hidden="true"
						style={{
							position: 'absolute',
							left: '-9999px',
							width: '1px',
							height: '1px',
							overflow: 'hidden',
						}}
					/>
				)}

				<input type="hidden" name="dsg_form_id" value={formId} />

				{/* Timestamp added via JavaScript in view.js to avoid validation errors */}

				{/* Turnstile widget container - rendered by JS */}
				{enableTurnstile && (
					<div
						className="dsgo-turnstile-widget"
						data-dsgo-turnstile-container="true"
					/>
				)}

				{submitButtonPosition === 'below' && (
					<div className="dsgo-form__footer">
						<button
							type="submit"
							className={`dsgo-form__submit${submitVariationClass} wp-element-button`}
							style={submitButtonStyle}
						>
							{submitButtonText}
						</button>
					</div>
				)}

				<div
					className="dsgo-form__message"
					role="status"
					aria-live="polite"
					aria-atomic="true"
					style={{ display: 'none' }}
				/>
			</form>
		</div>
	);
}

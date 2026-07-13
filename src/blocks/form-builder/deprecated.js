/**
 * Form Builder Block - Deprecations
 *
 * Handles migration from older save formats.
 *
 * @since 2.3.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import {
	convertColorToCSSVar,
	convertPresetToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import { validateCSSLength } from '../../utils/css-generator';
import metadata from './block.json';
import { getDeprecatedBlockHTML } from '../../utils/deprecated-block-html';

/**
 * V4 deprecation: Before the border-color fallback moved from inline style to CSS.
 *
 * Previously save() always forced a `--dsgo-form-border-color` custom property
 * onto the wrapper — falling back to the literal `#d1d5db` whenever
 * fieldBorderColor was empty. That made it impossible for hand-authored markup
 * (patterns) to omit the property and simply inherit the block's CSS default.
 * The fix drops the inline fallback; `.dsgo-form-builder` in style.scss now
 * supplies `#d1d5db` as the CSS default instead.
 */
// Attribute schema frozen at the spacing / sizing defaults that were baked into
// the wrapper + submit button UNCONDITIONALLY before those tokens became
// nullable and theme-inheritable. Every deprecation below parses implicit
// (unset) forms with these, so its save() reproduces the values actually
// present in the stored HTML — even though the live block.json now defaults them
// to '' (inherit).
const legacyAttributes = {
	...metadata.attributes,
	fieldSpacing: { type: 'string', default: '1.5rem' },
	inputHeight: { type: 'string', default: '44px' },
	inputPadding: { type: 'string', default: '0.75rem' },
	submitButtonHeight: { type: 'string', default: '44px' },
	submitButtonPaddingVertical: { type: 'string', default: '0.75rem' },
	submitButtonPaddingHorizontal: { type: 'string', default: '2rem' },
};

/**
 * V5 deprecation: before the spacing / sizing tokens became nullable
 * (removable) and the submit button inherited the theme's global button styles.
 *
 * The old save() wrote --dsgo-form-field-spacing / --dsgo-form-input-height /
 * --dsgo-form-input-padding on the wrapper and min-height / padding on the
 * button for every form, even at their defaults. The current save() omits them
 * at their defaults so the form inherits the theme. isEligible matches any form
 * that baked the input-height token (all pre-change forms did); migrate strips
 * values that equal the old defaults so they inherit, keeping real overrides.
 */
const v5 = {
	attributes: legacyAttributes,
	supports: metadata.supports,
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		return (
			Boolean(innerHTML) && innerHTML.includes('--dsgo-form-input-height')
		);
	},
	migrate(attributes) {
		const strip = (value, def) => (value === def ? '' : value);
		return {
			...attributes,
			fieldSpacing: strip(attributes.fieldSpacing, '1.5rem'),
			inputHeight: strip(attributes.inputHeight, '44px'),
			inputPadding: strip(attributes.inputPadding, '0.75rem'),
			submitButtonHeight: strip(attributes.submitButtonHeight, '44px'),
			submitButtonPaddingVertical: strip(
				attributes.submitButtonPaddingVertical,
				'0.75rem'
			),
			submitButtonPaddingHorizontal: strip(
				attributes.submitButtonPaddingHorizontal,
				'2rem'
			),
		};
	},
	save({ attributes }) {
		const {
			formId,
			hasFields,
			submitButtonText,
			submitButtonAlignment,
			submitButtonPosition,
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

		if (!hasFields) {
			return null;
		}

		const formClasses = classnames('dsgo-form-builder', {
			[`dsgo-form-builder--align-${submitButtonAlignment}`]:
				submitButtonAlignment && submitButtonPosition === 'below',
			'dsgo-form-builder--button-inline':
				submitButtonPosition === 'inline',
		});

		const formStyles = {
			'--dsgo-form-field-spacing': fieldSpacing,
			'--dsgo-form-input-height': inputHeight,
			'--dsgo-form-input-padding': inputPadding,
			'--dsgo-form-label-color': convertColorToCSSVar(fieldLabelColor),
			'--dsgo-form-border-color': convertColorToCSSVar(fieldBorderColor),
			'--dsgo-form-field-bg': convertColorToCSSVar(fieldBackgroundColor),
			'--dsgo-form-border-radius': validateCSSLength(fieldBorderRadius),
		};

		const submitButtonStyle = {
			...(submitButtonColor && {
				color: convertColorToCSSVar(submitButtonColor),
			}),
			...(submitButtonBackgroundColor && {
				backgroundColor: convertColorToCSSVar(
					submitButtonBackgroundColor
				),
			}),
			minHeight: submitButtonHeight,
			paddingTop: submitButtonPaddingVertical,
			paddingBottom: submitButtonPaddingVertical,
			paddingLeft: submitButtonPaddingHorizontal,
			paddingRight: submitButtonPaddingHorizontal,
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
								className="dsgo-form__submit dsgo-form__submit--inline wp-element-button"
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
								className="dsgo-form__submit wp-element-button"
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
	},
};

const v4 = {
	attributes: legacyAttributes,
	supports: metadata.supports,
	// No isEligible: markup-change deprecation, reached by save-matching on an
	// INVALID block (WordPress skips isEligible for those). The old guard,
	// `!attributes.fieldBorderColor`, was true of every CURRENT form that simply
	// never customised the border colour, so it claimed current content. Whether
	// the old forced-fallback markup is actually present is decided by matching
	// this version's save() against the stored HTML, which is the real test.
	migrate(attributes) {
		return attributes;
	},
	save({ attributes }) {
		const {
			formId,
			hasFields,
			submitButtonText,
			submitButtonAlignment,
			submitButtonPosition,
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

		if (!hasFields) {
			return null;
		}

		const formClasses = classnames('dsgo-form-builder', {
			[`dsgo-form-builder--align-${submitButtonAlignment}`]:
				submitButtonAlignment && submitButtonPosition === 'below',
			'dsgo-form-builder--button-inline':
				submitButtonPosition === 'inline',
		});

		const formStyles = {
			'--dsgo-form-field-spacing': fieldSpacing,
			'--dsgo-form-input-height': inputHeight,
			'--dsgo-form-input-padding': inputPadding,
			'--dsgo-form-label-color': convertColorToCSSVar(fieldLabelColor),
			'--dsgo-form-border-color':
				convertColorToCSSVar(fieldBorderColor) || '#d1d5db',
			'--dsgo-form-field-bg': convertColorToCSSVar(fieldBackgroundColor),
			'--dsgo-form-border-radius': validateCSSLength(fieldBorderRadius),
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
								className="dsgo-form__submit dsgo-form__submit--inline wp-element-button"
								style={{
									...(submitButtonColor && {
										color: convertColorToCSSVar(
											submitButtonColor
										),
									}),
									...(submitButtonBackgroundColor && {
										backgroundColor: convertColorToCSSVar(
											submitButtonBackgroundColor
										),
									}),
									minHeight: submitButtonHeight,
									paddingTop: submitButtonPaddingVertical,
									paddingBottom: submitButtonPaddingVertical,
									paddingLeft: submitButtonPaddingHorizontal,
									paddingRight: submitButtonPaddingHorizontal,
									...(submitButtonFontSize && {
										fontSize: submitButtonFontSize,
									}),
									...(submitButtonHoverBackgroundColor && {
										'--dsgo-button-hover-bg':
											convertColorToCSSVar(
												submitButtonHoverBackgroundColor
											),
									}),
									...(submitButtonHoverColor && {
										'--dsgo-button-hover-color':
											convertColorToCSSVar(
												submitButtonHoverColor
											),
									}),
								}}
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
								className="dsgo-form__submit wp-element-button"
								style={{
									...(submitButtonColor && {
										color: convertColorToCSSVar(
											submitButtonColor
										),
									}),
									...(submitButtonBackgroundColor && {
										backgroundColor: convertColorToCSSVar(
											submitButtonBackgroundColor
										),
									}),
									minHeight: submitButtonHeight,
									paddingTop: submitButtonPaddingVertical,
									paddingBottom: submitButtonPaddingVertical,
									paddingLeft: submitButtonPaddingHorizontal,
									paddingRight: submitButtonPaddingHorizontal,
									...(submitButtonFontSize && {
										fontSize: submitButtonFontSize,
									}),
									...(submitButtonHoverBackgroundColor && {
										'--dsgo-button-hover-bg':
											convertColorToCSSVar(
												submitButtonHoverBackgroundColor
											),
									}),
									...(submitButtonHoverColor && {
										'--dsgo-button-hover-color':
											convertColorToCSSVar(
												submitButtonHoverColor
											),
									}),
								}}
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
	},
};

/**
 * V3 deprecation: Before convertColorToCSSVar was applied to form style properties.
 *
 * Raw empty-string attribute values were passed directly into inline CSS custom
 * properties, producing `--dsgo-form-label-color:;` and `--dsgo-form-field-bg:`
 * in the serialised markup. After the fix, convertColorToCSSVar/convertPresetToCSSVar
 * returns undefined for empty strings, so React omits those properties entirely.
 *
 * This version also predates: fieldBorderRadius / --dsgo-form-border-radius,
 * redirectUrl / data-redirect-url, submitButtonHoverColor /
 * submitButtonHoverBackgroundColor on the button, and the hasFields early-return.
 */
const v3 = {
	attributes: legacyAttributes,
	supports: metadata.supports,
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		// v3 blocks have raw empty CSS vars rendered as `--dsgo-form-label-color:;`
		return innerHTML && innerHTML.includes('--dsgo-form-label-color:;');
	},
	migrate(attributes) {
		return attributes;
	},
	save({ attributes }) {
		const {
			formId,
			submitButtonText,
			submitButtonAlignment,
			submitButtonPosition,
			ajaxSubmit,
			successMessage,
			errorMessage,
			fieldSpacing,
			inputHeight,
			inputPadding,
			fieldLabelColor,
			fieldBorderColor,
			fieldBackgroundColor,
			submitButtonColor,
			submitButtonBackgroundColor,
			submitButtonPaddingVertical,
			submitButtonPaddingHorizontal,
			submitButtonFontSize,
			submitButtonHeight,
			enableHoneypot,
			enableTurnstile,
		} = attributes;

		const formClasses = classnames('dsgo-form-builder', {
			[`dsgo-form-builder--align-${submitButtonAlignment}`]:
				submitButtonAlignment && submitButtonPosition === 'below',
			'dsgo-form-builder--button-inline':
				submitButtonPosition === 'inline',
		});

		// Raw values — no convertColorToCSSVar; empty strings render as empty CSS vars.
		const formStyles = {
			'--dsgo-form-field-spacing': fieldSpacing,
			'--dsgo-form-input-height': inputHeight,
			'--dsgo-form-input-padding': inputPadding,
			'--dsgo-form-label-color': fieldLabelColor,
			'--dsgo-form-border-color': fieldBorderColor || '#d1d5db',
			'--dsgo-form-field-bg': fieldBackgroundColor,
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
		});

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
								className="dsgo-form__submit dsgo-form__submit--inline wp-element-button"
								style={{
									...(submitButtonColor && {
										color: submitButtonColor,
									}),
									...(submitButtonBackgroundColor && {
										backgroundColor:
											submitButtonBackgroundColor,
									}),
									minHeight: submitButtonHeight,
									paddingTop: submitButtonPaddingVertical,
									paddingBottom: submitButtonPaddingVertical,
									paddingLeft: submitButtonPaddingHorizontal,
									paddingRight: submitButtonPaddingHorizontal,
									...(submitButtonFontSize && {
										fontSize: submitButtonFontSize,
									}),
								}}
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
								className="dsgo-form__submit wp-element-button"
								style={{
									...(submitButtonColor && {
										color: submitButtonColor,
									}),
									...(submitButtonBackgroundColor && {
										backgroundColor:
											submitButtonBackgroundColor,
									}),
									minHeight: submitButtonHeight,
									paddingTop: submitButtonPaddingVertical,
									paddingBottom: submitButtonPaddingVertical,
									paddingLeft: submitButtonPaddingHorizontal,
									paddingRight: submitButtonPaddingHorizontal,
									...(submitButtonFontSize && {
										fontSize: submitButtonFontSize,
									}),
								}}
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
	},
};

/**
 * V2 deprecation: Before aria-hidden and aria-atomic were added.
 *
 * The honeypot input lacked aria-hidden="true" and the message div lacked
 * aria-atomic="true". These were added for accessibility improvements but
 * content generated by the site-designer-api predates these additions.
 */
const v2 = {
	attributes: legacyAttributes,
	supports: metadata.supports,
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		// v2 blocks lack aria-hidden on honeypot and aria-atomic on message div
		return (
			innerHTML &&
			((innerHTML.includes('dsg_website') &&
				!innerHTML.includes('aria-hidden')) ||
				(innerHTML.includes('dsgo-form__message') &&
					!innerHTML.includes('aria-atomic')))
		);
	},
	migrate(attributes) {
		return attributes;
	},
	save({ attributes }) {
		const {
			formId,
			submitButtonText,
			submitButtonAlignment,
			submitButtonPosition,
			ajaxSubmit,
			successMessage,
			errorMessage,
			fieldSpacing,
			inputHeight,
			inputPadding,
			fieldLabelColor,
			fieldBorderColor,
			fieldBackgroundColor,
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
		} = attributes;

		const formClasses = classnames('dsgo-form-builder', {
			[`dsgo-form-builder--align-${submitButtonAlignment}`]:
				submitButtonAlignment && submitButtonPosition === 'below',
			'dsgo-form-builder--button-inline':
				submitButtonPosition === 'inline',
		});

		const formStyles = {
			'--dsgo-form-field-spacing': fieldSpacing,
			'--dsgo-form-input-height': inputHeight,
			'--dsgo-form-input-padding': inputPadding,
			'--dsgo-form-label-color': convertPresetToCSSVar(fieldLabelColor),
			'--dsgo-form-border-color':
				convertPresetToCSSVar(fieldBorderColor) || '#d1d5db',
			'--dsgo-form-field-bg': convertPresetToCSSVar(fieldBackgroundColor),
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
		});

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
								className="dsgo-form__submit dsgo-form__submit--inline wp-element-button"
								style={{
									...(submitButtonColor && {
										color: convertPresetToCSSVar(
											submitButtonColor
										),
									}),
									...(submitButtonBackgroundColor && {
										backgroundColor: convertPresetToCSSVar(
											submitButtonBackgroundColor
										),
									}),
									minHeight: submitButtonHeight,
									paddingTop: submitButtonPaddingVertical,
									paddingBottom: submitButtonPaddingVertical,
									paddingLeft: submitButtonPaddingHorizontal,
									paddingRight: submitButtonPaddingHorizontal,
									...(submitButtonFontSize && {
										fontSize: submitButtonFontSize,
									}),
									...(submitButtonHoverBackgroundColor && {
										'--dsgo-button-hover-bg':
											convertPresetToCSSVar(
												submitButtonHoverBackgroundColor
											),
									}),
									...(submitButtonHoverColor && {
										'--dsgo-button-hover-color':
											convertPresetToCSSVar(
												submitButtonHoverColor
											),
									}),
								}}
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
								className="dsgo-form__submit wp-element-button"
								style={{
									...(submitButtonColor && {
										color: convertPresetToCSSVar(
											submitButtonColor
										),
									}),
									...(submitButtonBackgroundColor && {
										backgroundColor: convertPresetToCSSVar(
											submitButtonBackgroundColor
										),
									}),
									minHeight: submitButtonHeight,
									paddingTop: submitButtonPaddingVertical,
									paddingBottom: submitButtonPaddingVertical,
									paddingLeft: submitButtonPaddingHorizontal,
									paddingRight: submitButtonPaddingHorizontal,
									...(submitButtonFontSize && {
										fontSize: submitButtonFontSize,
									}),
									...(submitButtonHoverBackgroundColor && {
										'--dsgo-button-hover-bg':
											convertPresetToCSSVar(
												submitButtonHoverBackgroundColor
											),
									}),
									...(submitButtonHoverColor && {
										'--dsgo-button-hover-color':
											convertPresetToCSSVar(
												submitButtonHoverColor
											),
									}),
								}}
							>
								{submitButtonText}
							</button>
						</div>
					)}

					<div
						className="dsgo-form__message"
						role="status"
						aria-live="polite"
						style={{ display: 'none' }}
					/>
				</form>
			</div>
		);
	},
};

/**
 * V1 deprecation: Email config was exposed as data attributes.
 *
 * Email settings (data-enable-email, data-email-to, data-email-subject, etc.)
 * were rendered as HTML data attributes on the block wrapper. This was a
 * deliverability and security concern because:
 * - Email configuration was visible in page source
 * - Client-side JavaScript sent these values in the REST API request
 * - An attacker could modify the request to send email to arbitrary addresses
 *
 * The fix moves email configuration to server-side-only: the PHP handler looks
 * up block attributes directly from post content when processing submissions.
 */
const v1 = {
	attributes: legacyAttributes,
	supports: metadata.supports,
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		// v1 blocks have email config exposed as data attributes
		return innerHTML && innerHTML.includes('data-enable-email');
	},
	migrate(attributes) {
		return attributes;
	},
	save({ attributes }) {
		const {
			formId,
			submitButtonText,
			submitButtonAlignment,
			submitButtonPosition,
			ajaxSubmit,
			successMessage,
			errorMessage,
			fieldSpacing,
			inputHeight,
			inputPadding,
			fieldLabelColor,
			fieldBorderColor,
			fieldBackgroundColor,
			submitButtonColor,
			submitButtonBackgroundColor,
			submitButtonPaddingVertical,
			submitButtonPaddingHorizontal,
			submitButtonFontSize,
			submitButtonHeight,
			enableHoneypot,
			enableTurnstile,
			enableEmail,
			emailTo,
			emailSubject,
			emailFromName,
			emailFromEmail,
			emailReplyTo,
			emailBody,
		} = attributes;

		const formClasses = classnames('dsgo-form-builder', {
			[`dsgo-form-builder--align-${submitButtonAlignment}`]:
				submitButtonAlignment && submitButtonPosition === 'below',
			'dsgo-form-builder--button-inline':
				submitButtonPosition === 'inline',
		});

		const formStyles = {
			'--dsgo-form-field-spacing': fieldSpacing,
			'--dsgo-form-input-height': inputHeight,
			'--dsgo-form-input-padding': inputPadding,
			'--dsgo-form-label-color': convertPresetToCSSVar(fieldLabelColor),
			'--dsgo-form-border-color':
				convertPresetToCSSVar(fieldBorderColor) || '#d1d5db',
			'--dsgo-form-field-bg': convertPresetToCSSVar(fieldBackgroundColor),
		};

		const blockProps = useBlockProps.save({
			className: formClasses,
			style: formStyles,
			'data-form-id': formId,
			'data-ajax-submit': ajaxSubmit,
			'data-success-message': successMessage,
			'data-error-message': errorMessage,
			'data-submit-text': submitButtonText,
			'data-enable-email': enableEmail,
			'data-email-to': emailTo,
			'data-email-subject': emailSubject,
			'data-email-from-name': emailFromName,
			'data-email-from-email': emailFromEmail,
			'data-email-reply-to': emailReplyTo,
			'data-email-body': emailBody,
			...(enableTurnstile && {
				'data-dsgo-turnstile': 'true',
			}),
		});

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
								className="dsgo-form__submit dsgo-form__submit--inline wp-element-button"
								style={{
									...(submitButtonColor && {
										color: convertPresetToCSSVar(
											submitButtonColor
										),
									}),
									...(submitButtonBackgroundColor && {
										backgroundColor: convertPresetToCSSVar(
											submitButtonBackgroundColor
										),
									}),
									minHeight: submitButtonHeight,
									paddingTop: submitButtonPaddingVertical,
									paddingBottom: submitButtonPaddingVertical,
									paddingLeft: submitButtonPaddingHorizontal,
									paddingRight: submitButtonPaddingHorizontal,
									...(submitButtonFontSize && {
										fontSize: submitButtonFontSize,
									}),
								}}
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
								className="dsgo-form__submit wp-element-button"
								style={{
									...(submitButtonColor && {
										color: convertPresetToCSSVar(
											submitButtonColor
										),
									}),
									...(submitButtonBackgroundColor && {
										backgroundColor: convertPresetToCSSVar(
											submitButtonBackgroundColor
										),
									}),
									minHeight: submitButtonHeight,
									paddingTop: submitButtonPaddingVertical,
									paddingBottom: submitButtonPaddingVertical,
									paddingLeft: submitButtonPaddingHorizontal,
									paddingRight: submitButtonPaddingHorizontal,
									...(submitButtonFontSize && {
										fontSize: submitButtonFontSize,
									}),
								}}
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
	},
};

const deprecated = [v5, v4, v3, v2, v1];

export default deprecated;

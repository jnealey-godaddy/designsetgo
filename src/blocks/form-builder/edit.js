/**
 * Form Builder Block - Editor Component
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	useSettings,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	RangeControl,
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import classnames from 'classnames';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { validateCSSLength } from '../../utils/css-generator';

export default function FormBuilderEdit({
	attributes,
	setAttributes,
	clientId,
}) {
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
		enableRateLimit,
		rateLimitCount,
		rateLimitWindow,
		enableTurnstile,
		enableEmail,
		emailTo,
		emailSubject,
		emailFromName,
		emailFromEmail,
		emailReplyTo,
		emailBody,
		redirectUrl,
	} = attributes;

	// Get theme color palette and gradient settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Read the site-wide default hover animation from theme.json custom settings.
	const [themeDefaultHover] = useSettings(
		'custom.designsetgo.defaultIconButtonHover'
	);

	// Resolve the effective animation for editor preview.
	// Form submit always uses the site-wide default — no per-block override.
	// Validate against allowlist to match backend (class-plugin.php ALLOWED_HOVER_ANIMATIONS).
	const ALLOWED_ANIMATIONS = [
		'fill-diagonal',
		'zoom-in',
		'slide-left',
		'slide-right',
		'slide-down',
		'slide-up',
		'border-pulse',
		'border-glow',
		'lift',
		'shrink',
	];
	const adminDefault = themeDefaultHover || 'none';
	const isValidAnimation = ALLOWED_ANIMATIONS.includes(adminDefault);
	const effectiveAnimation =
		isValidAnimation && adminDefault !== 'none' ? adminDefault : null;

	// Build animation class for the submit button
	const submitAnimationClass =
		effectiveAnimation && effectiveAnimation !== 'none'
			? ` dsgo-form__submit--${effectiveAnimation}`
			: '';

	// Generate unique formId on mount
	useEffect(() => {
		if (!formId) {
			setAttributes({ formId: clientId.substring(0, 8) });
		}
	}, [formId, clientId, setAttributes]);

	// Calculate classes
	const formClasses = classnames('dsgo-form-builder', {
		[`dsgo-form-builder--align-${submitButtonAlignment}`]:
			submitButtonAlignment && submitButtonPosition === 'below',
		'dsgo-form-builder--button-inline': submitButtonPosition === 'inline',
	});

	// Apply form settings as CSS custom properties
	const formStyles = {
		'--dsgo-form-field-spacing': fieldSpacing,
		'--dsgo-form-input-height': inputHeight,
		'--dsgo-form-input-padding': inputPadding,
		'--dsgo-form-label-color': convertColorToCSSVar(fieldLabelColor),
		'--dsgo-form-border-color':
			convertColorToCSSVar(fieldBorderColor) || '#d1d5db',
		'--dsgo-form-field-bg': convertColorToCSSVar(fieldBackgroundColor),
		'--dsgo-form-border-radius': validateCSSLength(fieldBorderRadius),
		// Button colors now applied as inline styles on button element
	};

	const blockProps = useBlockProps({
		className: formClasses,
		style: formStyles,
		'data-form-id': formId,
	});

	// Inner blocks for form fields
	// Extract children so we can add button inside fields container
	const { children, ...innerBlocksPropsWithoutChildren } =
		useInnerBlocksProps(
			{
				className: 'dsgo-form__fields',
			},
			{
				allowedBlocks: [
					'designsetgo/form-text-field',
					'designsetgo/form-email-field',
					'designsetgo/form-textarea-field',
					'designsetgo/form-number-field',
					'designsetgo/form-phone-field',
					'designsetgo/form-url-field',
					'designsetgo/form-date-field',
					'designsetgo/form-time-field',
					'designsetgo/form-select-field',
					'designsetgo/form-checkbox-field',
					'designsetgo/form-hidden-field',
				],
				template: [
					[
						'designsetgo/form-text-field',
						{
							label: __('Name', 'designsetgo'),
							fieldName: 'name',
							required: true,
						},
					],
					[
						'designsetgo/form-email-field',
						{
							label: __('Email', 'designsetgo'),
							fieldName: 'email',
							required: true,
						},
					],
					[
						'designsetgo/form-textarea-field',
						{
							label: __('Message', 'designsetgo'),
							fieldName: 'message',
						},
					],
				],
				orientation: 'vertical',
			}
		);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Form Settings', 'designsetgo')}
					initialOpen={true}
				>
					<ToggleControl
						label={__('AJAX Submit', 'designsetgo')}
						checked={ajaxSubmit}
						onChange={(value) =>
							setAttributes({ ajaxSubmit: value })
						}
						help={__(
							'Submit form without page reload',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>

					<TextControl
						label={__('Submit Button Text', 'designsetgo')}
						value={submitButtonText}
						onChange={(value) =>
							setAttributes({ submitButtonText: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Button Position', 'designsetgo')}
						value={submitButtonPosition}
						options={[
							{
								label: __('Below fields', 'designsetgo'),
								value: 'below',
							},
							{
								label: __(
									'Inline with last field',
									'designsetgo'
								),
								value: 'inline',
							},
						]}
						onChange={(value) =>
							setAttributes({ submitButtonPosition: value })
						}
						help={__(
							'Place button below all fields or inline with the last field (useful for subscribe forms)',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					{submitButtonPosition === 'below' && (
						<SelectControl
							label={__('Button Alignment', 'designsetgo')}
							value={submitButtonAlignment}
							options={[
								{
									label: __('Left', 'designsetgo'),
									value: 'left',
								},
								{
									label: __('Center', 'designsetgo'),
									value: 'center',
								},
								{
									label: __('Right', 'designsetgo'),
									value: 'right',
								},
							]}
							onChange={(value) =>
								setAttributes({ submitButtonAlignment: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					)}
				</PanelBody>

				<PanelBody
					title={__('Button Styling', 'designsetgo')}
					initialOpen={false}
				>
					<UnitControl
						label={__('Button Height', 'designsetgo')}
						value={submitButtonHeight}
						onChange={(value) =>
							setAttributes({
								submitButtonHeight: value || '44px',
							})
						}
						units={[
							{ value: 'px', label: 'px', default: 44 },
							{ value: 'rem', label: 'rem', default: 2.75 },
							{ value: 'em', label: 'em', default: 2.75 },
						]}
						min={28}
						max={200}
						help={__(
							'Minimum height for submit button',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<UnitControl
						label={__('Button Padding (Vertical)', 'designsetgo')}
						value={submitButtonPaddingVertical}
						onChange={(value) =>
							setAttributes({
								submitButtonPaddingVertical: value || '0.75rem',
							})
						}
						units={[
							{ value: 'px', label: 'px', default: 12 },
							{ value: 'rem', label: 'rem', default: 0.75 },
							{ value: 'em', label: 'em', default: 0.75 },
						]}
						min={0}
						max={50}
						help={__(
							'Top and bottom padding for button',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<UnitControl
						label={__('Button Padding (Horizontal)', 'designsetgo')}
						value={submitButtonPaddingHorizontal}
						onChange={(value) =>
							setAttributes({
								submitButtonPaddingHorizontal: value || '2rem',
							})
						}
						units={[
							{ value: 'px', label: 'px', default: 32 },
							{ value: 'rem', label: 'rem', default: 2 },
							{ value: 'em', label: 'em', default: 2 },
						]}
						min={0}
						max={100}
						help={__(
							'Left and right padding for button',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<UnitControl
						label={__('Button Font Size', 'designsetgo')}
						value={submitButtonFontSize}
						onChange={(value) =>
							setAttributes({ submitButtonFontSize: value || '' })
						}
						units={[
							{ value: 'px', label: 'px', default: 16 },
							{ value: 'rem', label: 'rem', default: 1 },
							{ value: 'em', label: 'em', default: 1 },
						]}
						min={10}
						max={100}
						help={__(
							'Font size for button text (leave empty to inherit)',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={__('Field Styling', 'designsetgo')}
					initialOpen={false}
				>
					<UnitControl
						label={__('Field Spacing', 'designsetgo')}
						value={fieldSpacing}
						onChange={(value) =>
							setAttributes({ fieldSpacing: value || '1.5rem' })
						}
						units={[
							{ value: 'px', label: 'px', default: 24 },
							{ value: 'rem', label: 'rem', default: 1.5 },
							{ value: 'em', label: 'em', default: 1.5 },
						]}
						min={0}
						max={100}
						help={__('Space between form fields', 'designsetgo')}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<UnitControl
						label={__('Input Height', 'designsetgo')}
						value={inputHeight}
						onChange={(value) =>
							setAttributes({ inputHeight: value || '44px' })
						}
						units={[
							{ value: 'px', label: 'px', default: 44 },
							{ value: 'rem', label: 'rem', default: 2.75 },
							{ value: 'em', label: 'em', default: 2.75 },
						]}
						min={28}
						max={200}
						help={__(
							'Minimum height for input fields',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<UnitControl
						label={__('Input Padding', 'designsetgo')}
						value={inputPadding}
						onChange={(value) =>
							setAttributes({ inputPadding: value || '0.75rem' })
						}
						units={[
							{ value: 'px', label: 'px', default: 12 },
							{ value: 'rem', label: 'rem', default: 0.75 },
							{ value: 'em', label: 'em', default: 0.75 },
						]}
						min={0}
						max={50}
						help={__('Padding inside input fields', 'designsetgo')}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<UnitControl
						label={__('Border Radius', 'designsetgo')}
						value={fieldBorderRadius}
						onChange={(value) =>
							setAttributes({
								fieldBorderRadius: value || '',
							})
						}
						units={[
							{ value: 'px', label: 'px', default: 6 },
							{ value: 'rem', label: 'rem', default: 0.375 },
							{ value: 'em', label: 'em', default: 0.375 },
						]}
						min={0}
						max={100}
						help={__(
							'Border radius for input fields',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={__('Messages', 'designsetgo')}
					initialOpen={false}
				>
					<TextareaControl
						label={__('Success Message', 'designsetgo')}
						value={successMessage}
						onChange={(value) =>
							setAttributes({ successMessage: value })
						}
						help={__(
							'Message shown after successful submission',
							'designsetgo'
						)}
						rows={3}
						__nextHasNoMarginBottom
					/>

					<TextareaControl
						label={__('Error Message', 'designsetgo')}
						value={errorMessage}
						onChange={(value) =>
							setAttributes({ errorMessage: value })
						}
						help={__(
							'Message shown if submission fails',
							'designsetgo'
						)}
						rows={3}
						__nextHasNoMarginBottom
					/>

					<TextControl
						label={__('Redirect URL', 'designsetgo')}
						value={redirectUrl}
						onChange={(value) =>
							setAttributes({ redirectUrl: value })
						}
						type="url"
						placeholder="https://example.com/thank-you"
						help={__(
							'Redirect to this URL after successful submission. Leave empty to show the success message instead. Requires AJAX Submit to be enabled.',
							'designsetgo'
						)}
						disabled={!ajaxSubmit}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={__('Spam Protection', 'designsetgo')}
					initialOpen={false}
				>
					<ToggleControl
						label={__('Enable Honeypot', 'designsetgo')}
						checked={enableHoneypot}
						onChange={(value) =>
							setAttributes({ enableHoneypot: value })
						}
						help={__(
							'Invisible field to catch spam bots',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>

					<ToggleControl
						label={__('Enable Rate Limiting', 'designsetgo')}
						checked={enableRateLimit}
						onChange={(value) =>
							setAttributes({ enableRateLimit: value })
						}
						help={__(
							'Limit submissions per IP address',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>

					{enableRateLimit && (
						<>
							<RangeControl
								label={__('Max Submissions', 'designsetgo')}
								value={rateLimitCount}
								onChange={(value) =>
									setAttributes({ rateLimitCount: value })
								}
								min={1}
								max={10}
								help={__(
									'Maximum submissions allowed per time window',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<RangeControl
								label={__(
									'Time Window (seconds)',
									'designsetgo'
								)}
								value={rateLimitWindow}
								onChange={(value) =>
									setAttributes({ rateLimitWindow: value })
								}
								min={30}
								max={300}
								step={30}
								help={__(
									'Time period for rate limiting',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</>
					)}

					<ToggleControl
						label={__('Enable Cloudflare Turnstile', 'designsetgo')}
						checked={enableTurnstile}
						onChange={(value) =>
							setAttributes({ enableTurnstile: value })
						}
						help={__(
							'Privacy-friendly CAPTCHA alternative',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>

					{enableTurnstile && (
						<p className="dsgo-form-builder__turnstile-note">
							{__(
								'Configure your Turnstile keys in',
								'designsetgo'
							)}{' '}
							<a
								href={
									window.designSetGoAdmin?.adminUrl +
									'admin.php?page=designsetgo-settings'
								}
								target="_blank"
								rel="noopener noreferrer"
								aria-label={__(
									'Settings → Integrations (opens in new tab)',
									'designsetgo'
								)}
							>
								{__('Settings → Integrations', 'designsetgo')}
							</a>
							.{' '}
							{__(
								'Widget mode (Managed, Non-interactive, Invisible) is configured in your Cloudflare dashboard.',
								'designsetgo'
							)}
						</p>
					)}
				</PanelBody>

				<PanelBody
					title={__('Email Notifications', 'designsetgo')}
					initialOpen={false}
				>
					<ToggleControl
						label={__('Enable Email Notifications', 'designsetgo')}
						checked={enableEmail}
						onChange={(value) =>
							setAttributes({ enableEmail: value })
						}
						help={__(
							'Send email when form is submitted',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>

					{enableEmail && (
						<>
							<TextControl
								label={__('Recipient Email', 'designsetgo')}
								value={emailTo}
								onChange={(value) =>
									setAttributes({ emailTo: value })
								}
								type="email"
								placeholder="admin@example.com"
								help={__(
									'Leave empty to use the site admin email address',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<TextControl
								label={__('Email Subject', 'designsetgo')}
								value={emailSubject}
								onChange={(value) =>
									setAttributes({ emailSubject: value })
								}
								help={__(
									'Subject line for notification emails. Use {field_name} for dynamic values.',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<TextControl
								label={__('From Name', 'designsetgo')}
								value={emailFromName}
								onChange={(value) =>
									setAttributes({ emailFromName: value })
								}
								placeholder={__('Site Name', 'designsetgo')}
								help={__(
									'Name shown as email sender (leave empty for site name)',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<TextControl
								label={__('From Email', 'designsetgo')}
								value={emailFromEmail}
								onChange={(value) =>
									setAttributes({ emailFromEmail: value })
								}
								type="email"
								placeholder="wordpress@example.com"
								help={__(
									'Email address shown as sender (leave empty for wordpress@yourdomain.com)',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<TextControl
								label={__('Reply-To Field Name', 'designsetgo')}
								value={emailReplyTo}
								onChange={(value) =>
									setAttributes({ emailReplyTo: value })
								}
								placeholder="email"
								help={__(
									'Use a form field value for reply-to (e.g., "email" or "user_email")',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<TextareaControl
								label={__('Email Body Template', 'designsetgo')}
								value={emailBody}
								onChange={(value) =>
									setAttributes({ emailBody: value })
								}
								placeholder={
									__('New form submission:', 'designsetgo') +
									'\n\n{all_fields}\n\n' +
									__(
										'Submitted from: {page_url}',
										'designsetgo'
									)
								}
								help={__(
									'Email content template. Use {field_name} for specific fields or {all_fields} for all submitted data.',
									'designsetgo'
								)}
								rows={5}
								__nextHasNoMarginBottom
							/>
						</>
					)}
				</PanelBody>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Form Colors', 'designsetgo')}
					settings={[
						{
							label: __('Label Color', 'designsetgo'),
							colorValue: decodeColorValue(
								fieldLabelColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									fieldLabelColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Border Color', 'designsetgo'),
							colorValue: decodeColorValue(
								fieldBorderColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									fieldBorderColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Field Background', 'designsetgo'),
							colorValue: decodeColorValue(
								fieldBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									fieldBackgroundColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Button Text Color', 'designsetgo'),
							colorValue: decodeColorValue(
								submitButtonColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									submitButtonColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Button Background Color', 'designsetgo'),
							colorValue: decodeColorValue(
								submitButtonBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									submitButtonBackgroundColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Button Hover Text Color', 'designsetgo'),
							colorValue: decodeColorValue(
								submitButtonHoverColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									submitButtonHoverColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __(
								'Button Hover Background Color',
								'designsetgo'
							),
							colorValue: decodeColorValue(
								submitButtonHoverBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									submitButtonHoverBackgroundColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
					]}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<div {...blockProps}>
				<div {...innerBlocksPropsWithoutChildren}>
					{children}
					{submitButtonPosition === 'inline' && (
						<button
							type="button"
							className={`dsgo-form__submit dsgo-form__submit--inline wp-element-button${submitAnimationClass}`}
							disabled
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

				{submitButtonPosition === 'below' && (
					<div className="dsgo-form__footer">
						<button
							type="button"
							className={`dsgo-form__submit wp-element-button${submitAnimationClass}`}
							disabled
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
			</div>
		</>
	);
}

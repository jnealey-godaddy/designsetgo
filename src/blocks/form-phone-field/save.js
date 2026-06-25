/**
 * Form Phone Field Block - Save Component
 *
 * Country code options are populated by view.js at runtime to avoid
 * hardcoding a long option list in post_content (which would require
 * a deprecation every time the list changes).
 *
 * @since 1.0.0
 */

import { useBlockProps } from '@wordpress/block-editor';
import classnames from 'classnames';

export default function FormPhoneFieldSave({ attributes }) {
	const {
		fieldName,
		label,
		placeholder,
		helpText,
		required,
		defaultValue,
		phoneFormat,
		showCountryCode,
		countryCode,
		autoFormat,
		fieldWidth,
	} = attributes;

	const fieldClasses = classnames(
		'dsgo-form-field',
		'dsgo-form-field--phone'
	);

	const blockProps = useBlockProps.save({
		className: fieldClasses,
		style: {
			// Use flex-basis with calc to account for gap between fields
			flexBasis:
				fieldWidth === '100'
					? '100%'
					: `calc(${fieldWidth}% - var(--dsgo-form-field-spacing, 1.5rem) / 2)`,
			maxWidth:
				fieldWidth === '100'
					? '100%'
					: `calc(${fieldWidth}% - var(--dsgo-form-field-spacing, 1.5rem) / 2)`,
		},
	});

	const fieldId = `field-${fieldName}`;

	// Get pattern based on phone format
	const getPattern = () => {
		switch (phoneFormat) {
			case 'us':
				return '[0-9]{3}-[0-9]{3}-[0-9]{4}';
			case 'international':
				return '\\+[0-9]{1,3}[0-9\\s\\-]{4,14}';
			default:
				return undefined;
		}
	};

	// Get placeholder based on format
	const getPlaceholder = () => {
		if (placeholder) {
			return placeholder;
		}

		switch (phoneFormat) {
			case 'us':
				return '555-123-4567';
			case 'international':
				return '+1 555 123 4567';
			default:
				return '';
		}
	};

	return (
		<div {...blockProps}>
			<label htmlFor={fieldId} className="dsgo-form-field__label">
				{label}
				{required && (
					<span
						className="dsgo-form-field__required"
						aria-label="required"
					>
						*
					</span>
				)}
			</label>

			<div
				className="dsgo-form-field__phone-wrapper"
				style={{ display: 'flex', gap: '0.5rem' }}
				data-auto-format={autoFormat}
			>
				{showCountryCode && (
					// Options are intentionally empty here — view.js hydrates this
					// <select> at runtime from the shared COUNTRY_CODES constant.
					// The form already requires JS for auto-formatting, so the
					// no-JS experience is an accepted trade-off.
					<select
						name={`${fieldName}_country_code`}
						className="dsgo-form-field__country-code"
						data-dsgo-country-code={countryCode}
						style={{ minWidth: '85px', flexShrink: 0 }}
						aria-label="Country Code"
					/>
				)}
				<input
					type="tel"
					id={fieldId}
					name={fieldName}
					className="dsgo-form-field__input"
					placeholder={getPlaceholder()}
					required={required || undefined}
					defaultValue={defaultValue || undefined}
					pattern={getPattern()}
					aria-describedby={helpText ? `${fieldId}-help` : undefined}
					aria-required={required ? 'true' : undefined}
					data-field-type="tel"
					data-phone-format={phoneFormat}
					style={{ flex: '1' }}
				/>
			</div>

			{helpText && (
				<p id={`${fieldId}-help`} className="dsgo-form-field__help">
					{helpText}
				</p>
			)}
		</div>
	);
}

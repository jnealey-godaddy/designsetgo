/**
 * Form Text Field Block - Deprecated Versions
 *
 * vStatic reproduces the last STATIC save (the block is now server-rendered via
 * render.php; save() returns null). isEligible matches any stored static text
 * field so existing content — and the current block-patterns HTML — migrates
 * silently (passthrough) with no "Attempt Recovery" warning.
 *
 * @since 2.0.34
 */

import { useBlockProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { getDeprecatedBlockHTML } from '../../utils/deprecated-block-html';

/**
 * Supports definition for deprecated versions.
 * Matches block.json supports (with border → __experimentalBorder if applicable).
 */
const sharedSupports = {
	html: false,
	reusable: false,
	inserter: true,
};

/**
 * Shared attribute schema for the static deprecations (identical to block.json).
 */
const sharedAttributes = {
	fieldName: { type: 'string', default: '' },
	label: { type: 'string', default: 'Text Field' },
	placeholder: { type: 'string', default: '' },
	helpText: { type: 'string', default: '' },
	required: { type: 'boolean', default: false },
	defaultValue: { type: 'string', default: '' },
	minLength: { type: 'number', default: 0 },
	maxLength: { type: 'number', default: 0 },
	validation: { type: 'string', default: 'none' },
	validationPattern: { type: 'string', default: '' },
	validationMessage: { type: 'string', default: '' },
	fieldWidth: { type: 'string', default: '100' },
};

/**
 * The last static markup, immediately before the block became server-rendered.
 */
const vStatic = {
	supports: sharedSupports,
	attributes: sharedAttributes,

	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		// Any stored static text field carries this wrapper class; the dynamic
		// block saves no inner HTML, so it never matches.
		return (
			Boolean(innerHTML) && innerHTML.includes('dsgo-form-field--text')
		);
	},

	save({ attributes }) {
		const {
			fieldName,
			label,
			placeholder,
			helpText,
			required,
			defaultValue,
			minLength,
			maxLength,
			validation,
			validationPattern,
			validationMessage,
			fieldWidth,
		} = attributes;

		const fieldClasses = classnames(
			'dsgo-form-field',
			'dsgo-form-field--text'
		);

		const blockProps = useBlockProps.save({
			className: fieldClasses,
			style: {
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

		const getValidationPattern = () => {
			switch (validation) {
				case 'letters':
					return '[A-Za-z\\s]+';
				case 'numbers':
					return '[0-9]+';
				case 'alphanumeric':
					return '[A-Za-z0-9]+';
				case 'custom':
					return validationPattern;
				default:
					return null;
			}
		};

		const pattern = getValidationPattern();
		const fieldId = `field-${fieldName}`;

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

				<input
					type="text"
					id={fieldId}
					name={fieldName}
					className="dsgo-form-field__input"
					placeholder={placeholder || undefined}
					required={required || undefined}
					minLength={minLength > 0 ? minLength : undefined}
					maxLength={maxLength > 0 ? maxLength : undefined}
					pattern={pattern || undefined}
					title={validationMessage || undefined}
					defaultValue={defaultValue || undefined}
					aria-describedby={helpText ? `${fieldId}-help` : undefined}
					aria-required={required ? 'true' : undefined}
					data-field-type="text"
				/>

				{helpText && (
					<p id={`${fieldId}-help`} className="dsgo-form-field__help">
						{helpText}
					</p>
				)}
			</div>
		);
	},

	migrate(attributes) {
		return attributes;
	},
};

/**
 * Version 1: Before aria-required was added to required fields.
 *
 * The site-designer-api generated HTML without aria-required="true" on required
 * input fields. Kept for provenance; vStatic already covers this content.
 */
const v1 = {
	supports: sharedSupports,
	attributes: sharedAttributes,

	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		// v1 blocks lack aria-required on input
		return (
			innerHTML &&
			innerHTML.includes('type="text"') &&
			!innerHTML.includes('aria-required')
		);
	},

	save({ attributes }) {
		const {
			fieldName,
			label,
			placeholder,
			helpText,
			required,
			defaultValue,
			minLength,
			maxLength,
			validation,
			validationPattern,
			validationMessage,
			fieldWidth,
		} = attributes;

		const fieldClasses = classnames(
			'dsgo-form-field',
			'dsgo-form-field--text'
		);

		const blockProps = useBlockProps.save({
			className: fieldClasses,
			style: {
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

		const getValidationPattern = () => {
			switch (validation) {
				case 'letters':
					return '[A-Za-z\\s]+';
				case 'numbers':
					return '[0-9]+';
				case 'alphanumeric':
					return '[A-Za-z0-9]+';
				case 'custom':
					return validationPattern;
				default:
					return null;
			}
		};

		const pattern = getValidationPattern();
		const fieldId = `field-${fieldName}`;

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

				<input
					type="text"
					id={fieldId}
					name={fieldName}
					className="dsgo-form-field__input"
					placeholder={placeholder || undefined}
					required={required || undefined}
					minLength={minLength > 0 ? minLength : undefined}
					maxLength={maxLength > 0 ? maxLength : undefined}
					pattern={pattern || undefined}
					title={validationMessage || undefined}
					defaultValue={defaultValue || undefined}
					aria-describedby={helpText ? `${fieldId}-help` : undefined}
					data-field-type="text"
				/>

				{helpText && (
					<p id={`${fieldId}-help`} className="dsgo-form-field__help">
						{helpText}
					</p>
				)}
			</div>
		);
	},

	migrate(attributes) {
		return attributes;
	},
};

export default [vStatic, v1];

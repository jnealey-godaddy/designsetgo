/**
 * Form Textarea Field Block - Deprecated Versions
 *
 * vStatic reproduces the last STATIC save (the block is now server-rendered via
 * render.php; save() returns null). isEligible matches any stored static
 * textarea field so existing content — and the current block-patterns HTML —
 * migrates silently (passthrough) with no "Attempt Recovery" warning.
 *
 * Also handles backward compatibility for blocks saved with the old name
 * (designsetgo/form-textarea) before the rename to designsetgo/form-textarea-field.
 *
 * @since 2.5.0
 */

import { useBlockProps } from '@wordpress/block-editor';
import classnames from 'classnames';

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
	label: { type: 'string', default: 'Message' },
	placeholder: { type: 'string', default: '' },
	helpText: { type: 'string', default: '' },
	required: { type: 'boolean', default: false },
	defaultValue: { type: 'string', default: '' },
	rows: { type: 'number', default: 4 },
	maxLength: { type: 'number', default: 0 },
	fieldWidth: { type: 'string', default: '100' },
};

/**
 * The last static markup, immediately before the block became server-rendered.
 */
const vStatic = {
	supports: sharedSupports,
	attributes: sharedAttributes,

	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const innerHTML = blockNode?.innerHTML ?? block?.originalContent ?? '';
		// Any stored static textarea field carries this wrapper class; the
		// dynamic block saves no inner HTML, so it never matches.
		return (
			Boolean(innerHTML) &&
			innerHTML.includes('dsgo-form-field--textarea')
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
			rows,
			maxLength,
			fieldWidth,
		} = attributes;

		const fieldClasses = classnames(
			'dsgo-form-field',
			'dsgo-form-field--textarea'
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

				<textarea
					id={fieldId}
					name={fieldName}
					className="dsgo-form-field__textarea"
					placeholder={placeholder || undefined}
					required={required || undefined}
					rows={rows}
					maxLength={maxLength > 0 ? maxLength : undefined}
					defaultValue={defaultValue || undefined}
					aria-describedby={helpText ? `${fieldId}-help` : undefined}
					aria-required={required ? 'true' : undefined}
					data-field-type="textarea"
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
 * Version 2: Before aria-required was added to required fields.
 *
 * The site-designer-api generates HTML without aria-required="true" on
 * required textarea fields. This deprecation matches that older format.
 */
const v2 = {
	supports: sharedSupports,
	attributes: {
		fieldName: {
			type: 'string',
			default: '',
		},
		label: {
			type: 'string',
			default: 'Message',
		},
		placeholder: {
			type: 'string',
			default: '',
		},
		helpText: {
			type: 'string',
			default: '',
		},
		required: {
			type: 'boolean',
			default: false,
		},
		defaultValue: {
			type: 'string',
			default: '',
		},
		rows: {
			type: 'number',
			default: 4,
		},
		maxLength: {
			type: 'number',
			default: 0,
		},
		fieldWidth: {
			type: 'string',
			default: '100',
		},
	},

	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const innerHTML = blockNode?.innerHTML ?? block?.originalContent ?? '';
		// v2 blocks lack aria-required on textarea
		return (
			innerHTML &&
			innerHTML.includes('dsgo-form-field__textarea') &&
			!innerHTML.includes('aria-required')
		);
	},
	migrate(attributes) {
		return attributes;
	},

	save({ attributes }) {
		const {
			fieldName,
			label,
			placeholder,
			helpText,
			required,
			defaultValue,
			rows,
			maxLength,
			fieldWidth,
		} = attributes;

		const fieldClasses = classnames(
			'dsgo-form-field',
			'dsgo-form-field--textarea'
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

				<textarea
					id={fieldId}
					name={fieldName}
					className="dsgo-form-field__textarea"
					placeholder={placeholder || undefined}
					required={required || undefined}
					rows={rows}
					maxLength={maxLength > 0 ? maxLength : undefined}
					defaultValue={defaultValue || undefined}
					aria-describedby={helpText ? `${fieldId}-help` : undefined}
					data-field-type="textarea"
				/>

				{helpText && (
					<p id={`${fieldId}-help`} className="dsgo-form-field__help">
						{helpText}
					</p>
				)}
			</div>
		);
	},
};

/**
 * Version 1: Original version as 'designsetgo/form-textarea'
 * Deprecated when block was renamed to 'designsetgo/form-textarea-field' for consistency.
 */
const v1 = {
	supports: sharedSupports,
	attributes: {
		fieldName: {
			type: 'string',
			default: '',
		},
		label: {
			type: 'string',
			default: 'Message',
		},
		placeholder: {
			type: 'string',
			default: '',
		},
		helpText: {
			type: 'string',
			default: '',
		},
		required: {
			type: 'boolean',
			default: false,
		},
		defaultValue: {
			type: 'string',
			default: '',
		},
		rows: {
			type: 'number',
			default: 4,
		},
		maxLength: {
			type: 'number',
			default: 0,
		},
		fieldWidth: {
			type: 'string',
			default: '100',
		},
	},

	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const innerHTML = blockNode?.innerHTML ?? block?.originalContent ?? '';
		// v1 was the original designsetgo/form-textarea block before rename
		return (
			innerHTML &&
			innerHTML.includes('aria-required') &&
			innerHTML.includes('dsgo-form-field__textarea')
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
			rows,
			maxLength,
			fieldWidth,
		} = attributes;

		const fieldClasses = classnames(
			'dsgo-form-field',
			'dsgo-form-field--textarea'
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

				<textarea
					id={fieldId}
					name={fieldName}
					className="dsgo-form-field__textarea"
					placeholder={placeholder || undefined}
					required={required || undefined}
					rows={rows}
					maxLength={maxLength > 0 ? maxLength : undefined}
					defaultValue={defaultValue || undefined}
					aria-describedby={helpText ? `${fieldId}-help` : undefined}
					aria-required={required ? 'true' : undefined}
					data-field-type="textarea"
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
		// No attribute changes needed, just return as-is
		return attributes;
	},
};

export default [vStatic, v2, v1];

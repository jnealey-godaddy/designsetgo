/**
 * Form Select Field Block - Deprecated Versions
 *
 * vStatic reproduces the last STATIC save (the block is now server-rendered via
 * render.php; save() returns null). isEligible matches any stored static select
 * field so existing content — and the current block-patterns HTML — migrates
 * silently (passthrough) with no "Attempt Recovery" warning.
 *
 * @since 2.0.32
 */

import { useBlockProps } from '@wordpress/block-editor';
import classnames from 'classnames';

/**
 * Supports definition for deprecated versions.
 * Matches block.json supports (with border → __experimentalBorder if applicable).
 */
const sharedSupports = {
	html: false,
	anchor: false,
	customClassName: false,
	reusable: false,
};

/**
 * Shared attribute schema for the static deprecations (identical to block.json).
 */
const sharedAttributes = {
	fieldName: { type: 'string', default: '' },
	label: { type: 'string', default: 'Select Option' },
	helpText: { type: 'string', default: '' },
	required: { type: 'boolean', default: false },
	defaultValue: { type: 'string', default: '' },
	options: {
		type: 'array',
		default: [
			{ label: 'Option 1', value: 'option-1' },
			{ label: 'Option 2', value: 'option-2' },
			{ label: 'Option 3', value: 'option-3' },
		],
		items: {
			type: 'object',
			properties: {
				label: { type: 'string' },
				value: { type: 'string' },
			},
		},
	},
	// Sourced from the stored HTML (the empty-value option's text) rather than a
	// fixed default. The placeholder is the one piece of visible text that the
	// pattern bakes into the markup WITHOUT a matching block-comment attribute,
	// so a translated / site-designer-substituted placeholder (e.g. "-- Choisir
	// --") would otherwise never match save()'s output and block recovery would
	// fail. Sourcing it makes the deprecation reproduce the stored text exactly,
	// so migration is silent and the real placeholder is carried over.
	placeholder: {
		type: 'string',
		source: 'text',
		selector: 'option[value=""]',
	},
	fieldWidth: { type: 'string', default: '100' },
};

/**
 * The last static markup, immediately before the block became server-rendered.
 */
const vStatic = {
	supports: sharedSupports,
	attributes: sharedAttributes,

	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Any stored static select field carries this wrapper class; the
		// dynamic block saves no inner HTML, so it never matches.
		return (
			Boolean(innerHTML) && innerHTML.includes('dsgo-form-field--select')
		);
	},

	save({ attributes }) {
		const {
			fieldName,
			label,
			helpText,
			required,
			options,
			placeholder,
			fieldWidth,
		} = attributes;

		const fieldClasses = classnames(
			'dsgo-form-field',
			'dsgo-form-field--select'
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

				<select
					id={fieldId}
					name={fieldName}
					className="dsgo-form-field__select"
					required={required || undefined}
					aria-describedby={helpText ? `${fieldId}-help` : undefined}
					aria-required={required ? 'true' : undefined}
					data-field-type="select"
				>
					{placeholder && <option value="">{placeholder}</option>}
					{options
						.filter(
							(option) => !(placeholder && option.value === '')
						)
						.map((option, index) => (
							<option key={index} value={option.value}>
								{option.label}
							</option>
						))}
				</select>

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
 * required select fields. This deprecation matches that older format.
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
			default: 'Select Option',
		},
		helpText: {
			type: 'string',
			default: '',
		},
		required: {
			type: 'boolean',
			default: false,
		},
		options: {
			type: 'array',
			default: [
				{ label: 'Option 1', value: 'option-1' },
				{ label: 'Option 2', value: 'option-2' },
				{ label: 'Option 3', value: 'option-3' },
			],
			items: {
				type: 'object',
				properties: {
					label: { type: 'string' },
					value: { type: 'string' },
				},
			},
		},
		placeholder: {
			type: 'string',
			default: '-- Select an option --',
		},
		fieldWidth: {
			type: 'string',
			default: '100',
		},
	},

	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v2 blocks lack aria-required on select
		return (
			innerHTML &&
			innerHTML.includes('dsgo-form-field__select') &&
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
			helpText,
			required,
			options,
			placeholder,
			fieldWidth,
		} = attributes;

		const fieldClasses = classnames(
			'dsgo-form-field',
			'dsgo-form-field--select'
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

				<select
					id={fieldId}
					name={fieldName}
					className="dsgo-form-field__select"
					required={required || undefined}
					aria-describedby={helpText ? `${fieldId}-help` : undefined}
					data-field-type="select"
				>
					{placeholder && <option value="">{placeholder}</option>}
					{options.map((option, index) => (
						<option key={index} value={option.value}>
							{option.label}
						</option>
					))}
				</select>

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
 * Version 1: Original version with defaultValue on <select>.
 *
 * React's defaultValue prop serializes to the non-standard `defaultvalue`
 * HTML attribute, which wp_kses_post() strips. Removed in favour of letting
 * the browser select the first <option> naturally.
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
			default: 'Select Option',
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
		options: {
			type: 'array',
			default: [
				{ label: 'Option 1', value: 'option-1' },
				{ label: 'Option 2', value: 'option-2' },
				{ label: 'Option 3', value: 'option-3' },
			],
			items: {
				type: 'object',
				properties: {
					label: { type: 'string' },
					value: { type: 'string' },
				},
			},
		},
		placeholder: {
			type: 'string',
			default: '-- Select an option --',
		},
		fieldWidth: {
			type: 'string',
			default: '100',
		},
	},

	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v1 blocks use defaultValue on select (non-standard attribute)
		return (
			innerHTML &&
			innerHTML.includes('defaultvalue') &&
			innerHTML.includes('dsgo-form-field__select')
		);
	},

	save({ attributes }) {
		const {
			fieldName,
			label,
			helpText,
			required,
			defaultValue,
			options,
			placeholder,
			fieldWidth,
		} = attributes;

		const fieldClasses = classnames(
			'dsgo-form-field',
			'dsgo-form-field--select'
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

				<select
					id={fieldId}
					name={fieldName}
					className="dsgo-form-field__select"
					required={required || undefined}
					defaultValue={defaultValue || ''}
					aria-describedby={helpText ? `${fieldId}-help` : undefined}
					aria-required={required ? 'true' : undefined}
					data-field-type="select"
				>
					{placeholder && <option value="">{placeholder}</option>}
					{options.map((option, index) => (
						<option key={index} value={option.value}>
							{option.label}
						</option>
					))}
				</select>

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

export default [vStatic, v2, v1];

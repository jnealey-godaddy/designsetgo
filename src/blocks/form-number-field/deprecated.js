/**
 * Form Number Field Block - Deprecated Versions
 *
 * vStatic reproduces the last STATIC save (the block is now server-rendered via
 * render.php; save() returns null). isEligible matches any stored static number
 * field so existing content migrates silently (passthrough) with no "Attempt
 * Recovery" warning.
 *
 * @since 2.5.0
 */

import { useBlockProps } from '@wordpress/block-editor';
import classnames from 'classnames';

/**
 * Supports definition for deprecated versions.
 * Matches block.json supports.
 */
const sharedSupports = {
	html: false,
	anchor: false,
	customClassName: false,
	reusable: false,
};

/**
 * Shared attribute schema for the static deprecation (identical to block.json).
 */
const sharedAttributes = {
	fieldName: { type: 'string', default: '' },
	label: { type: 'string', default: 'Number' },
	placeholder: { type: 'string', default: '' },
	helpText: { type: 'string', default: '' },
	required: { type: 'boolean', default: false },
	defaultValue: { type: 'string', default: '' },
	min: { type: 'number', default: null },
	max: { type: 'number', default: null },
	step: { type: 'number', default: 1 },
	allowDecimals: { type: 'boolean', default: false },
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
		// Any stored static number field carries this wrapper class; the dynamic
		// block saves no inner HTML, so it never matches.
		return (
			Boolean(innerHTML) && innerHTML.includes('dsgo-form-field--number')
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
			min,
			max,
			step,
			allowDecimals,
			fieldWidth,
		} = attributes;

		const fieldClasses = classnames(
			'dsgo-form-field',
			'dsgo-form-field--number'
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

				<input
					type="number"
					id={fieldId}
					name={fieldName}
					className="dsgo-form-field__input"
					placeholder={placeholder || undefined}
					required={required || undefined}
					defaultValue={defaultValue || undefined}
					min={min !== null ? min : undefined}
					max={max !== null ? max : undefined}
					step={allowDecimals ? step : 1}
					aria-describedby={helpText ? `${fieldId}-help` : undefined}
					aria-required={required ? 'true' : undefined}
					data-field-type="number"
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

export default [vStatic];

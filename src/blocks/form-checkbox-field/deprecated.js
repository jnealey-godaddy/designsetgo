/**
 * Form Checkbox Field Block - Deprecated Versions
 *
 * vStatic reproduces the last STATIC save (the block is now server-rendered via
 * render.php; save() returns null). isEligible matches any stored static
 * checkbox field so existing content — and the current block-patterns HTML —
 * migrates silently (passthrough) with no "Attempt Recovery" warning.
 *
 * @since 2.5.0
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';
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
	label: { type: 'string', default: 'I agree to the terms and conditions' },
	helpText: { type: 'string', default: '' },
	required: { type: 'boolean', default: false },
	checkedByDefault: { type: 'boolean', default: false },
	value: { type: 'string', default: '1' },
};

/**
 * The last static markup, immediately before the block became server-rendered.
 */
const vStatic = {
	supports: sharedSupports,
	attributes: sharedAttributes,

	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Any stored static checkbox field carries this wrapper class; the
		// dynamic block saves no inner HTML, so it never matches.
		return (
			Boolean(innerHTML) &&
			innerHTML.includes('dsgo-form-field--checkbox')
		);
	},

	save({ attributes }) {
		const {
			fieldName,
			label,
			helpText,
			required,
			checkedByDefault,
			value,
		} = attributes;

		const fieldClasses = classnames(
			'dsgo-form-field',
			'dsgo-form-field--checkbox'
		);

		const blockProps = useBlockProps.save({
			className: fieldClasses,
		});

		const fieldId = `field-${fieldName}`;

		return (
			<div {...blockProps}>
				<div className="dsgo-form-field__checkbox-wrapper">
					<input
						type="checkbox"
						id={fieldId}
						name={fieldName}
						className="dsgo-form-field__checkbox-input"
						value={value}
						defaultChecked={checkedByDefault || undefined}
						required={required || undefined}
						aria-describedby={
							helpText ? `${fieldId}-help` : undefined
						}
						aria-required={required ? 'true' : undefined}
						data-field-type="checkbox"
					/>
					<label
						htmlFor={fieldId}
						className="dsgo-form-field__checkbox-label"
					>
						<RichText.Content tagName="span" value={label} />
						{required && (
							<span
								className="dsgo-form-field__required"
								aria-label="required"
							>
								{' '}
								*
							</span>
						)}
					</label>
				</div>

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

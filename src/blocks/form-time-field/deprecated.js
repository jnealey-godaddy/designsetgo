/**
 * Form Time Field Block - Deprecated Versions
 *
 * vStatic reproduces the last STATIC save (the block is now server-rendered via
 * render.php; save() returns null). isEligible matches any stored static time
 * field so existing content — and the current block-patterns HTML — migrates
 * silently (passthrough) with no "Attempt Recovery" warning.
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
	label: { type: 'string', default: 'Time' },
	helpText: { type: 'string', default: '' },
	required: { type: 'boolean', default: false },
	defaultValue: { type: 'string', default: '' },
	minTime: { type: 'string', default: '' },
	maxTime: { type: 'string', default: '' },
	step: { type: 'number', default: 60 },
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
		// Any stored static time field carries this wrapper class; the dynamic
		// block saves no inner HTML, so it never matches.
		return (
			Boolean(innerHTML) && innerHTML.includes('dsgo-form-field--time')
		);
	},

	save({ attributes }) {
		const {
			fieldName,
			label,
			helpText,
			required,
			defaultValue,
			minTime,
			maxTime,
			step,
			fieldWidth,
		} = attributes;

		const fieldClasses = classnames(
			'dsgo-form-field',
			'dsgo-form-field--time'
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
					type="time"
					id={fieldId}
					name={fieldName}
					className="dsgo-form-field__input"
					required={required || undefined}
					defaultValue={defaultValue || undefined}
					min={minTime || undefined}
					max={maxTime || undefined}
					step={step}
					aria-describedby={helpText ? `${fieldId}-help` : undefined}
					aria-required={required ? 'true' : undefined}
					data-field-type="time"
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

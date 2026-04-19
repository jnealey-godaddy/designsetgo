/**
 * Form Phone Field Block - Edit Component
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	TextControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { useEffect } from '@wordpress/element';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import COUNTRY_CODES from './country-codes';

const FIELD_WIDTH_OPTIONS = [
	{ label: __('Full Width (100%)', 'designsetgo'), value: '100' },
	{ label: __('Half Width (50%)', 'designsetgo'), value: '50' },
	{ label: __('One Third (33%)', 'designsetgo'), value: '33' },
	{ label: __('Two Thirds (66%)', 'designsetgo'), value: '66' },
	{ label: __('One Quarter (25%)', 'designsetgo'), value: '25' },
	{ label: __('Three Quarters (75%)', 'designsetgo'), value: '75' },
];

export default function FormPhoneFieldEdit({
	attributes,
	setAttributes,
	clientId,
	context,
}) {
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

	// Generate field name from clientId if empty
	useEffect(() => {
		if (!fieldName) {
			setAttributes({ fieldName: `phone-${clientId.slice(0, 8)}` });
		}
	}, [fieldName, clientId, setAttributes]);

	// Get context values from parent form
	const fieldLabelColor = context['designsetgo/form/fieldLabelColor'];
	const fieldBorderColor = context['designsetgo/form/fieldBorderColor'];
	const fieldBackgroundColor =
		context['designsetgo/form/fieldBackgroundColor'];

	const fieldClasses = classnames(
		'dsgo-form-field',
		'dsgo-form-field--phone'
	);

	const fieldStyles = {
		'--dsgo-field-label-color': convertColorToCSSVar(fieldLabelColor),
		'--dsgo-field-border-color': convertColorToCSSVar(fieldBorderColor),
		'--dsgo-form-field-bg': convertColorToCSSVar(fieldBackgroundColor),
	};

	const blockProps = useBlockProps({
		className: fieldClasses,
		style: {
			...fieldStyles,
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
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							fieldName: '',
							label: 'Phone Number',
							placeholder: '',
							helpText: '',
							required: false,
							defaultValue: '',
							phoneFormat: 'any',
							showCountryCode: true,
							countryCode: '+1',
							autoFormat: true,
							fieldWidth: '100',
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Field Name', 'designsetgo')}
						hasValue={() =>
							!!fieldName &&
							!/^phone-[a-z0-9]{1,8}$/i.test(fieldName)
						}
						onDeselect={() => setAttributes({ fieldName: '' })}
						isShownByDefault
					>
						<TextControl
							label={__('Field Name', 'designsetgo')}
							value={fieldName}
							onChange={(value) =>
								setAttributes({
									fieldName: value.replace(
										/[^a-z0-9_-]/gi,
										''
									),
								})
							}
							help={__(
								'Unique identifier for this field (letters, numbers, hyphens, underscores only)',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Label', 'designsetgo')}
						hasValue={() => label !== 'Phone Number'}
						onDeselect={() =>
							setAttributes({ label: 'Phone Number' })
						}
						isShownByDefault
					>
						<TextControl
							label={__('Label', 'designsetgo')}
							value={label}
							onChange={(value) =>
								setAttributes({ label: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Required', 'designsetgo')}
						hasValue={() => required !== false}
						onDeselect={() => setAttributes({ required: false })}
						isShownByDefault
					>
						<ToggleControl
							label={__('Required', 'designsetgo')}
							checked={required}
							onChange={(value) =>
								setAttributes({ required: value })
							}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Phone Format', 'designsetgo')}
						hasValue={() => phoneFormat !== 'any'}
						onDeselect={() => setAttributes({ phoneFormat: 'any' })}
						isShownByDefault
					>
						<SelectControl
							label={__('Phone Format', 'designsetgo')}
							value={phoneFormat}
							options={[
								{
									label: __('Any Format', 'designsetgo'),
									value: 'any',
								},
								{
									label: __(
										'US Format (555–123–4567)',
										'designsetgo'
									),
									value: 'us',
								},
								{
									label: __(
										'International (+1 555 123 4567)',
										'designsetgo'
									),
									value: 'international',
								},
							]}
							onChange={(value) =>
								setAttributes({ phoneFormat: value })
							}
							help={__(
								'Choose how phone numbers should be formatted',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Show Country Code Selector', 'designsetgo')}
						hasValue={() => showCountryCode !== true}
						onDeselect={() =>
							setAttributes({ showCountryCode: true })
						}
						isShownByDefault
					>
						<ToggleControl
							label={__(
								'Show Country Code Selector',
								'designsetgo'
							)}
							checked={showCountryCode}
							onChange={(value) =>
								setAttributes({ showCountryCode: value })
							}
							help={__(
								'Display a dropdown for selecting country code',
								'designsetgo'
							)}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{showCountryCode && (
						<DsgoInspectorPanel.Item
							label={__('Default Country Code', 'designsetgo')}
							hasValue={() => countryCode !== '+1'}
							onDeselect={() =>
								setAttributes({ countryCode: '+1' })
							}
							isShownByDefault
						>
							<SelectControl
								label={__(
									'Default Country Code',
									'designsetgo'
								)}
								value={countryCode}
								options={COUNTRY_CODES}
								onChange={(value) =>
									setAttributes({ countryCode: value })
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					<DsgoInspectorPanel.Item
						label={__('Auto-Format Phone Number', 'designsetgo')}
						hasValue={() => autoFormat !== true}
						onDeselect={() => setAttributes({ autoFormat: true })}
						isShownByDefault
					>
						<ToggleControl
							label={__(
								'Auto-Format Phone Number',
								'designsetgo'
							)}
							checked={autoFormat}
							onChange={(value) =>
								setAttributes({ autoFormat: value })
							}
							help={__(
								'Automatically format phone number as user types',
								'designsetgo'
							)}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Placeholder', 'designsetgo')}
						hasValue={() => placeholder !== ''}
						onDeselect={() => setAttributes({ placeholder: '' })}
						isShownByDefault
					>
						<TextControl
							label={__('Placeholder', 'designsetgo')}
							value={placeholder}
							onChange={(value) =>
								setAttributes({ placeholder: value })
							}
							help={__(
								'Example text shown when field is empty',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Help Text', 'designsetgo')}
						hasValue={() => helpText !== ''}
						onDeselect={() => setAttributes({ helpText: '' })}
						isShownByDefault
					>
						<TextControl
							label={__('Help Text', 'designsetgo')}
							value={helpText}
							onChange={(value) =>
								setAttributes({ helpText: value })
							}
							help={__(
								'Additional guidance shown below the field',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Default Value', 'designsetgo')}
						hasValue={() => defaultValue !== ''}
						onDeselect={() => setAttributes({ defaultValue: '' })}
						isShownByDefault
					>
						<TextControl
							label={__('Default Value', 'designsetgo')}
							value={defaultValue}
							onChange={(value) =>
								setAttributes({ defaultValue: value })
							}
							help={__(
								'Pre-filled value for this field',
								'designsetgo'
							)}
							type="tel"
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Field Width', 'designsetgo')}
						hasValue={() => fieldWidth !== '100'}
						onDeselect={() => setAttributes({ fieldWidth: '100' })}
						isShownByDefault
					>
						<SelectControl
							label={__('Field Width', 'designsetgo')}
							value={fieldWidth}
							options={FIELD_WIDTH_OPTIONS}
							onChange={(value) =>
								setAttributes({ fieldWidth: value })
							}
							help={__(
								'Set field width to create multi-column layouts',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

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
				>
					{showCountryCode && (
						<select
							className="dsgo-form-field__country-code"
							defaultValue={countryCode}
							disabled
							style={{ minWidth: '85px', flexShrink: 0 }}
						>
							{COUNTRY_CODES.map((code) => (
								<option key={code.value} value={code.value}>
									{code.value}
								</option>
							))}
						</select>
					)}
					<input
						type="tel"
						id={fieldId}
						className="dsgo-form-field__input"
						placeholder={getPlaceholder()}
						defaultValue={defaultValue || undefined}
						pattern={getPattern()}
						aria-describedby={
							helpText ? `${fieldId}-help` : undefined
						}
						disabled
						style={{ flex: 1 }}
					/>
				</div>

				{helpText && (
					<p id={`${fieldId}-help`} className="dsgo-form-field__help">
						{helpText}
					</p>
				)}
			</div>
		</>
	);
}

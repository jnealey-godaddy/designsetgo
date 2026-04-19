/**
 * Form Email Field Block - Editor Component
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { useEffect } from '@wordpress/element';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

const FIELD_WIDTH_OPTIONS = [
	{ label: __('Full Width (100%)', 'designsetgo'), value: '100' },
	{ label: __('Half Width (50%)', 'designsetgo'), value: '50' },
	{ label: __('One Third (33%)', 'designsetgo'), value: '33' },
	{ label: __('Two Thirds (66%)', 'designsetgo'), value: '66' },
	{ label: __('One Quarter (25%)', 'designsetgo'), value: '25' },
	{ label: __('Three Quarters (75%)', 'designsetgo'), value: '75' },
];

export default function FormEmailFieldEdit({
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
		fieldWidth,
	} = attributes;

	// Generate unique field name on mount if not set
	useEffect(() => {
		if (!fieldName) {
			setAttributes({ fieldName: `field_${clientId.substring(0, 8)}` });
		}
	}, [fieldName, clientId, setAttributes]);

	// Get context values from parent form
	const fieldLabelColor = context['designsetgo/form/fieldLabelColor'];
	const fieldBorderColor = context['designsetgo/form/fieldBorderColor'];
	const fieldBackgroundColor =
		context['designsetgo/form/fieldBackgroundColor'];

	const fieldClasses = classnames(
		'dsgo-form-field',
		'dsgo-form-field--email'
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
							label: 'Email',
							placeholder: '',
							helpText: '',
							required: false,
							defaultValue: '',
							fieldWidth: '100',
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Field Name', 'designsetgo')}
						hasValue={() =>
							!!fieldName &&
							!/^field_[a-z0-9]{1,8}$/i.test(fieldName)
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
										/[^a-z0-9_]/g,
										'_'
									),
								})
							}
							help={__(
								'Unique identifier for this field',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Label', 'designsetgo')}
						hasValue={() => label !== 'Email'}
						onDeselect={() => setAttributes({ label: 'Email' })}
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
						label={__('Required Field', 'designsetgo')}
						hasValue={() => required !== false}
						onDeselect={() => setAttributes({ required: false })}
						isShownByDefault
					>
						<ToggleControl
							label={__('Required Field', 'designsetgo')}
							checked={required}
							onChange={(value) =>
								setAttributes({ required: value })
							}
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
							placeholder="email@example.com"
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
						<TextareaControl
							label={__('Help Text', 'designsetgo')}
							value={helpText}
							onChange={(value) =>
								setAttributes({ helpText: value })
							}
							rows={2}
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
							type="email"
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
				<label
					htmlFor={`field-${clientId}`}
					className="dsgo-form-field__label"
				>
					{label}
					{required && (
						<span className="dsgo-form-field__required">*</span>
					)}
				</label>

				<input
					type="email"
					id={`field-${clientId}`}
					className="dsgo-form-field__input"
					placeholder={placeholder}
					disabled
				/>

				{helpText && (
					<p className="dsgo-form-field__help">{helpText}</p>
				)}
			</div>
		</>
	);
}

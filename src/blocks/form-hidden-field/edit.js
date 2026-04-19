/**
 * Form Hidden Field Block - Edit Component
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { TextControl, Notice } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { useEffect } from '@wordpress/element';

export default function FormHiddenFieldEdit({
	attributes,
	setAttributes,
	clientId,
}) {
	const { fieldName, value } = attributes;

	// Generate field name from clientId if empty
	useEffect(() => {
		if (!fieldName) {
			setAttributes({ fieldName: `hidden-${clientId.slice(0, 8)}` });
		}
	}, [fieldName, clientId, setAttributes]);

	const blockProps = useBlockProps({
		className: 'dsgo-form-field dsgo-form-field--hidden',
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
							value: '',
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Field Name', 'designsetgo')}
						hasValue={() =>
							!!fieldName &&
							!/^hidden-[a-z0-9]{1,8}$/i.test(fieldName)
						}
						onDeselect={() => setAttributes({ fieldName: '' })}
						isShownByDefault
					>
						<TextControl
							label={__('Field Name', 'designsetgo')}
							value={fieldName}
							onChange={(newValue) =>
								setAttributes({
									fieldName: newValue.replace(
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
						label={__('Value', 'designsetgo')}
						hasValue={() => value !== ''}
						onDeselect={() => setAttributes({ value: '' })}
						isShownByDefault
					>
						<TextControl
							label={__('Value', 'designsetgo')}
							value={value}
							onChange={(newValue) =>
								setAttributes({ value: newValue })
							}
							help={__(
								'The hidden value to be submitted with the form',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

			<div {...blockProps}>
				<Notice status="info" isDismissible={false}>
					<strong>{__('Hidden Field:', 'designsetgo')}</strong>{' '}
					{fieldName} = {value || __('(empty)', 'designsetgo')}
				</Notice>
			</div>
		</>
	);
}

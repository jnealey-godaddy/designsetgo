/**
 * Optional native track templates for tablet and mobile Grid layouts.
 */
import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';
import { sanitizeColumnTemplate } from '../grid-columns';

/**
 * Resettable Settings controls for responsive track templates.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute update callback.
 * @return {JSX.Element} Inspector items.
 */
export default function ResponsiveTemplateControls({
	attributes,
	setAttributes,
}) {
	return (
		<>
			{[
				[
					'tabletColumnTemplate',
					__('Tablet Column Template', 'designsetgo'),
					__(
						'Column widths from 768px to 1024px, replacing Tablet Columns. Leave empty to use Tablet Columns.',
						'designsetgo'
					),
				],
				[
					'mobileColumnTemplate',
					__('Mobile Column Template', 'designsetgo'),
					__(
						'Column widths at 767px and below, replacing Mobile Columns. Leave empty to use Mobile Columns.',
						'designsetgo'
					),
				],
			].map(([attribute, label, help]) => (
				<DsgoInspectorPanel.Item
					key={attribute}
					label={label}
					hasValue={() => !!attributes[attribute]}
					onDeselect={() => setAttributes({ [attribute]: '' })}
					isShownByDefault
				>
					<TextControl
						label={label}
						value={attributes[attribute] || ''}
						onChange={(value) =>
							setAttributes({
								[attribute]: sanitizeColumnTemplate(value),
							})
						}
						placeholder={__('e.g. 2fr 1fr', 'designsetgo')}
						help={help}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			))}
		</>
	);
}

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
				],
				[
					'mobileColumnTemplate',
					__('Mobile Column Template', 'designsetgo'),
				],
			].map(([attribute, label]) => (
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
						placeholder="minmax(0, 2fr) minmax(0, 1fr)"
						help={__(
							'Overrides the column count at this screen size. Leave empty to use the column count.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			))}
		</>
	);
}

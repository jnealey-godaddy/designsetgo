/**
 * Slider — navigation behaviour fields.
 *
 * Whether arrows and dots render at all. Their visual appearance (style,
 * position, size, color) lives in ArrowAppearanceFields / DotAppearanceFields
 * in the Style panel. Composed inside the Settings DsgoInspectorPanel in
 * SettingsPanel.js.
 */

import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

/**
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element} Navigation behaviour fields.
 */
export default function NavigationFields({ attributes, setAttributes }) {
	const { showArrows, showDots } = attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Show Arrows', 'designsetgo')}
				hasValue={() => showArrows !== true}
				onDeselect={() => setAttributes({ showArrows: true })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Show Arrows', 'designsetgo')}
					checked={showArrows}
					onChange={(value) => setAttributes({ showArrows: value })}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Show Dots', 'designsetgo')}
				hasValue={() => showDots !== true}
				onDeselect={() => setAttributes({ showDots: true })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Show Dots', 'designsetgo')}
					checked={showDots}
					onChange={(value) => setAttributes({ showDots: value })}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}

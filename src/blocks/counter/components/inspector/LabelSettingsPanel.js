/**
 * Counter Block - Label Settings Panel Component
 *
 * Renders DsgoInspectorPanel.Item for the counter label. Meant to be
 * composed inside the Settings DsgoInspectorPanel in counter/edit.js.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

export const LabelSettingsPanel = ({ label, setAttributes }) => {
	return (
		<DsgoInspectorPanel.Item
			label={__('Label', 'designsetgo')}
			hasValue={() => label !== ''}
			onDeselect={() => setAttributes({ label: '' })}
			isShownByDefault
		>
			<TextControl
				label={__('Label', 'designsetgo')}
				value={label}
				onChange={(value) => setAttributes({ label: value })}
				placeholder={__('Enter label…', 'designsetgo')}
				help={__('Description text below counter', 'designsetgo')}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
		</DsgoInspectorPanel.Item>
	);
};

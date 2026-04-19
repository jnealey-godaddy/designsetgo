/**
 * Overlay Settings Panel Component
 *
 * Renders DsgoInspectorPanel.Item entries for the modal's overlay
 * attributes, meant to be composed inside the Settings panel in
 * modal/edit.js.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { RangeControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

export default function OverlaySettings({ attributes, setAttributes }) {
	const { overlayOpacity, overlayBlur } = attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Overlay Opacity (%)', 'designsetgo')}
				hasValue={() => overlayOpacity !== 80}
				onDeselect={() => setAttributes({ overlayOpacity: 80 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Overlay Opacity (%)', 'designsetgo')}
					value={overlayOpacity}
					onChange={(value) =>
						setAttributes({ overlayOpacity: value })
					}
					min={0}
					max={100}
					step={5}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Backdrop Blur (px)', 'designsetgo')}
				hasValue={() => overlayBlur !== 0}
				onDeselect={() => setAttributes({ overlayBlur: 0 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Backdrop Blur (px)', 'designsetgo')}
					value={overlayBlur}
					onChange={(value) => setAttributes({ overlayBlur: value })}
					min={0}
					max={20}
					step={1}
					help={__(
						'Blurs the background content when modal is open.',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}

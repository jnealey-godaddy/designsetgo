/**
 * Animation Settings Panel Component
 *
 * Renders DsgoInspectorPanel.Item entries for the modal's animation
 * attributes, meant to be composed inside the Settings panel in
 * modal/edit.js.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { RangeControl, SelectControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

export default function AnimationSettings({ attributes, setAttributes }) {
	const { animationType, animationDuration } = attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Animation Type', 'designsetgo')}
				hasValue={() => animationType !== 'fade'}
				onDeselect={() => setAttributes({ animationType: 'fade' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Animation Type', 'designsetgo')}
					value={animationType}
					onChange={(value) =>
						setAttributes({ animationType: value })
					}
					options={[
						{ label: __('Fade', 'designsetgo'), value: 'fade' },
						{
							label: __('Slide Up', 'designsetgo'),
							value: 'slide-up',
						},
						{
							label: __('Slide Down', 'designsetgo'),
							value: 'slide-down',
						},
						{ label: __('Zoom In', 'designsetgo'), value: 'zoom' },
						{ label: __('None', 'designsetgo'), value: 'none' },
					]}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Animation Duration (ms)', 'designsetgo')}
				hasValue={() => animationDuration !== 300}
				onDeselect={() => setAttributes({ animationDuration: 300 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Animation Duration (ms)', 'designsetgo')}
					value={animationDuration}
					onChange={(value) =>
						setAttributes({ animationDuration: value })
					}
					min={0}
					max={1000}
					step={50}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}

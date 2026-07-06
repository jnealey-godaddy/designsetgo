/**
 * Section Divider — inspector Settings panel
 *
 * Shape / Height / Width / Flip controls, wired to the Theme-3
 * `<DsgoInspectorPanel>` convention (Settings panel, per-control reset).
 * Extracted from edit.js to keep both files under the 300-line guideline.
 *
 * @since 2.7.0
 */

import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	RangeControl,
	ToggleControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';
import { getShapeDividerOptions } from '../utils';

export default function SectionDividerControls({
	attributes,
	setAttributes,
	clientId,
}) {
	const { shape, height, width, flipX, flipY } = attributes;

	return (
		<DsgoInspectorPanel
			title={__('Settings', 'designsetgo')}
			panelName="settings"
			panelId={clientId}
			resetAll={() =>
				setAttributes({
					shape: 'inherit',
					height: null,
					width: 100,
					flipX: false,
					flipY: false,
				})
			}
		>
			<DsgoInspectorPanel.Item
				label={__('Shape', 'designsetgo')}
				hasValue={() => shape !== 'inherit'}
				onDeselect={() => setAttributes({ shape: 'inherit' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Shape', 'designsetgo')}
					value={shape}
					options={(() => {
						// Strip the leading "None" (value: '') option — this
						// block has no "off" state (to remove a divider you
						// delete the block); selecting it would emit a bare
						// `is-shape-` class and paint an unmasked rectangle.
						// Mirrors src/blocks/section/components/ShapeDividerControls.js.
						const [, ...shapes] = getShapeDividerOptions();
						return [
							{
								label: __('Theme default', 'designsetgo'),
								value: 'inherit',
							},
							...shapes,
						];
					})()}
					onChange={(value) => setAttributes({ shape: value })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Height', 'designsetgo')}
				hasValue={() => height !== null}
				onDeselect={() => setAttributes({ height: null })}
				isShownByDefault
			>
				<RangeControl
					label={__('Height', 'designsetgo')}
					value={height}
					onChange={(value) =>
						setAttributes({ height: value ?? null })
					}
					min={10}
					max={500}
					step={1}
					allowReset
					placeholder={__('Theme default', 'designsetgo')}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Width', 'designsetgo')}
				hasValue={() => width !== 100}
				onDeselect={() => setAttributes({ width: 100 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Width', 'designsetgo')}
					value={width}
					onChange={(value) => setAttributes({ width: value ?? 100 })}
					min={100}
					max={300}
					step={1}
					help={__(
						'Stretch the shape wider for more dramatic effect.',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Flip horizontal', 'designsetgo')}
				hasValue={() => flipX}
				onDeselect={() => setAttributes({ flipX: false })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Flip horizontal', 'designsetgo')}
					checked={flipX}
					onChange={(value) => setAttributes({ flipX: value })}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Flip vertical', 'designsetgo')}
				hasValue={() => flipY}
				onDeselect={() => setAttributes({ flipY: false })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Flip vertical', 'designsetgo')}
					checked={flipY}
					onChange={(value) => setAttributes({ flipY: value })}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</DsgoInspectorPanel>
	);
}

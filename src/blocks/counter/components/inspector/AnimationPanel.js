/**
 * Counter Block - Animation Panel Component
 *
 * Renders DsgoInspectorPanel.Item entries for animation override.
 * Meant to be composed inside the Settings DsgoInspectorPanel in
 * counter/edit.js.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	ToggleControl,
	RangeControl,
	SelectControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

export const AnimationPanel = ({
	overrideAnimation,
	customDuration,
	customDelay,
	customEasing,
	context,
	setAttributes,
}) => {
	// Get parent settings from context (with fallback defaults)
	const parentDuration =
		context?.['designsetgo/counterGroup/animationDuration'] || 2;
	const parentDelay =
		context?.['designsetgo/counterGroup/animationDelay'] || 0;
	const parentEasing =
		context?.['designsetgo/counterGroup/animationEasing'] || 'easeOutQuad';

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Override Parent Animation', 'designsetgo')}
				hasValue={() => overrideAnimation !== false}
				onDeselect={() => setAttributes({ overrideAnimation: false })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Override Parent Animation', 'designsetgo')}
					checked={overrideAnimation}
					onChange={(value) =>
						setAttributes({ overrideAnimation: value })
					}
					help={__(
						'Use custom animation settings instead of parent settings',
						'designsetgo'
					)}
					__nextHasNoMarginBottom
				/>
				{!overrideAnimation && (
					<div
						style={{
							padding: '12px',
							background: '#f0f0f0',
							borderRadius: '4px',
							marginTop: '12px',
						}}
					>
						<p
							style={{
								margin: 0,
								fontSize: '12px',
								color: '#666',
							}}
						>
							<strong>
								{__('Using parent settings:', 'designsetgo')}
							</strong>
							<br />
							{__('Duration:', 'designsetgo')} {parentDuration}s
							<br />
							{__('Delay:', 'designsetgo')} {parentDelay}s
							<br />
							{__('Easing:', 'designsetgo')} {parentEasing}
						</p>
					</div>
				)}
			</DsgoInspectorPanel.Item>

			{overrideAnimation && (
				<DsgoInspectorPanel.Item
					label={__('Animation Duration (seconds)', 'designsetgo')}
					hasValue={() => customDuration !== 2}
					onDeselect={() => setAttributes({ customDuration: 2 })}
					isShownByDefault
				>
					<RangeControl
						label={__(
							'Animation Duration (seconds)',
							'designsetgo'
						)}
						value={customDuration}
						onChange={(value) =>
							setAttributes({ customDuration: value })
						}
						min={0.5}
						max={5}
						step={0.1}
						help={__(
							'How long the counting animation takes',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{overrideAnimation && (
				<DsgoInspectorPanel.Item
					label={__('Animation Delay (seconds)', 'designsetgo')}
					hasValue={() => customDelay !== 0}
					onDeselect={() => setAttributes({ customDelay: 0 })}
					isShownByDefault
				>
					<RangeControl
						label={__('Animation Delay (seconds)', 'designsetgo')}
						value={customDelay}
						onChange={(value) =>
							setAttributes({ customDelay: value })
						}
						min={0}
						max={2}
						step={0.1}
						help={__(
							'Delay before animation starts',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{overrideAnimation && (
				<DsgoInspectorPanel.Item
					label={__('Easing Function', 'designsetgo')}
					hasValue={() => customEasing !== 'easeOutQuad'}
					onDeselect={() =>
						setAttributes({ customEasing: 'easeOutQuad' })
					}
					isShownByDefault
				>
					<SelectControl
						label={__('Easing Function', 'designsetgo')}
						value={customEasing}
						options={[
							{
								label: __('Ease Out Quad', 'designsetgo'),
								value: 'easeOutQuad',
							},
							{
								label: __('Ease Out Cubic', 'designsetgo'),
								value: 'easeOutCubic',
							},
							{
								label: __('Ease In Out', 'designsetgo'),
								value: 'easeInOutQuad',
							},
							{
								label: __('Linear', 'designsetgo'),
								value: 'linear',
							},
						]}
						onChange={(value) =>
							setAttributes({ customEasing: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}
		</>
	);
};

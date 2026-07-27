/**
 * Block Animations - Settings Panel
 *
 * Panel for the per-block animation tri-state (Inherit / Custom / Off),
 * the Custom controls, and the inherited-theme-default indicator.
 *
 * @package
 * @since 1.0.0
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	RangeControl,
	Notice,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import {
	ANIMATION_TYPES,
	ANIMATION_TRIGGERS,
	ANIMATION_DURATIONS,
	ANIMATION_EASINGS,
} from '../constants';
import { resolveBlockAnimationDefault } from '../resolve-default';

/**
 * Human label for an entrance/exit value.
 *
 * @param {string} value Animation value.
 * @return {string} Label or the raw value.
 */
function animationLabel(value) {
	const all = [...ANIMATION_TYPES.entrance, ...ANIMATION_TYPES.exit];
	const found = all.find((opt) => opt.value === value);
	return found ? found.label : value;
}

/**
 * Animation Settings Panel.
 *
 * @param {Object}   props               Component props.
 * @param {string}   props.name          Block name.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element} Panel.
 */
export default function AnimationPanel({ name, attributes, setAttributes }) {
	const {
		dsgoAnimationEnabled,
		dsgoAnimationOptOut,
		dsgoEntranceAnimation,
		dsgoExitAnimation,
		dsgoAnimationTrigger,
		dsgoAnimationDuration,
		dsgoAnimationDelay,
		dsgoAnimationEasing,
		dsgoAnimationOffset,
		dsgoAnimationOnce,
	} = attributes;

	// Derive tri-state from the two attributes.
	let mode = 'inherit';
	if (dsgoAnimationEnabled) {
		mode = 'custom';
	} else if (dsgoAnimationOptOut) {
		mode = 'off';
	}

	const themeDefault = resolveBlockAnimationDefault(name);

	const onModeChange = (value) => {
		if (value === 'custom') {
			setAttributes({
				dsgoAnimationEnabled: true,
				dsgoAnimationOptOut: false,
			});
		} else if (value === 'off') {
			setAttributes({
				dsgoAnimationEnabled: false,
				dsgoAnimationOptOut: true,
			});
		} else {
			setAttributes({
				dsgoAnimationEnabled: false,
				dsgoAnimationOptOut: false,
			});
		}
	};

	return (
		<PanelBody
			title={__('Animations', 'designsetgo')}
			initialOpen={false}
			icon="video-alt3"
		>
			<ToggleGroupControl
				label={__('Animation', 'designsetgo')}
				value={mode}
				isBlock
				onChange={onModeChange}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			>
				<ToggleGroupControlOption
					value="inherit"
					label={__('Theme', 'designsetgo')}
				/>
				<ToggleGroupControlOption
					value="custom"
					label={__('Custom', 'designsetgo')}
				/>
				<ToggleGroupControlOption
					value="off"
					label={__('Off', 'designsetgo')}
				/>
			</ToggleGroupControl>

			{mode === 'inherit' && themeDefault && (
				<Notice status="info" isDismissible={false}>
					{sprintf(
						/* translators: 1: animation name, 2: trigger, 3: duration in ms. */
						__(
							'Inheriting theme animation: %1$s · %2$s · %3$dms',
							'designsetgo'
						),
						[themeDefault.entrance, themeDefault.exit]
							.filter(Boolean)
							.map(animationLabel)
							.join(' / '),
						themeDefault.trigger,
						themeDefault.duration
					)}
				</Notice>
			)}

			{mode === 'inherit' && !themeDefault && (
				<Notice status="info" isDismissible={false}>
					{__(
						'No theme animation for this block type.',
						'designsetgo'
					)}
				</Notice>
			)}

			{mode === 'custom' && (
				<>
					<SelectControl
						label={__('Entrance Animation', 'designsetgo')}
						value={dsgoEntranceAnimation}
						options={[
							{ label: __('None', 'designsetgo'), value: '' },
							...ANIMATION_TYPES.entrance,
						]}
						onChange={(value) =>
							setAttributes({ dsgoEntranceAnimation: value })
						}
						help={__('Animation when block appears', 'designsetgo')}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Exit Animation (Optional)', 'designsetgo')}
						value={dsgoExitAnimation}
						options={[
							{ label: __('None', 'designsetgo'), value: '' },
							...ANIMATION_TYPES.exit,
						]}
						onChange={(value) => {
							if (value && dsgoAnimationTrigger === 'scroll') {
								setAttributes({
									dsgoExitAnimation: value,
									dsgoAnimationOnce: false,
								});
							} else {
								setAttributes({ dsgoExitAnimation: value });
							}
						}}
						help={__(
							'Animation when block disappears',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Animation Trigger', 'designsetgo')}
						value={dsgoAnimationTrigger}
						options={ANIMATION_TRIGGERS}
						onChange={(value) =>
							setAttributes({ dsgoAnimationTrigger: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Duration', 'designsetgo')}
						value={dsgoAnimationDuration}
						options={ANIMATION_DURATIONS}
						onChange={(value) =>
							setAttributes({
								dsgoAnimationDuration: parseInt(value, 10),
							})
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<RangeControl
						label={__('Delay (ms)', 'designsetgo')}
						value={dsgoAnimationDelay}
						onChange={(value) =>
							setAttributes({ dsgoAnimationDelay: value })
						}
						min={0}
						max={3000}
						step={100}
						help={__(
							'Delay before animation starts',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Easing', 'designsetgo')}
						value={dsgoAnimationEasing}
						options={ANIMATION_EASINGS}
						onChange={(value) =>
							setAttributes({ dsgoAnimationEasing: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					{dsgoAnimationTrigger === 'scroll' && (
						<>
							<RangeControl
								label={__(
									'Viewport Offset (px)',
									'designsetgo'
								)}
								value={dsgoAnimationOffset}
								onChange={(value) =>
									setAttributes({
										dsgoAnimationOffset: value,
									})
								}
								min={0}
								max={500}
								step={10}
								help={__(
									'Distance from viewport to trigger animation',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							{dsgoExitAnimation && (
								<Notice status="info" isDismissible={false}>
									{__(
										'Exit animations require repeating behavior. "Animate Once" is disabled.',
										'designsetgo'
									)}
								</Notice>
							)}

							<ToggleControl
								label={__('Animate Once', 'designsetgo')}
								checked={dsgoAnimationOnce}
								onChange={(value) =>
									setAttributes({ dsgoAnimationOnce: value })
								}
								disabled={!!dsgoExitAnimation}
								help={
									dsgoExitAnimation
										? __(
												'Disabled when exit animation is set',
												'designsetgo'
											)
										: __(
												'Only animate the first time block enters viewport',
												'designsetgo'
											)
								}
								__nextHasNoMarginBottom
							/>
						</>
					)}

					{!dsgoEntranceAnimation && !dsgoExitAnimation && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'Please select at least one animation type.',
								'designsetgo'
							)}
						</Notice>
					)}
				</>
			)}
		</PanelBody>
	);
}

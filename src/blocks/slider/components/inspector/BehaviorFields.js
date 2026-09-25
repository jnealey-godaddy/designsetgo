/**
 * Slider — interaction behaviour fields.
 *
 * Loop, drag/swipe/free-mode, centering, and scroll-driven navigation.
 * Enabling scroll-driven mode forces autoplay/loop off and the effect back
 * to `slide` — those three attributes aren't otherwise reachable from this
 * mode, so the write happens here rather than being modeled as three
 * separate resets. Composed inside the Settings DsgoInspectorPanel in
 * SettingsPanel.js.
 *
 * Loop, Swipeable and Draggable are locked while scroll-driven mode is on,
 * so their hasValue() is false then too. Otherwise the ⋮ menu would offer
 * "Reset Loop" on a disabled toggle, and resetting it would switch loop back
 * on underneath a notice saying loop is disabled.
 */

import { __ } from '@wordpress/i18n';
import { Notice, RangeControl, ToggleControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

/**
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element} Interaction behaviour fields.
 */
export default function BehaviorFields({ attributes, setAttributes }) {
	const {
		loop,
		draggable,
		swipeable,
		freeMode,
		centeredSlides,
		scrollDriven,
		scrollDrivenSpeed,
	} = attributes;

	const handleScrollDrivenChange = (value) => {
		const updates = { scrollDriven: value };
		if (value) {
			updates.autoplay = false;
			updates.loop = false;
			updates.effect = 'slide';
		}
		setAttributes(updates);
	};

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Scroll-Driven Horizontal', 'designsetgo')}
				hasValue={() => scrollDriven !== false}
				onDeselect={() => setAttributes({ scrollDriven: false })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Scroll-Driven Horizontal', 'designsetgo')}
					checked={scrollDriven}
					onChange={handleScrollDrivenChange}
					help={
						scrollDriven
							? __(
									'Vertical scrolling drives horizontal slide navigation. Autoplay and loop are disabled.',
									'designsetgo'
								)
							: __(
									'Enable to scroll through slides horizontally as the user scrolls down the page',
									'designsetgo'
								)
					}
					__nextHasNoMarginBottom
				/>
				{scrollDriven && (
					<Notice status="info" isDismissible={false}>
						{__(
							'Scroll-driven mode pins the slider in the viewport and uses vertical scroll to navigate horizontally through slides. Arrows, dots, autoplay, and loop are disabled.',
							'designsetgo'
						)}
					</Notice>
				)}
			</DsgoInspectorPanel.Item>

			{scrollDriven && (
				<DsgoInspectorPanel.Item
					label={__('Scroll Speed', 'designsetgo')}
					hasValue={() => scrollDrivenSpeed !== 1}
					onDeselect={() => setAttributes({ scrollDrivenSpeed: 1 })}
					isShownByDefault
				>
					<RangeControl
						label={__('Scroll Speed', 'designsetgo')}
						value={scrollDrivenSpeed}
						onChange={(value) =>
							setAttributes({ scrollDrivenSpeed: value })
						}
						min={0.5}
						max={3}
						step={0.5}
						help={__(
							'Controls how much scrolling is needed to traverse all slides. Higher = more scroll distance.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			<DsgoInspectorPanel.Item
				label={__('Loop', 'designsetgo')}
				hasValue={() => !scrollDriven && loop !== true}
				onDeselect={() => setAttributes({ loop: true })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Loop', 'designsetgo')}
					checked={loop}
					onChange={(value) => setAttributes({ loop: value })}
					help={
						loop
							? __('Infinite loop navigation', 'designsetgo')
							: __('Stop at first/last slide', 'designsetgo')
					}
					disabled={scrollDriven}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Swipeable (Touch)', 'designsetgo')}
				hasValue={() => !scrollDriven && swipeable !== true}
				onDeselect={() => setAttributes({ swipeable: true })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Swipeable (Touch)', 'designsetgo')}
					checked={swipeable}
					onChange={(value) => setAttributes({ swipeable: value })}
					disabled={scrollDriven}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Draggable (Mouse)', 'designsetgo')}
				hasValue={() => !scrollDriven && draggable !== true}
				onDeselect={() => setAttributes({ draggable: true })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Draggable (Mouse)', 'designsetgo')}
					checked={draggable}
					onChange={(value) => setAttributes({ draggable: value })}
					disabled={scrollDriven}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Free Mode', 'designsetgo')}
				hasValue={() => freeMode !== false}
				onDeselect={() => setAttributes({ freeMode: false })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Free Mode', 'designsetgo')}
					checked={freeMode}
					onChange={(value) => setAttributes({ freeMode: value })}
					help={__(
						'Smooth scrolling without snap points',
						'designsetgo'
					)}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Centered Slides', 'designsetgo')}
				hasValue={() => centeredSlides !== false}
				onDeselect={() => setAttributes({ centeredSlides: false })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Centered Slides', 'designsetgo')}
					checked={centeredSlides}
					onChange={(value) =>
						setAttributes({ centeredSlides: value })
					}
					help={__('Active slide centered in view', 'designsetgo')}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}

/**
 * Slider — autoplay fields.
 *
 * Composed inside the Settings DsgoInspectorPanel in SettingsPanel.js.
 */

import { __ } from '@wordpress/i18n';
import { RangeControl, ToggleControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

/**
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element} Autoplay fields.
 */
export default function AutoplayFields({ attributes, setAttributes }) {
	const { autoplay, autoplayInterval, pauseOnHover, pauseOnInteraction } =
		attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Enable Auto-play', 'designsetgo')}
				hasValue={() => autoplay !== false}
				onDeselect={() => setAttributes({ autoplay: false })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Enable Auto-play', 'designsetgo')}
					checked={autoplay}
					onChange={(value) => setAttributes({ autoplay: value })}
					help={
						autoplay
							? __('Slides advance automatically', 'designsetgo')
							: __('Manual navigation only', 'designsetgo')
					}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{autoplay && (
				<>
					<DsgoInspectorPanel.Item
						label={__('Auto-play Interval', 'designsetgo')}
						hasValue={() => autoplayInterval !== 3000}
						onDeselect={() =>
							setAttributes({ autoplayInterval: 3000 })
						}
						isShownByDefault
					>
						<RangeControl
							label={__('Auto-play Interval (ms)', 'designsetgo')}
							value={autoplayInterval}
							onChange={(value) =>
								setAttributes({ autoplayInterval: value })
							}
							min={1000}
							max={10000}
							step={500}
							help={__('Time between slides', 'designsetgo')}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Pause on Hover', 'designsetgo')}
						hasValue={() => pauseOnHover !== true}
						onDeselect={() => setAttributes({ pauseOnHover: true })}
						isShownByDefault
					>
						<ToggleControl
							label={__('Pause on Hover', 'designsetgo')}
							checked={pauseOnHover}
							onChange={(value) =>
								setAttributes({ pauseOnHover: value })
							}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Pause on Interaction', 'designsetgo')}
						hasValue={() => pauseOnInteraction !== true}
						onDeselect={() =>
							setAttributes({ pauseOnInteraction: true })
						}
						isShownByDefault
					>
						<ToggleControl
							label={__('Pause on Interaction', 'designsetgo')}
							checked={pauseOnInteraction}
							onChange={(value) =>
								setAttributes({ pauseOnInteraction: value })
							}
							help={__(
								'Pause after user clicks, swipes, or drags',
								'designsetgo'
							)}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
				</>
			)}
		</>
	);
}

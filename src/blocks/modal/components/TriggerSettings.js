/**
 * Auto-Trigger Settings Panel Component
 *
 * Renders DsgoInspectorPanel.Item entries for the modal's auto-trigger
 * attributes, meant to be composed inside the Settings panel in
 * modal/edit.js.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	RangeControl,
	ToggleControl,
	Notice,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

export default function TriggerSettings({ attributes, setAttributes }) {
	const {
		autoTriggerType,
		autoTriggerDelay,
		autoTriggerFrequency,
		cookieDuration,
		exitIntentSensitivity,
		exitIntentMinTime,
		exitIntentExcludeMobile,
		scrollDepth,
		scrollDirection,
		timeOnPage,
	} = attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Auto Trigger Type', 'designsetgo')}
				hasValue={() => autoTriggerType !== 'none'}
				onDeselect={() => setAttributes({ autoTriggerType: 'none' })}
				isShownByDefault={false}
			>
				<SelectControl
					label={__('Trigger Type', 'designsetgo')}
					value={autoTriggerType}
					options={[
						{ label: __('None', 'designsetgo'), value: 'none' },
						{
							label: __('Page Load', 'designsetgo'),
							value: 'pageLoad',
						},
						{
							label: __('Exit Intent', 'designsetgo'),
							value: 'exitIntent',
						},
						{
							label: __('Scroll Depth', 'designsetgo'),
							value: 'scroll',
						},
						{
							label: __('Time on Page', 'designsetgo'),
							value: 'time',
						},
					]}
					onChange={(value) =>
						setAttributes({ autoTriggerType: value })
					}
					help={__(
						'Automatically open the modal based on user behavior.',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				{autoTriggerType !== 'none' && (
					<Notice status="info" isDismissible={false}>
						{__(
							'Auto-triggers are disabled in the editor. They will work on the frontend.',
							'designsetgo'
						)}
					</Notice>
				)}
			</DsgoInspectorPanel.Item>

			{autoTriggerType !== 'none' && (
				<DsgoInspectorPanel.Item
					label={__('Trigger Frequency', 'designsetgo')}
					hasValue={() => autoTriggerFrequency !== 'always'}
					onDeselect={() =>
						setAttributes({ autoTriggerFrequency: 'always' })
					}
					isShownByDefault={false}
				>
					<SelectControl
						label={__('Frequency', 'designsetgo')}
						value={autoTriggerFrequency}
						options={[
							{
								label: __('Every Visit', 'designsetgo'),
								value: 'always',
							},
							{
								label: __('Once per Session', 'designsetgo'),
								value: 'session',
							},
							{
								label: __('Once per User', 'designsetgo'),
								value: 'once',
							},
						]}
						onChange={(value) =>
							setAttributes({ autoTriggerFrequency: value })
						}
						help={__(
							'How often the modal should automatically open.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{autoTriggerType !== 'none' && autoTriggerFrequency === 'once' && (
				<DsgoInspectorPanel.Item
					label={__('Cookie Duration (days)', 'designsetgo')}
					hasValue={() => cookieDuration !== 7}
					onDeselect={() => setAttributes({ cookieDuration: 7 })}
					isShownByDefault={false}
				>
					<RangeControl
						label={__('Cookie Duration (days)', 'designsetgo')}
						value={cookieDuration}
						onChange={(value) =>
							setAttributes({ cookieDuration: value })
						}
						min={1}
						max={365}
						help={__(
							'How long to remember that the user has seen this modal.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{autoTriggerType === 'pageLoad' && (
				<DsgoInspectorPanel.Item
					label={__('Page Load Delay (s)', 'designsetgo')}
					hasValue={() => autoTriggerDelay !== 0}
					onDeselect={() => setAttributes({ autoTriggerDelay: 0 })}
					isShownByDefault={false}
				>
					<RangeControl
						label={__('Delay (seconds)', 'designsetgo')}
						value={autoTriggerDelay}
						onChange={(value) =>
							setAttributes({ autoTriggerDelay: value })
						}
						min={0}
						max={300}
						step={1}
						help={__(
							'Wait time before opening the modal after page loads.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{autoTriggerType === 'exitIntent' && (
				<DsgoInspectorPanel.Item
					label={__('Exit Intent Sensitivity', 'designsetgo')}
					hasValue={() => exitIntentSensitivity !== 'medium'}
					onDeselect={() =>
						setAttributes({ exitIntentSensitivity: 'medium' })
					}
					isShownByDefault={false}
				>
					<SelectControl
						label={__('Sensitivity', 'designsetgo')}
						value={exitIntentSensitivity}
						options={[
							{
								label: __('Low', 'designsetgo'),
								value: 'low',
							},
							{
								label: __('Medium', 'designsetgo'),
								value: 'medium',
							},
							{
								label: __('High', 'designsetgo'),
								value: 'high',
							},
						]}
						onChange={(value) =>
							setAttributes({
								exitIntentSensitivity: value,
							})
						}
						help={__(
							'How close to the top edge triggers exit intent.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{autoTriggerType === 'exitIntent' && (
				<DsgoInspectorPanel.Item
					label={__('Exit Intent Minimum Time (s)', 'designsetgo')}
					hasValue={() => exitIntentMinTime !== 5}
					onDeselect={() => setAttributes({ exitIntentMinTime: 5 })}
					isShownByDefault={false}
				>
					<RangeControl
						label={__('Minimum Time (seconds)', 'designsetgo')}
						value={exitIntentMinTime}
						onChange={(value) =>
							setAttributes({ exitIntentMinTime: value })
						}
						min={0}
						max={300}
						help={__(
							'Minimum time on page before exit intent can trigger.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{autoTriggerType === 'exitIntent' && (
				<DsgoInspectorPanel.Item
					label={__('Exclude Mobile Devices', 'designsetgo')}
					hasValue={() => exitIntentExcludeMobile !== true}
					onDeselect={() =>
						setAttributes({ exitIntentExcludeMobile: true })
					}
					isShownByDefault={false}
				>
					<ToggleControl
						label={__('Exclude Mobile Devices', 'designsetgo')}
						checked={exitIntentExcludeMobile}
						onChange={(value) =>
							setAttributes({
								exitIntentExcludeMobile: value,
							})
						}
						help={__(
							"Don't trigger exit intent on mobile devices.",
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{autoTriggerType === 'scroll' && (
				<DsgoInspectorPanel.Item
					label={__('Scroll Depth (%)', 'designsetgo')}
					hasValue={() => scrollDepth !== 50}
					onDeselect={() => setAttributes({ scrollDepth: 50 })}
					isShownByDefault={false}
				>
					<RangeControl
						label={__('Scroll Depth (%)', 'designsetgo')}
						value={scrollDepth}
						onChange={(value) =>
							setAttributes({ scrollDepth: value })
						}
						min={0}
						max={100}
						help={__(
							'Percentage of page scrolled before modal opens.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{autoTriggerType === 'scroll' && (
				<DsgoInspectorPanel.Item
					label={__('Scroll Direction', 'designsetgo')}
					hasValue={() => scrollDirection !== 'down'}
					onDeselect={() =>
						setAttributes({ scrollDirection: 'down' })
					}
					isShownByDefault={false}
				>
					<SelectControl
						label={__('Scroll Direction', 'designsetgo')}
						value={scrollDirection}
						options={[
							{
								label: __('Down Only', 'designsetgo'),
								value: 'down',
							},
							{
								label: __('Up or Down', 'designsetgo'),
								value: 'both',
							},
						]}
						onChange={(value) =>
							setAttributes({ scrollDirection: value })
						}
						help={__(
							'Trigger only when scrolling down or in any direction.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{autoTriggerType === 'time' && (
				<DsgoInspectorPanel.Item
					label={__('Time on Page (s)', 'designsetgo')}
					hasValue={() => timeOnPage !== 30}
					onDeselect={() => setAttributes({ timeOnPage: 30 })}
					isShownByDefault={false}
				>
					<RangeControl
						label={__('Time on Page (seconds)', 'designsetgo')}
						value={timeOnPage}
						onChange={(value) =>
							setAttributes({ timeOnPage: value })
						}
						min={0}
						max={300}
						help={__(
							'Seconds on page before modal opens.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}
		</>
	);
}

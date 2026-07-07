/**
 * Close Button Settings Panel Component
 *
 * Renders DsgoInspectorPanel.Item entries for the modal's close-button
 * attributes, meant to be composed inside the Settings panel in
 * modal/edit.js.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import {
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

export default function CloseButtonSettings({ attributes, setAttributes }) {
	const {
		showCloseButton,
		closeButtonPosition,
		closeButtonSize,
		closeButtonLabel,
	} = attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Show Close Button', 'designsetgo')}
				hasValue={() => showCloseButton !== true}
				onDeselect={() => setAttributes({ showCloseButton: true })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Show Close Button', 'designsetgo')}
					checked={showCloseButton}
					onChange={(value) =>
						setAttributes({ showCloseButton: value })
					}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{showCloseButton && (
				<DsgoInspectorPanel.Item
					label={__('Close Button Position', 'designsetgo')}
					hasValue={() => closeButtonPosition !== 'inside-top-right'}
					onDeselect={() =>
						setAttributes({
							closeButtonPosition: 'inside-top-right',
						})
					}
					isShownByDefault
				>
					<SelectControl
						label={__('Position', 'designsetgo')}
						value={closeButtonPosition}
						onChange={(value) =>
							setAttributes({ closeButtonPosition: value })
						}
						options={[
							{
								label: __('Top Right', 'designsetgo'),
								value: 'top-right',
							},
							{
								label: __('Top Left', 'designsetgo'),
								value: 'top-left',
							},
							{
								label: __('Inside Top Right', 'designsetgo'),
								value: 'inside-top-right',
							},
							{
								label: __('Inside Top Left', 'designsetgo'),
								value: 'inside-top-left',
							},
						]}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{showCloseButton && (
				<DsgoInspectorPanel.Item
					label={__('Close Button Size (px)', 'designsetgo')}
					hasValue={() => closeButtonSize !== 24}
					onDeselect={() => setAttributes({ closeButtonSize: 24 })}
					isShownByDefault
				>
					<RangeControl
						label={__('Button Size (px)', 'designsetgo')}
						value={closeButtonSize}
						onChange={(value) =>
							setAttributes({ closeButtonSize: value })
						}
						min={16}
						max={48}
						step={2}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{showCloseButton && (
				<DsgoInspectorPanel.Item
					label={__('Close Button Label', 'designsetgo')}
					hasValue={() => closeButtonLabel !== ''}
					onDeselect={() => setAttributes({ closeButtonLabel: '' })}
					isShownByDefault
				>
					<TextControl
						label={__('Close Button Label', 'designsetgo')}
						value={closeButtonLabel}
						onChange={(value) =>
							setAttributes({ closeButtonLabel: value })
						}
						help={__(
							'Accessible label for the close button (aria-label). Defaults to "Close modal" when empty.',
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

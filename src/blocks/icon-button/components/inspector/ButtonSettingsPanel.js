/**
 * Icon Button - Button Settings Panel Component
 *
 * Renders DsgoInspectorPanel.Item entries for icon-button icon,
 * animation, and modal-close attributes. Meant to be composed inside
 * the Settings DsgoInspectorPanel in icon-button/edit.js.
 *
 * @since 1.0.0
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	SelectControl,
	RangeControl,
	ToggleControl,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';
import { IconPicker } from '../../../icon/components/IconPicker';

const ANIMATION_LABELS = {
	none: __('None', 'designsetgo'),
	'fill-diagonal': __('Fill Diagonal', 'designsetgo'),
	'zoom-in': __('Zoom In', 'designsetgo'),
	'slide-left': __('Slide Left', 'designsetgo'),
	'slide-right': __('Slide Right', 'designsetgo'),
	'slide-down': __('Slide Down', 'designsetgo'),
	'slide-up': __('Slide Up', 'designsetgo'),
	'border-pulse': __('Border Pulse', 'designsetgo'),
	'border-glow': __('Border Glow', 'designsetgo'),
	lift: __('Lift', 'designsetgo'),
	shrink: __('Shrink', 'designsetgo'),
};

export const ButtonSettingsPanel = ({
	icon,
	iconPosition,
	iconSize,
	iconGap,
	hoverAnimation,
	adminDefaultHover,
	modalCloseId,
	isInsideModal,
	setAttributes,
}) => {
	const adminDefault = adminDefaultHover || 'none';
	const defaultLabel =
		adminDefault !== 'none'
			? sprintf(
					/* translators: %s: animation name */
					__('Default (%s)', 'designsetgo'),
					ANIMATION_LABELS[adminDefault] || adminDefault
				)
			: __('Default (None)', 'designsetgo');

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Hover Animation', 'designsetgo')}
				hasValue={() => hoverAnimation !== 'none'}
				onDeselect={() => setAttributes({ hoverAnimation: 'none' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Hover Animation', 'designsetgo')}
					value={hoverAnimation}
					options={[
						{
							label: defaultLabel,
							value: 'none',
						},
						{
							label: __('None (No Animation)', 'designsetgo'),
							value: 'explicit-none',
						},
						{
							label: __('Fill Diagonal', 'designsetgo'),
							value: 'fill-diagonal',
						},
						{
							label: __('Zoom In', 'designsetgo'),
							value: 'zoom-in',
						},
						{
							label: __('Slide Left', 'designsetgo'),
							value: 'slide-left',
						},
						{
							label: __('Slide Right', 'designsetgo'),
							value: 'slide-right',
						},
						{
							label: __('Slide Down', 'designsetgo'),
							value: 'slide-down',
						},
						{
							label: __('Slide Up', 'designsetgo'),
							value: 'slide-up',
						},
						{
							label: __('Border Pulse', 'designsetgo'),
							value: 'border-pulse',
						},
						{
							label: __('Border Glow', 'designsetgo'),
							value: 'border-glow',
						},
						{
							label: __('Lift', 'designsetgo'),
							value: 'lift',
						},
						{
							label: __('Shrink', 'designsetgo'),
							value: 'shrink',
						},
					]}
					onChange={(value) =>
						setAttributes({
							hoverAnimation: value,
						})
					}
					help={__(
						'Choose a hover animation. "Default" uses the site-wide setting from Settings > Animations.',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Icon Position', 'designsetgo')}
				hasValue={() => iconPosition !== 'start'}
				onDeselect={() => setAttributes({ iconPosition: 'start' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Icon Position', 'designsetgo')}
					value={iconPosition}
					options={[
						{ label: __('Start', 'designsetgo'), value: 'start' },
						{ label: __('End', 'designsetgo'), value: 'end' },
						{ label: __('None', 'designsetgo'), value: 'none' },
					]}
					onChange={(value) => setAttributes({ iconPosition: value })}
					help={__(
						'Position of icon relative to text',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{iconPosition !== 'none' && (
				<DsgoInspectorPanel.Item
					label={__('Icon', 'designsetgo')}
					hasValue={() => icon !== 'lightbulb'}
					onDeselect={() => setAttributes({ icon: 'lightbulb' })}
					isShownByDefault
				>
					<IconPicker
						value={icon}
						onChange={(value) => setAttributes({ icon: value })}
					/>
				</DsgoInspectorPanel.Item>
			)}

			{iconPosition !== 'none' && (
				<DsgoInspectorPanel.Item
					label={__('Icon Size', 'designsetgo')}
					hasValue={() => iconSize !== 20}
					onDeselect={() => setAttributes({ iconSize: 20 })}
					isShownByDefault
				>
					<RangeControl
						label={__('Icon Size', 'designsetgo')}
						value={iconSize}
						onChange={(value) => setAttributes({ iconSize: value })}
						min={12}
						max={48}
						help={__('Icon size in pixels', 'designsetgo')}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{iconPosition !== 'none' && (
				<DsgoInspectorPanel.Item
					label={__('Icon Gap', 'designsetgo')}
					hasValue={() => iconGap !== '8px'}
					onDeselect={() => setAttributes({ iconGap: '8px' })}
					isShownByDefault
				>
					<UnitControl
						label={__('Icon Gap', 'designsetgo')}
						value={iconGap}
						onChange={(value) => setAttributes({ iconGap: value })}
						units={[
							{ value: 'px', label: 'px' },
							{ value: 'em', label: 'em' },
							{ value: 'rem', label: 'rem' },
						]}
						help={__('Space between icon and text', 'designsetgo')}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			<DsgoInspectorPanel.Item
				label={__('Close modal on click', 'designsetgo')}
				hasValue={() => !!modalCloseId}
				onDeselect={() => setAttributes({ modalCloseId: '' })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Close modal on click', 'designsetgo')}
					checked={!!modalCloseId}
					onChange={(value) =>
						setAttributes({
							modalCloseId: value ? 'true' : '',
						})
					}
					help={
						isInsideModal
							? __(
									'Close the parent modal when this button is clicked',
									'designsetgo'
								)
							: __(
									'Close a modal when this button is clicked (enter modal ID below)',
									'designsetgo'
								)
					}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{modalCloseId && !isInsideModal && (
				<DsgoInspectorPanel.Item
					label={__('Modal ID', 'designsetgo')}
					hasValue={() => !!modalCloseId && modalCloseId !== 'true'}
					onDeselect={() => setAttributes({ modalCloseId: 'true' })}
					isShownByDefault
				>
					<TextControl
						label={__('Modal ID', 'designsetgo')}
						value={modalCloseId === 'true' ? '' : modalCloseId}
						onChange={(value) =>
							setAttributes({
								modalCloseId: value || 'true',
							})
						}
						placeholder={__('Enter modal ID', 'designsetgo')}
						help={__(
							'Enter the ID of the modal to close',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}
		</>
	);
};

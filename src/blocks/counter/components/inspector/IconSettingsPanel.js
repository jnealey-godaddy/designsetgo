/**
 * Counter Block - Icon Settings Panel Component
 *
 * Renders DsgoInspectorPanel.Item entries for icon show/hide, type, and
 * position. Meant to be composed inside the Settings DsgoInspectorPanel
 * in counter/edit.js.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { ToggleControl, SelectControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

export const IconSettingsPanel = ({
	showIcon,
	icon,
	iconPosition,
	setAttributes,
}) => {
	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Show Icon', 'designsetgo')}
				hasValue={() => showIcon !== false}
				onDeselect={() => setAttributes({ showIcon: false })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Show Icon', 'designsetgo')}
					checked={showIcon}
					onChange={(value) => setAttributes({ showIcon: value })}
					help={
						showIcon
							? __('Icon is displayed', 'designsetgo')
							: __('No icon displayed', 'designsetgo')
					}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{showIcon && (
				<DsgoInspectorPanel.Item
					label={__('Icon', 'designsetgo')}
					hasValue={() => icon !== 'star'}
					onDeselect={() => setAttributes({ icon: 'star' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Icon', 'designsetgo')}
						value={icon}
						options={[
							{ label: __('Star', 'designsetgo'), value: 'star' },
							{
								label: __('Trophy', 'designsetgo'),
								value: 'trophy',
							},
							{
								label: __('Heart', 'designsetgo'),
								value: 'heart',
							},
							{
								label: __('Check', 'designsetgo'),
								value: 'check',
							},
							{
								label: __('Dollar', 'designsetgo'),
								value: 'dollar',
							},
							{
								label: __('Users', 'designsetgo'),
								value: 'users',
							},
							{
								label: __('Chart', 'designsetgo'),
								value: 'chart',
							},
							{
								label: __('Rocket', 'designsetgo'),
								value: 'rocket',
							},
						]}
						onChange={(value) => setAttributes({ icon: value })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{showIcon && (
				<DsgoInspectorPanel.Item
					label={__('Icon Position', 'designsetgo')}
					hasValue={() => iconPosition !== 'top'}
					onDeselect={() => setAttributes({ iconPosition: 'top' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Icon Position', 'designsetgo')}
						value={iconPosition}
						options={[
							{ label: __('Top', 'designsetgo'), value: 'top' },
							{ label: __('Left', 'designsetgo'), value: 'left' },
							{
								label: __('Right', 'designsetgo'),
								value: 'right',
							},
						]}
						onChange={(value) =>
							setAttributes({ iconPosition: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}
		</>
	);
};

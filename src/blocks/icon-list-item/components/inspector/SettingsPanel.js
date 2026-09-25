/**
 * Icon List Item - Settings Panel
 *
 * Icon choice and the optional whole-item link.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	TextControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';
import { IconPicker } from '../../../icon/components/IconPicker';

const REL_OPTIONS = [
	{ label: __('None', 'designsetgo'), value: '' },
	{ label: 'noopener', value: 'noopener' },
	{ label: 'noreferrer', value: 'noreferrer' },
	{ label: 'noopener noreferrer', value: 'noopener noreferrer' },
	{ label: 'nofollow', value: 'nofollow' },
];

/**
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @param {string}   props.clientId      - Block client ID
 * @return {JSX.Element} Settings panel
 */
export const SettingsPanel = ({ attributes, setAttributes, clientId }) => {
	const { icon, linkUrl, linkTarget, linkRel } = attributes;

	return (
		<DsgoInspectorPanel
			title={__('Settings', 'designsetgo')}
			panelName="settings"
			panelId={clientId}
			resetAll={() =>
				setAttributes({
					icon: 'star',
					linkUrl: '',
					linkTarget: '_self',
					linkRel: '',
				})
			}
		>
			<DsgoInspectorPanel.Item
				label={__('Icon', 'designsetgo')}
				hasValue={() => icon !== 'star'}
				onDeselect={() => setAttributes({ icon: 'star' })}
				isShownByDefault
			>
				<IconPicker
					value={icon}
					onChange={(newIcon) => setAttributes({ icon: newIcon })}
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Link URL', 'designsetgo')}
				hasValue={() => linkUrl !== ''}
				onDeselect={() =>
					setAttributes({
						linkUrl: '',
						linkTarget: '_self',
						linkRel: '',
					})
				}
				isShownByDefault
			>
				<TextControl
					label={__('Link URL', 'designsetgo')}
					value={linkUrl}
					onChange={(value) => setAttributes({ linkUrl: value })}
					placeholder="https://"
					help={__(
						'Make the entire list item clickable',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{linkUrl && (
				<DsgoInspectorPanel.Item
					label={__('Open in new tab', 'designsetgo')}
					hasValue={() => linkTarget === '_blank'}
					onDeselect={() =>
						setAttributes({ linkTarget: '_self', linkRel: '' })
					}
					isShownByDefault
				>
					<ToggleControl
						label={__('Open in new tab', 'designsetgo')}
						checked={linkTarget === '_blank'}
						onChange={(value) =>
							setAttributes({
								linkTarget: value ? '_blank' : '_self',
								linkRel: value ? 'noopener noreferrer' : '',
							})
						}
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{linkUrl && (
				<DsgoInspectorPanel.Item
					label={__('Link Rel', 'designsetgo')}
					hasValue={() => linkRel !== ''}
					onDeselect={() => setAttributes({ linkRel: '' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Link Rel', 'designsetgo')}
						value={linkRel}
						options={REL_OPTIONS}
						onChange={(value) => setAttributes({ linkRel: value })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}
		</DsgoInspectorPanel>
	);
};

/**
 * Star Rating — Style panel.
 *
 * Icon shape, weight, size and spacing. Colour is not here: it stays in
 * WordPress's own Color panel, per the inspector IA.
 *
 * @since 2.8.0
 */

import {
	RangeControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { DsgoInspectorPanel } from '../../../components/shared';
import { IconPicker } from '../../icon/components/IconPicker';
import { DEFAULTS } from '../utils/defaults';

/**
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @param {string}   props.clientId      Block client id.
 * @return {JSX.Element} Style panel.
 */
export default function StylePanel({ attributes, setAttributes, clientId }) {
	const { icon, iconStyle, iconSize, iconGap } = attributes;

	return (
		<DsgoInspectorPanel
			title={__('Style', 'designsetgo')}
			panelName="style"
			panelId={clientId}
			resetAll={() =>
				setAttributes({
					icon: DEFAULTS.icon,
					iconStyle: DEFAULTS.iconStyle,
					iconSize: DEFAULTS.iconSize,
					iconGap: DEFAULTS.iconGap,
				})
			}
		>
			<DsgoInspectorPanel.Item
				label={__('Icon', 'designsetgo')}
				hasValue={() => icon !== DEFAULTS.icon}
				onDeselect={() => setAttributes({ icon: DEFAULTS.icon })}
				isShownByDefault
			>
				<IconPicker
					value={icon}
					onChange={(value) => setAttributes({ icon: value })}
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Icon style', 'designsetgo')}
				hasValue={() => iconStyle !== DEFAULTS.iconStyle}
				onDeselect={() =>
					setAttributes({ iconStyle: DEFAULTS.iconStyle })
				}
				isShownByDefault
			>
				<ToggleGroupControl
					label={__('Icon style', 'designsetgo')}
					value={iconStyle}
					onChange={(value) => setAttributes({ iconStyle: value })}
					isBlock
					__nextHasNoMarginBottom
				>
					<ToggleGroupControlOption
						value="filled"
						label={__('Filled', 'designsetgo')}
					/>
					<ToggleGroupControlOption
						value="outlined"
						label={__('Outlined', 'designsetgo')}
					/>
				</ToggleGroupControl>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Icon size', 'designsetgo')}
				hasValue={() => iconSize !== DEFAULTS.iconSize}
				onDeselect={() =>
					setAttributes({ iconSize: DEFAULTS.iconSize })
				}
				isShownByDefault
			>
				<RangeControl
					label={__('Icon size', 'designsetgo')}
					value={iconSize}
					onChange={(value) =>
						setAttributes({
							iconSize:
								typeof value === 'number'
									? value
									: DEFAULTS.iconSize,
						})
					}
					min={12}
					max={96}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Icon gap', 'designsetgo')}
				hasValue={() => iconGap !== DEFAULTS.iconGap}
				onDeselect={() => setAttributes({ iconGap: DEFAULTS.iconGap })}
				isShownByDefault
			>
				<RangeControl
					label={__('Icon gap', 'designsetgo')}
					value={iconGap}
					onChange={(value) =>
						setAttributes({
							iconGap:
								typeof value === 'number'
									? value
									: DEFAULTS.iconGap,
						})
					}
					min={0}
					max={24}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</DsgoInspectorPanel>
	);
}

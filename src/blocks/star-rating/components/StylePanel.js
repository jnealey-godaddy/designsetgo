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
import { __, sprintf } from '@wordpress/i18n';
import { DsgoInspectorPanel } from '../../../components/shared';
import { useIconDefaults } from '../../../hooks';
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

	// iconStyle / iconSize stay unset until the author overrides them, and
	// then inherit the theme tokens. The controls show the inherited value so
	// "unset" never looks like "nothing".
	const iconDefaults = useIconDefaults({
		sizeKey: 'starRating',
		sizeFallback: 24,
	});

	return (
		<DsgoInspectorPanel
			title={__('Style', 'designsetgo')}
			panelName="style"
			panelId={clientId}
			resetAll={() =>
				setAttributes({
					icon: DEFAULTS.icon,
					iconStyle: undefined,
					iconSize: undefined,
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
				hasValue={() => iconStyle !== undefined}
				onDeselect={() => setAttributes({ iconStyle: undefined })}
				isShownByDefault
			>
				<ToggleGroupControl
					label={__('Icon style', 'designsetgo')}
					value={iconStyle || iconDefaults.style}
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
				hasValue={() => typeof iconSize === 'number'}
				onDeselect={() => setAttributes({ iconSize: undefined })}
				isShownByDefault
			>
				<RangeControl
					label={__('Icon size', 'designsetgo')}
					value={iconSize}
					onChange={(value) =>
						setAttributes({
							iconSize:
								typeof value === 'number' ? value : undefined,
						})
					}
					min={12}
					max={96}
					allowReset
					placeholder={iconDefaults.size}
					help={
						typeof iconSize !== 'number' &&
						sprintf(
							/* translators: %d: inherited icon size in pixels. */
							__(
								'Inheriting theme default (%dpx).',
								'designsetgo'
							),
							iconDefaults.size
						)
					}
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

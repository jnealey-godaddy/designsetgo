/**
 * Icon Block - Edit Component
 *
 * Display inline SVG icons with customizable styling.
 * No external dependencies - works everywhere!
 *
 * @since 1.0.0
 */

import classnames from 'classnames';
import {
	useBlockProps,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseColorProps as useColorProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseBorderProps as useBorderProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetSpacingClassesAndStyles as getSpacingClassesAndStyles,
} from '@wordpress/block-editor';
import {
	RangeControl,
	ToggleControl,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import DsgoJustificationToolbar from '../../components/shared/DsgoJustificationToolbar';
import { getJustificationClass } from '../../utils/justification';
import { __, sprintf } from '@wordpress/i18n';
import { getIcon } from './utils/svg-icons';
import { IconPicker } from './components/IconPicker';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { useIconDefaults } from '../../hooks';

/**
 * Edit component
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @param {Object}   props.context       - Block context from parent
 * @param {string}   props.clientId      - Block client ID
 * @return {JSX.Element} Edit component
 */
export default function IconEdit({
	attributes,
	setAttributes,
	context,
	clientId,
}) {
	const {
		icon,
		iconStyle,
		strokeWidth,
		iconSize,
		rotation,
		linkUrl,
		linkTarget,
		ariaLabel,
		isDecorative,
		justification,
		style,
	} = attributes;

	// Theme-level icon defaults inherited when size/style are left unset.
	const iconDefaults = useIconDefaults();
	const effectiveStyle = iconStyle || iconDefaults.style;
	const effectiveSize =
		typeof iconSize === 'number' ? iconSize : iconDefaults.size;

	// Get hover icon background from parent container context
	const parentHoverIconBg = context['designsetgo/hoverIconBackgroundColor'];

	const blockProps = useBlockProps({
		className: `dsgo-icon dsgo-justify ${getJustificationClass(
			justification
		)}`.trim(),
		...(parentHoverIconBg && {
			style: {
				'--dsgo-parent-hover-icon-bg':
					convertColorToCSSVar(parentHoverIconBg),
			},
		}),
	});

	// block.json skip-serializes color, border, and spacing.padding off the
	// wrapper, so useBlockProps() above no longer carries them — there is
	// nothing to neutralise. The visible icon is the inner .dsgo-icon__wrapper
	// element, so re-derive the same classes/styles with the official
	// block-support helpers (identical to how core/button applies them to its
	// inner link) and apply them there instead, mirroring render.php's
	// designsetgo_route_visual_supports() so the editor canvas matches the
	// frontend.
	const colorProps = useColorProps(attributes);
	const borderProps = useBorderProps(attributes);
	const paddingProps = getSpacingClassesAndStyles({
		style: { spacing: { padding: style?.spacing?.padding } },
	});

	// Icon wrapper styles. Preview uses the effective (possibly inherited) size.
	const iconWrapperStyle = {
		width: `${effectiveSize}px`,
		height: `${effectiveSize}px`,
		display: 'inline-flex',
		alignItems: 'center',
		justifyContent: 'center',
		transform: rotation !== 0 ? `rotate(${rotation}deg)` : undefined,
		// borderRadius inherits from parent for shape variants, unless an
		// explicit border radius is set via the native Border support.
		borderRadius: 'inherit',
		...borderProps.style,
		...colorProps.style,
		...paddingProps.style,
	};

	const iconWrapperClassName = classnames(
		'dsgo-icon__wrapper',
		colorProps.className,
		borderProps.className
	);

	return (
		<>
			<DsgoJustificationToolbar
				value={justification}
				onChange={(value) => setAttributes({ justification: value })}
			/>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							icon: 'star',
							iconStyle: undefined,
							strokeWidth: 1.5,
							iconSize: undefined,
							rotation: 0,
							linkUrl: '',
							linkTarget: '_self',
							linkRel: '',
							ariaLabel: '',
							isDecorative: false,
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
							onChange={(value) => setAttributes({ icon: value })}
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Style', 'designsetgo')}
						hasValue={() => typeof iconStyle === 'string'}
						onDeselect={() =>
							setAttributes({ iconStyle: undefined })
						}
						isShownByDefault
					>
						<ToggleGroupControl
							label={__('Style', 'designsetgo')}
							value={effectiveStyle}
							onChange={(value) =>
								setAttributes({ iconStyle: value })
							}
							help={
								!iconStyle &&
								sprintf(
									/* translators: %s: inherited icon style (Filled or Outlined). */
									__(
										'Inheriting theme default (%s).',
										'designsetgo'
									),
									iconDefaults.style === 'outlined'
										? __('Outlined', 'designsetgo')
										: __('Filled', 'designsetgo')
								)
							}
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

					{effectiveStyle === 'outlined' && (
						<DsgoInspectorPanel.Item
							label={__('Stroke Width', 'designsetgo')}
							hasValue={() => strokeWidth !== 1.5}
							onDeselect={() =>
								setAttributes({ strokeWidth: 1.5 })
							}
							isShownByDefault
						>
							<RangeControl
								label={__('Stroke Width', 'designsetgo')}
								value={strokeWidth}
								onChange={(value) =>
									setAttributes({ strokeWidth: value })
								}
								min={0.5}
								max={4}
								step={0.5}
								help={__(
									'Thinner strokes work better for detailed icons',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					<DsgoInspectorPanel.Item
						label={__('Icon Size', 'designsetgo')}
						hasValue={() => typeof iconSize === 'number'}
						onDeselect={() =>
							setAttributes({ iconSize: undefined })
						}
						isShownByDefault
					>
						<RangeControl
							label={__('Icon Size', 'designsetgo')}
							value={iconSize}
							onChange={(value) =>
								setAttributes({
									iconSize:
										typeof value === 'number'
											? value
											: undefined,
								})
							}
							min={16}
							max={200}
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
						label={__('Rotation', 'designsetgo')}
						hasValue={() => rotation !== 0}
						onDeselect={() => setAttributes({ rotation: 0 })}
						isShownByDefault
					>
						<ToggleGroupControl
							label={__('Rotation', 'designsetgo')}
							value={rotation}
							onChange={(value) =>
								setAttributes({ rotation: Number(value) })
							}
							isBlock
							__nextHasNoMarginBottom
						>
							<ToggleGroupControlOption value="0" label="0°" />
							<ToggleGroupControlOption value="90" label="90°" />
							<ToggleGroupControlOption
								value="180"
								label="180°"
							/>
							<ToggleGroupControlOption
								value="270"
								label="270°"
							/>
						</ToggleGroupControl>
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
							label={__('URL', 'designsetgo')}
							value={linkUrl}
							onChange={(value) =>
								setAttributes({ linkUrl: value })
							}
							placeholder="https://example.com"
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{linkUrl && (
						<DsgoInspectorPanel.Item
							label={__('Open in new tab', 'designsetgo')}
							hasValue={() => linkTarget === '_blank'}
							onDeselect={() =>
								setAttributes({
									linkTarget: '_self',
									linkRel: '',
								})
							}
							isShownByDefault
						>
							<ToggleControl
								label={__('Open in new tab', 'designsetgo')}
								checked={linkTarget === '_blank'}
								onChange={(value) =>
									setAttributes({
										linkTarget: value ? '_blank' : '_self',
										linkRel: value
											? 'noopener noreferrer'
											: '',
									})
								}
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					<DsgoInspectorPanel.Item
						label={__('Decorative icon', 'designsetgo')}
						hasValue={() => isDecorative !== false}
						onDeselect={() =>
							setAttributes({ isDecorative: false })
						}
						isShownByDefault
					>
						<ToggleControl
							label={__('Decorative icon', 'designsetgo')}
							checked={isDecorative}
							onChange={(value) =>
								setAttributes({ isDecorative: value })
							}
							help={__(
								'Enable if this icon is purely decorative and provides no information. Screen readers will ignore it.',
								'designsetgo'
							)}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{!isDecorative && (
						<DsgoInspectorPanel.Item
							label={__('Accessible label', 'designsetgo')}
							hasValue={() => ariaLabel !== ''}
							onDeselect={() => setAttributes({ ariaLabel: '' })}
							isShownByDefault
						>
							<TextControl
								label={__('Accessible label', 'designsetgo')}
								value={ariaLabel}
								onChange={(value) =>
									setAttributes({ ariaLabel: value })
								}
								placeholder={__(
									'Describe the icon for screen readers',
									'designsetgo'
								)}
								help={__(
									'Provide a brief description of what the icon represents (e.g., "Search", "Shopping cart", "Download").',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			<div {...blockProps}>
				<div className={iconWrapperClassName} style={iconWrapperStyle}>
					{getIcon(icon, effectiveStyle, strokeWidth)}
				</div>
			</div>
		</>
	);
}

/**
 * Icon Button Block - Edit Component
 *
 * Button with optional icon at start or end.
 * Link is managed via the inline toolbar, following the core Button block pattern.
 *
 * The block root is a plain block-level "justification wrapper" (`.dsgo-justify`)
 * that core's constrained layout caps at the content column. The visible button
 * (always a `div` in the editor, to preserve editability) shrink-wraps inside it.
 * Visual supports are re-derived with the hook variants of the block-support
 * helpers so the editor canvas matches the frontend save() output.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';
import {
	useBlockProps,
	BlockControls,
	InspectorControls,
	RichText,
	useSettings,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalLinkControl as LinkControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseBorderProps as useBorderProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseColorProps as useColorProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetShadowClassesAndStyles as getShadowClassesAndStyles,
	getTypographyClassesAndStyles,
} from '@wordpress/block-editor';
import { ToolbarButton, Popover, ToggleControl } from '@wordpress/components';
import clsx from 'clsx';
import { DsgoInspectorPanel } from '../../components/shared';
import DsgoJustificationToolbar from '../../components/shared/DsgoJustificationToolbar';
import { link as linkIcon } from '@wordpress/icons';
import { useSelect } from '@wordpress/data';
import { getIcon } from '../icon/utils/svg-icons';
import { ButtonSettingsPanel } from './components/inspector/ButtonSettingsPanel';
import { convertPaddingValue } from './utils/padding';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { hasExplicitString } from '../../utils/has-explicit-value';
import { useIconDefaults } from '../../hooks';
import { getJustificationClass } from '../../utils/justification';

/**
 * Icon Button Edit Component
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @param {Object}   props.context       - Block context from parent
 * @param {string}   props.clientId      - Block client ID
 * @param {boolean}  props.isSelected    - Whether the block is selected
 * @return {JSX.Element} Icon Button edit component
 */
export default function IconButtonEdit({
	attributes,
	setAttributes,
	context,
	clientId,
	isSelected,
}) {
	const {
		text,
		url,
		linkTarget,
		rel,
		icon,
		iconPosition,
		iconStyle,
		strokeWidth,
		iconSize,
		iconGap,
		justification,
		fullWidth,
		hoverAnimation,
		hoverBackgroundColor,
		hoverTextColor,
		style,
		modalCloseId,
	} = attributes;

	// Theme-level icon defaults inherited when size/style are left unset.
	const iconDefaults = useIconDefaults({
		sizeKey: 'iconButton',
		sizeFallback: 20,
	});
	const effectiveStyle = iconStyle || iconDefaults.style;
	const effectiveSize =
		typeof iconSize === 'number' ? iconSize : iconDefaults.size;

	// Check if button is inside a modal
	const isInsideModal = useSelect(
		(select) => {
			const { getBlockParents, getBlock } = select('core/block-editor');
			const parents = getBlockParents(clientId);
			return parents.some(
				(parentId) => getBlock(parentId)?.name === 'designsetgo/modal'
			);
		},
		[clientId]
	);

	// Link toolbar state (follows core Button block pattern)
	const ref = useRef();
	const richTextRef = useRef();
	const [isEditingURL, setIsEditingURL] = useState(false);
	const isURLSet = !!url;

	// Close link popover when block is deselected
	useEffect(() => {
		if (!isSelected) {
			setIsEditingURL(false);
		}
	}, [isSelected]);

	function startEditing(event) {
		event.preventDefault();
		setIsEditingURL(true);
	}

	function unlink() {
		setAttributes({ url: '', linkTarget: '_self', rel: '' });
		setIsEditingURL(false);
	}

	// Get hover button background from parent container context
	const parentHoverButtonBg =
		context['designsetgo/hoverButtonBackgroundColor'];

	// Get theme color palette and gradient settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// block.json skip-serializes border, color, shadow, and typography off the
	// wrapper, so useBlockProps() below no longer carries them — there is
	// nothing to neutralise. The visible button is the inner element, so
	// re-derive the same classes/styles with the official block-support
	// helpers (identical to how core/button applies them to its inner link)
	// and apply them there instead, mirroring save.js so the editor canvas
	// matches the frontend.
	const border = useBorderProps(attributes);
	const colors = useColorProps(attributes);
	const shadow = getShadowClassesAndStyles(attributes);
	const typography = getTypographyClassesAndStyles(attributes);

	// Extract padding - WordPress stores it in style.spacing.padding
	const paddingValue = style?.spacing?.padding;

	// Gap parity with save.js: omit entirely when no icon; inline only for an
	// explicit author gap, otherwise the stylesheet default owns it.
	const hasIcon = iconPosition !== 'none' && !!icon;
	const hasExplicitGap = hasExplicitString(iconGap);

	const buttonStyles = {
		...border.style,
		...colors.style,
		...shadow.style,
		...typography.style,
		...(hasIcon && hasExplicitGap && { gap: iconGap }),
		...(paddingValue && {
			paddingTop: convertPaddingValue(paddingValue.top),
			paddingRight: convertPaddingValue(paddingValue.right),
			paddingBottom: convertPaddingValue(paddingValue.bottom),
			paddingLeft: convertPaddingValue(paddingValue.left),
		}),
		...(hoverBackgroundColor && {
			'--dsgo-button-hover-bg':
				convertColorToCSSVar(hoverBackgroundColor),
		}),
		...(hoverTextColor && {
			'--dsgo-button-hover-color': convertColorToCSSVar(hoverTextColor),
		}),
		...(parentHoverButtonBg && {
			'--dsgo-parent-hover-button-bg':
				convertColorToCSSVar(parentHoverButtonBg),
		}),
	};

	// Calculate icon wrapper styles. Preview uses the effective (possibly
	// inherited) size so it always shows a size in the editor.
	// Layout comes from style.scss (.dsgo-icon-button__icon), which the editor
	// canvas loads too — only the size is set here, using the effective
	// (possibly inherited) value so the preview always shows a size.
	const iconWrapperStyles = {
		width: `${effectiveSize}px`,
		height: `${effectiveSize}px`,
	};

	// Read the site-wide default hover animation from theme.json custom settings.
	// This is set by the admin panel and injected via class-global-styles.php.
	const [themeDefaultHover] = useSettings(
		'custom.designsetgo.defaultIconButtonHover'
	);

	// Resolve the effective animation for editor preview
	// "none" = use admin default, "explicit-none" = no animation
	let effectiveAnimation = hoverAnimation;
	if (!hoverAnimation || hoverAnimation === 'none') {
		const adminDefault = themeDefaultHover || 'none';
		effectiveAnimation = adminDefault !== 'none' ? adminDefault : null;
	} else if (hoverAnimation === 'explicit-none') {
		effectiveAnimation = null;
	}

	const buttonClasses = clsx(
		'dsgo-icon-button',
		'wp-block-button',
		'wp-block-button__link',
		'wp-element-button',
		border.className,
		colors.className,
		shadow.className,
		typography.className,
		hasIcon && 'dsgo-icon-button--has-icon',
		fullWidth && 'dsgo-icon-button--full-width',
		effectiveAnimation &&
			effectiveAnimation !== 'none' &&
			`dsgo-icon-button--${effectiveAnimation}`,
		iconPosition === 'end' && 'dsgo-icon-button--icon-end'
	);

	const blockProps = useBlockProps({
		className: clsx('dsgo-justify', getJustificationClass(justification)),
	});

	return (
		<>
			<DsgoJustificationToolbar
				value={justification}
				onChange={(value) => setAttributes({ justification: value })}
			/>

			<BlockControls group="block">
				<ToolbarButton
					name="link"
					icon={linkIcon}
					title={__('Link', 'designsetgo')}
					onClick={startEditing}
					isActive={isURLSet}
				/>
			</BlockControls>

			{isSelected && (isEditingURL || isURLSet) && (
				<Popover
					placement="bottom"
					onClose={() => {
						setIsEditingURL(false);
						richTextRef.current?.focus();
					}}
					anchor={ref.current}
					focusOnMount={isEditingURL ? 'firstElement' : false}
					__unstableSlotName="__unstable-block-tools-after"
					shift
				>
					<LinkControl
						value={{
							url,
							opensInNewTab: linkTarget === '_blank',
						}}
						onChange={(nextValue) => {
							const newUrl = nextValue?.url ?? '';
							const opensInNewTab =
								nextValue?.opensInNewTab ?? false;

							const attrs = {
								url: newUrl,
								linkTarget: opensInNewTab ? '_blank' : '_self',
							};

							// Auto-manage rel when toggling new tab
							if (opensInNewTab && linkTarget !== '_blank') {
								const parts = rel
									? rel.split(/\s+/).filter(Boolean)
									: [];
								if (!parts.includes('noopener')) {
									parts.push('noopener');
								}
								if (!parts.includes('noreferrer')) {
									parts.push('noreferrer');
								}
								attrs.rel = parts.join(' ');
							} else if (
								!opensInNewTab &&
								linkTarget === '_blank'
							) {
								attrs.rel = (rel || '')
									.split(/\s+/)
									.filter(
										(t) =>
											t &&
											t !== 'noopener' &&
											t !== 'noreferrer'
									)
									.join(' ');
							}

							setAttributes(attrs);
						}}
						onRemove={unlink}
						forceIsEditingLink={isEditingURL}
						settings={[
							{
								id: 'opensInNewTab',
								title: __('Open in new tab', 'designsetgo'),
							},
						]}
					/>
				</Popover>
			)}

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Hover Colors', 'designsetgo')}
					settings={[
						{
							label: __('Hover Background', 'designsetgo'),
							colorValue: decodeColorValue(
								hoverBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									hoverBackgroundColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Hover Text', 'designsetgo'),
							colorValue: decodeColorValue(
								hoverTextColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									hoverTextColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
					]}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							icon: 'lightbulb',
							iconPosition: 'start',
							iconStyle: undefined,
							strokeWidth: 1.5,
							iconSize: undefined,
							iconGap: undefined,
							hoverAnimation: 'none',
							modalCloseId: '',
							fullWidth: false,
						})
					}
				>
					<ButtonSettingsPanel
						icon={icon}
						iconPosition={iconPosition}
						iconStyle={iconStyle}
						strokeWidth={strokeWidth}
						iconSize={iconSize}
						iconGap={iconGap}
						iconDefaults={iconDefaults}
						hoverAnimation={hoverAnimation}
						adminDefaultHover={themeDefaultHover || 'none'}
						modalCloseId={modalCloseId}
						isInsideModal={isInsideModal}
						setAttributes={setAttributes}
					/>
					<DsgoInspectorPanel.Item
						label={__('Full width', 'designsetgo')}
						hasValue={() => !!fullWidth}
						onDeselect={() => setAttributes({ fullWidth: false })}
						isShownByDefault
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={__('Full width', 'designsetgo')}
							checked={!!fullWidth}
							onChange={(value) =>
								setAttributes({ fullWidth: value })
							}
						/>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

			<div {...blockProps}>
				<div ref={ref} className={buttonClasses} style={buttonStyles}>
					{iconPosition !== 'none' && icon && (
						<span
							className="dsgo-icon-button__icon"
							style={iconWrapperStyles}
						>
							{getIcon(icon, effectiveStyle, strokeWidth)}
						</span>
					)}
					<RichText
						ref={richTextRef}
						tagName="span"
						className="dsgo-icon-button__text"
						value={text}
						onChange={(value) => setAttributes({ text: value })}
						placeholder={__('Button text…', 'designsetgo')}
						allowedFormats={['core/bold', 'core/italic']}
						withoutInteractiveFormatting
					/>
				</div>
			</div>
		</>
	);
}

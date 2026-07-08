/**
 * Icon Button Block - Edit Component
 *
 * Button with optional icon at start or end.
 * Link is managed via the inline toolbar, following the core Button block pattern.
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
} from '@wordpress/block-editor';
import { ToolbarButton, Popover } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
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
import { useIconDefaults } from '../../hooks';

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
		align,
		hoverAnimation,
		hoverBackgroundColor,
		hoverTextColor,
		style,
		backgroundColor,
		textColor,
		fontSize,
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

	// Extract WordPress color values
	// Custom colors come from style.color.background (hex/rgb)
	// Preset colors come from backgroundColor/textColor (slugs that need conversion)
	const bgColor =
		style?.color?.background ||
		(backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
	const txtColor =
		style?.color?.text ||
		(textColor && `var(--wp--preset--color--${textColor})`);

	// Extract font size
	// Custom font sizes come from style.typography.fontSize (px/rem/em)
	// Preset font sizes come from fontSize (slug that needs conversion)
	const fontSizeValue =
		style?.typography?.fontSize ||
		(fontSize && `var(--wp--preset--font-size--${fontSize})`);

	// Extract padding - WordPress stores it in style.spacing.padding
	const paddingValue = style?.spacing?.padding;

	// Combined styles for single element (must match save.js)
	// Visual styles (colors, padding, font size, hover) + layout styles (flexbox)
	// Use flex for full-width (alignfull), inline-flex for auto
	// Gap parity with save.js: omit entirely when no icon; inline only for an
	// explicit author gap, otherwise the stylesheet default owns it.
	const hasIcon = iconPosition !== 'none' && !!icon;
	const hasExplicitGap = typeof iconGap === 'string' && iconGap.trim() !== '';

	const isFullWidth = align === 'full';
	const buttonStyles = {
		display: isFullWidth ? 'flex' : 'inline-flex',
		alignItems: 'center',
		justifyContent: 'center',
		...(hasIcon && hasExplicitGap && { gap: iconGap }),
		width: isFullWidth ? '100%' : 'auto',
		flexDirection: iconPosition === 'end' ? 'row-reverse' : 'row',
		...(bgColor && { backgroundColor: bgColor }),
		...(txtColor && { color: txtColor }),
		...(fontSizeValue && { fontSize: fontSizeValue }),
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
	const iconWrapperStyles = {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		width: `${effectiveSize}px`,
		height: `${effectiveSize}px`,
		flexShrink: 0,
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

	// Build animation class
	const animationClass =
		effectiveAnimation && effectiveAnimation !== 'none'
			? ` dsgo-icon-button--${effectiveAnimation}`
			: '';

	// Single element with all classes and styles combined
	// wp-block-button and wp-element-button enable theme.json button styles
	// wp-block-button__link ensures theme compatibility
	// WordPress automatically adds alignfull class when align="full"
	const ButtonElement = 'div'; // Always div in editor to preserve editability

	const iconClass = hasIcon ? ' dsgo-icon-button--has-icon' : '';

	const blockProps = useBlockProps({
		ref,
		className: `dsgo-icon-button wp-block-button wp-block-button__link wp-element-button${iconClass}${animationClass}`,
		style: buttonStyles,
	});

	return (
		<>
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
				</DsgoInspectorPanel>
			</InspectorControls>

			<ButtonElement {...blockProps}>
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
			</ButtonElement>
		</>
	);
}

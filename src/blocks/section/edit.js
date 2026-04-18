/**
 * Section Block - Edit Component
 *
 * Vertical stacking container for sections and content areas.
 * Leverages WordPress's native flex layout system.
 *
 * @since 1.0.0
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InnerBlocks,
	InspectorControls,
	store as blockEditorStore,
	useSettings,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
} from '@wordpress/block-editor';
import {
	ToggleControl,
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseCustomUnits as useCustomUnits,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { createBlock } from '@wordpress/blocks';
import ShapeDividerControls from './components/ShapeDividerControls';
import ShapeDivider from './components/ShapeDivider';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { useBlockColors } from '../../hooks';

/**
 * Section Container Edit Component
 *
 * @param {Object}   props               Component props
 * @param {Object}   props.attributes    Block attributes
 * @param {Function} props.setAttributes Function to update attributes
 * @param {string}   props.clientId      Block client ID
 * @return {JSX.Element} Edit component
 */
export default function SectionEdit({ attributes, setAttributes, clientId }) {
	const {
		align,
		className,
		tagName = 'div',
		backgroundColor,
		textColor,
		constrainWidth,
		contentWidth,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		overlayColor,
		layout,
		// Shape divider attributes
		shapeDividerTop,
		shapeDividerTopColor,
		shapeDividerTopBackgroundColor,
		shapeDividerTopHeight,
		shapeDividerTopWidth,
		shapeDividerTopFlipX,
		shapeDividerTopFlipY,
		shapeDividerTopFront,
		shapeDividerBottom,
		shapeDividerBottomColor,
		shapeDividerBottomBackgroundColor,
		shapeDividerBottomHeight,
		shapeDividerBottomWidth,
		shapeDividerBottomFlipX,
		shapeDividerBottomFlipY,
		shapeDividerBottomFront,
	} = attributes;

	// Get section's effective background color for shape divider default
	// Prefer inline style (custom color) over preset slug
	const sectionBackgroundColor =
		attributes.style?.color?.background ||
		(backgroundColor ? `var(--wp--preset--color--${backgroundColor})` : '');

	// Get section's effective text color for shape divider background default
	const sectionTextColor =
		attributes.style?.color?.text ||
		(textColor ? `var(--wp--preset--color--${textColor})` : '');

	// Auto-migrate old blocks that use className for alignment
	useEffect(() => {
		// Only run if block doesn't have align attribute but has alignfull/alignwide in className
		if (!align && className) {
			let newAlign;
			if (className.includes('alignfull')) {
				newAlign = 'full';
			} else if (className.includes('alignwide')) {
				newAlign = 'wide';
			}

			if (newAlign) {
				// Remove alignment classes from className
				const cleanClassName = className
					.split(' ')
					.filter((cls) => cls !== 'alignfull' && cls !== 'alignwide')
					.join(' ')
					.trim();

				setAttributes({
					align: newAlign,
					className: cleanClassName || undefined,
				});
			}
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []); // Run only once on mount

	// Get theme settings (WP 6.5+)
	const [themeContentSize] = useSettings('layout.contentSize');

	// Hover Settings panel (static entries) — migrated to useBlockColors hook.
	// The two conditional entries (icon/button background) are kept inline below.
	// colorGradientSettings is also returned by the hook (same shape as
	// useMultipleOriginColorsAndGradients) and used by the inline panels below.
	const { settings: hoverColorSettings, colorGradientSettings } =
		useBlockColors({
			attributes,
			setAttributes,
			entries: [
				{
					label: __('Overlay Color', 'designsetgo'),
					attribute: 'overlayColor',
				},
				{
					label: __('Hover Background Color', 'designsetgo'),
					attribute: 'hoverBackgroundColor',
				},
				{
					label: __('Hover Text Color', 'designsetgo'),
					attribute: 'hoverTextColor',
				},
			],
		});

	// Setup custom units for width control
	const units = useCustomUnits({
		availableUnits: ['px', 'em', 'rem', 'vh', 'vw', '%'],
	});

	const { replaceBlock } = useDispatch(blockEditorStore);

	// Get inner blocks to determine if container is empty
	const { hasInnerBlocks, innerBlocks } = useSelect(
		(select) => {
			const { getBlock } = select(blockEditorStore);
			const block = getBlock(clientId);
			return {
				hasInnerBlocks: block?.innerBlocks?.length > 0,
				innerBlocks: block?.innerBlocks || [],
			};
		},
		[clientId]
	);

	// Detect if this section is nested inside another section
	const isNested = useSelect(
		(select) => {
			const { getBlockParents, getBlockName } = select(blockEditorStore);
			const parents = getBlockParents(clientId);
			return parents.some(
				(id) => getBlockName(id) === 'designsetgo/section'
			);
		},
		[clientId]
	);

	// CRITICAL: Auto-convert to Row block when orientation changes to horizontal
	// Section is meant for vertical stacking only
	// If user wants horizontal layout, they should use Row block
	useEffect(() => {
		if (layout?.orientation === 'horizontal') {
			// Create a new Row block with the same attributes and inner blocks
			const rowBlock = createBlock(
				'designsetgo/row',
				{
					hoverBackgroundColor,
					hoverTextColor,
					hoverIconBackgroundColor,
					hoverButtonBackgroundColor,
				},
				innerBlocks
			);

			// Replace this Section block with the Row block
			replaceBlock(clientId, rowBlock);
		}
	}, [
		layout?.orientation,
		clientId,
		replaceBlock,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		innerBlocks,
	]);

	// Auto-clear default padding for nested sections.
	// Intentionally uses [] deps (mount-only) — isNested is excluded because
	// re-running on block moves would clear user-customized padding.
	useEffect(() => {
		if (!isNested) {
			return;
		}

		const currentPadding = attributes.style?.spacing?.padding;
		const hasDefaultPadding =
			currentPadding?.top === 'var(--wp--preset--spacing--50)' &&
			currentPadding?.bottom === 'var(--wp--preset--spacing--50)' &&
			currentPadding?.left === 'var(--wp--preset--spacing--30)' &&
			currentPadding?.right === 'var(--wp--preset--spacing--30)';

		if (hasDefaultPadding) {
			setAttributes({
				style: {
					...attributes.style,
					spacing: {
						...attributes.style?.spacing,
						padding: {
							top: '0',
							bottom: '0',
							left: '0',
							right: '0',
						},
					},
				},
			});
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	// Build className (must match save.js)
	const blockClassName = [
		'dsgo-stack',
		!constrainWidth && 'dsgo-no-width-constraint',
		overlayColor && 'dsgo-stack--has-overlay',
		(shapeDividerTop || shapeDividerBottom) &&
			'dsgo-stack--has-shape-divider',
	]
		.filter(Boolean)
		.join(' ');

	// Block wrapper props - outer div stays full width (must match save.js EXACTLY)
	// WordPress handles flex layout through layout support and CSS classes
	// We only add custom CSS variables for hover effects and overlay
	const TagName = tagName || 'div';
	const blockProps = useBlockProps({
		className: blockClassName,
		style: {
			...(hoverBackgroundColor && {
				'--dsgo-hover-bg-color':
					convertColorToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-hover-text-color': convertColorToCSSVar(hoverTextColor),
			}),
			...(hoverIconBackgroundColor && {
				'--dsgo-parent-hover-icon-bg': convertColorToCSSVar(
					hoverIconBackgroundColor
				),
			}),
			...(hoverButtonBackgroundColor && {
				'--dsgo-parent-hover-button-bg': convertColorToCSSVar(
					hoverButtonBackgroundColor
				),
			}),
			...(overlayColor && {
				'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
				'--dsgo-overlay-opacity': '0.8',
			}),
		},
	});

	// Inner container props with width constraints (must match save.js EXACTLY)
	// Use custom contentWidth if set, otherwise fallback to theme's contentSize, then default
	const innerStyle = {};
	if (constrainWidth) {
		innerStyle.maxWidth = contentWidth || themeContentSize || '1140px';
		innerStyle.marginLeft = 'auto';
		innerStyle.marginRight = 'auto';
	}

	// Add padding to clear shape dividers (must match save.js EXACTLY)
	if (shapeDividerTop) {
		innerStyle.paddingTop = `${shapeDividerTopHeight || 100}px`;
	}
	if (shapeDividerBottom) {
		innerStyle.paddingBottom = `${shapeDividerBottomHeight || 100}px`;
	}

	// Merge inner blocks props
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-stack__inner',
			style: innerStyle,
		},
		{
			templateLock: false,
			renderAppender: hasInnerBlocks
				? undefined
				: InnerBlocks.ButtonBlockAppender,
		}
	);

	return (
		<>
			<InspectorControls group="advanced">
				<SelectControl
					label={__('HTML Element', 'designsetgo')}
					value={tagName}
					options={[
						{
							label: __('Default (<div>)', 'designsetgo'),
							value: 'div',
						},
						{ label: '<section>', value: 'section' },
						{ label: '<article>', value: 'article' },
						{ label: '<aside>', value: 'aside' },
						{ label: '<header>', value: 'header' },
						{ label: '<footer>', value: 'footer' },
						{ label: '<main>', value: 'main' },
					]}
					onChange={(value) => setAttributes({ tagName: value })}
					help={__(
						'Choose the HTML element for this block. Use semantic elements when appropriate for better accessibility.',
						'designsetgo'
					)}
					__nextHasNoMarginBottom
				/>
			</InspectorControls>

			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							constrainWidth: true,
							contentWidth: '',
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Constrain Inner Width', 'designsetgo')}
						hasValue={() => constrainWidth !== true}
						onDeselect={() =>
							setAttributes({
								constrainWidth: true,
								contentWidth: '',
							})
						}
						isShownByDefault
					>
						<ToggleControl
							label={__('Constrain Inner Width', 'designsetgo')}
							checked={constrainWidth}
							onChange={(value) =>
								setAttributes({ constrainWidth: value })
							}
							help={
								constrainWidth
									? __(
											'Inner content is constrained to max width',
											'designsetgo'
										)
									: __(
											'Inner content spans full container width',
											'designsetgo'
										)
							}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
					{constrainWidth && (
						<DsgoInspectorPanel.Item
							label={__('Max Content Width', 'designsetgo')}
							hasValue={() => contentWidth !== ''}
							onDeselect={() =>
								setAttributes({ contentWidth: '' })
							}
							isShownByDefault
						>
							<UnitControl
								label={__('Max Content Width', 'designsetgo')}
								value={contentWidth}
								onChange={(value) =>
									setAttributes({ contentWidth: value })
								}
								placeholder={
									themeContentSize ||
									__('Theme default', 'designsetgo')
								}
								units={units}
								__unstableInputWidth="80px"
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								help={
									!contentWidth && themeContentSize
										? sprintf(
												/* translators: %s: theme content size value */
												__(
													'Using theme default: %s',
													'designsetgo'
												),
												themeContentSize
											)
										: ''
								}
							/>
						</DsgoInspectorPanel.Item>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Hover Settings', 'designsetgo')}
					settings={[
						...hoverColorSettings,
						// Only show icon background control if hover background is set
						...(hoverBackgroundColor
							? [
									{
										label: __(
											'Hover Icon Background Color',
											'designsetgo'
										),
										colorValue: decodeColorValue(
											hoverIconBackgroundColor,
											colorGradientSettings
										),
										onColorChange: (color) =>
											setAttributes({
												hoverIconBackgroundColor:
													encodeColorValue(
														color,
														colorGradientSettings
													) || '',
											}),
										enableAlpha: true,
										clearable: true,
									},
								]
							: []),
						// Only show button background control if hover background is set
						...(hoverBackgroundColor
							? [
									{
										label: __(
											'Hover Button Background Color',
											'designsetgo'
										),
										colorValue: decodeColorValue(
											hoverButtonBackgroundColor,
											colorGradientSettings
										),
										onColorChange: (color) =>
											setAttributes({
												hoverButtonBackgroundColor:
													encodeColorValue(
														color,
														colorGradientSettings
													) || '',
											}),
										enableAlpha: true,
										clearable: true,
									},
								]
							: []),
					]}
					{...colorGradientSettings}
				/>
				{/* Shape Divider Colors - only show when shapes are enabled */}
				{(shapeDividerTop || shapeDividerBottom) && (
					<ColorGradientSettingsDropdown
						panelId={clientId}
						title={__('Shape Divider Colors', 'designsetgo')}
						settings={[
							// Top shape colors
							...(shapeDividerTop
								? [
										{
											label: __(
												'Top Shape Color',
												'designsetgo'
											),
											colorValue: decodeColorValue(
												shapeDividerTopColor,
												colorGradientSettings
											),
											onColorChange: (color) =>
												setAttributes({
													shapeDividerTopColor:
														encodeColorValue(
															color,
															colorGradientSettings
														) || '',
												}),
											clearable: true,
											enableAlpha: true,
										},
										{
											label: __(
												'Top Shape Background',
												'designsetgo'
											),
											colorValue: decodeColorValue(
												shapeDividerTopBackgroundColor,
												colorGradientSettings
											),
											onColorChange: (color) =>
												setAttributes({
													shapeDividerTopBackgroundColor:
														encodeColorValue(
															color,
															colorGradientSettings
														) || '',
												}),
											clearable: true,
											enableAlpha: true,
										},
									]
								: []),
							// Bottom shape colors
							...(shapeDividerBottom
								? [
										{
											label: __(
												'Bottom Shape Color',
												'designsetgo'
											),
											colorValue: decodeColorValue(
												shapeDividerBottomColor,
												colorGradientSettings
											),
											onColorChange: (color) =>
												setAttributes({
													shapeDividerBottomColor:
														encodeColorValue(
															color,
															colorGradientSettings
														) || '',
												}),
											clearable: true,
											enableAlpha: true,
										},
										{
											label: __(
												'Bottom Shape Background',
												'designsetgo'
											),
											colorValue: decodeColorValue(
												shapeDividerBottomBackgroundColor,
												colorGradientSettings
											),
											onColorChange: (color) =>
												setAttributes({
													shapeDividerBottomBackgroundColor:
														encodeColorValue(
															color,
															colorGradientSettings
														) || '',
												}),
											clearable: true,
											enableAlpha: true,
										},
									]
								: []),
						]}
						{...colorGradientSettings}
					/>
				)}
			</InspectorControls>

			<InspectorControls>
				<ShapeDividerControls
					attributes={attributes}
					setAttributes={setAttributes}
					sectionBackgroundColor={sectionBackgroundColor}
					sectionTextColor={sectionTextColor}
				/>
			</InspectorControls>

			<TagName {...blockProps}>
				<ShapeDivider
					shape={shapeDividerTop}
					color={
						convertColorToCSSVar(shapeDividerTopColor) ||
						sectionBackgroundColor
					}
					backgroundColor={
						convertColorToCSSVar(shapeDividerTopBackgroundColor) ||
						sectionTextColor
					}
					height={shapeDividerTopHeight}
					width={shapeDividerTopWidth}
					flipX={shapeDividerTopFlipX}
					flipY={shapeDividerTopFlipY}
					front={shapeDividerTopFront}
					position="top"
				/>
				<div {...innerBlocksProps} />
				<ShapeDivider
					shape={shapeDividerBottom}
					color={
						convertColorToCSSVar(shapeDividerBottomColor) ||
						sectionBackgroundColor
					}
					backgroundColor={
						convertColorToCSSVar(
							shapeDividerBottomBackgroundColor
						) || sectionTextColor
					}
					height={shapeDividerBottomHeight}
					width={shapeDividerBottomWidth}
					flipX={shapeDividerBottomFlipX}
					flipY={shapeDividerBottomFlipY}
					front={shapeDividerBottomFront}
					position="bottom"
				/>
			</TagName>
		</>
	);
}

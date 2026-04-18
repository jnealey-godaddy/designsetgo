import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	store as blockEditorStore,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import classnames from 'classnames';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import AccordionPlaceholder from './components/AccordionPlaceholder';

export default function AccordionEdit({ attributes, setAttributes, clientId }) {
	const {
		allowMultipleOpen,
		iconStyle,
		iconPosition,
		borderBetween,
		borderBetweenColor,
		itemGap,
		openBackgroundColor,
		openTextColor,
		hoverBackgroundColor,
		hoverTextColor,
	} = attributes;

	// Get theme color palette and gradient settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	const hasInnerBlocks = useSelect(
		(select) =>
			select(blockEditorStore).getBlock(clientId)?.innerBlocks?.length >
			0,
		[clientId]
	);

	// Smart default: hover mirrors open unless explicitly set
	const effectiveHoverBg = hoverBackgroundColor || openBackgroundColor;
	const effectiveHoverText = hoverTextColor || openTextColor;

	// Declaratively calculate classes based on attributes
	const accordionClasses = classnames('dsgo-accordion', {
		'dsgo-accordion--multiple': allowMultipleOpen,
		'dsgo-accordion--icon-left': iconPosition === 'left',
		'dsgo-accordion--icon-right': iconPosition === 'right',
		'dsgo-accordion--no-icon': iconStyle === 'none',
		'dsgo-accordion--border-between': borderBetween,
	});

	// Apply colors and gap as CSS custom properties that will cascade to accordion items
	const customStyles = {
		'--dsgo-accordion-open-bg': convertColorToCSSVar(openBackgroundColor),
		'--dsgo-accordion-open-text': convertColorToCSSVar(openTextColor),
		'--dsgo-accordion-hover-bg': convertColorToCSSVar(effectiveHoverBg),
		'--dsgo-accordion-hover-text': convertColorToCSSVar(effectiveHoverText),
		'--dsgo-accordion-gap': itemGap,
		...(borderBetweenColor && {
			'--dsgo-accordion-border-color':
				convertColorToCSSVar(borderBetweenColor),
		}),
	};

	// Block wrapper props
	const blockProps = useBlockProps({
		className: accordionClasses,
		style: customStyles,
	});

	// Inner blocks configuration - ONLY allow accordion-item children. The
	// initial seeding is handled by AccordionPlaceholder so the user always
	// picks a starter layout instead of landing on a generic two-item template.
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-accordion__items',
		},
		{
			allowedBlocks: ['designsetgo/accordion-item'],
			orientation: 'vertical',
		}
	);

	if (!hasInnerBlocks) {
		return (
			<div {...blockProps}>
				<AccordionPlaceholder
					clientId={clientId}
					setAttributes={setAttributes}
				/>
			</div>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Accordion Settings', 'designsetgo')}>
					<ToggleControl
						label={__('Allow Multiple Open', 'designsetgo')}
						help={
							allowMultipleOpen
								? __(
										'Multiple panels can be open at once',
										'designsetgo'
									)
								: __(
										'Only one panel can be open at a time',
										'designsetgo'
									)
						}
						checked={allowMultipleOpen}
						onChange={(value) =>
							setAttributes({ allowMultipleOpen: value })
						}
						__nextHasNoMarginBottom
					/>
					<p className="components-base-control__help">
						{__(
							'Tip: Use the "Open by Default" toggle on individual accordion items to control which panels are open when the page loads.',
							'designsetgo'
						)}
					</p>
				</PanelBody>

				<PanelBody
					title={__('Icon Settings', 'designsetgo')}
					initialOpen={false}
				>
					<SelectControl
						label={__('Icon Style', 'designsetgo')}
						value={iconStyle}
						options={[
							{
								label: __('Chevron', 'designsetgo'),
								value: 'chevron',
							},
							{
								label: __('Plus/Minus', 'designsetgo'),
								value: 'plus-minus',
							},
							{
								label: __('Caret', 'designsetgo'),
								value: 'caret',
							},
							{ label: __('None', 'designsetgo'), value: 'none' },
						]}
						onChange={(value) =>
							setAttributes({ iconStyle: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					{iconStyle !== 'none' && (
						<SelectControl
							label={__('Icon Position', 'designsetgo')}
							value={iconPosition}
							options={[
								{
									label: __('Left', 'designsetgo'),
									value: 'left',
								},
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
					)}
				</PanelBody>

				<PanelBody
					title={__('Style Settings', 'designsetgo')}
					initialOpen={false}
				>
					<ToggleControl
						label={__('Border Between Items', 'designsetgo')}
						checked={borderBetween}
						onChange={(value) =>
							setAttributes({ borderBetween: value })
						}
						help={
							borderBetween
								? __(
										'Items have borders between them with no gap',
										'designsetgo'
									)
								: __(
										'Items are separated with spacing',
										'designsetgo'
									)
						}
						__nextHasNoMarginBottom
					/>

					{!borderBetween && (
						<UnitControl
							label={__('Gap Between Items', 'designsetgo')}
							value={itemGap}
							onChange={(value) =>
								setAttributes({ itemGap: value || '0.5rem' })
							}
							units={[
								{ value: 'px', label: 'px', default: 8 },
								{ value: 'rem', label: 'rem', default: 0.5 },
								{ value: 'em', label: 'em', default: 0.5 },
							]}
							min={0}
							max={100}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					)}
				</PanelBody>
			</InspectorControls>

			<InspectorControls group="color">
				{borderBetween && (
					<ColorGradientSettingsDropdown
						panelId={clientId}
						title={__('Border Color', 'designsetgo')}
						settings={[
							{
								label: __('Between Items', 'designsetgo'),
								colorValue: decodeColorValue(
									borderBetweenColor,
									colorGradientSettings
								),
								onColorChange: (color) =>
									setAttributes({
										borderBetweenColor:
											encodeColorValue(
												color,
												colorGradientSettings
											) || '',
									}),
								clearable: true,
								enableAlpha: true,
							},
						]}
						{...colorGradientSettings}
					/>
				)}

				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Open State Colors', 'designsetgo')}
					settings={[
						{
							label: __('Background', 'designsetgo'),
							colorValue: decodeColorValue(
								openBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (value) =>
								setAttributes({
									openBackgroundColor:
										encodeColorValue(
											value,
											colorGradientSettings
										) || '',
								}),
							clearable: true,
							enableAlpha: true,
						},
						{
							label: __('Text', 'designsetgo'),
							colorValue: decodeColorValue(
								openTextColor,
								colorGradientSettings
							),
							onColorChange: (value) =>
								setAttributes({
									openTextColor:
										encodeColorValue(
											value,
											colorGradientSettings
										) || '',
								}),
							clearable: true,
							enableAlpha: true,
						},
					]}
					{...colorGradientSettings}
				/>
				<p className="components-base-control__help">
					{__(
						'Colors applied to all accordion items when open.',
						'designsetgo'
					)}
				</p>

				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Hover State Colors', 'designsetgo')}
					settings={[
						{
							label: __('Background', 'designsetgo'),
							colorValue: decodeColorValue(
								hoverBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (value) =>
								setAttributes({
									hoverBackgroundColor:
										encodeColorValue(
											value,
											colorGradientSettings
										) || '',
								}),
							clearable: true,
							enableAlpha: true,
						},
						{
							label: __('Text', 'designsetgo'),
							colorValue: decodeColorValue(
								hoverTextColor,
								colorGradientSettings
							),
							onColorChange: (value) =>
								setAttributes({
									hoverTextColor:
										encodeColorValue(
											value,
											colorGradientSettings
										) || '',
								}),
							clearable: true,
							enableAlpha: true,
						},
					]}
					{...colorGradientSettings}
				/>
				<p className="components-base-control__help">
					{hoverBackgroundColor || hoverTextColor
						? __(
								'Custom hover colors set. Clear to use open state colors.',
								'designsetgo'
							)
						: __(
								'Hover colors mirror open state by default. Set custom colors to override.',
								'designsetgo'
							)}
				</p>
			</InspectorControls>

			{/* NO wrapper div - spread props directly per WordPress best practices */}
			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}

import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	RangeControl,
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

export default function ImageAccordionEdit({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		height,
		gap,
		expandedRatio,
		transitionDuration,
		enableOverlay,
		overlayColor,
		overlayOpacity,
		overlayOpacityExpanded,
		triggerType,
		defaultExpanded,
	} = attributes;

	// Get theme color palette and gradient settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Build options for the default-expanded picker from the actual child items.
	// Uses the first core/heading content when present so authors recognize each item.
	const itemOptions = useSelect(
		(select) => {
			const children =
				select('core/block-editor').getBlocks(clientId) || [];
			const options = [
				{
					label: __('None (no item expanded)', 'designsetgo'),
					value: '0',
				},
			];
			children.forEach((child, index) => {
				const heading = child.innerBlocks?.find(
					(inner) => inner.name === 'core/heading'
				);
				const raw = heading?.attributes?.content ?? '';
				// DOMParser handles malformed/unterminated tags safely
				// (unlike a naive regex).
				const parsed = new window.DOMParser().parseFromString(
					String(raw),
					'text/html'
				);
				const text = (parsed.body.textContent || '')
					.trim()
					.slice(0, 40);
				const label = text
					? sprintf(
							/* translators: %1$d: item position, %2$s: item title */
							__('Item %1$d: %2$s', 'designsetgo'),
							index + 1,
							text
						)
					: sprintf(
							/* translators: %d: item position */
							__('Item %d', 'designsetgo'),
							index + 1
						);
				options.push({ label, value: String(index + 1) });
			});
			return options;
		},
		[clientId]
	);

	// Declaratively calculate classes based on attributes
	const accordionClasses = classnames('dsgo-image-accordion', {
		'dsgo-image-accordion--hover': triggerType === 'hover',
		'dsgo-image-accordion--click': triggerType === 'click',
	});

	// Apply settings as CSS custom properties for consistent styling
	// Note: Unitless values must be strings to prevent React from adding 'px'
	const customStyles = {
		'--dsgo-image-accordion-height': height,
		'--dsgo-image-accordion-gap': gap,
		'--dsgo-image-accordion-expanded-ratio': String(expandedRatio), // Unitless
		'--dsgo-image-accordion-transition': transitionDuration,
		'--dsgo-image-accordion-overlay-color':
			convertColorToCSSVar(overlayColor),
		'--dsgo-image-accordion-overlay-opacity': String(overlayOpacity / 100), // Unitless
		'--dsgo-image-accordion-overlay-opacity-expanded': String(
			overlayOpacityExpanded / 100
		), // Unitless
	};

	// Block wrapper props
	const blockProps = useBlockProps({
		className: accordionClasses,
		style: customStyles,
	});

	// Inner blocks configuration - ONLY allow image-accordion-item children
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-image-accordion__items',
		},
		{
			allowedBlocks: ['designsetgo/image-accordion-item'],
			template: [
				['designsetgo/image-accordion-item', {}],
				['designsetgo/image-accordion-item', {}],
				['designsetgo/image-accordion-item', {}],
			],
			orientation: 'vertical', // Always vertical in editor for easier editing
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Layout', 'designsetgo')}
					initialOpen={true}
				>
					<UnitControl
						label={__('Height', 'designsetgo')}
						value={height}
						onChange={(value) =>
							setAttributes({ height: value || '500px' })
						}
						units={[
							{ value: 'px', label: 'px', default: 500 },
							{ value: 'vh', label: 'vh', default: 50 },
							{ value: 'rem', label: 'rem', default: 30 },
						]}
						min={200}
						max={1000}
						help={__(
							'Fixed height for the accordion',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<UnitControl
						label={__('Gap Between Items', 'designsetgo')}
						value={gap}
						onChange={(value) =>
							setAttributes({ gap: value || '4px' })
						}
						units={[
							{ value: 'px', label: 'px', default: 4 },
							{ value: 'rem', label: 'rem', default: 0.25 },
						]}
						min={0}
						max={32}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={__('Expansion Behavior', 'designsetgo')}
					initialOpen={false}
				>
					<RangeControl
						label={__('Expanded Ratio', 'designsetgo')}
						value={expandedRatio}
						onChange={(value) =>
							setAttributes({ expandedRatio: value })
						}
						min={2}
						max={5}
						step={0.5}
						help={__(
							'How much larger the expanded item becomes (others stay normal size)',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<UnitControl
						label={__('Transition Duration', 'designsetgo')}
						value={transitionDuration}
						onChange={(value) =>
							setAttributes({
								transitionDuration: value || '0.5s',
							})
						}
						units={[
							{ value: 's', label: 's', default: 0.5 },
							{ value: 'ms', label: 'ms', default: 500 },
						]}
						min={0.1}
						max={2}
						help={__(
							'Speed of expansion/collapse animation',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={__('Interaction', 'designsetgo')}
					initialOpen={false}
				>
					<SelectControl
						label={__('Trigger Type', 'designsetgo')}
						value={triggerType}
						options={[
							{
								label: __('Hover (Desktop)', 'designsetgo'),
								value: 'hover',
							},
							{
								label: __('Click/Tap', 'designsetgo'),
								value: 'click',
							},
						]}
						onChange={(value) =>
							setAttributes({ triggerType: value })
						}
						help={__(
							'Hover is automatically replaced with click on mobile',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Default Expanded Item', 'designsetgo')}
						value={String(defaultExpanded)}
						options={itemOptions}
						onChange={(value) =>
							setAttributes({
								defaultExpanded: parseInt(value, 10) || 0,
							})
						}
						help={__(
							'Which item is expanded when the page loads',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={__('Overlay', 'designsetgo')}
					initialOpen={false}
				>
					<ToggleControl
						label={__('Enable Overlay', 'designsetgo')}
						checked={enableOverlay}
						onChange={(value) =>
							setAttributes({ enableOverlay: value })
						}
						help={
							enableOverlay
								? __(
										'Overlay applied to all items',
										'designsetgo'
									)
								: __('No overlay on items', 'designsetgo')
						}
						__nextHasNoMarginBottom
					/>

					{enableOverlay && (
						<>
							<RangeControl
								label={__(
									'Overlay Opacity (Default)',
									'designsetgo'
								)}
								value={overlayOpacity}
								onChange={(value) =>
									setAttributes({ overlayOpacity: value })
								}
								min={0}
								max={100}
								help={__(
									'Opacity when item is not expanded',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<RangeControl
								label={__(
									'Overlay Opacity (Expanded)',
									'designsetgo'
								)}
								value={overlayOpacityExpanded}
								onChange={(value) =>
									setAttributes({
										overlayOpacityExpanded: value,
									})
								}
								min={0}
								max={100}
								help={__(
									'Opacity when item is expanded',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</>
					)}
				</PanelBody>
			</InspectorControls>

			{enableOverlay && (
				<InspectorControls group="color">
					<ColorGradientSettingsDropdown
						panelId={clientId}
						title={__('Overlay Color', 'designsetgo')}
						settings={[
							{
								label: __('Color', 'designsetgo'),
								colorValue: decodeColorValue(
									overlayColor,
									colorGradientSettings
								),
								onColorChange: (value) =>
									setAttributes({
										overlayColor: encodeColorValue(
											value,
											colorGradientSettings
										),
									}),
								clearable: true,
								enableAlpha: true,
							},
						]}
						{...colorGradientSettings}
					/>
				</InspectorControls>
			)}

			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}

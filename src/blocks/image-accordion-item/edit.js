import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	BlockControls,
	MediaReplaceFlow,
} from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { PanelBody, SelectControl, ToolbarGroup } from '@wordpress/components';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

export default function ImageAccordionItemEdit({
	attributes,
	setAttributes,
	context,
	clientId,
	isSelected,
}) {
	const { uniqueId, verticalAlignment, horizontalAlignment, style } =
		attributes;

	// Expand when this item (or any of its inner blocks) is selected so the
	// inline toolbar and content editing stay stable — hover-based expansion
	// collapsed the item when the cursor moved to the toolbar.
	const hasChildSelected = useSelect(
		(select) =>
			select('core/block-editor').hasSelectedInnerBlock(clientId, true),
		[clientId]
	);
	const isExpanded = isSelected || hasChildSelected;

	const backgroundImage = style?.background?.backgroundImage;
	const mediaUrl = backgroundImage?.url || '';
	const mediaId = backgroundImage?.id;

	const onSelectMedia = (media) => {
		if (!media?.url) {
			return;
		}
		setAttributes({
			style: {
				...(style || {}),
				background: {
					...(style?.background || {}),
					backgroundImage: {
						url: media.url,
						id: media.id,
						source: 'file',
					},
				},
			},
		});
	};

	const onRemoveMedia = () => {
		setAttributes({
			style: {
				...(style || {}),
				background: {
					...(style?.background || {}),
					backgroundImage: undefined,
				},
			},
		});
	};

	// Get context from parent accordion
	const enableOverlay =
		context['designsetgo/imageAccordion/enableOverlay'] !== undefined
			? context['designsetgo/imageAccordion/enableOverlay']
			: true;
	const overlayColor =
		context['designsetgo/imageAccordion/overlayColor'] || '#000000';
	const overlayOpacity =
		context['designsetgo/imageAccordion/overlayOpacity'] || 40;
	const overlayOpacityExpanded =
		context['designsetgo/imageAccordion/overlayOpacityExpanded'] || 20;

	// Generate unique ID for accessibility
	useEffect(() => {
		if (!uniqueId) {
			setAttributes({
				uniqueId: `image-accordion-item-${Math.random().toString(36).substr(2, 9)}`,
			});
		}
	}, [uniqueId, setAttributes]);

	// Declaratively calculate classes
	const itemClasses = classnames('dsgo-image-accordion-item', {
		'dsgo-image-accordion-item--has-overlay': enableOverlay,
		'is-expanded': isExpanded,
	});

	// Apply overlay and alignment as inline styles
	// Note: Unitless values must be strings to prevent React from adding 'px'
	const overlayStyles = enableOverlay
		? {
				'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
				'--dsgo-overlay-opacity': String(overlayOpacity / 100), // Unitless
				'--dsgo-overlay-opacity-expanded': String(
					overlayOpacityExpanded / 100
				), // Unitless
			}
		: {};

	const blockProps = useBlockProps({
		className: itemClasses,
		style: {
			...overlayStyles,
			'--dsgo-vertical-alignment': verticalAlignment,
			'--dsgo-horizontal-alignment': horizontalAlignment,
		},
	});

	// Inner blocks for item content
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-image-accordion-item__content',
		},
		{
			template: [
				[
					'core/heading',
					{
						level: 2,
						placeholder: __('Add title…', 'designsetgo'),
					},
				],
				[
					'core/paragraph',
					{
						placeholder: __('Add description…', 'designsetgo'),
					},
				],
			],
		}
	);

	return (
		<>
			<BlockControls group="other">
				<ToolbarGroup>
					<MediaReplaceFlow
						mediaId={mediaId}
						mediaURL={mediaUrl}
						allowedTypes={['image']}
						accept="image/*"
						onSelect={onSelectMedia}
						onReset={mediaUrl ? onRemoveMedia : undefined}
						name={
							mediaUrl
								? __('Replace', 'designsetgo')
								: __('Add image', 'designsetgo')
						}
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody
					title={__('Content Alignment', 'designsetgo')}
					initialOpen={true}
				>
					<SelectControl
						label={__('Vertical Alignment', 'designsetgo')}
						value={verticalAlignment}
						options={[
							{
								label: __('Top', 'designsetgo'),
								value: 'flex-start',
							},
							{
								label: __('Center', 'designsetgo'),
								value: 'center',
							},
							{
								label: __('Bottom', 'designsetgo'),
								value: 'flex-end',
							},
						]}
						onChange={(value) =>
							setAttributes({ verticalAlignment: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Horizontal Alignment', 'designsetgo')}
						value={horizontalAlignment}
						options={[
							{
								label: __('Left', 'designsetgo'),
								value: 'flex-start',
							},
							{
								label: __('Center', 'designsetgo'),
								value: 'center',
							},
							{
								label: __('Right', 'designsetgo'),
								value: 'flex-end',
							},
						]}
						onChange={(value) =>
							setAttributes({ horizontalAlignment: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}

/**
 * Fifty Fifty Block - Edit Component
 *
 * Full-width 50/50 split with edge-to-edge media and constrained content.
 *
 * @since 1.5.0
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InnerBlocks,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	MediaReplaceFlow,
	BlockControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	SelectControl,
	FocalPointPicker,
	Button,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseCustomUnits as useCustomUnits,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { useSelect } from '@wordpress/data';
import { convertPresetToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Fifty Fifty Edit Component
 *
 * @param {Object}   props               Component props
 * @param {Object}   props.attributes    Block attributes
 * @param {Function} props.setAttributes Function to set attributes
 * @param {string}   props.clientId      Block client ID
 * @return {JSX.Element} Edit component
 */
export default function FiftyFiftyEdit({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		mediaPosition,
		mediaId,
		mediaUrl,
		mediaAlt,
		focalPoint,
		minHeight,
		verticalAlignment,
		contentPadding,
	} = attributes;

	// Units for min height control
	const units = useCustomUnits({
		availableUnits: ['px', 'vh', 'vw', 'em', 'rem'],
	});

	// Check if block has inner blocks for appender logic
	const { hasInnerBlocks } = useSelect(
		(select) => {
			const { getBlock } = select(blockEditorStore);
			const block = getBlock(clientId);
			return {
				hasInnerBlocks: block?.innerBlocks?.length > 0,
			};
		},
		[clientId]
	);

	// Map verticalAlignment to CSS align-items
	const alignItemsMap = {
		top: 'flex-start',
		center: 'center',
		bottom: 'flex-end',
	};

	// Build block class name
	const blockClassName = [
		'dsgo-fifty-fifty',
		`dsgo-fifty-fifty--media-${mediaPosition}`,
	].join(' ');

	// Block wrapper props
	const blockProps = useBlockProps({
		className: blockClassName,
		style: {
			'--dsgo-fifty-fifty-min-height': minHeight || undefined,
			'--dsgo-fifty-fifty-content-justify':
				alignItemsMap[verticalAlignment] || 'center',
			'--dsgo-fifty-fifty-content-padding':
				convertPresetToCSSVar(contentPadding) || undefined,
		},
	});

	// InnerBlocks props - placed in the content-inner wrapper
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-fifty-fifty__content-inner',
		},
		{
			template: [
				[
					'core/heading',
					{
						level: 2,
						placeholder: __('Add heading…', 'designsetgo'),
					},
				],
				[
					'core/paragraph',
					{
						placeholder: __('Add content…', 'designsetgo'),
					},
				],
			],
			templateLock: false,
			renderAppender: hasInnerBlocks
				? undefined
				: InnerBlocks.ButtonBlockAppender,
		}
	);

	// Media selection handler
	const onSelectMedia = (media) => {
		setAttributes({
			mediaId: media.id,
			mediaUrl: media.url,
			mediaAlt: media.alt || '',
		});
	};

	const onRemoveMedia = () => {
		setAttributes({
			mediaId: 0,
			mediaUrl: '',
			mediaAlt: '',
		});
	};

	// Focal point as object-position (coerce to Number to prevent CSS injection)
	const objectPosition = focalPoint
		? `${Number(focalPoint.x) * 100}% ${Number(focalPoint.y) * 100}%`
		: '50% 50%';

	return (
		<>
			{/* Toolbar: flip media side + replace media */}
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="image-flip-horizontal"
						label={__('Flip Layout', 'designsetgo')}
						onClick={() =>
							setAttributes({
								mediaPosition:
									mediaPosition === 'left' ? 'right' : 'left',
							})
						}
					/>
				</ToolbarGroup>
				{mediaUrl && (
					<MediaReplaceFlow
						mediaId={mediaId}
						mediaURL={mediaUrl}
						allowedTypes={['image']}
						accept="image/*"
						onSelect={onSelectMedia}
						name={__('Replace Image', 'designsetgo')}
					/>
				)}
			</BlockControls>

			{/* Inspector Controls */}
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							mediaPosition: 'left',
							verticalAlignment: 'center',
							minHeight: '500px',
							mediaId: 0,
							mediaUrl: '',
							mediaAlt: '',
							focalPoint: { x: 0.5, y: 0.5 },
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Media Position', 'designsetgo')}
						hasValue={() => mediaPosition !== 'left'}
						onDeselect={() =>
							setAttributes({ mediaPosition: 'left' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Media Position', 'designsetgo')}
							value={mediaPosition}
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
								setAttributes({ mediaPosition: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Content Vertical Alignment', 'designsetgo')}
						hasValue={() => verticalAlignment !== 'center'}
						onDeselect={() =>
							setAttributes({ verticalAlignment: 'center' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__(
								'Content Vertical Alignment',
								'designsetgo'
							)}
							value={verticalAlignment}
							options={[
								{
									label: __('Top', 'designsetgo'),
									value: 'top',
								},
								{
									label: __('Center', 'designsetgo'),
									value: 'center',
								},
								{
									label: __('Bottom', 'designsetgo'),
									value: 'bottom',
								},
							]}
							onChange={(value) =>
								setAttributes({ verticalAlignment: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Min Height', 'designsetgo')}
						hasValue={() => minHeight !== '500px'}
						onDeselect={() => setAttributes({ minHeight: '500px' })}
						isShownByDefault={false}
					>
						<UnitControl
							label={__('Min Height', 'designsetgo')}
							value={minHeight}
							onChange={(value) =>
								setAttributes({ minHeight: value })
							}
							units={units}
							__next40pxDefaultSize
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Image', 'designsetgo')}
						hasValue={() => !!mediaUrl}
						onDeselect={() =>
							setAttributes({
								mediaId: 0,
								mediaUrl: '',
								mediaAlt: '',
								focalPoint: { x: 0.5, y: 0.5 },
							})
						}
						isShownByDefault
					>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={onSelectMedia}
								allowedTypes={['image']}
								value={mediaId}
								render={({ open }) => (
									<>
										{mediaUrl ? (
											<>
												<img
													src={mediaUrl}
													alt={mediaAlt}
													style={{
														width: '100%',
														height: 'auto',
														marginBottom: '8px',
														borderRadius: '4px',
													}}
												/>
												<div
													style={{
														display: 'flex',
														gap: '8px',
														marginBottom: '12px',
													}}
												>
													<Button
														onClick={open}
														variant="secondary"
														style={{ flex: 1 }}
													>
														{__(
															'Replace',
															'designsetgo'
														)}
													</Button>
													<Button
														onClick={onRemoveMedia}
														variant="secondary"
														isDestructive
													>
														{__(
															'Remove',
															'designsetgo'
														)}
													</Button>
												</div>
											</>
										) : (
											<Button
												onClick={open}
												variant="secondary"
												style={{
													width: '100%',
													justifyContent: 'center',
													marginBottom: '12px',
												}}
											>
												{__(
													'Select Image',
													'designsetgo'
												)}
											</Button>
										)}
									</>
								)}
							/>
						</MediaUploadCheck>
					</DsgoInspectorPanel.Item>

					{mediaUrl && (
						<DsgoInspectorPanel.Item
							label={__('Alt Text', 'designsetgo')}
							hasValue={() => mediaAlt !== ''}
							onDeselect={() => setAttributes({ mediaAlt: '' })}
							isShownByDefault
						>
							<TextControl
								label={__('Alt Text', 'designsetgo')}
								value={mediaAlt}
								onChange={(value) =>
									setAttributes({ mediaAlt: value })
								}
								help={__(
									'Describe the image for accessibility.',
									'designsetgo'
								)}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					{mediaUrl && (
						<DsgoInspectorPanel.Item
							label={__('Focal Point', 'designsetgo')}
							hasValue={() =>
								focalPoint?.x !== 0.5 || focalPoint?.y !== 0.5
							}
							onDeselect={() =>
								setAttributes({
									focalPoint: { x: 0.5, y: 0.5 },
								})
							}
							isShownByDefault={false}
						>
							<FocalPointPicker
								label={__('Focal Point', 'designsetgo')}
								url={mediaUrl}
								value={focalPoint}
								onChange={(value) =>
									setAttributes({ focalPoint: value })
								}
								help={__(
									'Click to adjust which part of the image stays visible.',
									'designsetgo'
								)}
							/>
						</DsgoInspectorPanel.Item>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			{/* Block Output */}
			<div {...blockProps}>
				<div className="dsgo-fifty-fifty__media">
					{mediaUrl ? (
						<img
							src={mediaUrl}
							alt={mediaAlt}
							style={{ objectPosition }}
						/>
					) : (
						<MediaUploadCheck>
							<MediaUpload
								onSelect={onSelectMedia}
								allowedTypes={['image']}
								value={mediaId}
								render={({ open }) => (
									<div
										className="dsgo-fifty-fifty__media-placeholder"
										onClick={open}
										onKeyDown={(e) => {
											if (
												e.key === 'Enter' ||
												e.key === ' '
											) {
												e.preventDefault();
												open();
											}
										}}
										role="button"
										tabIndex={0}
										aria-label={__(
											'Select image',
											'designsetgo'
										)}
									>
										<span className="dashicons dashicons-format-image" />
										<span>
											{__('Select Image', 'designsetgo')}
										</span>
									</div>
								)}
							/>
						</MediaUploadCheck>
					)}
				</div>

				<div className="dsgo-fifty-fifty__content">
					<div {...innerBlocksProps} />
				</div>
			</div>
		</>
	);
}

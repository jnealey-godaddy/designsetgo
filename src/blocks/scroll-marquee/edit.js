import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	RangeControl,
	Button,
	Notice,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalUnitControl as UnitControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { plus, close } from '@wordpress/icons';

export default function ScrollMarqueeEdit({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		rows,
		scrollSpeed,
		imageHeight,
		imageWidth,
		gap,
		rowGap,
		borderRadius,
	} = attributes;

	const imageInlineStyle = {
		height: imageHeight,
		width: imageWidth,
		borderRadius,
	};

	// Performance: Calculate total images across all rows
	const totalImages = rows.reduce(
		(sum, row) => sum + (row.images?.length || 0),
		0
	);
	const showPerformanceWarning = totalImages > 20;

	const blockProps = useBlockProps({
		className: 'dsgo-scroll-marquee',
		style: {
			'--dsgo-marquee-gap': gap,
			'--dsgo-marquee-row-gap': rowGap,
			'--dsgo-marquee-image-height': imageHeight,
			'--dsgo-marquee-image-width': imageWidth,
			'--dsgo-marquee-border-radius': borderRadius,
		},
	});

	const addRow = () => {
		const newRows = [
			...rows,
			{ images: [], direction: rows.length % 2 === 0 ? 'left' : 'right' },
		];
		setAttributes({ rows: newRows });
	};

	const removeRow = (rowIndex) => {
		const newRows = rows.filter((_, index) => index !== rowIndex);
		setAttributes({ rows: newRows });
	};

	const toggleRowDirection = (rowIndex) => {
		const newRows = [...rows];
		newRows[rowIndex].direction =
			newRows[rowIndex].direction === 'left' ? 'right' : 'left';
		setAttributes({ rows: newRows });
	};

	const onSelectImages = (rowIndex, images) => {
		const newRows = [...rows];
		// MediaUpload with 'value' prop pre-selects existing images,
		// so 'images' param contains ALL selected images (existing + new)
		// We just need to map them to our format
		newRows[rowIndex].images = images.map((img) => ({
			id: img.id,
			url: img.url,
			alt: img.alt || '',
		}));
		setAttributes({ rows: newRows });
	};

	const removeImage = (rowIndex, imageIndex) => {
		const newRows = [...rows];
		newRows[rowIndex].images = newRows[rowIndex].images.filter(
			(_, index) => index !== imageIndex
		);
		setAttributes({ rows: newRows });
	};

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							scrollSpeed: 0.5,
							imageHeight: '200px',
							imageWidth: '300px',
							gap: '20px',
							rowGap: '20px',
							borderRadius: '8px',
						})
					}
				>
					{showPerformanceWarning ? (
						<Notice status="warning" isDismissible={false}>
							{__('You have', 'designsetgo')}
							<strong>{totalImages}</strong>
							{__(
								'images. For best performance, consider using fewer images (20 or less) or optimizing image sizes. Each image is duplicated 6 times for smooth infinite scrolling.',
								'designsetgo'
							)}
						</Notice>
					) : (
						<Notice status="success" isDismissible={false}>
							{__('Total images:', 'designsetgo')}
							<strong>{totalImages}</strong>
							{__(
								'(duplicated 6x for infinite scroll)',
								'designsetgo'
							)}
						</Notice>
					)}

					<DsgoInspectorPanel.Item
						label={__('Scroll Speed', 'designsetgo')}
						hasValue={() => scrollSpeed !== 0.5}
						onDeselect={() => setAttributes({ scrollSpeed: 0.5 })}
						isShownByDefault
					>
						<RangeControl
							label={__('Scroll Speed', 'designsetgo')}
							value={scrollSpeed}
							onChange={(value) =>
								setAttributes({ scrollSpeed: value })
							}
							min={0.1}
							max={2}
							step={0.1}
							help={__(
								'Controls how fast images move based on scroll',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Image Height', 'designsetgo')}
						hasValue={() => imageHeight !== '200px'}
						onDeselect={() =>
							setAttributes({ imageHeight: '200px' })
						}
						isShownByDefault
					>
						<UnitControl
							label={__('Image Height', 'designsetgo')}
							value={imageHeight}
							onChange={(value) =>
								setAttributes({ imageHeight: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Image Width', 'designsetgo')}
						hasValue={() => imageWidth !== '300px'}
						onDeselect={() =>
							setAttributes({ imageWidth: '300px' })
						}
						isShownByDefault
					>
						<UnitControl
							label={__('Image Width', 'designsetgo')}
							value={imageWidth}
							onChange={(value) =>
								setAttributes({ imageWidth: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Border Radius', 'designsetgo')}
						hasValue={() => borderRadius !== '8px'}
						onDeselect={() =>
							setAttributes({ borderRadius: '8px' })
						}
						isShownByDefault
					>
						<UnitControl
							label={__('Border Radius', 'designsetgo')}
							value={borderRadius}
							onChange={(value) =>
								setAttributes({ borderRadius: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Gap Between Images', 'designsetgo')}
						hasValue={() => gap !== '20px'}
						onDeselect={() => setAttributes({ gap: '20px' })}
						isShownByDefault
					>
						<UnitControl
							label={__('Gap Between Images', 'designsetgo')}
							value={gap}
							onChange={(value) => setAttributes({ gap: value })}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Gap Between Rows', 'designsetgo')}
						hasValue={() => rowGap !== '20px'}
						onDeselect={() => setAttributes({ rowGap: '20px' })}
						isShownByDefault
					>
						<UnitControl
							label={__('Gap Between Rows', 'designsetgo')}
							value={rowGap}
							onChange={(value) =>
								setAttributes({ rowGap: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

			<div {...blockProps}>
				{rows.map((row, rowIndex) => (
					<div
						key={rowIndex}
						className="dsgo-scroll-marquee__row"
						data-direction={row.direction}
					>
						<div className="dsgo-scroll-marquee__row-controls">
							<HStack justify="space-between" spacing={3}>
								<div className="dsgo-scroll-marquee__direction-control">
									<Button
										variant="secondary"
										size="small"
										onClick={() =>
											toggleRowDirection(rowIndex)
										}
										style={{ minWidth: '120px' }}
									>
										{row.direction === 'left'
											? '← Scroll Left'
											: 'Scroll Right →'}
									</Button>
								</div>
								<Button
									icon={close}
									label={__('Remove Row', 'designsetgo')}
									onClick={() => removeRow(rowIndex)}
									isDestructive
									size="small"
								/>
							</HStack>
						</div>

						<div className="dsgo-scroll-marquee__track">
							<div className="dsgo-scroll-marquee__track-segment">
								{row.images.map((image, imageIndex) => (
									<div
										key={imageIndex}
										className="dsgo-scroll-marquee__image-wrapper"
									>
										<img
											src={image.url}
											alt={image.alt}
											className="dsgo-scroll-marquee__image"
											style={imageInlineStyle}
										/>
										<Button
											icon={close}
											label={__(
												'Remove Image',
												'designsetgo'
											)}
											onClick={() =>
												removeImage(
													rowIndex,
													imageIndex
												)
											}
											className="dsgo-scroll-marquee__remove-image"
											isDestructive
											size="small"
										/>
									</div>
								))}

								<MediaUploadCheck>
									<MediaUpload
										onSelect={(images) =>
											onSelectImages(rowIndex, images)
										}
										allowedTypes={['image']}
										multiple={true}
										value={row.images.map((img) => img.id)}
										render={({ open }) => (
											<Button
												icon={plus}
												onClick={open}
												className="dsgo-scroll-marquee__add-images"
												variant="secondary"
											>
												{row.images.length === 0
													? __(
															'Add Images',
															'designsetgo'
														)
													: __(
															'Add More Images',
															'designsetgo'
														)}
											</Button>
										)}
									/>
								</MediaUploadCheck>
							</div>
						</div>
					</div>
				))}

				<Button
					icon={plus}
					onClick={addRow}
					className="dsgo-scroll-marquee__add-row"
					variant="primary"
				>
					{__('Add Row', 'designsetgo')}
				</Button>
			</div>
		</>
	);
}

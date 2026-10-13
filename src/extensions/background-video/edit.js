/**
 * Background Video Extension - Editor Controls
 *
 * Inspector controls and editor preview for background video.
 * Lazy-loaded to reduce initial bundle size.
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Button,
	ToggleControl,
	Notice,
} from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';

/**
 * Video file upload control
 *
 * @param {Object}   props               Component props
 * @param {string}   props.videoUrl      Current video URL
 * @param {Function} props.setAttributes Block setAttributes
 */
function VideoUploadControl({ videoUrl, setAttributes }) {
	return (
		<MediaUploadCheck>
			<MediaUpload
				onSelect={(media) => {
					if (media?.type === 'video' && media?.url) {
						setAttributes({ dsgoVideoUrl: media.url });
					}
				}}
				allowedTypes={['video']}
				value={videoUrl}
				render={({ open }) => (
					<div className="dsgo-video-upload">
						{videoUrl ? (
							<Fragment>
								<video
									src={videoUrl}
									autoPlay
									loop
									muted
									style={{
										width: '100%',
										maxHeight: '200px',
										objectFit: 'cover',
										borderRadius: '4px',
										marginBottom: '12px',
									}}
								/>
								<Button
									onClick={open}
									variant="secondary"
									isSmall
									style={{ marginRight: '8px' }}
								>
									{__('Replace Video', 'designsetgo')}
								</Button>
								<Button
									onClick={() =>
										setAttributes({
											dsgoVideoUrl: '',
											dsgoVideoPoster: '',
										})
									}
									variant="secondary"
									isDestructive
									isSmall
								>
									{__('Remove Video', 'designsetgo')}
								</Button>
							</Fragment>
						) : (
							<Button onClick={open} variant="primary">
								{__('Upload Video', 'designsetgo')}
							</Button>
						)}
					</div>
				)}
			/>
		</MediaUploadCheck>
	);
}

/**
 * Poster image upload control
 *
 * @param {Object}   props               Component props
 * @param {string}   props.posterUrl     Current poster URL
 * @param {Function} props.setAttributes Block setAttributes
 */
function PosterUploadControl({ posterUrl, setAttributes }) {
	return (
		<div style={{ marginTop: '16px' }}>
			<MediaUploadCheck>
				<MediaUpload
					onSelect={(media) => {
						if (media?.type === 'image' && media?.url) {
							setAttributes({ dsgoVideoPoster: media.url });
						}
					}}
					allowedTypes={['image']}
					value={posterUrl}
					render={({ open }) => (
						<div className="dsgo-poster-upload">
							<div
								style={{
									display: 'block',
									marginBottom: '8px',
									fontSize: '11px',
									fontWeight: '500',
									textTransform: 'uppercase',
								}}
							>
								{__('Poster Image (Optional)', 'designsetgo')}
							</div>
							{posterUrl ? (
								<Fragment>
									<img
										src={posterUrl}
										alt={__('Video poster', 'designsetgo')}
										style={{
											width: '100%',
											maxHeight: '100px',
											objectFit: 'cover',
											borderRadius: '4px',
											marginBottom: '8px',
										}}
									/>
									<Button
										onClick={open}
										variant="secondary"
										isSmall
										style={{ marginRight: '8px' }}
									>
										{__('Replace Poster', 'designsetgo')}
									</Button>
									<Button
										onClick={() =>
											setAttributes({
												dsgoVideoPoster: '',
											})
										}
										variant="secondary"
										isDestructive
										isSmall
									>
										{__('Remove Poster', 'designsetgo')}
									</Button>
								</Fragment>
							) : (
								<Button
									onClick={open}
									variant="secondary"
									isSmall
								>
									{__('Upload Poster', 'designsetgo')}
								</Button>
							)}
						</div>
					)}
				/>
			</MediaUploadCheck>
		</div>
	);
}

/**
 * Background video inspector controls panel
 *
 * @param {Object} props Block props
 */
export function BackgroundVideoPanel(props) {
	const { attributes, setAttributes, name, clientId } = props;
	const {
		dsgoVideoUrl,
		dsgoVideoPoster,
		dsgoVideoMuted,
		dsgoVideoLoop,
		dsgoVideoAutoplay,
		dsgoVideoMobileHide,
		dsgoVideoOverlayColor,
		shapeDividerTop,
		shapeDividerBottom,
	} = attributes;

	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Check if shape dividers are enabled (only applies to Section block)
	const hasShapeDivider =
		name === 'designsetgo/section' &&
		(!!shapeDividerTop || !!shapeDividerBottom);

	return (
		<Fragment>
			{dsgoVideoUrl && !hasShapeDivider && (
				<InspectorControls group="color">
					<ColorGradientSettingsDropdown
						panelId={clientId}
						title={__('Video Overlay', 'designsetgo')}
						settings={[
							{
								label: __('Video Overlay Color', 'designsetgo'),
								colorValue: decodeColorValue(
									dsgoVideoOverlayColor,
									colorGradientSettings
								),
								onColorChange: (color) => {
									setAttributes({
										dsgoVideoOverlayColor:
											encodeColorValue(
												color,
												colorGradientSettings
											) || '',
									});
								},
								clearable: true,
								enableAlpha: true,
							},
						]}
						{...colorGradientSettings}
					/>
				</InspectorControls>
			)}
			<InspectorControls>
				<PanelBody
					title={__('Background Video', 'designsetgo')}
					initialOpen={false}
				>
					{hasShapeDivider ? (
						<Fragment>
							<Notice status="warning" isDismissible={false}>
								{__(
									'Video backgrounds cannot be used with shape dividers.',
									'designsetgo'
								)}
							</Notice>
							<Button
								variant="secondary"
								onClick={() =>
									setAttributes({
										shapeDividerTop: '',
										shapeDividerBottom: '',
									})
								}
								style={{ marginTop: '12px' }}
							>
								{__('Remove Shape Dividers', 'designsetgo')}
							</Button>
						</Fragment>
					) : (
						<>
							<VideoUploadControl
								videoUrl={dsgoVideoUrl}
								setAttributes={setAttributes}
							/>

							{dsgoVideoUrl && (
								<Fragment>
									<PosterUploadControl
										posterUrl={dsgoVideoPoster}
										setAttributes={setAttributes}
									/>

									<ToggleControl
										label={__('Autoplay', 'designsetgo')}
										checked={dsgoVideoAutoplay}
										onChange={(value) =>
											setAttributes({
												dsgoVideoAutoplay: value,
											})
										}
										help={__(
											'Automatically start playing when page loads',
											'designsetgo'
										)}
										__nextHasNoMarginBottom
									/>

									<ToggleControl
										label={__('Loop', 'designsetgo')}
										checked={dsgoVideoLoop}
										onChange={(value) =>
											setAttributes({
												dsgoVideoLoop: value,
											})
										}
										help={__(
											'Restart video when it ends',
											'designsetgo'
										)}
										__nextHasNoMarginBottom
									/>

									<ToggleControl
										label={__('Muted', 'designsetgo')}
										checked={dsgoVideoMuted}
										onChange={(value) =>
											setAttributes({
												dsgoVideoMuted: value,
											})
										}
										help={__(
											'Mute audio (required for autoplay)',
											'designsetgo'
										)}
										__nextHasNoMarginBottom
									/>

									<ToggleControl
										label={__(
											'Hide on Mobile',
											'designsetgo'
										)}
										checked={dsgoVideoMobileHide}
										onChange={(value) =>
											setAttributes({
												dsgoVideoMobileHide: value,
											})
										}
										help={__(
											'Hide video on mobile devices to save bandwidth',
											'designsetgo'
										)}
										__nextHasNoMarginBottom
									/>
								</Fragment>
							)}
						</>
					)}
				</PanelBody>
			</InspectorControls>
		</Fragment>
	);
}

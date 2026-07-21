/**
 * Dynamic Image block — editor.
 *
 * Authors pick a Dynamic Tag source (featured image, ACF image,
 * site logo, etc.), optionally configure a size and focal point,
 * and set a fallback image for when the source is empty.
 *
 * The editor preview hits REST /dynamic-tags/preview so what you see
 * here matches what render.php will output on the frontend.
 *
 * Inspector follows the Theme 3 IA convention: a single canonical
 * Settings ToolsPanel with one DsgoInspectorPanel.Item per attribute.
 */
import {
	InspectorControls,
	useBlockProps,
	MediaUpload,
	MediaUploadCheck,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	Button,
	SelectControl,
	TextControl,
	FocalPointPicker,
	Placeholder,
	Spinner,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { store as editorStore } from '@wordpress/editor';

import {
	DynamicTagButton,
	useDynamicTagPreview,
} from '../../components/DynamicTagPicker';
import { DsgoInspectorPanel } from '../../components/shared';

const OBJECT_FIT_OPTIONS = [
	{ label: __('Cover', 'designsetgo'), value: 'cover' },
	{ label: __('Contain', 'designsetgo'), value: 'contain' },
	{ label: __('Fill', 'designsetgo'), value: 'fill' },
	{ label: __('Scale down', 'designsetgo'), value: 'scale-down' },
];

const ASPECT_RATIO_OPTIONS = [
	{ label: __('Original', 'designsetgo'), value: '' },
	{ label: __('Square (1 : 1)', 'designsetgo'), value: '1/1' },
	{ label: __('Landscape (16 : 9)', 'designsetgo'), value: '16/9' },
	{ label: __('Landscape (4 : 3)', 'designsetgo'), value: '4/3' },
	{ label: __('Landscape (3 : 2)', 'designsetgo'), value: '3/2' },
	{ label: __('Portrait (3 : 4)', 'designsetgo'), value: '3/4' },
	{ label: __('Portrait (2 : 3)', 'designsetgo'), value: '2/3' },
	{ label: __('Portrait (9 : 16)', 'designsetgo'), value: '9/16' },
];

const LINK_TARGET_OPTIONS = [
	{ label: __('Same tab', 'designsetgo'), value: '' },
	{ label: __('New tab', 'designsetgo'), value: '_blank' },
];

const REL_OPTIONS = [
	{ label: __('None', 'designsetgo'), value: '' },
	{ label: 'nofollow', value: 'nofollow' },
	{ label: 'noopener noreferrer', value: 'noopener noreferrer' },
	{
		label: 'nofollow noopener noreferrer',
		value: 'nofollow noopener noreferrer',
	},
	{ label: 'sponsored', value: 'sponsored' },
	{ label: 'ugc', value: 'ugc' },
];

const DEFAULTS = {
	source: '',
	sourceArgs: {},
	size: 'full',
	altOverride: '',
	focalPoint: { x: 0.5, y: 0.5 },
	aspectRatio: '',
	objectFit: 'cover',
	fallbackId: 0,
	fallbackUrl: '',
	fallbackAlt: '',
	href: '',
	linkTarget: '',
	rel: '',
};

export default function Edit({ attributes, setAttributes, clientId, context }) {
	const {
		source,
		sourceArgs,
		size,
		altOverride,
		focalPoint,
		aspectRatio,
		objectFit,
		fallbackId,
		fallbackUrl,
		fallbackAlt,
		href,
		linkTarget,
		rel,
	} = attributes;

	const { editorPostId, imageSizes } = useSelect((select) => {
		const editor = select(editorStore);
		const blockEditor = select(blockEditorStore);
		return {
			editorPostId: editor?.getCurrentPostId?.() || 0,
			imageSizes: blockEditor?.getSettings?.()?.imageSizes || [],
		};
	}, []);
	const postId = context?.postId || editorPostId;

	const sizeOptions = imageSizes.length
		? imageSizes.map((s) => ({ label: s.name, value: s.slug }))
		: [
				{ label: __('Thumbnail', 'designsetgo'), value: 'thumbnail' },
				{ label: __('Medium', 'designsetgo'), value: 'medium' },
				{ label: __('Large', 'designsetgo'), value: 'large' },
				{ label: __('Full', 'designsetgo'), value: 'full' },
			];

	// If a stored aspectRatio value isn't one of our presets (e.g. a
	// custom 4/5 saved by an earlier free-text version of this control),
	// surface it as an extra option so authors can still see and edit
	// it instead of the dropdown silently snapping to "Original".
	const aspectRatioOptions = useMemo(() => {
		if (
			!aspectRatio ||
			ASPECT_RATIO_OPTIONS.some((opt) => opt.value === aspectRatio)
		) {
			return ASPECT_RATIO_OPTIONS;
		}
		return [
			...ASPECT_RATIO_OPTIONS,
			{
				label: sprintf(
					/* translators: %s: custom aspect-ratio value such as 4/5 */
					__('Custom (%s)', 'designsetgo'),
					aspectRatio
				),
				value: aspectRatio,
			},
		];
	}, [aspectRatio]);

	const preview = useDynamicTagPreview({
		source,
		args: sourceArgs,
		postId,
		size,
	});

	const blockProps = useBlockProps({
		style: aspectRatio ? { aspectRatio } : undefined,
	});

	const sourceValue = source ? { source, args: sourceArgs } : null;

	const handleSourceChange = (next) => {
		if (!next) {
			setAttributes({ source: '', sourceArgs: {} });
			return;
		}
		setAttributes({ source: next.source, sourceArgs: next.args || {} });
	};

	const resolvedImage =
		preview.status === 'resolved' &&
		preview.returns === 'image' &&
		preview.value
			? preview.value
			: null;

	const displayImage =
		resolvedImage ||
		(fallbackUrl
			? { url: fallbackUrl, alt: fallbackAlt || '', width: 0, height: 0 }
			: null);

	const isDefaultFocalPoint = (fp) => !fp || (fp.x === 0.5 && fp.y === 0.5);

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() => setAttributes(DEFAULTS)}
				>
					<DsgoInspectorPanel.Item
						label={__('Dynamic source', 'designsetgo')}
						hasValue={() => source !== ''}
						onDeselect={() =>
							setAttributes({ source: '', sourceArgs: {} })
						}
						isShownByDefault
					>
						<DynamicTagButton
							value={sourceValue}
							onChange={handleSourceChange}
							returns="image"
							postId={postId}
						/>
						{source && (
							<p className="dsgo-dynamic-image__source-summary">
								<code>{source}</code>
							</p>
						)}
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Image size', 'designsetgo')}
						hasValue={() => size !== 'full'}
						onDeselect={() => setAttributes({ size: 'full' })}
						isShownByDefault
					>
						<SelectControl
							label={__('Image size', 'designsetgo')}
							value={size}
							options={sizeOptions}
							onChange={(value) => setAttributes({ size: value })}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Alt text override', 'designsetgo')}
						hasValue={() => altOverride !== ''}
						onDeselect={() => setAttributes({ altOverride: '' })}
						isShownByDefault
					>
						<TextControl
							label={__('Alt text override', 'designsetgo')}
							help={__(
								'Leave blank to use the alt text from the source.',
								'designsetgo'
							)}
							value={altOverride}
							onChange={(value) =>
								setAttributes({ altOverride: value })
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Aspect ratio', 'designsetgo')}
						hasValue={() => aspectRatio !== ''}
						onDeselect={() => setAttributes({ aspectRatio: '' })}
						isShownByDefault
					>
						<SelectControl
							label={__('Aspect ratio', 'designsetgo')}
							value={aspectRatio}
							options={aspectRatioOptions}
							onChange={(value) =>
								setAttributes({ aspectRatio: value })
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Object fit', 'designsetgo')}
						hasValue={() => objectFit !== 'cover'}
						onDeselect={() => setAttributes({ objectFit: 'cover' })}
						isShownByDefault
					>
						<SelectControl
							label={__('Object fit', 'designsetgo')}
							value={objectFit}
							options={OBJECT_FIT_OPTIONS}
							onChange={(value) =>
								setAttributes({ objectFit: value })
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</DsgoInspectorPanel.Item>

					{displayImage?.url && (
						<DsgoInspectorPanel.Item
							label={__('Focal point', 'designsetgo')}
							hasValue={() => !isDefaultFocalPoint(focalPoint)}
							onDeselect={() =>
								setAttributes({
									focalPoint: { x: 0.5, y: 0.5 },
								})
							}
							isShownByDefault={false}
						>
							<FocalPointPicker
								label={__('Focal point', 'designsetgo')}
								url={displayImage.url}
								value={focalPoint}
								onChange={(value) =>
									setAttributes({ focalPoint: value })
								}
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					<DsgoInspectorPanel.Item
						label={__('Fallback image', 'designsetgo')}
						hasValue={() => fallbackId !== 0 || fallbackUrl !== ''}
						onDeselect={() =>
							setAttributes({
								fallbackId: 0,
								fallbackUrl: '',
								fallbackAlt: '',
							})
						}
						isShownByDefault
					>
						<p className="dsgo-dynamic-image__help">
							{__(
								'Shown when the source is empty — e.g. a post without a featured image.',
								'designsetgo'
							)}
						</p>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={(media) =>
									setAttributes({
										fallbackId: media.id,
										fallbackUrl: media.url,
										fallbackAlt: media.alt || '',
									})
								}
								allowedTypes={['image']}
								value={fallbackId}
								render={({ open }) => (
									<HStack>
										<Button
											variant="secondary"
											onClick={open}
										>
											{fallbackId
												? __('Replace', 'designsetgo')
												: __(
														'Select image',
														'designsetgo'
													)}
										</Button>
										{fallbackId && (
											<Button
												variant="tertiary"
												isDestructive
												onClick={() =>
													setAttributes({
														fallbackId: 0,
														fallbackUrl: '',
														fallbackAlt: '',
													})
												}
											>
												{__('Remove', 'designsetgo')}
											</Button>
										)}
									</HStack>
								)}
							/>
						</MediaUploadCheck>
						{fallbackUrl && (
							<img
								src={fallbackUrl}
								alt=""
								className="dsgo-dynamic-image__fallback-preview"
							/>
						)}
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Link URL', 'designsetgo')}
						hasValue={() => href !== ''}
						onDeselect={() =>
							setAttributes({ href: '', linkTarget: '', rel: '' })
						}
						isShownByDefault={false}
					>
						<TextControl
							label={__('Link URL', 'designsetgo')}
							value={href}
							onChange={(value) => setAttributes({ href: value })}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</DsgoInspectorPanel.Item>

					{href && (
						<DsgoInspectorPanel.Item
							label={__('Open in', 'designsetgo')}
							hasValue={() => linkTarget !== ''}
							onDeselect={() => setAttributes({ linkTarget: '' })}
							isShownByDefault={false}
						>
							<SelectControl
								label={__('Open in', 'designsetgo')}
								value={linkTarget}
								options={LINK_TARGET_OPTIONS}
								onChange={(value) =>
									setAttributes({ linkTarget: value })
								}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</DsgoInspectorPanel.Item>
					)}

					{href && (
						<DsgoInspectorPanel.Item
							label={__('Rel attribute', 'designsetgo')}
							hasValue={() => rel !== ''}
							onDeselect={() => setAttributes({ rel: '' })}
							isShownByDefault={false}
						>
							<SelectControl
								label={__('Rel attribute', 'designsetgo')}
								value={rel}
								options={REL_OPTIONS}
								onChange={(value) =>
									setAttributes({ rel: value })
								}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</DsgoInspectorPanel.Item>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			<figure {...blockProps}>
				{!source && !fallbackUrl && (
					<Placeholder
						icon="format-image"
						label={__('Dynamic Image', 'designsetgo')}
						instructions={__(
							'Pick a dynamic source (featured image, ACF field, site logo, …).',
							'designsetgo'
						)}
					>
						<DynamicTagButton
							value={null}
							onChange={handleSourceChange}
							returns="image"
							postId={postId}
							size="default"
							variant="primary"
						/>
					</Placeholder>
				)}

				{preview.status === 'loading' && source && !displayImage && (
					<div className="dsgo-dynamic-image__loading">
						<Spinner />
					</div>
				)}

				{displayImage?.url && (
					<img
						src={displayImage.url}
						alt={altOverride || displayImage.alt || ''}
						style={{
							objectFit,
							objectPosition: focalPoint
								? `${Math.round(focalPoint.x * 100)}% ${Math.round(focalPoint.y * 100)}%`
								: undefined,
						}}
					/>
				)}

				{source && preview.status === 'empty' && !displayImage && (
					<div className="dsgo-dynamic-image__empty">
						{__(
							'Source is empty on this post. Add a fallback image to always show something.',
							'designsetgo'
						)}
					</div>
				)}
			</figure>
		</>
	);
}

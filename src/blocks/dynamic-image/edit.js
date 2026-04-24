/**
 * Dynamic Image block — editor.
 *
 * Authors pick a Dynamic Tag source (featured image, ACF image,
 * site logo, etc.), optionally configure a size and focal point,
 * and set a fallback image for when the source is empty.
 *
 * The editor preview hits REST /dynamic-tags/preview so what you see
 * here matches what render.php will output on the frontend.
 */
import {
	InspectorControls,
	useBlockProps,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Button,
	SelectControl,
	TextControl,
	FocalPointPicker,
	Placeholder,
	Spinner,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';

import { DynamicTagButton, useDynamicTagPreview } from '../../components/DynamicTagPicker';

const SIZE_OPTIONS = [
	{ label: __( 'Thumbnail', 'designsetgo' ), value: 'thumbnail' },
	{ label: __( 'Medium', 'designsetgo' ), value: 'medium' },
	{ label: __( 'Large', 'designsetgo' ), value: 'large' },
	{ label: __( 'Full', 'designsetgo' ), value: 'full' },
];

const OBJECT_FIT_OPTIONS = [
	{ label: __( 'Cover', 'designsetgo' ), value: 'cover' },
	{ label: __( 'Contain', 'designsetgo' ), value: 'contain' },
	{ label: __( 'Fill', 'designsetgo' ), value: 'fill' },
	{ label: __( 'Scale down', 'designsetgo' ), value: 'scale-down' },
];

export default function Edit( { attributes, setAttributes, context } ) {
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

	const editorPostId = useSelect(
		( select ) => select( editorStore )?.getCurrentPostId?.() || 0,
		[]
	);
	const postId = context?.postId || editorPostId;

	const preview = useDynamicTagPreview( {
		source,
		args: sourceArgs,
		postId,
		size,
	} );

	const blockProps = useBlockProps( {
		style: aspectRatio ? { aspectRatio } : undefined,
	} );

	const sourceValue = source ? { source, args: sourceArgs } : null;

	const handleSourceChange = ( next ) => {
		if ( ! next ) {
			setAttributes( { source: '', sourceArgs: {} } );
			return;
		}
		setAttributes( { source: next.source, sourceArgs: next.args || {} } );
	};

	const resolvedImage =
		preview.status === 'resolved' && preview.returns === 'image' && preview.value
			? preview.value
			: null;

	const displayImage = resolvedImage
		|| ( fallbackUrl
			? { url: fallbackUrl, alt: fallbackAlt || '', width: 0, height: 0 }
			: null );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Dynamic Source', 'designsetgo' ) } initialOpen>
					<VStack spacing={ 3 }>
						<DynamicTagButton
							value={ sourceValue }
							onChange={ handleSourceChange }
							returns="image"
							postId={ postId }
						/>
						{ source && (
							<p className="dsgo-dynamic-image__source-summary">
								<code>{ source }</code>
							</p>
						) }

						<SelectControl
							label={ __( 'Image size', 'designsetgo' ) }
							value={ size }
							options={ SIZE_OPTIONS }
							onChange={ ( value ) => setAttributes( { size: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<TextControl
							label={ __( 'Alt text override', 'designsetgo' ) }
							help={ __( 'Leave blank to use the alt text from the source.', 'designsetgo' ) }
							value={ altOverride }
							onChange={ ( value ) => setAttributes( { altOverride: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</VStack>
				</PanelBody>

				<PanelBody title={ __( 'Display', 'designsetgo' ) } initialOpen={ false }>
					<VStack spacing={ 3 }>
						<TextControl
							label={ __( 'Aspect ratio', 'designsetgo' ) }
							help={ __( 'e.g. 16/9, 1/1. Leave blank for natural.', 'designsetgo' ) }
							value={ aspectRatio }
							onChange={ ( value ) => setAttributes( { aspectRatio: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<SelectControl
							label={ __( 'Object fit', 'designsetgo' ) }
							value={ objectFit }
							options={ OBJECT_FIT_OPTIONS }
							onChange={ ( value ) => setAttributes( { objectFit: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						{ displayImage?.url && (
							<FocalPointPicker
								label={ __( 'Focal point', 'designsetgo' ) }
								url={ displayImage.url }
								value={ focalPoint }
								onChange={ ( value ) => setAttributes( { focalPoint: value } ) }
								__nextHasNoMarginBottom
							/>
						) }
					</VStack>
				</PanelBody>

				<PanelBody title={ __( 'Fallback image', 'designsetgo' ) } initialOpen={ false }>
					<p className="dsgo-dynamic-image__help">
						{ __( 'Shown when the dynamic source is empty — e.g. a post without a featured image.', 'designsetgo' ) }
					</p>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ ( media ) =>
								setAttributes( {
									fallbackId: media.id,
									fallbackUrl: media.url,
									fallbackAlt: media.alt || '',
								} )
							}
							allowedTypes={ [ 'image' ] }
							value={ fallbackId }
							render={ ( { open } ) => (
								<HStack>
									<Button variant="secondary" onClick={ open }>
										{ fallbackId ? __( 'Replace', 'designsetgo' ) : __( 'Select image', 'designsetgo' ) }
									</Button>
									{ fallbackId && (
										<Button
											variant="tertiary"
											isDestructive
											onClick={ () =>
												setAttributes( { fallbackId: 0, fallbackUrl: '', fallbackAlt: '' } )
											}
										>
											{ __( 'Remove', 'designsetgo' ) }
										</Button>
									) }
								</HStack>
							) }
						/>
					</MediaUploadCheck>
					{ fallbackUrl && (
						<img src={ fallbackUrl } alt="" className="dsgo-dynamic-image__fallback-preview" />
					) }
				</PanelBody>

				<PanelBody title={ __( 'Link', 'designsetgo' ) } initialOpen={ false }>
					<VStack spacing={ 3 }>
						<TextControl
							label={ __( 'URL', 'designsetgo' ) }
							value={ href }
							onChange={ ( value ) => setAttributes( { href: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<SelectControl
							label={ __( 'Open in', 'designsetgo' ) }
							value={ linkTarget }
							options={ [
								{ label: __( 'Same tab', 'designsetgo' ), value: '' },
								{ label: __( 'New tab', 'designsetgo' ), value: '_blank' },
							] }
							onChange={ ( value ) => setAttributes( { linkTarget: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<TextControl
							label={ __( 'Rel attribute', 'designsetgo' ) }
							value={ rel }
							onChange={ ( value ) => setAttributes( { rel: value } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</VStack>
				</PanelBody>
			</InspectorControls>

			<figure { ...blockProps }>
				{ ! source && ! fallbackUrl && (
					<Placeholder
						icon="format-image"
						label={ __( 'Dynamic Image', 'designsetgo' ) }
						instructions={ __( 'Pick a dynamic source (featured image, ACF field, site logo, …).', 'designsetgo' ) }
					>
						<DynamicTagButton
							value={ null }
							onChange={ handleSourceChange }
							returns="image"
							postId={ postId }
							size="default"
							variant="primary"
						/>
					</Placeholder>
				) }

				{ preview.status === 'loading' && source && ! displayImage && (
					<div className="dsgo-dynamic-image__loading">
						<Spinner />
					</div>
				) }

				{ displayImage?.url && (
					<img
						src={ displayImage.url }
						alt={ altOverride || displayImage.alt || '' }
						style={ {
							objectFit,
							objectPosition: focalPoint
								? `${ Math.round( focalPoint.x * 100 ) }% ${ Math.round( focalPoint.y * 100 ) }%`
								: undefined,
						} }
					/>
				) }

				{ source && preview.status === 'empty' && ! displayImage && (
					<div className="dsgo-dynamic-image__empty">
						{ __( 'Source is empty on this post. Add a fallback image to always show something.', 'designsetgo' ) }
					</div>
				) }
			</figure>
		</>
	);
}

/**
 * Gallery Settings Panel Component
 *
 * Renders DsgoInspectorPanel.Item entries for the modal's gallery
 * attributes, meant to be composed inside the Settings panel in
 * modal/edit.js.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import {
	TextControl,
	RangeControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

export default function GallerySettings({ attributes, setAttributes }) {
	const {
		galleryGroupId,
		galleryIndex,
		showGalleryNavigation,
		navigationStyle,
		navigationPosition,
	} = attributes;

	const isGalleryEnabled = !!galleryGroupId;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Gallery Group ID', 'designsetgo')}
				hasValue={() => galleryGroupId !== ''}
				onDeselect={() =>
					setAttributes({
						galleryGroupId: '',
						galleryIndex: 0,
						showGalleryNavigation: true,
					})
				}
				isShownByDefault={false}
			>
				<TextControl
					label={__('Gallery Group ID', 'designsetgo')}
					value={galleryGroupId}
					onChange={(value) =>
						setAttributes({ galleryGroupId: value })
					}
					help={__(
						'Enter a group ID to link this modal with others (e.g., "product-gallery"). Leave empty to disable gallery navigation.',
						'designsetgo'
					)}
					placeholder="e.g., product-gallery"
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{isGalleryEnabled && (
				<DsgoInspectorPanel.Item
					label={__('Gallery Index', 'designsetgo')}
					hasValue={() => galleryIndex !== 0}
					onDeselect={() => setAttributes({ galleryIndex: 0 })}
					isShownByDefault={false}
				>
					<RangeControl
						label={__('Gallery Index', 'designsetgo')}
						value={galleryIndex}
						onChange={(value) =>
							setAttributes({ galleryIndex: value })
						}
						min={0}
						max={50}
						help={__(
							'Position of this modal in the gallery sequence (0-based).',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{isGalleryEnabled && (
				<DsgoInspectorPanel.Item
					label={__('Show Gallery Navigation', 'designsetgo')}
					hasValue={() => showGalleryNavigation !== true}
					onDeselect={() =>
						setAttributes({ showGalleryNavigation: true })
					}
					isShownByDefault={false}
				>
					<ToggleControl
						label={__('Show Navigation', 'designsetgo')}
						checked={showGalleryNavigation}
						onChange={(value) =>
							setAttributes({ showGalleryNavigation: value })
						}
						help={__(
							'Display previous/next navigation buttons.',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{isGalleryEnabled && showGalleryNavigation && (
				<DsgoInspectorPanel.Item
					label={__('Navigation Style', 'designsetgo')}
					hasValue={() => navigationStyle !== 'arrows'}
					onDeselect={() =>
						setAttributes({ navigationStyle: 'arrows' })
					}
					isShownByDefault={false}
				>
					<SelectControl
						label={__('Navigation Style', 'designsetgo')}
						value={navigationStyle}
						options={[
							{
								label: __('Arrows', 'designsetgo'),
								value: 'arrows',
							},
							{
								label: __('Chevrons', 'designsetgo'),
								value: 'chevrons',
							},
							{
								label: __('Text', 'designsetgo'),
								value: 'text',
							},
						]}
						onChange={(value) =>
							setAttributes({ navigationStyle: value })
						}
						help={__(
							'Choose how navigation buttons appear.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{isGalleryEnabled && showGalleryNavigation && (
				<DsgoInspectorPanel.Item
					label={__('Navigation Position', 'designsetgo')}
					hasValue={() => navigationPosition !== 'sides'}
					onDeselect={() =>
						setAttributes({ navigationPosition: 'sides' })
					}
					isShownByDefault={false}
				>
					<SelectControl
						label={__('Navigation Position', 'designsetgo')}
						value={navigationPosition}
						options={[
							{
								label: __('Sides', 'designsetgo'),
								value: 'sides',
							},
							{
								label: __('Bottom', 'designsetgo'),
								value: 'bottom',
							},
							{
								label: __('Top', 'designsetgo'),
								value: 'top',
							},
						]}
						onChange={(value) =>
							setAttributes({ navigationPosition: value })
						}
						help={__(
							'Position of navigation buttons.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}
		</>
	);
}

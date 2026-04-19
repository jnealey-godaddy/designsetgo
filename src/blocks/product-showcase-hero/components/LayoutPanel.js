/**
 * Layout Inspector Panel
 *
 * Renders DsgoInspectorPanel.Item entries for media position, content
 * alignment, min height, and focal point. Meant to be composed inside
 * the Settings DsgoInspectorPanel in product-showcase-hero/edit.js.
 *
 * @since 2.1.0
 */

import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	FocalPointPicker,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

export default function LayoutPanel({
	attributes,
	setAttributes,
	units,
	imageUrl,
}) {
	const { layout, contentVerticalAlignment, minHeight, mediaFocalPoint } =
		attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Media Position', 'designsetgo')}
				hasValue={() => layout !== 'media-left'}
				onDeselect={() => setAttributes({ layout: 'media-left' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Media Position', 'designsetgo')}
					value={layout}
					options={[
						{
							label: __('Left', 'designsetgo'),
							value: 'media-left',
						},
						{
							label: __('Right', 'designsetgo'),
							value: 'media-right',
						},
					]}
					onChange={(value) => setAttributes({ layout: value })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Content Vertical Alignment', 'designsetgo')}
				hasValue={() => contentVerticalAlignment !== 'center'}
				onDeselect={() =>
					setAttributes({ contentVerticalAlignment: 'center' })
				}
				isShownByDefault
			>
				<SelectControl
					label={__('Content Vertical Alignment', 'designsetgo')}
					value={contentVerticalAlignment}
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
						setAttributes({ contentVerticalAlignment: value })
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Min Height', 'designsetgo')}
				hasValue={() => minHeight !== '500px'}
				onDeselect={() => setAttributes({ minHeight: '500px' })}
				isShownByDefault
			>
				<UnitControl
					label={__('Min Height', 'designsetgo')}
					value={minHeight}
					onChange={(value) => setAttributes({ minHeight: value })}
					units={units}
					__next40pxDefaultSize
				/>
			</DsgoInspectorPanel.Item>

			{imageUrl && (
				<DsgoInspectorPanel.Item
					label={__('Focal Point', 'designsetgo')}
					hasValue={() =>
						mediaFocalPoint?.x !== 0.5 || mediaFocalPoint?.y !== 0.5
					}
					onDeselect={() =>
						setAttributes({
							mediaFocalPoint: { x: 0.5, y: 0.5 },
						})
					}
					isShownByDefault
				>
					<FocalPointPicker
						label={__('Focal Point', 'designsetgo')}
						url={imageUrl}
						value={mediaFocalPoint}
						onChange={(value) =>
							setAttributes({ mediaFocalPoint: value })
						}
					/>
				</DsgoInspectorPanel.Item>
			)}
		</>
	);
}

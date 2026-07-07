/**
 * Section Divider — edit
 *
 * Renders the identical classed inner div as save.js so the shape paints
 * live on the canvas: same outer/inner structure, same emit-only-when-
 * differs inline style contract (both derived from ./utils so they can't
 * drift). Inspector controls live in the Theme-3 Settings panel
 * (shape/height/width/flip) plus the shared color group (fill).
 *
 * @since 2.7.0
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
} from '@wordpress/block-editor';
import { useBlockColors } from '../../hooks';
import {
	getDividerStyle,
	getDividerShapeClass,
	getDividerWrapperStyle,
} from './utils';
import SectionDividerControls from './components/SectionDividerControls';

export default function SectionDividerEdit({
	attributes,
	setAttributes,
	clientId,
}) {
	const { settings: colorSettings, colorGradientSettings } = useBlockColors({
		attributes,
		setAttributes,
		entries: [
			{
				label: __('Background', 'designsetgo'),
				attribute: 'backgroundColor',
			},
			{ label: __('Fill', 'designsetgo'), attribute: 'fillColor' },
		],
	});

	// Derived from the same ./utils helpers save.js uses, so the editor
	// canvas matches the frontend output byte for byte.
	const style = getDividerStyle(attributes);
	const shapeClass = getDividerShapeClass(attributes.shape);

	const blockProps = useBlockProps({
		style: getDividerWrapperStyle(attributes),
	});

	return (
		<>
			<InspectorControls>
				<SectionDividerControls
					attributes={attributes}
					setAttributes={setAttributes}
					clientId={clientId}
				/>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Color', 'designsetgo')}
					settings={colorSettings}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<div {...blockProps}>
				<div
					className={`dsgo-section-divider__shape dsgo-shape-divider ${shapeClass}`}
					style={Object.keys(style).length ? style : undefined}
					aria-hidden="true"
				/>
			</div>
		</>
	);
}

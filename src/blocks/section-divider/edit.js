/**
 * Section Divider — edit
 *
 * Renders the identical classed inner div as save.js so the shape paints
 * live on the canvas: same outer/inner structure, same emit-only-when-
 * differs inline style contract. Inspector controls live in the Theme-3
 * Settings panel (shape/height/width/flip) plus the shared color group
 * (fill).
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
import SectionDividerControls from './components/SectionDividerControls';

export default function SectionDividerEdit( {
	attributes,
	setAttributes,
	clientId,
} ) {
	const { shape, height, width, flipX, flipY, fillColor } = attributes;

	const { settings: colorSettings, colorGradientSettings } = useBlockColors( {
		attributes,
		setAttributes,
		entries: [ { label: __( 'Fill', 'designsetgo' ), attribute: 'fillColor' } ],
	} );

	// Mirrors save.js exactly — same vars, same emit-only-when-differs guards —
	// so the editor canvas matches the frontend output byte for byte.
	const style = {};

	if ( fillColor ) {
		style[ '--dsgo-section-divider-fill' ] = fillColor;
	}

	if ( typeof height === 'number' ) {
		style[ '--dsgo-shape-height' ] = `${ height }px`;
	}

	if ( width !== 100 ) {
		style[ '--dsgo-shape-width' ] = `${ width }%`;
	}

	if ( flipX ) {
		style[ '--dsgo-shape-flip-x' ] = -1;
	}

	if ( flipY ) {
		style[ '--dsgo-shape-flip-y' ] = -1;
	}

	const shapeClass =
		shape === 'inherit' ? 'is-shape-inherit' : `is-shape-${ shape }`;

	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<SectionDividerControls
					attributes={ attributes }
					setAttributes={ setAttributes }
					clientId={ clientId }
				/>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={ clientId }
					title={ __( 'Color', 'designsetgo' ) }
					settings={ colorSettings }
					{ ...colorGradientSettings }
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<div
					className={ `dsgo-section-divider__shape dsgo-shape-divider ${ shapeClass }` }
					style={ Object.keys( style ).length ? style : undefined }
				/>
			</div>
		</>
	);
}

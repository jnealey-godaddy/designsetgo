/**
 * Section Divider — save
 *
 * Single-div output: a full-width, solid-filled, masked shape (the same
 * `is-shape-<slug>` CSS mask-image library the section block's shape
 * dividers use). Custom props are emitted only when they differ from the
 * CSS-inherited default, so a fully-default divider serializes as bare
 * `is-shape-inherit` markup with no inline style.
 *
 * @since 2.7.0
 */

import { useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { shape, height, width, flipX, flipY, fillColor } = attributes;

	const style = {};

	if ( fillColor ) {
		style[ '--dsgo-section-divider-fill' ] = fillColor;
	}

	if ( typeof height === 'number' ) {
		style[ '--dsgo-shape-height' ] = `${ height }px`;
	}

	if ( width && width !== 100 ) {
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

	return (
		<div { ...useBlockProps.save() }>
			<div
				className={ `dsgo-section-divider__shape dsgo-shape-divider ${ shapeClass }` }
				style={ Object.keys( style ).length ? style : undefined }
			/>
		</div>
	);
}

/**
 * Section Divider — save
 *
 * Single-div output: a full-width, solid-filled, masked shape (the same
 * `is-shape-<slug>` CSS mask-image library the section block's shape
 * dividers use). Custom props are emitted only when they differ from the
 * CSS-inherited default, so a fully-default divider serializes as bare
 * `is-shape-inherit` markup with no inline style. The style + shape-class
 * logic is shared with edit.js via ./utils so the two can't drift.
 *
 * @since 2.7.0
 */

import { useBlockProps } from '@wordpress/block-editor';
import { getDividerStyle, getDividerShapeClass } from './utils';

export default function save( { attributes } ) {
	const style = getDividerStyle( attributes );
	const shapeClass = getDividerShapeClass( attributes.shape );

	return (
		<div { ...useBlockProps.save() }>
			<div
				className={ `dsgo-section-divider__shape dsgo-shape-divider ${ shapeClass }` }
				style={ Object.keys( style ).length ? style : undefined }
				aria-hidden="true"
			/>
		</div>
	);
}

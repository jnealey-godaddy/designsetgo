/**
 * Blobs Block - Save Component
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { hasExplicitString } from '../../utils/has-explicit-value';

export default function BlobsSave({ attributes }) {
	const {
		blobShape,
		blobAnimation,
		animationDuration,
		animationEasing,
		size,
		height,
		maxWidth,
		enableOverlay,
		overlayColor,
		overlayOpacity,
	} = attributes;

	// Same classes as edit.js - MUST MATCH
	const blobClasses = classnames('dsgo-blobs', {
		[`dsgo-blobs--${blobShape}`]: blobShape,
		[`dsgo-blobs--${blobAnimation}`]:
			blobAnimation && blobAnimation !== 'none',
	});

	// Apply animation settings as CSS custom properties - MUST MATCH edit.js
	const customStyles = {
		'--dsgo-blob-size': size,
		...(height ? { '--dsgo-blob-height': height } : {}),
		'--dsgo-blob-animation-duration': animationDuration,
		'--dsgo-blob-animation-easing': animationEasing,
	};

	// Optional max-width constraint on the wrapper - MUST MATCH edit.js.
	// Emitted as a kit-controllable CSS custom property so themes/kits can
	// retheme via --dsgo-blob-max-width or the blobs max-width token; the raw
	// max-width and centering margins live in the stylesheet, not inline. The
	// class + var are only added when the author sets an explicit maxWidth, so
	// unset blobs serialize byte-identically to the pre-maxWidth output.
	const hasMaxWidth = hasExplicitString(maxWidth);

	// Get block props with our wrapper class
	const blockProps = useBlockProps.save({
		className: classnames('dsgo-blobs-wrapper', {
			'dsgo-has-max-width': hasMaxWidth,
		}),
		...(hasMaxWidth && {
			style: { '--dsgo-blob-max-width': maxWidth },
		}),
	});

	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-blobs__content',
	});

	return (
		<div {...blockProps}>
			<div
				className={blobClasses}
				style={customStyles}
				data-blob-animation={blobAnimation}
			>
				{enableOverlay && (
					<div
						className="dsgo-blobs__overlay"
						style={{
							backgroundColor: convertColorToCSSVar(overlayColor),
							opacity: overlayOpacity / 100,
						}}
					/>
				)}
				<div className="dsgo-blobs__shape">
					<div {...innerBlocksProps} />
				</div>
			</div>
		</div>
	);
}

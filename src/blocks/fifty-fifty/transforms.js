/**
 * Fifty Fifty Block - Transforms
 *
 * Converts to and from core Media & Text, which has the same shape: one
 * image on one side, blocks on the other.
 */

import { createBlock } from '@wordpress/blocks';

const transforms = {
	from: [
		{
			type: 'block',
			blocks: ['core/media-text'],
			// Fifty Fifty only shows images.
			isMatch: ({ mediaType, mediaUrl }) =>
				!mediaUrl || mediaType === 'image',
			transform: (attributes, innerBlocks) =>
				createBlock(
					'designsetgo/fifty-fifty',
					{
						mediaPosition:
							attributes.mediaPosition === 'right'
								? 'right'
								: 'left',
						mediaId: attributes.mediaId || 0,
						mediaUrl: attributes.mediaUrl || '',
						mediaAlt: attributes.mediaAlt || '',
						...(attributes.focalPoint && {
							focalPoint: attributes.focalPoint,
						}),
						...(attributes.verticalAlignment && {
							verticalAlignment: attributes.verticalAlignment,
						}),
						...(attributes.anchor && { anchor: attributes.anchor }),
					},
					innerBlocks
				),
		},
	],
	to: [
		{
			type: 'block',
			blocks: ['core/media-text'],
			transform: (attributes, innerBlocks) =>
				createBlock(
					'core/media-text',
					{
						align: attributes.align,
						mediaPosition: attributes.mediaPosition,
						...(attributes.mediaUrl && {
							mediaId: attributes.mediaId,
							mediaUrl: attributes.mediaUrl,
							mediaAlt: attributes.mediaAlt,
							mediaType: 'image',
							focalPoint: attributes.focalPoint,
							// Fifty Fifty always fills its half edge to edge.
							imageFill: true,
						}),
						verticalAlignment: attributes.verticalAlignment,
						...(attributes.anchor && { anchor: attributes.anchor }),
					},
					innerBlocks
				),
		},
	],
};

export default transforms;

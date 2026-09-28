/**
 * Fifty Fifty Block - Transforms
 *
 * Converts to and from core Media & Text, which has the same shape: one
 * image on one side, blocks on the other.
 */

import { createBlock } from '@wordpress/blocks';
import { pickColors } from '../../utils/pick-attributes';

// Fifty Fifty supports colour and margin; it has no border or padding.
const FIFTY_FIFTY_STYLE = { margin: true };

const transforms = {
	from: [
		{
			type: 'block',
			blocks: ['core/media-text'],
			// Fifty Fifty only shows images, and has no image link: a linked
			// Media & Text would lose its link, so it isn't offered.
			isMatch: ({ mediaType, mediaUrl, href }) =>
				(!mediaUrl || mediaType === 'image') && !href,
			transform: (attributes, innerBlocks) =>
				createBlock(
					'designsetgo/fifty-fifty',
					{
						// Fifty Fifty is always full width (its only alignment).
						...pickColors(attributes, FIFTY_FIFTY_STYLE),
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
						...pickColors(attributes, FIFTY_FIFTY_STYLE),
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

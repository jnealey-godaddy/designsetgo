/**
 * Flip Card Face - Save Component
 *
 * @since 2.0.52
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function FlipCardFaceSave({ attributes }) {
	const side = attributes.side === 'back' ? 'back' : 'front';

	const blockProps = useBlockProps.save({
		className: `dsgo-flip-card__face dsgo-flip-card__${side}`,
	});

	const innerBlocksProps = useInnerBlocksProps.save(blockProps);

	return <div {...innerBlocksProps} />;
}

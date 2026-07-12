/**
 * Flip Card Block - Save Component
 *
 * Saves the flip card with front and back content.
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

/**
 * Flip Card Save Component
 *
 * @param {Object} props            Component props
 * @param {Object} props.attributes Block attributes
 * @return {JSX.Element} Save component
 */
export default function FlipCardSave({ attributes }) {
	const { flipTrigger, flipEffect, flipDirection, flipDuration } = attributes;

	// Block wrapper props
	const blockProps = useBlockProps.save({
		className: `dsgo-flip-card dsgo-flip-card--${flipTrigger} dsgo-flip-card--effect-${flipEffect} dsgo-flip-card--${flipDirection}`,
		// `width: 100%` is NOT serialized — it is constant for every card and
		// style.scss already declares it on `.dsgo-flip-card`. Only the
		// author-controlled flip duration is written inline.
		style: {
			'--dsgo-flip-duration': flipDuration,
		},
		'data-flip-trigger': flipTrigger,
		'data-flip-effect': flipEffect,
		'data-flip-direction': flipDirection,
	});

	// Inner blocks props
	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-flip-card__container',
	});

	return (
		<div {...blockProps}>
			<div {...innerBlocksProps} />
		</div>
	);
}

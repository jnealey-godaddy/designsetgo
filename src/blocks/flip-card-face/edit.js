/**
 * Flip Card Face - Edit Component
 *
 * Replaces the separate flip-card-front and flip-card-back blocks. The
 * `side` attribute (front|back) controls placement inside the parent
 * flip card. Kept as a child-only block so it can only appear inside
 * designsetgo/flip-card.
 *
 * @since 2.0.52
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

export default function FlipCardFaceEdit({ attributes, setAttributes }) {
	const side = attributes.side === 'back' ? 'back' : 'front';

	const blockProps = useBlockProps({
		className: `dsgo-flip-card__face dsgo-flip-card__${side}`,
	});

	const innerBlocksProps = useInnerBlocksProps(blockProps, {
		template: [
			[
				'core/heading',
				{
					content:
						side === 'back'
							? __('Back of Card', 'designsetgo')
							: __('Front of Card', 'designsetgo'),
					level: 2,
					textAlign: 'center',
				},
			],
			[
				'core/paragraph',
				{
					content: __('Add any blocks you want here…', 'designsetgo'),
					align: 'center',
				},
			],
		],
		templateLock: false,
	});

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Face Settings', 'designsetgo')}
					initialOpen={true}
				>
					<SelectControl
						label={__('Side', 'designsetgo')}
						value={side}
						options={[
							{
								label: __('Front', 'designsetgo'),
								value: 'front',
							},
							{
								label: __('Back', 'designsetgo'),
								value: 'back',
							},
						]}
						onChange={(value) => setAttributes({ side: value })}
						help={__(
							'Choose whether this face shows on the front or back of the flip card.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div {...innerBlocksProps} />
		</>
	);
}
